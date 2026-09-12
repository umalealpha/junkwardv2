<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PDF;
use DateTime;
use Validator;
use Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DB;
use GuzzleHttp\Exception\ClientException;
use Hash;
use Http\Client\Exception;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Mail;
use Illuminate\Cache\NullStore;
use Log;
use AlphaDirect\Models\SMSEmailLogs;
use AlphaDirect\Events\ExcelImportForPolicyActivate;
use Illuminate\Support\Arr;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\ExcelImportForPolicy;
use AlphaDirect\Imports\ExpiredPoliciesImport;
use AlphaDirect\Imports\ExcelImportPolicyActivation;
use AlphaDirect\Imports\ExcelImportPolicyCancellation;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ExpiredPoliciesExcel;
use AlphaDirect\Imports\ExcelImport;
use AlphaDirect\Jobs\ImportExpiredPolicies;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Mail\SendMail;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Ledger;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\PolicyRenew;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\User;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Events\ExcelImportPolicyCancellation as ExcelImportPolicyCancellationEvent;
use AlphaDirect\ExpiredPoliciesImportJobs as AlphaDirectExpiredPoliciesImportJobs;
use AlphaDirect\Models\ExcelImportActivity;

class ExcelImportController extends Controller
{

    public function excelImport(){
        return view('admin.import.excelImport');
    }

    public function excelExpiredPolicies(){
        return view('admin.import.excelPoliciesExpired');
    }

