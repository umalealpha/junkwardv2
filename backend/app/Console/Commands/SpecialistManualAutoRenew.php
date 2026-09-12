<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Ledger;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use AlphaDirect\SubLedger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PDF;

/**
 * MANUAL INPUT auto-renew for SPECIALIST products.
 *
 * Third sibling of SpecialistMonthlyAutoRenew / SpecialistQuaterlyAutoRenew.
 * Those two step the schedule by a FIXED cadence (1 month / 3 months) taken from
 * the NEWBUSINESS day-of-month. A MANUAL INPUT policy (policies.premium_freq = 6)
 * has no cadence at all — its period of insurance is whatever the two dates on
 * the policy say it is (e.g. 01-03-2026 -> 01-04-2026 on an Engineering policy).
 * So this cron derives the cadence from the DATE DIFFERENCE of the period in
 * force and repeats exactly that many days for the next batch:
 *
 *     periodDays = (effective_to - effective_from) + 1        (inclusive)
 *     next batch = [previous effective_to + 1 day, + periodDays - 1 days]
 *
 *   e.g. 01/03/2026 -> 01/04/2026  (32 days)
 *        next        02/04/2026 -> 03/05/2026
 *        next        04/05/2026 -> 04/06/2026 ... up to today / the anniversary
 *
 * Two gates decide whether a policy is touched at all:
 *   1. policies.premium_freq = 6         -> MANUAL INPUT
 *   2. "Renewable Policy" (is_renewable) -> must be 'Yes' on the operative
 *      specialist coverage row. 'No' or unset -> never auto-renewed.
 *
 *   - DomComMonthlyAutoRenew:cron       -> products 7, 8             (DOM/COM motor)
 *   - SpecialistMonthlyAutoRenew:cron   -> products 16..24, freq 1   (specialist)
 *   - SpecialistQuaterlyAutoRenew:cron  -> products 16..24, freq 5   (specialist)
 *   - SpecialistManualAutoRenew:cron    -> products 16..24, freq 6   (specialist)  <- this one
 *
 * Disjoint frequency filters mean a policy is never picked up by two crons.
 *
 * Specialist products carry their coverages in dedicated one-to-one tables
 * (car/par/ear/travel/marine/...) that the plain newPolicyActionReplace() path
 * does NOT carry, so the clone goes through newPolicyActionReplaceSpecialist()
 * and the premium through calculatePremiumRenewSpecialist() — the Rate-button
 * recipe that sums those ten tables. The standard calculatePremiumRenew ignores
 * them and would zero the action and its invoice.
 *
 * INVOICING IS OPT-IN (--invoice). CreateInvoiceForDomCom deliberately excludes
 * premium_freq 6 from its unscoped daily batch ("a manual-billing policy should
 * not be auto-invoiced by the scheduler without a business decision"). This cron
 * keeps that stance: by default it creates the RENEW action only. Pass --invoice
 * (or store the cron_kernel row as 'SpecialistManualAutoRenew:cron --invoice')
 * once Finance signs off on auto-invoicing manual-billing policies.
 */
