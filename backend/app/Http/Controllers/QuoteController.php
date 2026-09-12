<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Banks;
use AlphaDirect\BlackListIp;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Exports\QuotesExport;
use AlphaDirect\KYC;
use AlphaDirect\Lookup;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\ReratedPremiumQuote;
use AlphaDirect\Stores;
use AlphaDirect\User;
use AlphaDirect\VehicleMake;
use Auth;
use Carbon\Carbon;
use DB;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Response;
use DataTables;
use Redirect;


class QuoteController extends Controller
{
    public function index()
    {
        $agents = User::where('active', 1)->get();
        return view('admin.policy.quotes.index',compact('agents'));

    }

    public function data(Request $request)
    {
        try {
            // $search_arr = $request->get('search');
            // $searchValue = trim($search_arr['value']); // Search value

            // // Total records
            // $totalRecords = Quote::select('count(*) as allcount')->count();
            // // Fetch records
            // $records = Quote::orderBy('id', 'DESC')
            //     ->leftJoin('customer', 'customer.id', 'quotes.customerId')

            // if ($searchValue != null) {
            //     $records->where('customer.firstName', 'like', '%' . $searchValue . '%')
            //         ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'), 'like', '%' . $searchValue . '%')
            //         ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.lastName)'), 'like', '%' . $searchValue . '%');
            // }

            $quotes = Quote::with('customerQuote','createdByAgent','policy');
            if(Auth::user()->hasPermissionTo('quote-Full List') || (Auth::user()->hasRole('Manager') || Auth::user()->hasRole('Super Admin')))
                $quotes = Quote::with('customerQuote','createdByAgent','policy');
            else
                $quotes->where('agentId', null)->orWhere('agentId',auth()->user()->id);
                if(isset($request->agent_filter) && $request->agent_filter != '-1'){
                    $quotes->where('agentId',$request->agent_filter);
                }
            return DataTables::eloquent($quotes)

                ->editColumn('created_at', function ($quotes) {
                    return $quotes->created_at->diffForHumans();
                })

                ->editColumn('customerId', function ($quotes) {
                    # code...
                    if(isset($quotes->customerId)){
                        return $quotes->customerId;
                    }else{
                        return '-' ;
                    }
                })
                //quoteNumber
                ->editColumn('policyNumber', function ($quotes) {
                   // dd($quotes->policy['policyNumber']);
                    # code...
                        if (isset($quotes->policy['policyNumber'])) {
                            return $quotes->policy['policyNumber'];
                        } else {
                            return '-';
                        }
                })

                // ->addColumn('policyNumber', function ($quotes) {
                //     if (isset($quotes->quoteCode)) {
                //        $policy = Policy::where('quoteNumber',$quotes->quoteCode)->first(array('policyNumber'));
                //        if($policy){
                //            return $policy->policyNumber;
                //        }else{
                //            return '--';
                //        }

                //     } else {
                //         return '--';
                //     }

                // })
                ->addColumn('fname', function ($quotes) {
                        if (isset($quotes->customerQuote)) {
                            return ucwords($quotes->customerQuote->firstName). ' ' . ucwords($quotes->customerQuote->middleName) . ' ' . ucwords($quotes->customerQuote->lastName);
                        } else {
                            return null;
                        }
                })
                ->addColumn('lname', function ($quotes) {
                    if (isset($quotes->customerQuote)) {
                        return ucwords($quotes->customerQuote->firstName). ' ' . ucwords($quotes->customerQuote->middleName) . ' ' . ucwords($quotes->customerQuote->lastName);
                    } else {
                        return null;
                    }
            })
                ->editColumn('agentId', function ($quotes) {
                    if (isset($quotes->createdByAgent)) {
                        return ucwords($quotes->createdByAgent->firstName) . ' ' . ucwords($quotes->createdByAgent->lastName);
                    } else {
                        return null;
                    }

                })
                ->editColumn('userIPAddress', function ($quotes) {
                    if($quotes->userIPAddress != null){
                        $blocked = BlackListIp::where('ip',$quotes->userIPAddress)
                            ->first(array('status'));
                        if($blocked && $blocked->status == 1)
                            return '<span class="kt-font-bold kt-font-danger">'. $quotes->userIPAddress .'</span>';
                        else
                            return '<span class="kt-font-bold kt-font-success">'. $quotes->userIPAddress .'</span>';
                    }
                    else {
                        return '-';
                    }

                })
                ->editColumn('premiumRate', function ($quotes) {
                    $data = MotorComprehensiveQuotes::where('quoteNumber',$quotes->quoteCode)->first(array('premiumAnnually','estimatedValue'));
                    if($data != null && $data->premiumAnnually && $data->estimatedValue){
                        $rate = ($data->premiumAnnually/$data->estimatedValue)*100;
                        if($rate > 0 && $rate < 100){
                            return number_format((float) $rate, 2, '.', '').'%';
                        }else{
                            return '-';
                        }

                    }
                    else {
                        return '-';
                    }

                })
//                ->editColumn('type', function ($quotes) {
//                    $data = MotorComprehensiveQuotes::where('quoteNumber',$quotes->quoteCode)->first(array('premiumAnnually','estimatedValue','type'));
//
//                    if($data->type != null) {
//                        $re = $data->type;
//                    }
//                    else {
//                        $re = 'New';
//                    }
//
//                    return $re;
//                })
                ->editColumn('productId', function ($quotes) {
                    $productName = Product::where('id', $quotes->productId)->first(array('name'));
                    return $productName ? $productName->name : '';

                })
                ->addColumn('actions', function ($quotes) {
                    $status = MotorComprehensiveQuotes::where('quoteNumber',$quotes->quoteCode)->first(array('status'));
                    $actions = '
                                <a href="" value="' . $quotes->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                    <i class="la la-trash"></i>
                                </a>';
                    $actions .= '<a href="' . route('quote.edit', $quotes->id) . '"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-eye"></i>
                            </a>';
//                    if($status->status == 1 && auth::user()->hasPermissionTo('quotes-Update Premium')) {
                        $actions .= '<a href="' . route('quote.rerate', $quotes->id) . '"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
//                    }

                    return $actions;
                })//Update Premium

                ->addColumn('status',function($quotes){
                    $data = MotorComprehensiveQuotes::where('quoteNumber',$quotes->quoteCode)->first(array('status'));

                    if($data && $data->status != null){
                            if($data->status == 1)
                                return '<span class="kt-font-bold kt-font-success">Active</span>';
                            elseif($data->status == 2)
                                return '<span class="kt-font-bold kt-font-brand">Used</span>';
                            elseif($data->status == 3 || $data->status == 0)
                                return '<span class="kt-font-bold kt-font-danger">Expired</span>';
                            elseif($data->status == 4)
                                return '<span class="kt-font-bold kt-font-danger">Rejected</span>';
                            else
                                return 'N/A';

                    }else{
                        return 'N/A';
                    }
                })
                ->addColumn('agentID',function($quotes){
                    if($quotes->quoteCode != null){
                        $q = MotorComprehensiveQuotes::where('quoteNumber',$quotes->quoteCode)->first(array('agentID'));
                        if($q && $q->agentID != null){
                            $user = User::where('id',$q->agentID)->first(array('firstName','lastName'));
                            if($user != null){
                                return '<span class="kt-font-bold kt-font-brand">'.ucwords($user->firstName).' '.ucwords($user->lastName).'</span>';
                            }else{
                                return '<span class="kt-font-bold kt-font-danger">User details not found</span>';
                            }
                        }else{
                            return '-';
                        }
                    }else{
                        return '<span class="kt-font-bold kt-font-danger">Quote details not found</span>';
                    }
                })
                // ->rawColumns(['actions','policyNumber','names','status','agentID','userIPAddress','premiumRate'])
                ->rawColumns(['actions','names','status','agentID','userIPAddress','premiumRate'])
                ->make(true);

        } catch (Exception $ex) {
            return response()->json_encode(['error' => $ex->getMessage()]);
        }

    }

