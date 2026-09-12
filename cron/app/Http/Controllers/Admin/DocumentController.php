<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Accounts;
use AlphaDirect\BankBranches;
use AlphaDirect\Banks;
use AlphaDirect\Branch;
use AlphaDirect\Country;
use AlphaDirect\PolicyTerm;
use AlphaDirect\sentPolicyDocumentLogs;
use Illuminate\Support\Facades\Auth;
use AlphaDirect\PolicyCoveredPerson;
use Response;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Documents;
use AlphaDirect\PolicyBundled;
use AlphaDirect\Helper;
use AlphaDirect\Lookup;
use AlphaDirect\MotorComprehensiveSchedule;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\PolicyCoverNote;
use AlphaDirect\Product;
use AlphaDirect\State;
use Http\Client\Exception;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Ledger;
use Illuminate\Support\Facades\Storage;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Yajra\DataTables\DataTables;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Redirect;
use PDF;
use File;
use DateTime;
use DateInterval;
use Carbon\Carbon;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Mail\SendMail;
use AlphaDirect\Mail\SendPolicyMail;
use AlphaDirect\Models\PolicyDocument as ModelsPolicyDocument;
use AlphaDirect\PolicyDocument;
use AlphaDirect\Models\GetPolicyDocuments;
use AlphaDirect\Models\PolicyDocuments;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Productplan;
use AlphaDirect\Stores;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Builder\Builder;
use DB;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Redirect as FacadesRedirect;
use AlphaDirect\Models\DocumentDelete;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Http\Controllers\Admin\PolicyController as AdminPolicyController;

class DocumentController extends Controller
{
    public function index(){

        //if(auth::user()->hasPermissionTo('account-list')){
        return view('admin.documents.index');
//       }
//       else{
//           return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
//       }
    }

    public function data()
    {
        $docs = Documents::get();
        return DataTables::of($docs)
            ->addColumn('product_id',function($docs) {
                if($docs){
                    if($docs->product_id != null &&  $docs->product_id != -1 && $docs->product_id != 0){
                        $product = Product::where('id',$docs->product_id)->first();
                        if($product->name)
                            return $product->name;
                        else
                            return 'Product name not found';
                    }elseif ($docs->product_id == -1){
                        return 'All Products';
                    }
                    elseif ($docs->product_id == 0){
                        return 'Not required';
                    }else{
                        return 'N/A';
                    }
                }


            })
            ->addColumn('link',function($docs) {
                if($docs){
                    if($docs->link != null){
                        return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($docs->link) . ' " target= "_blank"> View Document </a>';
                    }else{
                        return 'N/A';
                    }
                }


            })
            ->addColumn('status', function ($docs)
            {
                if($docs->status == 1)
                    return '<span style="color:darkgreen">Active</span>';
                else
                    return '<span style="color:red">In-active</span>';
            })
            ->addColumn('actions',function($docs) {
                $actions = '';
                if(auth::user()->can('account-edit')){
                    $actions .= '<a href="'. route('admin.documents.edit', $docs->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }/*else{
                    $actions .= '<a href="'. route('admin.accounts.edit', $accounts->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i>
                            </a>';
                }*/
//                if(auth::user()->can('account-delete')) {
//                    $actions .= '<a href="" value="' . $accounts->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
//                                <i class="la la-trash"></i>
//                            </a>';
//                }
                return $actions;
            })
            ->rawColumns(['product_id','link','status','actions'])
            ->make(true);
    }

    public function create(){
        $products = Product::where('status',1)->get(array('id','name'));
        $doc_types = Lookup::where('key','document')
            ->where('status',1)
            ->get(array('id','key','value'));
        return view('admin.documents.create',compact('products','doc_types'));
    }

    public function store(Request $request){
        $doc = new Documents();
        $doc->product_id = $request->get('product');
        $doc->category = preg_replace('/\s+/', '_', $request->get('document_cat'));
        $doc->name = preg_replace('/\s+/', '_', $request->get('document_name'));
        $doc->status = $request->get('statusValue');

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $filePath = 'Document/' . $doc->category . '/' . $doc->name . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $doc->link = $filePath;
        }

        $doc->save();

        activity('Create')
            ->performedOn($doc)
            ->causedBy(User::where('id',auth()->user()->id)->first())
            ->log('New document has been created');

        return Redirect::route('admin.documents.index')->with('success', 'Document added successfully');
    }

    public function edit($id){
        $products = Product::where('status',1)->get(array('id','name'));
        $doc_types = Lookup::where('key','document')
            ->where('status',1)
            ->get(array('id','key','value'));
        $document = Documents::where('id',$id)->first();
        return view('admin.documents.edit',compact('products','doc_types','document'));
    }

