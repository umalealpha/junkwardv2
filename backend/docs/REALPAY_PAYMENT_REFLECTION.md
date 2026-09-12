# RealPay debit with no Graphite transaction — root cause and fix

**Reported:** RealPay creates the contract and debits the customer, but no
payment/transaction record appears in Graphite. Affects both new policy creation
and payment reprocessing.

**Reported policies:** `MIS2026215341`, `MIS2026215350`, `MIS2026214543`,
`MIS2026215336`.

---

## 1. Reproduction status

The four policies could **not** be inspected from a developer workstation. The
only reachable database (`graphite-test-write…rds.amazonaws.com` / `Graphite_live`,
which `backend/.env` points at) is a clone whose newest MIS policy is
`MIS2026213724`, created 2026-06-20. All four affected policies are numbered
above that, so they exist only on real production.

Read-only checks that *were* run against the clone:

| Check | Result |
|---|---|
| The four policies | absent |
| `webhook_buffer`, last 60 days | 18 rows, all `done`/`processed` — the clone receives no live RealPay traffic |
| Collected instalments with no payment row | 0 (consistent with no live traffic) |
| `payment_transactions.policyNumber` nullability | `NOT NULL` — relevant to RC-3 below |

`database/reconciliation/realpay_reflection_diagnostic.sql` is a read-only pack
to run **on production**, where the data lives. Query 1 is the reproduction:
one row per policy showing RealPay-collected instalments against Graphite
payments. Queries 2–7 separate the failure modes; query 6 sizes the fleet-wide
exposure.

A read-only replica of real production is reachable through the SSH bastion
(`graphite-v2-prod-ro…rds.amazonaws.com` via `15.240.138.54`), but it needs a
DB username/password issued separately by whoever administers bastion access.
That is the channel to run the diagnostic pack through — not `backend/.env`,
which points at the writable primary.

The root causes below are established from the code and are independent of that
data — each is a defect that drops a confirmed debit, and they compound.

---

## 2. Root causes

### RC-1 — the buffer drain recorded failures as successes *(the reason this was permanent)*

`ProcessWebhookBuffer::replay()` returned `$statusCode < 500`.

`RealPayController::updateInstallment()` returns **HTTP 401** from its own
catch-all on *any* exception, and returns **no Response at all** on its
unresolved-policy paths (an implicit 200). Both satisfied `< 500`, so the row
was marked `processed`.

RealPay itself never retries — the `WebhookBuffer` middleware returns 200 to
RealPay on receipt. So the buffer was the only copy of the notification, and a
failed application consumed it. **A collected debit was discarded with no retry,
no `failed` row, and no log line.**

Fixed: only 2xx counts as applied. 4xx is retried and then parked as `failed`,
and a parked row now raises a `Log::error`.

### RC-2 — the payment was addressed by the raw webhook `ClientNumber`

Both branches did `$paymentData['policyNumber'] = $clientNumber` (MIS) /
`$check->domgcomg` (DOM/COM) — the string RealPay sent, not the resolved
policy's own number. Where those differ, the write cannot find the policy:

- `ClientNumber` carrying the `{policyNumber}/{n}` suffix. This shape demonstrably
  occurs in production — `RecoverRealpaySuccessMissingTx` already `strtok`s on
  `/` for exactly this reason.
- `ClientNumber` that is a quote number (`MQ-`/`BQ-`), from a contract created
  before the policy existed. See RC-5.
- A policy resolvable only through `realpay_client_contracts.policy_id`.

Additionally, when no policy resolved at all (`$check == null`), both branches
wrote a `realpay_webhook_response` row and then **fell out of the method
returning nothing** — the implicit 200 that RC-1 then recorded as applied.

### RC-3 — the payment writer failed silently

`PolicyController::updatePaymentTransactions()`:

