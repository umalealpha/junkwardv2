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


/**
 * Monthly auto-renew for SPECIALIST products only (cron-server copy).
 *
 * Specialist twin of DomComMonthlyAutoRenew (DOM/COM motor products 7/8).
 * Kept as a separate command so the two crons never overlap and either can be
 * scheduled independently via the cron_kernel table / Cron Portal:
 *
 *   - DomComMonthlyAutoRenew:cron       → products 7, 8            (DOM/COM motor)
 *   - SpecialistMonthlyAutoRenew:cron   → products 16,17,18,19,20,22 (specialist)
 *
 * Specialist products carry their coverages in dedicated one-to-one tables
 * (car/par/ear/travel/marine/…) that the plain newPolicyActionReplace() path
 * does NOT carry on its own. We therefore clone with
 * newPolicyActionReplaceSpecialist(), which also walks
 * SpecialistCoverageRegistry via replicateSpecialistCoveragesIfMissing().
 * Premium is then priced by calculatePremiumRenewSpecialist() — the Rate-button
 * recipe that sums the ten specialist one-to-one tables (the standard
 * calculatePremiumRenew ignores them and would zero the premium). All other
 * monthly cadence semantics mirror DomComMonthlyAutoRenew.
 */
class SpecialistMonthlyAutoRenew extends Command
{
    public $policyAction;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SpecialistMonthlyAutoRenew:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Specialist Monthly Auto Renew';

    /**
     * Specialist product ids handled by this cron. Deliberately disjoint from
     * the DOM/COM motor ids (7, 8) that DomComMonthlyAutoRenew handles, so a
     * policy is never picked up by both crons.
     */
    private const SPECIALIST_PRODUCTS = [16, 17, 18, 19, 20, 22, 23, 24];

    /**
     * The specialist one-to-one coverage tables. Each carries the
     * per-policy "Renewable Policy" flag in its is_renewable column
     * ('Yes' / 'No' / null). Hard-coded here (rather than via
     * SpecialistCoverageRegistry, which lives only in the backend app) to keep
     * the cron command self-contained — mirrors SPECIALIST_PRODUCTS above.
     */
    private const SPECIALIST_COVERAGE_TABLES = [
        'car_coverages', 'par_coverages', 'ear_coverages', 'travel_coverages',
        'medical_malpractice_coverages', 'machinery_breakdown_coverages',
        'professional_indemnity_coverages', 'marine_directors_officers_coverages',
        'marine_cargo_once_off_coverages', 'marine_cargo_open_coverages',
        'medical_evacuation_coverages', 'commercial_crime_coverages',
        'environmental_liability_coverages', 'bonds_coverages',
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Specialist policies opt INTO auto-renewal via the "Renewable Policy"
     * (is_renewable) flag on their specialist coverage rows. We renew only when
     * the policy's operative (latest ISSUED) specialist coverage is explicitly
     * marked 'Yes'. A 'No' — or an unset flag — means do NOT auto-renew.
     *
     * The flag is read off the latest ISSUED term-establishing action's
     * coverage rows when those rows carry an action_id (V2-created policies);
     * otherwise it falls back to any policy-level 'Yes' so legacy rows that
     * were never action-stamped still renew when marked renewable.
     */
    private function isSpecialistPolicyRenewable(int $policyId): bool
    {
        $latestIssued = PolicyAction::where('policy_id', $policyId)
            ->whereIn('transaction_type', ['NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE'])
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
        $actionId = $latestIssued->id ?? null;

        $sawAnyRow = false; // operative action carries any specialist coverage

        foreach (self::SPECIALIST_COVERAGE_TABLES as $table) {
            if (!\Schema::hasColumn($table, 'is_renewable')) {
                continue;
            }
            $base = DB::table($table)->where('policy_id', $policyId);
            if ($actionId && \Schema::hasColumn($table, 'action_id')) {
                $base->where('action_id', $actionId);
            }
            if ((clone $base)->exists()) {
                $sawAnyRow = true;
                if ((clone $base)->where('is_renewable', 'Yes')->exists()) {
                    return true;
                }
            }
        }

        // Operative action carried specialist coverage but none was 'Yes' → No.
        if ($sawAnyRow) {
            return false;
        }

        // Fallback: action_id not stamped on older rows — any policy-level 'Yes'.
        foreach (self::SPECIALIST_COVERAGE_TABLES as $table) {
            if (!\Schema::hasColumn($table, 'is_renewable')) {
                continue;
            }
            if (DB::table($table)->where('policy_id', $policyId)->where('is_renewable', 'Yes')->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
{
    // Tag every log line emitted by this command with {"cron":"SpecialistMonthlyAutoRenew:cron"}
    // so the Cron Logs admin page can filter by cron name. Without this, per-policy
    // "Policy X: DONE" lines don't carry the cron identifier and filter returns 0 matches.
    Log::withContext(['cron' => 'SpecialistMonthlyAutoRenew:cron']);

    // Last-line-of-defence: catches fatal errors (E_ERROR, E_PARSE, E_COMPILE_ERROR,
    // E_CORE_ERROR, OOM) that escape the try/catch below — e.g. memory exhaustion
    // mid-loop, type errors in helpers, undefined functions. Without this, the
    // PHP process exits straight to stderr and nothing reaches the shared cron log.
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err !== null && \in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR], true)) {
            Log::error("SpecialistMonthlyAutoRenew FATAL: {$err['message']} @ {$err['file']}:{$err['line']}");
        }
    });

