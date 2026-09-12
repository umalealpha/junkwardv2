# RealPay mandate tracking — what is implemented, and what still blocks the rest

**Status:** the mandate state layer and the payment-success hook are implemented.
The hosted eMandate / DebiCheck journey is **not** — it is blocked on RealPay (§4).

This file replaces the references that used to point at
`docs/REALPAY_EMANDATE_DEBICHECK.md` and `docs/REALPAY_EMANDATE_TASKS.md`. Those
were untracked working-tree files and are no longer present; the parts the code
depends on are restated here so nothing in the source cites a missing document.

---

## 1. The order the flow actually runs in

```
1. Policy created                                     → status = 0
2. Customer registered with RealPay                   → ClientPostRequest / ClientPutRequest
3. Contract created — this IS the mandate             → ContractPostRequest
4. (DebiCheck only) customer authenticates at bank    → BLOCKED, see §4
5. RealPay collects the first instalment              → on the action date
6. RealPay posts the result to our instalment webhook → InstalmentStatus = 'S'
7. Policy activated                                   → status = 1
```

**The mandate is created before the payment, not after it.** A debit-order
mandate is an authority to collect; the collection follows it. If this is ever
briefed as "create the mandate after a successful payment", that is the wrong way
round and produces collections the bank rejects.

What legitimately happens *after* a confirmed successful payment is:

- **policy activation** (step 7) — already implemented, in
  `RealPayController::updateInstallment()`, and left exactly where it is; and
- **the mandate moving to `active`** — which is a mandate *update*, not a mandate
  *creation*. That is what this change adds.

> An `active` mandate is not an active policy, and mandate completion is **not**
> policy activation. Activation follows the first successful collection. No
> front-end screen may promise otherwise.

---

## 2. Mandate state machine

```
pending ──► registered ──► redirected ──► authenticated ──► active
   │            │              │                │
   │            │              │                └──► cancelled
   │            │              └──► abandoned
   │            └──► rejected
   └──► failed
```

| State | Set when | Policy status |
| --- | --- | --- |
| `pending` | row claimed, before any RealPay call | 0 |
| `registered` | `ContractPostRequest` succeeded | 0 |
| `redirected` | eMandate URL handed to the customer | 0 (unused — §4) |
| `authenticated` | RealPay confirms bank approval | 0 (unused — §4) |
| `active` | first instalment returned `InstalmentStatus == 'S'` | unchanged by this layer |
| `rejected` | customer or bank declined | 0 |
| `abandoned` | no callback within the SLA | 0 |
| `cancelled` | contract cancelled by us or the customer | unchanged |
| `failed` | RealPay call errored or was refused | 0 |

Two live paths into `active`:

- `registered → active` — the flow that runs **today**. This codebase has no
  customer-authentication step, so a registered contract whose first instalment
  comes back `'S'` is proven to have been a live authority.
- `authenticated → active` — the DebiCheck path, once §4 unblocks.

`pending → active` is refused: a mandate with no registered contract cannot have
collected anything. Terminal states (`failed`, `rejected`, `abandoned`,
`cancelled`) have no exits — a fresh attempt takes a new row with an incremented
contract-number suffix.

---

## 3. What was implemented

| Piece | Where |
| --- | --- |
| Mandate + event tables | `database/migrations/2026_08_10_000001_*`, `..._000002_*` |
| State machine | `app/RealpayMandate.php` (`moveTo()`) |
| Append-only event log | `app/RealpayMandateEvent.php` |
| Mandate logic | `app/Services/RealPayMandateService.php` |
| **Payment-success hook** | `RealPayController::applyMandateInstalmentOutcome()`, called from both branches of `updateInstallment()` |
| Claim / register / fail / supersede | `RealPayController::addRealpayPaymentForInstantProduct()` |
| Duplicate-contract guard | same method, top — `guardContractCreation()` |
| Read-only status endpoint | `GET /api/v1/public/realpay/mandate-status` |
| Settings | `config/realpay.php` → `mandate`, `debicheck` |
| Tests | `tests/Feature/Public/RealPayMandateSuccessFlowTest.php` |

### Invariants the tests hold down

- Only `InstalmentStatus == 'S'` runs the success action. `'F'`, `'E'`, `'W'`,
  `'A'`, `'R'`, `'D'`, `'I'` and anything unrecognised do not.
- The mandate layer never writes `policy.status`. There remains exactly one code
  path that can set a policy live.
- A replayed instalment webhook is a no-op — `ProcessWebhookBuffer` replays the
  same payloads every minute.
- A RealPay API failure leaves the mandate `failed`, never looking complete.
- Mandate tracking off, or the tables not yet migrated, is a clean no-op.

### Deliberately not done

`Admin/RealPayController.php` contains roughly fifteen near-identical
contract-creation blocks. Only the one the redo-payment journey actually runs
through (`addRealpayPaymentForInstantProduct`, called by
`PolicyController::redoPaymentFromStart`) is instrumented. Nothing is lost by
that: the instalment-success hook back-fills a mandate row for a contract created
by any uninstrumented path, so mandate state converges on the truth from the
webhook regardless. Instrumenting all fifteen means first de-duplicating them,
which is a separate refactor.

---

## 4. Blocked on RealPay — do not guess these

