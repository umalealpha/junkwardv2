<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use Log;
class PolicyRenewUpdateData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policyRenewUpdateData:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updating term data and term status';

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
        $cron->name = "policyRenewUpdateData:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started for updating term data and term status.');

        $policy_renewal = PolicyRenewal::where('is_renewed', 1)->where('renew_completed', 0)->get('policy_id');
        $terms = PolicyTerm::distinct('policy_id')->whereIn('policy_id',$policy_renewal)->orderBy('id', 'desc')->get(array('policy_id'));
        $currentDate = Carbon::now()->format('Y-m-d');

        $deactiveOldTerm = PolicyTerm::whereDate('term_end_date','<', $currentDate)->where('status','Active')->update(['status' => 'Deactive']);

        foreach ($terms as $key => $term) {

            $active = PolicyTerm::where('policy_id', $term->policy_id)->whereDate('term_start_date','<=', $currentDate)->whereDate('term_end_date','>', $currentDate)->where('status','Deactive')->first();
            // dd($active);
            if ($active != NULL) {

                $deactive = PolicyTerm::where('policy_id', $term->policy_id)->whereDate('term_end_date', '<', $currentDate)->update(['status' => 'Deactive']);

                $active->status = 'Active';
                $active->save();

                $policyDetails = Policy::where('id',$term->policy_id)->first();
                $policyDetails->term_id = $active->id;
                $policyDetails->premium = $active->premium;
                $policyDetails->annual_premium = $active->annual_premium;
                $policyDetails->first_premium = $active->first_premium;
                $policyDetails->premium_freq = $active->frequency;
                $policyDetails->policyActivatedDate = $active->policyActivatedDate;
                $policyDetails->billingStartDate = $active->billing_start_date;
                $policyDetails->term_start_date = $active->term_start_date;
                $policyDetails->term_end_date = $active->term_end_date;

                $policyRenewal = PolicyRenewal::where('policyNumber',$policyDetails->policyNumber)->where('is_renewed',1)->where('renew_completed', 0)->orderBy('id','desc')->first();
                if (isset($policyRenewal)) {
                    $policyDetails->expiry_date = $active->term_end_date;
                    $policyDetails->sum_assured = $policyRenewal->sum_assured;
                } else {
                    $policyDetails->expiry_date = NULL;
                    $policyDetails->sum_assured = NULL;
                }

                $policyDetails->save();
                // dd( $policyDetails->save());

                $deactiveTermId = PolicyTerm::where('policy_id', $term->policy_id)->orderBy('id', 'desc')->skip(1)->take(1)->first();

                $policy = new PolicyController();
                // $oldVehicleData = $policy->getOldVehicleImages($policyDetails->id,$deactiveTermId->id);
                // if ($oldVehicleData == true) {
                //     $vehicle = Vehicle::where('policy_id',$policyDetails->id)->first();
                //     $vehicle->front = NULL;
                //     $vehicle->back = NULL;
                //     $vehicle->left = NULL;
                //     $vehicle->right = NULL;
                //     $vehicle->vehicleRegistration = NULL;
                //     $vehicle->vehicle_valuation = NULL;
                //     $vehicle->save();
                // }

                $document = new DocumentController();
                $generatePolicyDocument = $document->generatePolicyDocument($policyDetails->id);

                if ($generatePolicyDocument == true) {
                    $sentBy = 'System';
                    $getDocument =  $document->sendPolicyDocumentForRenew($policyDetails->id,$sentBy);
                }

                $renewal = PolicyRenewal::where('is_renewed', 1)->where('policy_id', $term->policy_id)->where('renew_completed', 0)->first();
                if (isset($renewal)) {
                    $renewal->renew_completed = 1;
                    $renewal->save();
                }
            }
        }
           $cron->end = \Carbon\Carbon::now();
           $cron->save();

    }
}
