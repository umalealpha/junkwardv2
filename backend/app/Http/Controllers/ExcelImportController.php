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
use AlphaDirect\Models\ExcelImportActivity;
use AlphaDirect\PolicyTerm;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Imports\ExcelImportDpoRefund;
use AlphaDirect\Events\DpoRefundExcelEvent;
use AlphaDirect\Imports\ExcelImportNgeniusAddTrxn;
use AlphaDirect\Events\ExcelImportForNgeniusAddTrxn;

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

    public function sendPolicyExpiredSmsEmail($data)
    {
        $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$data['policyNumber'])->first();
        if (isset($expired_policies_import)) {

            if (isset($data['cellphone'])) {
                //sms
                $smsMessaging = new SmsMessaging();
                $smsMessaging->sendPolicyExpiredSMS($data['cellphone']);

                $expired_policies_import->expired_sms_sent = Carbon::now()->format("Y-m-d H:i:s");

                activity('Expired Policies Import')
                    ->performedOn($expired_policies_import)
                    // ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy expired SMS sent');
            }

            //mail
            if (isset($data['email'])) {
                $sdata = new \stdClass();
                $sdata->user_id = null; //$urlData->id;
                $sdata->hook = 'send_policy_expired';
                $sdata->customer_id = $data['customer_id'];
                $sdata->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                $markdown = new MailTemplate($sdata);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                event(new \AlphaDirect\Events\SendMail($data['email'],$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $data['policyNumber'],'hook' => $sdata->hook]));

                $expired_policies_import->expired_email_sent = Carbon::now()->format("Y-m-d H:i:s");

            }

            $expired_policies_import->save();
        }

    }

    public function sendRenewIntimationSmsEmail($data)
    {
        $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$data['policyNumber'])->first();
        if (isset($expired_policies_import)) {
            $policyController = new PolicyController();
            $link = $policyController->generateRenewalLink($data['policyNumber']);
            if (isset($link)) {
                if (isset($data['cellphone'])) {
                    //sms
                    // $smsMessaging = new SmsMessaging();
                    // $smsMessaging->sendRenewSmsWithPremiumChanged($data['cellphone']);

                    if(env('APP_STATUS') == 'Production')
                            $tempId = 44;
                        else
                            $tempId = 46;

                    $sms = new SmsMessaging();
                    $sms = $sms->sendOneTimePaymentLinkRenewal($tempId,$data['cellphone'],$link,$data['policyNumber'],$data['amount']);

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
                    $sdata->link = $link;
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
    public function excelImportDPORefund(){
        if (auth::user()->hasPermissionTo('DpoRefundByExcel')) {
        return view('admin.import.excelImportDPORefund');
    } else {
        return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
    }

    }
    public function getExcelImportDpoRefund(Request $request)
    {
    if (auth::user()->hasPermissionTo('DpoRefundByExcel')) {
        try {
            $request->validate([
                'file' => 'required',
            ]);
            $date = \Carbon\Carbon::now()->timestamp;
            $file = $request->file('file');
            $name = $file->getClientOriginalName();
            $filePath = 'excel_upload_file-dpo_refund'.$date. '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $new = new ExcelImportActivity();
            $new->excel_uploaded_file =  $filePath;
            $new->added_by = auth()->user()->id;
            $new->status = 3;
            $new->save();
             Excel::import(new ExcelImportDpoRefund, $request->file('file')->store('files'));

              $data = auth()->user()->id;
              $id = $new->id;
              $response =   event(new DpoRefundExcelEvent($data,$id));

            return redirect()->back()->withSuccess('Data imported successfully.');
        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    } else {
        return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
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
    public function excel_n_genius_add_transaction(){
    if (auth::user()->hasPermissionTo('excel_import_n_genius')) {
            return view('admin.import.n_genius_add_transaction');
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function get_excel_n_genius_add_transaction(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required',
            ]);
            $date = \Carbon\Carbon::now()->timestamp;
            $file = $request->file('file');
            $name = $file->getClientOriginalName();
            $filePath = 'excel_upload_file-n_genius_add_trxn'.$date. '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $new = new ExcelImportActivity();
            $new->excel_uploaded_file =  $filePath;
            $new->save();
             Excel::import(new ExcelImportNgeniusAddTrxn, $request->file('file')->store('files'));

             $data = auth()->user()->id;
             $id = $new->id;
             $response =   event(new ExcelImportForNgeniusAddTrxn($data,$id));

            return redirect()->back()->withSuccess('Data imported successfully.');
        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }

    public function excelImportPolicyCancellation(){

        return view('admin.import.excelPolicyCancellation');
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
            }elseif ($policy->status == 3)
            {
                $return = '<span class="kt-font-bold kt-font-info">DPO Refund</span>';
            }elseif ($policy->status == 4)
            {
                $return = '<span class="kt-font-bold kt-font-info">N-Genius Add Transaction</span>';
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

    public function PolicyVatChange()
    {
//dd("Failed");
      /*  $policyNumbers = [
            'MIS2023052250',
            'MIS2023052249',
            'MIS2023052241',
            'MIS2023052140',
            'MIS2023052111',
            'MIS2023051977',
            'MIS2023051924',
            'MIS2023051884',
            'MIS2023051782',
            'MIS2023051769',
            'MIS2023051753',
            'MIS2023051610',
            'MIS2023051264',
            'MIS2023051263',
            'MIS2023051179',
            'MIS2023051177',
            'MIS2023051159',
            'MIS2023051158',
            'MIS2023050890',
            'MIS2023050884',
            'MIS2023050846',
            'MIS2023050821',
            'MIS2023050820',
            'MIS2023050789',
            'MIS2023050723',
            'MIS2023050644',
            'MIS2023050608',
            'MIS2023050602',
            'MIS2023050581',
            'MIS2023050527',
            'MIS2023050504',
            'MIS2023050500',
            'MIS2023050497',
            'MIS2023050349',
            'MIS2023050272',
            'MIS2023050256',
            'MIS2023050163',
            'MIS2023050133',
            'MIS2023049997',
            'MIS2023049965',
            'MIS2023049929',
            'MIS2023049831',
            'MIS2023049805',
            'MIS2023049797',
            'MIS2023049659',
            'MIS2023049626',
            'MIS2023049596',
            'MIS2023049480',
            'MIS2023049362',
            'MIS2023049061',
            'MIS2023049034',
            'MIS2023048537',
            'MIS2023048353',
            'MIS2023048327',
            'MIS2023048260',
            'MIS2023048140',
            'MIS2023048051',
            'MIS2023048021',
            'MIS2023047926',
            'MIS2023047919',
            'MIS2023047814',
            'MIS2023047803',
            'MIS2023047744',
            'MIS2023047643',
            'MIS2023047603',
            'MIS2023047591',
            'MIS2023047590',
            'MIS2023047511',
            'MIS2023047334',
            'MIS2023047108',
            'MIS2023047000',
            'MIS2023046414',
            'MIS2023046277',
            'MIS2023046045',
            'MIS2023046044',
            'MIS2023046037',
            'MIS2023045922',
            'MIS2023045916',
            'MIS2023045864',
            'MIS2023045732',
            'MIS2023045493',
            'MIS2022045241',
            'MIS2022045058',
            'MIS2022044934',
            'MIS2022044781',
            'MIS2022044757',
            'MIS2022044696',
            'MIS2022044573',
            'MIS2022044309',
            'MIS2022044277',
            'MIS2022044270',
            'MIS2022044213',
            'MIS2022044028',
            'MIS2022044011',
            'MIS2022043967',
            'MIS2022043835',
            'MIS2022043613',
            'MIS2022043612',
            'MIS2022043611',
            'MIS2022043576',
            'MIS2022043472',
            'MIS2022043398',
            'MIS2022043397',
            'MIS2022043391',
            'MIS2022043386',
            'MIS2022043385' ,
            'MIS2022043384',
            'MIS2022043383',
            'MIS2022043346',
            'MIS2022043311',
            'MIS2022043225',
            'MIS2022042805',
            'MIS2022042690',
            'MIS2022042677',
            'MIS2022042116',
            'MIS2022042079',
            'MIS2022041916',
            'MIS2022041790',
            'MIS2022041481',
            'MIS2022041390',
            'MIS2022041218',
            'MIS2022041216',
            'MIS2022041067',
            'MIS2022040986',
            'MIS2022040929',
            'MIS2022040892',
            'MIS2022040831',
            'MIS2022040767',
            'MIS2022040740',
            'MIS2022040737',
            'MIS2022040728',
            'MIS2022040697',
            'MIS2022040648',
            'MIS2022040645',
            'MIS2022040520',
            'MIS2022040514',
            'MIS2022040448',
            'MIS2022040438',
            'MIS2022040148',
            'MIS2022040126',
            'MIS2022039760',
            'MIS2022039758',
            'MIS2022039750',
            'MIS2022039747',
            'MIS2022039734',
            'MIS2022039732',
            'MIS2022039685',
            'MIS2022039684',
            'MIS2022039662',
            'MIS2022039647',
            'MIS2022039631',
            'MIS2022039617',
            'MIS2022039586',
            'MIS2022039548',
            'MIS2022039547',
            'MIS2022039531',
            'MIS2022039528',
            'MIS2022039470',
            'MIS2022039469',
            'MIS2022039439',
            'MIS2022039346',
            'MIS2022039180',
            'MIS2022038955',
            'MIS2022038872',
            'MIS2022038827',
            'MIS2022038813',
            'MIS2022038783',
            'MIS2022038767',
            'MIS2022038733',
            'MIS2022038651',
            'MIS2022038528',
            'MIS2022038503',
            'MIS2022038382',
            'MIS2022038336',
            'MIS2022038254',
            'MIS2022038233',
            'MIS2022038188',
            'MIS2022038185',
            'MIS2022037937',
            'MIS2022037934',
            'MIS2022037911',
            'MIS2022037842',
            'MIS2022037763',
            'MIS2022037723',
            'MIS2022037668',
            'MIS2022037609',
            'MIS2022037598',
            'MIS2022037382',
            'MIS2022036877',
            'MIS2022036777',
            'MIS2022036758',
            'MIS2022036742',
            'MIS2022036736',
            'MIS2022036719',
            'MIS2022036711',
            'MIS2022036656',
            'MIS2022036525',
            'MIS2022036493',
            'MIS2022036472',
            'MIS2022036445',
            'MIS2022036404',
            'MIS2022036300',
            'MIS2022036202',
            'MIS2022036198',
            'MIS2022036194',
            'MIS2022036166',
            'MIS2022036098',
            'MIS2022036094',
            'MIS2022036084',
            'MIS2022036018',
            'MIS2022035978',
            'MIS2022035954',
            'MIS2022035942',
            'MIS2022035937',
            'MIS2022035898',
            'MIS2022035895'

            ];
            $policies = Policy::whereIn('policyNumber',$policyNumbers)->where('vat_percent',12)->whereIn('status',[0,1])->get();
            $policyPr = [];
            $policyTermPr = [];
            $schPr = [];
            $policyPrent =[];
            if($policies->count() > 0){
             foreach($policies as $policy){
                /////policy////////
                if($policy->annual_premium != null){
                $policy->annual_premium = number_format((float)($policy->annual_premium * 1.14 / 1.12), 2, '.', '');
                }
                if($policy->premium != null){
                $policy->premium = number_format((float)($policy->premium * 1.14 / 1.12), 2, '.', '');
                }
                $policy->vat = number_format((float)($policy->vat * 1.14 / 1.12), 2, '.', '');
                $policy->vat_percent = 14;
                //$policyPr = ['policyNumber'=>$policy->policyNumber, 'annual_premium'=>$policy->annual_premium,
               // 'premium'=>$policy->premium,'vat'=>$policy->vat,'vat_percent'=>$policy->vat_percent];
                $policy->save();
                    if( $policy->product_id == 3){
                        $policyterms =   PolicyTerm::where('policy_id',$policy->id)->where('term_start_date' ,'>',Carbon::parse('2022-08-01')->format('Y-m-d'))->get();
                        if($policyterms->count() > 0){
                            foreach($policyterms as $policyterm){
                                ///////////terms//////////
                                if($policyterm->annual_premium != null){
                                    $policyterm->annual_premium = number_format((float)($policyterm->annual_premium * 1.14 / 1.12), 2, '.', '');
                                }
                                if($policyterm->premium != null){
                                    $policyterm->premium = number_format((float)($policyterm->premium * 1.14 / 1.12), 2, '.', '');
                                }
                                $policyterm->vat = number_format((float)($policyterm->vat * 1.14 / 1.12), 2, '.', '');
                                $policyterm->vat_percent = 14;
                                $policyterm->save();
                               // $policyTermPr[] = [$policyterm->annual_premium, $policyterm->premium ,$policyterm->vat,$policyterm->vat_percent];
                            }
                        }
                    }

                if(CustomerBanking::where('policy_id',$policy->id)->where('billing','DPO')->exists()){
                    $schds = ScheduleTransaction::where('policy_number',$policy->policyNumber)->where('status',0)->get();
                    if($schds->count() > 0){
                       foreach($schds as $schd){
                        /////ScheduleTransaction/////////
                            if($schd->premium != null){
                                $schd->premium = number_format((float)($schd->premium * 1.14 / 1.12), 2, '.', '');
                            }

                             $schd->save();
                            // $schPr[] = $schd->premium;
                            }

                    }

                }
                //$policyPrent[] = ['Policy' => $policyPr, 'term' => $policyTermPr, 'sch'=>$schPr ];
               }
            }


           //return response()->json(['policies' => $policyPrent], 200);
           return response()->json(['policies' => "success"], 200);

    }
           //return response()->json(['policies' => $policyPrent], 200);
           return response()->json(['policies' => "success"], 200);
    */
   }

   public function sendIntimationForRenewSmsEmail($data)
   {
       $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$data['policyNumber'])->first();
       if (isset($expired_policies_import)) {

           if (isset($data['cellphone'])) {
               //sms
               $smsMessaging = new SmsMessaging();
               $smsMessaging->sendRenewSmsForExpiredPolicy($data['cellphone']);

               $expired_policies_import->renew_sms_sent = Carbon::now()->format("Y-m-d H:i:s");

               activity('Expired Policies Import')
                   ->performedOn($expired_policies_import)
                   ->log('Renew notification SMS sent');
           }

           //mail
           if (isset($data['email'])) {
               $sdata = new \stdClass();
               $sdata->user_id = null; //$urlData->id;
               $sdata->policy_id = $data['policy_id'];
               $sdata->attachment = null;
               $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
               $markdown = new MailTemplate($sdata);
               $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
               event(new \AlphaDirect\Events\SendMail($data['email'],$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $data['policyNumber'],'hook' => $sdata->hook]));

               $expired_policies_import->renew_email_sent = Carbon::now()->format("Y-m-d H:i:s");

           }

           $expired_policies_import->save();
       }

   }
   public function CreditNoteAndInvioce()
   { 
    if (auth::user()->hasPermissionTo('DpoRefundByExcel')) {
        return view('admin.import.creditnoteinvioce');
    } else {
        return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
    }
   }
   public function CreditNoteAndInvioceGen(Request $request)
   { 
       //dd($request->all());
        $policy =   Policy::where('policyNumber',$request->policyNumber)->first();
        if($policy != null){
           // if($policy->status == 1){
                $paymentSuccessAll = PaymentTransaction::orderBy('id','desc')->where('policyNumber',$policy->policyNumber)
                ->whereIn('status', ['SUCCESS','success','Success','1'])->get(['created_at','policy_id']);
                 if(count($paymentSuccessAll) > 0){
                     foreach($paymentSuccessAll as $payTrxn){
                         $policy_id = $payTrxn->policy_id;
                         $date =    Carbon::parse($payTrxn->created_at)->format('Y-m-d');
                         $path = Helper::addInvoiceToLedger($policy->id, $date);
                         
                     }

                 }
            /// }
            //if($policy->status == 2){
                $this->CreditNote($policy);
           // }
        
            return Redirect::back()->with('success', 'policy Credit Note And Invioce Generated successfully');
            
        }
        return Redirect::back()->with('error', 'Sorry! PolicyNumber not found');
   }
   public function CreditNote($policy)
   {

       $firsttrxn = PaymentTransaction::orderBy('id','asc')->where('policyNumber',$policy->policyNumber)
       ->whereIn('status', ['SUCCESS','success','Success','1'])->first();
       $allTrx = PaymentTransaction::orderBy('id','asc')->where('policyNumber',$policy->policyNumber)
       ->whereIn('status', ['SUCCESS','success','Success','1'])->get();
       $earned_premium = 0;
           if(count($allTrx) > 0){

               foreach($allTrx as $trx){
                 $earned_premium   += $trx->amount;
               }

           }

           if($firsttrxn != null ){
           $earlier = Carbon::parse($firsttrxn->created_at)->format('d-m-Y');
           $today = Carbon::parse('today')->format('d-m-Y');
           $earlier1 = Carbon::parse($firsttrxn->created_at)->format('Y-m-d');
           $today1 = Carbon::parse('today')->format('Y-m-d');
           $from_date = Carbon::parse(date('Y-m-d', strtotime($earlier1)));
           $through_date = Carbon::parse(date('Y-m-d', strtotime($today1)));

           // get total number of minutes between from and throung date
           $shift_difference = $from_date->diffInDays($through_date);
           $month =  number_format(1 + ($shift_difference/ 30));
           $unearned_premium =  $policy->premium *  $month;
           $before_vat  = number_format(($earned_premium / 1.14) , 2)   ;
           $vat = $earned_premium - $before_vat;
           //$pos_diff = $earlier->diff($today)->format("%r%a");
           $request = new \stdClass();
           $request->start_date =$earlier;
           $request->end_date =  $today;
           $request->earned_premium =  $earned_premium;
           $request->unearned_premium =  $unearned_premium;
           $request->before_vat =  $before_vat;
           $request->vat =  $vat;



           $request->no_of_days = $shift_difference;
           $id = $policy->id;
           $Led =  Ledger::where('policy_id', $id)->first(array('id','invoice_no'));
           if($Led){
           $ledger = $Led->id;
           }else{
               $LedA = LedgerArchive::where('policy_id', $id)->first(array('id','invoice_no'));
               if($LedA){
                   $ledger = $LedA->id;
               }else{
                   $ledger = null;
               }
           }

           $policyController = new  PolicyController();

           //$cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
           if($ledger != null){
           $creditNote = $policyController->creditNoteStatement2($request,$id,$ledger);
           //$policyController->creditSendNoteMail($id);
          
           }
           }
           return true;
   }
}