    public function getExcelExpiredPolicies(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required',
            ]);
            // $policies_expired_list = ExpiredPoliciesImportJobs::get();
            // $report = [
            //     'policies' => $policies_expired_list,
            //     'title'    => 'Expired policies Listing'
            // ];
            // return view('admin.notes.expiredPoliciesList',$report);
            $data =  Excel::import(new ExpiredPoliciesImport, $request->file('file')->store('files'));
            ImportExpiredPolicies::dispatchSync();
            return redirect()->back()->withSuccess('Data imported successfully.');
        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }

    public function sendRenewSmsWithPremium($data)
    {
        $old_premium = $data['old_premium'];
        $new_premium = $data['new_premium'];

        $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$data['policyNumber'])->first();
        if (isset($expired_policies_import)) {
            if ($old_premium == $new_premium) {
                foreach($data['cellphone'] as $key => $cellphoneitem){
                    if (isset($cellphoneitem)) {
                        //sms
                        // $smsMessaging = new SmsMessaging;
                        // $smsMessaging->sendRenewSmsWithNoPremiumChanged($cellphoneitem);

                        $expired_policies_import->sms_sent = Carbon::now()->format("Y-m-d H:i:s");

                        // activity('Send SMS')
                        //     // ->performedOn($data)
                        //     ->causedBy(User::where('id', auth()->user()->id)->first())
                        //     ->log('SMS send');
                    }
                }

                foreach($data['email'] as $key => $emailitem){
                    //mail
                    if (isset($emailitem)) {
                        // $sdata = new \stdClass();
                        // $sdata->user_id = null; //$urlData->id;
                        // $sdata->hook = 'send_renew_with_no_premium_changed';
                        // $sdata->customer_id = $data['customer_id'];
                        // $sdata->attachment = null;
                        // $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                        // $markdown = new MailTemplate($sdata);
                        // $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                        // event(new \AlphaDirect\Events\SendMail($emailitem,$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $data['policyNumber'],'hook' => $sdata->hook]));

                        $expired_policies_import->email_sent = Carbon::now()->format("Y-m-d H:i:s");
                    }
                }
            }else{
                foreach($data['cellphone'] as $key => $cellphoneitem){
                    if (isset($cellphoneitem)) {
                        //sms
                        // $smsMessaging = new SmsMessaging;
                        // $smsMessaging->sendRenewSmsWithPremiumChanged($cellphoneitem);

                        $expired_policies_import->sms_sent = Carbon::now()->format("Y-m-d H:i:s");

                        // activity('Send SMS')
                        //     // ->performedOn($data)
                        //     ->causedBy(User::where('id', auth()->user()->id)->first())
                        //     ->log('SMS send');
                    }

                }

                foreach($data['email'] as $key => $emailitem){
                   //mail
                    if (isset($emailitem)) {
                        // $sdata = new \stdClass();
                        // $sdata->user_id = null; //$urlData->id;
                        // $sdata->hook = 'send_renew_with_premium_changed';
                        // $sdata->customer_id = $data['customer_id'];
                        // $sdata->attachment = null;
                        // $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                        // $markdown = new MailTemplate($sdata);
                        // $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                        // event(new \AlphaDirect\Events\SendMail($emailitem,$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $data['policyNumber'],'hook' => $sdata->hook]));

                        $expired_policies_import->email_sent = Carbon::now()->format("Y-m-d H:i:s");
                    }

                }
            }

            $expired_policies_import->save();
        }

    }

    public function getExcelImport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required',
            ]);
            $data =  Excel::import(new ExcelImport, $request->file('file')->store('files'));
            return redirect()->back()->withSuccess('Data imported successfully.');
        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }

    public function excelImportPolicyActivation(){

        return view('admin.import.excelImportPolicyActivation');
    }

    public function getExcelImportPolicyActivation(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required',
            ]);
            $date = \Carbon\Carbon::now()->timestamp;
            $file = $request->file('file');
            $name = $file->getClientOriginalName();
            $filePath = 'excel_upload_file-Activation'.$date. '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $new = new ExcelImportActivity();
            $new->excel_uploaded_file =  $filePath;
            $new->save();
             Excel::import(new ExcelImportPolicyActivation, $request->file('file')->store('files'));

             $data = auth()->user()->id;
             $id = $new->id;
             $response =   event(new ExcelImportForPolicyActivate($data,$id));

            return redirect()->back()->withSuccess('Data imported successfully.');
        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }

    public function excelImportPolicyCancellation(){

        return view('admin.import.excelImportPolicyCancellation');
    }



    public function getExcelImportPolicyCancellation(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required',
            ]);
            $date = \Carbon\Carbon::now()->timestamp;
            $file = $request->file('file');
            $name = $file->getClientOriginalName();
            $filePath = 'excel_upload_file-Cancellation'.$date. '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $new = new ExcelImportActivity();
            $new->excel_uploaded_file =  $filePath;
            $new->save();
            Excel::import(new ExcelImportPolicyCancellation, $request->file('file')->store('files'));
            $id = new \stdClass();
            $id->id = auth()->user()->id;
            $id->id2 = $new->id;
            $response =   event(new ExcelImportPolicyCancellationEvent($id));

            return redirect()->back()->withSuccess('Data imported successfully.');
        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }
    public function policyActivateCancelReportData(Request $request)
    {
        $query = ExcelImportActivity::orderBy('created_at', 'DESC');


        if ($request->policyStatus_filter != - 1)
        {
            $query->where('status', $request->policyStatus_filter);
        }

       if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }

        $policy = $query->get();



        return DataTables::of($policy)->editColumn('created_at', function ($policy)
        {
            return $policy
                ->created_at
                ->diffForHumans();
        })->addColumn('action', function ($policy)
        {
            if ($policy->status == 1)
            {
                $return = '<span class="kt-font-bold kt-font-brand">Activation</span>';
            }
            elseif ($policy->status == 2)
            {
                $return = '<span class="kt-font-bold kt-font-danger">Cancellation</span>';
            }
            else
            {
                $return = '-';
            }



            return $return;
        })->editColumn('excel_uploaded_file', function ($policy)
        {
            if ($policy->excel_uploaded_file != null)
            {
                $return = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($policy->excel_uploaded_file) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="download"><i class="flaticon-download"></i></a>';
            }

            else
            {
                $return = '-';
            }



            return $return;
        })->editColumn('excel_perform_file', function ($policy)
        {
            if ($policy->excel_perform_file != null)
            {
                $return = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($policy->excel_perform_file) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="download"><i class="flaticon-download"></i></a>';
            }else{
                $return = '-';
            }



            return $return;
        })->editColumn('status', function ($policy)
        {
            if ($policy->excel_uploaded_file != null && $policy->excel_perform_file != null)
            {
                $return = '<span class="kt-font-bold kt-font-brand">Success</span>';
            }

            else
            {
                $return = '<span class="kt-font-bold kt-font-danger">Failed</span>';
            }



            return $return;

        })->editColumn('added_by', function ($policy)
        {
           if($policy->added_by != null){
            $user = User::where('id',$policy->added_by)->first(['firstName','lastName']);
            if($user){
                $name = $user->firstName.' '.$user->lastName;
            }else{
                $name = '-';
            }

            return  $name;
           }else{
            return  '-';
           }


        })
            ->rawColumns(['status','action','excel_perform_file','excel_uploaded_file','added_by'])
            ->make(true);
    }


    public function policyActivateCancelReport(Request $request)
    {
        return view('admin.reports.policyActivateCancelReport');
    }

    public function sendRenewIntimationSmsEmail($data)
    {
        $expired_policies_import = AlphaDirectExpiredPoliciesImportJobs::where('policyNumber',$data['policyNumber'])->first();
        if (isset($expired_policies_import)) {

            if (isset($data['cellphone'])) {
                //sms
                $smsMessaging = new SmsMessaging();
                $smsMessaging->sendRenewSmsWithPremiumChanged($data['cellphone']);

                $expired_policies_import->sms_sent = Carbon::now()->format("Y-m-d H:i:s");

                activity('Expired Policies Import')
                    ->performedOn($expired_policies_import)
                    // ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Renew notification SMS sent');
            }

            //mail
            if (isset($data['email'])) {
                $sdata = new \stdClass();
                $sdata->user_id = null; //$urlData->id;
                $sdata->hook = 'send_renew_with_premium_changed';
                $sdata->customer_id = $data['customer_id'];
                $sdata->policy_id = $data['policy_id'];
                $sdata->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                $markdown = new MailTemplate($sdata);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                event(new \AlphaDirect\Events\SendMail($data['email'],$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $data['policyNumber'],'hook' => $sdata->hook]));

                $expired_policies_import->email_sent = Carbon::now()->format("Y-m-d H:i:s");

            }

            $expired_policies_import->customer_sms_email_count = $expired_policies_import->customer_sms_email_count + 1;
            $expired_policies_import->save();
        }

    }
}
