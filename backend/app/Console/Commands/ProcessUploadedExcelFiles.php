<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\UploadedExcelFile;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Imports\PoliciesImport;
use AlphaDirect\Models\CompanyPolicy;
use AlphaDirect\Policy;
use AlphaDirect\Exports\CreatedPoliciesExport;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\User;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Helper;
use Carbon\Carbon;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Models\CompanyName;
use PDF;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Product;
use AlphaDirect\Customer;
use AlphaDirect\Documents;
use DB;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\WhatsAppController;

class ProcessUploadedExcelFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:process-uploaded-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes uploaded Excel files and imports data into the database';

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
        $cron->name = "excel:process-uploaded-files";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $files = UploadedExcelFile::where('status', 'pending')->where('remarks','Policy Create')->get();

        if ($files->isEmpty()) {
            $this->info('No files to process.');
            return 0;
        }

        foreach ($files as $file) {
            try {
               
                $this->info("Processing file: {$file->file_name}");
                $upload_id = $file->id;
                Excel::import(new PoliciesImport($upload_id), $file->file_path,'s3');
               
                $file->update([
                    'status' => 'success',
                    'remarks' => 'Batch Policy Create Successfully',
                ]);
                sleep(1);
                $createdPolicies = CompanyPolicy::where('upload_id', $file->id)->get();
                $totalpremium = 0;
                $results = [];
                if (count($createdPolicies)>0) {
                   
                    foreach($createdPolicies as $Companypolicy){
                       $policy = Policy::where('policyNumber',$Companypolicy->policyNumber)->with(['customer', 'profile'])->first();
                       $totalpremium += $policy->premium;
                       $policyStatus = match ($policy->status) {
                        0 => 'Deactivated',
                        1 => 'Policy Activated',
                        2 => 'Cancelled',
                        3 => 'Expired',
                        default => '-',
                    };
                   

                    $results[] = [
                        'policyNumber'=>  $policy->policyNumber,
                        'Premium'=>  $policy->premium,
                        'FirstName'=>isset($policy->customer) ? $policy->customer->firstName : '',
                        'LastName'=>isset($policy->customer) ? $policy->customer->lastName : '',
                        'Customer_Cellphone'=>isset($policy->customer) ? $policy->customer->cellphone : 'N/A',
                        'Email'=> isset($policy->customer) ?  $policy->customer->email : 'N/A',
                        'Omang'=>isset($policy->profile) ? $policy->profile->omang : 'N/A',
                        'Passport'=>isset($policy->profile) ? $policy->profile->passport : 'N/A',
                        'STATUS'=>  $policyStatus,
                        'Created_at'=> $policy->created_at->format('Y-m-d H:i:s'),
                    ];

                   

                    }
                    $pages = "policyNumber,FirstName,LastName,Premium,Customer_Cellphone,Email,Omang,Passport,STATUS,Created_at\n";
                    foreach ($results as $where) {
                        $pages .="{$where['policyNumber']},{$where['FirstName']},{$where['LastName']},{$where['Premium']},{$where['Customer_Cellphone']},{$where['Email']},{$where['Omang']},{$where['Passport']},{$where['STATUS']},{$where['Created_at']},\n"; 
                    } 
                    $attachments = array();
                    $exportFileName = 'file/created_policies_' . now()->format('Y_m_d_H_i_s') . '.csv';
                    Storage::disk('s3')->put($exportFileName, $pages, 'public');

                    $file->report_file = $exportFileName;
                    $file->save();
                    $user = User::where('id',$file->uploaded_by)->first();
                    ;
                    $company = CompanyName::where('id',$createdPolicies[0]->company_id)->first();
                     $Invoicedata = [
                        "company"=>$company,
                        "totalpremium" =>$totalpremium,
                        "premiumExcludingVAT" => $totalpremium - ($totalpremium *0.14),
                        "vat"=> $totalpremium *0.14
                     ];
                    $date = \Carbon\Carbon::now()->timestamp;

                    $InvoicePath = 'Invoice/Created-'.$date.'/Invoice.pdf';

                    libxml_use_internal_errors(true);
                    $pdf = PDF::loadView('admin.companyname.invoice', $Invoicedata);
                    Storage::disk('s3')->put($InvoicePath, $pdf->output(), 'public');
                    $file->invoice = $InvoicePath;
                    $file->save();
                    array_push($attachments, $InvoicePath);      
                    array_push($attachments, $exportFileName);      
                    ////*************Email send new fuction **************/////
                    $user = User::where('id',$file->uploaded_by)->first();

                     if ($user->email != null) {
                        $data = new \stdClass();
                        $data->user_id = $user->id;
                        $data->customer_id = null;
                       
                        $data->hook = 'get_excel_created_policies_report';
                        $data->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                        event(new \AlphaDirect\Events\SendMail($user->email, $emailTemplate->subject, "", $html, null, ['hook' => $data->hook]));
                    }
                    $cronSendMail = new CronController();
                    $hook = 'get_excel_created_policies_report';
                    $cronSendMail->AllCronMail($attachments,$hook,$cron);
                    ////*************Email send new fuction END **************///// 
                    foreach($createdPolicies as $Companypolicy){

                        $policy = Policy::where('policyNumber',$Companypolicy->policyNumber)->with(['customer', 'profile'])->first();
                        $Esent = $this->sendPolicyDocumentByEmail($policy);
                         if($Esent){
                            $Companypolicy->document_send_email = 1;
                           
                        }
                        $WAsent = $this->sendPolicyDocumentByWhatsApp($policy->id);
                         if($WAsent == 1){
                            $Companypolicy->document_send_wa = 1;
                            
                        }
                       $kyc = $this->sendEmailForKyc($policy);
                        if($kyc == 1){
                            $Companypolicy->kyc_email_send = 1;
                        }
                        $Companypolicy->save();
                    }
                }
            } catch (\Exception $e) {
               
                $file->update([
                    'status' => 'failed',
                    'remarks' => 'Batch policy creation failed.',
                ]);

               
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
       
       
    }
    public function sendPolicyDocumentByEmail($policy)
    {
        try{
            $policyController = new PolicyController();
            $sent = $policyController->sendPolicyDocument($policy->id,"System");
            
            return $sent;
        }catch(\Exception $e){
          return false;     
        }


    }
    public function sendPolicyDocumentByWhatsApp($policyId)
    {
        try{
            $policy = Policy::where('id', $policyId)->first();
            $product = Product::where('id', $policy->product_id)->first(array('id', 'has_schedule', 'has_wordings'));
            $customer = Customer::where('id', $policy->customer_id)->first(array('firstName', 'lastName', 'middleName', 'email', 'cellphone'));
            //$docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));
            $docsid = [];
            if(Documents::where('product_id', $policy->product_id)->where('status', 1)->where('plan_id', $policy->plan_id)->where('plan_id','!=',null)->exists()){
             $pid = Documents::where('product_id', $policy->product_id)->where('status', 1)
                                          ->where('plan_id', $policy->plan_id)->get(['id']);
            if(count($pid) > 0){
                foreach($pid as $id){
                    $docsid[] = $id->id;
                }
            }

            $pdi = Documents::where('product_id', -1)->where('status', 1)->get(['id']);
            if(count($pdi) > 0){
                foreach($pdi as $id2){
                    $docsid[] = $id2->id;
                }
            }


            $docs = Documents::whereIn('id', $docsid)->where('status', 1)->get(array('link'));
            }else{
                $docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));
            }

            $attachments = array();
            if ($product->has_wordings == 1) {
                if ($docs != null) {
                    foreach ($docs as $doc) {
                        if ($doc->link)
                            array_push($attachments, $doc->link);
                    }
                }
            }
            if ($product->has_schedule == 1) {
                if ($policy->policyDocument != null) {
                    array_push($attachments, $policy->policyDocument);
                } else {
                    $document = new DocumentController();
                    $path = $document->generatePolicyDocument($policyId);
                    $newPolicy = Policy::where('id',$policy->id)->first();
                    if ($newPolicy->policyDocument != null) {
                        array_push($attachments, $newPolicy->policyDocument);
                    }
                }
            }
            $sent = false;
            foreach ($attachments as $file) {
                $dataDocumentPolicy=[
                    "type"=>"template",
                    "subType"=>"create_policy_document",
                    "mobileNumber"=>'267'.$policy->customer->cellphone,
                    "file"=>Helper::getCloudFrontURL($file),
                    "fileName"=>substr($file,23),
                    "policyNumber"=>$policy->policyNumber,
                    "customer_id"=>$policy->customer_id
 
                ];
                if( $policy->agency_id != 26 ){
                    $WhatsAppController = new WhatsAppController();
                    $sent =   $WhatsAppController->sendMessage($dataDocumentPolicy);
                  
                }
            }
            return 1;
        
           
            
            

        }catch(\Exception $e){
          return false;     
        }


    }
    public function sendEmailForKyc($policy)
    {
        try{
             if(isset($policy->customer) && $policy->customer->email != null){
                        $data = new \stdClass();
                        $data->user_id = null;
                        $data->hook = 'bonu_kyc_email';
                        $data->customer_id = $policy->customer_id;
                        $data->attachment = null;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($policy->customer->email ,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                      
                        
                        return 1;
                    }
            
            return null;
        }catch(\Exception $e){
          return null;     
        }


    }
    
}