    public function edit($id)
    {
        try{
            $data = Quote::join('motor_comp_quotes','motor_comp_quotes.quoteNumber','quotes.quoteCode')
                ->join('customer','customer.id','quotes.customerId')
                ->join('customer_profile','customer_profile.customer_id','quotes.customerId')
                ->join('products','products.id','quotes.productId')
                ->where('quotes.id',$id)
                ->orderBy('quotes.id','desc')
                ->first();

            $quote = Quote::join('motor_comp_quotes','motor_comp_quotes.quoteNumber','quotes.quoteCode')
                ->where('quotes.id',$id)
                ->first(array('motor_comp_quotes.status','motor_comp_quotes.created_at'));

            $dataStatus = MotorComprehensiveQuotes::where('quoteNumber',$data->quoteCode)->first(array('status','agentID','premium_rate'));

            $plan = Productplan::where('id',8)->first();

            $date = $quote->created_at->format('Y-m-d');

            if($data->premium_rate != null){
                $ratio = $data->premium_rate;
            }else{
                if($data->estimatedValue != 0)
                    $ratio = ($data->premiumAnnually/$data->estimatedValue)*100;
                else
                    $ratio = '-';
            }

            $data['created_at'] = $date;
            $data['plan_name'] = $plan->name;
            $data['ratio'] = $ratio;
            $data['quote_status'] = $dataStatus->status;

            $agent = User::where('id',$dataStatus->agentID)->first(array('firstName','lastName'));
            $setting = QuoteSettings::first();
            $days = (int)(($setting->DaysToExpireQuote ?? 0) ?: 30);
            $start = new \Carbon\Carbon($data->created_at);
            $expiryDate = $start->addDays($days)->format('d-m-Y');
            $policyNumber = Policy::where('quoteNumber',$data->quoteCode)->first(array('policyNumber'));

            $store = Stores::where('id',$data->storeID)->first();
            if($store && $store->name){
                $storeName = $store->name;
            }else{
                $storeName = null;
            }

            $discount_surcharge = 0;
            $orig_rat = 0;

            $d = ReratedPremiumQuote::where('rate_id',$data->ratings_id)->orderBy('id','desc')->get(array('discount_surcharge','reason'));
            $sum = $d->sum('discount_surcharge');
            $rate = null;
            $reason = null;
            if(count($d) > 0){
                $rate = ReratedPremiumQuote::where('rate_id',$data->ratings_id)->first();
                $fetchreason = ReratedPremiumQuote::where('rate_id',$data->ratings_id)->orderBy('id','DESC')->first();
                $reason = $fetchreason->reason;
                if($data->estimatedValue)
                    $orig_rate = ($rate->old_value/$data->estimatedValue)*100;
            }else{
                $orig_rate = $dataStatus->premium_rate;
                $data['ratio'] = 0;
            }
            $orig_rate = number_format((float)$orig_rate, 2, '.', '');


            return view('admin/policy/quotes/view', compact('policyNumber','data','agent','expiryDate','storeName','sum','orig_rate','rate','reason'));
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
    public function rerate($id)
    {
        try{
            $data = Quote::join('motor_comp_quotes','motor_comp_quotes.quoteNumber','quotes.quoteCode')
                ->join('customer','customer.id','quotes.customerId')
                ->join('customer_profile','customer_profile.customer_id','quotes.customerId')
                ->join('products','products.id','quotes.productId')
                ->where('quotes.id',$id)
                ->orderBy('quotes.id','desc')
                ->first();
            $quote = Quote::join('motor_comp_quotes','motor_comp_quotes.quoteNumber','quotes.quoteCode')
                ->where('quotes.id',$id)
                ->first(array('motor_comp_quotes.status','motor_comp_quotes.created_at'));

            $dataStatus = MotorComprehensiveQuotes::where('quoteNumber',$data->quoteCode)->first(array('status','agentID','premium_rate','variant'));

            $plan = Productplan::where('product_id',3)->first(); // please change this after adding more plans on motor comprehensive

            $date = $quote->created_at->format('Y-m-d');

            if($data->premium_rate != null){
                $ratio = $data->premium_rate;
            }else{
                if($data->estimatedValue != 0)
                    $ratio = ($data->premiumAnnually/$data->estimatedValue)*100;
                else
                    $ratio = '-';
            }

            $data['created_at']   = $date;
            $data['plan_name']    = $plan->name;
            $data['ratio']        = $ratio;
            $data['quote_status'] = $dataStatus->status;
            $data['variant'] = $dataStatus->variant;

            $agent        = User::where('id',$dataStatus->agentID)->first(array('firstName','lastName','bypass_500k'));
            $setting      = QuoteSettings::first();
            $days         = (int)(($setting->DaysToExpireQuote ?? 0) ?: 30);
            $start        = new \Carbon\Carbon($data->created_at);
            $expiryDate   = $start->addDays($days)->format('d-m-Y');
            $policyNumber = Policy::where('quoteNumber',$data->quoteCode)->first(array('policyNumber'));

            $store = Stores::where('id',$data->storeID)->first();
            if($store && $store->name){
                $storeName = $store->name;
            }else{
                $storeName = null;
            }

            $discount_surcharge = 0;
            $orig_rat = 0;

            $d    = ReratedPremiumQuote::where('rate_id',$data->ratings_id)->orderBy('id','desc')->get(array('discount_surcharge','reason'));
            $sum  = $d->sum('discount_surcharge');
            $rate = null;
            if(count($d) > 0){
                $rate        = ReratedPremiumQuote::where('rate_id',$data->ratings_id)->first();
                $fetchreason = ReratedPremiumQuote::where('rate_id',$data->ratings_id)->orderBy('id','DESC')->first();
                $reason      = $fetchreason->reason;
                if($data->estimatedValue)
                    $orig_rate = ($rate->old_value/$data->estimatedValue)*100;
            }else{
                $orig_rate = $dataStatus->premium_rate;
                $data['ratio'] = 0;
                $reason = null;
            }
            $orig_rate = number_format((float)$orig_rate, 2, '.', '');

            $dataMake = $this->getVehicleMakes($data->is_imported);
            $dataModel = $this->getVehicleModels($data->make,$data->is_imported,$data->manufacturingYear);
//dd($dataModel);
            return view('admin/policy/quotes/rerate', compact('policyNumber','data','agent','expiryDate','storeName','sum','orig_rate','rate','reason','dataMake','dataModel'));
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getVehicleMakes($status)
    {
        try{
            if($status == 'No'){
               

                $API_CRED = base64_encode('6594a578-1088-4794-a6ba-450b0f4ce5f6' . ':' . 'fa1808e3-360c-4c09-9f71-506d9a207ae8');
                $ch = curl_init();
    
                curl_setopt($ch, CURLOPT_URL, "https://api.yourvehiclevalue.co.za/api/lookup/make");
    
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $headers = array();
                $headers[] = "Authorization: Basic " . $API_CRED;
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $response = curl_exec($ch);
    
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
                curl_close($ch);
                
                $data = json_decode($response, true);
                if ($httpCode == 200) {
                    return $data;
                }else{
                    return []; 
                }
            }elseif($status == 'Yes'){
                $Makes = VehicleMake::groupBy('s_Make')
                    ->get(['s_Make']);
                return $Makes;
            }
            else{
                return array();
                //$exp = new \Exception();
                //return Redirect::back()->with('error','Import status not found'.' '.$exp->getFile().' '.$exp->getLine());
            }
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getVehicleModels($make,$status,$year)
    {
        try{
            if($status == 'No'){
                $make = str_replace(' ', '+', trim($make, " "));

                if ($make) {
                    $API_CRED = base64_encode('6594a578-1088-4794-a6ba-450b0f4ce5f6' . ':' . 'fa1808e3-360c-4c09-9f71-506d9a207ae8');
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL,  "https://api.yourvehiclevalue.co.za/api/lookup/model/" . $make);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $headers = array();
                    $headers[] = "Authorization: Basic " . $API_CRED;
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $response = curl_exec($ch);
                
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
                    curl_close($ch);
                    
                    $data = json_decode($response, true);

                    if ($httpCode == 200) {
                        $Models = [];
                        
                            foreach($data as $m){
                                $Models[] = [
                                    'Model' => $m,
                                    'IntroYear' => null,
                                    'DisconYear' => null
                                ];
        
                            }
                        return $Models;
                    } else {
                        return Redirect::back()->with('error','Vehicle model transaction failed');
                    }
                } else {
                    return Redirect::back()->with('error','Import status not found');
                }
            }elseif($status == 'Yes'){
                $models = VehicleMake::where('s_Make',$make)->where('s_Variant', '<>', '')->where('s_Variant', '<>', null)->get(['s_Variant as model']);
                return $models;
            }else{
                return Redirect::back()->with('error','Import status not found');
            }
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
    public function getTTVehicleYear($make,$model)
    {
        $make = trim($make);               
        $model = trim($model);     
        
        $encodedMake = rawurlencode($make);   
        $encodedModel = rawurlencode($model);  
        
        $url = "https://api.yourvehiclevalue.co.za/api/lookup/year/{$encodedMake}/{$encodedModel}";
        $apiCred = base64_encode('6594a578-1088-4794-a6ba-450b0f4ce5f6:fa1808e3-360c-4c09-9f71-506d9a207ae8');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic {$apiCred}"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $data = json_decode($response, true);
    
            if ($httpCode == 200) {
                return $data;

            } elseif ($httpCode == 0) {
                return Redirect::back()->with('error','Import status not found');
            
            } else {
                return Redirect::back()->with('error','Import status not found');
            }
        
    
        return response()->json(['success' => false, 'message' => 'Vehicle make and model are required'], 422);
    }
    
    public function getTTVehicleVariant($make,$model,$year)
    {

        try {
            $make = trim($make);               
            $model = trim($model);     
            $encodedMake = rawurlencode($make);   
            $encodedModel = rawurlencode($model);  
           
            if ($make) {
                $url = "https://api.yourvehiclevalue.co.za/api/lookup/variant/{$encodedMake}/{$encodedModel}/{$year}";
                $API_CRED = base64_encode('6594a578-1088-4794-a6ba-450b0f4ce5f6' . ':' . 'fa1808e3-360c-4c09-9f71-506d9a207ae8');
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL,  $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $headers = array();
                $headers[] = "Authorization: Basic " . $API_CRED;
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $response = curl_exec($ch);
            
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
                curl_close($ch);
                
                $data = json_decode($response, true);
              
               
                
                   
                if ($httpCode == 200) {
                    return $data;
                } else {
                    return Redirect::back()->with('error','Import status not found');
                }
               
            } else {
                return Redirect::back()->with('error','Import status not found');
            }
        } catch (\Exception $e) {
            return Redirect::back()->with('error','Import status not found');
        }
    }

    public function store(Request $request)
    {
        try {

            $user = new Customer();
            $user->firstName = ucfirst($request->firstName);
            $user->lastName = ucfirst($request->lastName);
            $user->email = $request->email;
            $user->save();
            if ($user != null) {
                $ifExists = CustomerProfile::where('customer_id',$user->id)->exists();
                if(!empty($ifExists))
                {
                    $profile = CustomerProfile::where('customer_id',$user->id)->first();
                }else{
                    /*Add Records to Customers Profile table*/
                    $profile = new CustomerProfile();
                }
                $profile->customer_id = $user->id;
                $profile->gender = $request->get('gender');
                $profile->address = $request->get('address');
                $profile->omang = $request->get('omang');
                $profile->passport = $request->get('passport');
                $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
                $profile->save();

                $kyc = new KYC();
                $kyc->customer_id = $user->id;
                $kyc->compliance = 0;
                $kyc->save();
                $product = Product::where('id', $request->get('product'))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
                $productPlans = Productplan::where('product_id', $request->get('product'))->first(array('id', 'name', 'slug', 'premium'));
                $qoute = new Quote();
                $qoute->customerId = $user->id;
                $qoute->quoteCode = 'QZ' . Carbon::now()->year . str_pad(($qoute->id + 1), 6, '0', STR_PAD_LEFT);
                $qoute->productId = $request->product;
                $qoute->planId = $request->plan;
                $qoute->status = 0;
                $qoute->agentId = Auth::id();
                $qoute->has_vehicle = $product->has_vehicle;
                $qoute->has_member = $product->has_member;
                $qoute->preinspection = $product->preinspection;
                $qoute->is_motor_items = $product->is_motor_items;
                $qoute->quote_limit = $product->limit;
                $qoute->kyc_customer = $product->kyc_customer;
                $qoute->kyc_recipient = $product->kyc_recipient;

                if ($qoute->save()) {
                    return Response::json(['message' => 'Quote Saved','productPlans'=>productPlans], 200);
                } else {
                    return Response::json(['message' => 'Failed to create quote'], 400);
                }

            }

        } catch (Exception $ex) {

            return Response::json(['message' => 'Something went wrong,please try again'], 500);
        }

    }

    public function getModalDelete(Request $request)
    {
        $body = "Are you sure you want to delete the Customer Quote ? ";
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);

    }

    public function getUpdatedPremium(Request $request){
       
        try{
        // if (isset($data['variant'])) {
        //     unset($data['variant']);
        // }
        // if (isset($data['rate_value_type'])) {
        //     unset($data['rate_value_type']);
        // }
        // if (isset($data['value'])) {
        //     unset($data['value']);
        // }
        // if (isset($data['reason'])) {
        //     unset($data['reason']);
        // }
        // if (isset($data['type'])) {
        //     unset($data['type']);
        // }
        // if (isset($data['value_type'])) {
        //     unset($data['value_type']);
        // }
            $array = array('type','value_type','value','reason','middle_name','model_other','email');

            if(isset($request->passport)){
                array_push($array,"omang");
            }
            if(isset($request->omang)){
                array_push($array,"passport");
            }


            foreach($request->except($array) as $key=>$data){
                if($key == 'prior_accidents'){
                    if($data > 3)
                        return Redirect::back()->with('error','Claim count should be in between 0 to 3');
                }

                if($key == 'estimatedValue'){
                    if($data > 499999)
                        return Redirect::back()->with('error','Estimated value should be P20,000 and above and below P500,000 ');
                }

                // if($data == null){
                //     return Redirect::back()->with('error',ucfirst($key).' is required');
                // }
            }
            $make           = $request->get('make');
            $year           = $request->get('year');
            $model          = $request->get('model');
            if(!empty($request->get('dob'))){
                $dob            = date("d/m/Y", strtotime($request->get('dob')));
            }else{
                return Redirect::back()->with('success','Please provide date of birth');
            }
            $sum_insured    = $request->get('estimatedValue');
            $status         = $request->get('is_imported');
            $marital_status = $request->get('marital');
            $claim_count    = $request->get('prior_accidents');
            $gender         = $request->get('gender');
            $omang          = $request->get('omang');
            $passport       = $request->get('passport');

            if($marital_status == 1) {
                $updated_marital_status = "Never Married";
            }elseif($marital_status == 2){
                $updated_marital_status = "Married Before";
            }elseif($marital_status == 3){
                $updated_marital_status = "Married Before";
            }elseif($marital_status == 4){
                $updated_marital_status = "Married Before";
            }elseif($marital_status == 5){
                $updated_marital_status = "Never Married";
            }elseif($marital_status == 6){
                $updated_marital_status = "Married Before";
            }else{
                return Redirect::back()->with('error','Maritial status not found');
            }

            if($gender == 1) {
                $updated_gender = "Male";
            }elseif($gender == 0){
                $updated_gender = "Female";
            }else{
                return Redirect::back()->with('error','Please provide gender');
            }

            // dd($make,$model,$year,$dob,$sum_insured,$status,$updated_marital_status,$claim_count,$updated_gender,$omang,$passport);

            /*Hardcoded URL because ENV variables (env('RATINGS_URL')) is not working*/

            if(env('APP_STATUS') == 'Production')
                $rating_url = 'https://rate.alphadirect.co.bw/api/';
            else
                $rating_url = 'https://rate.alphadirect.co.bw/api/';

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $rating_url.'calculation',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>'{
                "make":"'.$make.'",
                "manufacturing_year":"'.$year.'",
                "dob":"'.$dob.'",
                "sum_insured":"'.$sum_insured.'",
                "status":"'.$status.'",
                "marital_status":"'.$updated_marital_status.'",
                "claim_count":"'.$claim_count.'",
                "gender":"'.$updated_gender.'"
                }',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Cookie: __cfduid=d52d1be0186f72c5ab914ad142d22ba941620644343'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $data = json_decode($response,true);

            if($data['success'] == 1){
                $update = MotorComprehensiveQuotes::where('quoteNumber',$request->quoteCode)->orderBy('id','desc')->first();
                if($update != null){
                    $update->ratings_id = $data['rate_id'];
                    $update->premiumMonthly = $data['monthly_premium_vat'];
                    $update->premium3Inst = $data['threemonthly_preminum_vat'];
                    $update->premiumAnnually = $data['result'];
                    $update->premium_rate = ($data['result']/$sum_insured)*100;
                    $update->make = $make;
                    if($request->get('is_imported')=="Yes"){
                        if($model=='other'){
                            $update->model = strtoupper($request->get('model_other'));
                            if(isset($update->model)){
                                $modelExists = VehicleMake::where('s_Make',$make)->where('s_Variant',$update->model)->where('is_imported',$status)->exists();
                                if($modelExists){
                                    return Redirect::back()->with('error','This other model is already exists');
                                }else{
                                    $models = new VehicleMake;
                                    $models->s_Make = $make;
                                    $models->s_Variant = $update->model;
                                    $models->is_imported = $status;
                                    $models->save();
                                }
                            }
                            $update->other_model = 1;
                        }else{
                            $update->model = $model;
                            $update->other_model = 0;
                        }
                    }else{
                        if($model=='other'){
                            $update->model =  strtoupper($request->get('model_other'));
                            $update->other_model = 1;
                        }else{
                            $update->model = $model;
                            $update->other_model = 0;
                        }
                    }
                    $update->manufacturingYear = $year;
                    $update->estimatedValue = $sum_insured;
                    $update->priorAccidents = $claim_count;
                    $update->is_imported = $status;
                    $update->discount_surcharge = null;
                    $update->percent_discount_surcharge = null;
                    if(isset($request->variant)){
                        $update->variant = $request->variant;
                    }
                    $update->save();

                    $customerProfile = CustomerProfile::where('customer_id',$update->customer_id)->first();
                    $customerProfile->dob = date("Y-m-d", strtotime($request->get('dob')));
                    $customerProfile->maritalstatus = $marital_status;
                    $customerProfile->gender = $gender;
                    $customerProfile->omang = $omang;
                    $customerProfile->passport = $passport;
                    $customerProfile->save();

                    $customer_data = Customer::where('id',$update->customer_id)->first();
                    $customer_data->firstName  =  $request->get('first_name');
                    $customer_data->middleName =  $request->get('middle_name');
                    $customer_data->lastName   =  $request->get('last_name');
                    $customer_data->email      =  $request->get('email');
                    $customer_data->cellphone  =  $request->get('mobile');
                    $customer_data->save();

                    $add = new ReratedPremiumQuote();
                    $add->quote_number = $request->quoteCode;
                    $add->rate_id = $data['rate_id'];
                    $add->old_value = $request->annual_premium;
                    $add->new_value = $data['result'];
                    $add->discount_surcharge = ($request->discountSurchargePerAdded) ? $request->discountSurchargePerAdded : null;
                    $add->reason = 'Premium Rerating';
                    $add->added_by = auth()->user()->id;
                    $add->ip = $request->ip();
                    $add->save();

                    return Redirect::back()->with('success','Quote updated successfully');
                }else{
                    return Redirect::back()->with('error','Quote not found with quote number: '.$request->quoteCode);
                }
            }else{
                return Redirect::back()->with('error','Problem fetch ratings response');
            }
        }catch(\Exception $ex){
            return Redirect::back()->with('error',$ex->getMessage().'-'.$ex->getLine());
        }
    }

    public function destroy($id)
    {
        try {
            $qoute = Quote::findorFail($id);
            $qoute->delete();
            return redirect()->back()->with('message', 'Quote Successfuly Deleted');

        } catch (Exception $ex) {
            return Response::json(['errormsg' => 'something went wrong, try again'], 500);
        }
    }

    public function export(Request $request)
    {
        return Excel::Download(new QuotesExport(), 'QuotesExport.xlsx');

    }
}