    $cron = new CronStatus();
    $cron->name = "SpecialistMonthlyAutoRenew:cron";
    $cron->start = \Carbon\Carbon::now();
    $cron->current_step = 'starting';
    $cron->save();
    Log::info("SpecialistMonthlyAutoRenew Cron is working fine!");

    try {
        $cron->update(['current_step' => 'fetching_policies']);

        return $this->doHandle($cron);
    } catch (\Throwable $e) {
        // Always close out the cron_status row on failure so the row is
        // observable as "ran but failed" rather than "never finished" —
        // and re-throw so Laravel's scheduler can still log + release
        // its mutex in the finally block of runInForeground().
        $cron->update([
            'current_step' => 'failed',
            'end' => \Carbon\Carbon::now(),
            'error_message' => substr($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 0, 1000),
        ]);
        Log::error("SpecialistMonthlyAutoRenew cron failed: " . $e->getMessage(), [
            'last_policy_id' => $cron->last_policy_id ?? null,
            'exception' => $e,
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
    $policies = DB::table('policies as p')
    ->select('p.id', 'p.policyNumber')
    // joinSub with MAX(id) — guarantees ONE row per policy, no DISTINCT needed
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
    ->where('p.premium_freq', 1)
    // Specialist products only — disjoint from DOM/COM (7, 8).
    ->whereIn('p.product_id', self::SPECIALIST_PRODUCTS)
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
    // [3+4+10] Combined: skip if latest action is CANCEL+ISSUED, LAPSED, or REINSTATE+QUOTE
    // Single subquery instead of 3 separate MAX(id) scans
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
                });
            })
            ->whereRaw("pa_check.id = (
                SELECT MAX(px.id) FROM policy_actions px
                WHERE px.policy_id = p.id AND px.deleted_at IS NULL
            )");
    })
    ->orderByDesc('p.id')
    ->get();
       // dd(count($policies));

    // Capture count BEFORE the loop — inside the loop $policies gets reassigned
    // to a single Policy model via Policy::find($policy->id), which shadows the
    // outer collection. Without this capture the SUMMARY log below would crash
    // with "count(): Argument must be Countable|array, AlphaDirect\Policy given".
    $totalPoliciesCount = \count($policies);

    $cron->update(['current_step' => 'processing', 'processedCount' => 0]);

    Log::info("SpecialistMonthlyAutoRenew: starting batch — {$totalPoliciesCount} policies to process");

    $finalData = [];
    $processedCount = 0;
    $createdCountTotal = 0;
    $skippedCountTotal = 0;