```php
$policyData = Policy::where('policyNumber', $payTrans->policyNumber)->first();
$payTrans->policy_id = $policyData->id;      // null deref when unresolved
…
} catch (Exception $e) { return false; }     // unreachable
```

Two independent defects:

1. The null dereference is a PHP 8 *warning* yielding `null`; the save then hit
   `policyNumber NOT NULL` and threw a `QueryException`.
2. That catch could never fire. The file declares no `use Exception;` and lives
   in `namespace AlphaDirect\Http\Controllers\Admin`, so `Exception` resolved to
   `AlphaDirect\Http\Controllers\Admin\Exception` — a class that does not exist.
   Every failure escaped to the caller, which turned it into the 401 of RC-1.
   (`\Exception` would not have helped either: an `Error` is not an `Exception`.)

There was no logging on either path.

### RC-4 — no atomicity

Policy activation, the `transactions` row and the `payment_transactions` write
were separate un-transacted saves in that order. A throw partway through left
the policy **activated with no payment record** — precisely the reported state.

### RC-5 — quote-created contracts were never linked to their policy

`Api\Public\RealpayController::initiate()` must create the contract while the
customer is still a quote, so it writes `realpay_client_contracts` with
`client_number = MQ-…` and `policy_id = NULL`. `MaterialiseMotorQuoteJob` never
filled that in. Every later instalment webhook for that contract therefore
carried a `ClientNumber` matching no policy → RC-2 → RC-1.

*(This path mints `MOT-` policy numbers, so it is not the origin of the four
reported MIS policies — but it is the same defect class and produces the same
outcome.)*

### RC-6a — reprocessing: a local write failure was reported as a failed collection

RC-1 to RC-5 are all on the *recurring collection* path. "Collect Now"
(`PayNowService::collectViaRealpay`) is the other way a RealPay debit is
started, and it failed differently.

Once RealPay returns `Successful` for an `InstalmentPostRequest`, the OOFF debit
is accepted and the customer will be debited. Everything after that point —
the `realpay_contract_installments` write, the customer notification — sat
inside the same `try`. Any throw there fell to:

```php
} catch (\Throwable $e) {
    return $this->result(false, 'REALPAY', null, 'RealPay exception: …', …);
}
```

`success = false` then made `collect()` call `releaseSelection()`, putting the
premiums back to outstanding. Net effect of one failed `save()`:

- the customer **is** debited,
- Graphite holds no instalment row, so the later webhook has nothing local to
  attach to and the `realpay:recover-missing-tx` backstop is blind to it,
- the operator is told the collection **failed**, with the premiums showing as
  still owing — an explicit invitation to press Collect Now again and debit a
  second time.

Nothing was written anywhere to say a debit had been accepted.

### RC-6b — reprocessing: no protection across the settlement window

`settleSelection()` parks a RealPay collection's premiums at
`scheduled_transactions.status = 1`, which is in `OUTSTANDING_STATUSES`. They
are therefore selectable again the moment the button returns, while the bank
result is still a day away. `claimSelection()` only holds rows for the duration
of one in-flight call, and `CollectNowController`'s existing guard is a
**2-minute** debounce. Between minute 3 and the webhook landing, a second
Collect Now sent RealPay a second OOFF post for the same premiums — two real
debits, and the UI gave the operator no way to know the first was pending.

### RC-6c — reprocessing: the wrong RealPay merchant for MIS products

Instant MIS products (1, 2, 4, 5, 6, 9, 10 — the family all four reported
policies belong to) have their contracts created on the **START** platform,
merchant 19413. `PolicyRealpayController` switched credentials for this via a
private `INSTANT_PRODUCT_IDS` const; `PayNowService` had no equivalent and read
`config('realpay.*')` — base URL, client auth, merchant, product and version —
unconditionally from the **legacy** merchant. A contract created on START is
not visible to the legacy merchant, so the one-off post could not address it.

