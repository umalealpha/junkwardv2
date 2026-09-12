<?php

namespace AlphaDirect\Http\Controllers\frontendPay;

use AlphaDirect\AccidentInjury;
use AlphaDirect\BankBranches;
use AlphaDirect\Beneficiary;
use DateTime;
use AlphaDirect\ClaimAssessment;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ClaimKeyLoss;
use AlphaDirect\ClaimQuoteDetail;
use AlphaDirect\ClaimQuote;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\Country;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Lookup;
use AlphaDirect\QuoteSettings;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RepairCenter;
use AlphaDirect\Supplier;
use AlphaDirect\City;
use AlphaDirect\State;
use AlphaDirect\User;
use AlphaDirect\VehiclePopularity;
use Illuminate\Http\Request;
use AlphaDirect\AccidentDriver;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimAccidentPassenger;
use AlphaDirect\ClaimLife;
use AlphaDirect\ClaimThirdParty;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\Product;
use AlphaDirect\RecipientKyc;
use AlphaDirect\Vehicle;
use AlphaDirect\VehicleMake;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Customer;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use DB;
use AlphaDirect\AgentKyc;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\ClaimLegal;
use AlphaDirect\UserPassword;
use stdClass;
use Illuminate\Support\Str;

class ClaimController extends Controller
{