/**
 * SPECIALIST MONTHLY AUTO-RENEW CRON — ALL CASES HANDLED
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  THREE control variables (not two):
 *  ───────────────────────────────────────────────────────────────────────────
 *  $renewStartFrom       → cycleStart must be >= this (skip dates before it)
 *                          Set to (anniversaryEnd + 1 day) when anniversary ISSUED
 *                          Ensures RENEWs never overlap the anniversary period
 *
 *  $anniversaryStopDate  → caps cycleEnd only (anniversary boundary)
 *
 *  $hardStopDate         → min(anniversaryStopDate, today) → CREATE gate only
 *
 *  ─────────────────────────────────────────────────────────────────────────
 *  EXAMPLE — NB = 01/01/2025, Anniversary ISSUED 01/01/2026 → 31/01/2026:
 *    $renewStartFrom      = 01/02/2026  (day after anniversary ends)
 *    $anniversaryStopDate = 31/12/2026  (next anniversary 01/01/2027 − 1)
 *    $hardStopDate        = today
 *
 *    cycleStart = 01/01/2026 → lt $renewStartFrom → SKIP ✅ (not created)
 *    cycleStart = 01/02/2026 → gte $renewStartFrom, lte hardStop → CREATE ✅
 *
 *  ─────────────────────────────────────────────────────────────────────────
 *  ALL CASES:
 *  [C1]  Cancelled / Lapsed                     → skip entire policy
 *  [C2]  No NEWBUSINESS action                  → skip entire policy
 *  [C3]  Day 1–28                               → no drift, always exact
 *  [C4]  Day 29/30/31 in Feb                    → clamped to 28, bounces back
 *  [C5]  Day 31 in 30-day months                → clamped to 30, bounces back
 *  [C6]  Before backfill cutoff (2024-01-01)    → skipped (continue, not break)
 *  [C7]  cycleStart > today                     → stop loop
 *  [C8]  cycleStart > anniversaryStopDate       → stop loop
 *  [C9]  cycleEnd   > anniversaryStopDate       → cap to anniversaryStopDate
 *  [C10] cycleEnd   > today                     → NOT capped (future end OK)
 *  [C11] Anniversary QUOTE exists               → hardStop = quoteDate − 1
 *  [C12] Anniversary ISSUED                     → renewStartFrom = anniversaryTo + 1
 *                                                  stop before NEXT anniversary
 *  [C13] No anniversary at all                  → stop before first anniversary
 *  [C14] Duplicate — memory check               → skip, continue
 *  [C15] Duplicate — DB safety check            → skip, continue
 * ─────────────────────────────────────────────────────────────────────────────
 */

$backfillFrom = Carbon::create(2024, 1, 1);