class SpecialistManualAutoRenew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SpecialistManualAutoRenew:cron
        {--policy= : Restrict the run to a single policy (id or policyNumber) — for local/testing. Omit to run the full batch as the scheduler does.}
        {--dry-run : Log every batch that WOULD be created and write nothing.}
        {--invoice : Also raise the ledger invoice for each created batch. Off by default — manual-billing policies are not auto-invoiced without a Finance decision.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Specialist MANUAL INPUT Auto Renew — repeats the policy period by its own date difference';

    /** policies.premium_freq value for MANUAL INPUT (LookupController::premium_frequencies). */
    private const MANUAL_FREQ = 6;

    /**
     * Specialist product ids handled by this cron. Deliberately disjoint from
     * the DOM/COM motor ids (7, 8), so a policy is never picked up by both the
     * DOM/COM and the specialist renewal crons.
     */
    private const SPECIALIST_PRODUCTS = [16, 17, 18, 19, 20, 22, 23, 24];

    /**
     * The specialist one-to-one coverage tables. Each carries the per-policy
     * "Renewable Policy" flag in its is_renewable column ('Yes' / 'No' / null).
     * Hard-coded here (rather than via SpecialistCoverageRegistry, which lives
     * only in the backend app) so this command stays byte-identical in backend/
     * and cron/ — see project_specialist_cron_list_drift.
     */
    private const SPECIALIST_COVERAGE_TABLES = [
        'car_coverages', 'par_coverages', 'ear_coverages', 'travel_coverages',
        'medical_malpractice_coverages', 'machinery_breakdown_coverages',
        'professional_indemnity_coverages', 'marine_directors_officers_coverages',
        'marine_cargo_once_off_coverages', 'marine_cargo_open_coverages',
        'medical_evacuation_coverages', 'commercial_crime_coverages',
        'environmental_liability_coverages', 'bonds_coverages',
    ];

    /** Term-establishing transaction types — the ones that carry a period of insurance. */
    private const TERM_TYPES = ['NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE'];

    /** Runaway guard for the cycle loop (a 1-day manual period would otherwise spin). */
    private const MAX_CYCLES = 400;

    /** Longest manual period we will repeat. Anything longer is anniversary work. */
    private const MAX_PERIOD_DAYS = 366;

    /** Same V2 backfill floor the sibling renewal crons use. */
    private const BACKFILL_FROM = '2024-01-01';

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
     * The flag is read off the latest ISSUED term-establishing action's coverage
     * rows when those rows carry an action_id (V2-created policies); otherwise it
     * falls back to any policy-level 'Yes' so legacy rows that were never
     * action-stamped still renew when marked renewable.
     */
    private function isSpecialistPolicyRenewable(int $policyId): bool
    {
        $latestIssued = PolicyAction::where('policy_id', $policyId)
            ->whereIn('transaction_type', self::TERM_TYPES)
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

        // Operative action carried specialist coverage but none was 'Yes' -> No.
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
     */
    public function handle()
    {
        // Tag every log line with {"cron":"SpecialistManualAutoRenew:cron"} so the
        // Cron Logs admin page can filter by cron name.
        Log::withContext(['cron' => 'SpecialistManualAutoRenew:cron']);

        // Last-line-of-defence: catches fatal errors (OOM, type errors in helpers)
        // that escape the try/catch below, which would otherwise exit straight to
        // stderr with nothing reaching the shared cron log.
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err !== null && \in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR], true)) {
                Log::error("SpecialistManualAutoRenew FATAL: {$err['message']} @ {$err['file']}:{$err['line']}");
            }
        });

        $cron = new CronStatus();
        $cron->name = 'SpecialistManualAutoRenew:cron';
        $cron->start = Carbon::now();
        $cron->current_step = 'starting';
        $cron->save();
        Log::info('SpecialistManualAutoRenew Cron is working fine!');

        try {
            $cron->update(['current_step' => 'fetching_policies']);

            return $this->doHandle($cron);
        } catch (\Throwable $e) {
            // Always close out the cron_status row on failure so the run is
            // observable as "ran but failed" rather than "never finished" — and
            // re-throw so Laravel's scheduler still logs + releases its mutex.
            $cron->update([
                'current_step'  => 'failed',
                'end'           => Carbon::now(),
                'error_message' => substr($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 0, 1000),
            ]);
            Log::error('SpecialistManualAutoRenew cron failed: ' . $e->getMessage(), [
                'last_policy_id' => $cron->last_policy_id ?? null,
                'exception'      => $e,
            ]);
            throw $e;
        }
    }

    /**
     * The actual renewal work, separated so handle() can wrap it in a single
     * try/catch that guarantees cron_status closure on any failure.
     */
    private function doHandle(CronStatus $cron)
    {
        $today   = Carbon::today();
        $dryRun  = (bool) $this->option('dry-run');
        $invoice = (bool) $this->option('invoice');

        $policies = DB::table('policies as p')
            ->select('p.id', 'p.policyNumber')
            // joinSub with MAX(id) — guarantees ONE row per policy, no DISTINCT needed
            ->joinSub(function ($query) {
                $query->from('policy_actions as pa1')
                    ->select('pa1.policy_id', 'pa1.effective_from', 'pa1.effective_to')
                    ->where('pa1.status', 'ISSUED')
                    ->whereIn('pa1.transaction_type', self::TERM_TYPES)
                    ->whereNull('pa1.deleted_at')
                    ->whereRaw("pa1.id = (
                        SELECT MAX(pa2.id) FROM policy_actions pa2
                        WHERE pa2.policy_id = pa1.policy_id
                          AND pa2.status = 'ISSUED'
                          AND pa2.transaction_type IN ('NEWBUSINESS','RENEW','ANNIVERSARY-RENEW','REISSUE','REINSTATE')
                          AND pa2.deleted_at IS NULL
                    )");
            }, 'pa_last', 'pa_last.policy_id', '=', 'p.id')
            // MANUAL INPUT only — disjoint from the monthly (1) / quarterly (5) crons.
            ->where('p.premium_freq', self::MANUAL_FREQ)
            // Specialist products only — disjoint from DOM/COM (7, 8).
            ->whereIn('p.product_id', self::SPECIALIST_PRODUCTS)
            ->where('p.created_at', '>=', '2024-07-01 00:00:00')
            // Last issued period already expired
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
            // Skip if the latest action is CANCEL+ISSUED, LAPSED, or REINSTATE+QUOTE.
            // Single subquery instead of 3 separate MAX(id) scans.
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
            // Optional single-policy filter (local / testing). The eligibility
            // gates above STILL apply, so a policy that isn't due won't renew.
            ->when($this->option('policy'), function ($q, $policyRef) {
                $q->where(function ($w) use ($policyRef) {
                    $w->where('p.id', $policyRef)
                      ->orWhere('p.policyNumber', $policyRef);
                });
            })
            ->orderByDesc('p.id')
            ->get();

        // Capture the count BEFORE the loop — the SUMMARY line below must never
        // be the thing that crashes an otherwise successful batch.
        $totalPoliciesCount = \count($policies);

        $cron->update(['current_step' => 'processing', 'processedCount' => 0]);
        Log::info(
            "SpecialistManualAutoRenew: starting batch — {$totalPoliciesCount} MANUAL INPUT polic(ies) to process" .
            ($dryRun ? ' [DRY RUN]' : '') . ($invoice ? ' [invoicing ON]' : ' [invoicing OFF]')
        );

        $backfillFrom = Carbon::parse(self::BACKFILL_FROM);

        $finalData         = [];
        $processedCount    = 0;
        $createdCountTotal = 0;
        $skippedCountTotal = 0;

        foreach ($policies as $policy) {
            $processedCount++;
            $cron->update([
                'last_policy_id' => $policy->id,
                'processedCount' => $processedCount,
            ]);

            // ── Latest action safety ────────────────────────────────────────
            $latestAction = PolicyAction::where('policy_id', $policy->id)
                ->whereNull('deleted_at')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if (!$latestAction) {
                Log::warning("Policy {$policy->id}: No action found, skipping.");
                continue;
            }

            if ($latestAction->status === 'LAPSED') {
                Log::info("Policy {$policy->id}: Lapsed — skip.");
                continue;
            }

            // ── Cancelled-policy stop (GRA-0132) ───────────────────────────
            // The single-latest-action test is defeated once ONE bogus RENEW is
            // ISSUED after a cancel: that RENEW becomes the latest action, the
            // CANCEL is never seen again, and the policy renews every cycle
            // forever. Only a REINSTATE/REISSUE can clear a cancel — never a RENEW.
            $latestCancel = PolicyAction::where('policy_id', $policy->id)
                ->where('status', 'ISSUED')
                ->where('transaction_type', 'CANCEL')
                ->whereNull('deleted_at')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($latestCancel && !$this->revivedAfter($policy->id, $latestCancel)) {
                Log::info("Policy {$policy->id}: Cancelled (no reinstate after cancel) — skip.");
                continue;
            }

            // ── Lapsed-policy stop ─────────────────────────────────────────
            // A LAPSED action ends cover at its effective_from. The latest-action
            // test above is defeated once a renewal wrongly stacks on top of the
            // lapse. Only a REINSTATE/REISSUE can revive lapsed cover.
            $latestLapsed = PolicyAction::where('policy_id', $policy->id)
                ->where('status', 'LAPSED')
                ->whereNull('deleted_at')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($latestLapsed && !$this->revivedAfter($policy->id, $latestLapsed)) {
                Log::info("Policy {$policy->id}: Lapsed (no reinstate after lapse) — skip.");
                continue;
            }

            // ── REISSUE / REINSTATE in QUOTE — don't renew until re-issued ──
            if (in_array($latestAction->transaction_type, ['REINSTATE', 'REISSUE'], true)
                && $latestAction->status === 'QUOTE') {
                Log::info("Policy {$policy->id}: {$latestAction->transaction_type} in QUOTE — skip.");
                continue;
            }

            // ── NEWBUSINESS — the anniversary anchor ───────────────────────
            $nbAction = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'NEWBUSINESS')
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->first();

            if (!$nbAction) {
                Log::warning("Policy {$policy->id}: No NEWBUSINESS action — skip.");
                continue;
            }

            // ── Renewable Policy gate ──────────────────────────────────────
            // Only auto-renew when the operative specialist coverage is marked
            // is_renewable = 'Yes'. 'No' / unset -> skip.
            if (!$this->isSpecialistPolicyRenewable((int) $policy->id)) {
                Log::info("Policy {$policy->id}: Renewable Policy = No (or unset) — skip manual-input auto-renew.");
                continue;
            }

            // ── Seed period — the last ISSUED period of insurance in force ──
            // Chronology is (effective_from, id): a batch deleted & recreated
            // later carries a HIGHER id while holding an EARLIER period, so plain
            // id-desc would measure the wrong period.
            $seed = PolicyAction::where('policy_id', $policy->id)
                ->whereIn('transaction_type', self::TERM_TYPES)
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if (!$seed || empty($seed->effective_from) || empty($seed->effective_to)) {
                Log::warning("Policy {$policy->id}: No ISSUED period of insurance to measure — skip.");
                continue;
            }

            $seedFrom = Carbon::parse($seed->effective_from)->startOfDay();
            $seedTo   = Carbon::parse($seed->effective_to)->startOfDay();

            if ($seedTo->lt($seedFrom)) {
                Log::warning("Policy {$policy->id}: Inverted period on action #{$seed->id} ({$seed->effective_from} -> {$seed->effective_to}) — skip.");
                continue;
            }

            // THE MANUAL-INPUT CADENCE: the date difference of the period itself,
            // inclusive of both ends. 01/03/2026 -> 01/04/2026 = 32 days.
            $periodDays = $seedFrom->diffInDays($seedTo) + 1;

            if ($periodDays < 1 || $periodDays > self::MAX_PERIOD_DAYS) {
                Log::warning(
                    "Policy {$policy->id}: period of {$periodDays} day(s) on action #{$seed->id} is outside " .
                    '1-' . self::MAX_PERIOD_DAYS . ' — skip (annual+ periods belong to policy:renew-annual-specialist).'
                );
                continue;
            }

            $policyStartDate = Carbon::parse($nbAction->effective_from)->startOfDay();

            Log::info(
                "Policy {$policy->id}: NB = {$policyStartDate->format('Y-m-d')}, " .
                "seed action #{$seed->id} {$seed->transaction_type} {$seedFrom->format('Y-m-d')} -> {$seedTo->format('Y-m-d')}, " .
                "periodDays = {$periodDays}"
            );

            // ── Anniversary boundaries ─────────────────────────────────────
            $latestAnniversary = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->whereNull('deleted_at')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            $hasAnniversaryIssued = $latestAnniversary && $latestAnniversary->status === 'ISSUED';

            // Earliest PENDING anniversary QUOTE — the schedule's ceiling. Read
            // directly rather than off the latest anniversary's status: a
            // later-dated anniversary of another status would otherwise let the
            // batch run straight through a quote still awaiting UW.
            $pendingAnniversaryQuote = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->where('status', 'QUOTE')
                ->whereNull('deleted_at')
                ->orderBy('effective_from')
                ->first();

            $anniversaryStart = null;
            $anniversaryEnd   = null;

            // Next anniversary boundary. An ISSUED anniversary re-anchors it to
            // the (possibly UW-changed) anniversary start; otherwise it follows
            // the original NEWBUSINESS date.
            $anniversaryAnchor = $hasAnniversaryIssued
                ? Carbon::parse($latestAnniversary->effective_from)->startOfDay()
                : $policyStartDate->copy();

            $years = 1;
            while ($anniversaryAnchor->copy()->addYearsNoOverflow($years)->lte($seedTo)) {
                $years++;
            }
            $anniversaryStopDate = $anniversaryAnchor->copy()->addYearsNoOverflow($years)->subDay();

            if ($hasAnniversaryIssued) {
                $anniversaryStart = Carbon::parse($latestAnniversary->effective_from)->startOfDay();
                $anniversaryEnd   = Carbon::parse($latestAnniversary->effective_to)->startOfDay();
                Log::info(
                    "Policy {$policy->id}: Anniversary ISSUED {$anniversaryStart->format('Y-m-d')} -> " .
                    "{$anniversaryEnd->format('Y-m-d')}, anniversaryStop = {$anniversaryStopDate->format('Y-m-d')}"
                );
            } else {
                Log::info("Policy {$policy->id}: No issued anniversary — anniversaryStop = {$anniversaryStopDate->format('Y-m-d')}");
            }

            // A pending anniversary QUOTE closes the schedule. Nothing may be
            // created on/after its start date until UW issues it — otherwise
            // manual batches stack on top of a quote not yet taken up.
            if ($pendingAnniversaryQuote) {
                $quoteStopDate = Carbon::parse($pendingAnniversaryQuote->effective_from)->startOfDay()->subDay();
                if ($anniversaryStopDate->gt($quoteStopDate)) {
                    $anniversaryStopDate = $quoteStopDate;
                    Log::info("Policy {$policy->id}: pending ANNIVERSARY-RENEW QUOTE #{$pendingAnniversaryQuote->id} — anniversaryStop capped to {$anniversaryStopDate->format('Y-m-d')}.");
                }
            }

            // hardStopDate = min(anniversaryStopDate, today) — CREATE gate only.
            // It does NOT cap a batch's END date: a batch may legitimately run
            // into the future (that is what a period of insurance is).
            $hardStopDate = $anniversaryStopDate->copy();
            if ($hardStopDate->gt($today)) {
                $hardStopDate = $today->copy();
            }

            Log::info("Policy {$policy->id}: hardStop (CREATE gate) = {$hardStopDate->format('Y-m-d')}");

            // ── Cycle loop — day-difference stepping ───────────────────────
            $cursor       = $seedTo->copy()->addDay();
            $createdCount = 0;
            $skippedCount = 0;

            for ($cycle = 1; $cycle <= self::MAX_CYCLES; $cycle++) {

                // Never create anything before the V2 backfill floor.
                if ($cursor->lt($backfillFrom)) {
                    Log::info("Policy {$policy->id}: cursor {$cursor->format('Y-m-d')} before backfill floor — advancing to " . self::BACKFILL_FROM . '.');
                    $cursor = $backfillFrom->copy();
                }

                // A batch may not start inside an ISSUED anniversary period —
                // that period is already covered. Jump past it.
                if ($anniversaryStart && $anniversaryEnd
                    && $cursor->gte($anniversaryStart) && $cursor->lte($anniversaryEnd)) {
                    Log::info("Policy {$policy->id}: cursor {$cursor->format('Y-m-d')} inside anniversary period — advancing to {$anniversaryEnd->copy()->addDay()->format('Y-m-d')}.");
                    $cursor = $anniversaryEnd->copy()->addDay();
                    continue;
                }

                // CREATE gate: only batches whose start date has arrived.
                if ($cursor->gt($hardStopDate)) {
                    Log::info("Policy {$policy->id}: STOP — next batch start {$cursor->format('Y-m-d')} > hardStop {$hardStopDate->format('Y-m-d')}.");
                    break;
                }

                // Natural end from the manual cadence, then the anniversary caps.
                $cycleEnd = $cursor->copy()->addDays($periodDays - 1);

                if ($cycleEnd->gt($anniversaryStopDate)) {
                    $cycleEnd = $anniversaryStopDate->copy();
                }
                // A batch starting before an ISSUED anniversary must stop the day
                // before it, never overlap it.
                if ($anniversaryStart && $cursor->lt($anniversaryStart) && $cycleEnd->gte($anniversaryStart)) {
                    $cycleEnd = $anniversaryStart->copy()->subDay();
                }

                if ($cycleEnd->lt($cursor)) {
                    Log::info("Policy {$policy->id}: STOP — no room left between {$cursor->format('Y-m-d')} and the anniversary boundary.");
                    break;
                }

                $effectiveFrom = $cursor->format('Y-m-d');
                $effectiveTo   = $cycleEnd->format('Y-m-d');

                // ── Overlap / duplicate guard ──────────────────────────────
                // A manual period is arbitrary, so the sibling crons' "one RENEW
                // per calendar month/quarter" key does not apply. Test instead for
                // any live RENEW whose period overlaps this one and resume after
                // it — this is also what makes a re-run idempotent.
                $overlap = PolicyAction::where('policy_id', $policy->id)
                    ->where('transaction_type', 'RENEW')
                    ->whereNull('deleted_at')
                    ->whereDate('effective_from', '<=', $effectiveTo)
                    ->whereDate('effective_to', '>=', $effectiveFrom)
                    ->orderByDesc('effective_to')
                    ->first();

                if ($overlap) {
                    $skippedCount++;
                    $resumeFrom = Carbon::parse($overlap->effective_to)->startOfDay()->addDay();
                    Log::info(
                        "Policy {$policy->id}: SKIP {$effectiveFrom} -> {$effectiveTo} — overlaps existing RENEW " .
                        "#{$overlap->id} ({$overlap->status}, {$overlap->effective_from} -> {$overlap->effective_to}); " .
                        "resuming at {$resumeFrom->format('Y-m-d')}."
                    );
                    // Guaranteed forward progress: overlap.effective_to >= cursor,
                    // so resumeFrom is always > cursor.
                    $cursor = $resumeFrom->gt($cursor) ? $resumeFrom : $cursor->copy()->addDay();
                    continue;
                }

                if ($dryRun) {
                    $createdCount++;
                    Log::info("Policy {$policy->id}: [DRY RUN] would create {$effectiveFrom} -> {$effectiveTo} ({$periodDays} days, cycle {$cycle}).");
                    $cursor = $cycleEnd->copy()->addDay();
                    continue;
                }

                // ── Source for the clone + premium ─────────────────────────
                // The last ISSUED action in force before this batch (any type),
                // so the renewal carries forward the operative coverage set and
                // premium — a UW-adjusted anniversary or a mid-term endorsement —
                // instead of reverting to the original NEWBUSINESS figures.
                $referenceAction = PolicyAction::where('policy_id', $policy->id)
                    ->where('status', 'ISSUED')
                    ->whereNull('deleted_at')
                    ->whereDate('effective_from', '<', $effectiveFrom)
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id')
                    ->first() ?? $seed;

                $policyQuoteNo = $policy->policyNumber . '/' . $cursor->format('d');

                $action = $this->createPolicyActionFromPrevious(
                    $referenceAction,
                    'RENEW',
                    'ISSUED',
                    $policyQuoteNo,
                    $effectiveFrom,
                    $effectiveTo
                );

                // Specialist replicator — carries the ten one-to-one coverage
                // tables (car/par/ear/travel/marine/...) the plain DOM/COM path
                // does not, then price the replicated schedule with the specialist
                // Rate-button recipe (calculatePremiumRenew ignores those tables
                // and would zero the action).
                PolicyAction::newPolicyActionReplaceSpecialist($action, $referenceAction->id);
                PolicyAction::calculatePremiumRenewSpecialist($action->id, $action->term_id, $action->policy_id);

                if ($invoice) {
                    $this->generateInvoice($policy->id, $action->id, $effectiveFrom);
                } else {
                    Log::info("Policy {$policy->id}: action #{$action->id} created WITHOUT an invoice (manual-billing policy; pass --invoice to raise one).");
                }

                $createdCount++;

                $finalData[] = [
                    'policyNumber' => $policy->policyNumber,
                    'issuedDate'   => $cursor->format('d-m-Y'),
                    'totalpolicy'  => 1,
                ];

                $policyModel = Policy::find($policy->id);
                if ($policyModel) {
                    activity('Specialist Manual Auto Renewal')
                        ->performedOn($policyModel)
                        ->log('Specialist manual-input renewal - ' . $policy->policyNumber . ' - ' . $effectiveFrom . ' to ' . $effectiveTo);
                }

                Log::info(
                    "Policy {$policy->id}: CREATED {$effectiveFrom} -> {$effectiveTo} " .
                    "(action #{$action->id}, periodDays: {$periodDays}, cycle: {$cycle})"
                );

                // Next batch starts the day after this one ends.
                $cursor = $cycleEnd->copy()->addDay();
            }

            Log::info("Policy {$policy->id}: DONE — Created: {$createdCount}, Skipped: {$skippedCount}");
            $createdCountTotal += $createdCount;
            $skippedCountTotal += $skippedCount;
        }

        Log::info(
            "SpecialistManualAutoRenew: batch SUMMARY — processed {$totalPoliciesCount} polic(ies), " .
            "createdTotal={$createdCountTotal}, skippedTotal={$skippedCountTotal}, finalDataRows=" . \count($finalData) .
            ($dryRun ? ' [DRY RUN — nothing written]' : '')
        );

        if (!empty($finalData) && !$dryRun) {
            // Wrapped: a storage/mail failure must never fail a run whose
            // renewals were already written — the sibling crons let it throw and
            // the batch then reads as failed when it actually succeeded.
            try {
                $cron->update(['current_step' => 'sending_email']);
                $path = 'Policy/created-' . Carbon::now()->timestamp . '/PoliciesSpecialistRenewManual.pdf';
                $pdf  = PDF::loadView('admin.notes.daily_renew_monthly_policy', ['finalData' => $finalData]);
                Storage::disk('s3')->put($path, $pdf->output(), 'public');

                $cronSendMail = new CronController();
                $cronSendMail->AllCronMail([$path], 'Policies_renew_today', $cron);
            } catch (\Throwable $e) {
                Log::error('SpecialistManualAutoRenew: summary PDF/mail failed (renewals were still created): ' . $e->getMessage());
            }
        }

        $cron->update([
            'current_step' => 'completed',
            'end'          => Carbon::now(),
        ]);

        return 0;
    }

    /**
     * True when a REINSTATE/REISSUE was ISSUED chronologically AFTER the given
     * stop action (CANCEL or LAPSED) — i.e. the stop has been cleared.
     *
     * Chronology is (effective_from, id): a later effective date, or the same
     * date with a higher id. Legacy rows with no effective_from fall back to
     * creation order so the guard stays clearable.
     */
    private function revivedAfter($policyId, $stopAction): bool
    {
        return PolicyAction::where('policy_id', $policyId)
            ->where('status', 'ISSUED')
            ->whereIn('transaction_type', ['REINSTATE', 'REISSUE'])
            ->whereNull('deleted_at')
            ->where(function ($q) use ($stopAction) {
                if (empty($stopAction->effective_from)) {
                    $q->where('id', '>', $stopAction->id);
                    return;
                }
                $q->whereDate('effective_from', '>', $stopAction->effective_from)
                  ->orWhere(function ($same) use ($stopAction) {
                      $same->whereDate('effective_from', '=', $stopAction->effective_from)
                           ->where('id', '>', $stopAction->id);
                  });
            })
            ->exists();
    }

    /**
     * Clone the action header from the action in force. The premium written here
     * is a placeholder only — calculatePremiumRenewSpecialist() re-prices the
     * action from the replicated specialist coverage tables immediately after.
     */
    protected function createPolicyActionFromPrevious($previousAction, $transactionType, $status, $policyQuoteNo, $startDate, $endDate)
    {
        return PolicyAction::create([
            'policy_id'        => $previousAction->policy_id,
            'term_id'          => $previousAction->term_id,
            'premium'          => $previousAction->premium + $previousAction->endorse_premium,
            'transaction_type' => $transactionType,
            'policy_quote_no'  => $policyQuoteNo,
            'effective_from'   => $startDate,
            'effective_to'     => $endDate,
            'status'           => $status,
            'transaction_date' => $startDate,
            'note'             => 'Specialist renewal generated by cron manual input',
        ]);
    }

    /**
     * Raise the three policy_ledger legs + three policy_subledger GL legs for the
     * new action. Same shape as SpecialistMonthlyAutoRenew::generateInvoice, with
     * the frequency gate set to MANUAL INPUT (6) — this cron only ever handles
     * freq-6 policies, and this method is only reached when --invoice is passed.
     */
    public function generateInvoice($policyid, $actionId, $invoicedate)
    {
        try {
            $date = Carbon::now()->format('Y-m-d');

            $policy = Policy::whereIn('product_id', self::SPECIALIST_PRODUCTS)
                ->where('status', 1)
                ->where('id', $policyid)
                ->first();

            if (!$policy) {
                Log::info("generateInvoice: policy {$policyid} is not an active specialist policy — no invoice.");
                return false;
            }

            if ((int) $policy->premium_freq !== self::MANUAL_FREQ) {
                Log::info("generateInvoice: policy {$policyid} premium_freq={$policy->premium_freq} is not MANUAL INPUT — no invoice.");
                return false;
            }

            $policyAction = PolicyAction::where('policy_id', $policy->id)
                ->where('id', $actionId)
                ->where('status', 'ISSUED')
                ->first();

            if (!$policyAction) {
                Log::info("generateInvoice: action {$actionId} not found / not ISSUED — no invoice.");
                return false;
            }

            $termId = $policyAction->term_id ?? 0;

            $alreadyInvoiced = Ledger::where('policy_id', $policy->id)
                ->where('trans_type', 'Invoice')
                ->where('action_id', $policyAction->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyInvoiced) {
                Log::info("generateInvoice: action {$actionId} already invoiced — skip.");
                return false;
            }

            $lastInvoice = Ledger::where('policy_id', $policy->id)
                ->where('trans_type', 'Invoice')
                ->orderByDesc('id')
                ->first(['invoice_no', 'banking_id', 'invoice_date']);

            if ($lastInvoice != null) {
                $invoice_no = $lastInvoice->invoice_no;
                $invoice_no++;
                $banking_id = $lastInvoice->banking_id;
            } else {
                $invoice_no = $policy->policyNumber . '-' . sprintf('%03d', 1);
                $banking    = CustomerBanking::where('customer_id', $policy->customer_id)->first(['id']);
                $banking_id = $banking ? $banking->id : null;
            }

            $lastBalance = Ledger::where('policy_id', $policy->id)->orderByDesc('id')->first(['balance']);
            $balance     = $lastBalance ? $lastBalance->balance : 0;

            $data    = [];
            $subData = [];

            $base = [
                'customer_id'     => $policy->customer_id,
                'account_id'      => null,
                'policy_id'       => $policy->id,
                'term_id'         => $termId,
                'action_id'       => $policyAction->id,
                'claim_id'        => null,
                'banking_id'      => $banking_id,
                'account_name'    => null,
                'accounting_date' => Carbon::parse($date),
                'amount_type'     => null,
                'trans_ref'       => null,
                'orig_trans'      => null,
                'unallocated'     => null,
                'system_date'     => Carbon::parse($date),
                'trans_sub_type'  => null,
                'eff_date'        => Carbon::parse($date),
                'invoice_file'    => null,
                'invoice_date'    => null,
                'invoice_no'      => null,
                'invoice_amount'  => null,
                'premium'         => $policyAction->premium,
                'other_charges'   => null,
                'due_amount'      => null,
                'pmts_adjust'     => null,
                'due_date'        => null,
                'status'          => 'Pending',
                'credit'          => null,
            ];

            $subBase = [
                'customer_id'     => $policy->customer_id,
                'account_id'      => null,
                'policy_id'       => $policy->id,
                'term_id'         => $termId,
                'action_id'       => $policyAction->id,
                'claim_id'        => null,
                'banking_id'      => $banking_id,
                'accounting_date' => Carbon::parse($date),
                'trans_ref'       => $invoice_no,
                'system_date'     => Carbon::parse($date),
            ];

            //------------------------------- PREMIUM -------------------------------
            $premiumExclVat = str_replace(',', '', number_format(((float) $policyAction->premium - (float) $policy->vat), 2));

            $record               = $base;
            $record['trans_type'] = 'Invoice Premium';
            $record['debit']      = $premiumExclVat;
            $balance              = number_format(((float) str_replace(',', '', $balance) - (float) $premiumExclVat), 2);
            $record['balance']    = str_replace(',', '', $balance);
            $data[]               = $record;

            $subData[] = $subBase + [
                'account_name' => 'Insurance Sales A/C',
                'trans_type'   => 'Insurance Premium',
                'credit'       => $premiumExclVat,
                'debit'        => null,
            ];

            //------------------------------- VAT -----------------------------------
            $vatAmt = floatval($policy->vat);

            $record               = $base;
            $record['trans_type'] = 'Invoice VAT';
            $record['debit']      = str_replace(',', '', $vatAmt);
            $balance              = str_replace(',', '', number_format(((float) str_replace(',', '', $balance) - (float) $vatAmt), 2));
            $record['balance']    = str_replace(',', '', $balance);
            $data[]               = $record;

            $subData[] = $subBase + [
                'account_name' => 'VAT Control A/C',
                'trans_type'   => 'VAT on Insurance Premium',
                'credit'       => $vatAmt,
                'debit'        => null,
            ];

            //------------------------------- INVOICE -------------------------------
            $record                   = $base;
            $record['trans_type']     = 'Invoice';
            $record['invoice_file']   = 1;
            $record['invoice_date']   = Carbon::parse($policyAction->effective_from);
            $record['invoice_no']     = $invoice_no;
            $record['invoice_amount'] = $policyAction->premium;
            $record['due_amount']     = $policyAction->premium;
            $record['debit']          = $policyAction->premium;
            $record['balance']        = str_replace(',', '', $balance);
            $data[]                   = $record;

            $subData[] = $subBase + [
                'account_name' => 'Accounts Receivable A/C',
                'trans_type'   => 'Accounts Receivable',
                'credit'       => null,
                'debit'        => $policyAction->premium,
            ];

            Ledger::insert($data);
            SubLedger::insert($subData);
            Log::info("generateInvoice: invoice {$invoice_no} raised for policy {$policy->id} action {$policyAction->id}.");

            return true;
        } catch (\Exception $e) {
            Log::error('Error in generateInvoice method: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }
}