    public function updateDoc(Request $request,$id){
        $doc = Documents::where('id',$id)->first();
        if($doc){
            $doc->product_id = $request->get('product');
            $doc->category = preg_replace('/\s+/', '_', $request->get('document_cat'));
            $doc->name = preg_replace('/\s+/', '_', $request->get('document_name'));
            $doc->status = $request->get('statusValue');

            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $filePath = 'Document/' . $doc->category . '/' . $doc->name . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $doc->link = $filePath;
            }

            $doc->save();
            return Redirect::route('admin.documents.index')->with('success', 'Document updated successfully');
        }
    }

     public function generatePolicyDocument($policyId)
    {
        try {
            $date = Carbon::now()->format('d F Y');
            $schedule = MotorComprehensiveSchedule::orderBy('id','desc')->first();
            $policy = Policy::where('id',$policyId)->first();
            //store deleted docs start
            if($policy->policyDocument != null){
                $ddelete  = new   DocumentDelete();
                $ddelete->policy_id = $policy->id;
                $ddelete->document_name = 'policyDocument';
                $ddelete->document = $policy->policyDocument;
                $ddelete->activity_by = auth()->check() ? auth()->user()->id : 'System';
                $ddelete->save();
            }
            //*******End tore deleted docs start ***************


            $policy_stores = Stores::where('id',$policy->storeID)->first(array('id','name'));
            if(isset( $policy_stores) && $policy_stores != null ){
                $policy_stores = $policy_stores->name;
            }else{
                $policy_stores = "";
            }
            $bundles = array();
            if($policy->is_bundled == 1)
            {
                $bundles = PolicyBundled::where('policy_id', $policyId)->get(array('product_id', 'premium', 'final_premium', 'subtotal'))->toArray();
                $Bundledpremium = number_format( (float)( $bundles[0]['final_premium'] + $policy->bundled_discount - $bundles[0]['subtotal'] ), 2, '.', '');
            } else {
                $Bundledpremium = $policy->premium;
            }

            array_push($bundles, array('product_id'=>$policy->product_id,'premium'=>$Bundledpremium));

            if($policy && $policy->agent_id != null){
                $agentName = User::where('id',$policy->agent_id)->first(array('firstName','lastName'));
                if($agentName == null)
                    $agentName = 'N/A';
                else
                    $agentName = $agentName->firstName.' '.$agentName->lastName;
            }
            else {
                $agentName = 'N/A';
            }

            $banking = CustomerBanking::where('policy_id',$policyId)->first();
            $vehicle = Vehicle::where('policy_id',$policyId)->first();
            $product = Product::where('id',$policy->product_id)->first();
            $product_plan = Productplan::where('id',$policy->plan_id)->first();

            $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName','middleName','email','cellphone'));
            $customer_kyc = KYC::where('customer_id',$policy->customer_id)->first(array('omangExpiry','passportExpiry','passportIssuingCountry'));

            if($customer_kyc && $customer_kyc->passportIssuingCountry)
                $passpostIssueCountry = Country::where('id', $customer_kyc->passportIssuingCountry)->first(array('name'));
            else
                $passpostIssueCountry = null;

            if($customer == null){
                $customer = Customer::where('id',5240)->first(array('firstName','lastName','middleName','email','cellphone'));
            }
            $customerProfile = CustomerProfile::where('customer_id',$policy->customer_id)->first(array('maritalstatus','dob','state','city','address','omang','passport','gender'));
            if($customerProfile == null){
                $customerProfile = CustomerProfile::where('customer_id',5240)->first(array('maritalstatus','dob','state','city','address','omang','passport','gender'));
            }
            $today = new DateTime($policy->created_at); // this is policy created date but it should be policy activated date, this needs to be changed
            $curr = $policy->created_at->format('d-m-Y');


            if($customerProfile->state != null && $customerProfile->state != ''){
                $stateData = State::where('id',$customerProfile->state)->first(array('name'));
                if($stateData != null){
                    $state = $stateData->name;
                }else{
                    $state = null;
                }
            }else{
                $state = null;
            }

            $term = PolicyTerm::where('policy_id',$policyId)->where('status','Active')->orderBy('id','DESC')->first();

            $balance_due = NULL;
            if(isset($term)){
                $fromDate = Carbon::parse($term->term_start_date)->format('d-m-Y');
                $toDate = Carbon::parse($term->term_end_date)->format('d-m-Y');
                // $toDate = Carbon::parse($term->term_end_date)->subDays(1)->format('d-m-Y');
                // $premium = $term->total_premium;
                $premium = $term->annual_premium;
                $freq = $term->frequency;
                $policy->first_premium_wvat = $term->first_premium;
                $balance_due = $term->balance_due;

                // New Policy Schedule doc using submit tab
                $actionId = PolicyAction::Policy($policyId)->orderBy('id','DESC')->first('id');
                if(isset($actionId)){
                    $policy_schedule_doc = new AdminPolicyController();
                    $policy_schedule = $policy_schedule_doc->policy_schedule_mail($policyId,$term->id,$actionId->id);
                   // dd($policy_schedule);
                }
            } else {
                if (isset($policy->policyActivatedDate)) {
                    $fromDate = Carbon::parse($policy->policyActivatedDate)->format('d-m-Y');
                    $toDate = Carbon::parse($policy->policyActivatedDate)->addYear()->subDays(1)->format('d-m-Y');
                    $premium = $policy->premium;
                    $freq = $policy->premium_freq;
                } else {
                    if (isset($policy->billingStartDate)) {
                        $fromDate = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                        $toDate = Carbon::parse($policy->billingStartDate)->addYear()->subDays(1)->format('d-m-Y');
                        $premium = $policy->premium;
                        $freq = $policy->premium_freq;
                    } else {
                        $fromDate = Carbon::parse($policy->created_at)->format('d-m-Y');
                        $toDate = Carbon::parse($policy->created_at)->addYear()->subDays(1)->format('d-m-Y');
                        $premium = $policy->premium;
                        $freq = $policy->premium_freq;
                    }
                }
            }

            if($freq != null){
                switch ($freq) {
                    case 1:
                        $interval = new DateInterval('P1M');
                        //$premium = ($premium * 12) / 1.08;
                        break;
                    case 2:
                        $interval = new DateInterval('P1Y');
                        break;
                    case 3:
                        $interval = new DateInterval('P1Y');
                        break;
                    default:
                        $interval = new DateInterval('P1Y');
                        break;
                }
                $ani = new DateInterval('P1Y');
                $period = $today->add($interval)->modify("-1 day")->format('d-m-Y');
            }else{
                $interval = new DateInterval('P1M');
                $period = $today->add($interval)->modify("-1 day")->format('d-m-Y');
            }

            $dt = Carbon::parse($policy->created_at);
            $dat = $dt->addYear()->subDays(1)->format('d-m-Y');
             $AdultDependents = null;
             $ChildDependents = null;

            if ($policy->product_id == 10) {
                $AdultDependents = PolicyCoveredPerson::where('policy_id', $policy->id)
                    ->where('is_dependent', 1)
                    ->whereIn('relation', [0, 1])
                    ->count();

                $ChildDependents = PolicyCoveredPerson::where('policy_id', $policy->id)
                    ->where('is_dependent', 1)
                    ->where('relation', 2)
                    ->count();
            }
            $data = [
                'name'=>$customer->firstName.' '.$customer->middleName.' '.$customer->lastName,
                'firstName'=>$customer->firstName.' '.$customer->middleName,
                'lastName'=>$customer->lastName,
                'profile'=>$customerProfile,
                'policy' => $policy,
                'schedule' => $schedule,
                'fromDate' => $fromDate,
                'anniversaryDate' => Carbon::parse($toDate)->addDays(1)->format('d-m-Y'),
                'toDate' => $toDate,
                'today' => $date,
                'currentDate' => $curr,
                'premium' => $premium,
                'product' => $product,
                'product_plan' => $product_plan,
                'vehicle' => $vehicle,
                'bankingDetail' => $banking,
                'agentName' => $agentName,
                'state' => $state,
                'balance_due' => $balance_due,
                'customer' => $customer,
                'customer_kyc' => $customer_kyc,
                'policy_stores' => $policy_stores,
                'passpostIssueCountry' => $passpostIssueCountry,
                'AdultDependents'=> $AdultDependents,
                'ChildDependents'=> $ChildDependents
            ];

            libxml_use_internal_errors(true);
            foreach($bundles as $key => $bundle)
            {
                $data['premium'] = $bundle['premium'];
                switch ($bundle['product_id']) {
                    case 3: //Motor Comprehensive
                        $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule.pdf';
                        $pdf = PDF::loadView('mail_schedule.index', $data);
                        break;
                    case 1: // ADI
                        if($policy->plan_id == 13){
                            $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule_ADI_Gold.pdf';
                            $pdf = PDF::loadView('admin.notes.Schedule.index', $data);
                            break;
                        }else{
                            $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule_ADI.pdf';
                            $pdf = PDF::loadView('admin.notes.Schedule.index', $data);
                            break;
                        }

                    case 2: //Third Party
                        $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule_ThirdParty.pdf';
                        $pdf = PDF::loadView('admin.notes.Schedule.index', $data);
                        break;
                    case 4: //Legal
                        $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule_Legal.pdf';
                        $pdf = PDF::loadView('admin.notes.Schedule.index', $data);
                        break;
                    case 5: //Cellphone
                        $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Cellphone.pdf';
                        $pdf = PDF::loadView('admin.notes.Schedule.index', $data);
                        break;
                    case 9: //Hospital_Cashback
                        $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule_Hospital_Cashback_P99.pdf';
                        $pdf = PDF::loadView('admin.notes.Schedule.index', $data);
                        break;
                    case 10: //Health In Box
                        $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule_Health_In_Box.pdf';
                        $pdf = PDF::loadView('admin.notes.Schedule.index', $data);
                        break;
                    default:
                        $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule.pdf';
                        $pdf = PDF::loadView('mail_schedule.index', $data);
                        break;
                }
                Storage::disk('s3')->put($path, $pdf->output(), 'public');
                $policyBundledUpdate = PolicyBundled::where('policy_id', $policyId)->where('product_id', $bundle['product_id'])->first();
                    if($policyBundledUpdate != NULL) {
                        $policyBundledUpdate->policyDocument = $path;
                        $policyBundledUpdate->save();
                    } else {
                        $policy->policyDocument = $path;
                        $policy->save();
                    }
                    // stored document policy_document table
                    $policy_docs                 = new GetPolicyDocuments();
                    $policy_docs->policyNumber   = $policy->policyNumber;
                    $policy_docs->policy_id      = $policyId;
                    // $policy_docs->term_id        = $term->id;
                    // $policy_docs->action_id      = $actionId->id;
                    $policy_docs->doc_path       = $path;
                    $policy_docs->created_at     = Carbon::now();
                    $policy_docs->save();
            }

            if($policy->product_id == 3)
            {
                $policy = new PolicyController();
                $cover = $this->generateCoverCancelNoteCRON($policyId,'Cover');
            }
           
            return true;

        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    //For terms
    public function generatePolicyDocumentTerms($policyId,$term=null,$returnPath=false)
    {
        try {
            $date = Carbon::now()->format('d F Y');
            $schedule = MotorComprehensiveSchedule::orderBy('id','desc')->first();
            $policy = Policy::where('id',$policyId)->first();
            if($policy && $policy->agent_id != null){
                $agentName = User::where('id',$policy->agent_id)->first(array('firstName','lastName'));
                if($agentName == null)
                    $agentName = 'N/A';
                else
                    $agentName = $agentName->firstName.' '.$agentName->lastName;
            }
            else {
                $agentName = 'N/A';
            }

            $term = PolicyTerm::where('id',$term)->first();

            $banking = CustomerBanking::where('policy_id',$policyId)->first();
            $vehicle = Vehicle::where('policy_id',$policyId)->first();
            $product = Product::where('id',$policy->product_id)->first();
            $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName','middleName','email','cellphone'));
            if($customer == null){
                $customer = Customer::where('id',5240)->first(array('firstName','lastName','middleName','email','cellphone'));
            }
            $customerProfile = CustomerProfile::where('customer_id',$policy->customer_id)->first(array('state','city','address'));
            if($customerProfile == null){
                $customerProfile = Customer::where('customer_id',5240)->first(array('state','city','address'));
            }
            $today = new DateTime($policy->created_at); // this is policy created date but it should be policy activated date, this needs to be changed
            $curr = $policy->created_at->format('d-m-Y');

            if($customerProfile->state != null)
                $state = State::where('id',$customerProfile->state)->first(array('name'))->name;
            else
                $state = ' ';

            $period = Carbon::parse($term->term_start_date)->year.'-'.Carbon::parse($term->term_end_date)->year;

            $dt = Carbon::parse($term->term_start_date);
            $dat = $dt->addYear()->addDays(1)->format('d-m-Y');
            $data = [
                'name'=>$customer->firstName.' '.$customer->lastName,
                'profile'=>$customerProfile,
                'policy' => $policy,
                'schedule' => $schedule,
                'fromDate' => Carbon::parse($term->term_start_date)->format('d-m-Y'),
                'anniversaryDate' => $dat,
                'toDate' => Carbon::parse($term->term_end_date)->format('d-m-Y'),
                'today' => $date,
                'currentDate' => $curr,
                'premium' => $term->premium,
                'product' => $product,
                'vehicle' => $vehicle,
                'bankingDetail' => $banking,
                'agentName' => $agentName,
                'state' => $state,
            ];

            $path = 'PolicyDocument/'. $this->uuid() .'/'. $policy->policyNumber .'/Policy_Schedule.pdf';
            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('mail_schedule.index', $data);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
            $policy->policyDocument = $path;
            $policy->save();
            // stored document policy_document table
             $policy_docs                 = new GetPolicyDocuments();
             $policy_docs->policyNumber   = $policy->policyNumber;
             $policy_docs->policy_id      = $policyId;
             $policy_docs->term_id        = $term->id;
             $policy_docs->doc_path       = $path;
             $policy_docs->created_at     = $dt;

             $policy_docs->save();

            //    $policy_docs = DB::table('policy_documents')->insert([
            //     'policyNumber' => $policy->policyNumber,
            //     'policy_id'    => $policyId,
            //     'doc_path'     => $path,
            //     'created_at'   => $dt,
            //     ]);
            //     $policy_docs->save();

            $policy = new PolicyController();
            $cover = $this->generateCoverCancelNoteCRON($policyId,'Cover','web',$term->id);
            $doc = [];

            array_push($doc,$cover);
            array_push($doc,$path);

            if($returnPath==true)
                return $doc;
            else
                return Redirect::back()->with('success', 'Document regenerated successfully');

        } catch (Exception $ex) {
            if($returnPath==true)
                return null;
            else
                return Redirect::back()->with('error', $ex->getMessage().'-'.$ex->getLine());
        }
    }

    public function sendEmail($mailData){
        try{
            $data = new \stdClass();
            $data->user_id = null;
            $data->hook = $mailData['hook'];
            $data->customer_id = null;
            $data->attachment = $mailData['attachments'];
            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
            $markdown = new MailTemplate($data);
            $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
            event(new \AlphaDirect\Events\SendMail($mailData->email,$emailTemplate->subject,"",$html,$mailData['attachments'],['hook' => $data->hook]));

            return true;
        }catch(\Exception $ex){
            return false;
        }
    }


    public function getPolicyDocuments(Request $request){
        try{
            $policy = Policy::where('id',$request->policy_id)->first();
            $docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = ' . $policy->product_id . ' || product_id = -1)'));

            return response()->json(['success' => true, 'documents' => $docs], 200);

        }catch(\Exception $ex){
            return response()->json(['success' => false, 'documents' => null], 401);
        }
    }


    public function sendPolicyDocument($policyId,$sentBy='Agent')
    {
        try {
            $policy = Policy::where('id',$policyId)->first();
            $product = Product::where('id',$policy->product->id)->first(array('id','has_schedule','has_wordings'));
            $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName','middleName','email'));
            $docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));

           $policyCoverNote = PolicyCoverCancelNote::where('policy_id',$policyId)
                ->where('doc_type','Cover')
                ->orderBy('id','DESC')
                ->first(array('path'));

            $attachments = array();
            if($product->has_wordings == 1){
                if(count($docs) > 0){
                    foreach($docs as $doc){
                        if($doc->link)
                            array_push($attachments, $doc->link);
                    }
                }
            }

            if($product->has_schedule == 1) {
                if ($policy->policyDocument != null)
                    array_push($attachments, $policy->policyDocument);
            }

            if($policyCoverNote != null){
                if ($policyCoverNote->path != null)
                    array_push($attachments, $policyCoverNote->path);
            }

            if($policy->is_bundled == 1)
            {
                $bundled_products = PolicyBundled::where('policy_id', $policyId)->pluck('policyDocument')->toArray();
                $attachments = array_merge($attachments, $bundled_products);
            }

            if($customer->email != null){
               $data = new \stdClass();
               //$data->user_id = $policy->id;
               $data->hook = 'create_policy';
               $data->customer_id = $policy->customer_id;
               $data->policy_id = $policy->id;
               $data->attachment = $attachments;
               $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
               $markdown = new MailTemplate($data);
               $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
               // event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
               // $sent = Mail::to($customer->email)->send(new MailTemplate($data));
               event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));

                $sentDocs = new sentPolicyDocumentLogs();
                $sentDocs->policyNumber = $policy->policyNumber;
                $sentDocs->email =  $customer->email;
                $sentDocs->sentBy = $sentBy;
                $sentDocs->doc = 'Policy Document';
                $sentDocs->documents = serialize($attachments);
                $sentDocs->save();
                return true;
            }else{
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    /*generates unique id*/
    public function uuid(){
        $current_timestamp = Carbon::now()->timestamp;
        return $current_timestamp;
    }

    public function generateStoreCoverNote($policyId,Request $request){
        return $this->generateCoverCancelNote($policyId,$request->doc_type);
    }

    public function generateCoverCancelNote($policyId,$type,$requestFrom="Web"){
        try{
            if($policyId != null){
                $policy = Policy::join('customer','customer.id','policies.customer_id')
                    ->join('vehicle','vehicle.policy_id','policies.id')
                    ->join('customer_banking','customer_banking.policy_id','policies.id')
                    ->where('policies.id',$policyId)
                    ->first(
                        array(
                            'policies.id as policy_id',
                            'policies.policyNumber as policyNumber',
                            'policies.policyActivatedDate',
                            'policies.billingStartDate',
                            'policies.created_at as created_at',
                            'vehicle.make',
                            'vehicle.model',
                            'vehicle.year',
                            'vehicle.vehiclePlate',
                            'vehicle.financial_interest',
                            'vehicle.financial_interest_other',
                            'policies.sum_assured as estimated_value',
                            'customer_banking.billing as payment_method',
                            'customer_banking.bankName',
                            'customer_banking.branchCode',
                            'customer_banking.billing',
                            'customer.firstName',
                            'customer.middleName',
                            'customer.lastName',
                            'customer.email',
                            'customer.id as customer_id',
                        )
                    );


                if($policy != null){
                    if($policy->financial_interest){
                        $i = $policy->financial_interest;
                    }elseif($policy->financial_interest_other){
                        $i = $policy->financial_interest_other;
                    }else{
                        $i = null;
                    }

                    // $today = new DateTime($policy->created_at);
                    // $from = $today->format('d-m-Y');
                    // $interval = new DateInterval('P1Y');
                    // $to = $today->add($interval)->modify("-1 day")->format('d-m-Y');

                    $term = PolicyTerm::where('policy_id',$policyId)->where('status','Active')->orderBy('id','DESC')->first();

                    if(isset($term)){
                        $from = Carbon::parse($term->term_start_date)->format('d-m-Y');
                        $to = Carbon::parse($term->term_end_date)->format('d-m-Y');
                        // $toDate = Carbon::parse($term->term_end_date)->subDays(1)->format('d-m-Y');
                        // $premium = $term->premium;
                        // $freq = $term->frequency;
                    } else {
                        if (isset($policy->policyActivatedDate)) {
                            $from = Carbon::parse($policy->policyActivatedDate)->format('d-m-Y');
                            $to = Carbon::parse($policy->policyActivatedDate)->addYear()->subDays(1)->format('d-m-Y');
                            // $premium = $policy->premium;
                            // $freq = $policy->premium_freq;
                        } else {
                            if (isset($policy->billingStartDate)) {
                                $from = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                                $to = Carbon::parse($policy->billingStartDate)->addYear()->subDays(1)->format('d-m-Y');
                                // $premium = $policy->premium;
                                // $freq = $policy->premium_freq;
                            } else {
                                $from = Carbon::parse($policy->created_at)->format('d-m-Y');
                                $to = Carbon::parse($policy->created_at)->addYear()->subDays(1)->format('d-m-Y');
                                // $premium = $policy->premium;
                                // $freq = $policy->premium_freq;
                            }

                        }

                    }

                    if($policy->payment_method == "RealPay"){
                        if($policy->bankName && $policy->branchCode){
                            $bank = Banks::where('bank_number',$policy->bankName)->first(array('bank_name'));
                            $branch = BankBranches::where('bank_id',$policy->bankName)
                                ->where('branch_id',$policy->branchCode)
                                ->first(array('name'));

                            $policy['bankName'] = $bank->bank_name;
                            $policy['branchCode'] = $branch->name;
                        }
                    }

                    $today = new DateTime();
                    $policy['curr_date'] = $today->format('d-m-Y');
                    $policy['financial_interest'] = $i;
                    $pathqr = $this->generateQRCode($policy->policy_id);

                    $array =[
                        'policy'=>$policy,
                        'to'=>$to,
                        'from'=>$from,
                        'QRCode'=>$pathqr,
                    ];

                    $path = 'Policy/'. $this->uuid() .'/'.$type.'_'.$policy->policyNumber.'.pdf';
                    libxml_use_internal_errors(true);

                    if($type == 'Cancel') {
                        $pdf = PDF::loadView('admin.notes.cancel_note-New', $array);
                        $hook = 'policy_cancel_note';
                    }
                    elseif($type == 'Cover') {
                        $pdf = PDF::loadView('admin.notes.cover_note-New', $array);
                        $hook = 'policy_cover_note';
                    }
                    else {
                        return Redirect::back()->with('error', 'Sorry! Please specify document type to generate pdf');
                    }

                    Storage::disk('s3')->put($path, $pdf->output(), 'public');

                    if ($type == 'Cancel') {
                        $store = PolicyCoverCancelNote::where('policy_id',$policyId)->where('doc_type', 'Cancel')->orderBy('id', 'DESC')->first();

                    } else {
                        $store = PolicyCoverCancelNote::where('policy_id',$policyId)->where('doc_type', 'Cover')->orderBy('id', 'DESC')->first();

                    }

                    if($store == null)
                        $store = new PolicyCoverCancelNote();

                    $store->policy_id = $policy->policy_id;
                    $store->path = $path;
                    $store->doc_type = $type;
                    $store->save();

//                        $store = PolicyCoverCancelNote::updateOrCreate(
//                            ['policy_id' => $policy->policy_id, 'doc_type' => $type],
//                            ['path' => $path]
//                        );

                   // $qrCode = 'images/qrcode'.$policy->policy_id.'.png';

                    if(File::exists($pathqr)) {
                        File::delete($pathqr);
                    }

                    $attachments = array();
                    array_push($attachments,$path);
                    $p = Policy::where('policyNumber',$policy->policyNumber)->first();

                    if($policy->email != null){
                        $data = new \stdClass();
                        $data->hook = $hook;
                        $data->customer_id = $policy->customer_id;
                        $data->policy_id = $policy->policy_id;
                        $data->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($policy->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                       // $sent = Mail::to($policy->email)->send(new MailTemplate($data));
                    }
                    if($requestFrom == "LiveQuote"){
                        activity('Create')
                            ->performedOn($p)
                            ->causedBy('customer')
                            ->log('Policy '. $type .' note has been created');
                        return  $path;
                    }else{
                        activity('Create')
                            ->performedOn($p)
                            ->causedBy(User::where('id',auth()->user()->id)->first())
                            ->log('Policy '. $type .' note has been created');
                    }
                    return Redirect::back()->with('success', 'Policy '.$type.' note generated and stored successfully');
                }else{
                    return Redirect::back()->with('error', 'Sorry! Policy details not found');
                }

            }else{
                return Redirect::back()->with('error', 'Sorry! Please provide policy Id to generate cover note');
            }
        }catch(Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }


      public function test_adi() {

        return view('admin.notes.instance_personal_ADI');

      }

    public function generateCoverCancelNoteCRON($policyId,$type,$requestFrom="Web",$termId=null){
        try{
            if($policyId != null){
                $policy = Policy::join('customer','customer.id','policies.customer_id')
                    ->join('vehicle','vehicle.policy_id','policies.id')
                    ->join('customer_banking','customer_banking.policy_id','policies.id')
                    ->where('policies.id',$policyId)
                    ->first(
                        array(
                            'policies.id as policy_id',
                            'policies.policyNumber as policyNumber',
                            'policies.created_at as created_at',
                            'vehicle.make',
                            'vehicle.model',
                            'vehicle.year',
                            'vehicle.vehiclePlate',
                            'vehicle.financial_interest',
                            'vehicle.financial_interest_other',
                            'policies.sum_assured as estimated_value',
                            'customer_banking.billing as payment_method',
                            'customer_banking.bankName',
                            'customer_banking.branchCode',
                            'customer_banking.billing',
                            'customer.firstName',
                            'customer.lastName',
                            'customer.email',
                            'customer.id as customer_id',
                        )
                    );

                if($policy != null){
                    if($policy->financial_interest){
                        $i = $policy->financial_interest;
                    }elseif($policy->financial_interest_other){
                        $i = $policy->financial_interest_other;
                    }else{
                        $i = null;
                    }

                    if($termId != null)
                        $term = PolicyTerm::where('id',$termId)->first();
                    else
                        $term = PolicyTerm::where('policy_id',$policyId)->where('status', 'Active')->orderby('id','DESC')->first();

                    if($term != NULL)
                    {
                        $from = Carbon::parse($term->term_start_date)->format('d-m-Y');
                        $to = Carbon::parse($term->term_end_date)->format('d-m-Y');
                    } else {
                        $today = new DateTime($policy->created_at);
                        $from = $today->format('d-m-Y');
                        $interval = new DateInterval('P1Y');
                        $to = $today->add($interval)->modify("-1 day")->format('d-m-Y');
                    }

                    if($policy->payment_method == "RealPay"){
                        if($policy->bankName && $policy->branchCode){
                            $bank = Banks::where('bank_number',$policy->bankName)->first(array('bank_name'));
                            $branch = BankBranches::where('bank_id',$policy->bankName)
                                ->where('branch_id',$policy->branchCode)
                                ->first(array('name'));

                            $policy['bankName'] = $bank->bank_name;
                            $policy['branchCode'] = $branch->name;
                        }
                    }

                    $today = new DateTime();
                    $policy['curr_date'] = $today->format('d-m-Y');
                    $policy['financial_interest'] = $i;
                    $qrCode = $this->generateQRCode($policy->policy_id);

                    $array =[
                        'policy'=>$policy,
                        'to'=>$to,
                        'from'=>$from,
                        'QRCode'=>$qrCode,
                    ];

                    $path = 'Policy/'. $this->uuid() .'/'.$type.'_'.$policy->policyNumber.'.pdf';
                    libxml_use_internal_errors(true);

                    if($type == 'Cancel') {
                        $pdf = PDF::loadView('admin.notes.cancel_note-New', $array);
                        $hook = 'policy_cancel_note';
                    }
                    elseif($type == 'Cover') {
                        $pdf = PDF::loadView('admin.notes.cover_note-New', $array);
                        $hook = 'policy_cover_note';
                    }
                    else {
                        return Redirect::back()->with('error', 'Sorry! Please specify document type to generate pdf');
                    }

                    Storage::disk('s3')->put($path, $pdf->output(), 'public');

                    $store = PolicyCoverCancelNote::where('policy_id',$policyId)->first();

                    if($store == null)
                        $store = new PolicyCoverCancelNote();

                    $store->policy_id = $policy->policy_id;
                    $store->path = $path;
                    $store->doc_type = $type;
                    $store->save();

                   if(File::exists($qrCode)) {
                        File::delete($qrCode);
                    }

                    return $path;
                }else{
                    return null;
                }

            }else{
                return null;
            }
        }catch(Exception $e){
            return null;
        }
    }

    //test document cron
    public function testCronDocument()
    {
//        $policies = Policy::leftJoin('customer', 'customer.id', 'policies.customer_id')
//        ->leftJoin('customer_profile','customer_profile.customer_id','policies.customer_id')
//        ->where('policies.status',1)
//        ->get(array(
//            'policies.id as policy_id',
//            'policies.policyNumber',
//            'policies.status',
//            'customer.firstName as customerFname',
//            'customer.middleName as customerMname',
//            'customer.lastName as customerLname',
//            'customer.email',
//            'customer.cellphone',
//        ));

        $policies = Policy::where('status',1)
            ->groupBy('product_id')
            ->orderBy('id','desc')
            ->get(array('id','product_id'));

        if(count($policies) > 0){

            foreach($policies as $policy)
            {
                if($policy->product_id == 3){
                    $policyGenerated = $this->generatePolicyDocument($policy->id);
                }
                $sentBy = 'System';
                $getDocument =  $this->sendPolicyDocument($policy->id,$sentBy);
            }
            if ($policies){
                return response()->json(['success' => true, 'policies' => $policies], 200);
            }
            else{
                return response()->json(['success' => false, 'message' => 'No data found.'], 401);
            }

        }else{
            return response()->json(['success' => false, 'message' => 'No data found.'], 401);
        }
    }
    //end test document cron

    // Start API covercancle note

    // public function getCoverCancelNote($policyId,$docs_type){
    //     /*Start*/
    //     if($policyId != null){
    //         $p = PolicyCoverCancelNote::where('policy_id',$policyId)->first();

    //         $path = null;
    //         $path = "";
    //         if($p->path == "" || $p->path == null ){
    //             $path = $this->generateCoverCancelNote($policyId,$docs_type,"LiveQuote");
    //             // $path = Helper::getCloudFrontURL($p);
    //           }else{
    //             $path = $p->path;
    //           }

    //         if($path){
    //             return response()->json(['success'=> true, 'cover_cancel_file'=>$path, 'p'=>$p], 200);
    //         }else{
    //             return response()->json(['success'=> false, 'message'=>'Data not found'], 401);
    //         }

    //     }

    //     /*End*/
    // }
    // End API covercancle note

    public function generateInformationDocument($id){
        try{
            $data = Policy::leftJoin('customer', 'customer.id', 'policies.customer_id')
                ->leftJoin('customer_profile','customer_profile.customer_id','policies.customer_id')
                ->leftJoin('customer_kyc','customer_kyc.customer_id','policies.customer_id')
                ->leftJoin('vehicle','vehicle.policy_id','policies.id')
                ->leftJoin('countries','countries.id','customer_profile.countryId')
                ->leftJoin('states','states.id','customer_profile.state')
                ->leftJoin('products','products.id','policies.product_id')
                ->leftJoin('product_plans','product_plans.id','policies.plan_id')
                ->leftJoin('stores','stores.id','policies.storeID')
                ->leftJoin('users','users.id','policies.agent_id')
                ->where('policies.id',$id)
                ->first(array(
                    'policies.id as policy_id',
                    'policies.policyNumber',
                    'policies.premium_freq',
                    'policies.sum_assured',
                    'policies.premium',
                    'policies.note',
                    'policies.first_premium',
                    'policies.billingStartDate',
                    'policies.customer_id as customer_id',
                    'policies.is_bundled',
                    'policies.bundled_discount',
                    'customer.id as C_id',
                    'customer.firstName',
                    'customer.lastName',
                    'customer.email',
                    'customer.cellphone',
                    'customer_profile.customer_id as cp_id',
                    'customer_profile.gender',
                    'customer_profile.dob',
                    'customer_profile.maritalstatus',
                    'customer_profile.omang',
                    'customer_profile.passport',
                    'customer_profile.address',
                    'customer_profile.city',
                    'customer_profile.state',
                    'customer_profile.countryId',
                    'customer_profile.e_name',
                    'customer_profile.emp_no',
                    'customer_profile.emp_phone',
                    'customer_profile.salary_pay_date',
                    'products.id as product_id',
                    'products.has_vehicle',
                    'products.has_member',
                    'products.slug as product_name',
                    'policies.created_at',
                    'product_plans.slug as plan_name',
                    'users.firstName as agent_fname',
                    'users.lastName as agent_lname',
                    'stores.name as store_name',
                    'customer_kyc.passportExpiry as pExpiry',
                    'customer_kyc.omangExpiry as omangExpiry',
                    'countries.name as pic',
                    'states.name as stateName',
                    'vehicle.is_private as purpose',
                ));

            if($data != null){
                $vehicle = null;
                //if($data->has_vehicle){
                    $vehicle = Vehicle::where('policy_id',$data->policy_id)
                        ->orderBy('id','DESC')
                        ->first();
                //}

                $policy_cellphone = PolicyCellPhone::where('policy_id',$data->policy_id)
                    ->orderBy('id','DESC')
                    ->get();


                $beneficiaries = null;

                //if($data->has_member){
                    $beneficiaries = PolicyBeneficiary::where('policy_id',$data->policy_id)->get();
                //}

                $banking = CustomerBanking::where('policy_id',$data->policy_id)
                    ->orderBy('id','DESC')
                    ->first();

                $date = new DateTime($data->created_at);
                $today = $date->format('d-m-Y');

                if($banking->billing == "RealPay"){
                    if($banking->bankName && $banking->branchCode){
                        $bank = Banks::where('bank_number',$banking->bankName)->first(array('bank_name'));
                        $branch = BankBranches::where('bank_id',$banking->bankName)
                            ->where('branch_id',$banking->branchCode)
                            ->first(array('name'));

                        if($bank == null)
                            $bank_name = '-';
                        else
                            $bank_name = $bank->bank_name;


                        if($branch == null)
                            $branch_name = '-';
                        else
                            $branch_name = $branch->name;

                        $banking['bankName'] = $bank_name;
                        $banking['branchCode'] = $branch_name;
                    }
                }
                $bundles = null;
                if($data->is_bundled == 1)
                {
                    $bundles = PolicyBundled::where('policy_id', $data->policy_id)->get(array('product_id','plan_name','premium', 'final_premium', 'subtotal'))->toArray();
                    // $data->premium = number_format( ( $bundles[0]['final_premium'] + $data->bundled_discount - $bundles[0]['subtotal'] ),2,'.',',');
                }

                $array = [
                    'data'=>$data,
                    'benef' => $beneficiaries,
                    'vehicle' => $vehicle,
                    'banking' => $banking,
                    'policy_cellphone' =>$policy_cellphone,
                    'created_date' => $today,
                    'bundles' => $bundles
                ];

                $path = 'Policy/'. $this->uuid() .'/'. $data->policyNumber .'/Information_Verification.pdf';
                //dd($path);
                libxml_use_internal_errors(true);
                $pdf = PDF::loadView('admin.notes.information', $array);
                // Storage::disk('local')->put('public/example'.$data->policy_id.'.pdf', $pdf->output());
                Storage::disk('s3')->put($path, $pdf->output(), 'public');

                if($data->policy_id){
                    $policy = Policy::where('id',$data->policy_id)->first(array('verification_doc'));
                    $policy->verification_doc = $path;
                    $policy->save();
                }

                $attachments = array();
                array_push($attachments, $path);

                $policy_id = $data->policy_id;
                $customer_id = $data->C_id;
                $email = $data->email;

                if($email){
                    $data2 = new \stdClass();
                    //$data->user_id = $policy_id;
                    $data2->hook = 'info_verification_email';
                    $data2->customer_id = $customer_id;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $data->policyNumber,'hook' => $data2->hook]));
                 //   $sent = \Illuminate\Support\Facades\Mail::to($email)->send(new MailTemplate($data));
                }
                return $path;

            }else{
                return 2;
            }
        }catch(Exception $e){
            return 2;
        }
    }

    public function accountStatement($id) {

        $data = Ledger::where('policy_id', $id)
        ->where(function ($query) {
            $query->whereNotNull('invoice_file')->orWhereNotNull('credit')->orWhere('trans_type', 'Refund');
        })->orderBy('id', 'asc')->orderBy('accounting_date', 'asc')->get();

        $policy = Policy::where('id',$id)->first(array('customer_id','policyNumber'));

        $policyNumber =  $policy['policyNumber'];
        $customer = Customer::where('id',$policy['customer_id'])->first();

        $customer_profile = CustomerProfile::where('customer_id',$policy['customer_id'])->first();
        $balance = Ledger::where('policy_id', $id)->orderBy('id', 'DESC')->first(array('balance'));
        if ($balance != null) {
            $balance = $balance->balance;
        } else {
            $balance = 0;
        }
        $array = [
            'data'=>$data,
            'balance'=>$balance,
            'customer'=>$customer,
            'customer_profile'=>$customer_profile,
        ];

        // view()->share('Customer',$array);
        $pdf = PDF::loadView('admin.notes.account_statement',$array);
        // return view('admin.notes.account_statement',$array);
        // download PDF file with download method
        return $pdf->download($customer->firstName.'_'.$customer->lastName.'_'.$policyNumber.'.pdf');
      }

      public function beneficiaryList()
      {
         $beneficiaries = Policy::leftJoin('policy_beneficiary','policy_beneficiary.policy_id','=','policies.id')
                              ->leftJoin('customer', 'customer.id', 'policies.customer_id')
                              ->where('policies.product_id', 1)
                              ->orderBy('policies.id', 'DESC')
                              ->get([
                                  'policies.id',
                                  'policies.policyNumber',
                                  'policies.customer_id',
                                  'customer.firstName as CustomerfirstName',
                                  'customer.lastName as CustomerLastName',
                                  'customer.email as CustomerEmail',
                                  'customer.cellphone as CustomerCellphone',
                                  'policy_beneficiary.relation as BeneficiaryRelation',
                                  'policy_beneficiary.first_name as BeneficiaryfirstName',
                                  'policy_beneficiary.last_name as BeneficiaryLastName',
                                  'policy_beneficiary.dob as BeneficiaryDOB',
                                  'policy_beneficiary.gender as BeneficiaryGender',
                                  'policy_beneficiary.payment as BeneficiaryPayment',
                                  'policy_beneficiary.omang as BeneficiaryOmang',
                                  'policy_beneficiary.passport as BeneficiaryPassport',
                              ]);

          $count = count($beneficiaries);
          if ($count > 0) {
              return response()->json([
                  'success' => true, 'message' => "found Data",'beneficiaries' => $beneficiaries
              ], 200);
          } else {
              return response()->json([
                  'success' => false, 'message' => "Not found Data"
              ], 401);
          }
      }



    public function sendVerificationDoc($id){
        try{
        }catch(Exception $e){
            return null;
        }
    }

    public function generateQRCode($policyID){
        try{
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data(env('QRCODE_URL').base64_encode($policyID)) //base64 encoding
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(205)
                ->margin(5)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();

            header('Content-Type: '.$result->getMimeType());

            // echo $result->getString();
            //$path = 'images/qrcode'.$policyID.'.png';
            $path = public_path().'/qrcode'.$policyID.'.png';
            // Save it to a file
            $result->saveToFile($path);

            // Generate a data URI to include image data inline (i.e. inside an <img> tag)
            $dataUri = $result->getDataUri();

            return $path;
        }catch(\Exception $e){
            return null;
        }
    }

    public function sendPolicyDocumentForRenew($policyId,$sentBy='Agent')
    {
        try {
            $policy = Policy::where('id',$policyId)->first();
            $product = Product::where('id',$policy->product->id)->first(array('id','has_schedule','has_wordings'));
            $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName','middleName','email'));
            $docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));

           $policyCoverNote = PolicyCoverCancelNote::where('policy_id',$policyId)
                ->where('doc_type','Cover')
                ->orderBy('id','DESC')
                ->first(array('path'));

            $attachments = array();
            // if($product->has_wordings == 1){
            //     if(count($docs) > 0){
            //         foreach($docs as $doc){
            //             if($doc->link)
            //                 array_push($attachments, $doc->link);
            //         }
            //     }
            // }

            if($product->has_schedule == 1) {
                if ($policy->policyDocument != null)
                    array_push($attachments, $policy->policyDocument);
            }

            // if($policyCoverNote != null){
            //     if ($policyCoverNote->path != null)
            //         array_push($attachments, $policyCoverNote->path);
            // }

            if($customer->email != null){
               $data = new \stdClass();
               //$data->user_id = $policy->id;
               $data->hook = 'renew_policy';
               $data->customer_id = $policy->customer_id;
               $data->policy_id = $policy->id;
               $data->attachment = $attachments;
               $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
               $markdown = new MailTemplate($data);
               $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
            //    event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
               //$sent = Mail::to($customer->email)->send(new MailTemplate($data));
               event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));

                $sentDocs = new sentPolicyDocumentLogs();
                $sentDocs->policyNumber = $policy->policyNumber;
                $sentDocs->email =  $customer->email;
                $sentDocs->sentBy = $sentBy;
                $sentDocs->doc = 'Policy Document';
                $sentDocs->documents = serialize($attachments);
                $sentDocs->save();
                return true;
            }else{
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }
}
