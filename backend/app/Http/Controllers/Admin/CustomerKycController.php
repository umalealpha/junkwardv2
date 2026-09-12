<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\AgentKyc;
use AlphaDirect\ArchivedKYC;
use AlphaDirect\Country;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerKycDomCom;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Models\Kycclone;
use AlphaDirect\Models\Request;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Transaction;
use AlphaDirect\Vehicle;
use AlphaDirect\Models\Audits;
use Http\Client\Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Redirect;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;
use DB;
use AlphaDirect\User;
use AlphaDirect\KycActivityLogs;
use Illuminate\Cache\RateLimiting\Limit;
use OwenIt\Auditing\Models\Audit;
use AlphaDirect\Models\CustomerKycDelete;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\KycCase;
use AlphaDirect\Models\AmlResult;
use AlphaDirect\Jobs\RunOpenSanctionsJob;
use AlphaDirect\Models\RekycLink;

class CustomerKycController extends Controller
{

    /**shows listing of all agent KYC upload data
     * @return View KYC upload listing page
     */
    public function agentKycAppUpload()
    {
        if (Auth::user()->hasPermissionTo('customer-kyc-list'))
        {
            $kycDocuments = AgentKyc::all();
            return view('admin.agentAppUploads.kyc', compact('kycDocuments'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /*
        * Pass data through ajax call
        */
    /**
     * @return mixed
     */
    public function kycData(\Illuminate\Http\Request $request)
    {
        try{
            ## Read value
            $draw = $request->get('draw');
            $start = $request->get("start");
            $rowperpage = $request->get("length"); // Rows display per page

            $columnIndex_arr = $request->get('order');
            $columnName_arr = $request->get('columns');
            $order_arr = $request->get('order');
            $search_arr = $request->get('search');

            $columnIndex = $columnIndex_arr[0]['column']; // Column index
            $columnName = $columnName_arr[$columnIndex]['data']; // Column name
            $columnSortOrder = $order_arr[0]['dir']; // asc or desc
            $searchValue = trim($search_arr['value']); // Search value

            // ULTRA-OPTIMIZED: Get distinct customer_ids with active policies first
            // This query is fast because it uses indexes on policies.status and policies.product_id
            // Exclude DOM/COM-tier products (7,8,16-19,20,22) from this
            // MIS-tier KYC listing — they have their own review flow via
            // verifyDomCom() + the new API V1 DomComKycController.
            // Mirrors V8 CustomerKycController.php line 91.
            $customerIdsQuery = DB::table('policies')
                ->select('customer_id')
                ->where('status', 1)
                ->whereNotIn('product_id', \AlphaDirect\Support\KycDomComProducts::IDS)
                ->distinct();

            // Apply status filter if needed
            if($request->status == 'Cancelled') {
                $customerIdsQuery->where('status', 2);
            }

            // Get customer IDs as array for faster whereIn
            $customerIds = $customerIdsQuery->pluck('customer_id')->toArray();
            
            if (empty($customerIds)) {
                // No matching customers, return empty result
                $policies = array(
                    "draw" => intval($draw),
                    "iTotalRecords" => 0,
                    "iTotalDisplayRecords" => 0,
                    "aaData" => []
                );
                echo json_encode($policies);
                exit;
            }

            // Build main query - Direct query on customer_kyc with customer join only
            // NO policies join here - we'll get policy data separately if needed
            $baseQuery = DB::table('customer_kyc')
                ->whereIn('customer_kyc.customer_id', $customerIds)
                ->leftJoin('customer', 'customer.id', '=', 'customer_kyc.customer_id')
                ->select(
                    'customer_kyc.customer_id',
                    'customer_kyc.id',
                    'customer.firstName',
                    'customer.middleName',
                    'customer.lastName',
                    'customer_kyc.omang',
                    'customer_kyc.omangBack',
                    'customer_kyc.passport',
                    'customer_kyc.driving_license',
                    'customer_kyc.proof_residence',
                    'customer_kyc.proof_income',
                    'customer_kyc.compliance',
                    'customer_kyc.status',
                    'customer_kyc.remark',
                    'customer_kyc.updated_at',
                    'customer.company_id'
                );

            // Apply filters early (uses indexes on customer_kyc.status and customer_kyc.compliance)
            if($request->status != '' && $request->status != 'Cancelled')
            {
                $baseQuery->where('customer_kyc.status', $request->status);
            }

            if($request->compliance_status != '')
            {
                $baseQuery->where('customer_kyc.compliance', $request->compliance_status);
            }

            // Apply search filter
            if($searchValue != null){
                $baseQuery->where(function($q) use ($searchValue){
                    $q->where('customer.firstName', 'like', '%' .$searchValue . '%')
                      ->orWhere('customer.lastName', 'like', '%' .$searchValue . '%')
                      ->orWhere('customer.cellphone', 'like', '%' .$searchValue . '%')
                      ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'),'like','%'.$searchValue.'%')
                      ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.lastName)'),'like','%'.$searchValue.'%');
                });
            }

            // Get total count - fast count query
            $totalRecords = count($customerIds);

            // Get filtered count
            $totalRecordswithFilter = $baseQuery->count();

            // Get paginated results - NO GROUPBY, direct query with ordering
            $records = $baseQuery->orderBy('customer_kyc.updated_at', 'DESC')
                        ->skip($start)
                        ->take($rowperpage)
                        ->get();
            
            $records = json_decode($records, true);
            
            // Get policy data for the returned customers in one batch query (if needed)
            $returnedCustomerIds = array_column($records, 'customer_id');
            $policiesData = [];
            if (!empty($returnedCustomerIds)) {
                // Get one policy per customer - use whereIn with orderBy and limit per group
                // This is faster than groupBy on large datasets
                $policiesData = DB::table('policies')
                    ->select('customer_id', 'status as policy_status', 'product_id')
                    ->whereIn('customer_id', $returnedCustomerIds)
                    ->where('status', 1)
                    ->whereNotIn('product_id', [7, 8])
                    ->orderBy('id', 'ASC') // Get first policy per customer
                    ->get()
                    ->groupBy('customer_id')
                    ->map(function($group) {
                        return $group->first(); // Get first policy for each customer
                    })
                    ->toArray();
            }
            
            // Merge policy data into records
            foreach($records as &$record) {
                if (isset($policiesData[$record['customer_id']])) {
                    $policyData = $policiesData[$record['customer_id']];
                    $record['policy_status'] = $policyData->policy_status ?? null;
                    $record['product_id'] = $policyData->product_id ?? null;
                } else {
                    $record['policy_status'] = null;
                    $record['product_id'] = null;
                }
            }
            
            // OPTIMIZED: Eager load CustomerProfile and Company data to avoid N+1 queries
            $customerIds = array_filter(array_column($records, 'customer_id'));
            $customerProfiles = [];
            $companies = [];
            
            if (!empty($customerIds)) {
                // Batch load CustomerProfile (uses index on customer_profile.customer_id)
                $customerProfiles = CustomerProfile::whereIn('customer_id', $customerIds)
                    ->pluck('entity_type', 'customer_id')
                    ->toArray();
                
                // Batch load Company data (uses index on company.id)
                $companyIds = array_filter(array_column($records, 'company_id'));
                if (!empty($companyIds)) {
                    $companies = Company::whereIn('id', $companyIds)
                        ->pluck('name', 'id')
                        ->toArray();
                }
            }

            $data_arr = array();
            $sno = $start+1;
            foreach($records as $record){

                if($record['customer_id'])
                    $id = $record['customer_id'];
                else
                    $id = 'N/A';

                if($record['customer_id']){
                    if($record['omang'] != null){
                        $omangNumber = $record['omang'];
                    }else{
                        $omangNumber = 'N/A';
                    }

                    if($record['passport'] != null){
                        $passportNumber = $record['passport'];
                    }else{
                        $passportNumber = 'N/A';
                    }


                    if($record['customer_id']){
                        // DOM/COM-tier (7,8,16-19,20,22) — organisation
                        // policies use entity-type display instead of
                        // individual customer name. V8 parity.
                        if (\AlphaDirect\Support\KycDomComProducts::includes($record['product_id'])) {
                            $customerName = 'N/A';
                            // Use pre-loaded data instead of querying (eliminates N+1 queries)
                            $entityType = $customerProfiles[$record['customer_id']] ?? null;
                            if (!empty($entityType) && $entityType == "Organisation") {
                                $companyName = isset($record['company_id']) ? ($companies[$record['company_id']] ?? null) : null;
                                if (isset($companyName)) {
                                    $customerName = $companyName;
                                } else {
                                    $customerName = trim($record['firstName'] . ' ' . $record['middleName'] . ' ' . $record['lastName']);
                                }
                            }else{
                                $customerName = trim($record['firstName'] . ' ' . $record['middleName'] . ' ' . $record['lastName']);
                            }
                        } else {
                            $name = trim($record['firstName'].' '.$record['middleName'].' '.$record['lastName']);
                            $customerName ='<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"> ' . $name . '</a>';
                        }

                    }else{
                        $customerName = 'N/A';
                    }
                }else{
                    $omangNumber = 'N/A';
                }

                if($record['customer_id']){

                    if ($record['omang'] != null)
                    {
                        $omang = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($record['omang']) . ' " target= "_blank"> Omang front picture download link </a>';
                    }else{
                        $omang = 'Not Uploaded';
                    }

                    if ($record['omangBack'] != null)
                    {
                        $omangBack = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($record['omangBack']) . ' " target= "_blank"> Omang Back Picture Download Link </a>';
                    }else{
                        $omangBack = 'Not Uploaded';
                    }

                    if ($record['driving_license'] != null)
                    {
                        $driving_license = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($record['driving_license']) . ' " target= "_blank"> Driving License Download Link </a>';
                    }else{
                        $driving_license = 'Not Uploaded';
                    }

                    if ($record['proof_residence'] != null)
                    {
                        $proof_residence = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($record['proof_residence']) . ' " target= "_blank"> Proof Residence Download Link </a>';
                    }else{
                        $proof_residence = 'Not Uploaded';
                    }

                    if ($record['proof_income'] != null)
                    {
                        $proof_income = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($record['proof_income']) . ' " target= "_blank"> Proof Income Download Link </a>';
                    }else{
                        $proof_income = 'Not Uploaded';
                    }

                    if ($record['passport'] != null)
                    {
                        $passport = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($record['passport']) . ' " target= "_blank"> Pasport Picture Download Link </a>';
                    }else{
                        $passport = 'Not Uploaded';
                    }

                    if ($record['compliance'] == 1)
                    {
                        $compliance = 'Compliant';
                    }elseif($record['compliance'] == 2){
                        $compliance = 'Non-Compliant';
                    }elseif($record['compliance'] == 0){
                        $compliance = 'Verification Pending';
                    }elseif($record['compliance'] == 3){
                        $compliance = 'No Id-No Documents';
                    }else{
                        $compliance = 'N/A';
                    }

                    if($record['policy_status'] == '2'){
                        $status= 'Cancelled';
                    }else{

                        if ($record['status'] != null )
                        {
                            // if($record['status'] == 'Approve')
                            // {
                            //     $status= '<span class="text-success">Approved</span>';
                            // }
                            // if($record['status'] == 'Unapprove')
                            // {
                            //     $status= '<span class="text-danger">Rejected</span>';
                            // }
                            // if($record['status'] == 'Unchecked'){
                            //     $status= '<span class="text-primary">'.$record['status'].'</span>';
                            // }
                            // if($record['status'] == 'Recheck'){
                            //     $status= '<span class="text-info">'.$record['status'].'</span>';
                            // }

                            $status= $record['status'];

                        }else{
                            $status = 'N/A';
                        }
                    }

                    if ($record['remark'] != null)
                    {
                        $remark = $record['remark'];
                    }else{
                        $remark = 'N/A';
                    }
                }else{
                    $kyc = 'N/A';
                }

                if($record['updated_at'] != null)
                {
                    $updated_at= Carbon::parse($record['updated_at'])->format('Y-m-d H:i');
                }else{
                    $updated_at='N/A';
                }


                $actions = '';

                $actions .= '<a href="' . route('admin.viewCustomerKycData', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';

                $actions .= '<a href="' . route('admin.verifyCustomerKycData', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-eye"></i>
                            </a>';

                if (auth()->user()->hasRole('Super Admin')) {
                    $actions .= '<a href="'. route('admin.archiveKYC',$record['id']) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-archive" title="Archive Record">
                                <i class="la la-archive"></i>
                            </a>';
                }

                // $actions .= '<a href="" value="'.$kyc->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md kyc-confirm-delete" title="Delete">
                //     <i class="la la-trash"></i>
                //    </a>';

                $data_arr[] = array(
                    "id" => $id,
                    "omangNumber" => $omangNumber,
                    "passportNumber" => $passportNumber,
                    "customer_name" => $customerName,
                    "omang" => $omang,
                    "omangBack" => $omangBack,
                    "passport" => $passport,
                    "driving_license" => $driving_license,
                    "proof_residence" => $proof_residence,
                    "proof_income" =>$proof_income,
                    "compliance" =>$compliance,
                    "updated_at"=>$updated_at,
                    "status" =>$status,
                    "remark" =>$remark,
                    "action" =>$actions,
                    "stylesheet" =>$actions,

                );

            }

            $policies = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data_arr
            );

            echo json_encode($policies);
            exit;

        }catch(\Exception $exception){

        }
    }

    public function destroy(\Illuminate\Http\Request $request,$id)
    {
        $kycCompliance = KYC::where('id',$id)->first();
        $customer_data = Customer::where('id', $kycCompliance->customer_id)->first();
          $policyController = new PolicyController();

         if($request->image_data == 'omang_front'){
            if($kycCompliance->customer_id != null){
                 $policyController->customerKYCDeleteOmang($kycCompliance->customer_id);
            }
            $kycCompliance->omang = null;
            $kycCompliance->omangExpiry= null;
         }elseif($request->image_data == 'omang_back'){
            if($kycCompliance->customer_id != null){
                 $policyController->customerKYCDeleteOmangBack($kycCompliance->customer_id);
            }
            $kycCompliance->omangBack= null;
            $kycCompliance->omangExpiry= null;
         }elseif($request->image_data == 'data_passport'){
            if($kycCompliance->customer_id != null){
                 $policyController->customerKYCDeletePassport($kycCompliance->customer_id);
            }
            $kycCompliance->passport= null;
            $kycCompliance->passportExpiry= null;
         }elseif($request->image_data == 'drivers_license'){
            if($kycCompliance->customer_id != null){
                 $policyController->customerKYCDeleteDrivingLicense($kycCompliance->customer_id);
            }
            $kycCompliance->driving_license= null;
            $kycCompliance->licenseExpiry = null;
         }elseif($request->image_data == 'proof_residence'){
            if($kycCompliance->customer_id != null){
                 $policyController->customerKYCDeleteProofResidence($kycCompliance->customer_id);
            }
            $kycCompliance->proof_residence= null;
         }elseif($request->image_data == "proof_income"){
            if($kycCompliance->customer_id != null){
                 $policyController->customerKYCDeleteProofIncome($kycCompliance->customer_id);
            }
            $kycCompliance->proof_income= null;
         }
         $kycCompliance->compliance= 0;
         $kycCompliance->save();

         activity('Customer KYC')
            ->performedOn($customer_data)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Customer KYC Document Deleted Successfully');

        return Redirect::back()->with('success', 'Customer KYC Deleted Successfully');
    }

    public function view($id)
    {
        if (Auth::user()->hasPermissionTo('customer-kyc-view'))
        {
        $data = KYC::where('id',$id)->first();
        $policydetails = Policy::where('customer_id',$data->customer_id)->first();
       if($policydetails){
        $activity = Audit::orderBy('created_at', 'desc')
        ->where('event','updated')
        ->where('auditable_type','AlphaDirect\KYC')
        //->where('policy_id',$policydetails->id)
        ->where('user_id','!=',null)
        ->where('policy_number',$policydetails->policyNumber)
        ->first(array('id','agent_id','user_id'));

        if($activity){
            $performedBy = User::where('id',$activity->user_id)->first(array('firstName','lastName'));
        }else{
            $performedBy = User::where('id',$data->performed_by)->first(array('firstName','lastName'));
        }
       }else{
            $performedBy = User::where('id',$data->performed_by)->first(array('firstName','lastName'));
       }
       $customer = Customer::join('customer_profile','customer_profile.customer_id','customer.id')
            ->where('customer_id',$data->customer_id)
            ->first(['customer.id','customer.firstName','customer.middleName','customer.lastName','customer_profile.omang','customer_profile.passport','customer_profile.dob']);
        if($data->passportIssuingCountry != null){
            $country = Country::where('id',$data->passportIssuingCountry)->first(array('name'));
            if($country && $country->name != null)
                $pic = $country->name;
            else
                $pic = '--';
        }else{
            $pic = '--';
        }

        if($data && $customer){
            $rekycLink = RekycLink::where('customer_id', $customer->id)->first();
            return view('admin.agentAppUploads.view', compact('data','rekycLink','customer','pic','performedBy'));
        }else {
            return Redirect::back()->with('error', 'Customer not found');
        }
      }else{
        return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
      }
    }


    public function verify($id){
      if (Auth::user()->hasPermissionTo('customer-kyc-edit'))
      {
        $data = KYC::where('id',$id)->first();
        $kycDomCom = CustomerKycDomCom::where('customer_kyc_id',  $id)->first();
        // MIS view — surface only the customer's MIS-tier policies.
        $custPolicyNo = Policy::join('products','products.id','policies.product_id')
            ->whereNotIn('policies.product_id', \AlphaDirect\Support\KycDomComProducts::IDS)
            ->where('customer_id',$data->customer_id)->orderBy('policies.id','desc')->get(array('policies.policyNumber', 'products.name','policies.product_id','policies.status'));
            $policydetails = Policy::where('customer_id',$data->customer_id)->first();
            if($policydetails){
             $activity = Audit::orderBy('created_at', 'desc')
             ->where('event','updated')
             ->where('auditable_type','AlphaDirect\KYC')
             //->where('policy_id',$policydetails->id)
             ->where('user_id','!=',null)
             ->where('policy_number',$policydetails->policyNumber)
             ->first(array('id','agent_id','user_id'));

             if($activity){
                 $performedBy = User::where('id',$activity->user_id)->first(array('firstName','lastName'));
             }else{
                 $performedBy = User::where('id',$data->performed_by)->first(array('firstName','lastName'));
             }
            }else{
                 $performedBy = User::where('id',$data->performed_by)->first(array('firstName','lastName'));
            }
        $customer = Customer::join('customer_profile','customer_profile.customer_id','customer.id')
            ->where('customer_id',$data->customer_id)
            ->first(['customer.id','customer.firstName','customer.middleName','customer.lastName','customer_profile.omang','customer_profile.passport','customer_profile.dob','customer_profile.entity_type','customer.company_id']);
        if($data->passportIssuingCountry != null){
            $country = Country::where('id',$data->passportIssuingCountry)->first(array('name'));
            if($country && $country->name != null)
                $pic = $country->name;
            else
                $pic = '--';
        }else{
                $pic = '--';
        }
        $activePolicy = Policy::where('customer_id',$data->customer_id)
        ->where('status',1)
        ->orWhere('status',0)->count();

        $AdiPolicy = Policy::where('customer_id',$data->customer_id)
        ->where('product_id',1)
        ->count();

        $LegalPolicy = Policy::where('customer_id',$data->customer_id)
        ->where('product_id',4)
        ->count();

        if($data && $customer) {
            $rekycLink = RekycLink::where('customer_id', $customer->id)->first();
            
            // Fetch customer banking data
            $customerBanking = CustomerBanking::where('customer_id', $customer->id)->first();
            
            // Populate bank and branch names if RealPay billing
            if($customerBanking != null && $customerBanking->billing == 'RealPay')
            {
                $customerBanking->bank_name   = \AlphaDirect\Banks::where('bank_number', $customerBanking->bankName)->value('bank_name');
                $customerBanking->bank_branch = \AlphaDirect\BankBranches::where('branch_id', $customerBanking->branchCode)->value('name');
            }
           
            // Check if any of the policies have product_id 7 or 8
            // $hasProduct7Or8 = $custPolicyNo->contains(function($policy) {
            //     return in_array($policy->product_id, [7, 8]);
            // });

            // $hasProduct7Or8 = $custPolicyNo->every(function ($policy) {
            //     return isset($policy->product_id) && in_array((int) $policy->product_id, [7, 8]);
            // });

            // if ($hasProduct7Or8 === true) {
            //     $domComCustname = 'N/A';
            //     if (!empty($customer) && $customer->entity_type=="Organisation") {
            //         $policyCompany = Company::where('id',$customer->company_id)->first();
            //         if (isset($policyCompany)) {
            //             $domComCustname = $policyCompany->name;
            //         } else {
            //             $domComCustname = $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName;
            //         }
            //     }else{
            //         if(!empty($customer)){
            //             $domComCustname = $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName;
            //         }
            //     }

            //     $groupedPolicies = $custPolicyNo->groupBy('product_id');

            //     return view('admin.agentAppUploads.viewDataComDom', compact('data','customer','pic','performedBy','custPolicyNo','activePolicy','AdiPolicy','LegalPolicy','domComCustname','groupedPolicies','kycDomCom'));
            // } else {
                return view('admin.agentAppUploads.viewData', compact('data','rekycLink','customer','pic','performedBy','custPolicyNo','activePolicy','AdiPolicy','LegalPolicy','customerBanking'));
            // }
        } else{
            return Redirect::back()->with('error', 'Customer not found');
        }
      }else{
        return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
      }
    }

    public function verifyDomCom($id){
        if (Auth::user()->hasPermissionTo('customer-kyc-edit'))
        {
            $data = KYC::where('id',$id)->first();
            $kycDomCom = CustomerKycDomCom::where('customer_kyc_id',  $id)->first();
            // DOM/COM view — surface only the customer's DOM/COM-tier policies.
            $custPolicyNo = Policy::join('products','products.id','policies.product_id')
              ->whereIn('policies.product_id', \AlphaDirect\Support\KycDomComProducts::IDS)
              ->where('customer_id',$data->customer_id)->orderBy('policies.id','desc')->get(array('policies.policyNumber', 'products.name','policies.product_id','policies.status'));
            $policydetails = Policy::where('customer_id',$data->customer_id)->first();
            if($policydetails){
               $activity = Audit::orderBy('created_at', 'desc')
               ->where('event','updated')
               ->where('auditable_type','AlphaDirect\KYC')
               //->where('policy_id',$policydetails->id)
               ->where('user_id','!=',null)
               ->where('policy_number',$policydetails->policyNumber)
               ->first(array('id','agent_id','user_id'));

                if($activity){
                   $performedBy = User::where('id',$activity->user_id)->first(array('firstName','lastName'));
                }else{
                    $performedBy = User::where('id',$data->performed_by)->first(array('firstName','lastName'));
                }
                }else{
                    $performedBy = User::where('id',$data->performed_by)->first(array('firstName','lastName'));
                }

                $customer = Customer::join('customer_profile','customer_profile.customer_id','customer.id')
                    ->where('customer_id',$data->customer_id)
                    ->first(['customer.id','customer.firstName','customer.middleName','customer.lastName','customer_profile.omang','customer_profile.passport','customer_profile.dob','customer_profile.entity_type','customer.company_id']);
                if($data->passportIssuingCountry != null){
                    $country = Country::where('id',$data->passportIssuingCountry)->first(array('name'));
                    if($country && $country->name != null)
                        $pic = $country->name;
                    else
                        $pic = '--';
                }else{
                        $pic = '--';
                }
                $activePolicy = Policy::where('customer_id',$data->customer_id)
                ->where('status',1)
                ->orWhere('status',0)->count();

                $AdiPolicy = Policy::where('customer_id',$data->customer_id)
                ->where('product_id',1)
                ->count();

                $LegalPolicy = Policy::where('customer_id',$data->customer_id)
                ->where('product_id',4)
                ->count();

                if($data && $customer) {
                // Check if any of the policies have product_id 7 or 8
                // $hasProduct7Or8 = $custPolicyNo->contains(function($policy) {
                //     return in_array($policy->product_id, [7, 8]);
                // });

                // $hasProduct7Or8 = $custPolicyNo->every(function ($policy) {
                //     return isset($policy->product_id) && in_array((int) $policy->product_id, [7, 8]);
                // });

                // if ($hasProduct7Or8 === true) {
                    $domComCustname = 'N/A';
                    if (!empty($customer) && $customer->entity_type=="Organisation") {
                        $policyCompany = Company::where('id',$customer->company_id)->first();
                        if (isset($policyCompany)) {
                            $domComCustname = $policyCompany->name;
                        } else {
                            $domComCustname = $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName;
                        }
                    }else{
                        if(!empty($customer)){
                            $domComCustname = $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName;
                        }
                    }

                    $groupedPolicies = $custPolicyNo->groupBy('product_id');

                    return view('admin.agentAppUploads.viewDataComDom', compact('data','customer','pic','performedBy','custPolicyNo','activePolicy','AdiPolicy','LegalPolicy','domComCustname','groupedPolicies','kycDomCom'));
                // }
            } else{
                return Redirect::back()->with('error', 'Customer not found');
            }
        }else{
          return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function archiveKYC($id){
        if (Auth::user()->hasPermissionTo('customer-kyc-archive'))
        {
        try{
            $kyc = KYC::where('id',$id)->first();
            $customerCount = KYC::where('customer_id',$kyc->customer_id)->count();
            if ($kyc != null) {
                if($customerCount > 1) {
                    $kyc_arr = $kyc->toArray();
                    $ar_kyc = new ArchivedKYC();
                    foreach ($kyc_arr as $key => $kycdata) {
                        $ar_kyc->$key = $kycdata;
                    }
                    $ar_kyc->save();

                    if ($ar_kyc->save())
                        $kyc->delete();

                    return Redirect::back()->with('success', 'Record archived successfully');
                }else{
                    return Redirect::back()->with('error', 'Can not archieve this record');
                }
            }else{
                return Redirect::back()->with('error', 'KYC record not found with id '.$id);
            }
        }catch (\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
      }else{
        return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
      }
    }

    public function updateCustomerKYCData($id,Request $request){
        if(isset($request->status) && isset($request->remark)){
            $data = AgentKyc::where('id',$id)->first();
            $data->status = $request->status;
            $data->remark = $request->remark;
            $data->save();
            if($data->save())
                return Redirect::back()->with('success', 'Information updated successfully');
        }
        else{
            return Redirect::back()->with('error', 'Code is not complete.You can not update at present');
        }

    }

    public function getKycDetails($user_id){
        try{
            if($user_id){
                $kycDetails = KYC::where('customer_id',$user_id)->first();
                if($kycDetails)
                    return  $kycDetails;
            }else{
                return null;
            }
        }catch(Exception $e){
                return null;
        }
    }

    public function getInspectionDetails($id){
        try{
            if($id){
                $vehicle = Vehicle::where('policy_id',$id)->first();
                if($vehicle)
                    return  $vehicle;
            }else{
                return null;
            }
        }catch(Exception $e){
            return null;
        }
    }

    public function customerKycActivityLog($id){
        $kyc = KYC::where('id',$id)->first('customer_id');
        $customer = Customer::where('id',$kyc->customer_id)->first();

        return view('admin.agentAppUploads.kyc_activity_log', compact('customer'));
    }


    public function getKycActivityLogData($id)
    {

        $data = KycActivityLogs::where('customer_id',$id)->orderBy('id','desc')->get();

        return DataTables::of($data)
                ->editColumn('activity_description', function ($data) {
                    $activity_description = '-';
                    if (isset($data->description)) {
                        $activity_description = $data->description;
                    }
                    return $activity_description;
                })

                ->editColumn('action_perfomed_by', function ($data) {
                    $performedBy = User::where('id',$data->action_perfomed_by)->first(array('firstName','lastName'));
                    $action_perfomed_by = '-';
                    if (isset($performedBy)) {
                        $action_perfomed_by = $performedBy['firstName']. ' ' .$performedBy['lastName'];
                    }
                    return $action_perfomed_by;
                })

                ->rawColumns(['activity_description','action_perfomed_by'])
                ->make(true);
    }


    public function customerKycActivityLogRecordes($id)
    {
        $kyc = KYC::where('id',$id)->first('customer_id');
        $customer = Customer::where('id',$kyc->customer_id)->first();

        return view('admin.agentAppUploads.kyc_activity_log_record', compact('customer'));
    }

    public function getcustomerKycActivityLogRecordes($id)
    {
         // $data = Audits::where('event','updated')->where('auditable_type','AlphaDirect\KYC')->orderBy('id', 'desc')->get(['id','old_values','new_values','user_id','agent_id','tags','updated_at']);

          $policydetails = Policy::where('customer_id',$id)->first();

          $activity = Audit::orderBy('created_at', 'desc')
          ->where('event','updated')
          ->where('auditable_type','AlphaDirect\KYC')
          //->where('policy_id',$policydetails->id)
          ->where('policy_number',$policydetails->policyNumber)
          ->get(array('id','agent_id','user_id','user_agent', 'auditable_id', 'old_values', 'new_values','tags','ip_address','created_at'))->take(15);

          return DataTables::of($activity)
              ->editColumn('user_id', function ($activity) {
                  if($activity->user_id != null){
                      $user = User::where('id', $activity->user_id)->first();
                      if($user && $user->firstName != null){
                        $userfirstName = $user->firstName;
                      }else{
                        $userfirstName = null;
                      }

                      if($user && $user->lastName != null){
                        $userlastName = $user->lastName;
                      }else{
                        $userlastName = null;
                      }

                      return $activity ? $userfirstName . ' ' . $userlastName : '-';
                  }
                  elseif($activity->agent_id != null){
                      $user = User::where('id', $activity->agent_id)->first();
                      if($user && $user->firstName != null){
                        $userfirstName = $user->firstName;
                      }else{
                        $userfirstName = null;
                      }

                      if($user && $user->lastName != null){
                        $userlastName = $user->lastName;
                      }else{
                        $userlastName = null;
                      }
                      return $activity ? '(Agent) '.' '.$userfirstName . ' ' . $userlastName : '-';
                  }
                  else{
                      return 'NA';
                  }
              })
              ->editColumn('old_values', function ($activity) {
                  if ($activity->old_values){
                    $data_1 = json_encode($activity->old_values);
                  return $json_string = json_encode(json_decode($data_1), JSON_PRETTY_PRINT);
                  }
                  else{
                      return 'NA';
                  }
              })
              ->editColumn('new_values', function ($activity) {
                  if ($activity->new_values){
                     $data = json_encode($activity->new_values);
                     return $json_string = json_encode(json_decode($data), JSON_PRETTY_PRINT);
                  }
                  else{
                      return 'NA';
                  }
              })
              ->editColumn('tag', function ($activity) {
                  if ($activity->tags){
                     $tag = $activity->tags;
                     return $tag;
                  }
                  else{
                      return 'NA';
                  }
              })
              ->editColumn('created_at', function ($activity) {
                return $activity->created_at->format('d-m-Y H:i');
              })
              ->rawColumns(['user_id','old_values','new_values','tag','created_at'])
              ->make(true);
     }
     public function VehiclePreinspectionActivityLogRecordes($id)
     {
         $vehicle = Vehicle::where('id',$id)->first();
        // $customer = Customer::where('id',$vehicle->customer_id)->first();

         return view('admin.agentAppUploads.vehiclePreinspection_activity_log_record', compact('vehicle'));
     }

     public function getVehiclePreinspectionActivityLogRecordes($id)
     {
          // $data = Audits::where('event','updated')->where('auditable_type','AlphaDirect\KYC')->orderBy('id', 'desc')->get(['id','old_values','new_values','user_id','agent_id','tags','updated_at']);
           $vehicle = Vehicle::where('id',$id)->first();
           //$policydetails = Policy::where('id',$vehicle->policy_id)->first();

           $activity = Audit::orderBy('created_at', 'desc')
           ->where('event','updated')
           ->where('auditable_type','AlphaDirect\Vehicle')
           ->where('policy_id',$vehicle->policy_id)
           //->where('policy_number',$policydetails->policyNumber)
           ->get(array('id','agent_id','user_id','user_agent', 'auditable_id', 'old_values', 'new_values','tags','ip_address','created_at'))->take(15);

           return DataTables::of($activity)
               ->editColumn('user_id', function ($activity) {
                   if($activity->user_id != null){
                       $user = User::where('id', $activity->user_id)->first();
                       if($user && $user->firstName != null){
                         $userfirstName = $user->firstName;
                       }else{
                         $userfirstName = null;
                       }

                       if($user && $user->lastName != null){
                         $userlastName = $user->lastName;
                       }else{
                         $userlastName = null;
                       }

                       return $activity ? $userfirstName . ' ' . $userlastName : '-';
                   }
                   elseif($activity->agent_id != null){
                       $user = User::where('id', $activity->agent_id)->first();
                       if($user && $user->firstName != null){
                         $userfirstName = $user->firstName;
                       }else{
                         $userfirstName = null;
                       }

                       if($user && $user->lastName != null){
                         $userlastName = $user->lastName;
                       }else{
                         $userlastName = null;
                       }
                       return $activity ? '(Agent) '.' '.$userfirstName . ' ' . $userlastName : '-';
                   }
                   else{
                       return 'NA';
                   }
               })
               ->editColumn('old_values', function ($activity) {
                   if ($activity->old_values){
                     $data_1 = json_encode($activity->old_values);
                   return $json_string = json_encode(json_decode($data_1), JSON_PRETTY_PRINT);
                   }
                   else{
                       return 'NA';
                   }
               })
               ->editColumn('new_values', function ($activity) {
                   if ($activity->new_values){
                      $data = json_encode($activity->new_values);
                      return $json_string = json_encode(json_decode($data), JSON_PRETTY_PRINT);
                   }
                   else{
                       return 'NA';
                   }
               })
               ->editColumn('tag', function ($activity) {
                   if ($activity->tags){
                      $tag = $activity->tags;
                      return $tag;
                   }
                   else{
                       return 'NA';
                   }
               })
               ->editColumn('created_at', function ($activity) {
                 return $activity->created_at->format('d-m-Y H:i');
               })
               ->rawColumns(['user_id','old_values','new_values','tag','created_at'])
               ->make(true);
      }

    public function customerKycDeleteRecordes($id)
    {
      $data = CustomerKycDelete::where('customer_id',$id)->first();
      if($data){
        return view('admin.agentAppUploads.kyc_delete_record', compact('data'));
      }else{
        return Redirect::back()->with('error', 'No Data Found');
      }
    }

    /**
     * Get sanctioned customers with their countries from datasets
     */
    public function getSanctionedCustomers()
    {
        try {
            // Get KYC cases with sanctions_max > 0 (indicating sanctions found)
            $sanctionedCases = KycCase::where('sanctions_max', '>', 0)
                ->with(['amlResults' => function($query) {
                    $query->orderBy('created_at', 'desc')->first();
                }])
                ->get();

            $sanctionedCustomers = collect();

            foreach ($sanctionedCases as $case) {
                // Get customer details
                $customer = Customer::find($case->customer_id);
                if (!$customer) continue;

                // Get the latest AML result for datasets and programId
                $latestAmlResult = $case->amlResults->first();
                $datasets = [];
                $programIds = [];
                $countries = [];
                $target = null;
                
                if ($latestAmlResult) {
                    // Extract datasets
                    if ($latestAmlResult->datasets) {
                        $datasets = $latestAmlResult->datasets;
                        // Show datasets directly as countries for now
                        $countries = $this->extractCountriesFromDatasets($datasets);
                    }
                    
                    // Extract programId
                    if ($latestAmlResult->programId) {
                        $programIds = $latestAmlResult->programId;
                    }
                    
                    // Extract target
                    $target = $latestAmlResult->target;
                }

                // Get KYC data ID for this customer
                $kycData = KYC::where('customer_id', $customer->id)->first();
                $kycId = $kycData ? $kycData->id : null;

                $sanctionedCustomers->push([
                    'customer_id' => $customer->id,
                    'kyc_id' => $kycId,
                    'customer_name' => trim($customer->firstName . ' ' . $customer->lastName),
                    'max_score' => $case->sanctions_max,
                    'status' => $case->status,
                    'datasets' => $datasets,
                    'countries' => $countries,
                    'programIds' => $programIds,
                    'target' => $target,
                    'checked_at' => $case->updated_at
                ]);
            }

            return view('admin.agentAppUploads.sanctioned_customers', compact('sanctionedCustomers'));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error fetching sanctioned customers: ' . $e->getMessage());
        }
    }

    /**
     * Extract country names from dataset names
     */
    private function extractCountriesFromDatasets($datasets)
    {
        $countries = [];
        
        foreach ($datasets as $dataset) {
            // Split dataset by underscore to get parts
            $parts = explode('_', $dataset);
            
            if (count($parts) >= 2) {
                $countryCode = strtolower($parts[0]);
                $agency = strtoupper($parts[1]);
                
                // Convert country code to proper name dynamically
                $countryName = $this->convertCountryCodeToName($countryCode);
                
                // Format as "Country (Agency)"
                $countries[] = $countryName . ' (' . $agency . ')';
            } else {
                // Fallback for single part datasets
                $formatted = str_replace('_', ' ', $dataset);
                $countries[] = ucwords($formatted);
            }
        }
        
        // Remove duplicates and return
        return array_unique($countries);
    }

    /**
     * Convert country code to proper name dynamically
     */
    private function convertCountryCodeToName($countryCode)
    {
        // Completely dynamic formatting - no hardcoding, no external APIs
        // Just format the country code intelligently regardless of what it is
        
        // Convert underscores to spaces and apply proper case
        $formatted = str_replace('_', ' ', $countryCode);
        $formatted = ucwords(strtolower($formatted));
        
        return $formatted;
    }

    /**
     * Run OpenSanctions check for a specific customer
     */
    public function runOpenSanctionsCheck($customerId)
    {
        try {
            // Find or create KYC case for the customer
            $customer = Customer::find($customerId);
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $kycCase = KycCase::firstOrCreate(
                ['customer_id' => $customerId],
                [
                    'customer_id' => $customerId,
                    'status' => 'pending',
                    'sanctions_max' => 0,
                    'sanctions_count' => 0
                ]
            );

            // Run the OpenSanctions check synchronously
            $job = new RunOpenSanctionsJob($kycCase->id);
            $job->handle(app(\AlphaDirect\Services\OpenSanctionsClient::class));

            // Refresh the KYC case to get updated data
            $kycCase->refresh();

            // Get the latest AML result for datasets count
            $latestAmlResult = $kycCase->amlResults()->latest()->first();
            $datasetsCount = 0;
            if ($latestAmlResult && $latestAmlResult->datasets) {
                $datasetsCount = count($latestAmlResult->datasets);
            }

            return response()->json([
                'success' => true,
                'message' => 'OpenSanctions check completed successfully!',
                'max_score' => $kycCase->sanctions_max ?? 0,
                'status' => $kycCase->status ?? 'unknown',
                'datasets_count' => $datasetsCount,
                'kyc_case_id' => $kycCase->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error running OpenSanctions check: ' . $e->getMessage()
            ], 500);
        }
    }
}


