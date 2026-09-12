<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GRA-0117 — TEMPORARY, gated, throttled backfill of missing monthly invoices.
 *
 * WHY: at the V1->V2 cutover the listener that advanced policies' billingStartDate
 * stopped, so PolicyLedgerDaily's full-date-equality selection stopped re-selecting
 * in-force fixed-premium policies and monthly invoicing froze (~Feb 2026) for ~62k
 * policies (products 1/2/4/5). Balance = sum(payments) - sum(invoices), so the
 * missing invoices make those customers wrongly look in-credit / not-in-arrears.
 *
 * WHAT THIS DOES: re-presents a SMALL batch of "behind & safe" policies per run to
 * the EXISTING PolicyLedgerDaily catch-up engine (no new billing logic). That engine
 * anchors at (last invoice_date + 1 month), posts one Invoice triplet per missing
 * month, and is idempotent via the (policy_id,'Invoice',invoice_date) dup-guard, so
 * this is safe to re-run / resume and CANNOT double-post.
 *
 * SAFETY (all enforced below):
 *   - MASTER OFF FLAG: a real run does nothing unless gra0117_backfill_control.enabled = 1.
 *   - Window guard: never runs 03:00-07:59 / 20:00-21:29 BW. BW is computed as UTC+2
 *     (fixed, no DST) so it does NOT depend on container tz data. Bypass with --force.
 *   - --dry-run: read-only preview — resolves the batch + classification, writes NOTHING.
 *   - Small batch per run (--limit) + id cursor => self-paced, resumable.
 *   - RISKY policies are SKIPPED, never written, logged to gra0117_backfill_policies
 *     for a Finance decision:
 *       * Motor Comp (3) / DomCom (7,8) / Hospital (9): excluded at selection.
 *       * null-or-zero premium.
 *       * premium changed: >1 distinct historical Invoice amount.
 *       * never invoiced (0 invoices): no safe anchor — Finance must review.
 *       * abnormal gap: last invoice > 8 months ago (not the Feb-2026 freeze pattern).
 *   - Records ledger id high-water per run for surgical rollback.
 *
 * NOT added to the permanent schedule by default; remove after stale count -> 0.
 */
class PolicyLedgerBackfill extends PolicyLedgerDaily
{
    protected $signature = 'PolicyLedgerBackfill:cron {--limit=200 : safe policies to process this run} {--products=1,2,4,5 : fixed-premium product ids} {--dry-run : preview only, write nothing} {--force : bypass the safe-window guard}';

    protected $description = 'GRA-0117 temporary gated/throttled backfill of missing monthly invoices for stale fixed-premium policies (reuses PolicyLedgerDaily engine; skips risky policies to a Finance report)';

    /** Safe policies resolved this run; reused to scope the orphan passes. */
    protected $batch;

    /** Read-only preview — set from --dry-run; suppresses all writes. */
    protected bool $dryRun = false;

    /** Risky policies seen during this run's scan (for the dry-run summary). */
    protected int $skippedThisRun = 0;

    /** Botswana local time as fixed UTC+2 (no DST; independent of container tz data). */
    protected function bwNow(): Carbon
    {
        return Carbon::now('UTC')->addHours(2);
    }

    public function handle()
    {
        $this->dryRun = (bool) $this->option('dry-run');

        // --- DRY RUN: read-only preview, bypasses both gates -------------------
        if ($this->dryRun) {
            $this->selectPolicies(Carbon::today());
            $n = $this->batch ? $this->batch->count() : 0;
            $msg = "[DRY-RUN] PolicyLedgerBackfill: would process {$n} safe policies this batch; "
                . "{$this->skippedThisRun} skipped (risky -> Finance). No data written.";
            $this->info($msg);
            Log::info($msg);
            return 0;
        }

        // --- MASTER GATE (real runs only) --------------------------------------
        $control = DB::table('gra0117_backfill_control')->where('id', 1)->first();
        if (!$control || (int) $control->enabled !== 1) {
            Log::info('PolicyLedgerBackfill: control flag OFF — no-op.');
            return 0;
        }

        // --- WINDOW GUARD (BW = UTC+2) — skippable with --force ----------------
        // Run ONLY in the low-load, conflict-free window 22:00-00:59 BW: after the
        // 20:40 PolicyLedgerDaily ledger cron has finished, and before the 01:01
        // DPO debit run + the 03:00-08:00 nightly batch + the 03:30 DomCom ledger
        // cron. Avoids both peak-hours write load and ledger-writer collisions.
        if (!$this->option('force')) {
            $hr = (int) $this->bwNow()->format('H');
            if (!in_array($hr, [22, 23, 0], true)) {
                Log::info("PolicyLedgerBackfill: outside low-load window 22:00-00:59 BW (hour={$hr} BW) — skipping this tick.");
                return 0;
            }
        }

        // High-water mark BEFORE this run, for surgical rollback.
        $hwLedger = (int) Ledger::max('id');
        DB::table('gra0117_backfill_control')->where('id', 1)->update([
            'last_run_started_at' => Carbon::now(),
            'last_run_ledger_highwater' => $hwLedger,
        ]);

        $result = parent::handle();

        $this->recordProgress($hwLedger);

        return $result;
    }

