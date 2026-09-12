<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\CronStatus;
use DB;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Exports\ExcelExportCustomerAgeMoreThan65;
use AlphaDirect\Exports\ExcelExportlegalCustomerAgeMoreThan65;
use Maatwebsite\Excel\Facades\Excel;

class PolicyWhereCustomerAgeMoreThan65 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policywherecustomeragemorethan65:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policy Where Customer Age MoreThan 65';

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
        $cron->name = "policywherecustomeragemorethan65:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyAdiId = [];
        $policyLegelId = [];
        /*******************ADI*******************/
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-1');
        $adipolicys = Policy:: leftJoin('customer_profile','customer_profile.customer_id','policies.customer_id')
                     ->where('policies.product_id',1)->where('policies.status',1)
                     ->where('customer_profile.dob','!=',null)->where(function($query){
                       $query->where(DB::raw("(DATE_FORMAT(customer_profile.dob,'%Y-%m-%d'))"),'<=',Carbon::parse('today')->subYear(65)->format('Y-m-d'))
                     ->orWhere(DB::raw("(DATE_FORMAT(customer_profile.dob,'%Y-%m-%d'))"),'>=',Carbon::parse('today')->subYear(18)->format('Y-m-d'));
                       })->orderBy('policies.id','DESC')->get(['policies.id']);
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-2');
                if($adipolicys->count() > 0){
                    foreach($adipolicys as  $adipolicy){
                        $policyAdiId[] =   $adipolicy->id;
                    }
                }
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-3');
                 $AdiDuplicatepolicy = Policy::whereIn('id',$policyAdiId)->orderBy('customer_id','DESC')->get();
                // $report = [
                //     'policies' => $AdiDuplicatepolicy,
                //     'title'    => 'Adi Policy Customer Age Over 65 Years Reports'
                // ];
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-4');
                // $date = \Carbon\Carbon::now()->timestamp;
                // $path = 'policies-'.$date.'/adi_policy_where_customer_age_morethan_65.pdf';
                // libxml_use_internal_errors(true);
                // $pdf = PDF::loadView('admin.notes.policy_where_customer_age_morethan_65', $report);
                $date = \Carbon\Carbon::now()->timestamp;
                $path = 'adi_policy_where_customer_age_morethan_65-'.$date.'.xls';
                $exportData =  Excel::store(new ExcelExportCustomerAgeMoreThan65($AdiDuplicatepolicy), $path,'s3');

    Log::info('Cron Started policywherecustomeragemorethan65:cron step-5');
                //Storage::disk('s3')->put($path, $pdf->output(), 'public');
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-6');
                $attachments = array();
                array_push($attachments, $path);

        /*******************LEGAL*******************/
        $Legalpolicys = Policy:: leftJoin('customer_profile','customer_profile.customer_id','policies.customer_id')
                ->where('policies.product_id',4)->where('policies.status',1)
                ->where('customer_profile.dob','!=',null)
                ->where(function($query){
                  $query->where(DB::raw("(DATE_FORMAT(customer_profile.dob,'%Y-%m-%d'))"),'>=',Carbon::parse('today')->subYear(18)->format('Y-m-d'));
              
                  })->orderBy('policies.id','DESC')->get(['policies.id']);
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-7');
                if($Legalpolicys->count() > 0){
                    foreach($Legalpolicys as  $Legalpolicy){
                        $policyLegelId[] =   $Legalpolicy->id;
                    }
                }
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-8');
                $LegalDuplicatepolicy = Policy::whereIn('id',$policyLegelId)->orderBy('customer_id','DESC')->get();
                // $report = [
                  
                //     'policiesLegal' => $LegalDuplicatepolicy,
                //     'title'    => 'Legal Policy Customer Age Over 65 Years Reports'
                // ];
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-9');
        //    $date = \Carbon\Carbon::now()->timestamp;
        //    $lpath = 'policies-'.$date.'/legal_policy_where_customer_age_morethan_65.pdf';
        //    libxml_use_internal_errors(true);
        //    $pdf = PDF::loadView('admin.notes.policy_where_customer_age_morethan_65', $report);
        $date = \Carbon\Carbon::now()->timestamp;
                $lpath = 'legal_policy_where_customer_age_morethan_65-'.$date.'.xls';
                $exportData =  Excel::store(new ExcelExportlegalCustomerAgeMoreThan65($LegalDuplicatepolicy), $lpath,'s3');
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-10');
           //Storage::disk('s3')->put($lpath, $pdf->output(), 'public');
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-11');
         
           array_push($attachments, $lpath);
      
        if(count($AdiDuplicatepolicy) > 0 || count($LegalDuplicatepolicy) > 0){
        ////*************Email send new fuction **************/////
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-mail-start');
            $cronSendMail = new CronController();
            $hook = 'policy_where_customer_age_morethan_65_admin';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
    Log::info('Cron Started policywherecustomeragemorethan65:cron step-mail-end');
        ////*************Email send new fuction END **************/////
        }
    Log::info('Cron Started policywherecustomeragemorethan65:cron finished');
       $cron->end = \Carbon\Carbon::now();
       $cron->save();
        return 1;
    }
}