This is masked wherever `REALPAY_START_*` env vars are unset, because
`config/realpay.php` then falls START back to the legacy values. It bites
exactly on the environments where the two merchants really are different.

### RC-6 — the safety net was off

`realpay:recover-missing-tx` is commented out of the scheduler (disabled
2026-08-03 after its un-indexed `NOT EXISTS` scan pegged the master). It also
can only see gaps where a **local instalment row exists with status 'S'** — and
`updateInstallment()` only ever *updated* that table, never created rows. A
webhook that could not be attached left no instalment row, so the backstop was
structurally blind to exactly these cases.

---

## 3. The fix

### `AlphaDirect\Services\RealpayPaymentRecorder` (new)

The single writer that turns a RealPay instalment outcome into a payment record.

- **Idempotent** on `InstalmentReferenceNumber`. Buffer replay, RealPay
  redelivery and a reconciliation run converge on one row; an identical
  re-application returns `duplicate` and writes nothing.
- **Atomic** — instalment row and payment row move together.
- **Resolves the policy properly**: contract table (`policy_id`) → `ClientNumber`
  as policy number → `{policyNumber}/{n}` suffix → `ContractNumber` as policy
  number → `{policy_id}/{n}` convention.
- **Writes the policy's own `policyNumber`**, never the raw webhook string.
- **Creates the instalment row when absent**, so a gap is visible to reporting.
- **Never silent**: any failure writes `realpay_reflection_exceptions` with the
  full payload and returns `ok = false`.

### `realpay_reflection_exceptions` (new table)

One open row per debit Graphite failed to reflect, carrying enough payload to
rebuild the payment from that row alone. Unique on
`(instalment_reference, instalment_sequence)` — a repeated failure bumps
`occurrences` rather than growing the table. `resolved_at` is stamped when the
payment is finally written, so the open-item list is a true outstanding list.

### `realpay:reconcile-reflection` (new command)

Answers *"which RealPay debits have no Graphite payment record?"* from two
sources — the exception ledger, and `InstalmentStatus='S'` instalments with no
matching payment — and with `--commit` repairs them through the recorder.

```bash
php artisan realpay:reconcile-reflection                      # report, 30d
php artisan realpay:reconcile-reflection --policy=MIS2026215341
php artisan realpay:reconcile-reflection --commit --days=30   # repair
```

Report-only by default. Repairs are made with `PaymentTransaction` events
suppressed and `request_type = 0`, so a recovered payment does not retro-fire
agent commission, cashback or a customer SMS. **It never calls RealPay and never
initiates a debit** — it only records money that already moved, so it cannot
cause a second charge.

Scheduled daily at 06:15 as a **report** (`--source=exceptions`), which reads the
indexed exception table only — not the un-indexed scan that had to be disabled.
Repair stays a human decision.

### Other changes

| File | Change |
|---|---|
| `ProcessWebhookBuffer` | only 2xx = applied (RC-1); `Log::error` when rows park as `failed` |
| `RealPayController::updateInstallment` | both branches write through the recorder; unresolved instalments are ledgered and return 500 (retryable) instead of a silent 200; catch widened to `\Throwable` with logging, and ledgers the payload before returning |
| `PolicyController::updatePaymentTransactions` | refuses + logs on unresolved policy instead of dereferencing null; `catch (\Throwable)` with logging |
| `MaterialiseMotorQuoteJob` | back-links `realpay_client_contracts.policy_id` at materialisation (RC-5) |

### The reprocessing path (`PayNowService`)

**A point-of-no-return boundary (RC-6a).** Everything after RealPay returns
`Successful` is now isolated. A failure to store the instalment no longer
reports a failed collection; it returns `success = true` with a message that
says the debit was accepted, that it is flagged for reconciliation, and not to
collect again. That keeps `settleSelection()` — not `releaseSelection()` — as
the outcome, which is what stops the retry-driven double debit. The debit also
goes onto `realpay_reflection_exceptions` through the same recorder the webhook
path uses, so one reconciliation report covers both flows. The accepted
instalment is logged with its reference, amount, contract and platform before
any local write is attempted, so the debit is greppable even if the database is
unavailable.

