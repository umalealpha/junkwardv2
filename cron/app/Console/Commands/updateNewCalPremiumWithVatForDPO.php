<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\DPODummyTransactions;
use AlphaDirect\DPOPremiumDummyTransactions;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Region;
use AlphaDirect\ScheduleTransaction;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;

class updateNewCalPremiumWithVatForDPO extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateNewCalPremiumWithVatForDPO:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update New Calculated Premium With Vat For DPO';

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
        $cron->name = "updateNewCalPremiumWithVatForDPO:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to update premium for dpo');

        $records = DPOPremiumDummyTransactions::orderBy('id','desc')->get();
        if (isset($records)) {
            foreach ($records as $key => $record) {
                // $record->policyNumber = 'MIS2022035715';
                $policy = Policy::where('policyNumber',$record->policyNumber)->first();
                if (isset($policy)) {
                    $product = Product::where('id', $policy->product_id)->first(array('region_id'));
                    $region_vat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

                    $policyPremium = $policy->premium;
                    if($policy->premium_freq == 1)
                        $policyPremium = $policyPremium/1.08; //Remove only in case of monthly frequency
                    $policyPremium = $policyPremium/1.12;

                    $premiumWithoutVAT = $policyPremium;

                    $policyPremium = $premiumWithoutVAT*(1 + ($region_vat/100)); //Add new VAT : 14%
                    if($policy->premium_freq == 1)
                        $policyPremium = $policyPremium*1.08; // Add service Tax 1.08
                    $policy->premium = number_format($policyPremium, 2);
                    $policy->vat = number_format($policyPremium - $premiumWithoutVAT, 2);
                    // dd($policy->premium . ' and ' . $policy->vat);
                    $policy->save();

                    $scheduleds = ScheduleTransaction::where('policy_number',$record->policy_number)->get();
                    if (isset($scheduleds)) {
                        foreach ($scheduleds as $key => $scheduled) {
                            $scheduled->premium = number_format($policyPremium, 2);
                            $scheduled->save();
                        }
                    }
                }
            }
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}