This codebase talks to RealPay only through `/maintain/clients/{product}` and
`/maintain/contracts/{product}`. The Express/DebiCheck hosted-mandate journey is
absent, and its field names are **not** invented anywhere in this change. Get the
following from RealPay's Express/DebiCheck integration pack first:

1. The endpoint that returns a hosted eMandate URL for a client + contract, with
   its request and response schema.
2. Whether the hosted page *creates* the contract or only *authenticates* the one
   we already posted. This changes the ordering in §1 and is the single most
   important question to settle.
3. The DebiCheck mandate type — TT1 real-time, TT2 delayed or TT3 registered —
   and its approval SLA. Determines how long a customer has to approve, and the
   timeout behaviour.
4. `MaximumCollectionAmount` semantics and the required headroom over the
   instalment amount. This caps what we may ever collect; confirm with Finance
   before changing `realpay.debicheck.max_collection_multiplier`.
5. Whether an amount increase above the authenticated maximum forces
   re-authentication. Getting this wrong means silently unauthorised collections.
6. The mandate webhook — event names, payload, signing method, retry policy.
7. The parameters RealPay appends to the browser return URL.
8. Whether `TrackingCode` `'44'` / `'B3'` carry over to Express unchanged.

The `redirected` / `authenticated` states and the `emandate_*`,
`mandate_type`, `max_collection_amount` columns already exist, so answering the
above adds a service method and a webhook controller — not another migration.

### 4a. Mandate document retrieval (GRA-0351)

Raised separately as "how do we download the signed mandate after successful
authentication". It is the same blocker, so it is asked in the same breath
rather than as its own investigation.

State of play: this codebase calls RealPay only at `/oauth/token`,
`/maintain/clients/{product}`, `/maintain/contracts/{product}` and
`/maintain/instalments/{product}`. There is no document, PDF or file endpoint
of any kind, and `realpay_mandates.emandate_url` is never written — the only
reference to it in `app/` is a date cast on the companion `_expires_at` column.

Note the framing problem before scoping any work: **on the journey that runs
today there is no document and no authentication step.** The mandate is the
`ContractPostRequest` — an authority created API-to-API, which the customer
never signs. Question 9 is therefore the one that decides whether GRA-0351 can
be delivered at all before DebiCheck lands.

9. Whether a mandate document exists for contracts created through
   `/maintain/contracts` — the flow we run today, with no customer
   authentication step — or **only** on the Express/DebiCheck journey.
10. If it exists: the retrieval endpoint with its full request and response
    schema.
11. What identifies the document on that request — `ClientNumber`,
    `ContractNumber`, `ContractSequence`, or a mandate identifier RealPay
    issues. `provider_mandate_id` exists on our side but is never populated,
    so if RealPay issues one we need to know which response field carries it
    and capture it at creation.
12. The response form — PDF binary, base64 inside JSON, or a time-limited URL
    — and the content type returned.
13. Retention: how long the document stays retrievable after authentication,
    and whether it is still retrievable once the contract is cancelled or
    superseded by a new contract-number suffix.
14. Whether it exists for all mandate types (TT1 / TT2 / TT3) or only some, and
    whether an amount increase that forces re-authentication (§4.5) produces a
    new document or replaces the old one.

If the answer to 9 is "DebiCheck only", GRA-0351 is blocked behind §4.1–4.8 and
should be re-sequenced rather than estimated. If the underlying need is
compliance evidence rather than a signed document, we already hold the creation
payload, `realpay_client_contracts.rp_response` and
`realpay_mandates.provider_payload` — that evidences the authority to collect,
and is worth confirming as an interim with whoever raised the ticket.

---

## 5. Settings

| Key | Default | Effect |
| --- | --- | --- |
| `realpay.mandate.enabled` | `true` | Master switch. Writes only to the two new tables; no-ops when they are absent, so shipping ahead of the migration is safe. |
| `realpay.mandate.block_on_active` | `true` | Refuse a second contract when the policy holds an `active` mandate — it has already collected, so another contract is an unambiguous double debit. |
| `realpay.mandate.block_on_registered` | `false` | Same refusal for `registered` / `redirected` / `authenticated`. Off by default: those can be stale if a contract was cancelled outside the paths that report back here, and a false "already active" permanently blocks reprocessing — the failure documented on `RealPayController::contractIsActive()`. Left off, the guard shadow-logs, so the real duplicate rate is measurable before anyone enforces it. |
| `realpay.debicheck.mandate_type` | unset | TT1 / TT2 / TT3 — unconfirmed, see §4.3. |
| `realpay.debicheck.max_collection_multiplier` | `1.0` | Headroom over the instalment. Confirm before changing, see §4.4. |

Every value is read inside `config/realpay.php`. Under
`php artisan config:cache` — standard in production — `env()` returns `null`
outside config files, which is exactly how the START OAuth failure surfaced as
"Could not authenticate with RealPay (START)". Do not add `env()` calls elsewhere.

---

## 6. Deployment order

1. Run the two migrations. Until then the service no-ops and logs one warning.
2. Deploy. `mandate.enabled` defaults on; nothing it writes can affect an
   existing journey.
3. Watch `realpay_mandate_events` and the `[REALPAY MANDATE]` log lines. The
   shadow-mode entries — "duplicate contract would be refused" — measure how
   often a stale `registered` mandate would have blocked a redo before anyone
   turns `block_on_registered` on.
