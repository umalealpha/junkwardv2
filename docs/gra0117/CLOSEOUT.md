# GRA-0117 — Invoicing Outage: Closeout (root cause, fix, runbook)

**Owner:** ADRisk IT · **Status:** PR-ready, gated OFF · **Branch:** `fix/gra0117-backfill-cron`

## 1. Root cause (verified in source + live data, HIGH confidence)
Monthly invoicing for fixed-premium products **1 (Acc Death), 2 (Third Party), 4 (Legal), 5 (Mobile)** + Motor Comp (3) froze **~Feb 2026** — ~62k active policies, no invoice since. DomCom (7/8) + specialist (16–22) use a separate path and are healthy.

**Mechanism:** `PolicyLedgerDaily::selectPolicies()` re-selects a policy only when `created_at == today` **OR** `billingStartDate == today` (full-date equality). In V1, the schedule/payment listener (`NewScheduleTransactionListener`) **advanced `billingStartDate` forward each cycle**, so policies re-matched monthly. In V2 that advancement is **gone** (listener no longer saves it; the only `addMonth` left is report-display math + commented-out RealPay code). `billingStartDate` froze → selection never re-matched → invoicing stopped. The catch-up engine itself is correct and self-healing **once a policy is re-selected.**

> Both prior AI analyses were wrong (1: "scheduler outage" — it fires fine; 2: "V2 dropped DB-driven re-presentation" — V1 selection is identical). Caught by verifying against live data + V1 source. Do not regress to those.

## 2. Two-track solution (decoupled by deadline)

### Track A — FY board reports correct by **30 June** (no live writes)
The reports read **posted ledger invoices/balances**, which are missing → understated. Fix at the **report layer** in the Reporting portal (`D:\ADRisk\Reporting\reporting-dashboard-v2\backend`, Node/Express/TS) to compute from **policy schedule + actual payments**:
- **Written Premium** (`written-premium.controller.ts:61-98`) — currently filters on `pl.invoice_date`, dropping un-invoiced policies. Replace with schedule basis (see `reports_and_finance_queries.sql` §2).
- **Age Analysis summary** (`age-analysis.controller.ts:111-134` → `summary_age_analyst_*` procs) — balance from expected-invoiced(schedule) − payments (§3).
- **All-policy** + **detailed age analysis** — already ledger-free, **no change**.
- Insertion: new `corrected-reports.service.ts` behind a `?useScheduleCompute=true` flag; return legacy+corrected side-by-side for one validation window, then flip default. **Month counting = whole billing months (`TIMESTAMPDIFF(MONTH)+1`), NOT DATEDIFF/30.**

### Track B — Ledger backfill (permanent, gated, self-paced)
**`PolicyLedgerBackfill:cron`** ([cron/app/Console/Commands/PolicyLedgerBackfill.php](../../cron/app/Console/Commands/PolicyLedgerBackfill.php)) — reuses the proven `PolicyLedgerDaily` engine per policy (no new billing logic). Per tick: master-flag gate, window guard (never 03:00–08:00 / 20:00–21:30 BW), small batch via id cursor (self-paced/resumable), **skips risky policies** (Motor Comp 3 / Hospital 9 / null-premium / premium-changed) to `gra0117_backfill_policies` for Finance, captures ledger high-water for rollback, idempotent via the existing `(policy_id,'Invoice',invoice_date)` dup-guard. Migration: [2026_06_27_000000_create_gra0117_backfill_tables.php](../../backend/database/migrations/2026_06_27_000000_create_gra0117_backfill_tables.php) (control + audit tables, registers the cron **DISABLED**).

## 3. Architect review
- ✅ **No new billing logic** — reuses the validated engine; lowest correctness risk. PHP lints clean.
- ✅ **Idempotent / resumable / reversible** — dup-guard + cursor + per-run high-water rollback (`DELETE WHERE id > hw AND trans_type IN ('Invoice','Invoice Premium','Invoice VAT')`).
- ✅ **Fails safe** — OFF flag default + registered disabled + window guard; does nothing until two gates flipped.
- ⚠️ **One-pass to today.** The cron catches everyone up once; **go-forward** (future months) still needs the permanent selection/listener fix — ship separately, calmly. (Do NOT leave the backfill cron sweeping forever.)
- ⚠️ **cron/ only.** Command lives in the runtime `cron/` copy; `backend/PolicyLedgerDaily` is structurally drifted (no `[7,8]` guard) — a naive backend parity copy is risky, so deliberately omitted. Flag if backend/ ever becomes the deploy source.
- ⚠️ **Scope > validated sample.** CFO validated 23,864 (FY + unposted-payment subset); full safe set is larger. The affected-policy report (§1 SQL) separates `validated_set` vs `additional_set` for re-confirm.

## 4. Runbook (after PR review + Finance sign-off)
1. Deploy code (cron image) — tables created, cron registered **disabled**. No behaviour change.
2. Run affected-policy report SQL → Finance verifies (validated + additional sets). Correct the earlier "gap not growing" note to CFO.
3. Confirm dunning/auto-debit won't fire on back-dated invoices (cron owner: Monika) — pause if needed.
4. RDS snapshot. Set `gra0117_backfill_control.enabled=1`, set cron_kernel `status=1`. Start `--limit` small (e.g. 50), watch one tick + replica lag, then raise.
5. Daily: read `gra0117_backfill_control` (posted/cursor) + `gra0117_backfill_policies` (done/skipped) for progress. Hand the `skipped` list to Finance.
6. When stale count → 0: set flag OFF, cron status=0, and **ship the permanent go-forward fix**; retire this cron + the Track-A `useScheduleCompute` flag.

## 5. Open items (not blockers to merge; blockers to flip-ON)
- CFO record correction (root cause was mis-stated as "scheduler freeze").
- Dunning/auto-debit behaviour on back-dated invoices — confirm + pause.
- Motor Comp (3) re-rating + Hospital (9) pricing decision (the skipped set).
- Track-A TS wiring + live-number validation in the Reporting portal.
- Permanent go-forward fix (restore listener `billingStartDate` advancement, or day-of-month selection).
