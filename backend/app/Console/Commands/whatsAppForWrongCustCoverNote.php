<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Models\whatsAppModel;
use AlphaDirect\Models\TempWrongCoverNoteData;

class whatsAppForWrongCustCoverNote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsAppForWrongCustCoverNote:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'whats App Message For Wrong Customer CoverNote';

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
        Log::info('Cron Started for processing Whats App Meesage Cover Note.');
        $cron = new CronStatus();
        $cron->name = "whatsAppForWrongCustCoverNote:cron";
        $cron->start = \Carbon\Carbon::now();
         $cron->save();

         $jobs = TempWrongCoverNoteData::get();
       
        foreach($jobs as $job)
        {
            if(isset($job->cellphone)){
                if($job->plan_id == 4){

                    $dataCreatePolicy =[
                        "type"=>"template",
                        "subType"=>"cover_note49_customer_wrong",//"cover_note_customer_wrong",
                        "mobileNumber"=>'267'.$job->cellphone ,
                        "policyNumber"=>$job->policyNumber
                    ];
                    $WhatsAppController=  new WhatsAppController();
                    $logs=$WhatsAppController->sendMessage($dataCreatePolicy);
 
                    Log::info("message send for ".$job->policyNumber." on".$job->cellphone);
 
                    $update = TempWrongCoverNoteData::where('id', $job->id)->update(['updated_whatsApp' => 1,
                    'updated_by_whatsApp' => \Carbon\Carbon::now()]);



                }else if($job->plan_id == 16){
                    $dataCreatePolicy =[
                        "type"=>"template",
                        "subType"=>"cover_note_customer_wrong",//"cover_note_customer_wrong",
                        "mobileNumber"=>'267'.$job->cellphone ,
                        "policyNumber"=>$job->policyNumber
                    ];

                    Log::info("message send for ".$job->policyNumber." on".$job->cellphone);


                    $WhatsAppController=  new WhatsAppController();
                    $WhatsAppController->sendMessage($dataCreatePolicy);
                    $update = TempWrongCoverNoteData::where('id', $job->id)->update(['updated_whatsApp' => 1,
                    'updated_by_whatsApp' => \Carbon\Carbon::now()]);
                 }
            }
            sleep(1);
        }
    

         $cron->end = \Carbon\Carbon::now();
         $cron->save();
 
    }
}
