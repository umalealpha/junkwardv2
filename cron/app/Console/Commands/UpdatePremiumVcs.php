<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\Payment\VCS\VcsController;
use AlphaDirect\Policy;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;

class updatePremiumVcs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatePremiumVcs:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Premium VCS';

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
        $cron->name = "updatePremiumVcs:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        try{
            //Fetch al the Realpay contract data with freq: 1 &2 , product comprehensive , policy status!= cancelled.
                $data = Policy::join('transactions','policies.policyNumber','transactions.policyNumber')
                ->where('transactions.status','SUCCESS')
                ->where('policies.premium_freq','1')
                ->where('policies.product_id',3)
                ->where('policies.status',1)
                ->where('policies.policyNumber','=',"MIS2021009758")

                ->orderBy('policies.id','desc')
                ->get(array(
                    'policies.policyNumber',
                    'transactions.referenceNumber',
                    'policies.premium',
                    'policies.id'
                     ));

               /*

            'MIS2021009678'
            'MIS2021009670'
            'MIS2021009655'
            'MIS2021009653'
            'MIS2021009639'
            'MIS2021009616'
            'MIS2021009602'

               */
            if($data != null || count($data) > 0){
                foreach($data as $row){
                    echo  $row->policyNumber;
                    sleep(1);
                            if($row->premium && $row->premium > 0){

                                $grossPremium = $row->premium;

                                $removeService = $grossPremium/1.08; //Remove only in case of monthly frequency

                                $netPremium = $removeService/1.12; //Remove 12% VAT

                                $updatedGrossPremium = $netPremium*1.14; //Add new VAT : 14%

                                $updatedGrossPremium = $updatedGrossPremium*1.08; // Add service Tax 1.08

                                $updatedGrossPremium = number_format($updatedGrossPremium,2);

                                $vcs = new PaymentController();

                                $res = $vcs->editTransaction($row->referenceNumber,str_replace(",","",$updatedGrossPremium));
                                    if($res->status() == 200){

                                        $res1 = DB::table('vat_change_log')->insert(
                                            array(
                                                'payment_method' => "VCS",
                                                'policyNumber' => $row->policyNumber,
                                                'old_value' => (string)str_replace(",","",$grossPremium),
                                                'new_value' => (string)str_replace(",","",$updatedGrossPremium),
                                                'Message' => $row->referenceNumber,
                                                'status' => "Success",
                                            )
                                        );

                                    }else{
                                        DB::table('vat_change_log')->insert(
                                            array(
                                                'payment_method' => "VCS",
                                                'policyNumber' => $row->policyNumber,
                                                'old_value' => (string)$grossPremium,
                                                'new_value' => (string)$updatedGrossPremium,
                                                'Message' => $row->referenceNumber."-". $res->getContent(),
                                                'status' => "Failed",
                                            )
                                        );
                                    }

                            }else{
                                DB::table('vat_change_log')->insert(
                                    array(
                                        'payment_method' => "VCS",
                                        'policyNumber' => $row->policyNumber,
                                        'old_value' => "",
                                        'new_value' => "",
                                        'Message' => "Error in fetching data",
                                        'status' => "Failed",
                                    )
                                );
                            }

                        }

            }
        }catch(\Exception $e){
            DB::table('vat_change_log')->insert(
                array(
                    'payment_method' => "VCS",
                    'Message' => $e->getMessage(),
                    'status' => "Failed",
                )
            );
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
