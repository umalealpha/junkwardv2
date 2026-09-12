<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Claim;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Exports\CustomerExport;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Country;
use AplhaDirect\countries;
use AlphaDirect\State;
use AlphaDirect\City;
use AlphaDirect\cities;
use AlphaDirect\CustomerMati;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Product;
use AlphaDirect\sentPolicyDocumentLogs;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\AgentKyc;
use AlphaDirect\Config;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use Auth;
use Hash;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Mpdf\Tag\Input;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use Yajra\DataTables\DataTables;
use AlphaDirect\KycFields;
use AlphaDirect\KycActivityLogs;
use AlphaDirect\KycCompliance;
use Image;
use Modules\Cashback\Entities\Policies;
use File;
use AlphaDirect\Models\OrangeMandate;
use AlphaDirect\Models\CronMail;

class CronController extends Controller
{
    public function create()
    {
        if (auth::user()->hasPermissionTo('customer-list')) {
           
            return view("admin.cron.create");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function store(Request $request)
    {
       //dd($request->all());
        if (auth::user()->hasPermissionTo('customer-list')) {
            $production_emails = [];
            $development_emails = [];
            $productions = $request->production_emails;
            foreach($productions as $proemail){
                if($proemail != null){
                    $production_emails[] = $proemail;  
                }
            }
            $development = $request->development_emails;
            foreach($development as $devemail){
                if($devemail != null){
                    $development_emails[] = $devemail;  
                }
            }
            $cronmail =  CronMail::updateOrCreate([
                'id'        => $request->id,
            ], [
                "cron_name" => $request->cron_name,
                "production_emails" =>json_encode($production_emails),
                "development_emails" => json_encode($development_emails),
                "status" => 1,
            ]);

          
              
            return view("admin.cron.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function index()
    {
        if (auth::user()->hasPermissionTo('customer-list')) {
           
            return view("admin.cron.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function crondata()
    {
        if (auth::user()->hasPermissionTo('customer-list')) {
      

               $cronmail = CronMail::all();
        
        
               return DataTables::of($cronmail)
                  
                        ->editColumn('production_emails', function ($cronmail) {
                            if($cronmail->production_emails){
                                $prEmails = '';
                               foreach(json_decode($cronmail->production_emails) as $proemail){
                                $prEmails .= $proemail .',<br>';
                               }
                                
                            }else{
                                $prEmails = '-';
                            }
                            return $prEmails;
                        })
                        ->editColumn('development_emails', function ($cronmail) {
                            if($cronmail->development_emails){
                                $prEmails = '';
                               foreach(json_decode($cronmail->development_emails) as $proemail){
                                $prEmails .= $proemail .',<br>';
                               }
                                
                            }else{
                                $prEmails = '-';
                            }
                            return $prEmails;
                        })
                    ->editColumn('status', function ($cronmail) {
                               if($cronmail->status == 1){
                
                                   return "Activated";
                               }else{
                                   return 'Deactivated';
                               }
                           })

                   
                   ->addColumn('actions', function ($cronmail) {
                       $actions = '';
                       if (auth::user()->can('customer-edit')) {
                           $actions .= '<a href="' . route('admin.cron.edit', $cronmail->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                       <i class="la la-edit"></i>
                                   </a>';
                       } else {
                           $actions .= '<a href="' . route('admin.cron.edit', $cronmail->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                       <i class="flaticon-eye"></i>
                                   </a>';
                       }
                       if (auth::user()->can('customer-delete')) {
                           $actions .= '<a href="" value="' . $cronmail->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                       <i class="la la-trash"></i>
                                   </a>';
                       }
                       return $actions;
                   })
                   ->rawColumns(['actions','status','production_emails','development_emails'])
                   ->make(true);
          
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function edit($id)
    {
      
        if (auth::user()->hasPermissionTo('customer-list')) {
            $cronmail = CronMail::where('id',$id)->first();
            
            if($cronmail){

                return view("admin.cron.edit",compact('cronmail'));
            }else{
                return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have cron mail');
            }
           
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function delete($id)
    {
       
        if (auth::user()->hasPermissionTo('customer-list')) {
            $res= CronMail::find($id)->delete();

            return \Illuminate\Support\Facades\Redirect::back()->with('success', 'Delete');
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function cronModelDelete(Request $request)
    {
            $cronmail = CronMail::where('id',$request->get('id'))->first();
            if($cronmail){
                $body = 'Are you sure you want to delete '.$cronmail->cron_name.' Cron Mails data ?';
            }else{
                $body = 'Are you sure you want to delete this Cron Mails data ?';
            }
           
            return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }

    public function AllCronMail($attachments,$hook,$cron){

        $cronmail = CronMail::where('status',1)->where('cron_name',$cron->name)->first();
        $cronmail2 = CronMail::where('status',1)->where('cron_name','default')->first();

        if(isset($cronmail) && $cronmail != null){
            if(env('APP_STATUS') == 'Production') {
                $email = json_decode($cronmail->production_emails);
            }else{
                $email = json_decode($cronmail->development_emails);
            }
        }elseif(isset($cronmail2) && $cronmail2 != null){
            if(env('APP_STATUS') == 'Production') {
                $email = json_decode($cronmail2->production_emails);
            }else{
                $email = json_decode($cronmail2->development_emails);
            }
            
        }else{
            $email = ['kkatolkar@alphadirect.co.bw'];
        }

     
    if(count($email) > 0 ) {
        foreach($email as $d){
            if($d){
                $data = new \stdClass();
                $data->user_id = null;
                $data->hook = $hook;
                $data->customer_id = null;
                $data->attachment = $attachments;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
             //   $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
            }
        }
        $cron->mail_send = 1;
        $cron->save();
    }
  }
}
