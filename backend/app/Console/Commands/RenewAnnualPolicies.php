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
    protected $signature = 'policy:renew-annual {--policy= : Restrict the run to a single policy (id or policyNumber) — for local/testing. Omit to run the full batch as the scheduler does.}';
    protected $description = 'Renew annual policies if eligible';
    /**
     * The console command description.
     *
     * @var string
     */

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
     * @return int
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "policy:renew-annual";
        $cron->start = \Carbon\Carbon::now();
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
        $ninetyDaysFromNow = Carbon::today()->addDays(90);

        // Single-policy manual run (DOM/COM Batch Renew screen passes --policy=).
        // When set, the 90-day "prior 3 months" window is bypassed so an
        // operator can renew a MISSED anniversary at any time. The full
        // scheduled batch (no --policy) keeps the 90-day window untouched.
        $singlePolicy = $this->option('policy');

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
            ->where('p.status', '!=', 2)
            // V2 go-live floors + 90-day window: applied only on the full
            // scheduled batch. A single-policy manual run (Batch Renew screen)
            // bypasses ALL three so an operator can catch a MISSED anniversary
            // on a legacy/early policy at any time — otherwise a July-2024
            // policy (effective_from before the 2024-08-01 floor) could never
            // be renewed from the screen even though it is genuinely due.
            ->when(empty($singlePolicy), function ($q) {
                $q->whereDate('p.created_at', '>=', '2024-07-01')
                  ->whereDate('last_action.effective_from', '>=', '2024-08-01')
                  ->whereRaw('CURDATE() >= DATE_SUB(DATE_ADD(last_action.effective_from, INTERVAL 1 YEAR), INTERVAL 90 DAY)');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as renew')
                    ->whereRaw('renew.policy_id = p.id')
                    ->where('renew.transaction_type', 'ANNIVERSARY-RENEW')
                    ->whereNull('renew.deleted_at')
                    ->whereIn('renew.status', ['QUOTE', 'ISSUED'])
                    ->whereRaw('renew.id > last_action.id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('policy_actions as paRenew')
                    ->whereRaw('paRenew.policy_id = p.id')
                    ->where('paRenew.transaction_type', 'ANNIVERSARY-RENEW')
                    ->whereNull('paRenew.deleted_at')
                    ->where('paRenew.status', '!=', 'CANCEL')
                    ->whereRaw('YEAR(paRenew.effective_from) = YEAR(DATE_ADD(last_action.effective_from, INTERVAL 1 YEAR))');
            })
            ->select(
                'p.id as policy_id',
                'p.policyNumber',
                'last_action.transaction_type as last_transaction',
                'last_action.status',
                'last_action.effective_from',
                'last_action.effective_to',
                DB::raw('DATE_ADD(last_action.effective_from, INTERVAL 1 YEAR) as next_anniversary_date'),
                DB::raw("
            CASE 
                WHEN p.premium_freq = 1 THEN 'Monthly'
                WHEN p.premium_freq = 5 THEN 'Quarterly'
                WHEN p.premium_freq = 3 THEN 'Annual'
                ELSE 'NA'
            END as premium_type
        ")
            )
            //->whereIn('p.id', $ids)
            // Optional single-policy filter (local / testing). When --policy is
            // omitted the run is unchanged — all eligible policies, exactly as the
            // scheduler invokes it. When set, narrow to that id or policyNumber;
            // the eligibility gates above (90-day window, no existing anniversary,
            // etc.) STILL apply, so a policy that isn't actually due won't renew.
            ->when($this->option('policy'), function ($q, $policyRef) {
                $q->where(function ($w) use ($policyRef) {
                    $w->where('p.id', $policyRef)
                      ->orWhere('p.policyNumber', $policyRef);
                });
            })
            ->orderByDesc('p.id')
            ->get();

       // dd(count($policyData));
        Log::info('start cron here Anniversary');
        $finalData = array();
        foreach ($policyData as $policy) {

            // 0️⃣ Cancelled-policy stop (GRA-0132). The SQL filter excludes
            //     p.status = 2, but a policy cancelled via an ISSUED CANCEL action
            //     whose status flag lags would still slip through. The old per-action
            //     check ("latest ISSUED action is a CANCEL") is ALSO defeated once a
            //     renewal has wrongly been issued after the cancel — that renewal
            //     becomes the latest ISSUED action and hides the cancel forever. Look
            //     for the latest ISSUED CANCEL and treat the policy as cancelled
            //     unless a REINSTATE/REISSUE was ISSUED *after* it; only a
            //     reinstatement can clear a cancel, never a renewal.
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
                    continue;
                }
            }

            // 0️⃣a Lapsed-policy stop. A LAPSED action — typically an ANNIVERSARY-RENEW
            //     the customer never took up — ends cover at its effective_from. As with
            //     the cancel stop above, a "latest action is LAPSED" test is defeated once
            //     a renewal wrongly stacks on top of the lapse: that RENEW becomes the
            //     latest action and hides the LAPSED row forever. Only a REINSTATE/REISSUE
            //     can revive lapsed cover — never a RENEW. Find the latest LAPSED action
            //     and treat the policy as lapsed unless a REINSTATE/REISSUE was ISSUED
            //     *after* it.
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
                    continue;
                }
            }

            // 1️⃣ Get last ISSUED base action — the latest ISSUED term-establishing
            //    action (NEWBUSINESS, ANNIVERSARY-RENEW, REISSUE or REINSTATE).
            //    Ordering by id DESC (not effective_from) makes a backdated/earlier
            //    anniversary still win on its higher id, and lets a cancelled-and-
            //    reissued policy re-anchor on its REISSUE/REINSTATE (the live term)
            //    instead of silently following the original NEWBUSINESS day.
            $baseAction = PolicyAction::where('policy_id', $policy->policy_id)
                ->whereIn('transaction_type', ['ANNIVERSARY-RENEW', 'REISSUE', 'REINSTATE', 'NEWBUSINESS'])
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->orderBy('effective_from', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$baseAction) {
                continue;
            }

            // 2️⃣ Calculate anniversary dates (+1 year)
            $anniversaryFrom = Carbon::parse($baseAction->effective_from)
                ->addYear()
                ->startOfDay();

            $anniversaryTo = Carbon::parse($baseAction->effective_to)
                ->addYear()
                ->endOfDay();

            // 3️⃣ Allow creation only 90 days before anniversary.
            //    Skipped on a single-policy manual run so a missed anniversary
            //    can be created at any time from the Batch Renew screen.
            $allowedCreateFrom = $anniversaryFrom->copy()->subDays(90);

            if (empty($singlePolicy) && today()->lt($allowedCreateFrom)) {
                continue; // ❌ Too early
            }

            // 4️⃣ HARD STOP: same ANNIVERSARY date already exists (any status)
            $sameDateExists = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->whereDate('effective_from', $anniversaryFrom)
                ->whereNull('deleted_at')
                ->exists();

            if ($sameDateExists) {
                continue;
            }

            // 5️⃣ HARD STOP: only ONE anniversary per YEAR
            $yearExists = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')
                ->whereYear('effective_from', $anniversaryFrom->year)
                ->whereNull('deleted_at')
                ->exists();

            if ($yearExists) {
                continue;
            }

            // 6️⃣ HARD STOP: NEWBUSINESS date conflict
            $newBusinessConflict = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('transaction_type', 'NEWBUSINESS')
                ->whereDate('effective_from', $anniversaryFrom)
                ->whereNull('deleted_at')
                ->exists();

            if ($newBusinessConflict) {
                continue;
            }

            // 7️⃣ Get last premium source. Seed value only — calculatePremiumRenew
            //    below recomputes premium off the cloned tree and overwrites it.
            //    ENDORSE stays excluded here because an endorsement's own premium
            //    is a pro-rata delta, not a full-period figure.
            //    Bounded to periods that start BEFORE the anniversary and ordered
            //    (effective_from, id): without the id tie-break two actions on the
            //    same date came back in undefined order.
            $lastPremiumAction = PolicyAction::where('policy_id', $policy->policy_id)
                ->where('transaction_type', '!=', 'ENDORSE')
                ->whereDate('effective_from', '<', $anniversaryFrom)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if (!$lastPremiumAction) {
                continue;
            }

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

            // 8️⃣ Decide status (QUOTE before anniversary)
            $status = today()->lt($anniversaryFrom) ? 'QUOTE' : 'ISSUED';

            // 9️⃣ Create ANNIVERSARY
            $policyQuoteNo = $policy->policyNumber . '/' . $anniversaryFrom->format('d');

            $anniversary = PolicyAction::create([
                'policy_id' => $policy->policy_id,
                'term_id' => $baseAction->term_id,
                'premium' => $lastPremiumAction->premium,
                'transaction_type' => 'ANNIVERSARY-RENEW',
                'policy_quote_no' => $policyQuoteNo,
                'effective_from' => $anniversaryFrom,
                'effective_to' => $anniversaryTo,
                'transaction_date' => $anniversaryFrom,
                'status' => 'QUOTE',
                'note' => 'Anniversary Renewal generated by cron (90 days prior)',
            ]);

            // 🔁 Clone + calculate premium. Clone from the action in force at the
            // anniversary date ($sourceAction), NOT from the date anchor.
            PolicyAction::newPolicyAction($anniversary, $sourceAction->id);

            PolicyAction::calculatePremiumRenew(
                $anniversary->id,
                $baseAction->term_id,
                $policy->policy_id
            );

            Log::info("ANNIVERSARY created | Policy {$policy->policy_id} | {$anniversaryFrom}");
        }



        $dateStart = Carbon::today();
        $dateEnd = Carbon::tomorrow();
        //if(count($finalData) != 0){
        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Policy/PoliciesAnniversaryRenew-' . $date . '.pdf';
        $data = [
            'finalData' => $finalData
        ];
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.daily_renew_annual_policy', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //Storage::disk('local')->put('public/example.pdf', $pdf->output());

        $attachments = array();
        array_push($attachments, $path);
        ////*************Email send new fuction **************/////
        $cronSendMail = new CronController();
        $hook = 'Policies_annual_renew_today';
        $cronSendMail->AllCronMail($attachments, $hook, $cron);
        //}
        ////*************Email send new fuction END **************/////
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
        // echo 'Annual policy renewal task completed.';
        $this->info('Annual policy renewal task completed.');
        return 0;
    }

}