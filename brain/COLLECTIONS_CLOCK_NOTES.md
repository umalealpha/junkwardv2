# Collections non-payment clock — build notes & CFO flag-back

Engine: `brain/lib/collectionsClock.js` (pure clock logic) + `brain/lib/collectionsRo.js`
(read-only SQL + mappers). Arms OFF — the engine only **computes and returns**
candidate policies; it never sends, writes, cancels or debits. Read-only against
the PROD read replica, SELECT-only, throws on error so the caller falls back to a
fixture (mirrors `graphiteRo.js`).

Scope implemented per the CFO rule (2026-07-18, ROUND 1): monthly-pay **DOM +
COM + MIS**; ANNUAL excluded. The clock is blanket: **2 consecutive unpaid/failed
months → deactivate (suspend) at close of the 2nd month → overdue email + 15
calendar days → still unpaid → cancel.**

**RULE START (CFO 2026-07-21): the clock begins 1 July 2026.** Months due before
`RULE_START_DATE = '2026-07-01'` never enter the clock — legacy arrears (incl.
old MIS/DOM/COM deals struck under different terms) are invisible to it, however
long unpaid. Enforced in `classifyPolicy` (the single candidate producer), so the
400-day SQL window still feeds payment history for the GRA-0203 success guard
without old months counting toward suspension. Consequence: the earliest possible
`deactivate_candidate` is the close of **August 2026** (July + August both unpaid);
the candidate list is expected EMPTY before then.

**Timing refinements (CFO 2026-07-21):** a month only counts as missed once it
has fully ENDED (debits run mid/late month — on the 5th that month is still
collectable); deactivation anchors on the LAST DAY of the 2nd unpaid month
(resolves the due-date-vs-close-of-month question: 31 Aug, not 01 Aug); the
cancellation lands ON day 15 of grace (31 Aug + 15 = **15 Sep 2026** for the
first cycle). Also: a MIS policy with ZERO transactions in the window is forced
`signalConfidence: 'uncertain'` ("no payment data") — absence of data is never
proof of non-payment. The old 30-day `collectionWatchdog` is **retired** — this
clock is the single non-payment rule; the sweep (`brains.js`) now consumes clock
inputs, and `graphiteRo`'s naive failed-debit collections feed is superseded by
`collectionsRo` (swap when wiring the live feed).

---

## CFO's explicit question: does each channel give a CLEAN failed-debit signal?

**Short answer: NO channel gives a self-sufficient "this debit failed" flag you
can blindly key off.** The engine therefore never trusts a single stale status.
For MIS it declares a month unpaid only when there is **no SUCCESS on ANY channel**
for that month, and it flips the policy to `signalConfidence: 'uncertain'` the
moment any source shows (or later shows) a success in the disputed window. This is
the direct mitigation for GRA-0203 (~2,237 instalments that SUCCEEDED on RealPay
but still read unpaid/'A' in Graphite).

### RealPay — native per-instalment status, but a large STALE bucket
- Source: `realpay_contract_installments.InstalmentStatus` (join to policy via
  `realpay_client_contracts.contract_number`; the installments table's own
  `policy_id` is unreliable — same caveat the recon command documents).
- Legend (from `AddRealpayWebhookInTransactionLog.php`): **S** success, **F**
  failed, **A** active/pending, **I** cancelled, **W** processing, **R** retry,
  **E** error.
- **NOT clean to key off.** The `A` (active/pending) bucket is ~2.33M rows live.
  GRA-0203 proves a material slice of "unpaid" RealPay instalments actually
  succeeded but never flipped off `A`. So "InstalmentStatus != 'S'" or "== 'A'"
  is **not** a safe "failed" signal.
- How we use it: we pull the **`S` (success) rows only** and fold them into the
  policy's success events. If a RealPay-native success exists that
  `payment_transactions` never recorded, that success (a) prevents the month being
  called unpaid and (b) raises `sourceConflict` → `signalConfidence: 'uncertain'`.
  RealPay's `F` is treated only as *corroboration of a channel*, never as proof of
  non-payment on its own.

### VCS — payment_transactions only, no independent reconciliation feed
- Source: `payment_transactions` where `paymentMethod='VCS'` (~239k rows), status
  `Success`/`Failed`.
- **Reliable when a row is present**, but there is **no separate VCS-native status
  table** discovered to cross-check against, and it is exposed to the same
  posting-lag as RealPay. A `Failed` VCS row with no later success anywhere is our
  best signal; it is marked `clean` only under that "no success on any channel"
  test, otherwise `uncertain`.

