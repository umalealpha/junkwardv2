<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Policy;
use AlphaDirect\MotorCompExpiry;
use Carbon\Carbon;
use DB;
use AlphaDirect\Models\CronStatus;

class CheckMotorCompExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkmotorcompexpiry:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'checking motor comp policies expiry';

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
     * @return mixed
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "checkmotorcompexpiry:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policies = Policy::where('product_id',3)->where('status','=',1)->get();
        if(count($policies)>0){
            foreach($policies as $policy){
                $motorCompExpiry = new MotorCompExpiry;
                $motorCompExpiry->policy_id =  $policy->id;
                $motorCompExpiry->activation_date =  $policy->policyActivatedDate;
                if (isset($policy->policyActivatedDate)) {
                    if(str_contains($policy->policyActivatedDate, '/')){
                        $expiryDate = Carbon::createFromFormat('d/m/Y', $policy->policyActivatedDate)->format('Y-m-d');
                    } else {
                        $expiryDate = Carbon::parse($policy->policyActivatedDate)->addYear(1)->format('Y-m-d');
                    }
                } else {
                    if (isset($policy->billingStartDate)) {
                        if(str_contains($policy->billingStartDate, '/')){
                            $expiryDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('Y-m-d');
                        } else {
                            $expiryDate = Carbon::parse($policy->billingStartDate)->addYear(1)->format('Y-m-d');
                        }

                    } else {
                        if(str_contains($policy->created_at, '/')){
                            $expiryDate = Carbon::createFromFormat('d/m/Y', $policy->created_at)->format('Y-m-d');
                        } else {
                            $expiryDate = Carbon::parse($policy->created_at)->addYear(1)->format('Y-m-d');
                        }

                    }
                }
                $motorCompExpiry->expiry_date = !empty($expiryDate)?$expiryDate:'';
                $motorCompExpiry->billing_date =  $policy->billingStartDate;
                $motorCompExpiry->save();
            }
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}