**The instalment row now carries `policy_id`**, so a Collect Now debit is
attributable without re-parsing client-number conventions — the same property
the createContract path already had.

**An in-flight guard (RC-6b).** `realpayDebitInFlight()` refuses a RealPay
collection while an earlier accepted debit has not yet produced a
`payment_transactions` row. It is a settlement check, not a cooldown: once the
webhook records the earlier debit, collecting again is allowed immediately. A
debit with no captured reference is treated as in flight — the safe reading.
Window is `realpay.collect_now_inflight_hours` (default 72; `0` disables). Only
`success` REALPAY events gate it, so a genuinely failed attempt can still be
retried at once, and DPO policies are unaffected (they settle synchronously).

**Platform-correct credentials (RC-6c).** The product→platform rule moved from
a private const on `PolicyRealpayController` to
`RealpayService::platformForProduct()`, and `PayNowService` now resolves OAuth,
base URL, merchant, product and version through
`RealpayService::platformConfig()` for the platform that owns the contract.
FNB's product code stays shared across both platforms (V8 parity). For
non-instant products this resolves identically to the previous behaviour.

**Message accuracy.** The success message said "Payment collected successfully
via RealPay" for an instalment RealPay had only *accepted*. It now says
submitted, with the bank result to follow — matching what `settleSelection()`
already wrote to `scheduled_transactions.reason`.

---

## 4. Acceptance criteria

| Criterion | Status |
|---|---|
| Successful debit → payment record created | Done — recorder, exercised by tests 1–3 |
| Contract creation + payment status mapped to the transaction | Done — `S→SUCCESS`, `F→FAILED`, pending letters settle nothing |
| Duplicate callbacks don't duplicate transactions | Done — keyed on reference; replay test asserts exactly one row |
| Failed creation after a successful debit is logged for reconciliation | Done — `realpay_reflection_exceptions` + `Log::error` |
| System can identify successful-but-unrecorded RealPay payments | Done — `realpay:reconcile-reflection`, diagnostic SQL query 6 |
| Both creation and reprocessing flows covered | Done — one writer serves both webhook branches; the reprocessing path additionally fixed for RC-6a/6b/6c |
| Fix cannot cause duplicate customer debits | Done — nothing in this change calls RealPay; the recorder and the reconcile command only write records. The reprocessing change *removes* a duplicate-debit route (RC-6a/6b) and adds no new call to RealPay |
| **Reproduced on the four policies** | **Blocked** — not present in any database reachable from here (§1). Run the diagnostic pack on production. |

### Test coverage

`tests/Feature/Public/RealpayPaymentReflectionTest.php` — 11 tests, 47
assertions, all passing:

1. plain success writes the payment
2. `/n`-suffixed `ClientNumber` still resolves (the shape that dropped payments)
3. quote-number `ClientNumber` resolves via the contract table
4. unresolvable policy → no payment, exception raised, caller told it failed
5. repeated failures bump one row
6. replayed debit → exactly one payment row
7. existing instalment row updated, not duplicated
8. pending (`W`) settles nothing and raises nothing
9. declined (`F`) recorded as FAILED
10. a later successful delivery closes the open exception
11. a debit with no reference is ledgered rather than written

`tests/Feature/Public/RealpayWebhookReflectionSmokeTest.php` — 7 tests, 53
assertions, all passing. Drives the whole chain the way production does
(`webhook_buffer` → `webhook:process-buffer` → `updateInstallment` → recorder →
`payment_transactions`), asserting on the **buffer row's fate** as much as the
payment's — a delivery marked `processed` without being applied is the bug,
whatever any single class returned:

1. collected debit flows buffer → payment record; instalment marked `S`, policy
   activated, buffer row `processed`
