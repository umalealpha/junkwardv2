<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\KYC;
use DateTime;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\CronStatus;
class KycComplianceUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kyccomplianceupdate:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update kyc compliance';

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
        $cron->name = "kyccomplianceupdate:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
       // return Command::SUCCESS;
       Log::info('Cron started to kyc compliance update');

       $kyc_data= KYC::where('status','Approve')->get();
       if($kyc_data != null){
           foreach($kyc_data as $data){
               if($data->approved_date != null){
                   $current_date = new DateTime(Carbon::now()->format('Y-m-d'));
                   $omangExpiry = Carbon::parse($data->omangExpiry)->format('Y-m-d');
                   $passportExpiry = Carbon::parse($data->passportExpiry)->format('Y-m-d');
                   $licenseExpiry = Carbon::parse($data->licenseExpiry)->format('Y-m-d');
                   $approve_date= new DateTime($data->approved_date);
                   $kyc_update=Kyc::find($data->id);
                   if($current_date == $omangExpiry){
                       $kyc_update->compliance=0;
                       $kyc_update->status='Renew';
                       $kyc_update->approved_date=null;
                       $kyc_update->save();
                   }

                   if($current_date == $passportExpiry){
                       $kyc_update->compliance=0;
                       $kyc_update->status='Renew';
                       $kyc_update->approved_date=null;
                       $kyc_update->save();
                   }

                   if($current_date == $licenseExpiry){
                       $kyc_update->compliance=0;
                       $kyc_update->status='Renew';
                       $kyc_update->approved_date=null;
                       $kyc_update->save();
                   }
                   $interval = $current_date->diff($approve_date);
                   $days=$interval->format('%a');

                   if($days > 365){
                       $kyc_update->compliance=0;
                       $kyc_update->status='Renew';
                       $kyc_update->approved_date=null;
                       $kyc_update->save();
                   }
               }
           }
       }else{
           echo 'Nothing to update';
       }
        $cron->end = \Carbon\Carbon::now();
       $cron->save();
    }
}
