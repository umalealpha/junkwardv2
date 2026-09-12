<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\UploadedExcelFile;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Imports\ExcelPolicyCancellation;
use AlphaDirect\Models\CompanyPolicy;
use AlphaDirect\Policy;
use AlphaDirect\Exports\CreatedPoliciesExport;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\User;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\CustomerFeedback;

class PoliciesCancellation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excelpolicyCancellation:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $cron->name = "excelpolicyCancellation:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $files = UploadedExcelFile::where('status', 'pending')->where('remarks','Policy Cancellation')->get();

        if ($files->isEmpty()) {
            $this->info('No files to process.');
            return 0;
        }

        foreach ($files as $file) {
            try {
               
                $this->info("Processing file: {$file->file_name}");
                $upload_id = $file->id;
                Excel::import(new ExcelPolicyCancellation($upload_id), $file->file_path,'s3');
                $this->info("all policy Cancellation: {$file->file_name}");
               
                $file->update([
                    'status' => 'success',
                    'remarks' => 'Batch policy has been successfully canceled',
                ]);
                $createdPolicies = CompanyPolicy::where('cancel_file_id', $file->id)->get(['policyNumber']);
                $results = [];
                if (count($createdPolicies)>0) {
                   
                    foreach($createdPolicies as $Companypolicy){
                       $policy = Policy::where('policyNumber',$Companypolicy->policyNumber)->with('customer')->first();
                       $policyStatus = match ($policy->status) {
                        0 => 'Deactivated',
                        1 => 'Policy Activated',
                        2 => 'Cancelled',
                        3 => 'Expired',
                        default => '-',
                    };
                    
                    $feedback = CustomerFeedback::where('policy_id',$policy->id)
                    ->orderby('id','desc')->first();
                    if($feedback){
                        $Reason = "other :".$feedback->circumstances;
                    }else{
                        $Reason = "other";
                    }
                    $results[] = [
                            'policyNumber'=>  $policy->policyNumber,
                            'Premium'=>  $policy->premium,
                            'Customer_Cellphone'=> $policy->customer->cellphone ?? 'N/A',
                            'Email'=>  $policy->customer->email ?? 'N/A',
                            'STATUS'=>  $policyStatus,
                            'Cancelled_at'=> \Carbon\Carbon::parse($policy->updated_at)->format('Y-m-d H:i:s'),
                            'Reason'=> $Reason,
                        ];

                    }
                    $pages = "policyNumber,Premium,Customer_Cellphone,Email,STATUS,Cancelled_at,Reason\n";
                    foreach ($results as $where) {
                        $pages .="{$where['policyNumber']},{$where['Premium']},{$where['Customer_Cellphone']},{$where['Email']},{$where['STATUS']},{$where['Cancelled_at']},{$where['Reason']},\n"; 
                    } 
            
                    $exportFileName = 'file/Cancellation_policies_' . now()->format('Y_m_d_H_i_s') . '.csv';
                    Storage::disk('s3')->put($exportFileName, $pages, 'public');

                    $file->report_file = $exportFileName;
                    $file->save();
                    $attachments = array();
                    array_push($attachments, $exportFileName);      
                    ////*************Email send new fuction **************/////
                    $user = User::where('id',$file->uploaded_by)->first();

                //     if ($user->email != null) {
                //        $data = new \stdClass();
                //        $data->user_id = $user->id;
                //        $data->customer_id = null;
                      
                //        $data->hook = 'get_excel_cancellation_policies_report';
                //        $data->attachment = $attachments;
                //        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                //        $markdown = new MailTemplate($data);
                //        $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                //        event(new \AlphaDirect\Events\SendMail($user->email, $emailTemplate->subject, "", $html, null, ['hook' => $data->hook]));
                //    }
                    $cronSendMail = new CronController();
                    $hook = 'get_excel_cancellation_policies_report';
                    $cronSendMail->AllCronMail($attachments,$hook,$cron);
                    ////*************Email send new fuction END **************///// 

                }
                
               

                $this->info("File processed successfully: {$file->file_name}");
            } catch (\Exception $e) {
               
                $file->update([
                    'status' => 'failed',
                    'remarks' => 'Batch policy cancellation failed',
                ]);

                $this->error("Failed to process file: {$file->file_name} - {$e->getMessage()}");
            }
        }

        
    $cron->end = \Carbon\Carbon::now();
    $cron->save();
    return 1;
    }
}