2. unattachable debit — no payment, buffer **not** consumed, open ledger item
3. a delivery that keeps failing is parked `failed`, never silently succeeded
4. three deliveries of one debit → exactly one payment row
5. `realpay:reconcile-reflection` reports the open item, writes nothing; then
   `--commit` repairs it with `is_ledger=0`, `new_payment_date` set and
   `request_type=0` (statement yes, customer SMS no), and closes the item
6. a second `--commit` run does not duplicate the payment
7. the migration creates a usable table (built from the real migration file, so
   the test fails if migration and code drift apart)

**What this smoke test found:** the customer-notification block (SMS, one-time
payment link, email template) runs *after* the payment write but was inside the
same `try`. With the drain now correctly treating non-2xx as unapplied, a
notification failure would return a retryable 500 for a delivery that **had**
been applied — re-driving it and eventually parking a correctly-recorded payment
as `failed`. Both branches' notification blocks are now isolated in their own
try/catch, matching the pattern the audit-log block already used. Idempotency
meant no double-write was ever possible, but the reconciliation signal would
have been noisy.

`tests/Feature/Public/RealpayCollectNowReflectionTest.php` — 9 tests, 32
assertions, all passing. Covers the reprocessing path. No test reaches the
network: `realpay.base_url` is pointed at the discard port, so a RealPay call
fails locally and instantly, and "the guard let this through" is asserted as
*reaching* the RealPay path rather than by mocking it.

1. instant MIS products resolve to START; product 3 / DOMG / COMG stay legacy
2. `platformConfig()` returns each platform's own merchant, FNB product shared
3. a second Collect Now is refused while the first debit is unsettled, and the
   refusal names the reference it is protecting
4. once the earlier debit has a payment row, collection proceeds
5. an accepted debit with no reference blocks (cannot prove it settled)
6. a debit older than the window does not block forever
7. the guard can be disabled by config
8. a *failed* prior attempt never blocks a retry
9. DPO policies are not affected by the RealPay guard

`tests/Feature/Public/RealPayMandateSuccessFlowTest.php` — 20 tests still
passing, unchanged.

**Not covered:** SQLite cannot catch MySQL-specific DDL problems, and there is
no local MySQL on the dev machine (127.0.0.1:3306 refuses connections), so the
migration's DDL must be verified when it first runs in a real environment. Index
name lengths and the composite unique key width (528 bytes, under InnoDB's 767)
were checked by hand.

---

## 5. Deploy order

1. Run the migration (`realpay_reflection_exceptions`) — additive, no
   backfill, nothing reads it until the code deploys.
2. Deploy the code.
3. Run the production diagnostic pack; confirm the four policies and size the
   fleet-wide exposure (query 6).
4. `realpay:reconcile-reflection --days=90` (report) to see the full gap.
5. Spot-check one: `--policy=MIS2026215341 --commit`. Verify the statement.
6. Repair the rest: `--commit --days=90`.
7. Watch `realpay_reflection_exceptions WHERE resolved_at IS NULL` — it should
   stay at or near zero. Anything appearing there is a live gap, visible the
   day it happens rather than when a customer complains.

**Note on step 4/6:** `--source=installments` runs the same `NOT EXISTS` join
that caused the 2026-08-03 master-CPU incident. Both join columns are in fact
indexed (`payment_transactions.idx_reference_number_deleted`,
`realpay_contract_installments.idx_installment_reference`) — the cost is in the
*driving* scan: `realpay_contract_installments` is ~6.4M rows and has **no index
on `created_at`**, and none on `InstalmentStatus` alone, so the `--days` window
cannot be served by an index and degenerates into a full scan. Keep it windowed,
run it off-peak, and add an index on
`realpay_contract_installments (InstalmentStatus, created_at)` before this
source is ever scheduled. The scheduled job added here uses
`--source=exceptions` precisely to avoid it.
