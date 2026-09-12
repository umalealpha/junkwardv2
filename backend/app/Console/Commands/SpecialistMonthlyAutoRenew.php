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
 * Monthly auto-renew for SPECIALIST products only (backend copy).
 *
 * Specialist twin of DomComMonthlyAutoRenew (DOM/COM motor products 7/8).
 * Kept as a separate command so the two crons never overlap and either can be
 * scheduled / triggered independently:
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
    protected $signature = 'SpecialistMonthlyAutoRenew:cron {--policy= : Restrict the run to a single policy (id or policyNumber) — for local/testing. Omit to run the full batch as the scheduler does.}';

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
     * ('Yes' / 'No' / null). Hard-coded to keep the command self-contained —
     * mirrors SPECIALIST_PRODUCTS above.
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
        $cron = new CronStatus();
        $cron->name = "SpecialistMonthlyAutoRenew:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info("SpecialistMonthlyAutoRenew Cron is working fine!");

        try {
            return $this->doHandle($cron);
        } catch (\Throwable $e) {
            // Always close out the cron_status row on failure so the row is
            // observable as "ran but failed" rather than "never finished" —
            // and re-throw so Laravel's scheduler can still log + release
            // its mutex in the finally block of runInForeground().
            Log::error("SpecialistMonthlyAutoRenew Cron failed: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            $cron->end = \Carbon\Carbon::now();
            $cron->save();
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
            ->distinct()
            ->select('p.id', 'p.policyNumber')

            ->join('policy_term as pt', 'pt.policy_id', '=', 'p.id')

            // 1️⃣ Join last ISSUED valid action
            ->join('policy_actions as pa_last', function ($join) {
                $join->on('pa_last.policy_id', '=', 'p.id')
                    ->where('pa_last.status', 'ISSUED')
                    ->whereIn('pa_last.transaction_type', [
                        'NEWBUSINESS',
                        'RENEW',
                        'REISSUE',
                        'REINSTATE'
                    ])
                    ->whereNull('pa_last.deleted_at')
                    ->whereRaw("
                pa_last.effective_from = (
                    SELECT MAX(pa2.effective_from)
                    FROM policy_actions pa2
                    WHERE pa2.policy_id = p.id
                      AND pa2.status = 'ISSUED'
                      AND pa2.transaction_type IN ('NEWBUSINESS','RENEW','REISSUE','REINSTATE')
                      AND pa2.deleted_at IS NULL
                )
            ");
            })

            // Date conditions
            ->where('p.created_at', '>=', '2024-07-01 00:00:00') // Graphite v2 go-live date — keep as-is
            ->where('pa_last.effective_from', '>=', Carbon::now()->subMonths(18)->startOfMonth()->toDateTimeString())
            ->whereRaw('pa_last.effective_to < CURDATE()')

            // 4️⃣ Exclude future renewals
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as pa4')
                    ->whereRaw('pa4.policy_id = p.id')
                    ->where('pa4.transaction_type', 'RENEW')
                    ->where('pa4.status', 'ISSUED')
                    ->whereRaw('pa4.effective_from > CURDATE()')
                    ->whereNull('pa4.deleted_at');
            })

            // 5️⃣ Monthly only
            ->where('p.premium_freq', 1)
            // Specialist products only — disjoint from DOM/COM (7, 8).
            ->whereIn('p.product_id', self::SPECIALIST_PRODUCTS)
            // Optional single-policy filter (local / testing). When --policy is
            // omitted the run is unchanged — all due monthly policies, exactly
            // as the scheduler invokes it. When set, narrow to that id or
            // policyNumber; the eligibility gates above (effective_to < today,
            // etc.) STILL apply, so a policy that isn't actually due won't renew.
            ->when($this->option('policy'), function ($q, $policyRef) {
                $q->where(function ($w) use ($policyRef) {
                    $w->where('p.id', $policyRef)
                      ->orWhere('p.policyNumber', $policyRef);
                });
            })
            ->orderByDesc('p.id')
            ->get();


        //dd('test', $policies);
        $finalData = [];
        foreach ($policies as $policy) {

            // 1️⃣ Latest action safety
            $latestAction = PolicyAction::where('policy_id', $policy->id)
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (
                ($latestAction->transaction_type === 'CANCEL' && $latestAction->status === 'ISSUED') ||
                $latestAction->status === 'LAPSED'
            ) {
                continue;
            }

            // 1️⃣a Pending REISSUE / REINSTATE stop. When the policy's latest
            //      transaction is a REISSUE or REINSTATE still in QUOTE, the policy
            //      is mid-change and not yet re-issued — do NOT create a renewal on
            //      top of it. Once that REISSUE/REINSTATE is ISSUED it becomes the
            //      latest action (no longer QUOTE) and renewals resume normally.
            if (
                $latestAction->status === 'QUOTE' &&
                in_array($latestAction->transaction_type, ['REISSUE', 'REINSTATE'], true)
            ) {
                continue;
            }

            // 1️⃣b Cancelled-policy stop (GRA-0132). The "latest ISSUED action is a
            //      CANCEL" test is defeated as soon as ONE renewal has wrongly been
            //      issued after the cancel: that RENEW becomes the latest ISSUED
            //      action, the CANCEL is never seen again, and the policy renews
            //      every cycle forever. RENEWs can never clear a cancel — only
            //      a REINSTATE/REISSUE can. So look for the latest ISSUED CANCEL and
            //      treat the policy as cancelled unless a REINSTATE/REISSUE was
            //      ISSUED *after* it.
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
                    continue;
                }
            }

            // 1️⃣c Lapsed-policy stop. A LAPSED action — typically an ANNIVERSARY-RENEW
            //      the customer never took up — ends cover at its effective_from. The
            //      "latest action is LAPSED" test above is defeated the moment ONE
            //      renewal wrongly stacks on top of the lapse: that RENEW becomes the
            //      latest action, the LAPSED row is never seen again, and the policy
            //      renews forever (same stacking defect as the cancel stop). Only a
            //      REINSTATE/REISSUE can revive lapsed cover — never a RENEW. So find
            //      the latest LAPSED action and treat the policy as lapsed unless a
            //      REINSTATE/REISSUE was ISSUED *after* it.
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
                    continue;
                }
            }

            // 2️⃣ NEWBUSINESS (policy start)
            $nbAction = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'NEWBUSINESS')
                ->where('status', 'ISSUED')
                ->first();

            if (!$nbAction) {
                continue;
            }

            // Renewable Policy gate — only auto-renew specialist policies whose
            // operative coverage is marked is_renewable = 'Yes'. 'No' / unset → skip.
            if (!$this->isSpecialistPolicyRenewable($policy->id)) {
                Log::info("Policy {$policy->id}: Renewable Policy = No (or unset) — skip specialist auto-renew.");
                continue;
            }

            $policyStartDate = Carbon::parse($nbAction->effective_from);

            // 3️⃣ Latest Anniversary
            $latestAnniversary = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            $hasAnniversaryQuote = $latestAnniversary && $latestAnniversary->status === 'QUOTE';
            $hasAnniversaryIssued = $latestAnniversary && $latestAnniversary->status === 'ISSUED';

            // Latest ISSUED anniversary (even when a newer anniversary QUOTE is
            // pending) carries the operative — possibly UW-changed — period of
            // insurance for the current policy year.
            $latestIssuedAnniversary = $hasAnniversaryIssued
                ? $latestAnniversary
                : PolicyAction::where('policy_id', $policy->id)
                    ->where('transaction_type', 'ANNIVERSARY-RENEW')
                    ->where('status', 'ISSUED')
                    ->whereNull('deleted_at')
                    ->orderBy('effective_from', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

            // 4️⃣ Cursor starts from NEWBUSINESS effective_from — unless an
            // anniversary renewal was ISSUED: when UW changes the period of
            // insurance on the anniversary, all later RENEWs must follow the
            // anniversary dates, not the original NEWBUSINESS schedule.
            $cursor = $policyStartDate->copy();
            $anchorStartDate = $policyStartDate->copy();
            if ($latestIssuedAnniversary) {
                $anchorStartDate = Carbon::parse($latestIssuedAnniversary->effective_from);
                $cursor = $anchorStartDate->copy();
            }

            // Earliest PENDING anniversary QUOTE — the schedule's ceiling. Read
            // it directly instead of testing the status of the latest anniversary
            // row: a later-dated anniversary of another status (stale ISSUED /
            // LAPSED row) made $hasAnniversaryQuote false and let the batch run
            // straight through a quote that was still awaiting UW.
            $pendingAnniversaryQuote = PolicyAction::where('policy_id', $policy->id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->where('status', 'QUOTE')
                ->whereNull('deleted_at')
                ->orderBy('effective_from')
                ->first();

            // 5️⃣ Hard stop date. A pending anniversary QUOTE ends the schedule:
            //     nothing may be created on/after its start date until UW issues
            //     it.
            $hardStopDate = $pendingAnniversaryQuote
                ? Carbon::parse($pendingAnniversaryQuote->effective_from)->subDay()
                : Carbon::today();

            // 6️⃣ Policy-cycle loop (monthly: anchor day bounces back in short months)
            // Compute each cycleStart from the FIXED schedule anchor + month offset
            // (not from a moving cursor) so a 31st/30th-day policy clamps to the
            // short month (28/29/30) and bounces back to its original day the next
            // long month, instead of permanently drifting to the clamped day.
            $originalDay = (int) $anchorStartDate->day;
            $anchorMonth = (int) $anchorStartDate->month;
            $anchorYear  = (int) $anchorStartDate->year;

            for ($offset = 1; $offset <= 300; $offset++) {

                // Next cycle start = anchor + $offset months, day clamped to month length
                $totalMonths = ($anchorYear * 12 + $anchorMonth - 1) + $offset;
                $year        = intdiv($totalMonths, 12);
                $month       = ($totalMonths % 12) + 1;
                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                $day         = min($originalDay, $daysInMonth); // bounces back next month
                $cycleStart  = Carbon::create($year, $month, $day);

                // cycleEnd = next cycle start − 1 day (same clamp)
                $totalMonthsEnd = $totalMonths + 1;
                $eyear          = intdiv($totalMonthsEnd, 12);
                $emonth         = ($totalMonthsEnd % 12) + 1;
                $edaysInMonth   = Carbon::create($eyear, $emonth, 1)->daysInMonth;
                $eday           = min($originalDay, $edaysInMonth);
                $cycleEnd       = Carbon::create($eyear, $emonth, $eday)->subDay();

                // Stop conditions
                if ($cycleStart->gt($hardStopDate)) {
                    break;
                }

                if ($cycleStart->gt(today())) {
                    break;
                }

                $effectiveFrom = $cycleStart->format('Y-m-d');
                $effectiveTo = $cycleEnd->format('Y-m-d');

                // Duplicate protection
                $exists = PolicyAction::where('policy_id', $policy->id)
                    ->whereDate('effective_from', $effectiveFrom)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Never create a renewal overlapping an already-issued period —
                // e.g. NB-aligned rows issued before the anniversary changed the
                // schedule anchor. Such wrong-dated rows must be deleted before
                // the correctly-dated ones can regenerate.
                $overlaps = PolicyAction::where('policy_id', $policy->id)
                    ->where('status', 'ISSUED')
                    ->whereNull('deleted_at')
                    ->whereDate('effective_from', '<=', $effectiveTo)
                    ->whereDate('effective_to', '>=', $effectiveFrom)
                    ->exists();

                if ($overlaps) {
                    continue;
                }

                // Premium / replication SOURCE = the last ISSUED action in force
                // before this renewal (any transaction type). The schedule dates
                // re-anchor to the issued anniversary, but the premium must
                // follow the operative coverage set too — a UW-adjusted
                // anniversary or a mid-term endorsement — instead of reverting
                // to the original NEWBUSINESS figures. Falls back to NEWBUSINESS
                // for the first cycle when nothing else is issued yet.
                //
                // Order by effective_from FIRST, id only as the tie-break: a
                // batch deleted & recreated (or re-issued) later carries a
                // HIGHER id while holding an EARLIER period, so plain id-desc
                // sourced from that older period instead of the true last one.
                // Same (effective_from, id) chronology the DomCom crons use.
                $sourceAction = PolicyAction::where('policy_id', $policy->id)
                    ->where('status', 'ISSUED')
                    ->whereNull('deleted_at')
                    ->whereDate('effective_from', '<', $effectiveFrom)
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id')
                    ->first() ?? $nbAction;

                // 🔁 Create renewal. Every 12th month from the policy start
                // (months 12, 24, …) is the policy ANNIVERSARY, created as an
                // ANNIVERSARY-RENEW *QUOTE* (UW prepares the renewal) and NOT
                // invoiced until issued; every other month is an ordinary
                // monthly RENEW, issued and invoiced. The 12-month count runs
                // from the schedule anchor (issued anniversary when present,
                // else NEWBUSINESS) so a date change on the anniversary moves
                // the next anniversary with it.
                $monthsFromStart = $offset;
                $isAnniversary   = $monthsFromStart > 0 && ($monthsFromStart % 12 === 0);
                $renewType       = $isAnniversary ? 'ANNIVERSARY-RENEW' : 'RENEW';
                $renewStatus     = $isAnniversary ? 'QUOTE' : 'ISSUED';

                $action = $this->createPolicyActionFromPrevious(
                    $sourceAction,
                    $renewType,
                    $renewStatus,
                    $policy->policyNumber . '/' . $cycleStart->format('d'),
                    $effectiveFrom,
                    $effectiveTo
                );

                // Replicate the SPECIALIST source coverage tree (the ten
                // one-to-one tables car/par/ear/travel/marine/… the plain
                // DOM/COM path does not carry), then price the replicated
                // schedule with the specialist Rate-button recipe (the standard
                // calculatePremiumRenew ignores the specialist tables and would
                // zero the action and its invoice).
                PolicyAction::newPolicyActionReplaceSpecialist($action, $sourceAction->id);

                PolicyAction::calculatePremiumRenewSpecialist($action->id, $action->term_id, $action->policy_id);

                // Only issued monthly renewals are invoiced. An anniversary QUOTE
                // must not generate an invoice until the renewal is issued.
                if (!$isAnniversary) {
                    $this->generateInvoice($policy->id, $action->id, $effectiveFrom);
                }

                Log::info(
                    "Policy {$policy->id}: Renewal {$effectiveFrom} → {$effectiveTo}"
                );

                // A freshly-created anniversary QUOTE ends this run. The next
                // monthly period may only be created once UW issues that quote,
                // so never continue the cadence past it. The quarterly commands
                // already stop here; the monthly ones ran straight on and stacked
                // ISSUED RENEWs after the anniversary QUOTE (the pending quote is
                // only read BEFORE the loop, so it could not stop the same run).
                if ($isAnniversary) {
                    $this->say("Policy {$policy->policyNumber}: anniversary QUOTE created — stopping here until it is issued.");
                    break;
                }
            }

            // [GAP-FILL] Patch any pending periods the cadence loop above could
            // not reach — an orphan month BEFORE an off-cadence anniversary and
            // the trailing months AFTER it (see backfillPendingPeriods).
            $this->backfillPendingPeriods($policy, $nbAction, 1);
        }




        if (!empty($finalData)) {
            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'Policy/created-' . $date . '/PoliciesSpecialistRenewMonthly.pdf';
            $data = [
                'finalData' => $finalData
            ];
            $pdf = PDF::loadView('admin.notes.daily_renew_monthly_policy', $data);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
            // Storage::disk('local')->put('public/example.pdf', $pdf->output());

            $attachments = array();
            array_push($attachments, $path);
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'Policies_renew_today';
            $cronSendMail->AllCronMail($attachments, $hook, $cron);

            ////*************Email send new fuction END **************/////
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
        return 0;
    }

    /**
     * [GAP-FILL] Safety net for pending periods the cadence loop above can leave
     * behind. That loop re-anchors to the latest ISSUED period, so it cannot
     * (a) reach back and fill a month orphaned BEFORE an off-cadence anniversary,
     * nor (b) resume cleanly AFTER an anniversary whose UW-set day doesn't match
     * the policy's renewal day. This pass walks the policy's actual covered
     * timeline and creates an ISSUED RENEW for every uncovered slot up to the
     * hard stop, clipping each slot to its neighbouring period so it can never
     * overlap one.
     *
     * SAFETY: skipped entirely for any policy carrying a CANCEL / LAPSED /
     * REINSTATE / REISSUE in its live history — those are INTENTIONAL breaks in
     * cover that must never be auto-filled. Anniversary QUOTE creation stays the
     * cadence loop's job; this pass only ever writes ordinary monthly RENEWs to
     * patch holes around an already-established anniversary.
     *
     * @param object       $policy         batch-query row (id, policyNumber)
     * @param PolicyAction  $nbAction       NEWBUSINESS action (premium fallback)
     * @param int           $intervalMonths 1 = monthly, 3 = quarterly
     */
    private function backfillPendingPeriods($policy, $nbAction, int $intervalMonths): void
    {
        $today = Carbon::today();

        // Intentional break in cover → never auto-fill.
        $hasBreak = PolicyAction::where('policy_id', $policy->id)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereIn('transaction_type', ['CANCEL', 'REINSTATE', 'REISSUE'])
                  ->orWhere('status', 'LAPSED');
            })
            ->exists();
        if ($hasBreak) {
            return;
        }

        // Live coverage periods, oldest first (QUOTE rows included so a pending
        // anniversary quote both blocks a fill over it and is honoured below).
        $periods = PolicyAction::where('policy_id', $policy->id)
            ->whereNull('deleted_at')
            ->whereIn('transaction_type', ['NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE'])
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get(['effective_from', 'effective_to']);
        if ($periods->isEmpty()) {
            return;
        }

        $covered = [];
        foreach ($periods as $p) {
            $from = Carbon::parse($p->effective_from)->startOfDay();
            $to   = Carbon::parse($p->effective_to)->startOfDay();
            if ($to->gte($from)) {
                $covered[] = [$from, $to];
            }
        }
        usort($covered, fn ($a, $b) => $a[0]->timestamp <=> $b[0]->timestamp);

        // Trailing fill stops the day before a pending anniversary QUOTE, else
        // today. EARLIEST pending quote — the first one awaiting UW closes the
        // schedule, so a later pending quote must not raise the ceiling.
        $annivQuote = PolicyAction::where('policy_id', $policy->id)
            ->where('transaction_type', 'ANNIVERSARY-RENEW')
            ->where('status', 'QUOTE')
            ->whereNull('deleted_at')
            ->orderBy('effective_from')
            ->first();
        $trailingStop = $annivQuote
            ? Carbon::parse($annivQuote->effective_from)->subDay()
            : $today->copy();

        // Collect uncovered slots: internal gaps (clipped to the next period) and
        // the trailing gap after the last period (full natural slots while the
        // slot START is on/before the hard stop — a full final period beyond
        // today is intentional, matching the cadence loop).
        $slots = [];
        $guard = 0;
        for ($i = 0; $i < count($covered); $i++) {
            [, $to] = $covered[$i];
            $next   = $covered[$i + 1] ?? null;

            $slotStart = $to->copy()->addDay();
            $gapEnd    = $next ? $next[0]->copy()->subDay() : $trailingStop->copy();

            while ($slotStart->lte($gapEnd) && $slotStart->lte($trailingStop) && $guard < 300) {
                $guard++;
                $naturalEnd = $slotStart->copy()->addMonthsNoOverflow($intervalMonths)->subDay();
                $slotEnd    = ($next && $naturalEnd->gt($gapEnd)) ? $gapEnd->copy() : $naturalEnd->copy();
                $slots[]    = [$slotStart->copy(), $slotEnd->copy()];
                $slotStart  = $slotEnd->copy()->addDay();
            }
        }

        foreach ($slots as [$slotStart, $slotEnd]) {
            $effectiveFrom = $slotStart->format('Y-m-d');
            $effectiveTo   = $slotEnd->format('Y-m-d');

            // Same guards as the cadence loop: never duplicate, never overlap issued.
            $exists = PolicyAction::where('policy_id', $policy->id)
                ->whereDate('effective_from', $effectiveFrom)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                continue;
            }

            $overlaps = PolicyAction::where('policy_id', $policy->id)
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->whereDate('effective_from', '<=', $effectiveTo)
                ->whereDate('effective_to', '>=', $effectiveFrom)
                ->exists();
            if ($overlaps) {
                continue;
            }

            // SOURCE = the last ISSUED action in force before this slot (any
            // type), so it carries the operative coverage set & premium forward.
            // Ordered by effective_from first, id only as the tie-break (a
            // recreated/re-issued batch carries a higher id on an earlier
            // period). Falls back to NEWBUSINESS when nothing else is issued yet.
            $sourceAction = PolicyAction::where('policy_id', $policy->id)
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->whereDate('effective_from', '<', $effectiveFrom)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first() ?? $nbAction;

            $action = $this->createPolicyActionFromPrevious(
                $sourceAction,
                'RENEW',
                'ISSUED',
                $policy->policyNumber . '/' . $slotStart->format('d'),
                $effectiveFrom,
                $effectiveTo
            );

            // Specialist replicator + specialist Rate-button pricing (matches
            // this command's cadence loop — the standard calculatePremiumRenew
            // ignores the specialist tables and would zero the premium).
            PolicyAction::newPolicyActionReplaceSpecialist($action, $sourceAction->id);
            PolicyAction::calculatePremiumRenewSpecialist($action->id, $action->term_id, $action->policy_id);
            $this->generateInvoice($policy->id, $action->id, $effectiveFrom);

            Log::info("Policy {$policy->id}: [GAP-FILL] pending {$effectiveFrom} → {$effectiveTo}");
        }
    }

    protected function findReferenceActionForRenewal($policyId, $startMonth)
    {
        $types = [
            'ANNIVERSARY-RENEW',
            'ENDORSE',
            'RENEW',
            'REISSUE',
            'ENDORSE-RENEW',
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