    /**
     * Next batch of BEHIND + SAFE policies, by id cursor. Risky policies are
     * logged as 'skipped' and never returned. In --dry-run nothing is persisted.
     */
    protected function selectPolicies(Carbon $today)
    {
        $limit    = max(1, (int) $this->option('limit'));
        $products = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('products')))));
        $products = array_diff($products, [3, 7, 8, 9]); // never auto-backfill Motor Comp / DomCom / Hospital
        $cursor   = (int) (DB::table('gra0117_backfill_control')->where('id', 1)->value('scan_cursor') ?? 0);

        $columns = ['id', 'customer_id', 'product_id', 'plan_id', 'premium_freq',
            'created_at', 'updated_at', 'first_premium', 'premium', 'vat',
            'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated',
            'billingStartDate', 'ori_billingStartDate', 'status'];

        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $candidates = Policy::whereIn('product_id', $products)
            ->where('status', 1)
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($limit * 3)
            ->get($columns);

        $safe = collect();
        $this->skippedThisRun = 0;
        $lastExaminedId = $cursor;

        foreach ($candidates as $p) {
            $lastExaminedId = (int) $p->id;

            // already current this month? nothing to do
            $hasCurrent = Ledger::where('policy_id', $p->id)
                ->where('trans_type', 'Invoice')
                ->where('accounting_date', '>=', $monthStart)
                ->exists();
            if ($hasCurrent) {
                continue;
            }

            $reason = $this->riskReason($p);
            if ($reason !== null) {
                $this->skippedThisRun++;
                if (!$this->dryRun) {
                    $this->logPolicy($p, 'skipped', $reason);
                }
                continue;
            }

            $safe->push($p);
            if (!$this->dryRun) {
                $this->logPolicy($p, 'queued', null);
            }
            if ($safe->count() >= $limit) {
                break;
            }
        }

        if (!$this->dryRun) {
            DB::table('gra0117_backfill_control')->where('id', 1)->update(['scan_cursor' => $lastExaminedId]);
            Log::info("PolicyLedgerBackfill: cursor->{$lastExaminedId}, queued {$safe->count()} safe policies this run.");
        }

        $this->batch = $safe;
        return $safe->chunk(500);
    }

    /** Null if safe to auto-backfill; otherwise the Finance-facing skip reason. */
    protected function riskReason(Policy $p): ?string
    {
        if ((float) ($p->premium ?? 0) <= 0) {
            return 'null-or-zero premium';
        }

        $invQuery = Ledger::where('policy_id', $p->id)->where('trans_type', 'Invoice');

        // never invoiced => no safe anchor; a stale-since-origin policy must be reviewed
        $lastInvoice = (clone $invQuery)->max('accounting_date');
        if ($lastInvoice === null) {
            return 'never invoiced (0 invoices) — Finance review, no safe anchor';
        }

        // abnormal gap => not the Feb-2026 freeze pattern; bounds the blast radius
        if (Carbon::parse($lastInvoice)->diffInMonths(Carbon::now()) > 8) {
            return 'abnormal gap (>8 months since last invoice) — Finance review';
        }

        // premium changed historically => >1 distinct positive Invoice amount
        $distinctAmts = (clone $invQuery)->where('invoice_amount', '>', 0)->distinct()->count('invoice_amount');
        if ($distinctAmts > 1) {
            return 'premium changed (>1 distinct historical invoice amount) — Finance pricing decision';
        }

        return null;
    }

    protected function logPolicy(Policy $p, string $status, ?string $reason): void
    {
        DB::table('gra0117_backfill_policies')->updateOrInsert(
            ['policy_id' => $p->id],
            [
                'policy_number' => $p->policyNumber,
                'product_id'    => $p->product_id,
                'status'        => $status,
                'reason'        => $reason,
                'updated_at'    => Carbon::now(),
                'created_at'    => Carbon::now(),
            ]
        );
    }

    protected function recordProgress(int $hwLedger): void
    {
        $postedThisRun = (int) Ledger::where('id', '>', $hwLedger)->where('trans_type', 'Invoice')->count();
        $totalSkipped  = (int) DB::table('gra0117_backfill_policies')->where('status', 'skipped')->count();

        if ($this->batch && $this->batch->count()) {
            DB::table('gra0117_backfill_policies')
                ->whereIn('policy_id', $this->batch->pluck('id')->all())
                ->where('status', 'queued')
                ->update(['status' => 'done', 'updated_at' => Carbon::now()]);
        }

        $totalDone = (int) DB::table('gra0117_backfill_policies')->where('status', 'done')->count();

        $summary = sprintf(
            'PolicyLedgerBackfill run: invoices posted this run=%d, cumulative done=%d, skipped(for Finance)=%d.',
            $postedThisRun, $totalDone, $totalSkipped
        );
        Log::info($summary);
        DB::table('gra0117_backfill_control')->where('id', 1)->update([
            'last_run_finished_at' => Carbon::now(),
            'last_run_posted' => $postedThisRun,
        ]);
    }

    /** Scope the orphan-payment pass to this batch (base pulls ALL unposted globally). */
    protected function selectOrphanPayments()
    {
        if (!$this->batch || !$this->batch->count()) {
            return collect();
        }
        return PaymentTransaction::whereIn('policyNumber', $this->batch->pluck('policyNumber')->all())
            ->where('is_ledger', 0)->where('amount', '!=', 1)->where('is_refund', 0)->get();
    }

    protected function selectOrphanRefunds()
    {
        if (!$this->batch || !$this->batch->count()) {
            return collect();
        }
        return PaymentTransaction::whereIn('policyNumber', $this->batch->pluck('policyNumber')->all())
            ->where('is_ledger', 0)->where('is_refund', 1)->get();
    }

    protected function cronName(): string
    {
        return 'PolicyLedgerBackfill:cron';
    }

    protected function shouldSendDigestEmail(): bool
    {
        return false;
    }
}
