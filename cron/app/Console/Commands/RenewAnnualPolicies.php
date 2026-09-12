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


class RenewAnnualPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:renew-annual';
    protected $description = 'Renew annual policies if eligible';

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
     * Execute the console command.
     *
     * ANNIVERSARY RENEW CRON — ALL CASES HANDLED
     * ─────────────────────────────────────────────────────────────────────
     * Handles ALL premium frequencies (Monthly=1, Quarterly=5, Annual=3, Semiannual=4)
     *
     * RULES:
     *  [R1] One anniversary per policy year — no duplicates even if dates change
     *  [R2] Always created in QUOTE status, 90 days before anniversary date
     *  [R3] Safe date math — handles Feb 28/29, months with 30/31 days
     *  [R4] Populates $finalData for email reporting
     *  [R5] Skips cancelled/lapsed policies
     *  [R6] No conflict with existing NEWBUSINESS dates
     *  [R7] Uses last ISSUED base (NEWBUSINESS or ANNIVERSARY-RENEW) for date chain
     *  [R8] Uses last ISSUED action (any type) for premium reference
     * ─────────────────────────────────────────────────────────────────────
     *
     * @return int
     */
    public function handle()
    {
        \Log::withContext(['cron' => 'policy:renew-annual']);

        // Last-line-of-defence: catches fatal errors (E_ERROR, E_PARSE, E_COMPILE_ERROR,
        // E_CORE_ERROR, OOM) that escape the try/catch below.
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err !== null && \in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR], true)) {
                \Log::error("RenewAnnualPolicies FATAL: {$err['message']} @ {$err['file']}:{$err['line']}");
            }
        });

        $cron = new CronStatus();
        $cron->name = "policy:renew-annual";
        $cron->start = Carbon::now();
        $cron->save();

        try {
            return $this->doHandle($cron);
        } catch (\Throwable $e) {
            // Always close out the cron_status row on failure so the row is
            // observable as "ran but failed" rather than "never finished" —
            // and re-throw so Laravel's scheduler can still log + release
            // its mutex in the finally block of runInForeground().
            \Log::error("RenewAnnualPolicies Cron failed: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            $cron->end = Carbon::now();
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

        // ──────────────────────────────────────────────────────────────────
        // Fetch eligible policies — ALL frequencies (no premium_freq filter)
        // Base = latest ISSUED NEWBUSINESS or ANNIVERSARY-RENEW
        // ──────────────────────────────────────────────────────────────────
        $policyData = DB::table('policies as p')
            ->joinSub(function ($query) {
                $query->from('policy_actions as pa1')
                    ->select(
                        'pa1.policy_id',
                        'pa1.transaction_type',
                        'pa1.effective_from',
                        'pa1.effective_to',
                        'pa1.id',
                        'pa1.status'
                    )
                    ->where('pa1.status', 'ISSUED')
                    // Anchor on the latest ISSUED term-establishing action. A
                    // cancelled-and-reissued policy re-anchors on its REISSUE/
                    // REINSTATE (new live term), not the original NEWBUSINESS day.
                    ->whereIn('pa1.transaction_type', ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE'])
                    ->whereNull('pa1.deleted_at')
                    ->whereRaw('pa1.id = (
                        SELECT MAX(pa2.id)
                        FROM policy_actions AS pa2
                        WHERE pa2.policy_id = pa1.policy_id
                          AND pa2.status = "ISSUED"
                          AND pa2.transaction_type IN ("NEWBUSINESS", "ANNIVERSARY-RENEW", "REISSUE", "REINSTATE")
                          AND pa2.deleted_at IS NULL
                    )');
            }, 'last_action', 'last_action.policy_id', '=', 'p.id')
            ->whereIn('p.product_id', [7, 8])
            ->whereDate('p.created_at', '>=', '2024-07-01')
            ->whereDate('last_action.effective_from', '>=', '2024-08-01')
            ->where('p.status', '!=', 2)
            // [1] 90-DAY WINDOW — only policies whose anniversary is within 90 days
            ->whereRaw('CURDATE() >= DATE_SUB(DATE_ADD(last_action.effective_from, INTERVAL 1 YEAR), INTERVAL 90 DAY)')
            // [2+5] PENDING + ALREADY DONE — no ANNIVERSARY-RENEW (any status) for this anniversary year
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as pa_ann')
                    ->whereRaw('pa_ann.policy_id = p.id')
                    ->where('pa_ann.transaction_type', 'ANNIVERSARY-RENEW')
                    ->whereNull('pa_ann.deleted_at')
                    ->whereRaw('YEAR(pa_ann.effective_from) = YEAR(DATE_ADD(last_action.effective_from, INTERVAL 1 YEAR))');
            })
            // [3] CANCELLED — last policyAction is CANCEL + ISSUED
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as pa_cancel')
                    ->whereRaw('pa_cancel.policy_id = p.id')
                    ->whereNull('pa_cancel.deleted_at')
                    ->where('pa_cancel.transaction_type', 'CANCEL')
                    ->where('pa_cancel.status', 'ISSUED')
                    ->whereRaw('pa_cancel.id = (
                        SELECT MAX(pc.id) FROM policy_actions pc
                        WHERE pc.policy_id = p.id AND pc.deleted_at IS NULL
                    )');
            })
            // [4] LAPSED — last policyAction status is LAPSED
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as pa_lapsed')
                    ->whereRaw('pa_lapsed.policy_id = p.id')
                    ->whereNull('pa_lapsed.deleted_at')
                    ->where('pa_lapsed.status', 'LAPSED')
                    ->whereRaw('pa_lapsed.id = (
                        SELECT MAX(pl.id) FROM policy_actions pl
                        WHERE pl.policy_id = p.id AND pl.deleted_at IS NULL
                    )');
            })
            ->select(
                'p.id as policy_id',
                'p.policyNumber',
                'p.premium_freq',
                'last_action.transaction_type as last_transaction',
                'last_action.status',
                'last_action.effective_from',
                'last_action.effective_to',
                'last_action.id as last_action_id'
            )
            ->orderByDesc('p.id')
            ->get();
         //  dd(count($policyData));
        Log::info("Anniversary Renew Cron started. Eligible policies: " . count($policyData));

        $finalData = [];
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($policyData as $policy) {

            // ──────────────────────────────────────────────────────────────
            // Get base action record — the latest ISSUED term-establishing
            // action (NEWBUSINESS, ANNIVERSARY-RENEW, REISSUE or REINSTATE).
            // Ordering by id DESC (not effective_from) makes a backdated/earlier
            // anniversary still win on its higher id, and lets a cancelled-and-
            // reissued policy re-anchor on its REISSUE/REINSTATE (the live term)
            // instead of silently following the original NEWBUSINESS day.
            // ──────────────────────────────────────────────────────────────
            $baseAction = PolicyAction::where('policy_id', $policy->policy_id)
                ->whereIn('transaction_type', ['ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE', 'NEWBUSINESS'])
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first()
                ?: PolicyAction::where('id', $policy->last_action_id)->first();

            if (!$baseAction) {
                $skippedCount++;
                continue;
            }

            // ──────────────────────────────────────────────────────────────
            // [R5] Latest action safety — skip cancelled/lapsed
            // ──────────────────────────────────────────────────────────────
            $latestAction = PolicyAction::where('policy_id', $policy->policy_id)
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            // [R5] No action or lapsed — skip outright.
            if (!$latestAction || $latestAction->status === 'LAPSED') {
                $skippedCount++;
                Log::info("Policy {$policy->policy_id}: No action / Lapsed — skip.");
                continue;
            }

            // [R5] Cancelled-policy stop (GRA-0132). A bogus RENEW issued after a
            // cancel becomes the latest action and hides the cancel, so the policy
            // renews forever. Find the latest ISSUED CANCEL and skip unless a
            // REINSTATE/REISSUE was ISSUED *after* it.
            $latestCancel = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('status', 'ISSUED')
                ->where('transaction_type', 'CANCEL')
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestCancel) {
                $reinstatedAfterCancel = PolicyAction::where('policy_id', $policy->policy_id)
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
                    $skippedCount++;
                    Log::info("Policy {$policy->policy_id}: Cancelled (no reinstate after cancel) — skip.");
                    continue;
                }
            }

            // [R5b] Lapsed-policy stop. A LAPSED action — typically an ANNIVERSARY-RENEW
            // the customer never took up — ends cover at its effective_from. As with the
            // cancel stop, a "latest action is LAPSED" test is defeated once a renewal
            // wrongly stacks on top of the lapse: that RENEW becomes the latest action
            // and hides the LAPSED row forever. Only a REINSTATE/REISSUE can revive
            // lapsed cover — never a RENEW. Find the latest LAPSED action and skip unless
            // a REINSTATE/REISSUE was ISSUED *after* it.
            $latestLapsed = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('status', 'LAPSED')
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestLapsed) {
                $revivedAfterLapse = PolicyAction::where('policy_id', $policy->policy_id)
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
                    $skippedCount++;
                    Log::info("Policy {$policy->policy_id}: Lapsed (no reinstate after lapse) — skip.");
                    continue;
                }
            }

            // ──────────────────────────────────────────────────────────────
            // [R3] Safe anniversary date calculation
            // addYearNoOverflow handles Feb 29 → Feb 28, Jan 31 → Jan 31 etc.
            // ──────────────────────────────────────────────────────────────
            $baseFrom = Carbon::parse($baseAction->effective_from);
            $baseTo   = Carbon::parse($baseAction->effective_to);

            $anniversaryFrom = $baseFrom->copy()->addYearNoOverflow()->startOfDay();
            $anniversaryTo   = $baseTo->copy()->addYearNoOverflow()->endOfDay();

            // ──────────────────────────────────────────────────────────────
            // [R2] Allow creation only 90 days before anniversary
            // ──────────────────────────────────────────────────────────────
            $allowedCreateFrom = $anniversaryFrom->copy()->subDays(90);

            if ($today->lt($allowedCreateFrom)) {
                $skippedCount++;
                Log::info("Policy {$policy->policy_id}: Too early — anniversary {$anniversaryFrom->format('Y-m-d')}, allowed from {$allowedCreateFrom->format('Y-m-d')}");
                continue;
            }

            // ──────────────────────────────────────────────────────────────
            // [R1] Check if anniversary already exists (QUOTE or ISSUED)
            // ──────────────────────────────────────────────────────────────
            $existingAnniversary = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->where('id', '>', $baseAction->id)
                ->whereNull('deleted_at')
                ->whereIn('status', ['QUOTE', 'ISSUED'])
                ->first();

            if ($existingAnniversary) {
                // Already exists — add to report as PENDING if QUOTE, skip creation
                if ($existingAnniversary->status === 'QUOTE') {
                    $finalData[] = [
                        'policyNumber' => $policy->policyNumber,
                        'issuedDate'   => Carbon::parse($existingAnniversary->effective_from)->format('d-m-Y'),
                        'totalpolicy'  => 'Pending (QUOTE)',
                    ];
                    Log::info("Policy {$policy->policy_id}: PENDING QUOTE anniversary on {$existingAnniversary->effective_from}");
                } else {
                    Log::info("Policy {$policy->policy_id}: SKIP — ISSUED anniversary already exists #{$existingAnniversary->id}");
                }
                $skippedCount++;
                continue;
            }

            // ──────────────────────────────────────────────────────────────
            // [R6] HARD STOP: NEWBUSINESS date conflict
            // ──────────────────────────────────────────────────────────────
            $newBusinessConflict = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('transaction_type', 'NEWBUSINESS')
                ->whereDate('effective_from', $anniversaryFrom)
                ->whereNull('deleted_at')
                ->exists();

            if ($newBusinessConflict) {
                $skippedCount++;
                Log::info("Policy {$policy->policy_id}: SKIP — NEWBUSINESS conflict on {$anniversaryFrom->format('Y-m-d')}");
                continue;
            }

            // ──────────────────────────────────────────────────────────────
            // [R8] Get premium from last ISSUED action (any relevant type)
            // ──────────────────────────────────────────────────────────────
            // Bounded to periods that start BEFORE the anniversary and ordered
            // (effective_from, id): without the id tie-break two actions on the
            // same date came back in undefined order. Seed value only —
            // calculatePremiumRenew below recomputes premium off the cloned tree.
            $lastPremiumAction = PolicyAction::where('policy_id', $policy->policy_id)
                ->whereIn('transaction_type', [
                    'NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW',
                    'ENDORSE', 'REISSUE', 'REINSTATE', 'ENDORSE-RENEW'
                ])
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->whereDate('effective_from', '<', $anniversaryFrom)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if (!$lastPremiumAction) {
                $skippedCount++;
                Log::warning("Policy {$policy->policy_id}: No premium reference action found — skip.");
                continue;
            }

            // ──────────────────────────────────────────────────────────────
            // [R2] Always create as QUOTE — user issues manually
            // ──────────────────────────────────────────────────────────────
            $policyQuoteNo = $policy->policyNumber . '/' . $anniversaryFrom->format('d');

            $anniversary = PolicyAction::create([
                'policy_id'        => $policy->policy_id,
                'term_id'          => $baseAction->term_id,
                'premium'          => $lastPremiumAction->premium,
                'transaction_type' => 'ANNIVERSARY-RENEW',
                'policy_quote_no'  => $policyQuoteNo,
                'effective_from'   => $anniversaryFrom,
                'effective_to'     => $anniversaryTo,
                'transaction_date' => $anniversaryFrom,
                'status'           => 'QUOTE',
                'note'             => 'Anniversary Renewal generated by cron (90 days prior)',
            ]);

            // Replication SOURCE = the last ISSUED action in force before the
            // anniversary, ANY transaction type (ENDORSE included), ordered by
            // effective_from then id. The anniversary must carry the cover that
            // was actually operative the day before it starts.
            //
            // $baseAction is the DATE anchor (latest term-establishing action by
            // id) and stays untouched — but it is not a data source: it skips
            // ENDORSE entirely, so an anniversary created by this cron AFTER a
            // mid-term endorsement cloned pre-endorsement cover and had to be
            // repaired by hand with Refresh Endorsement. Falls back to
            // $baseAction when nothing else is issued yet.
            $sourceAction = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->whereDate('effective_from', '<', $anniversaryFrom)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first() ?? $baseAction;

            // Clone coverages + calculate premium
            PolicyAction::newPolicyAction($anniversary, $sourceAction->id);

            PolicyAction::calculatePremiumRenew(
                $anniversary->id,
                $baseAction->term_id,
                $policy->policy_id
            );

            $createdCount++;

            // [R4] Add to finalData for email report
            $finalData[] = [
                'policyNumber' => $policy->policyNumber,
                'issuedDate'   => $anniversaryFrom->format('d-m-Y'),
                'totalpolicy'  => 1,
            ];
            $policies = Policy::find($policy->policy_id);
         activity('Monthly Auto Renewal')
                ->performedOn($policies)
                ->log('Monthly renewal - '. $policy->policyNumber.' - '.$anniversaryFrom->format('Y-m-d').' to '.$anniversaryTo->format('Y-m-d'));
       

            Log::info("ANNIVERSARY created | Policy {$policy->policy_id} | {$policyQuoteNo} | From: {$anniversaryFrom->format('Y-m-d')} | To: {$anniversaryTo->format('Y-m-d')} | Premium: {$lastPremiumAction->premium}");
        }

        Log::info("Anniversary Renew Cron completed. Created: {$createdCount}, Skipped: {$skippedCount}");

        // ──────────────────────────────────────────────────────────────────
        // [R4] Generate PDF report and send email
        // ──────────────────────────────────────────────────────────────────
        $date = Carbon::now()->timestamp;
        $path = 'Policy/PoliciesAnniversaryRenew-' . $date . '.pdf';
        $data = ['finalData' => $finalData];
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.daily_renew_annual_policy', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = [$path];
        $cronSendMail = new CronController();
        $hook = 'Policies_annual_renew_today';
        $cronSendMail->AllCronMail($attachments, $hook, $cron);

        $cron->end = Carbon::now();
        $cron->save();

        $this->info("Annual policy renewal task completed. Created: {$createdCount}, Skipped: {$skippedCount}");
        return 0;
    }
}