### DPO — payment_transactions only, largest channel, no reconciliation feed
- Source: `payment_transactions` where `paymentMethod='DPO'` (~1.84M rows), status
  `Success`/`Failed`. DPO is card/recurring-token; "Collect Now" out-of-cycle
  charges are also DPO.
- Same standing as VCS: trustworthy for **recorded** success/fail, but **no
  independent DPO status source** was found to reconcile against, so a DPO
  "Failed" cannot be independently corroborated. Handled by the same
  success-absence + confidence guard.

**Bottom line for the CFO:** RealPay is the only channel with a native per-debit
status, and that very feed is the one with the proven staleness problem (`A`
bucket / GRA-0203). VCS and DPO have no native reconciliation source at all — they
live only in `payment_transactions`. So the engine keys off **success across all
sources** (absence of any success = missed), and flags every case where a source
disagrees or a later success appears as **`uncertain` for human verification**.
`clean` = safe to action; `uncertain` = verify before any arm is ever wired on.

---

## How the clock decides (pure, in `collectionsClock.js`)

- `consecutiveUnpaidMonths(months, now)` — walks already-due months newest→oldest,
  counting the trailing unpaid run; a paid month resets it. Not-yet-due (future)
  months are ignored (important: some ledgers carry future-dated invoices).
- Deactivation anchors on the **close of the 2nd consecutive unpaid month**
  (`deactivatedAt`); `graceEndsAt = deactivatedAt + 15 calendar days`.
- Stage uses the **real policy state** (arms are off, so nothing was actually
  suspended): Active + ≥2 unpaid → `deactivate_candidate` (suspend + email are the
  pending action); Deactivated within 15 days → `grace`; Deactivated past 15 days →
  `cancel_candidate`. Grace boundary is inclusive (day 15 = grace, day 16 = cancel).
- `< 2` consecutive unpaid → not affected (dropped).

## Data model used (verified live, `Graphite_live` / MariaDB read replica)

- `policies.status` (tinyint): **1 Active, 0 Deactivated, 2 Cancelled, 3 Expired**
  (per `ReconRealpayExceptions.php`). We scan **0 + 1** only (2/3 are terminal).
- Prefixes: `MIS`/`ADH` = Instant (MIS); `DOMG` = Domestic (DOM); `COMG`/`COMD` =
  Commercial (COM).
- `premium_freq` (varchar): `1`/`monthly`/NULL treated as monthly; `3`/`annual`
  excluded; `2` (quarterly), `5`/`6` (other plans) excluded. Most instant policies
  store NULL and are monthly by default.
- DOM/COM: schedule-driven `policy_ledger` (`Invoice` rows vs `Payment` credits),
  allocated FIFO via the existing `ageing.age()` so bulk catch-up payments clear
  the right months. A month is unpaid iff its invoice is still open.
- MIS: `payment_transactions` (status `Success`/`SUCCESSFUL`/`Paid` vs `Failed`,
  `paymentMethod` RealPay/DPO/VCS + one-off Cash/N-Genius/orangeMoney/PayM8). A
  month is paid if **any** channel succeeded that calendar month; multiple failed
  rows in one month are retries → collapse to one month.

## Honest gaps / schema I could not fully confirm

1. **No native VCS or DPO status table** was found — VCS/DPO failed-debit relies
   solely on `payment_transactions.status`. If a reconciliation feed exists for
   them (like RealPay's), it was not located; flagged for follow-up.
2. **RealPay `A` staleness (GRA-0203)** is unresolved at source; the engine works
   around it (success-corroboration + `uncertain`) rather than fixing the feed.
3. **No per-policy debit-schedule table consulted for MIS** — the monthly schedule
   is inferred from `billingStartDate`/`policyActivatedDate` → now. A legitimately
   paused mandate could over-flag; this is why every case is human-verified and the
   confidence guard exists.
4. **Future-dated ledger rows** exist (e.g. `invoice_date`/`accounting_date` in
   2029). The clock ignores not-yet-due months, so these do not cause false
   suspensions, but the schedule is not always clean.
5. **Actual deactivation date is not sourced** (no cheap column). Grace is anchored
   on the computed close of the 2nd unpaid month, not a DB deactivation timestamp.
6. **DPA:** `customerName` is sourced for internal display only and must never be
   sent to any external model; ledger payment refs use the ledger row id, never the
   bank narrative.
7. **Full live pull not executed** — every SQL was validated statement-by-statement
   with tight LIMITs against the live schema, but the unbounded 400-day sweep was
   NOT run (capped read-only account + zero-risk-on-live-data policy). Load-test /
   batch before scheduling `fetchAffectedPolicies` as a daily job.