foreach ($policies as $policy) {
    $processedCount++;
    $cron->update([
        'last_policy_id' => $policy->id,
        'processedCount' => $processedCount,
    ]);

    // ──────────────────────────────────────────────────────────────────────
    // [C1] Skip cancelled / lapsed
    // ──────────────────────────────────────────────────────────────────────
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

    // [C1] Cancelled-policy stop (GRA-0132). The single-latest-action test is
    // defeated once ONE bogus RENEW is ISSUED after a cancel: that RENEW becomes
    // the latest action, the CANCEL is never seen again, and the policy renews
    // every cycle forever. A RENEW can never clear a cancel — only a
    // REINSTATE/REISSUE can. So find the latest ISSUED CANCEL and treat the
    // policy as cancelled unless a REINSTATE/REISSUE was ISSUED *after* it.
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
    // single-latest-action LAPSED test above is defeated once a renewal wrongly
    // stacks on top of the lapse: that RENEW becomes the latest action and hides
    // the LAPSED row forever. Only a REINSTATE/REISSUE can revive lapsed cover —
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

    // ──────────────────────────────────────────────────────────────────────
    // [C16] REISSUE / REINSTATE in QUOTE — don't renew until re-issued
    // ──────────────────────────────────────────────────────────────────────
    if (in_array($latestAction->transaction_type, ['REINSTATE', 'REISSUE'], true) && $latestAction->status === 'QUOTE') {
        Log::info("Policy {$policy->id}: {$latestAction->transaction_type} in QUOTE — skip.");
        continue;
    }

    // ──────────────────────────────────────────────────────────────────────
    // [C2] NEWBUSINESS — source of truth for dates
    // ──────────────────────────────────────────────────────────────────────
    $nbAction = PolicyAction::where('policy_id', $policy->id)
        ->where('transaction_type', 'NEWBUSINESS')
        ->where('status', 'ISSUED')
        ->first();

    if (!$nbAction) {
        Log::warning("Policy {$policy->id}: No NEWBUSINESS action — skip.");
        continue;
    }

    // Renewable Policy gate — only auto-renew specialist policies whose
    // operative coverage is marked is_renewable = 'Yes'. 'No' / unset → skip.
    if (!$this->isSpecialistPolicyRenewable($policy->id)) {
        Log::info("Policy {$policy->id}: Renewable Policy = No (or unset) — skip specialist auto-renew.");
        continue;
    }

    $policyStartDate = Carbon::parse($nbAction->effective_from);
    $originalDay     = (int) $policyStartDate->day;
    $nbMonth         = (int) $policyStartDate->month;
    $nbYear          = (int) $policyStartDate->year;

    Log::info("Policy {$policy->id}: NB = {$policyStartDate->format('Y-m-d')}, originalDay = {$originalDay}");

    // ──────────────────────────────────────────────────────────────────────
    // Latest Anniversary
    // ──────────────────────────────────────────────────────────────────────
    $latestAnniversary = PolicyAction::where('policy_id', $policy->id)
        ->where('transaction_type', 'ANNIVERSARY-RENEW')
        ->whereNull('deleted_at')
        ->orderBy('effective_from', 'DESC')
        ->orderBy('id', 'DESC')
        ->first();

    $hasAnniversaryQuote  = $latestAnniversary && $latestAnniversary->status === 'QUOTE';
    $hasAnniversaryIssued = $latestAnniversary && $latestAnniversary->status === 'ISSUED';

    // Earliest PENDING anniversary QUOTE — the schedule's ceiling, applied after
    // the branches below. Read directly instead of relying on the status of the
    // latest anniversary row: when a later-dated anniversary of another status
    // exists, $hasAnniversaryQuote is false and the [C12]/[C13] branches let the
    // batch run straight through a quote still awaiting UW.
    $pendingAnniversaryQuote = PolicyAction::where('policy_id', $policy->id)
        ->where('transaction_type', 'ANNIVERSARY-RENEW')
        ->where('status', 'QUOTE')
        ->whereNull('deleted_at')
        ->orderBy('effective_from')
        ->first();

    // ──────────────────────────────────────────────────────────────────────
    // Last ISSUED action (for premium reference only — not date logic)
    // ──────────────────────────────────────────────────────────────────────
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

    // ──────────────────────────────────────────────────────────────────────
    // Next anniversary boundary (always calculated from NB)
    // ──────────────────────────────────────────────────────────────────────
    $yearsFromStart = 1;
    while ($policyStartDate->copy()->addYearsNoOverflow($yearsFromStart)->lte($anchorDate)) {
        $yearsFromStart++;
    }
    $nextAnniversaryDate = $policyStartDate->copy()->addYearsNoOverflow($yearsFromStart);

    // ──────────────────────────────────────────────────────────────────────
    // [C11–C13] Calculate the three control variables
    // ──────────────────────────────────────────────────────────────────────
    $anniversaryStart = null;
    $anniversaryEnd   = null;

    if ($hasAnniversaryQuote) {
        // [C11] QUOTE pending → stop monthly RENEWs just before it
        $anniversaryStopDate = Carbon::parse($latestAnniversary->effective_from)->subDay();
        $renewStartFrom      = $backfillFrom->copy();

        Log::info("Policy {$policy->id}: Anniversary QUOTE — anniversaryStop = {$anniversaryStopDate->format('Y-m-d')}");

    } elseif ($hasAnniversaryIssued) {
        // [C12] Anniversary ISSUED → two windows:
        //   Window 1: BEFORE anniversary → allow, cap cycleEnd at anniversaryStart - 1
        //   Window 2: AFTER anniversary  → allow, cap at next anniversary - 1
        //   INSIDE anniversary period    → skip
        $anniversaryStart    = Carbon::parse($latestAnniversary->effective_from);
        $anniversaryEnd      = Carbon::parse($latestAnniversary->effective_to);
        $renewStartFrom      = $backfillFrom->copy(); // allow cycles before anniversary

        // Next anniversary boundary follows the ISSUED anniversary's (possibly
        // UW-changed) start date, not the original NB schedule — otherwise a
        // date change on the anniversary cuts the last re-anchored cycle short.
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

    // ──────────────────────────────────────────────────────────────────────
    // [C11b] A pending anniversary QUOTE closes the schedule, whichever branch
    // above set the stop date. Nothing may be created on/after its start date
    // until UW issues it — otherwise monthly RENEWs stack on top of a quote the
    // customer has not taken up yet.
    // ──────────────────────────────────────────────────────────────────────
    if ($pendingAnniversaryQuote) {
        $quoteStopDate = Carbon::parse($pendingAnniversaryQuote->effective_from)->subDay();
        if ($anniversaryStopDate->gt($quoteStopDate)) {
            $anniversaryStopDate = $quoteStopDate;
            Log::info("Policy {$policy->id}: pending ANNIVERSARY-RENEW QUOTE #{$pendingAnniversaryQuote->id} — anniversaryStop capped to {$anniversaryStopDate->format('Y-m-d')}.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // [C18] An ISSUED anniversary re-anchors the cycle dates. When UW changes
    // the period of insurance on the anniversary renewal, all later RENEWs
    // must follow the anniversary's day, not the original NEWBUSINESS day
    // (e.g. anniversary issued 01/04–30/04 → renews 01/05–31/05, 01/06–30/06,
    // NOT 26/04–25/05 from a NB on the 26th). Applies even when a newer
    // anniversary QUOTE is pending — cycles between the issued anniversary
    // and that quote still follow the issued dates.
    // ──────────────────────────────────────────────────────────────────────
    $latestIssuedAnniversary = $hasAnniversaryIssued
        ? $latestAnniversary
        : PolicyAction::where('policy_id', $policy->id)
            ->where('transaction_type', 'ANNIVERSARY-RENEW')
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
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

    // [C17] If NB was annual (or any longer freq), don't create monthly RENEWs inside the NB period
    // But if there's a REINSTATE or existing RENEW after NB, use that as the boundary instead
    $latestIssuedAfterNB = PolicyAction::where('policy_id', $policy->id)
        ->whereIn('transaction_type', ['REINSTATE', 'RENEW', 'ANNIVERSARY-RENEW'])
        ->where('status', 'ISSUED')
        ->whereNull('deleted_at')
        ->where('id', '>', $nbAction->id)
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
    // Does NOT cap cycleEnd (a batch can legitimately end in the future)
    $hardStopDate = $anniversaryStopDate->copy();
    if ($hardStopDate->gt($today)) {
        $hardStopDate = $today->copy();
    }

    Log::info("Policy {$policy->id}: hardStop (CREATE gate) = {$hardStopDate->format('Y-m-d')}");

    // ──────────────────────────────────────────────────────────────────────
    // [C14] Load existing RENEW months for in-memory duplicate check
    // ──────────────────────────────────────────────────────────────────────
    $existingMonths = PolicyAction::where('policy_id', $policy->id)
        ->where('transaction_type', 'RENEW')
        ->where('status', 'ISSUED')
        ->whereNull('deleted_at')
        ->get()
        ->map(fn($pa) => Carbon::parse($pa->effective_from)->format('Y-m'))
        ->toArray();

    // ──────────────────────────────────────────────────────────────────────
    // Monthly cycle loop
    // ──────────────────────────────────────────────────────────────────────
    $createdCount = 0;
    $skippedCount = 0;

    for ($offset = 1; $offset <= 300; $offset++) {

        // ── [C3–C5] Calculate cycleStart from NB (manual math, no overflow) ──
        $totalMonths = ($nbYear * 12 + $nbMonth - 1) + $offset;
        $year        = intdiv($totalMonths, 12);
        $month       = ($totalMonths % 12) + 1;

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $day         = min($originalDay, $daysInMonth); // bounces back next month ✅
        $cycleStart  = Carbon::create($year, $month, $day);

        // ── [C6] Skip before backfill cutoff (continue, not break) ──
        if ($cycleStart->lt($backfillFrom)) {
            continue;
        }

        // ── [C12] Skip dates that fall inside the anniversary period ──
        if ($cycleStart->lt($renewStartFrom)) {
            Log::info("Policy {$policy->id}: SKIP offset {$offset} ({$cycleStart->format('Y-m-d')}) — before renewStartFrom = {$renewStartFrom->format('Y-m-d')}.");
            continue;
        }

        // ── [C12b] Skip cycles that start INSIDE an ISSUED anniversary period ──
        if ($hasAnniversaryIssued && isset($anniversaryStart) && isset($anniversaryEnd)
            && $cycleStart->gte($anniversaryStart) && $cycleStart->lte($anniversaryEnd)) {
            Log::info("Policy {$policy->id}: SKIP offset {$offset} ({$cycleStart->format('Y-m-d')}) — inside anniversary period {$anniversaryStart->format('Y-m-d')} to {$anniversaryEnd->format('Y-m-d')}.");
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

        // ── [C3–C5] Natural cycleEnd = next cycleStart − 1 day ──
        $totalMonthsEnd = ($nbYear * 12 + $nbMonth - 1) + $offset + 1;
        $eyear          = intdiv($totalMonthsEnd, 12);
        $emonth         = ($totalMonthsEnd % 12) + 1;
        $edaysInMonth   = Carbon::create($eyear, $emonth, 1)->daysInMonth;
        $eday           = min($originalDay, $edaysInMonth);
        $nextCycleStart = Carbon::create($eyear, $emonth, $eday);
        $cycleEnd       = $nextCycleStart->copy()->subDay();

        // ── [C9] Cap cycleEnd at anniversary boundary ONLY (NOT today) ──
        // NB = Jan 14, today = Feb 28 → cycleEnd stays Mar 13 ✅ (not capped to Feb 28)
        // NB = Jan 29, today = Feb 28 → cycleEnd stays Mar 28 ✅
        if ($cycleEnd->gt($anniversaryStopDate)) {
            $cycleEnd = $anniversaryStopDate->copy();
        }

        // ── [C12c] For cycles BEFORE issued anniversary: cap cycleEnd at anniversaryStart - 1 ──
        // e.g. March batch (09/03 → 08/04) but anniversary starts 09/04
        //      → cap cycleEnd to 08/04 (anniversaryStart - 1 = 08/04) ✅
        if ($hasAnniversaryIssued && isset($anniversaryStart)
            && $cycleStart->lt($anniversaryStart) && $cycleEnd->gte($anniversaryStart)) {
            $cycleEnd = $anniversaryStart->copy()->subDay();
        }

        $effectiveFrom = $cycleStart->format('Y-m-d');
        $effectiveTo   = $cycleEnd->format('Y-m-d');

        // ── [C14] Duplicate check — memory ──
        $monthKey = $cycleStart->format('Y-m');
        if (in_array($monthKey, $existingMonths)) {
            $skippedCount++;
            Log::info("Policy {$policy->id}: SKIP {$effectiveFrom} — exists in memory ({$monthKey}).");
            continue;
        }

        // ── [C15] Duplicate check — DB safety net ──
        $existsInDb = PolicyAction::where('policy_id', $policy->id)
            ->where('transaction_type', 'RENEW')
            ->whereYear('effective_from', $year)
            ->whereMonth('effective_from', $month)
            ->whereNull('deleted_at')
            ->exists();

        if ($existsInDb) {
            $skippedCount++;
            Log::info("Policy {$policy->id}: SKIP {$effectiveFrom} — DB found existing ({$monthKey}).");
            continue;
        }

        // ── Source for action + replication + premium ──
        // The last ISSUED action in force before this renewal (any transaction
        // type), so the renewal carries forward the operative coverage set &
        // premium — a UW-adjusted anniversary or a mid-term endorsement —
        // instead of reverting to the original NEWBUSINESS figures. Falls back
        // to NB when nothing else is issued yet.
        //
        // Order by effective_from FIRST, id only as the tie-break: a batch
        // deleted & recreated (or re-issued) later carries a HIGHER id while
        // holding an EARLIER period, so plain id-desc sourced from that older
        // period instead of the true last one. Same (effective_from, id)
        // chronology the DomCom crons use.
        $referenceAction = PolicyAction::where('policy_id', $policy->id)
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->whereDate('effective_from', '<', $effectiveFrom)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first() ?? $nbAction;

        $quoteDay      = str_pad($originalDay, 2, '0', STR_PAD_LEFT);
        $policyQuoteNo = $policy->policyNumber . '/' . $quoteDay;

        // ── Create the monthly renewal ──
        $action = $this->createPolicyActionFromPrevious(
            $referenceAction,
            'RENEW',
            'ISSUED',
            $policyQuoteNo,
            $effectiveFrom,
            $effectiveTo

        );

        // Specialist replicator — carries the ten one-to-one coverage tables
        // (car/par/ear/travel/marine/…) the plain DOM/COM path does not, then
        // price the replicated schedule with the specialist Rate-button recipe
        // (the standard calculatePremiumRenew ignores the specialist tables and
        // would zero the action and its invoice).
        PolicyAction::newPolicyActionReplaceSpecialist($action, $referenceAction->id);
        PolicyAction::calculatePremiumRenewSpecialist($action->id, $action->term_id, $action->policy_id);
        $this->generateInvoice($policy->id, $action->id, $effectiveFrom);

        $createdCount++;

        $finalData[] = [
            'policyNumber' => $policy->policyNumber,
            'issuedDate'   => $cycleStart->format('d-m-Y'),
            'totalpolicy'  => 1,
        ];
        $policies = Policy::find($policy->id);
         activity('Specialist Monthly Auto Renewal')
                ->performedOn($policies)
                ->log('Specialist monthly renewal - '. $policy->policyNumber.' - '.$effectiveFrom.' to '.$effectiveTo);

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
        "SpecialistMonthlyAutoRenew: batch SUMMARY — processed {$totalPoliciesCount}" .
        " polic(ies), createdTotal={$createdCountTotal}, skippedTotal={$skippedCountTotal}, finalDataRows=" . \count($finalData)
    );

    if (!empty($finalData)) {
        $cron->update(['current_step' => 'sending_email']);
        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Policy/created-' . $date . '/PoliciesSpecialistRenewMonthly.pdf';
        $data = ['finalData' => $finalData];
        $pdf = PDF::loadView('admin.notes.daily_renew_monthly_policy', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = [$path];
        $cronSendMail = new CronController();
        $hook = 'Policies_renew_today';
        $cronSendMail->AllCronMail($attachments, $hook, $cron);
    }

    $cron->update([
        'current_step' => 'completed',
        'end' => \Carbon\Carbon::now(),
    ]);
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
                ->where('effective_from', '<', $startMonth)
                ->orderByRaw("ABS(DATEDIFF(effective_from, ?))", [$startMonth])
                ->orderByDesc('id')
                ->first();

            if ($action) {
                return $action;
            }
        }

        // Fallback to latest issued action
        return PolicyAction::where('policy_id', $policyId)
            ->where('status', 'ISSUED')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    protected function getLatestPolicyAction($policyId, $transactionType, $date = null)
    {
        $query = PolicyAction::where('policy_id', $policyId)
            ->where('status', 'ISSUED')
            ->where('transaction_type', $transactionType)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($date) {
            $query->where('effective_from', '<', $date);
        }

        return $query->first();
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
            'note' => 'Specialist renewal generated by cron monthly',
        ]);
    }


    public function generateInvoice($policyid, $actionId, $invoicedate)
    {

        try {
            $today = Carbon::now()->format('d');//Carbon::today();
            $date = Carbon::now()->format('Y-m-d');

            $policiesbyBilled = Policy::
                whereIn('product_id', self::SPECIALIST_PRODUCTS)
                ->where('status', 1)
                ->where('id', $policyid)
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium', 'annual_premium', 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'))
                ->get();

            $policies = $policiesbyBilled->chunk(1000);
            if ($policies->isEmpty()) {
                Log::info('Invoices data Not found');
            } else {
                foreach ($policies as $records) {
                    foreach ($records as $policy) {
                        $policy_id = $policy->id;

                        $policyAction = PolicyAction::where('policy_id', $policy_id)->where('id', $actionId)->where('status', 'ISSUED')->get();
                        foreach ($policyAction as $policyActions) {
                            $termId = isset($policyActions->term_id) ? $policyActions->term_id : 0;
                            $actionId = isset($policyActions->id) ? $policyActions->id : 0;
                            // ->where('action_id', $policyActions->id)
                            $check_invoice_exists = Ledger::where('policy_id', $policy_id)
                                ->where('trans_type', 'Invoice')
                                ->where('action_id', $actionId)
                                ->whereNull('deleted_at')
                                ->count();
                            if ($check_invoice_exists == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == 2 || $policy->premium_freq == 3)) {
                                $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                                $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                                $policy = Policy::where('id', $policy_id)->first();
                                if ($ledger != NULL) {
                                    $invoice_no = $ledger->invoice_no;
                                    $invoice_no++;
                                    $banking_id = $ledger->banking_id;

                                } else {
                                    $invoice_no = $policy->policyNumber . '-' . sprintf('%03d', 1);
                                    $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                                    if ($banking_id != NULL)
                                        $banking_id = $banking_id->id;
                                    else
                                        $banking_id = NULL;
                                }
                                $balance = 0;
                                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                                if ($balance != null) {
                                    $balance = $balance->balance;
                                } else {
                                    if ($balance != null) {
                                        $balance = $balance->balance;
                                    } else {
                                        $balance = 0;
                                    }
                                }
                                $data = array();
                                $record = array();
                                $subData = array();
                                $subRecord = array();

                                //-------------------------------PREMIUM--------------------------------------
                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice Premium';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = NULL;
                                $record['invoice_date'] = NULL;
                                $record['invoice_no'] = NULL;
                                $record['invoice_amount'] = NULL;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = NULL;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;

                                $amt = str_replace(',', '', number_format(((float) $policyActions->premium - (float) $policy->vat), 2));
                                $record['debit'] = str_replace(',', '', $amt);
                                $balance = number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2);
                                $record['balance'] = str_replace(',', '', $balance);

                                $data[] = $record;


                                //----SUB-LEDGER
                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'Insurance Sales A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'Insurance Premium';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = $amt;
                                $subRecord['debit'] = NULL;

                                $subData[] = $subRecord;

                                //-------------------------------PREMIUM--------------------------------------
                                //-------------------------------VAT--------------------------------------

                                $record = array();

                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice VAT';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = NULL;
                                $record['invoice_date'] = NULL;
                                $record['invoice_no'] = NULL;
                                $record['invoice_amount'] = NULL;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = NULL;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;

                                $amt = floatval($policy->vat);

                                $record['debit'] = str_replace(',', '', $amt);
                                $balance = str_replace(',', '', number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2));
                                $record['balance'] = str_replace(',', '', $balance);

                                $data[] = $record;

                                //----SUB-LEDGER

                                $subRecord = array();

                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'VAT Control A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'VAT on Insurance Premium';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = $amt;
                                $subRecord['debit'] = NULL;

                                $subData[] = $subRecord;

                                //-------------------------------VAT--------------------------------------
                                //-------------------------------INVOICE--------------------------------------

                                $record = array();
                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = 1;
                                $record['invoice_date'] = Carbon::parse($policyActions->effective_from);
                                $record['invoice_no'] = $invoice_no;
                                $record['invoice_amount'] = $policyActions->premium;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = $policyActions->premium;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;

                                $amt = number_format(((float) str_replace(',', '', $policyActions->premium) - (float) str_replace(',', '', $policy->vat)), 2);

                                $record['debit'] = $policyActions->premium;
                                $record['balance'] = str_replace(',', '', $balance);

                                $data[] = $record;

                                //----SUB-LEDGER

                                $subRecord = array();
                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'Accounts Receivable A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'Accounts Receivable';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = NULL;
                                $subRecord['debit'] = $policyActions->premium;

                                $subData[] = $subRecord;

                                $subData[0]['trans_ref'] = $invoice_no;
                                $subData[1]['trans_ref'] = $invoice_no;
                                $subData[2]['trans_ref'] = $invoice_no;
                                //-------------------------------INVOICE-------------------------------------
                                Ledger::insert($data);
                                SubLedger::insert($subData);
                                Log::info('Invoices data', $data);
                            }



                        }
                    }
                }

            }

        } catch (\Exception $e) {
            Log::error('Error in generateInvoice method: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

}