    public function register(Request $request)
    {
        if (filter_var(htmlspecialchars(strip_tags($request->register_email)), FILTER_VALIDATE_EMAIL) || htmlspecialchars(strip_tags($request->register_password)) != null) {
            if (htmlspecialchars(strip_tags($request->register_password)) == htmlspecialchars(strip_tags($request->register_cpassword))) {
                $check = Customer::where('email', htmlspecialchars(strip_tags($request->register_email)))->first();
                if ($check) {
                    return response()->json([
                        'error' => true, 'message' => "Email already exist."
                    ], 401);
                } else {
                    $customer = new Customer();
                    $customer->email = htmlspecialchars(strip_tags($request->register_email));
                    $customer->password = Hash::make(htmlspecialchars(strip_tags($request->register_password)));
                    $customer->save();
                    return response()->json([
                        'success' => true,
                        'user_id' => $customer->id
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Password and confirm password do not match',
                ], 401);
            }
        } else {
            return response()->json(['message' => 'Please provide valid email and password'], 401);
        }
    }
    public function login(Request $request)
    {
        if (filter_var(htmlspecialchars(strip_tags($request->login_email)), FILTER_VALIDATE_EMAIL) && htmlspecialchars(strip_tags($request->login_email)) != null) {
            $customer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                ->where('customer.email', $request->login_email)
                ->first();
            if ($customer) {
                if ($customer->email == $request->login_email) {
                    if (Hash::check($request->login_password, $customer->password)) {
                        $foundCustomer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                            ->leftJoin('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
                            ->leftJoin('policies', 'policies.customer_id', '=', 'customer.id')
                            ->where('customer.email', $customer->email)
                            ->first(['customer.id', 'customer.firstName', 'customer.lastName','customer.email','customer.f_login','customer_profile.omang', 'customer_profile.passport','customer_profile.state', 'customer_kyc.compliance as KYC_compliance','policies.policyNumber','policies.product_id','policies.id as policy_id']);

                        return response()->json([
                            'success' => true, 'message' => "User Found", 'customer' => $foundCustomer
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false, 'message' => "Invalid password."
                        ], 401);
                    }
                } else {
                    return response()->json([
                        'success' => false, 'message' => "Invalid Email."
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false, 'message' => "Invalid email/password."
                ], 401);
            }
        } elseif (is_numeric($request->login_email) && $request->login_email != null) {
            $customer = Customer::leftJoin('customer_profile', 'customer.id', '=', 'customer_profile.customer_id')
                ->where('customer.cellphone', $request->login_email)
                ->get();
            foreach ($customer as $c) {
                if ($c->password != null && $c->id) {
                    if (password_verify($request->login_password, $c->password) == true) {
                        $foundCustomer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                            ->leftJoin('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
                            ->leftJoin('policies', 'policies.customer_id', '=', 'customer.id')
                            ->where('customer.id', $c->customer_id)
                            ->orderBy('id', 'DESC')
                            ->first(['customer.id', 'customer.firstName', 'customer.lastName','customer.f_login','customer.email', 'customer_profile.omang','customer_profile.passport','customer_profile.state', 'customer_kyc.compliance as KYC_compliance','policies.policyNumber','policies.product_id','policies.id as policy_id']);
                        return response()->json([
                            'success' => true, 'message' => "User Found", 'customer' => $foundCustomer
                        ], 200);
                    }
                }
            }
            return response()->json([
                'success' => false, 'message' => "Invalid  Contact no./password."
            ], 401);
            //            if($customer){
            //                if($customer->cellphone == $request->login_email){
            //                    if (Hash::check($request->login_password , $customer->password)){
            //                        $foundCustomer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
            //                            ->leftJoin('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
            //                            ->where('customer.id',$customer->id)
            //                            ->first(['customer.id','customer.firstName','customer.lastName','customer.email','customer_profile.omang','customer_profile.passport','customer_kyc.compliance as KYC_compliance']);
            //                        return response()->json([
            //                            'success'=>true,'message'=>"User Found",'customer'=>$foundCustomer
            //                        ],200);
            //                    }  else{
            //                        return response()->json([
            //                            'success'=>false,'message'=>"Invalid password."
            //                        ],401);
            //                    }
            //                }else{
            //                    return response()->json([
            //                        'success'=>false,'message'=>"Invalid Contact no.."
            //                    ],401);
            //                }
            //            }else{
            //                return response()->json([
            //                    'success'=>false,'message'=>"Invalid  Contact no./password."
            //                ],401);
            //            }
        } {
        return response()->json(['success' => false, 'message' => 'Customer not found'], 401);
    }
    }

    public function repairlogin(Request $request)
    {
        if (htmlspecialchars(strip_tags($request->repair_username)) != null) {
            $repair_customer = RepairCenter::where('username', $request->repair_username)->first();
            if ($repair_customer) {
                if ($repair_customer->username == $request->repair_username) {
                    if (Hash::check($request->repair_password, $repair_customer->password)) {
                        $foundCustomer = RepairCenter::where('username', $repair_customer->username)->first();
                        return response()->json([
                            'success' => true, 'message' => "User Found", 'repair_centers' => $foundCustomer
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false, 'message' => "Invalid password."
                        ], 401);
                    }
                } else {
                    return response()->json([
                        'success' => false, 'message' => "Invalid Username."
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false, 'message' => "Invalid username/password."
                ], 401);
            }
        } {
        return response()->json(['success' => false, 'message' => 'Customer not found'], 401);
    }
    }

    public function getClaimsList(Request $request)
    {
        $claims = Claim::leftJoin('policies', 'policies.id', '=', 'claims.policy_id')
            ->where('claims.customer_id', $request->customer_id)
            ->orderBy('claims.id', 'DESC')
            ->get(['claims.id', 'claims.claim_number', 'claims.claim_type', 'claims.status', 'policies.policyNumber']);
        $count = count($claims);
        if ($count > 0) {
            return response()->json([
                'success' => true, 'message' => "Claims found for the user", 'claims' => $claims
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No claims found for the user"
            ], 401);
        }
    }

    public function getRepairClaims()
    {
        $claims = Claim::leftJoin('policies', 'policies.id', '=', 'claims.policy_id')
            ->where('claims.claim_type', 'Cellphone')->orderBy('claims.id', 'DESC')
            ->get(array('claims.id','claims.po', 'claims.customer_id', 'claims.agent_id', 'claims.policy_id', 'claims.supplier_id', 'claims.claim_type', 'claims.claim_number', 'claims.status', 'policies.policyNumber'));
        foreach ($claims as $cellphone_claim) {
            $cellphone_claim->assestment_count = ClaimAssessment::where('claim_id', $cellphone_claim->id)->count();
            $cellphone_claim->quote_count = ClaimQuote::where('claim_id', $cellphone_claim->id)->count();
            $cellphone_claim->invoice= ClaimCellphone::where('claim_id', $cellphone_claim->id)->first(array('after_repair_front','after_repair_back','after_repair_left','after_repair_right','after_repair_top','after_repair_bottom'));
            $quote= ClaimQuote::where('claim_id', $cellphone_claim->id)->first(array('status'));

            if($quote != NULL){
                $cellphone_claim->download_po = $quote->status;
            }
            else{
                $cellphone_claim->download_po = 0;
            }
        }

        $count = count($claims);
        if ($count > 0) {
            return response()->json([
                'success' => true, 'message' => "Claims found for the user", 'claims' => $claims
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No claims found for the user"
            ], 401);
        }
    }

    //  array:2 [
    //   "filter" => "CustomerName"
    //   "searchValue" => "Jo111111112xswger657 Sent"
    // ]
    public function searchClaim(Request $request)
    {
        $request->validate([
            "filter"      => 'bail|required|in:CustomerName,PolicyNumber,Cellphone',
        ]);

        if($request->filter == 'CustomerName')
        {
            $request->validate([
                "searchValue" => 'bail|required|regex:/^[A-Za-z0-9 ]{1,30}+$/|max:30|min:1',
            ]);

        }elseif($request->filter == 'PolicyNumber'){
            $request->validate([
                "searchValue" => 'bail|required|regex:/^[MIS]{3}[0-9]{10}+$/|max:13',
            ]);

        }elseif($request->filter == 'Cellphone'){
            $request->validate([
                "searchValue" => 'bail|required|digits:8|numeric',
            ]);

        }else{
            return response()->json(['status' => false, 'errors' => 'Validation fails.']);
        }
        //Search filter from search bar
        $category = $request['filter'];

        switch ($category) {

            case "Cellphone":

                $customerCellphone = $request->searchValue;
                $customers = Customer::where('cellphone', 'like', '%' . $customerCellphone . '%')->get(['id']);
                //$policies = Policy::whereIn('customer_id', $customers->pluck('id')->toArray())->get(['id', 'policyNumber', 'customer_id']);
                $searchResult = Claim::join('policies', 'claims.policy_id', 'policies.id')->whereIn('claims.customer_id', $customers->pluck('id')->toArray())
                    ->where('claims.claim_type', 'Cellphone')
                    ->get(['claims.id','claims.po','claims.customer_id', 'claims.agent_id', 'claims.policy_id', 'claims.supplier_id', 'claims.claim_type', 'claims.claim_number', 'claims.status','policies.policyNumber']);

                $resultCount  = count($searchResult);

                break;

            case "CustomerName":

                $customerName =  $request->searchValue;
                // $searchResult = Customer::customerClaim($customerName, "customerName");
                $searchValues = preg_split('/\s+/', $customerName, -1, PREG_SPLIT_NO_EMPTY);
                $searchResult = Claim::leftJoin('customer', 'customer.id', '=', 'claims.customer_id')
                    ->leftJoin( 'policies','policies.id', '=', 'claims.policy_id')
                    ->where('claims.claim_type', 'Cellphone')
                    ->where('customer.firstName', 'like', '%' . $searchValues[0] . '%')
                    ->orWhere('customer.lastName', 'like', '%' . $searchValues[1] . '%');
                $searchResult = $searchResult->orderBy('claims.id', 'DESC')
                    ->get(array('claims.id','claims.po','claims.customer_id',
                        'claims.agent_id', 'claims.policy_id',
                        'claims.supplier_id', 'claims.claim_type',
                        'claims.claim_number', 'claims.status','policies.policyNumber'
                    ));

                $resultCount  = count($searchResult);

                break;

            case "PolicyNumber":

                $PolicyNumber =  $request->searchValue;
                $searchResult =  Claim::leftJoin('policies', 'policies.id', '=', 'claims.policy_id')
                    ->where('claims.claim_type', 'Cellphone')
                    ->where('policies.policyNumber', $PolicyNumber)
                    ->orderBy('claims.id', 'DESC')
                    ->get(array('claims.id','claims.po','claims.customer_id', 'claims.agent_id', 'claims.policy_id', 'claims.supplier_id', 'claims.claim_type', 'claims.claim_number', 'claims.status', 'policies.policyNumber'));
                $objKeys      = get_object_vars($searchResult);
                $resultCount  = count($objKeys);
                break;
            default:

                return response()->json(['No case found'], 404);

                break;
        }

        foreach ($searchResult as $cellphone_claim) {
            $cellphone_claim->assestment_count = ClaimAssessment::where('claim_id', $cellphone_claim->id)->count();
            $cellphone_claim->quote_count = ClaimQuote::where('claim_id', $cellphone_claim->id)->count();
            $claim = Claim::where('id', $cellphone_claim->id)->first(array('invoice'));
            if($claim != NULL)
                $cellphone_claim->invoice = $claim->invoice;
            else
                $cellphone_claim->invoice = NULL;
            $quote= ClaimQuote::where('claim_id', $cellphone_claim->id)->where('status', 1)->first(array('supplier_id'));
            if($quote != NULL){
                $cellphone_claim->po_supplier_id = $quote->supplier_id;
            }
            else{
                $cellphone_claim->po_supplier_id = 0;
            }
        }

        return response()->json(['status' => true, 'claims' => $searchResult, 'resultCount' => $resultCount]);
    }


    public function getPolicyList(Request $request)
    {
        $policies = Policy::leftJoin('products', 'products.id', '=', 'policies.product_id')
            ->where('policies.customer_id', $request->customer_id)
            ->orderBy('policies.id', 'DESC')
            ->get([
                'policies.id',
                'policies.policyNumber',
                'products.name',
                'products.type',
                'products.has_vehicle',
                'policies.status',
                'policies.policyActivatedDate',
                'policies.product_id'
            ]);
        $count = count($policies);
        if ($count > 0) {

            foreach($policies as $key=>$policy){
                if($policy->status == 1 && $policy->policyActivatedDate){
                    $expiryDate = Carbon::parse($policy->policyActivatedDate)->addYear(1)->format('Y-m-d');

                    $datetime1 = new DateTime(Carbon::now()->format('Y-m-d'));
                    $datetime2 = new DateTime($expiryDate);
                    $interval = $datetime1->diff($datetime2);
                    $days = $interval->d;

                    if($days <= 15)
                        $policy['isRenewal'] = 1;
                    else
                        $policy['isRenewal'] = 0;

                }
            }

            return response()->json([
                'success' => true, 'message' => "Policies found for the user", 'policies' => $policies
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No policy found for the user"
            ], 401);
        }
    }

    public function getRepair()
    {
        $repair_quote = ClaimQuoteDetail::get();
        $count = count($repair_quote);
        if ($count > 0) {
            return response()->json([
                'success' => true, 'message' => "Repair Quote found for the user", 'repair_quote' => $repair_quote
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No Repair Quote found for the user"
            ], 401);
        }
    }

    public function viewClaimData(Request $request)
    {
        $claim = Claim::where('id', $request->get('claim_id'))->first();
        if ($claim)
            $claimType = $claim->claim_type;
        else
            return response()->json(['success' => false, 'message' => 'Claim not found.'], 401);

        if ($claimType == "Accident") {
            $passengers = ClaimAccidentPassenger::where('claim_id', $claim->id)->get();

            foreach ($passengers as $passenger) {
                if($passenger->country){
                    $passenger_country = Country::where('id', $passenger->country)->first('name');
                    $passenger->country_name = $passenger_country;
                }
               if($passenger->state){
                    $passenger_state = State::where('id', $passenger->state)->first('name');
                    $passenger->state_name = $passenger_state->name;
                }
            }
            $driverDetail = AccidentDriver::where('claim_id', $claim->id)->first();
            $driverCountry = Country::where('id', $driverDetail->country)->first();
            $driverState = State::where('id', $driverDetail->state)->first();
            $driverCity = City::where('name', $driverDetail->city)->first();
            $accidentDetails = ClaimAccident::where('claim_id', $claim->id)
                ->first([
                    'id',
                    'claim_id',
                    'third_party as third_party_involved',
                    'date_of_accident',
                    'detail_of_accident',
                    'purpose_of_trip',
                    'police_report',
                    'vehicle_document',
                    'party_at_fault',
                    'place_of_accident',
                    'time_of_accident',
                ]);
            $thirdParty = ClaimThirdParty::where('claim_id', $claim->id)->get();
            foreach ($thirdParty as $third) {
                $injury = AccidentInjury::where('otherparty_id', $third->id)->get()->toArray();
                $third->injury = $injury;
            }
            $recipient = RecipientKyc::where('claim_id', $claim->id)->first();
            $claimDetails = Claim::where('id', $request->get('claim_id'))->first(['id', 'policy_id', 'claim_number', 'claim_type', 'status']);
            $policyNumber = Policy::where('id', $claimDetails->policy_id)->first(['policyNumber']);
            $customer_id = Policy::where('id', $claimDetails->policy_id)->first(['customer_id']);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Claim found.',
                    'claimDetails' => $claimDetails,
                    'passengers' => $passengers,
                    'passenger' => $passenger,
                    'driverDetails' => $driverDetail,
                    'country' => $driverCountry,
                    'state' => $driverState,
                    'city' => $driverCity,
                    'accidentDetails' => $accidentDetails,
                    'thirdParty' => $thirdParty,
                    'recipient' => $recipient,
                    'policyNumber' => $policyNumber,
                    'customer' => $claim->customer_id,
                ],
                200
            );
        } elseif ($claimType == "Life") {
            $claimDetails = Claim::join('claim_life', 'claim_life.claim_id', '=', 'claims.id')
                ->join('claim_recipient', 'claim_recipient.claim_id', '=', 'claims.id')
                ->where('claims.id', $request->get('claim_id'))
                ->first([
                    'claims.id',
                    'claims.claim_number',
                    'claims.claim_type',
                    'claims.status',
                    'claim_life.date_of_death',
                    'claim_life.cause_of_death',
                    'claim_life.certificate',
                    'claim_life.description',
                    'claim_recipient.driving_license',
                    'claim_recipient.omang',
                    'claim_recipient.proof_residence',
                    'claim_recipient.proof_income',
                    'claim_recipient.passport',
                ]);
            $policyNumber = Policy::where('id', $claim->policy_id)->first(['policyNumber']);
            $beneficiary = PolicyBeneficiary::where('policy_id', $claim->policy_id)->get();
            $customer_id = Policy::where('id', $claimDetails->policy_id)->first(['customer_id']);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Claim found.',
                    'claimDetails' => $claimDetails,
                    'beneficiary' => $beneficiary,
                    "policyNumber" => $policyNumber,
                    'customer' => $claim->customer_id,
                ],
                200
            );
        }
        elseif ($claimType == "Glass") {
            $claimData = Claim::join('claim_vehicle', 'claim_vehicle.claim_id', '=', 'claims.id')
                ->join('claim_recipient', 'claim_recipient.claim_id', '=', 'claims.id')
                ->where('claims.id', $request->get('claim_id'))
                ->first();
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Claim found.',
                    'claimDetails' => $claimData,
                ],
                200
            );
        }
        else {
            return response()->json(['success' => false, 'message' => 'Claim type not found.'], 401);
        }
    }

    public function getClaimData(Request $request)
    {
        $claim = Claim::where('id', $request->get('claim_id'))->first();
        if ($claim)
            $claimType = $claim->claim_type;
        else
            return response()->json(['success' => false, 'message' => 'Claim not found.'], 401);

        if ($claimType == "Accident") {
            $passengers = ClaimAccidentPassenger::where('claim_id', $claim->id)->get();
            $driverDetail = AccidentDriver::where('claim_id', $claim->id)->first();
            $accidentDetails = ClaimAccident::where('claim_id', $claim->id)
                ->first([
                    'id',
                    'claim_id',
                    'third_party as third_party_involved',
                    'date_of_accident',
                    'place_of_accident',
                    'time_of_accident',
                ]);
            $thirdParty = ClaimThirdParty::where('claim_id', $claim->id)->first();
            $recipient = RecipientKyc::where('claim_id', $claim->id)->first();
            $claimDetails = Claim::where('id', $request->get('claim_id'))->first(['id', 'policy_id', 'claim_number', 'claim_type', 'status']);
            $policyNumber = Policy::where('id', $claimDetails->policy_id)->first(['policyNumber']);
            $customer_id = Policy::where('id', $claimDetails->policy_id)->first(['customer_id']);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Claim found.',
                    'claimDetails' => $claimDetails,
                    'passengers' => $passengers,
                    'driverDetails' => $driverDetail,
                    'accidentDetails' => $accidentDetails,
                    'thirdParty' => $thirdParty,
                    'recipient' => $recipient,
                    'policyNumber' => $policyNumber,
                    'customer' => $claim->customer_id,
                ],
                200
            );
        } elseif ($claimType == "Life") {
            $claimDetails = Claim::join('claim_life', 'claim_life.claim_id', '=', 'claims.id')
                ->join('claim_recipient', 'claim_recipient.claim_id', '=', 'claims.id')
                ->where('claims.id', $request->get('claim_id'))
                ->first([
                    'claims.id',
                    'claims.claim_number',
                    'claims.claim_type',
                    'claims.status',
                    'claim_life.date_of_death',
                    'claim_life.cause_of_death',
                    'claim_life.certificate',
                    'claim_life.description',
                    'claim_recipient.driving_license',
                    'claim_recipient.omang',
                    'claim_recipient.proof_residence',
                    'claim_recipient.proof_income',
                    'claim_recipient.passport',
                ]);
            $policyNumber = Policy::where('id', $claim->policy_id)->first(['policyNumber']);
            $beneficiary = PolicyBeneficiary::where('policy_id', $claim->policy_id)->get();
            $customer_id = Policy::where('id', $claimDetails->policy_id)->first(['customer_id']);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Claim found.',
                    'claimDetails' => $claimDetails,
                    'beneficiary' => $beneficiary,
                    "policyNumber" => $policyNumber,
                    'customer' => $claim->customer_id,
                ],
                200
            );
        }
        elseif($claimType == "Cellphone"){
            $policyNumber = Policy::where('id',$claim->policy_id)->first(['policyNumber']);
            $recipient = RecipientKyc::where('claim_id',$claim->id)->first();
            $claimDetails = Claim::join('claim_cellphones','claim_cellphones.claim_id', '=' , 'claims.id')
                ->where('claims.id',$request->get('claim_id'))
                ->first([
                    'claims.id',
                    'claims.claim_number',
                    'claims.claim_type',
                    'claims.status',
                    'claim_cellphones.damage_extent',
                    'claim_cellphones.lossDate',
                    'claim_cellphones.mileage',
                    'claim_cellphones.condition',
                    'claim_cellphones.imei',
                    'claim_cellphones.descriptionofLoss',
                    'claim_cellphones.front',
                    'claim_cellphones.back',
                    'claim_cellphones.left',
                    'claim_cellphones.right',
                    'claim_cellphones.top',
                    'claim_cellphones.bottom',
                    // 'claim_cellphones.police_station',
                    // 'claim_cellphones.case_number',
                    'claim_cellphones.contact_number',
                    // 'claim_cellphones.ITC_reference_number',
                    'claim_cellphones.date_reported',
                    'claim_cellphones.date_reported_to_alpha',
                ]);
            return response()->json(
                [
                    'success'=>true,
                    'message'=>'Claim found.',
                    'claimDetails'=>$claimDetails,
                    'recipient'=>$recipient,
                    'customer'=>$claim->customer_id,
                    "policyNumber"=>$policyNumber,
                ],
                200);
        }
        elseif ($claimType == "Glass") {
            $policyNumber = Policy::where('id', $claim->policy_id)->first(['policyNumber']);
            $recipient = RecipientKyc::where('claim_id', $claim->id)->first();
            $suppliers = Supplier::where('id', $claim->supplier_id)->first();
            $glassData = ClaimVehicle::where('claim_id', $claim->id)->first();

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Claim found.',
                    'claimDetails' => $claim,
                    'recipient' => $recipient,
                    'suppliers' => $suppliers,
                    'glassData' => $glassData,
                    'customer' => $claim->customer_id,
                    "policyNumber" => $policyNumber,
                ],
                200
            );
        } elseif ($claimType == "Key Loss") {
            $claimDetails = Claim::leftJoin('claim_key_loss', 'claim_key_loss.claim_id', '=', 'claims.id')
                ->where('claims.id', $request->get('claim_id'))
                ->first();
            $policyNumber = Policy::where('id', $claimDetails->policy_id)->first()->policyNumber;
            $purpose = Lookup::where('id', $claimDetails->purpose)->first()->value;
            $reason = Lookup::where('id', $claimDetails->reason)->first()->value;

            return response()->json(
                [
                    'success' => true,
                    'details' => $claimDetails,
                    'purpose' => $purpose,
                    'reason' => $reason,
                    "policyNumber" => $policyNumber,
                    'customer' => $claim->customer_id,
                ],
                200
            );
        } elseif ($claimType == "Legal") {
            $claimDetails = Claim::leftJoin('claim_legal', 'claim_legal.claim_id', '=', 'claims.id')
                ->where('claims.id', $request->get('claim_id'))
                ->first();

            $policyNumber = Policy::where('id', $claimDetails->policy_id)->first()->policyNumber;

            return response()->json(
                [
                    'success' => true,
                    'details' => $claimDetails,
                    "policyNumber" => $policyNumber,
                    'customer' => $claim->customer_id,
                ],
                200
            );
        }else {
            return response()->json(['success' => false, 'message' => 'Claim type not found.'], 401);
        }
    }

    public function vehicleMake()
    {
        $purpose = Lookup::where('key', 'vehicle_purpose')->whereIn('id', [27,28,29])->where('status', 1)->get(array('id', 'key', 'value'));

        $vehcileMake = VehicleMake::groupBy('s_Make')->where('s_Make', '<>', '')->where('s_Make', '<>', null)->get(['id', 's_Make']);

        if ($vehcileMake)
            return response()->json(['success' => true, 'makes' => $vehcileMake, 'Purpose' => $purpose], 200);
        else
            return response()->json(['success' => false, 'message' => 'No vehicle makes found.'], 401);
    }

    public function vehicleModel(Request $request)
    {
        $vehcileModels = VehicleMake::where('s_Make', $request->get('vehicle_make'))->where('s_Variant', '<>', '')->where('s_Variant', '<>', null)->get(['s_Variant', 's_IntroDate', 's_DiscDate']);
        if ($vehcileModels)
            return response()->json(['success' => true, 'makes' => $vehcileModels], 200);
        else
            return response()->json(['success' => false, 'message' => 'No vehicle models found.'], 401);
    }
    public function getCountries()
    {
        $countries = Country::groupBy('name')->get(['id', 'name']);
        if ($countries)
            return response()->json(['success' => true, 'countries' => $countries], 200);
        else
            return response()->json(['success' => false, 'message' => 'No countries found.'], 401);
    }

    public function getStates(Request $request)
    {
        $states = State::where('country_id', $request->get('country_id'))->get(['id', 'name']);
        if ($states)
            return response()->json(['success' => true, 'states' => $states], 200);
        else
            return response()->json(['success' => false, 'message' => 'No states found.'], 401);
    }

    public function getCities(Request $request)
    {
        $cities = City::where('state_id', $request->get('state_id'))->get(['id', 'name']);

        if (count($cities) > 0)
            return response()->json(['success' => true, 'cities' => $cities], 200);
        else
            return response()->json(['success' => false, 'message' => 'No city found.'], 401);
    }

    public function addClaim(Request $request)
    {
        if (htmlspecialchars(strip_tags($request->claim_type)) != null) {
            $policy = Policy::where('id', $request->policy_id)->first(array('has_vehicle', 'customer_id', 'agent_id', 'kyc_recipient', 'product_id'));
            if ($policy == NULL)
                return response()->json(['success' => false, 'message' => 'Policy not found'], 401);
            //Latestid for Claim Number
            $latest = Claim::orderBy('id', 'DESC')->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 0;
            }

            if ($request->get('customer_selected_supplier') == 1) {
                $supplier = new Supplier();
                $supplier->supplierName = htmlspecialchars(strip_tags($request->get('sname')));
                $supplier->supplierType = htmlspecialchars(strip_tags($request->get('stype')));
                $supplier->vat_no = htmlspecialchars(strip_tags($request->get('vat')));
                $supplier->telephone = htmlspecialchars(strip_tags($request->get('snumber')));
                $supplier->email = htmlspecialchars(strip_tags($request->get('semail')));
                $supplier->customer_selected = htmlspecialchars(strip_tags($request->get('customer_selected_supplier')));
                $supplier->supplierLocation = htmlspecialchars(strip_tags($request->get('slocation')));
                $supplier->save();
            }
            $customer_email = Customer::where('id', $policy->customer_id)->first();
            $claim = new Claim();
            $claim->claim_number = 'G' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $claim->customer_id = $policy->customer_id;
            $claim->agent_id = $policy->agent_id;
            $claim->policy_id = htmlspecialchars(strip_tags($request->policy_id));
            $claim->claim_type = htmlspecialchars(strip_tags($request->claim_type));

            if ($request->get('customer_selected_supplier') == 1)
                $claim->supplier_id = $supplier->id;
            $claim->ip = htmlspecialchars(strip_tags($request->ip));
            $claim->machine_data = htmlspecialchars(strip_tags($request->machine_data));
            $claim->customer_selected = 1;
            //            $data = new \stdClass();
            //            $data->user_id = $policy->customer_id;
            //            $data->hook = 'create_claim';
            //            $data->attachment = NULL;
            //            Mail::to($customer_email->email)->send(new MailTemplate($data));

            $saveClaim = $claim->save();

            if ($saveClaim) {
                if ($request->claim_type == "Life") {
                    $lifeClaim = new ClaimLife();
                    $lifeClaim->claim_id = $claim->id;
                    $lifeClaim->date_of_death = Carbon::createFromFormat('d/m/Y', $request->get('dateOfDeath'));
                    $lifeClaim->cause_of_death = htmlspecialchars(strip_tags($request->causeOfDeath));
                    $lifeClaim->description = htmlspecialchars(strip_tags($request->descriptionofDeath));

                    if ($request->hasFile('certificate')) {
                        $file = $request->file('certificate');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $lifeClaim->certificate = $filePath;
                    }

                    $saveLifeClaim = $lifeClaim->save();

                    if ($saveLifeClaim)
                        if ($saveClaim && $saveLifeClaim) {

                            $recipientKyc = new RecipientKyc();
                            $recipientKyc->policy_id = $claim->policy_id;
                            $recipientKyc->claim_id = $claim->id;

                            if ($request->hasFile('driving_license')) {
                                $file = $request->file('driving_license');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKyc->driving_license = $filePath;
                            }
                            if ($request->hasFile('omang')) {
                                $file = $request->file('omang');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKyc->omang = $filePath;
                            }
                            if ($request->hasFile('proof_residence')) {
                                $file = $request->file('proof_residence');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKyc->proof_residence = $filePath;
                            }
                            if ($request->hasFile('proof_income')) {
                                $file = $request->file('proof_income');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKyc->proof_income = $filePath;
                            }
                            if ($request->hasFile('passport')) {
                                $file = $request->file('passport');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKyc->passport = $filePath;
                            }
                            $recipientKyc->save();
                        }
                    if ($recipientKyc) {
                        return response()->json(['success' => true, 'message' => 'Claim Saved successfully..'], 200);
                    } else {
                        return response()->json(['success' => false, 'message' => 'Claim process failed..'], 401);
                    }
                } elseif ($request->claim_type == "Accident") {
                    $accidentClaim = new ClaimAccident();
                    $accidentClaim->claim_id = $claim->id;
                    $accidentClaim->claim_type = htmlspecialchars(strip_tags($request->claim_type));
                    $accidentClaim->date_of_accident = Carbon::createFromFormat('d/m/Y', $request->get('dateOfAccident'));
                    $accidentClaim->place_of_accident = htmlspecialchars(strip_tags($request->placeOfAccident));
                    $accidentClaim->purpose_of_trip = htmlspecialchars(strip_tags($request->purposeoftrip));
                    $accidentClaim->detail_of_accident = htmlspecialchars(strip_tags($request->placeOfAccident));
                    $accidentClaim->time_of_accident = htmlspecialchars(strip_tags($request->timeOfAccident));
                    $accidentClaim->third_party = htmlspecialchars(strip_tags($request->third_party));
                    $accidentClaim->party_at_fault = htmlspecialchars(strip_tags($request->partyatfault));

                    if ($request->hasFile('police_report')) {
                        $file = $request->file('police_report');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $accidentClaim->police_report = $filePath;
                    }
                    if ($request->hasFile('vehicle_document')) {
                        $file = $request->file('vehicle_document');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $accidentClaim->vehicle_document = $filePath;
                    }
                    $saveAccidentClaim = $accidentClaim->save();

                    $accidentDriver = new AccidentDriver();
                    $accidentDriver->claim_id = $claim->id;
                    $accidentDriver->name = htmlspecialchars(strip_tags($request->driverfName)) . htmlspecialchars(strip_tags($request->driverlName));
                    $accidentDriver->fname = htmlspecialchars(strip_tags($request->driverfName));
                    $accidentDriver->mname = htmlspecialchars(strip_tags($request->drivermName));
                    $accidentDriver->lname = htmlspecialchars(strip_tags($request->driverlName));
                    $accidentDriver->address = htmlspecialchars(strip_tags($request->driverAddress));
                    $accidentDriver->country = htmlspecialchars(strip_tags($request->driverCountry));
                    $accidentDriver->city = htmlspecialchars(strip_tags($request->driverCity));
                    $accidentDriver->state = htmlspecialchars(strip_tags($request->driverState));
                    $accidentDriver->country_code = htmlspecialchars(strip_tags($request->driverCity));
                    $accidentDriver->dob = Carbon::createFromFormat('d/m/Y', $request->get('driverDOB'));
                    $accidentDriver->country_code = htmlspecialchars(strip_tags($request->countryCode));
                    $accidentDriver->cellphone = htmlspecialchars(strip_tags($request->driverContactNumber));
                    $accidentDriver->license = htmlspecialchars(strip_tags($request->driverLicense));
                    $accidentDriver->purpose = htmlspecialchars(strip_tags($request->driverPurpose));
                    $accidentDriver->license_expiry = Carbon::createFromFormat('d/m/Y', $request->get('licenceexpiry'));
                    $accidentDriver->save();

                    $accidentPassenger = new ClaimAccidentPassenger();
                    $accidentPassenger->claim_id = $claim->id;
                    $accidentPassenger->name = htmlspecialchars(strip_tags($request->injuredPassengerName)) . htmlspecialchars(strip_tags($request->injuredPassengerlName));
                    $accidentPassenger->fname = htmlspecialchars(strip_tags($request->injuredPassengerfName));
                    $accidentPassenger->mname = htmlspecialchars(strip_tags($request->injuredPassengermName));
                    $accidentPassenger->lname = htmlspecialchars(strip_tags($request->injuredPassengerlName));
                    $accidentPassenger->city = htmlspecialchars(strip_tags($request->injuredPassengerCity));
                    $accidentPassenger->country = htmlspecialchars(strip_tags($request->injuredPassengerCountry));
                    $accidentPassenger->state = htmlspecialchars(strip_tags($request->injuredPassengerState));
                    $accidentPassenger->contact = htmlspecialchars(strip_tags($request->injuredPassengerlCellphone));
                    $accidentPassenger->address = htmlspecialchars(strip_tags($request->injuredPassengerAddress));
                    $accidentPassenger->injury = htmlspecialchars(strip_tags($request->injury));
                    $accidentPassenger->save();

                    if ($request->third_party == 1) {
                        if (count($request->get('third-party')) > 0) {
                            foreach ($request->get('third-party') as $key => $party) {
                                if ($party['otherPartyFirstName'] && $party['otherPartyLastName'] != null) {
                                    $p = new ClaimThirdParty();
                                    $p->claim_id = $claim->id;
                                    $p->first_name = htmlspecialchars(strip_tags($party['otherPartyFirstName']));
                                    $p->last_name = htmlspecialchars(strip_tags($party['otherPartyLastName']));
                                    $p->address = htmlspecialchars(strip_tags($party['otherPartyAddress']));
                                    $p->cellphone = htmlspecialchars(strip_tags($party['otherPartyMobileNumber']));
                                    $p->damage_details = htmlspecialchars(strip_tags($party['vehicleDamageDetail']));
                                    $p->registration_no = htmlspecialchars(strip_tags($party['vehicleRegistration']));
                                    if ($party['vehicleMake'] == "Other") {
                                        $p->othermake = htmlspecialchars(strip_tags($party['othermakemodel']));
                                    }
                                    $p->make = $party['vehicleMake'];
                                    $p->model = $party['vehicleModel'];
                                    $p->save();
                                }
                                if (count($party['accident-injured']) > 0) {
                                    foreach ($party['accident-injured'] as $key => $injured) {
                                        if ($injured['Injuredname'] && $injured['Injuredrelation'] != null) {
                                            $i = new AccidentInjury();
                                            $i->otherparty_id = $p->id;
                                            $i->claim_id = $claim->id;
                                            $i->injured_name = htmlspecialchars(strip_tags($injured['Injuredname']));
                                            $i->relationship = htmlspecialchars(strip_tags($injured['Injuredrelation']));
                                            $i->details = htmlspecialchars(strip_tags($injured['InjuredDetail']));
                                            $i->hospital_name = htmlspecialchars(strip_tags($injured['InjuredhospitalName']));
                                            $i->save();
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($saveClaim && $saveAccidentClaim) {
                        $check = RecipientKyc::where('claim_id', $claim->id)->first();
                        if ($check != null)
                            $recipientKYC = RecipientKyc::where('claim_id', $claim->id)->first();
                        else
                            $recipientKYC = new RecipientKyc();

                        $recipientKYC->claim_id = $claim->id;
                        $recipientKYC->policy_id = htmlspecialchars(strip_tags($request->policy_id));

                        if ($request->hasFile('driving_license')) {
                            $file = $request->file('driving_license');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKYC->driving_license = $filePath;
                        }
                        if ($request->hasFile('omang')) {
                            $file = $request->file('omang');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKYC->omang = $filePath;
                        }
                        if ($request->hasFile('proof_residence')) {
                            $file = $request->file('proof_residence');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKYC->proof_residence = $filePath;
                        }
                        if ($request->hasFile('proof_income')) {
                            $file = $request->file('proof_income');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKYC->proof_income = $filePath;
                        }
                        if ($request->hasFile('passport')) {
                            $file = $request->file('passport');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKYC->passport = $filePath;
                        }
                        $recipientKYC->save();
                    }
                    return response()->json(['success' => true, 'message' => 'Claim Saved successfully.'], 200);
                }
                elseif ($request->claim_type == "Glass") { //store Glass claim
                    $glass = new ClaimVehicle();
                    $glass->claim_id = $claim->id;

                    if ($request->policy_id)
                        $vehicle = Vehicle::where('policy_id', $request->policy_id)->first(['id']);
                    else
                        return response()->json(['success' => false, 'message' => 'Policy Id not found.'], 401);

                    $glass->vehicle_id = $vehicle->id;
                    $glass->date_of_damage = $this->formatDate(htmlspecialchars(strip_tags($request->date_of_damage)));
                    $glass->damage_extent = htmlspecialchars(strip_tags($request->damage_extent));
                    $glass->damage_cause = htmlspecialchars(strip_tags($request->damage_cause));
                    $glass->front_image_description = htmlspecialchars(strip_tags($request->front_image_description));
                    $glass->back_image_description = htmlspecialchars(strip_tags($request->back_image_description));
                    $glass->right_image_description = htmlspecialchars(strip_tags($request->right_image_description));
                    $glass->left_image_description = htmlspecialchars(strip_tags($request->left_image_description));
                    $glass->glass_location = htmlspecialchars(strip_tags($request->location_glass));
                    $glass->other_location = htmlspecialchars(strip_tags($request->other_location));
                    if ($request->hasFile('back_image')) {
                        $file = $request->file('back_image');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $glass->back_image = $filePath;
                    }
                    if ($request->hasFile('right_image')) {
                        $file = $request->file('right_image');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $glass->right_image = $filePath;
                    }
                    if ($request->hasFile('left_image')) {
                        $file = $request->file('left_image');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $glass->left_image = $filePath;
                    }
                    if ($request->hasFile('front_image')) {
                        $file = $request->file('front_image');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $glass->front_image = $filePath;
                    }
                    $glass->save();
                    if ($saveClaim && $glass) {

                        $recipientKyc = new RecipientKyc();
                        $recipientKyc->policy_id = $claim->policy_id;
                        $recipientKyc->claim_id = $claim->id;

                        if ($request->hasFile('driving_license')) {
                            $file = $request->file('driving_license');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKyc->driving_license = $filePath;
                        }
                        if ($request->hasFile('omang')) {
                            $file = $request->file('omang');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKyc->omang = $filePath;
                        }
                        if ($request->hasFile('proof_residence')) {
                            $file = $request->file('proof_residence');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKyc->proof_residence = $filePath;
                        }
                        if ($request->hasFile('proof_income')) {
                            $file = $request->file('proof_income');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKyc->proof_income = $filePath;
                        }
                        if ($request->hasFile('passport')) {
                            $file = $request->file('passport');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $recipientKyc->passport = $filePath;
                        }
                        if ($recipientKyc->driving_license && $recipientKyc->proof_residence && $recipientKyc->proof_income && ($recipientKyc->omang || $recipientKyc->passport)) {
                            $recipientKyc->compliance = 1;
                        } else {
                            $recipientKyc->compliance = 0;
                        }
                        $recipientKyc->save();
                    }
                }elseif ($request->claim_type == "Cellphone") {
                    $cellphone_claim = new ClaimCellphone();
                    $cellphone_claim->claim_id = $claim->id;
                    $cellphone_claim->policy_id = $request->get('policy_id');
                    $cellphone_claim->damage_extent = htmlspecialchars(strip_tags($request->damage_extent));
                    $cellphone_claim->lossDate = Carbon::createFromFormat('d/m/Y', $request->get('lossDate'));
                    $cellphone_claim->mileage = htmlspecialchars(strip_tags($request->mileage));
                    $cellphone_claim->condition = htmlspecialchars(strip_tags($request->condition));
                    $cellphone_claim->imei = htmlspecialchars(strip_tags($request->imei));
                    $cellphone_claim->descriptionofLoss = htmlspecialchars(strip_tags($request->descriptionofLoss));
                    // $cellphone_claim->police_station = htmlspecialchars(strip_tags($request->police_station));
                    // $cellphone_claim->case_number = htmlspecialchars(strip_tags($request->case_number));
                    $cellphone_claim->contact_number = htmlspecialchars(strip_tags($request->contact_number));
//                    $cellphone_claim->date_reported = Carbon::createFromFormat('d/m/Y', $request->get('date_reported'));
                    // $cellphone_claim->ITC_reference_number = htmlspecialchars(strip_tags($request->ITC_reference_number));
                    $cellphone_claim->date_reported_to_alpha = Carbon::createFromFormat('d/m/Y', $request->get('date_reported_to_alpha'));

                    if ($request->hasFile('front')) {
                        $file = $request->file('front');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $cellphone_claim->front = $filePath;
                    }
                    if ($request->hasFile('back')) {
                        $file = $request->file('back');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $cellphone_claim->back = $filePath;
                    }
                    if ($request->hasFile('left')) {
                        $file = $request->file('left');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $cellphone_claim->left = $filePath;
                    }
                    if ($request->hasFile('right')) {
                        $file = $request->file('right');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $cellphone_claim->right = $filePath;
                    }
                    if ($request->hasFile('top')) {
                        $file = $request->file('top');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $cellphone_claim->top = $filePath;
                    }
                    if ($request->hasFile('bottom')) {
                        $file = $request->file('bottom');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $cellphone_claim->bottom = $filePath;
                    }
                    $cellphone_claim->save();
                }elseif ($request->claim_type == "Key Loss") {
                    $keyLoss = new ClaimKeyLoss();
                    $keyLoss->claim_id = $claim->id;
                    $keyLoss->financial_interest = htmlspecialchars(strip_tags($request->financial_interest));
                    $keyLoss->chassis_num = htmlspecialchars(strip_tags($request->chassis_num));
                    $keyLoss->purpose = htmlspecialchars(strip_tags($request->purpose));
                    $keyLoss->reason = htmlspecialchars(strip_tags($request->reason));
                    $keyLoss->replacement_estimate = htmlspecialchars(strip_tags($request->estimate));
                    $keyLoss->date_of_loss =   $this->formatDate(htmlspecialchars(strip_tags($request->lossDate)));
                    $keyLoss->description = htmlspecialchars(strip_tags($request->descriptionofLoss));
                    $keyLoss->company_1 = htmlspecialchars(strip_tags($request->company_1));
                    $keyLoss->company_2 = htmlspecialchars(strip_tags($request->company_2));
                    $keyLoss->amount_quote_1 = htmlspecialchars(strip_tags($request->amount_quote_1));
                    $keyLoss->amount_quote_2 = htmlspecialchars(strip_tags($request->amount_quote_2));

                    if ($request->hasFile('police_affidavit')) {
                        $file = $request->file('police_affidavit');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'police_affidavit' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $keyLoss->police_affidavit = $filePath;
                    }
                    if ($request->hasFile('quote_1')) {
                        $file = $request->file('quote_1');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'quote_1' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $keyLoss->quote_1 = $filePath;
                    }
                    if ($request->hasFile('quote_2')) {
                        $file = $request->file('quote_2');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'quote_2' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $keyLoss->quote_2 = $filePath;
                    }
                    $keyLoss->save();
                }elseif ($request->claim_type == "Legal") {
                    $legal = new ClaimLegal();
                    $legal->claim_id = $claim->id;
                    $legal->legal_firm = htmlspecialchars(strip_tags($request->legal_firm));
                    $legal->lawyer_name = htmlspecialchars(strip_tags($request->lawyer_name));
                    $legal->legal_tel = htmlspecialchars(strip_tags($request->legal_tel));
                    $legal->legal_email = htmlspecialchars(strip_tags($request->legal_email));
                    $legal->member_name =  htmlspecialchars(strip_tags($request->member_name));
                    // $legal->membership_number = htmlspecialchars(strip_tags($request->membership_number));
                    $legal->membership_id = htmlspecialchars(strip_tags($request->membership_id));
                    $legal->member_contact = htmlspecialchars(strip_tags($request->member_contact));
                    $legal->member_email = htmlspecialchars(strip_tags($request->member_email));
                    $legal->lossreported_date = htmlspecialchars(strip_tags($request->lossreported_date));
                    $legal->matter_relatesto = htmlspecialchars(strip_tags($request->matter_relatesto));
                    $legal->child_financial_dependent = htmlspecialchars(strip_tags($request->child_financial_dependent));
                    $legal->idforchild = htmlspecialchars(strip_tags($request->idforchild));
                    $legal->child_dob = htmlspecialchars(strip_tags($request->child_dob));
                    $legal->realestate_enquiry_from = htmlspecialchars(strip_tags($request->realestate_enquiry_from));
                    $legal->matter_quantum = htmlspecialchars(strip_tags($request->matter_quantum));
                    $legal->course_of_action = htmlspecialchars(strip_tags($request->course_of_action));
                    $legal->criminalmatter_detail = htmlspecialchars(strip_tags($request->criminalmatter_detail));
                    $legal->criminalmatter_charge = htmlspecialchars(strip_tags($request->criminalmatter_charge));

                    $legal->legaloption = htmlspecialchars(strip_tags($request->legaloption));
                    $legal->representing_member = htmlspecialchars(strip_tags($request->representing_member));
                    $legal->lawyer_tarrif = htmlspecialchars(strip_tags($request->lawyer_tarrif));
                    $legal->arose_date = htmlspecialchars(strip_tags($request->arose_date));
                    $legal->jurisdiction = htmlspecialchars(strip_tags($request->jurisdiction));
                    $legal->save();
                }else {
                    return response()->json(['success' => false, 'message' => 'Claim Type not found'], 401);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Claim Type not found'], 401);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Claim Type not found'], 401);
        }
    }

    public function formatDate($date)
    {
        $date = Carbon::createFromFormat("d/m/Y", $date)->timestamp;
        $newDate = date("Y-m-d", $date);
        return $newDate;
    }

    public function updateClaim(Request $request)
    {
        $check = Claim::where('id', $request->claim_id)->first();
        if ($check) {
            if ($check->claim_type != null) {
                if ($check->claim_type == "Life") {
                    $claim = ClaimLife::where('claim_id', $request->claim_id)->first();
                    $claim->date_of_death = Carbon::parse($request->dateOfDeath)->format('Y-m-d');
                    $claim->cause_of_death = $request->causeOfDeath;
                    $claim->description = $request->descriptionodDeath;

                    if ($request->hasFile('certificate')) {
                        $file = $request->file('certificate');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $check->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claim->certificate = $filePath;
                    }

                    $saveClaim = $claim->save();

                    if ($saveClaim) {

                        $check = RecipientKyc::where('claim_id', $request->claim_id)->first();
                        if ($check != null) {
                            $recipientKYC = RecipientKyc::where('claim_id', $request->claim_id)->first();
                            if ($request->hasFile('driving_license')) {
                                $file = $request->file('driving_license');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $check->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->driving_license = $filePath;
                            }
                            if ($request->hasFile('omang')) {
                                $file = $request->file('omang');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $check->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->omang = $filePath;
                            }
                            if ($request->hasFile('proof_residence')) {
                                $file = $request->file('proof_residence');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $check->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->proof_residence = $filePath;
                            }
                            if ($request->hasFile('proof_income')) {
                                $file = $request->file('proof_income');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $check->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->proof_income = $filePath;
                            }
                            if ($request->hasFile('passport')) {
                                $file = $request->file('passport');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $check->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->passport = $filePath;
                            }
                            $recipientKYC->save();
                        } else {
                            $recipientKYC = new RecipientKyc();
                            $recipientKYC->claim_id = $request->claim_id;
                            $recipientKYC->policy_id = $request->policy_id;
                            if ($request->hasFile('driving_license')) {
                                $file = $request->file('driving_license');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->driving_license = $filePath;
                            }
                            if ($request->hasFile('omang')) {
                                $file = $request->file('omang');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->omang = $filePath;
                            }
                            if ($request->hasFile('proof_residence')) {
                                $file = $request->file('proof_residence');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->proof_residence = $filePath;
                            }
                            if ($request->hasFile('proof_income')) {
                                $file = $request->file('proof_income');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->proof_income = $filePath;
                            }
                            if ($request->hasFile('passport')) {
                                $file = $request->file('passport');
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $recipientKYC->passport = $filePath;
                            }
                            $recipientKYC->save();
                        }
                    }
                    if ($saveClaim && $recipientKYC)
                        return response()->json(['success' => true, 'message' => 'Claim Saved successfully..'], 200);
                    else
                        return response()->json(['success' => false, 'message' => 'Claim Process failed.'], 401);
                } elseif ($check->claim_type == "Accident") {
                    $claim = ClaimAccident::where('claim_id', $request->claim_id)->first();
                    $claim->place_of_accident = $request->placeOfAccident;
                    $claim->date_of_accident = Carbon::parse($request->dateOfAccident)->format('Y-m-d');
                    $claim->time_of_accident = $request->timeOfAccident;
                    $claim->third_party = $request->third_party;
                    $saveClaim = $claim->save();

                    $accidentDriver = AccidentDriver::where('claim_id', $request->claim_id)->first();
                    $accidentDriver->name = $request->driverfName . $request->driverlName;
                    $accidentDriver->fname = $request->driverfName;
                    $accidentDriver->mname = $request->drivermName;
                    $accidentDriver->lname = $request->driverlName;
                    $accidentDriver->address = $request->driverAddress;
                    $accidentDriver->country = $request->driverCountry;
                    $accidentDriver->state = $request->driverState;
                    $accidentDriver->country_code = $request->driverCity;
                    $accidentDriver->dob = Carbon::parse($request->driverDOB)->format('Y-m-d');
                    $accidentDriver->country_code = $request->countryCode;
                    $accidentDriver->cellphone = $request->driverContactNumber;
                    $accidentDriver->license = $request->driverLicense;
                    $accidentDriver->purpose = $request->driverPurpose;
                    $accidentDriver->license_expiry = Carbon::parse($request->licenceexpiry)->format('Y-m-d');
                    $accidentDriver->save();

                    if (($request->passengersInjured) != 0) {
                        $accidentPassenger = ClaimAccidentPassenger::where('claim_id', $request->claim_id)->first();
                        $accidentPassenger->name = $request->injuredPassengerfName . $request->injuredPassengerlName;
                        $accidentPassenger->fname = $request->injuredPassengerfName;
                        $accidentPassenger->mname = $request->injuredPassengermName;
                        $accidentPassenger->lname = $request->injuredPassengerlName;
                        $accidentPassenger->city = $request->injuredPassengerCity;
                        $accidentPassenger->country = $request->injuredPassengerCountry;
                        $accidentPassenger->contact = $request->injuredPassengerlCellphone;
                        $accidentPassenger->address = $request->injuredPassengerAddress;
                        $accidentPassenger->injury = $request->injury;
                        $accidentPassenger->save();
                    }

                    if ($request->third_party != 0) {
                        $check = ClaimThirdParty::where('claim_id', $request->claim_id)->first();
                        if ($check) {
                            $accidentThirdParty = ClaimThirdParty::where('claim_id', $request->claim_id)->first();
                        } else {
                            $accidentThirdParty = new ClaimThirdParty();
                            $accidentThirdParty->claim_id = $request->claim_id;
                        }


                        $accidentThirdParty->first_name = $request->otherPartyFirstName;
                        $accidentThirdParty->last_name = $request->otherPartyLastName;
                        $accidentThirdParty->address = $request->otherPartyAddress;
                        $accidentThirdParty->cellphone = $request->otherPartyMobileNumber;
                        $accidentThirdParty->damage_details = $request->vehicleDamageDetail;
                        $accidentThirdParty->registration_no = $request->vehicleRegistration;
                        $accidentThirdParty->make = $request->vehicleMake;
                        $accidentThirdParty->model = $request->vehicleModel;
                        $accidentThirdParty->injured_name = $request->nameOfInjured;
                        $accidentThirdParty->relationship = $request->relationWithInjured;
                        $accidentThirdParty->hospital_name = $request->hospitalName;
                        $accidentThirdParty->injured_details = $request->injuredDetails;
                        $accidentThirdParty->save();
                    }
                    $claimCheck = ClaimThirdParty::where('claim_id', $request->claim_id)->first();
                    if ($request->third_party == 0 && $claimCheck != null) {
                        $claimCheck->delete();
                    }

                    $check = RecipientKyc::where('claim_id', $claim->id)->first();
                    if ($check != null) {
                        $recipientKYC = RecipientKyc::where('claim_id', $claim->id)->first();
                    } else {
                        $recipientKYC = new RecipientKyc();
                        $recipientKYC->claim_id = $claim->id;
                        $recipientKYC->policy_id = $request->policy_id;
                    }
                    if ($request->hasFile('driving_license')) {
                        $file = $request->file('driving_license');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKYC->driving_license = $filePath;
                    }
                    if ($request->hasFile('omang')) {
                        $file = $request->file('omang');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKYC->omang = $filePath;
                    }
                    if ($request->hasFile('proof_residence')) {
                        $file = $request->file('proof_residence');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKYC->proof_residence = $filePath;
                    }
                    if ($request->hasFile('proof_income')) {
                        $file = $request->file('proof_income');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKYC->proof_income = $filePath;
                    }
                    if ($request->hasFile('passport')) {
                        $file = $request->file('passport');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claim->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKYC->passport = $filePath;
                    }
                    $recipientKYC->save();
                    if ($saveClaim) {
                        return response()->json(['success' => true, 'message' => 'Claim Saved successfully..'], 200);
                    } else {
                        return response()->json(['success' => false, 'message' => 'Claim update failed..'], 401);
                    }
                } else {
                    return response()->json(['success' => false, 'message' => 'Claim Type not found.'], 401);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Claim Type not found.'], 401);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Claim not found.'], 401);
        }
    }

    public function getBeneficaries(Request $request)
    {
        if ($request->get('id') == "policy") {
            $beneficiaries = PolicyBeneficiary::where('policy_id', $request->get('policy_id'))->get();
        }
        if ($request->get('id') == "claim") {
            $claim = Claim::where('id', $request->get('claim_id'))->first();
            $beneficiaries = PolicyBeneficiary::where('policy_id', $claim->policy_id)->get();
        }

        if (count($beneficiaries) > 0)
            return response()->json(['success' => true, 'beneficiaries' => $beneficiaries], 200);
        else
            return response()->json(['success' => true, 'message' => 'No beneficiary found.'], 200);
    }

    public function profileUpdate(Request $request)
    {
        try {
            if ($request->profile_customer_id) {
                $customer = Customer::where('id', $request->profile_customer_id)->first();
                $customer->firstName = $request->profile_fname;
                $customer->lastName = $request->profile_lname;
                $customer->cellphone = $request->profile_phone;
                $customer->email = $request->profile_email;
                $customer->save();
                $policy = Policy::where('customer_id', $request->profile_customer_id)->first();

                $profileCheck = CustomerProfile::where('customer_id', $request->profile_customer_id)->first();
                if ($profileCheck) {
                    $profile = CustomerProfile::where('customer_id', $request->profile_customer_id)->first();
                    $profile->dob = Carbon::parse($request->profile_dob1)->format('Y-m-d');
                    $profile->gender = $request->profile_gender;
                    // $profile->omang = $request->profile_omang;
                    // $profile->passport = $request->profile_passport;
                    $profile->maritalstatus = $request->profile_marital_status;
                    $profile->address = $request->profile_address;
                    $profile->city = $request->profile_city;
                    $profile->state = $request->profile_state;
                    $profile->save();
                } else {
                    $profile = new CustomerProfile();
                    $profile->customer_id = $request->profile_customer_id;
                    $profile->dob = Carbon::parse($request->profile_dob1)->format('Y-m-d');
                    $profile->gender = $request->profile_gender;
                    // $profile->omang = $request->profile_omang;
                    // $profile->passport = $request->profile_passport;
                    $profile->maritalstatus = $request->profile_marital_status;
                    $profile->address = $request->profile_address;
                    $profile->city = $request->profile_city;
                    $profile->save();
                }


                //sms
                $sms = new SmsMessaging();
                $sms->sendUpdatePolicy(30,$request->profilemp_phone, $request->profile_fname,$policy->policyNumber);

                //mail
                if ($request->profile_email != null) {
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->customer_id = $request->profile_customer_id;
                    $data->hook = 'update_policy';
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($request->profile_email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                  //  Mail::to($request->profile_email)->send(new MailTemplate($data));
                }



                $d = new DocumentController();
                $verificationDoc = $d->generateInformationDocument($policy->id);

                if ($verificationDoc != null) {
                    $policy->policyDocument = null;
                    $policy->verification_doc = $verificationDoc;
                    $saved = $policy->save();
                }


                if ($customer->save() && $profile->save()) {
                    return response()->json(['success' => true, 'message' => 'Customer updated successfully.'], 200);
                } else {
                    return response()->json(['success' => false, 'message' => 'Customer update failed.'], 401);
                }

            } else {
                return response()->json(['success' => false, 'message' => 'Customer not found.'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 401);
        }
    }

    public function getCustomerData(Request $request)
    {
        if ($request->customer_id != null) {

            $customerData = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                ->where('customer.id', $request->customer_id)
                ->first([
                    'customer.id',
                    'customer.firstName',
                    'customer.lastName',
                    'customer.email',
                    'customer.cellphone',
                    'customer_profile.dob',
                    'customer_profile.gender',
                    'customer_profile.maritalstatus',
                    'customer_profile.omang',
                    'customer_profile.passport',
                    'customer_profile.address',
                    'customer_profile.city',
                    'customer_profile.state',
                ]);
            $cities = City::where('state_id',$customerData->state)->get(['id','name']);
            return response()->json(['success' => true, 'customerData' => $customerData,'cities'=> $cities], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 401);
        }
    }

    public function deleteBeneficiary(Request $request)
    {
        try {
            $beneficiary = PolicyBeneficiary::where('id', $request->id)->delete();

            return response()->json(['success' => true, 'Message' => 'Beneficiary deleted succesfully'], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'Message' => 'Beneficiary delete failed'], 401);
        }
    }

    public function updateBeneficiary(Request $request)
    {
        try {

            $beneficiary = PolicyBeneficiary::where('id', $request->benefId)->first();
            if ($beneficiary != null) {
                $updateBeneficiary = PolicyBeneficiary::where('id', $request->benefId)->first();
                $updateBeneficiary->relation = $request->beneficiaryRelation;
                $updateBeneficiary->first_name = $request->beneficiaryFName;
                $updateBeneficiary->last_name = $request->beneficiaryLName;
                $updateBeneficiary->dob = Carbon::parse($request->beneficiaryDOB)->format('Y-m-d');
                $updateBeneficiary->gender = $request->beneficiaryGender;
                $updateBeneficiary->payment = $request->beneficiaryPayment;
                $updateBeneficiary->omang = $request->beneficiaryOmang;
                $updateBeneficiary->passport = $request->beneficiaryPassport;
                $updateBeneficiary->save();
                return response()->json(['success' => true, 'Message' => 'Beneficiary updated succesfully'], 200);
            } else {
                return response()->json(['success' => false, 'Message' => 'Beneficiary not found'], 401);
            }
        } catch (Exception $e) {
            return response()->json(['success' => false, 'Message' => $e->getMessage()], 401);
        }
    }

    public function updateKyc(Request $request)
    {
        if ($request->operationFor == "START") {
            if ($request->get('user_id')) {
                if ($request->hasFile('driving_license')) {
                    $file = $request->file('driving_license');
                    $result = $this->isImageValid($file);
                    if ($result == false)
                        return response()->json(['success' => false, 'Message' => 'Invalid driving license image'], 401);
                }
                if ($request->hasFile('omangFront')) {
                    $file = $request->file('omangFront');
                    $result = $this->isImageValid($file);
                    if ($result == false)
                        return response()->json(['success' => false, 'Message' => 'Invalid omang front image'], 401);
                }
                if ($request->hasFile('omangBack')) {
                    $file = $request->file('omangBack');
                    $result = $this->isImageValid($file);
                    if ($result == false)
                        return response()->json(['success' => false, 'Message' => 'Invalid omang back image'], 401);
                }
                if ($request->hasFile('proof_residence')) {
                    $file = $request->file('proof_residence');
                    $result = $this->isImageValid($file);
                    if ($result == false)
                        return response()->json(['success' => false, 'Message' => 'Invalid proof residence image'], 401);
                }
                if ($request->hasFile('proof_income')) {
                    $file = $request->file('proof_income');
                    $result = $this->isImageValid($file);
                    if ($result == false)
                        return response()->json(['success' => false, 'Message' => 'Invalid proof income image'], 401);
                }
                if ($request->hasFile('passport')) {
                    $file = $request->file('passport');
                    $result = $this->isImageValid($file);
                    if ($result == false)
                        return response()->json(['success' => false, 'Message' => 'Invalid passport image'], 401);
                }
                $check = KYC::where('customer_id', $request->get('user_id'))->first();
                if ($check) {
                    $kyc = KYC::where('customer_id', $request->get('user_id'))->first();
                } else {
                    $kyc = new KYC();
                    $kyc->customer_id = $request->get('user_id');
                }
                $kyc->omangNumber = $request->get('omangNumber');
                $kyc->passportNumber = $request->get('passportNumber');

                if ($request->get('omangExpiry') != null)
                    $kyc->omangExpiry = Carbon::createFromFormat('d/m/Y', $request->get('omangExpiry'));

                if ($request->get('passportExpiry') != null)
                    $kyc->passportExpiry = Carbon::createFromFormat('d/m/Y', $request->get('passportExpiry'));

                if ($request->get('licenseExpiry') != null)
                    $kyc->licenseExpiry = Carbon::createFromFormat('d/m/Y', $request->get('licenseExpiry'));

                if ($request->get('incomeExpiry') != null)
                    $kyc->incomeExpiry = Carbon::createFromFormat('d/m/Y', $request->get('incomeExpiry'));

                if ($request->get('residenceExpiry') != null)
                    $kyc->residenceExpiry = Carbon::createFromFormat('d/m/Y', $request->get('residenceExpiry'));

                $kyc->passportIssuingCountry = $request->get('passportIssuingCountry');
                $kyc->proof_residence_doc_type = $request->get('proof_residence_doc_type');
                $kyc->proof_income_doc_type = $request->get('proof_income_doc_type');
                if ($request->hasFile('driving_license')) {
                    $file = $request->file('driving_license');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->driving_license = $filePath;
                }
                if ($request->hasFile('omangFront')) {
                    $file = $request->file('omangFront');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->omang = $filePath;
                }
                if ($request->hasFile('omangBack')) {
                    $file = $request->file('omangBack');


                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->omangBack = $filePath;
                }
                if ($request->hasFile('proof_residence')) {
                    $file = $request->file('proof_residence');


                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->proof_residence = $filePath;
                }
                if ($request->hasFile('proof_income')) {
                    $file = $request->file('proof_income');

                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->proof_income = $filePath;
                }
                if ($request->hasFile('passport')) {
                    $file = $request->file('passport');

                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->passport = $filePath;
                }

                $kyc->compliance = 0;
                $kyc->save();

                if ($kyc->save()) {
                    $customer = DB::select('call getCustomerCelllphone(?)', [$request->get('user_id')]);
                    $cellPhone = $customer[0]->cellphone;
                    if ($cellPhone) {
                        $smsMessaging = new SmsMessaging;
                        $smsMessaging->sendKYCSMS(5, $cellPhone);
                    }
                    return redirect('https://start.alphadirect.co.bw/thankyou');
                } else {
                    return response()->json(['success' => false, 'Message' => 'something went wrong storing documents'], 401);
                }
            } else {
                return response()->json(['success' => false, 'Message' => 'User not found'], 401);
            }
        }

        //Operation to store Documents from LiveQuote
        if ($request->get('user_id')) {
            $check = KYC::where('customer_id', $request->get('user_id'))->first();
            if ($check) {
                $kyc = KYC::where('customer_id', $request->get('user_id'))->first();
            } else {
                $kyc = new KYC();
                $kyc->customer_id = $request->get('user_id');
            }

            if ($request->hasFile('driving_licenseKyc')) {
                $file = $request->file('driving_licenseKyc');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->driving_license = $filePath;
            }
            if ($request->hasFile('omangKyc')) {
                $file = $request->file('omangKyc');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/omang-front' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->omang = $filePath;
            }
            if ($request->hasFile('omangKycBack')) {
                $file = $request->file('omangKycBack');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/omang-back' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->omangBack = $filePath;
            }
            if ($request->hasFile('proof_residenceKyc')) {
                $file = $request->file('proof_residenceKyc');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->proof_residence = $filePath;
            }
            if ($request->hasFile('proof_incomeKyc')) {
                $file = $request->file('proof_incomeKyc');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->proof_income = $filePath;
            }
            if ($request->hasFile('passportKyc')) {
                $file = $request->file('passportKyc');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->passport = $filePath;
            }
            if ($request->get('omangExpiry'))
                $kyc->omangExpiry = Carbon::createFromFormat('d/m/Y', $request->get('omangExpiry'));

            if ($request->get('passportExpiry'))
                $kyc->passportExpiry = Carbon::createFromFormat('d/m/Y', $request->get('passportExpiry'));

            if ($request->get('licenseExpiry'))
                $kyc->licenseExpiry = Carbon::createFromFormat('d/m/Y', $request->get('licenseExpiry'));

            if ($request->get('incomeExpiry'))
                $kyc->incomeExpiry = Carbon::createFromFormat('d/m/Y', $request->get('incomeExpiry'));

            if ($request->get('residenceExpiry'))
                $kyc->residenceExpiry = Carbon::createFromFormat('d/m/Y', $request->get('residenceExpiry'));
            $kyc->compliance = 0;
            $kyc->save();

            $data = [
                'id'=>$kyc->id,
                'driving_license'=>$request->hasFile('driving_licenseKyc'),
                /*'driving_license_back'=>$request->hasFile('omangKyc'),*/
                'omang'=>$request->hasFile('omangKyc'),
                'omangBack'=>$request->hasFile('omangKycBack'),
                'proof_residence'=>$request->hasFile('proof_residenceKyc'),
                'passport'=>$request->hasFile('passportKyc'),
                /*'passport_back'=>$request->hasFile('omangKyc'),*/
                'proof_income'=>$request->hasFile('proof_incomeKyc'),
            ];

            $isUpdate = $this->checkKYCUpdate($data);
            if($isUpdate == 1){
                $kyc->status = 'Recheck';
                $kyc->save();
            }

            return response()->json(['success' => true, 'Message' => 'KYC information updated successfully'], 200);


        } else {
            return response()->json(['success' => false, 'Message' => 'User not found'], 401);
        }
    }

    public function checkKYCUpdate($data){
            $kyc = KYC::where('id',$data['id'])->first(['id','driving_license','omang','omangBack','proof_residence','passport','proof_income','status']);
            $isUpdate = 0;
            if($kyc != null){
                foreach($data as $key=>$d){
                    if(($kyc->status == 'Unapprove' || $kyc->status == 'rejected') && $kyc->$key != null && $d == true){
                        $isUpdate = 1;
                    }
                }
            }
            return $isUpdate;
    }

    public function verifyImage(Request $request)
    {
        $image = $request->get('image');
        $exif = exif_read_data($image, 0, true);
        if (array_key_exists('EXIF', $exif)) {
            $DateTime = \Carbon::parse($exif['EXIF']['DateTimeOriginal'])->timestamp;
            $createdDateTime = Carbon::createFromTimestamp($DateTime);
            $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
            $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
            if ($diff_in_hours > 24)
                return false;
            elseif ($diff_in_hours >= 0 && $diff_in_hours < 24)
                return true;
            else
                return false;
        } else {
            return false;
        }
    }

    public function isImageValid($image)
    {
        try {
            $data = Image::make($image)->exif();

            if ($data != null) {
                $exif = exif_read_data($image, 0, true);
                if ($exif) {
                    if (array_key_exists('EXIF', $exif) && array_key_exists('DateTimeOriginal', $exif['EXIF'])) {
                        $DateTime = Carbon::parse($exif['EXIF']['DateTimeOriginal'])->timestamp;
                        $createdDateTime = Carbon::createFromTimestamp($DateTime);
                        $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                        $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                        if ($diff_in_hours > 24)
                            return false;
                        elseif ($diff_in_hours < 24)
                            return true;
                        else
                            return false;
                    } elseif (array_key_exists('FILE', $exif)) {
                        $DateTime = $exif['FILE']['FileDateTime'];
                        $createdDateTime = Carbon::createFromTimestamp($DateTime);
                        $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                        $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                        if ($diff_in_hours > 24)
                            return false;
                        elseif ($diff_in_hours < 24)
                            return true;
                        else
                            return false;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'token'                     => 'required|string',
            'method'                    => 'required|string',
            'new_password'              => 'required|confirmed|min:6',
        ]);

        try{
            DB::beginTransaction();
            if ($request->get('method') == "reset_password") {
                if(UserPassword::where('token', $request->token)->exists())
                {
                    $user_status         = UserPassword::where('token', $request->token)->first();
                    $user_status->status = 1;
                    $user_status->token  = Str::random(8);
                    $user_status->save();

                    $customer           = Customer::where('id', $user_status->user_id)->first();
                    // ! add condition if customer is null
                    if($customer == null)
                    {
                        //redirect to error page
                    }
                    $customer->password = Hash::make($request->new_password);
                    $customer->f_login  = 1;
                    $customer->save();
                    DB::commit();
                    //sms
                    $sms = new SmsMessaging();
                    $sms= $sms->sendSmsResetPassword(27,$customer->cellphone,$customer->firstName,$customer->password);
                    //mail
                    if ($customer->email != null) {
                        $data              = new \stdClass();
                        $data->user_id     = null;
                        $data->customer_id = $customer->id;
                        $data->hook        = 'password_reset';
                        $data->attachment  = null;

                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                      //  Mail::to($customer->email)->send(new MailTemplate($data));
                    }

                }else{
                    // user not exists redirect on error page
                    dd('user not exists ');
                }
            }
            elseif ($request->filled('user_id')) {
                $customerCheck = Customer::where('id', $request->get('user_id'))->first();
                if ($request->get('method') == "update_password") {
                    if ($customerCheck->password != null) {
                        if (Hash::check($request->current_password, $customerCheck->password)) {
                            $customer = Customer::where('id', $request->get('user_id'))->first();
                            $customer->password = Hash::make($request->get('new_password'));
                            $customer->save();
                            return response()->json(['success' => true, 'Message' => 'Password updated successfully'], 200);
                        } else {
                            return response()->json(['success' => false, 'Message' => 'password do not match'], 401);
                        }
                    } else {
                        $customer = Customer::where('id', $request->get('user_id'))->first();
                        $customer->password = Hash::make($request->get('new_password'));
                        $customer->save();
                        return response()->json(['success' => true, 'Message' => 'Password updated successfully'], 200);
                    }
                }
            } else {
                return response()->json(['success' => false, 'Message' => 'User not found'], 401);
            }
        }catch(Exception $ex){
            DB::rollback();
            dd('in catch');
        }

    }

    public function getKycDetails(Request $request)
    {
        if ($request->user_id) {
            $kycDetails = KYC::where('customer_id', $request->user_id)->first();
            return response()->json(['success' => true, 'kyc' => $kycDetails], 200);
        } else {
            return response()->json(['success' => false, 'Message' => 'User not found'], 401);
        }
    }

    private function cancelPolicy($id)
    {
        if ($id) {
            $check = Policy::where('id', $id)->first(['id']);
            if ($check) {
                $policyToCancel = Policy::where('id', $id)->first(['status']);
                $policyToCancel->status = 2;
                $cancelled = $policyToCancel->save();
                if ($cancelled)
                    return response()->json(['success' => true, 'Message' => 'Policy cancelled successfully'], 200);
                else
                    return response()->json(['success' => false, 'Message' => 'Policy cancellation failed'], 401);
            } else {
                return response()->json(['success' => false, 'Message' => 'Policy not found'], 401);
            }
        } else {
            return response()->json(['success' => false, 'Message' => 'Policy not found'], 401);
        }
    }


    public function getBranchesLiveQuote(Request $request)
    {
        try {
            $branches = BankBranches::where('bank_id', $request->bank_id)->get();
            return response()->json(['code' => 200, 'branches' => $branches], 200);
        } catch (TeacherNotFoundException $e) {
            return response()->json('An error has occured with RealPay get branches', 400);
        }
    }

    public function getDataForKeyLossClaim(Request $request)
    {
        try {
            $reasons = $this->getLookUpData('key_loss_claim_reason');
            $purpose = $this->getLookUpData('vehicle_purpose');
            return response()->json(['code' => 200, 'reasons' => $reasons, 'purpose' => $purpose], 200);
        } catch (TeacherNotFoundException $e) {
            return response()->json(
                [
                    'code' => 401,
                    'message' => 'Unable to fetch required data for key loss cliam',
                    'reason' => $e
                ],
                401
            );
        }
    }

    private function getLookUpData($key)
    {
        $data = Lookup::where('key', $key)->get(['id', 'value']);
        return $data;
    }

    public function getModelYOM(Request $request)
    {
        try {
            $model = $request->get('model');
            $data = VehicleMake::where('')->first([]);
            return response()->json(['success' => 0, 'date' => $data], 401);
        } catch (Exception $ex) {
            return response()->json(['success' => 0, 'message' => $ex], 401);
        }
    }

    // public function getTruTradeValue(Request $request)
    // {
    //     //$API_CRED = base64_encode(env('TRUTRADE_KEY').':'.env('TRUTRADE_CUSTOMER_ID'));

    //     $API_CRED = base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B' . ':' . '10415');
    //     $vehicleMake =  $request->get('vehicleMake');   //required: eg. VOLVO
    //     $vehicleModel = $request->get('vehicleModel');  //required : eg. V40 T3 EXCEL

    //     $mileage = $request->get('mileage');           //optional
    //     $condition = $request->get('condition');      //optional
    //     $guide = '';                                  //optional

    //     if ($vehicleMake && $vehicleModel) {
    //         $ch = curl_init();

    //         curl_setopt($ch, CURLOPT_URL, "https://api.realintel.co.za/json/trutrade/GetModels?make=" . $vehicleMake);

    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //         $headers = array();
    //         $headers[] = "Authorization: Basic " . $API_CRED;
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //         $data = json_decode(curl_exec($ch), TRUE);

    //         if ($data) {
    //             if (array_key_exists('Variants', $data)) {
    //                 foreach ($data['Variants'] as $d) {
    //                     if ($d['Model'] == $vehicleModel) {
    //                         if ($d['VehicleCode'] && $d['IntroYear']) {
    //                             $vehicleCode = $d['VehicleCode'];

    //                             if ($request->get('manufacturing_year')) {
    //                                 $year = $request->get('manufacturing_year');
    //                             } else {
    //                                 if (strlen($d['IntroYear']) > 4)
    //                                     $yr = substr($d['IntroYear'], strlen($d['IntroYear']) - 4);
    //                                 else
    //                                     $yr = $d['IntroYear'];

    //                                 $year = $yr;
    //                             }


    //                             if ($data['TransactionSuccessful'] == true) {

    //                                 $ch = curl_init();

    //                                 curl_setopt($ch, CURLOPT_URL, "https://api.realintel.co.za/json/trutrade/GetValues?vehicle_code=" . $vehicleCode . "&year=" . $year . "&condition=" . $condition . "&mileage=" . $mileage);
    //                                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //                                 $headers = array();
    //                                 $headers[] = "Authorization: Basic " . $API_CRED;
    //                                 curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //                                 $data = json_decode(curl_exec($ch), TRUE);

    //                                 if ($data['TransactionSuccessful'] == true) {
    //                                     //$trade_value = $data['Valuation']['Trade'];
    //                                     $trade_value = $data['Valuation']['Retail'];

    //                                     if ($trade_value > 0) {
    //                                         $endpoint = 'https://api.currencylayer.com/convert';
    //                                         $access_key = '6610203a84d470aeb6bc48a4f476167c';

    //                                         /*  $client = new \GuzzleHttp\Client();
    //                                           $apiRequest = $client->request('POST', 'https://api.currencylayer.com/convert?access_key=6610203a84d470aeb6bc48a4f476167c&from=ZAR&to=BWP&amount=100');
    //                                           $response = $apiRequest->getBody()->getContents();*/

    //                                         //return $this->getConvertedValue($trade_value);
    //                                         return response()->json(['code' => 200, 'value' => $trade_value], 200);
    //                                     } elseif ($trade_value == 0) {
    //                                         return response()->json(['code' => 200, 'value' => $trade_value], 200);
    //                                     } else {
    //                                         return response()->json(['code' => 401, 'message' => 'Trade value not found.'], 401);
    //                                     }
    //                                 } else {
    //                                     return response()->json(['code' => 401, 'message' => "Failed to get value. Please provide valid vehcile code"], 401);
    //                                 }
    //                             } else {
    //                                 return response()->json(['code' => 401, 'message' => "Failed to get vehicle code"], 401);
    //                             }
    //                         } else {
    //                             return response()->json(['transactionSuccessful' => 0, 'Message' => 'Vehicle data not found.'], 401);
    //                         }
    //                     }
    //                 }
    //                 return response()->json(['transactionSuccessful' => 0, 'Message' => 'Model match not found.'], 401);
    //             } else {
    //                 return response()->json(['transactionSuccessful' => 0, 'Message' => 'Models could not be found for this make'], 401);
    //             }
    //         } else {
    //             return response()->json(['transactionSuccessful' => 0, 'Message' => 'Data not found.'], 401);
    //         }
    //     } else {
    //         return response()->json(['transactionSuccessful' => 0, 'Message' => 'Vehicle make / model not found.'], 401);
    //     }
    // }
    public function getTruTradeValue(Request $request)
    {
        try { 
            $API_CRED = base64_encode('6594a578-1088-4794-a6ba-450b0f4ce5f6' . ':' . 'fa1808e3-360c-4c09-9f71-506d9a207ae8');
            $make = trim($request->get('vehicleMake'));               
            $model = trim($request->get('vehicleModel'));
            $variant1 = trim($request->get('variant'));       
            $vehicleMake = rawurlencode($make);   
            $vehicleModel = rawurlencode($model);  
            $variant = rawurlencode($variant1);  
            $manufacturing_year =$request->get('manufacturing_year');
           
           if($vehicleMake && $vehicleModel && $manufacturing_year && $variant){
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.yourvehiclevalue.co.za/api/insight/".$vehicleMake."/".$vehicleModel."/".$variant."/".$manufacturing_year);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $headers = array();
                $headers[] = "Authorization: Basic " . $API_CRED;
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $data = json_decode(curl_exec($ch), TRUE);

                if ($data) {
                    
                    $trade_value = $data['truetrade_retailPrice'];

                    if ($trade_value > 0) {
                        return response()->json(['code' => 200, 'value' => $trade_value], 200);
                    } elseif ($trade_value == 0) {
                        return response()->json(['code' => 200, 'value' => $trade_value], 200);
                    } else {
                        return response()->json(['code' => 401, 'message' => 'Trade value not found.'], 401);
                    }
                } else {
                    return response()->json(['code' => 401, 'message' => "Failed to get value. Please provide valid vehcile code"], 401);
                }
            }else{
                return response()->json(['code' => 401, 'message' => "Failed to get value. Please provide valid vehcile code"], 401);
            
            }
        } catch (Exception $ex) {
            return response()->json(['success' => 0, 'message' => $ex], 401);
        }
        
    }

    public function getLeftOutPaymentDate()
    {
        $current_timestamp = Carbon::now()->timestamp;
        $currTime = date("H", $current_timestamp);
        if ($currTime >= 00 && $currTime < 13)
            return Carbon::now()->toDateString();
        else
            return Carbon::now()->addDay(1)->toDateString();
    }

    public function getConvertedValue($trade_value)
    {
        $endpoint = 'https://api.currencylayer.com/convert';
        $access_key = '6610203a84d470aeb6bc48a4f476167c';

        $ch = curl_init($endpoint . '?access_key=' . $access_key . '&from=ZAR&to=BWP&amount=' . $trade_value);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $json = curl_exec($ch);
        $exchangeRates = json_decode($json, true);

        curl_close($ch);

        $error = json_last_error();
        $errorMsg = json_last_error_msg();

        if ($error == 0) {
            //$value = $exchangeRates['result'] * 1.12;
            $value = $exchangeRates['result'];
            if ($value > 0) {
                return response()->json(['transaction' => 1, 'value' => $value], 200);
            } else {
                return response()->json(['transaction' => 0, 'data' => 'Unable to fetch vehicle value'], 401);
            }
        } else {
            return response()->json(['transaction' => 0, 'data' => 'Could not fetch data. JSON error code (' . $error . ')-' . $errorMsg], 401);
        }
    }

    public function getCellPhoneNumberUsingCustomerID(Request $request)
    {
        try {
            if ($request->get('user_id')) {
                $customer = Customer::where('id', $request->get('user_id'))->first();
                if ($customer && $customer->cellphone)
                    return response()->json(['successfull' => 1, 'cellphone' => $customer->cellphone], 200);
                else
                    return response()->json(['successfull' => 0, 'Message' => 'Cellphone not found'], 401);
            } else {
                return response()->json(['successfull' => 0, 'Message' => 'User ID not found'], 401);
            }
        } catch (Exception $ex) {
            return response()->json(['successfull' => 0, 'Message' => $ex], 401);
        }
    }

    private function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            // 16 bits for "time_mid"
            Helper::gen_ustring(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            Helper::gen_ustring(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            Helper::gen_ustring(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff)
        );
    }
    public function getinsuredData(Request $request)
    {
        if ($request->customerid != null) {
            $customerDetail = Customer::where('id', $request->customerid)->first();
            $profile = CustomerProfile::where('customer_id', $request->customerid)->first();
            return response()->json(['success' => true, 'insuredata' => $profile, 'Cdetail' => $customerDetail], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 401);
        }
    }

    public function getCustomerCellphoneByPolicyNumber(Request $request)
    {
        try {
            $info = Policy::join('customer', 'policies.customer_id', 'customer.id')
                ->where('policies.policyNumber', $request->policyNumber)
                ->first(array('customer.id', 'customer.cellphone', 'policies.policyNumber'));
            if ($info) {
                if ($info->cellphone) {
                    return response()->json(['success' => true, 'data' => $info], 200);
                } else {
                    return response()->json(['success' => false, 'Message' => 'Cellphone number not found related to policy number ' . $request->policyNumber], 401);
                }
            } else {
                return response()->json(['success' => false, 'Message' => 'No Policy Found with policy number ' . $request->policyNumber], 401);
            }
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'Message' => $exception], 401);
        }
    }

    // public function getTruTradeVehicles()
    // {
    //     try {
    //         $purpose = Lookup::where('key', 'vehicle_purpose')->where('status', 1)->get(array('id', 'key', 'value'));

    //         $API_CRED = base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B' . ':' . '10415');
    //         $ch = curl_init();

    //         curl_setopt($ch, CURLOPT_URL, "https://api.realintel.co.za/json/trutrade/GetMakes");

    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //         $headers = array();
    //         $headers[] = "Authorization: Basic " . $API_CRED;
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //         $data = json_decode(curl_exec($ch), TRUE);
    //         if ($data['TransactionSuccessful'] == true) {
    //             $makesData = $data['Makes'];
    //             $Makes = array();
    //             foreach ($makesData as $make) {
    //                 if ($make['Make'])
    //                     array_push($Makes, $make['Make']);
    //             }
    //             return response()->json(['success' => true, 'Makes' => $Makes, 'Purpose' => $purpose], 200);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'Message' => $e], 401);
    //     }
    // }
    public function getTruTradeVehicles()
    {
        try {
            $purpose = Lookup::where('key', 'vehicle_purpose')->where('status', 1)->get(array('id', 'key', 'value'));

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
                return response()->json([
                    'success' => true,
                    'Makes' => $data,
                    'Purpose' => $purpose
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data from API',
                    'status_code' => $httpCode
                ], $httpCode);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'Message' => $e], 401);
        }
    }

    // public function getTTVehicleModels(Request $request)
    // {

    //     try {
    //         $make = str_replace(' ', '+', trim($request->get('make'), " "));
    //         $year = $request->get('manufacturing_year');
    //         if ($make) {
    //             $API_CRED = base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B' . ':' . '10415');
    //             $ch = curl_init();
    //             curl_setopt($ch, CURLOPT_URL,  "https://api.realintel.co.za/json/trutrade/GetModels?make=" . $make . "&year=" . $year);

    //             curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //             $headers = array();
    //             $headers[] = "Authorization: Basic " . $API_CRED;
    //             curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //             $data = json_decode(curl_exec($ch), TRUE);
    //             if ($data['TransactionSuccessful'] == true) {
    //                 $modelData = $data['Variants'];
    //                 return response()->json(['success' => true, 'Models' => $modelData], 200);
    //             } else {
    //                 return response()->json(['success' => false, 'Message' => 'No model found for this make'], 401);
    //             }
    //         } else {
    //             return response()->json(['success' => false, 'Message' => 'Vehicle make is required'], 401);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'Message' => $e], 401);
    //     }
    // }
    public function getTTVehicleModels(Request $request)
    {

        try {
            $make = str_replace(' ', '+', trim($request->get('make'), " "));
            //$year = $request->get('manufacturing_year');
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
              
                $formatted = [
                    'success' => true,
                    'Models' => [],
                ];
                
                    foreach($data as $m){
                        $formatted['Models'][] = [
                            'Model' => $m,
                            'IntroYear' => null,
                            'DisconYear' => null
                        ];

                    }
                   if ($httpCode == 200) {
                        return response()->json($formatted);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to fetch data from API',
                            'status_code' => $httpCode
                        ], $httpCode);
                    }
               
            } else {
                return response()->json(['success' => false, 'Message' => 'Vehicle make is required'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'Message' => $e], 401);
        }
    }
    public function getTTVehicleYear(Request $request)
    {
        $make = trim($request->get('make'));               
        $model = trim($request->get('vehicleModel'));     
        
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
                return response()->json([
                    'success' => true,
                    'year' => $data,
                ]);

            } elseif ($httpCode == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No response from API (possibly a connection issue).',
                    'status_code' => 500
                ], 500);
            
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data from API',
                    'status_code' => $httpCode
                ], $httpCode);
            }
        
    
        return response()->json(['success' => false, 'message' => 'Vehicle make and model are required'], 422);
    }
    
    public function getTTVehicleVariant(Request $request)
    {

        try {
            $make = trim($request->get('make'));               
            $model = trim($request->get('vehicleModel'));     
            $encodedMake = rawurlencode($make);   
            $encodedModel = rawurlencode($model);  
            $year = $request->get('manufacturing_year');
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
              
                $formatted = [
                    'success' => true,
                    'variant' => $data,
                ];
                
                   
                if ($httpCode == 200) {
                    return response()->json($formatted);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch data from API',
                        'status_code' => $httpCode
                    ], $httpCode);
                }
               
            } else {
                return response()->json(['success' => false, 'Message' => 'Vehicle make/model/year is required'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'Message' => $e], 401);
        }
    }

    public function agentAppUploadKyc(Request $request)
    {

        $name = $request->omangId != null ? $request->omangId : $request->passportId;

        $passportNumber = $request->passportId;
        $omangNumber = $request->omangId;

        $AgentKyc = new AgentKyc();
        $AgentKyc->omangNumber = $request->omangId;
        $AgentKyc->agentId = $request->agentId;
        $AgentKyc->passportNumber = $request->passportId;
        $AgentKyc->passportIssuingCountry = isset($request->passportIssuingCountry) && $request->passportIssuingCountry != "" ? $request->passportIssuingCountry : "";
        $AgentKyc->proof_residence_doc_type = isset($request->proof_residence_doc_type) && $request->proof_residence_doc_type != "" ? $request->proof_residence_doc_type : "";
        $AgentKyc->proof_income_doc_type = isset($request->proof_income_doc_type) && $request->proof_income_doc_type != "" ? $request->proof_income_doc_type : "";
        $AgentKyc->omangExpiry = isset($request->omangExpiry) && $request->omangExpiry != "" ? $request->omangExpiry : "";
        $AgentKyc->passportExpiry = isset($request->passportExpiry) && $request->passportExpiry != "" ? $request->passportExpiry : "";
        $AgentKyc->licenseExpiry = isset($request->licenseExpiry) && $request->licenseExpiry != "" ? $request->licenseExpiry : "";

        /* foreach ($request->kyc as $key => $value) {

             switch ($key) {
                 case 'omang':
                     $imageName = $this->generateFileName($name, $value);
                     $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                     $AgentKyc->omang = $path;

                     break;

                 case 'omangBack':
                     $imageName = $this->generateFileName($name, $value);
                     $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                     $AgentKyc->omangBack = $path;

                     break;

                 case 'proofResidence':
                     $imageName = $this->generateFileName($name, $value);
                     $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                     $AgentKyc->proofResidence = $path;

                     break;


                 case 'driversLicense':
                     $imageName = $this->generateFileName($name, $value);
                     $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                     $AgentKyc->driversLicense = $path;

                     break;


                 case 'proofIncome':
                     $imageName = $this->generateFileName($name, $value);
                     $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                     $AgentKyc->proofIncome = $path;

                     break;


                 case 'passport':
                     $imageName = $this->generateFileName($name, $value);
                     $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                     $AgentKyc->passport = $path;

                     break;


                 case 'default':
                     # code...
                     break;
             }
         }*/


        $AgentKyc->compliance = 0;

        $saveStatus = $AgentKyc->save();

        $customer = CustomerProfile::where('omang', $name)->orWhere('passport', $name)->first();
        /*if ($customer != null) {
            $kyc = KYC::where('customer_id', $customer->customer_id)->first();
            if ($kyc != null) {
                $kyc->omang = $AgentKyc->omang;
                $kyc->proof_residence = $AgentKyc->proofResidence;
                $kyc->driving_license = $AgentKyc->driversLicense;
                $kyc->proof_income =  $AgentKyc->proofIncome;
                $kyc->passport = $AgentKyc->passport;
                $kyc->save(); //save customer_kyc



            }
        }*/

        if ($customer) {
            $kyc = KYC::where('customer_id', $customer->customer_id)->first();
        } else {
            $kyc = new KYC();
        }

        $kyc->omangNumber = $AgentKyc->omangNumber;
        $kyc->passportNumber = $passportNumber; //$request->get('');

        if ($AgentKyc->omangExpiry)
            $kyc->omangExpiry = $AgentKyc->omangExpiry;

        if ($AgentKyc->passportExpiry)
            $kyc->passportExpiry = $AgentKyc->passportExpiry;

        if ($AgentKyc->licenseExpiry)
            $kyc->licenseExpiry = $AgentKyc->licenseExpiry;

        if ($AgentKyc->passportIssuingCountry)
            $kyc->passportIssuingCountry = $AgentKyc->passportIssuingCountry;

        if ($AgentKyc->proof_residence_doc_type)
            $kyc->proof_residence_doc_type = $AgentKyc->proof_residence_doc_type;

        if ($AgentKyc->proof_income_doc_type)
            $kyc->proof_income_doc_type = $AgentKyc->proof_income_doc_type;

        if ($AgentKyc->driversLicense)
            $kyc->driving_license = $AgentKyc->driversLicense;

        if ($AgentKyc->omang)
            $kyc->omang = $AgentKyc->omang;

        if ($AgentKyc->omangBack)
            $kyc->omang = $AgentKyc->omangBack;

        if ($AgentKyc->omangNumber)
            $kyc->omangNumber = $omangNumber;

        if ($AgentKyc->proofResidence)
            $kyc->proof_residence = $AgentKyc->proofResidence;

        if ($AgentKyc->passport)
            $kyc->passport = $AgentKyc->passport;

        if ($AgentKyc->passport)
            $kyc->passport = $AgentKyc->passport;

        $kyc->compliance = $AgentKyc->compliance;

        $save = $kyc->save();

        if ($saveStatus == true) {

            return response()->json(['title' => 'Successful', 'description' => 'Customer KYC photos have been successfully uploaded'], 200);
        } else {

            return response()->json(['title' => 'Failed', 'description' => 'Customer KYC photos have not been uploaded'], 401);
        }
    }

    public function setVehiclePopularity($id)
    {
        $policy = Policy::where('id', $id)->first(array('has_vehicle'));
        if ($policy->has_vehicle == 1) {
            $vehicle = Vehicle::where('policy_id', $id)->first(array('make'));
            if ($vehicle != null) {
                $makeCount = Vehicle::where('make', $vehicle->make)->count();
                $totalCount = Vehicle::where('make', '!=', null)->get(array('id'));
                $vehiclePopularity = VehiclePopularity::where('make', $vehicle->make)->first();
                if ($vehiclePopularity != null) {
                    $vehiclePopularity->popularity = $makeCount;
                    $vehiclePopularity->save();
                } else {
                    $setPopularity = new VehiclePopularity();
                    $setPopularity->popularity = $makeCount;
                    $setPopularity->save();
                }
            }
        }
    }

    public function getPolicyVehicle($id)
    {
        try {
            if ($id != null) {
                $vehicle = Vehicle::where('policy_id', $id)->first();
                if ($vehicle) {
                    return response()->json(['success' => true, 'vehicleFound' => true, 'vehicleInfo' => $vehicle], 200);
                } else {
                    return response()->json(['success' => true, 'vehicleFound' => false, 'vehicleInfo' => 'No vehicle found for policy ' . $id], 200);
                }
            } else {

                return response()->json(['success' => false, 'vehicleFound' => false, 'vehicleInfo' => 'Policy id not found'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'exception' => $e], $e->getCode());
        }
    }

    public function actionAfterPolicyCreateFromQuote(Request $request)
    {
        try {

            $id = $request->get('policy_id');

            if ($id != null) {
                $policy = Policy::where('id', $id)->first();
                $product = Product::where('id', $policy->product_id)->first(array('has_wordings', 'has_schedule'));
                $document = new DocumentController();

                if ($product->has_schedule == 1)
                    $generatePolicyDoc =  $document->generatePolicyDocument($policy->id);

                $sent = $document->sendPolicyDocument($policy->id);
                $setVehiclePopularity = $this->setVehiclePopularity($id);
            }
        } catch (\Exception $e) {
            echo $e;
        }
    }

    public function checkVehicleExist(Request $request)
    {
        try {
            if ($request->vehiclePlate != null) {
                $checkVehicle = Vehicle::where('vehiclePlate', $request->vehiclePlate)->get(array('policy_id', 'vehiclePlate'));
                $vehicleCount = count($checkVehicle);
                
                if ($vehicleCount > 0 && $vehicleCount != null) {
                    foreach ($checkVehicle as $key => $vehicle) {
                        $policy = Policy::where('id', $vehicle->policy_id)->first(array('status'));
                        if ($policy && ($policy->status == 1 || $policy->status == 0)) {
                            return response()->json([
                                'success' => false, 'message' => "Vehicle associated with existing policy"
                            ], 401);
                        }
                    }
                }
                return response()->json([
                    'success' => true, 'message' => "Vehicle not associated with any policy"
                ], 200);
            } else {
                return response()->json([
                    'success' => false, 'message' => "Vehicle plate is null"
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false, 'message' => $e->getMessage()
            ], 401);
        }
    }



    public function check_imei_number(Request $request)
    {
        // Check if IMEI is present
        if (!$request->imei) {
            return response()->json(['message' => 'IMEI number is required'], 401);
        }

        // Find all policy cell phones with the provided IMEI
        $imeiNos = PolicyCellPhone::where('imei', $request->imei)->get();

        // If no such IMEI exists, return response
        if ($imeiNos->isEmpty()) {
            return response()->json([
            'message' => 'IMEI number is available'
        ], 200);
        }

        $policyCount = 0;

        foreach ($imeiNos as $imei) {
            $policy = Policy::find($imei->policy_id);
            if ($policy && $policy->status !== null && $policy->status != 2) {
                $policyCount++;
            }
        }

        if ($policyCount > 0) {
            return response()->json([
                'count' => $policyCount,
                'message' => 'IMEI number is already registered with another policy'
            ], 401);
        }

        // If no conflicts found
        return response()->json([
            'message' => 'IMEI number is available'
        ], 200);
    }


    public function actionAfterPolicyCreateFromQuoteRealPay($policyId)
    {
        try {
            $id = $policyId;

            if ($id != null) {
                $policy = Policy::where('id', $id)->first();
                $product = Product::where('id', $policy->product_id)->first(array('has_wordings', 'has_schedule'));
                $document = new DocumentController();

                if ($product->has_schedule == 1)
                    $generatePolicyDoc =  $document->generatePolicyDocument($policy->id);

                $sent = $document->sendPolicyDocument($policy->id);
                //$setVehiclePopularity = $this->setVehiclePopularity($id);
            }
        } catch (\Exception $e) {
            echo $e;
        }
    }

    public function getvehicleList(Request $request)
    {
        $vehicles = Vehicle::leftJoin('policies', 'policies.id', '=', 'vehicle.policy_id')
            ->where('vehicle.customer_id', $request->customer_id)
            ->orderBy('policies.id', 'DESC')
            ->get([
                'vehicle.id',
                'vehicle.front',
                'vehicle.back',
                'vehicle.right',
                'vehicle.left',
                'vehicle.vehicleRegistration',
                'vehicle.make',
                'vehicle.model',
                'vehicle.engineNo',
                'vehicle.vehiclePlate',
                'vehicle.front_upload_date',
                'vehicle.back_upload_date',
                'vehicle.right_upload_date',
                'vehicle.left_upload_date',
                'vehicle.vehicleRegistration_upload_date',
                'vehicle.compliance',
                'vehicle.vinnumber',
                'vehicle.left',
                'policies.policyNumber',
            ]);
        $count = count($vehicles);
        if ($count > 0) {
            return response()->json([
                'success' => true, 'message' => "Vehicles found for the user", 'vehicles' => $vehicles
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No vehicle found for the user associated with policy."
            ], 401);
        }
    }
    public function getvehicleInfo(Request $request)
    {
        $vehicles = Vehicle::leftJoin('policies', 'policies.id', '=', 'vehicle.policy_id')
            ->where('vehicle.id', $request->vehicle_data_id)
            ->orderBy('policies.id', 'DESC')
            ->first([
                'vehicle.id',
                'vehicle.customer_id',
                'vehicle.front',
                'vehicle.back',
                'vehicle.right',
                'vehicle.left',
                'vehicle.vehicleRegistration',
                'vehicle.make',
                'vehicle.model',
                'vehicle.engineNo',
                'vehicle.vehiclePlate',
                'vehicle.front_upload_date',
                'vehicle.back_upload_date',
                'vehicle.right_upload_date',
                'vehicle.left_upload_date',
                'vehicle.vehicleRegistration_upload_date',
                'vehicle.compliance',
                'vehicle.vinnumber',
                'vehicle.left',
                'policies.policyNumber',
            ]);

        if ($vehicles) {
            return response()->json([
                'success' => true, 'message' => "Vehicles found for the user", 'vehicles' => $vehicles
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No vehicle found for the user associated with policy."
            ], 401);
        }
    }
    public function getvehicleInfo2(Request $request)
    {

        $vehicles = Vehicle::leftJoin('policies', 'policies.id', '=', 'vehicle.policy_id')
            ->where('vehicle.id', $request->id)
            ->orderBy('policies.id', 'DESC')
            ->first([
                'vehicle.id',
                'vehicle.customer_id',
                'vehicle.front',
                'vehicle.back',
                'vehicle.right',
                'vehicle.left',
                'vehicle.vehicleRegistration',
                'vehicle.make',
                'vehicle.model',
                'vehicle.engineNo',
                'vehicle.vehiclePlate',
                'vehicle.front_upload_date',
                'vehicle.back_upload_date',
                'vehicle.right_upload_date',
                'vehicle.left_upload_date',
                'vehicle.vehicleRegistration_upload_date',
                'vehicle.compliance',
                'vehicle.vinnumber',
                'vehicle.left',
                'policies.policyNumber',
            ]);

        if ($vehicles) {
            return response()->json([
                'success' => true, 'message' => "Vehicles found for the user", 'vehicles' => $vehicles
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No vehicle found for the user associated with policy."
            ], 401);
        }
    }


    public function updateVehicleInfo(Request $request)
    {
        try {
            if ($request->hasFile('front')) {
                $file = $request->file('front');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'Message' => 'The photo of the Front of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }
            if ($request->hasFile('back')) {
                $file = $request->file('back');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'Message' => 'The photo of the rear (back) of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }
            if ($request->hasFile('right')) {
                $file = $request->file('right');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'Message' => 'The photo of the right of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }
            if ($request->hasFile('left')) {
                $file = $request->file('left');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'Message' => 'The photo of the left of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }

            if ($request->id != null) {
                $data = Vehicle::where('id', $request->id)->first();
                $data->engineNo = $request->engineNumber;
                $data->vinnumber = $request->vinNumber;
                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $data->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $data->front = $filePath;
                        $data->front_upload_date =  Carbon::now()->format('Y-m-d');
                    } else {
                        return response()->json(['success' => false, 'Message' => 'Front image is invalid'], 401);
                    }
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $data->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $data->back = $filePath;
                        $data->back_upload_date =  Carbon::now()->format('Y-m-d');
                    } else {
                        return response()->json(['success' => false, 'Message' => 'Back image is invalid'], 401);
                    }
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $data->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $data->right = $filePath;
                        $data->right_upload_date = Carbon::now()->format('Y-m-d');
                    } else {
                        return response()->json(['success' => false, 'Message' => 'Right image is invalid'], 401);
                    }
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');

                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $data->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $data->left = $filePath;
                        $data->left_upload_date =  Carbon::now()->format('Y-m-d');
                    } else {
                        return response()->json(['success' => false, 'Message' => 'Left image is invalid'], 401);
                    }
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    //$result = $this->isImageValid($file);
                    //                    if ($result) {
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $data->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $data->vehicleRegistration = $filePath;
                    $data->vehicleRegistration_upload_date =  Carbon::now()->format('Y-m-d');
                    //                    } else {
                    //                        return response()->json(['success' => false, 'Message' => 'Vehicle registration image is invalid'], 401);
                    //                    }

                }

                $data->compliance = 0;
                if($data->status == 2)
                    $data->status = 3;

                $data->save();
            } else {
                return response()->json([
                    'success' => false, 'message' => 'Data id not found',
                ], 401);
            }
            return response()->json([
                'success' => true, 'message' => 'Vehicle information updated successfully',
            ], 200);
        } catch (Exception $ex) {
            return response()->json([
                'success' => false, 'message' => $ex->getMessage()
            ], $ex->getMessage());
        }
    }

    public function cancelRealPayContract(Request $request)
    {
        try {
            $banking = CustomerBanking::where('policy_id', $request->policy_id)->first();

            $RealPayController = new RealPayController();
            $installments = $RealPayController->getInstallments($banking);

            //            $is_merged = $banking->merge_ref;
            //
            //            if ($is_merged){
            //                $data = Policy::where('policyNumber', $banking->client_number)->first();
            //                if($data)
            //                    $amountToDeduct = $data->premium + $data->vat;
            //            }


            if ($installments != null) {
                foreach ($installments as $i) {
                    $xml = '';
                    $xml .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                      <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                         <ns1:editInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pRequestdata>
                                <ns1:installmentReferenceNumber>' . $i['refNum'] . '</ns1:installmentReferenceNumber>
                                <ns1:ctcAmount></ns1:ctcAmount>
                                <ns1:actionDate></ns1:actionDate>
                                <ns1:tracking></ns1:tracking>';
                    //                    if ($is_merged) {
                    //                        $xml .=         '<ns1:status></ns1:status>
                    //                                <ns1:installmentAmount>' . $i['totalInstallmentAmount'] - $amountToDeduct . '</ns1:installmentAmount>';
                    //                    } else {
                    $xml .=         '<ns1:status>I</ns1:status>
                                <ns1:installmentAmount></ns1:installmentAmount>';
                    //}
                    $xml .=         '</ns1:pRequestdata>
                            </ns1:editInstallmentsElement>
                        </soap:Body>
                        </soap:Envelope>';

                    $options = [
                        'headers' => [
                            'Content-Type' => 'application/soap+xml',
                        ],
                        'body' => $xml,
                    ];

                    $client = new \GuzzleHttp\Client();
                    $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
                }
                $response = $apiRequest->getBody()->getContents();
            }
        } catch (Exception $ex) {
            return response()->json([
                'success' => false, 'message' => $ex->getMessage()
            ], $ex->getCode());
        }
    }

    public function getInstallments()
    {
        $requests = RealpayCancelRequests::where('cancel_status', 0)->get();
        if ($requests != null) {
            foreach ($requests as $req) {

                $banking = CustomerBanking::where('policy_id', $req->policy_id)->first();

                $RealPayController = new RealPayController();
                $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                    <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                        <ns1:getInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pClientNumber>MIS2020001206</ns1:pClientNumber>
                        <ns1:pContractNumber>1601302427</ns1:pContractNumber>
                    <ns1:pInstallmentReferenceNumber></ns1:pInstallmentReferenceNumber>
                    </ns1:getInstallmentsElement>
                    </soap:Body>
                </soap:Envelope>';

                $options = [
                    'headers' => [
                        'Content-Type' => 'application/soap+xml',
                    ],
                    'body' => $xml,
                ];

                $client = new \GuzzleHttp\Client();
                $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
                $response = $apiRequest->getBody()->getContents();
                $xml     = simplexml_load_string($response);

                $output = array();
                foreach ($xml->xpath('//env:Envelope/env:Body/ns0:getInstallmentsResponseElement/ns0:result/ns0:presponsedataOut') as $key => $header) {
                    $output[$key]['clientNumber'] = $header->xpath('ns0:clientNumber')[0][0];
                    $output[$key]['refNum'] = $header->xpath('ns0:installmentReferenceNumber')[0][0];
                    $output[$key]['totalInstallmentAmount'] = $header->xpath('ns0:totalInstallmentAmount')[0][0];
                    $output[$key]['actionDate'] = $header->xpath('ns0:actionDate')[0][0];
                    $output[$key]['status'] = $header->xpath('ns0:status')[0][0];
                }

                $json = json_encode($output);
                $array = json_decode($json, TRUE);

                /*added*/
                $data = array();
                $incI = 0;
                foreach ($array as $arrKey => $arrData) {

                    $data[$incI]['clientNumber'] = $arrData['clientNumber'][0];
                    $data[$incI]['refNum'] = $arrData['refNum'][0];
                    $data[$incI]['totalInstallmentAmount'] = $arrData['totalInstallmentAmount'][0];
                    $data[$incI]['actionDate'] = $arrData['actionDate'][0];
                    $data[$incI]['status'] = $arrData['status'][0];
                    $incI++;
                }

                $activeInstallments = array();

                foreach ($data as $d) {
                    if ($d['status'][0] == 'A') {
                        array_push($activeInstallments, $d);
                    }
                }
                return $activeInstallments;
            }
        }
    }

    public function checkAgent(Request $request)
    {
        try {
            if ($request->get('agentId')) {
                $data = User::where('id', $request->get('agentId'))
                    ->first(array('id', 'firstName', 'lastName','bypass_500k','active','agency_id'));

                $setting = QuoteSettings::first();

                if($setting && $setting->sum_assured_limit != null){
                    $maxValue = $setting->sum_assured_limit;
                }else{
                    $maxValue = 3000000;
                }

                if ($data != null) {
                    if($data->active == 1){
                    return response()->json([
                        'success' => true, 'message' => "You will be assisted by " . $data->firstName . ' ' . $data->lastName,
                        'bypass_validation'=>$data->bypass_500k,
                        'max_sum_assured'=>$maxValue,
                        'agency_id' =>$data->agency_id
                    ], 200);
                }else if($data->active == 2){
                    return response()->json([
                        'success' => false, 'message' => "Agent Suspended with ID: " . $request->get('agentId')
                    ], 401);
                }else{
                    return response()->json([
                        'success' => false, 'message' => "No agent found with ID: " . $request->get('agentId')
                    ], 401);
                }
                } else {
                    return response()->json([
                        'success' => false, 'message' => "No agent found with ID: " . $request->get('agentId')
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false, 'message' => "Please provide agent ID"
                ], 401);
            }
        } catch (Exception $ex) {
            return response()->json([
                'success' => false, 'message' => $ex->getMessage()
            ], $ex->getCode());
        }
    }
    public function checkAgent2(Request $request)
    {
        try {
            if ($request->get('agentId')) {
                $data = User::where('id', $request->get('agentId'))
                    ->first(array('id', 'firstName', 'lastName','bypass_500k','active','pin','agency_id'));
                if(isset($data) && $data->pin != null && $data->pin == $request->get('pin')){


                $setting = QuoteSettings::first();

                if($setting && $setting->sum_assured_limit != null){
                    $maxValue = $setting->sum_assured_limit;
                }else{
                    $maxValue = 3000000;
                }

                if ($data != null) {
                    if($data->active == 1){
                    return response()->json([
                        'success' => true,  'message' => "You will be assisted by " . $data->firstName . ' ' . $data->lastName,
                        'bypass_validation'=>$data->bypass_500k,
                        'max_sum_assured'=>$maxValue,
                        'agency_id' =>$data->agency_id 
                       
                    ], 200);
                }else if($data->active == 2){
                    return response()->json([
                        'success' => false, 'errortype'=>'agent',  'message' => "Agent Suspended with ID: " . $request->get('agentId')
                    ], 401);
                }else{
                    return response()->json([
                        'success' => false, 'errortype'=>'agent', 'message' => "No agent found with ID: " . $request->get('agentId')
                    ], 401);
                }
                } else {
                    return response()->json([
                        'success' => false,  'errortype'=>'agent', 'message' => "No agent found with ID: " . $request->get('agentId')
                    ], 401);
                }
            }else{
                return response()->json([
                    'success' => false, 'errortype'=>'pin','message' => "Please provide valid agent Pin"
                ], 401);
            }
            } else {
                return response()->json([
                    'success' => false, 'errortype'=>'agent', 'message' => "Please provide valid agent ID"
                ], 401);
            }
        } catch (Exception $ex) {
            return response()->json([
                'success' => false, 'message' => $ex->getMessage()
            ], $ex->getCode());
        }
    }

    public function getAllProducts()
    {
        try {
            $products = Product::where('status', 1)->get();
            return response()->json([
                'success' => True,
                'Products' => $products
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => 'Failed',
                'Message' => $e->getMessage(),
            ], 401);
        }
    }

    public function getDeviceBrandModels(Request $request)
    {
        $brand = $request->brand;
        $models = DeviceMakeModel::where('make_id', $brand)->get(array('id', 'name'))->sortBy('name')->values();

        if ($models != NULL) {
            return response()->json(['SUCCESS' => 1, 'Models' => $models], 200);
        } else {
            return response()->json(['SUCCESS' => 0, 'Models' => $models], 401);
        }
    }

    public function getDeviceBrands(Request $request)
    {
        try {
            $devices = array();
            if ($request->deviceType) {
                $brands = DeviceMakeModel::where('make_id',null)
                    ->where('device_type' , '=',$request->deviceType)
                    ->get(array('id', 'name','device_type'))->sortBy('name')->values();
                if ($brands != NULL) {
                    return response()->json(['SUCCESS' => 1, 'Brands' => $brands], 200);
                } else {
                    return response()->json(['SUCCESS' => 0, 'Brands' => 0], 401);
                }
            }else{
                return response()->json(['SUCCESS' => 0, 'Brands' => 'Device Type Not Found'], 401);
            }
            /* if ($request->deviceType != null) {
                 if ($request->deviceType == "Laptop") {
                     $brands = DeviceMakeModel::where('make_id',null)
                         ->where('device_type' , '=', 'Laptop' )
                         ->get(array('id', 'name','device_type'))->sortBy('name');;
                     if ($brands != NULL) {
                         return response()->json(['SUCCESS' => 1, 'Brands' => $brands], 200);
                     } else {
                         return response()->json(['SUCCESS' => 0, 'Brands' => 0], 401);
                     }
                 } elseif ($request->deviceType == "Tablet") {
                     $brands = DeviceMakeModel::where('device_type', 'Tablet')
                         ->where('make_id',null)
                         ->get(array('id', 'name'))->sortBy('name');;
                     if ($brands != NULL) {
                         return response()->json(['SUCCESS' => 1, 'Brands' => $brands], 200);
                     } else {
                         return response()->json(['SUCCESS' => 0, 'Brands' => 0], 401);
                     }
                 } elseif ($request->deviceType == "Cellphone") {
                     $brands = DeviceMakeModel::where('device_type', 'Cellphone')
                         ->where('make_id','=',null)
                         ->get(array('id', 'name'))
                         ->sortBy('name');
                     if ($brands != NULL) {
                         return response()->json(['SUCCESS' => 1, 'Brands' => $brands], 200);
                     } else {
                         return response()->json(['SUCCESS' => 0, 'Brands' => 0], 401);
                     }
                 } else {
                     return response()->json(['SUCCESS' => 0, 'Brands' => 'Not found'], 401);
                 }
             } else {
                 return response()->json(['SUCCESS' => 0, 'Brands' => 'Device Type Not Found'], 401);
             }*/
        } catch (Exception $e) {
            return response()->json([
                'SUCCESS' => '0',
                'Message' => $e->getMessage(),
            ], 401);
        }
    }

    public function checkAgentWhatsapp(Request $request)
    {
        try {
            if ($request->get('agentId')) {
                $data = User::where('id', $request->get('agentId'))
                    ->first(array('id', 'firstName', 'lastName'));
                if ($data != null) {
                    return response()->json([
                        'success' => true, 'message' => $data->firstName . ' ' . $data->lastName
                    ], 200);
                } else {
                    return response()->json([
                        'success' => false, 'message' => null
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false, 'message' => "Please provide agent ID"
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['SUCCESS' => 0, 'message' => $e->getMessage()], 401);
        }
    }

    public function getCellphoneClaim(Request $request)
    {
        $policy_cellphone = PolicyCellPhone::where('policy_id', $request->get('policy_id'))->get();
        return response()->json(
            array(
                'success' => 1,
                'policy_cellphone' => $policy_cellphone
            ),
            200
        );

    }

    public function tqcheckVehiclePlate(Request $request)
    {
        try {
            if ($request->vehiclePlate != null) {
                $checkVehicle = Vehicle::where('vehiclePlate', $request->vehiclePlate)->get(array('policy_id', 'vehiclePlate'));
                $vehicleCount = count($checkVehicle);
                
                if ($vehicleCount > 0 && $vehicleCount != null) {
                    foreach ($checkVehicle as $key => $vehicle) {
                        $policy = Policy::where('id', $vehicle->policy_id)->first(array('status'));
                        if ($policy && ($policy->status == 1 || $policy->status == 0)) {
                            return response()->json([
                                'success' => true, 'message' => "Vehicle associated with existing policy"
                            ], 200);
                        }
                    }
                }
                return response()->json([
                    'success' => true, 'message' => "Vehicle not associated with any policy"
                ], 200);
            } else {
                return response()->json([
                    'success' => false, 'message' => "Vehicle plate is null"
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false, 'message' => $e->getMessage()
            ], 401);
        }
    }



    /**
     * Assessment auto-fill lookup for MotoLink (Kago/Ditso 2026-06-27).
     * GET /frontendpay/assessment-lookup?claim_number=G2026004915  (VerifyApiKey).
     * Read-only. Returns the claim + policy fields a motor assessment needs so the
     * handler no longer types sum insured + excess by hand. Sum insured + excess
     * come from the motor coverage row (coverage_value_main / own_damage_minimun_percent
     * / own_damage_minimum_amount) matched to the claimed vehicle's plate, falling
     * back to the latest motor row, then policies.sum_assured.
     */
    public function assessmentLookup(Request $request)
    {
        $request->validate([
            'claim_number' => 'bail|required|string|max:40',
        ]);

        $claim = Claim::where('claim_number', trim($request->claim_number))->first();
        if (!$claim) {
            return response()->json(['success' => false, 'message' => 'Claim not found'], 404);
        }

        $policy   = $claim->policy;
        $customer = $claim->customer;

        $clientName = null;
        if ($customer) {
            $clientName = trim(implode(' ', array_filter([
                $customer->firstName ?? null,
                $customer->middleName ?? null,
                $customer->lastName ?? null,
            ]))) ?: null;
        }

        $sumInsured = $excessPercent = $excessMinimum = $vehicleMake = $vehicleModel = null;

        if ($policy) {
            $coverageIds = \DB::table('policy_coverages')->where('policy_id', $policy->id)->pluck('id');
            $motor = null;
            if ($coverageIds->isNotEmpty()) {
                $motorQuery = \AlphaDirect\Models\Motor::whereIn('policy_coverage_id', $coverageIds);
                if (!empty($claim->vehicle_plate)) {
                    $motor = (clone $motorQuery)->where('registration_no', $claim->vehicle_plate)->orderByDesc('id')->first();
                }
                if (!$motor) {
                    $motor = $motorQuery->orderByDesc('id')->first();
                }
            }
            if ($motor) {
                $sumInsured    = $motor->coverage_value_main ?: ($motor->coverage_value ?: null);
                $excessPercent = $motor->own_damage_minimun_percent;
                $excessMinimum = $motor->own_damage_minimum_amount;
                $vehicleMake   = $motor->make ?: null;
                $vehicleModel  = $motor->model ?: null;
            }
            if ($sumInsured === null && $policy->sum_assured > 0) {
                $sumInsured = $policy->sum_assured;
            }
        }

        return response()->json([
            'success'             => true,
            'claim_number'        => $claim->claim_number,
            'policy_number'       => $policy->policyNumber ?? null,
            'client_name'         => $clientName,
            'registration_number' => $claim->vehicle_plate ?: null,
            'date_of_loss'        => $claim->incident_date ?: null,
            'sum_insured'         => $sumInsured !== null ? (float) $sumInsured : null,
            'excess'              => [
                'percent'        => $excessPercent !== null ? (float) $excessPercent : null,
                'minimum_amount' => $excessMinimum !== null ? (float) $excessMinimum : null,
            ],
            'vehicle'             => ['make' => $vehicleMake, 'model' => $vehicleModel],
        ]);
    }

}
