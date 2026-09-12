<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use AlphaDirect\KYC;
use Log;
use AlphaDirect\Models\CronStatus;
class MatiUpdateKycDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'matiUpdateKycDocuments:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update mati kyc documents';

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
        $cron->name = "matiUpdateKycDocuments:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron started to mati update kyc documents');

        $customers = Customer::join('customer_mati','customer_mati.identity_id','customer.mati_identity')
                    ->orderBy('customer.id','DESC')
                    ->get(array(
                        'customer.id',
                        'customer.mati_identity',
                        'customer_mati.passport',
                        'customer_mati.driving_license',
                        'customer_mati.omang',
                        'customer_mati.omangBack',
                        'customer_mati.proof_residence'
                    ));

        foreach ($customers as $key => $customer) {
            $customerKyc = KYC::where('customer_id',$customer->id)->first();
                    $k = null;
            if (isset($customerKyc)) {
                if (!isset($customerKyc->passport)) {
                    $customerKyc->passport = $customer->passport;
                    $k = 1;
                }

                if (!isset($customerKyc->driving_license)) {
                    $customerKyc->driving_license = $customer->driving_license;
                    $k = 1;
                }

                if (!isset($customerKyc->omang)) {
                    $customerKyc->omang = $customer->omang;
                    $k = 1;
                }

                if (!isset($customerKyc->omangBack)) {
                    $customerKyc->omangBack = $customer->omangBack;
                    $k = 1;
                }

                if (!isset($customerKyc->proof_residence)) {
                    $customerKyc->proof_residence = $customer->proof_residence;
                    $k = 1;
                }
                if($k == 1){
                    $customerKyc->performed_by =null;
                    $saved = $customerKyc->save();
                }


            }

        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
