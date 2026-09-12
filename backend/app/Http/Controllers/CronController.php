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
use AlphaDirect\Models\CronKernel;

class CronController extends Controller
{
    public function create()
    {
        if (auth::user()->hasPermissionTo('Cron_Mail_create')) {
           
            return view("admin.cron.create");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function store(Request $request)
    {
       //dd($request->all());
        if (auth::user()->hasPermissionTo('Cron_Mail_create')) {
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
                "status" => $request->status,
            ]);

          
              
            return view("admin.cron.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function index()
    {
        if (auth::user()->hasPermissionTo('Cron_Mail_list')) {
           
            return view("admin.cron.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function crondata()
    {
        if (auth::user()->hasPermissionTo('Cron_Mail_list')) {
      

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
                       if (auth::user()->can('Cron_Mail_edit')) {
                           $actions .= '<a href="' . route('admin.cron.edit', $cronmail->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                       <i class="la la-edit"></i>
                                   </a>';
                       } 
                       if (auth::user()->can('Cron_Mail_delete')) {
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
      
        if (auth::user()->hasPermissionTo('Cron_Mail_edit')) {
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
       
        if (auth::user()->hasPermissionTo('Cron_Mail_delete')) {
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
    public function kernelcreate()
    {
        if (auth::user()->hasPermissionTo('Cron_Mail_create')) {
           
            return view("admin.cronkernel.create");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function kernelstore(Request $request)
    {
       //dd($request->all());
        if (auth::user()->hasPermissionTo('Cron_Mail_create')) {
       
            $cronmail =  CronKernel::updateOrCreate([
                'id'        => $request->id,
            ], [
                "cron_name" => $request->cron_name,
                "run_type" =>$request->run_type,
                "run_time" => $request->run_time,
                "run_on_server"=>$request->run_on_server,
                "status" => $request->status,
            ]);

          
              
            return view("admin.cronkernel.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function kernelindex()
    {
        if (auth::user()->hasPermissionTo('Cron_Mail_list')) {
           
            return view("admin.cronkernel.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function cronkerneldata()
    {
        if (auth::user()->hasPermissionTo('Cron_Mail_list')) {
      

               $cronkernel = CronKernel::all();
        
        
               return DataTables::of($cronkernel)
                  
                        ->editColumn('run_type', function ($cronkernel) {
                            if($cronkernel->run_type){
                               
                                $run_type = $cronkernel->run_type;
                            }else{
                                $run_type = '-';
                            }
                            return $run_type;
                        })
                        ->editColumn('run_time', function ($cronkernel) {
                            if($cronkernel->run_time){
                               if($cronkernel->run_type == "Hourly"){
                                   $run_time = '-';
                               }else{
                                $run_time = $cronkernel->run_time;
                               }
                                
                            }else{
                                $run_time = '-';
                            }
                            return $run_time;
                        })
                    ->editColumn('status', function ($cronkernel) {
                               if($cronkernel->status == 1){
                
                                   return "Activated";
                               }else{
                                   return 'Deactivated';
                               }
                           })
                    ->editColumn('run_on_server', function ($cronkernel) {
                            if($cronkernel->run_on_server == "bw_server" ){
             
                                return "Graphite Main Server";
                            }else{
                                return 'Cron Server';
                            }
                        })
                   
                   ->addColumn('actions', function ($cronkernel) {
                       $actions = '';
                       if (auth::user()->can('Cron_Mail_edit')) {
                           $actions .= '<a href="' . route('admin.cronkernel.edit', $cronkernel->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                       <i class="la la-edit"></i>
                                   </a>';
                       } 
                       if (auth::user()->can('Cron_Mail_delete')) {
                           $actions .= '<a href="" value="' . $cronkernel->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                       <i class="la la-trash"></i>
                                   </a>';
                       }
                       return $actions;
                   })
                   ->rawColumns(['actions','status','run_type','run_time','run_on_server'])
                   ->make(true);
          
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function kerneledit($id)
    {
      
        if (auth::user()->hasPermissionTo('Cron_Mail_edit')) {
            $cronkernel = CronKernel::where('id',$id)->first();
            
            if($cronkernel){

                return view("admin.cronkernel.edit",compact('cronkernel'));
            }else{
                return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have cron commond');
            }
           
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function kerneldelete($id)
    {
       
        if (auth::user()->hasPermissionTo('Cron_Mail_delete')) {
            $res= CronKernel::find($id)->delete();

            return \Illuminate\Support\Facades\Redirect::back()->with('success', 'Delete');
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function cronkernelModelDelete(Request $request)
    {
      
            $cronmail = CronKernel::where('id',$request->get('id'))->first();
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

  /**
   * Show the cron cancellation page (Super Admin only)
   */
  public function cancelCronPage()
  {
      if (!auth()->user()->hasRole('Super Admin')) {
          return redirect()->back()->with('error', 'Sorry! You do not have permission to access this page!');
      }
      
      return view("admin.cron.cancel");
  }

  /**
   * Cancel/Deactivate a cron job by command name (Super Admin only)
   */
  public function cancelCron(Request $request)
  {
      if (!auth()->user()->hasRole('Super Admin')) {
          return redirect()->back()->with('error', 'Sorry! You do not have permission to access this page!');
      }

      $request->validate([
          'cron_name' => 'required|string'
      ]);

      $cronName = $request->input('cron_name');
      
      // Find the cron by name
      $cron = CronKernel::where('cron_name', $cronName)->first();
      
      if (!$cron) {
          return redirect()->back()->with('error', 'Cron job with name "' . $cronName . '" not found!');
      }

      // Deactivate the cron by setting status to 0
      $cron->status = 0;
      $cron->save();

      return redirect()->back()->with('success', 'Cron job "' . $cronName . '" has been successfully cancelled/deactivated!');
  }

    // ─── Report Stakeholders (Finance Report Email Management) ──────────────

    public function stakeholdersIndex()
    {
        if (!auth::user()->hasPermissionTo('Cron_Mail_list')) {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

        $rows = DB::table('report_stakeholders')
            ->orderBy('report_type')
            ->orderBy('id')
            ->get();

        $reportTypes = [
            '__all__'               => '⭐ Master Recipients (receives ALL reports)',
            'written_premium'       => 'Written Premium (Monthly)',
            'ageing'                => 'Debtors Ageing (Weekly)',
            'anomaly'               => 'Finance Anomaly (Daily)',
            'premium_anomaly'       => 'Premium Anomaly (Wednesday)',
            'payment_anomaly'       => 'Payment Anomaly (Daily)',
            'kyc_compliance_report' => 'KYC Compliance (Tuesday)',
            'reinsurance_anomaly'   => 'Reinsurance Anomaly (Friday)',
            'policy_audit'          => 'Policy Audit (Daily)',
            'collections_report'    => 'Collections Report (Monday)',
            'claims_anomaly'        => 'Claims Anomaly (Thursday)',
        ];

        $grouped = [];
        foreach ($reportTypes as $key => $label) {
            $grouped[$key] = [
                'label'        => $label,
                'stakeholders' => $rows->where('report_type', $key)->values(),
            ];
        }

        return view('admin.cron.stakeholders', compact('grouped'));
    }

    public function stakeholdersStore(Request $request)
    {
        if (!auth::user()->hasPermissionTo('Cron_Mail_list')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'report_type' => 'required|string|max:100',
            'name'        => 'nullable|string|max:200',
            'email'       => 'required|email|max:200',
        ]);

        $exists = DB::table('report_stakeholders')
            ->where('report_type', $request->report_type)
            ->where('email', strtolower(trim($request->email)))
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'This email is already added for this report type.'], 422);
        }

        $id = DB::table('report_stakeholders')->insertGetId([
            'report_type' => $request->report_type,
            'name'        => $request->name ? trim($request->name) : null,
            'email'       => strtolower(trim($request->email)),
            'active'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'stakeholder' => DB::table('report_stakeholders')->find($id),
        ]);
    }

    public function stakeholdersToggle(Request $request, $id)
    {
        if (!auth::user()->hasPermissionTo('Cron_Mail_list')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $row = DB::table('report_stakeholders')->find($id);
        if (!$row) return response()->json(['error' => 'Not found'], 404);

        $newActive = $row->active ? 0 : 1;
        DB::table('report_stakeholders')->where('id', $id)->update([
            'active'     => $newActive,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'active' => $newActive]);
    }

    public function stakeholdersDelete($id)
    {
        if (!auth::user()->hasPermissionTo('Cron_Mail_list')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $deleted = DB::table('report_stakeholders')->where('id', $id)->delete();
        if (!$deleted) return response()->json(['error' => 'Not found'], 404);

        return response()->json(['success' => true]);
    }
}
