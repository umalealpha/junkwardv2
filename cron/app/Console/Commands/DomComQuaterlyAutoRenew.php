<?php
namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use AlphaDirect\Models\CronStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use AlphaDirect\Ledger;
use AlphaDirect\CustomerBanking;
use AlphaDirect\SubLedger;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Http\Controllers\CronController;
use Illuminate\Support\Facades\DB;

class DomComQuaterlyAutoRenew extends Command
{
    public $policyAction;

    protected $signature = 'DomComQuaterlyAutoRenew:cron';
    protected $description = 'Dom/Com Quarterly Auto Renew';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * QUARTERLY AUTO-RENEW CRON — ALL CASES HANDLED
     * ─────────────────────────────────────────────────────────────────────────────
     *
     *  THREE control variables:
     *  ───────────────────────────────────────────────────────────────────────────
     *  $renewStartFrom       → cycleStart must be >= this (skip dates before it)
     *                          Set to (anniversaryEnd + 1 day) when anniversary ISSUED
     *
     *  $anniversaryStopDate  → caps cycleEnd only (anniversary boundary)
     *
     *  $hardStopDate         → min(anniversaryStopDate, today) → CREATE gate only
     *
     *  ─────────────────────────────────────────────────────────────────────────
     *  ALL CASES:
     *  [C1]  Cancelled / Lapsed                     → skip entire policy
     *  [C2]  No NEWBUSINESS action                  → skip entire policy
     *  [C3]  Day 1–28                               → no drift, always exact
     *  [C4]  Day 29/30/31 in Feb                    → clamped to 28, bounces back
     *  [C5]  Day 31 in 30-day months                → clamped to 30, bounces back
     *  [C6]  Before backfill cutoff (2025-01-01)    → skipped (continue, not break)
     *  [C7]  cycleStart > today                     → stop loop
     *  [C8]  cycleStart > anniversaryStopDate       → stop loop
     *  [C9]  cycleEnd   > anniversaryStopDate       → cap to anniversaryStopDate
     *  [C10] cycleEnd   > today                     → NOT capped (future end OK)
     *  [C11] Anniversary QUOTE exists               → hardStop = quoteDate − 1
     *  [C12] Anniversary ISSUED                     → renewStartFrom = anniversaryTo + 1
     *  [C13] No anniversary at all                  → stop before first anniversary
     *  [C14] Duplicate — memory check               → skip, continue
     *  [C15] Duplicate — DB safety check            → skip, continue
     *  [C16] REINSTATE in QUOTE                     → skip entire policy
     *  [C17] NEWBUSINESS in QUOTE                   → skip entire policy
     * ─────────────────────────────────────────────────────────────────────────────
     */
    public function handle()
    {
        // Tag every log line with cron name so Cron Logs page filter actually finds them.
        Log::withContext(['cron' => 'DomComQuaterlyAutoRenew:cron']);

        // Last-line-of-defence: catches fatal errors (E_ERROR, E_PARSE, E_COMPILE_ERROR,
        // E_CORE_ERROR, OOM) that escape the try/catch below.
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err !== null && \in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR], true)) {
                Log::error("DomComQuaterlyAutoRenew FATAL: {$err['message']} @ {$err['file']}:{$err['line']}");
            }
        });

        $cron = new CronStatus();
        $cron->name = "DomComQuaterlyAutoRenew:cron";
        $cron->start = Carbon::now();
        $cron->current_step = 'starting';
        $cron->save();
        Log::info("DomComQuaterlyAutoRenew Cron started.");

        try {
            $cron->update(['current_step' => 'fetching_policies']);

            return $this->doHandle($cron);
        } catch (\Throwable $e) {
            // Always close out the cron_status row on failure so the row is
            // observable as "ran but failed" rather than "never finished".
            $cron->update([
                'current_step'  => 'failed',
                'end'           => Carbon::now(),
                'error_message' => substr($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 0, 1000),
            ]);
            Log::error("DomComQuaterlyAutoRenew cron failed: " . $e->getMessage(), [
                'exception' => \get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * The actual renewal work, separated so handle() can wrap it in a
     * single try/catch that guarantees cron_status closure on any failure.
     */
    private function doHandle(CronStatus $cron)
    {
        $today = Carbon::today();

        // ──────────────────────────────────────────────────────────────────────
        // Optimized query: joinSub with MAX(id) — ONE row per policy
        // Combined skip conditions in single whereNotExists
        // ──────────────────────────────────────────────────────────────────────
        $policies = DB::table('policies as p')
            ->select('p.id', 'p.policyNumber')
            ->joinSub(function ($query) {
                $query->from('policy_actions as pa1')
                    ->select('pa1.policy_id', 'pa1.effective_from', 'pa1.effective_to')
                    ->where('pa1.status', 'ISSUED')
                    ->whereIn('pa1.transaction_type', [
                        'NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE'
                    ])
                    ->whereNull('pa1.deleted_at')
                    ->whereRaw("pa1.id = (
                        SELECT MAX(pa2.id) FROM policy_actions pa2
                        WHERE pa2.policy_id = pa1.policy_id
                          AND pa2.status = 'ISSUED'
                          AND pa2.transaction_type IN ('NEWBUSINESS','RENEW','ANNIVERSARY-RENEW','REISSUE','REINSTATE')
                          AND pa2.deleted_at IS NULL
                    )");
            }, 'pa_last', 'pa_last.policy_id', '=', 'p.id')
            ->where('p.premium_freq', 5)              // Quarterly only
            ->whereIn('p.product_id', [7, 8])
            ->where('p.created_at', '>=', '2024-07-01 00:00:00')
            // Last issued action expired before today
            ->whereRaw('pa_last.effective_to < CURDATE()')
            // No current RENEW covering today (truly pending)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as pa_covered')
                    ->whereRaw('pa_covered.policy_id = p.id')
                    ->where('pa_covered.transaction_type', 'RENEW')
                    ->where('pa_covered.status', 'ISSUED')
                    ->whereNull('pa_covered.deleted_at')
                    ->whereRaw('pa_covered.effective_to >= CURDATE()');
            })
            // Combined: skip if latest action is CANCEL+ISSUED, LAPSED, REINSTATE+QUOTE, or NEWBUSINESS+QUOTE
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as pa_check')
                    ->whereRaw('pa_check.policy_id = p.id')
                    ->whereNull('pa_check.deleted_at')
                    ->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('pa_check.transaction_type', 'CANCEL')
                               ->where('pa_check.status', 'ISSUED');
                        })->orWhere('pa_check.status', 'LAPSED')
                        ->orWhere(function ($q3) {
                            $q3->where('pa_check.transaction_type', 'REINSTATE')
                               ->where('pa_check.status', 'QUOTE');
                        })->orWhere(function ($q4) {
                            $q4->where('pa_check.transaction_type', 'NEWBUSINESS')
                               ->where('pa_check.status', 'QUOTE');
                        });
                    })
                    ->whereRaw("pa_check.id = (
                        SELECT MAX(px.id) FROM policy_actions px
                        WHERE px.policy_id = p.id AND px.deleted_at IS NULL
                    )");
            })
            ->orderByDesc('p.id')
            ->get();
           // dd(($policies));
        // Capture count BEFORE the loop — inside the loop $policies gets
        // reassigned to a single Policy model via Policy::find($policy->id),
        // which shadows the outer collection. Without this capture the SUMMARY
        // log below would crash with "count(): Argument must be Countable|array,
        // AlphaDirect\Policy given".
        $totalPoliciesCount = \count($policies);

        Log::info("DomComQuaterlyAutoRenew: Eligible policies: {$totalPoliciesCount}");

        $cron->update(['current_step' => 'processing', 'processedCount' => 0]);

        $finalData = [];
        $backfillFrom = Carbon::create(2025, 1, 1);
        $createdCountTotal = 0;
        $skippedCountTotal = 0;

        foreach ($policies as $policy) {

            // ──────────────────────────────────────────────────────────────────
            // [C1] Skip cancelled / lapsed (PHP safety — already filtered in SQL)
            // ──────────────────────────────────────────────────────────────────
            $latestAction = PolicyAction::where('policy_id', $policy->id)
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$latestAction) {
                Log::warning("Policy {$policy->id}: No action found, skipping.");
                continue;
            }

            // [C1] Lapsed — skip outright.
            if ($latestAction->status === 'LAPSED') {
                Log::info("Policy {$policy->id}: Lapsed — skip.");
                continue;
            }

            // [C1] Cancelled-policy stop (GRA-0132). A bogus RENEW issued after a
            // cancel becomes the latest action and hides the cancel, so the policy
            // renews forever. Find the latest ISSUED CANCEL and skip unless a
            // REINSTATE/REISSUE was ISSUED *after* it.
            $latestCancel = PolicyAction::where('policy_id', $policy->id)
                ->where('status', 'ISSUED')
                ->where('transaction_type', 'CANCEL')
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestCancel) {
                $reinstatedAfterCancel = PolicyAction::where('policy_id', $policy->id)
                    ->where('status', 'ISSUED')
                    ->whereIn('transaction_type', ['REINSTATE', 'REISSUE'])
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($latestCancel) {
                        // Chronologically after the stop: later effective date, or the
                        // same date with a higher id. Legacy rows with no effective_from
                        // fall back to creation order so the guard stays clearable.
                        if (empty($latestCancel->effective_from)) {
                            $q->where('id', '>', $latestCancel->id);
                            return;
                        }
                        $q->whereDate('effective_from', '>', $latestCancel->effective_from)
                          ->orWhere(function ($same) use ($latestCancel) {
                              $same->whereDate('effective_from', '=', $latestCancel->effective_from)
                                   ->where('id', '>', $latestCancel->id);
                          });
                    })
                    ->exists();

                if (!$reinstatedAfterCancel) {
                    Log::info("Policy {$policy->id}: Cancelled (no reinstate after cancel) — skip.");
                    continue;
                }
            }

            // [C1b] Lapsed-policy stop. A LAPSED action — typically an ANNIVERSARY-RENEW
            // the customer never took up — ends cover at its effective_from. The
            // single-latest-action LAPSED test is defeated once a renewal wrongly stacks
            // on top of the lapse: that RENEW becomes the latest action and hides the
            // LAPSED row forever. Only a REINSTATE/REISSUE can revive lapsed cover —
            // never a RENEW. Find the latest LAPSED action and skip unless a
            // REINSTATE/REISSUE was ISSUED *after* it.
            $latestLapsed = PolicyAction::where('policy_id', $policy->id)
                ->where('status', 'LAPSED')
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestLapsed) {
                $revivedAfterLapse = PolicyAction::where('policy_id', $policy->id)
                    ->where('status', 'ISSUED')
                    ->whereIn('transaction_type', ['REINSTATE', 'REISSUE'])
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($latestLapsed) {
                        // Chronologically after the stop: later effective date, or the
                        // same date with a higher id. Legacy rows with no effective_from
                        // fall back to creation order so the guard stays clearable.
                        if (empty($latestLapsed->effective_from)) {
                            $q->where('id', '>', $latestLapsed->id);
                            return;
                        }
                        $q->whereDate('effective_from', '>', $latestLapsed->effective_from)
                          ->orWhere(function ($same) use ($latestLapsed) {
                              $same->whereDate('effective_from', '=', $latestLapsed->effective_from)
                                   ->where('id', '>', $latestLapsed->id);
                          });
                    })
                    ->exists();

                if (!$revivedAfterLapse) {
                    Log::info("Policy {$policy->id}: Lapsed (no reinstate after lapse) — skip.");
                    continue;
                }
            }

            // [C16] REISSUE / REINSTATE in QUOTE — don't renew until re-issued
            if (in_array($latestAction->transaction_type, ['REINSTATE', 'REISSUE'], true) && $latestAction->status === 'QUOTE') {
                Log::info("Policy {$policy->id}: {$latestAction->transaction_type} in QUOTE — skip.");
                continue;
            }

            // [C17] NEWBUSINESS in QUOTE — policy not yet issued
            if ($latestAction->transaction_type === 'NEWBUSINESS' && $latestAction->status === 'QUOTE') {
                Log::info("Policy {$policy->id}: NEWBUSINESS in QUOTE — skip.");
                continue;
            }

            // ──────────────────────────────────────────────────────────────────
            // [C2] NEWBUSINESS — source of truth for dates
            // ──────────────────────────────────────────────────────────────────
            $nbAction = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'NEWBUSINESS')
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->first();

            if (!$nbAction) {
                Log::warning("Policy {$policy->id}: No NEWBUSINESS action — skip.");
                continue;
            }

            $policyStartDate = Carbon::parse($nbAction->effective_from);
            $originalDay     = (int) $policyStartDate->day;
            $nbMonth         = (int) $policyStartDate->month;
            $nbYear          = (int) $policyStartDate->year;

            Log::info("Policy {$policy->id}: NB = {$policyStartDate->format('Y-m-d')}, originalDay = {$originalDay}");

            // ──────────────────────────────────────────────────────────────────
            // A REINSTATE / REISSUE resets the coverage cadence: everything ISSUED
            // *before* it is superseded history. A stale annual ANNIVERSARY-RENEW
            // that a later quarterly reinstate replaced still spans its whole year
            // — left in play it re-anchors the schedule and blocks (via [C12b] /
            // renewStartFrom) every pending quarter forever. Ignore anniversaries /
            // prior periods issued before the latest reinstate so the cadence
            // follows the live reinstate/renew chain. Null when never reinstated
            // → every lookup below is unchanged.
            // ──────────────────────────────────────────────────────────────────
            $supersedeAfterId = PolicyAction::where('policy_id', $policy->id)
                ->whereIn('transaction_type', ['REINSTATE', 'REISSUE'])
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->max('id');

            // ──────────────────────────────────────────────────────────────────
            // Latest Anniversary
            // ──────────────────────────────────────────────────────────────────
            $latestAnniversary = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->whereNull('deleted_at')
                ->when($supersedeAfterId, fn ($q) => $q->where('id', '>=', $supersedeAfterId))
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            $hasAnniversaryQuote  = $latestAnniversary && $latestAnniversary->status === 'QUOTE';
            $hasAnniversaryIssued = $latestAnniversary && $latestAnniversary->status === 'ISSUED';

            // Earliest PENDING anniversary QUOTE — the schedule's ceiling, applied
            // after the branches below. Read directly instead of relying on the
            // status of the latest anniversary row: when a later-dated anniversary
            // of another status exists, $hasAnniversaryQuote is false and the
            // [C12]/[C13] branches let the batch run straight through a quote
            // still awaiting UW.
            $pendingAnniversaryQuote = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->where('status', 'QUOTE')
                ->whereNull('deleted_at')
                ->when($supersedeAfterId, fn ($q) => $q->where('id', '>=', $supersedeAfterId))
                ->orderBy('effective_from')
                ->first();

            // ──────────────────────────────────────────────────────────────────
            // Last ISSUED action (for premium reference — not date logic)
            // ──────────────────────────────────────────────────────────────────
            $lastIssuedAction = PolicyAction::where('policy_id', $policy->id)
                ->whereIn('transaction_type', [
                    'NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE'
                ])
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            $anchorDate = $lastIssuedAction
                ? Carbon::parse($lastIssuedAction->effective_from)
                : $policyStartDate->copy();

            // ──────────────────────────────────────────────────────────────────
            // Next anniversary boundary (always calculated from NB)
            // ──────────────────────────────────────────────────────────────────
            $yearsFromStart = 1;
            while ($policyStartDate->copy()->addYearsNoOverflow($yearsFromStart)->lte($anchorDate)) {
                $yearsFromStart++;
            }
            $nextAnniversaryDate = $policyStartDate->copy()->addYearsNoOverflow($yearsFromStart);

            // ──────────────────────────────────────────────────────────────────
            // [C11–C13] Calculate the three control variables
            // ──────────────────────────────────────────────────────────────────
            $anniversaryStart = null;
            $anniversaryEnd   = null;

            if ($hasAnniversaryQuote) {
                // [C11] QUOTE pending → stop quarterly RENEWs just before it
                $anniversaryStopDate = Carbon::parse($latestAnniversary->effective_from)->subDay();
                $renewStartFrom      = $backfillFrom->copy();

                Log::info("Policy {$policy->id}: Anniversary QUOTE — anniversaryStop = {$anniversaryStopDate->format('Y-m-d')}");

            } elseif ($hasAnniversaryIssued) {
                // [C12] Anniversary ISSUED → two windows:
                //   Before anniversary → allow, cap cycleEnd at anniversaryStart - 1
                //   Inside anniversary → skip
                //   After anniversary  → allow
                $anniversaryStart    = Carbon::parse($latestAnniversary->effective_from);
                $anniversaryEnd      = Carbon::parse($latestAnniversary->effective_to);
                $renewStartFrom      = $backfillFrom->copy();

                // Next anniversary boundary follows the ISSUED anniversary's
                // (possibly UW-changed) start date, not the original NB
                // schedule — otherwise a date change on the anniversary cuts
                // the last re-anchored cycle short.
                $yearsFromAnniv = 1;
                while ($anniversaryStart->copy()->addYearsNoOverflow($yearsFromAnniv)->lte($anchorDate)) {
                    $yearsFromAnniv++;
                }
                $nextAnniversaryDate = $anniversaryStart->copy()->addYearsNoOverflow($yearsFromAnniv);
                $anniversaryStopDate = $nextAnniversaryDate->copy()->subDay();

                Log::info(
                    "Policy {$policy->id}: Anniversary ISSUED — " .
                    "anniversaryStart = {$anniversaryStart->format('Y-m-d')}, " .
                    "anniversaryEnd = {$anniversaryEnd->format('Y-m-d')}, " .
                    "anniversaryStop = {$anniversaryStopDate->format('Y-m-d')}"
                );

            } else {
                // [C13] No anniversary at all → stop before first anniversary
                $anniversaryStopDate = $nextAnniversaryDate->copy()->subDay();
                $renewStartFrom      = $backfillFrom->copy();

                Log::info("Policy {$policy->id}: No anniversary — anniversaryStop = {$anniversaryStopDate->format('Y-m-d')}");
            }

            // ──────────────────────────────────────────────────────────────────
            // [C11b] A pending anniversary QUOTE closes the schedule, whichever
            // branch above set the stop date. Nothing may be created on/after its
            // start date until UW issues it — otherwise quarterly RENEWs stack on
            // top of a quote the customer has not taken up yet.
            // ──────────────────────────────────────────────────────────────────
            if ($pendingAnniversaryQuote) {
                $quoteStopDate = Carbon::parse($pendingAnniversaryQuote->effective_from)->subDay();
                if ($anniversaryStopDate->gt($quoteStopDate)) {
                    $anniversaryStopDate = $quoteStopDate;
                    Log::info("Policy {$policy->id}: pending ANNIVERSARY-RENEW QUOTE #{$pendingAnniversaryQuote->id} — anniversaryStop capped to {$anniversaryStopDate->format('Y-m-d')}.");
                }
            }

            // ──────────────────────────────────────────────────────────────────
            // [C18] An ISSUED anniversary re-anchors the cycle dates. When UW
            // changes the period of insurance on the anniversary renewal, all
            // later RENEWs must follow the anniversary's day, not the original
            // NEWBUSINESS day (e.g. anniversary issued 01/04–30/06 → next renew
            // 01/07–30/09, NOT 26/07–25/10 from a NB on the 26th). Applies even
            // when a newer anniversary QUOTE is pending — cycles between the
            // issued anniversary and that quote still follow the issued dates.
            // ──────────────────────────────────────────────────────────────────
            $latestIssuedAnniversary = $hasAnniversaryIssued
                ? $latestAnniversary
                : PolicyAction::where('policy_id', $policy->id)
                    ->where('transaction_type', 'ANNIVERSARY-RENEW')
                    ->where('status', 'ISSUED')
                    ->whereNull('deleted_at')
                    ->when($supersedeAfterId, fn ($q) => $q->where('id', '>=', $supersedeAfterId))
                    ->orderBy('effective_from', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

            if ($latestIssuedAnniversary) {
                $cycleAnchor = Carbon::parse($latestIssuedAnniversary->effective_from);
                $originalDay = (int) $cycleAnchor->day;
                $nbMonth     = (int) $cycleAnchor->month;
                $nbYear      = (int) $cycleAnchor->year;

                Log::info("Policy {$policy->id}: [C18] cycle anchor re-based to issued anniversary {$cycleAnchor->format('Y-m-d')}.");
            }

            // [C17] If NB was annual (or any longer freq), don't create quarterly RENEWs inside the NB period
            // But if there's a REINSTATE or existing RENEW after NB, use that as the boundary instead
            $latestIssuedAfterNB = PolicyAction::where('policy_id', $policy->id)
                ->whereIn('transaction_type', ['REINSTATE', 'RENEW', 'ANNIVERSARY-RENEW'])
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->where('id', '>', $nbAction->id)
                // Ignore periods superseded by a later reinstate/reissue so the
                // boundary follows the live chain, not a stale annual anniversary.
                ->when($supersedeAfterId, fn ($q) => $q->where('id', '>=', $supersedeAfterId))
                ->orderByDesc('effective_to')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($latestIssuedAfterNB) {
                // Policy was reinstated or already has renewals — start after the latest one
                $adjustedStart = Carbon::parse($latestIssuedAfterNB->effective_to)->addDay();
                if ($renewStartFrom->lt($adjustedStart)) {
                    Log::info("Policy {$policy->id}: renewStartFrom adjusted to {$adjustedStart->format('Y-m-d')} (after latest ISSUED {$latestIssuedAfterNB->transaction_type} #{$latestIssuedAfterNB->id}).");
                    $renewStartFrom = $adjustedStart->copy();
                }
            } else {
                // No reinstate/renew — use NB end as boundary
                $nbEndPlusOne = Carbon::parse($nbAction->effective_to)->addDay();
                if ($renewStartFrom->lt($nbEndPlusOne)) {
                    Log::info("Policy {$policy->id}: renewStartFrom {$renewStartFrom->format('Y-m-d')} < NB end+1 {$nbEndPlusOne->format('Y-m-d')} — adjusting.");
                    $renewStartFrom = $nbEndPlusOne->copy();
                }
            }

            // hardStopDate = min(anniversaryStopDate, today) — CREATE gate only
            $hardStopDate = $anniversaryStopDate->copy();
            if ($hardStopDate->gt($today)) {
                $hardStopDate = $today->copy();
            }

            Log::info("Policy {$policy->id}: hardStop (CREATE gate) = {$hardStopDate->format('Y-m-d')}");

            // ──────────────────────────────────────────────────────────────────
            // [C14] Load existing RENEW quarters for in-memory duplicate check
            // ──────────────────────────────────────────────────────────────────
            $existingRenews = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'RENEW')
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->get()
                ->map(fn($pa) => Carbon::parse($pa->effective_from)->format('Y-m-d'))
                ->toArray();

            // ──────────────────────────────────────────────────────────────────
            // Quarterly cycle loop (3-month increments from NB)
            // ──────────────────────────────────────────────────────────────────
            $createdCount = 0;
            $skippedCount = 0;

            // Step in 3-month increments: offset 3, 6, 9, 12, 15, ...
            for ($offset = 3; $offset <= 300; $offset += 3) {

                // ── [C3–C5] Calculate cycleStart from NB (manual math, no overflow) ──
                $totalMonths = ($nbYear * 12 + $nbMonth - 1) + $offset;
                $year        = intdiv($totalMonths, 12);
                $month       = ($totalMonths % 12) + 1;

                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                $day         = min($originalDay, $daysInMonth); // bounces back next quarter
                $cycleStart  = Carbon::create($year, $month, $day);

                // ── [C6] Skip before backfill cutoff (continue, not break) ──
                if ($cycleStart->lt($backfillFrom)) {
                    continue;
                }

                // ── [C12] Skip dates before renewStartFrom ──
                if ($cycleStart->lt($renewStartFrom)) {
                    Log::info("Policy {$policy->id}: SKIP offset {$offset} ({$cycleStart->format('Y-m-d')}) — before renewStartFrom.");
                    continue;
                }

                // ── [C12b] Skip cycles that start INSIDE an ISSUED anniversary period ──
                if ($hasAnniversaryIssued && $anniversaryStart !== null && $anniversaryEnd !== null
                    && $cycleStart->gte($anniversaryStart) && $cycleStart->lte($anniversaryEnd)) {
                    Log::info("Policy {$policy->id}: SKIP offset {$offset} ({$cycleStart->format('Y-m-d')}) — inside anniversary period.");
                    continue;
                }

                // ── [C7] Stop if cycleStart is beyond today ──
                if ($cycleStart->gt($today)) {
                    Log::info("Policy {$policy->id}: STOP — cycleStart {$cycleStart->format('Y-m-d')} > today.");
                    break;
                }

                // ── [C8] Stop if cycleStart is beyond anniversary boundary ──
                if ($cycleStart->gt($anniversaryStopDate)) {
                    Log::info("Policy {$policy->id}: STOP — cycleStart {$cycleStart->format('Y-m-d')} > anniversaryStop {$anniversaryStopDate->format('Y-m-d')}.");
                    break;
                }

                // ── [C3–C5] Natural cycleEnd = next quarter start − 1 day ──
                $totalMonthsEnd = ($nbYear * 12 + $nbMonth - 1) + $offset + 3;
                $eyear          = intdiv($totalMonthsEnd, 12);
                $emonth         = ($totalMonthsEnd % 12) + 1;
                $edaysInMonth   = Carbon::create($eyear, $emonth, 1)->daysInMonth;
                $eday           = min($originalDay, $edaysInMonth);
                $nextCycleStart = Carbon::create($eyear, $emonth, $eday);
                $cycleEnd       = $nextCycleStart->copy()->subDay();

                // ── [C9] Cap cycleEnd at anniversary boundary ONLY (NOT today) ──
                if ($cycleEnd->gt($anniversaryStopDate)) {
                    $cycleEnd = $anniversaryStopDate->copy();
                }

                // ── [C12c] For cycles BEFORE issued anniversary: cap cycleEnd at anniversaryStart - 1 ──
                if ($hasAnniversaryIssued && $anniversaryStart !== null
                    && $cycleStart->lt($anniversaryStart) && $cycleEnd->gte($anniversaryStart)) {
                    $cycleEnd = $anniversaryStart->copy()->subDay();
                }

                $effectiveFrom = $cycleStart->format('Y-m-d');
                $effectiveTo   = $cycleEnd->format('Y-m-d');

                // ── [C14] Duplicate check — memory ──
                if (in_array($effectiveFrom, $existingRenews)) {
                    $skippedCount++;
                    Log::info("Policy {$policy->id}: SKIP {$effectiveFrom} — exists in memory.");
                    continue;
                }

                // ── [C15] Duplicate check — DB safety net ──
                $existsInDb = PolicyAction::where('policy_id', $policy->id)
                    ->where('transaction_type', 'RENEW')
                    ->whereDate('effective_from', $effectiveFrom)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($existsInDb) {
                    $skippedCount++;
                    Log::info("Policy {$policy->id}: SKIP {$effectiveFrom} — DB found existing.");
                    continue;
                }

                // ── Source for action + replication + premium ──
                // ISSUED action with the LATEST effective_from before this
                // renewal (any transaction type), tie-broken by id, so the
                // renewal carries forward the operative coverage set & premium
                // — a UW-adjusted anniversary or a mid-term endorsement —
                // instead of reverting to the original NEWBUSINESS figures.
                // Falls back to NB when nothing else is issued yet.
                //
                // Order by effective_from, NOT plain id-desc: a batch deleted &
                // recreated (or re-issued) later carries a HIGHER id but may
                // hold an EARLIER period, so id-desc would source from it
                // instead of the true latest period (e.g. the anniversary).
                $referenceAction = PolicyAction::where('policy_id', $policy->id)
                    ->where('status', 'ISSUED')
                    ->whereNull('deleted_at')
                    ->whereDate('effective_from', '<', $effectiveFrom)
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id')
                    ->first() ?? $nbAction;

                $quoteDay      = str_pad($originalDay, 2, '0', STR_PAD_LEFT);
                $policyQuoteNo = $policy->policyNumber . '/' . $quoteDay;

                // ── Create the quarterly renewal ──
                $action = $this->createPolicyActionFromPrevious(
                    $referenceAction,
                    'RENEW',
                    'ISSUED',
                    $policyQuoteNo,
                    $effectiveFrom,
                    $effectiveTo
                );

                PolicyAction::newPolicyActionReplace($action, $referenceAction->id);
                // Carry the source's ISSUED premium VERBATIM (full-period source),
                // matching the app. Re-summing the replicated tree diverged from
                // the issued figure (and collapsed to a fraction when replication
                // truncated), producing wrong premiums/invoices on renewal.
                PolicyAction::setRenewPremiumFromSource($action->id, $referenceAction);

                // Reload action to get calculated premium
                $action->refresh();

                // Generate invoice only if premium > 0
                if ($action->premium > 0) {
                    $this->generateInvoice($policy->id, $action->id, $effectiveFrom);
                } else {
                    Log::info("Policy {$policy->id}: Premium is 0 — skipping invoice for {$effectiveFrom}.");
                }

                $createdCount++;

                // Add to memory so next iteration won't duplicate
                $existingRenews[] = $effectiveFrom;

                $finalData[] = [
                    'policyNumber' => $policy->policyNumber,
                    'issuedDate'   => $cycleStart->format('d-m-Y'),
                    'totalpolicy'  => 1,
                ];
                $policies = Policy::find($policy->id);

                 activity('Quaterly Auto Renewal')
                ->performedOn($policies)
                ->log('Quaterly renewal - '. $policy->policyNumber.' - '.$effectiveFrom.' to '.$effectiveTo);
        

                Log::info(
                    "Policy {$policy->id}: CREATED {$effectiveFrom} → {$effectiveTo} " .
                    "(originalDay: {$originalDay}, clampedDay: {$day}, offset: {$offset})"
                );
            }

            Log::info("Policy {$policy->id}: DONE — Created: {$createdCount}, Skipped: {$skippedCount}");
            $createdCountTotal += $createdCount;
            $skippedCountTotal += $skippedCount;
        }

        Log::info(
            "DomComQuaterlyAutoRenew: batch SUMMARY — processed {$totalPoliciesCount}" .
            " polic(ies), createdTotal={$createdCountTotal}, skippedTotal={$skippedCountTotal}, finalDataRows=" . \count($finalData)
        );

        // ──────────────────────────────────────────────────────────────────────
        // PDF report and email
        // ──────────────────────────────────────────────────────────────────────
        if (!empty($finalData)) {
            $cron->update(['current_step' => 'sending_email']);
            $date = Carbon::now()->timestamp;
            $path = 'Policy/created-' . $date . '/PoliciesRenewQuarterly.pdf';
            $data = ['finalData' => $finalData];
            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.daily_renew_quaterly_policy', $data);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');

            $attachments = [$path];
            $cronSendMail = new CronController();
            $hook = 'Policies_quaterly_renew_today';
            $cronSendMail->AllCronMail($attachments, $hook, $cron);
        }

        $cron->update([
            'current_step' => 'completed',
            'end'          => Carbon::now(),
        ]);

        Log::info("DomComQuaterlyAutoRenew Cron completed. Total renewals in report: " . \count($finalData));
        $this->info("Quarterly policy renewal task completed. Renewals: " . \count($finalData));
        return 0;
    }

    protected function findReferenceActionForRenewal($policyId, $startMonth)
    {
        $types = [
            'ANNIVERSARY-RENEW',
            'RENEW',
            'NEWBUSINESS',
            'REISSUE',
            'REINSTATE',
        ];

        foreach ($types as $type) {
            $action = PolicyAction::where('policy_id', $policyId)
                ->where('status', 'ISSUED')
                ->where('transaction_type', $type)
                ->whereNull('deleted_at')
                ->where('effective_from', '<', $startMonth)
                ->orderByRaw("ABS(DATEDIFF(effective_from, ?))", [$startMonth])
                ->orderByDesc('id')
                ->first();

            if ($action) {
                return $action;
            }
        }

        return PolicyAction::where('policy_id', $policyId)
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    protected function createPolicyActionFromPrevious($previousAction, $transactionType, $status, $policyQuoteNo, $startMonth, $endMonth)
    {
        return PolicyAction::create([
            'policy_id' => $previousAction->policy_id,
            'term_id' => $previousAction->term_id,
            'premium' => $previousAction->premium + $previousAction->endorse_premium,
            'transaction_type' => $transactionType,
            'policy_quote_no' => $policyQuoteNo,
            'effective_from' => $startMonth,
            'effective_to' => $endMonth,
            'status' => $status,
            'transaction_date' => $startMonth,
        ]);
    }

    public function generateInvoice($policyid, $actionId, $invoicedate)
    {
        try {
            $date = Carbon::now()->format('Y-m-d');

            $policy = Policy::whereIn('product_id', [7, 8])
                ->where('id', $policyid)
                ->select('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq',
                    'created_at', 'updated_at', 'first_premium', 'annual_premium',
                    'premium', 'vat', 'vat_percent', 'policyNumber',
                    'policyActivatedDate', 'is_sys_act_generated',
                    'billingStartDate', 'status')
                ->first();

            if (!$policy) {
                Log::info("generateInvoice: Policy {$policyid} not found.");
                return;
            }

            $policyAction = PolicyAction::where('policy_id', $policyid)
                ->where('id', $actionId)
                ->where('status', 'ISSUED')
                ->first();

            if (!$policyAction) {
                Log::info("generateInvoice: PolicyAction {$actionId} not found.");
                return;
            }

            $termId = $policyAction->term_id ?? 0;

            // Check if invoice already exists for this action
            $check_invoice_exists = Ledger::where('policy_id', $policyid)
                ->where('trans_type', 'Invoice')
                ->where('action_id', $actionId)
                ->whereNull('deleted_at')
                ->count();

            if ($check_invoice_exists > 0) {
                Log::info("generateInvoice: Invoice already exists for policy {$policyid}, action {$actionId}.");
                return;
            }

            // Get last invoice info
            $ledger = Ledger::where('policy_id', $policyid)
                ->orderBy('id', 'DESC')
                ->where('trans_type', 'Invoice')
                ->first(['invoice_no', 'banking_id', 'invoice_date']);

            if ($ledger != null) {
                $invoice_no = $ledger->invoice_no;
                $invoice_no++;
                $banking_id = $ledger->banking_id;
            } else {
                $invoice_no = $policy->policyNumber . '-' . sprintf('%03d', 1);
                $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->value('id');
            }

            // Get current balance
            $balance = Ledger::where('policy_id', $policy->id)
                ->orderBy('id', 'DESC')
                ->value('balance') ?? 0;

            $data = [];
            $subData = [];

            // ─── PREMIUM ───
            $record = [];
            $record['customer_id'] = $policy->customer_id;
            $record['account_id'] = null;
            $record['policy_id'] = $policy->id;
            $record['term_id'] = $termId;
            $record['action_id'] = $actionId;
            $record['claim_id'] = null;
            $record['banking_id'] = $banking_id;
            $record['account_name'] = null;
            $record['accounting_date'] = Carbon::parse($date);
            $record['trans_type'] = 'Invoice Premium';
            $record['amount_type'] = null;
            $record['trans_ref'] = null;
            $record['orig_trans'] = null;
            $record['unallocated'] = null;
            $record['system_date'] = Carbon::parse($date);
            $record['trans_sub_type'] = null;
            $record['eff_date'] = Carbon::parse($date);
            $record['invoice_file'] = null;
            $record['invoice_date'] = null;
            $record['invoice_no'] = null;
            $record['invoice_amount'] = null;
            $record['premium'] = $policyAction->premium;
            $record['other_charges'] = null;
            $record['due_amount'] = null;
            $record['pmts_adjust'] = null;
            $record['due_date'] = null;
            $record['status'] = 'Pending';
            $record['credit'] = null;

            $amt = str_replace(',', '', number_format(((float) $policyAction->premium - (float) $policy->vat), 2));
            $record['debit'] = str_replace(',', '', $amt);
            $balance = number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2);
            $record['balance'] = str_replace(',', '', $balance);
            $data[] = $record;

            // SUB-LEDGER Premium
            $subRecord = [];
            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = null;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['term_id'] = $termId;
            $subRecord['action_id'] = $actionId;
            $subRecord['claim_id'] = null;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'Insurance Sales A/C';
            $subRecord['accounting_date'] = Carbon::parse($date);
            $subRecord['trans_type'] = 'Insurance Premium';
            $subRecord['trans_ref'] = null;
            $subRecord['system_date'] = Carbon::parse($date);
            $subRecord['credit'] = $amt;
            $subRecord['debit'] = null;
            $subData[] = $subRecord;

            // ─── VAT ───
            $record = [];
            $record['customer_id'] = $policy->customer_id;
            $record['account_id'] = null;
            $record['policy_id'] = $policy->id;
            $record['term_id'] = $termId;
            $record['action_id'] = $actionId;
            $record['claim_id'] = null;
            $record['banking_id'] = $banking_id;
            $record['account_name'] = null;
            $record['accounting_date'] = Carbon::parse($date);
            $record['trans_type'] = 'Invoice VAT';
            $record['amount_type'] = null;
            $record['trans_ref'] = null;
            $record['orig_trans'] = null;
            $record['unallocated'] = null;
            $record['system_date'] = Carbon::parse($date);
            $record['trans_sub_type'] = null;
            $record['eff_date'] = Carbon::parse($date);
            $record['invoice_file'] = null;
            $record['invoice_date'] = null;
            $record['invoice_no'] = null;
            $record['invoice_amount'] = null;
            $record['premium'] = $policyAction->premium;
            $record['other_charges'] = null;
            $record['due_amount'] = null;
            $record['pmts_adjust'] = null;
            $record['due_date'] = null;
            $record['status'] = 'Pending';
            $record['credit'] = null;

            $amt = floatval($policy->vat);
            $record['debit'] = str_replace(',', '', $amt);
            $balance = str_replace(',', '', number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2));
            $record['balance'] = str_replace(',', '', $balance);
            $data[] = $record;

            // SUB-LEDGER VAT
            $subRecord = [];
            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = null;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['term_id'] = $termId;
            $subRecord['action_id'] = $actionId;
            $subRecord['claim_id'] = null;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'VAT Control A/C';
            $subRecord['accounting_date'] = Carbon::parse($date);
            $subRecord['trans_type'] = 'VAT on Insurance Premium';
            $subRecord['trans_ref'] = null;
            $subRecord['system_date'] = Carbon::parse($date);
            $subRecord['credit'] = $amt;
            $subRecord['debit'] = null;
            $subData[] = $subRecord;

            // ─── INVOICE ───
            $record = [];
            $record['customer_id'] = $policy->customer_id;
            $record['account_id'] = null;
            $record['policy_id'] = $policy->id;
            $record['term_id'] = $termId;
            $record['action_id'] = $actionId;
            $record['claim_id'] = null;
            $record['banking_id'] = $banking_id;
            $record['account_name'] = null;
            $record['accounting_date'] = Carbon::parse($date);
            $record['trans_type'] = 'Invoice';
            $record['amount_type'] = null;
            $record['trans_ref'] = null;
            $record['orig_trans'] = null;
            $record['unallocated'] = null;
            $record['system_date'] = Carbon::parse($date);
            $record['trans_sub_type'] = null;
            $record['eff_date'] = Carbon::parse($date);
            $record['invoice_file'] = 1;
            $record['invoice_date'] = Carbon::parse($policyAction->effective_from);
            $record['invoice_no'] = $invoice_no;
            $record['invoice_amount'] = $policyAction->premium;
            $record['premium'] = $policyAction->premium;
            $record['other_charges'] = null;
            $record['due_amount'] = $policyAction->premium;
            $record['pmts_adjust'] = null;
            $record['due_date'] = null;
            $record['status'] = 'Pending';
            $record['credit'] = null;
            $record['debit'] = $policyAction->premium;
            $record['balance'] = str_replace(',', '', $balance);
            $data[] = $record;

            // SUB-LEDGER Invoice
            $subRecord = [];
            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = null;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['term_id'] = $termId;
            $subRecord['action_id'] = $actionId;
            $subRecord['claim_id'] = null;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'Accounts Receivable A/C';
            $subRecord['accounting_date'] = Carbon::parse($date);
            $subRecord['trans_type'] = 'Accounts Receivable';
            $subRecord['trans_ref'] = null;
            $subRecord['system_date'] = Carbon::parse($date);
            $subRecord['credit'] = null;
            $subRecord['debit'] = $policyAction->premium;
            $subData[] = $subRecord;

            // Set trans_ref on all sub-ledger entries
            $subData[0]['trans_ref'] = $invoice_no;
            $subData[1]['trans_ref'] = $invoice_no;
            $subData[2]['trans_ref'] = $invoice_no;

            Ledger::insert($data);
            SubLedger::insert($subData);
            Log::info("generateInvoice: Invoice created for policy {$policyid}, action {$actionId}.");

        } catch (\Exception $e) {
            Log::error('Error in generateInvoice: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }
}
