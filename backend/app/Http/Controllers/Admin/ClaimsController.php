<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\AccidentDriver;
use AlphaDirect\AccidentInjury;
use AlphaDirect\Accounts;
use AlphaDirect\Agency;
use AlphaDirect\ClaimAccidentOtherPartyBanking;
use AlphaDirect\ClaimAccidentOtherPartyKyc;
use AlphaDirect\ClaimAccidentPassenger;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\ClaimKeyLoss;
use AlphaDirect\ClaimReserves;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\ClaimSubType;
use AlphaDirect\ClaimThirdParty;
use AlphaDirect\GlassClaim;
use AlphaDirect\Ledger;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\RepairCenter;
use AlphaDirect\Role;
use AlphaDirect\State;
use AlphaDirect\City;
use AlphaDirect\Stores;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\UserRole;
use AlphaDirect\VehicleMake;
use Illuminate\Support\Facades\Redirect;
use AlphaDirect\Beneficiary;
use AlphaDirect\Claim;
use AlphaDirect\Country;
use AlphaDirect\ClaimAccident;
use AlphaDirect\Attachments;
use AlphaDirect\ClaimAssessment;
use AlphaDirect\ClaimComplaintLog;
use AlphaDirect\ClaimLife;
use AlphaDirect\ClaimQuote;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\FactorMain;
use AlphaDirect\Hook;
use AlphaDirect\KYC;
use AlphaDirect\Lookup;
use AlphaDirect\Mail\QuoteAcceptance;
use AlphaDirect\Mail\SendPO;
use AlphaDirect\Mail\SendQuote;
use AlphaDirect\Mail\AccidentAssessment;
use AlphaDirect\Policy;
use AlphaDirect\PolicyFactor;
use AlphaDirect\PolicyMember;
use AlphaDirect\PolicyMotorItems;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\RecipientKyc;
use AlphaDirect\Supplier;
use AlphaDirect\TemplateFields;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;
use PDF;
use File;
use AlphaDirect\Helper;
use function GuzzleHttp\Promise\all;
use AlphaDirect\Http\Controllers\Admin\EmailController;
use AlphaDirect\Mail\RejectMailTemplate;
use AlphaDirect\Quote;
use AlphaDirect\Sms;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use AlphaDirect\ClaimRecoveryInvolved;
use AlphaDirect\Models\ClaimLegal;
use AlphaDirect\OtherPartyInsured;
use AlphaDirect\Models\AllRiskAndElectronicEquipment;
use AlphaDirect\Models\Burglary;
use AlphaDirect\Models\NewClaim;
use AlphaDirect\Models\BusinessInterruption;
use AlphaDirect\Models\ClaimVoidPaymentLogs;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\DefectiveWorkmanship;
use AlphaDirect\Models\WorkersCompensation;
use AlphaDirect\Models\PropertyLossDamage;
use AlphaDirect\Models\PublicLiability;
use AlphaDirect\Models\FidelityGuarantee;
use AlphaDirect\Models\Fire;
use AlphaDirect\Models\GoodsInTransit;
use AlphaDirect\Models\MobileAndElectronicDevices;
use AlphaDirect\Models\ClaimHospitalCash;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\TravelInsurance;
use AlphaDirect\MotorComprehensiveSchedule;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Models\Audit;
use AlphaDirect\Models\PolicyAction;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\Motor;
class ClaimsController extends Controller
{
    /**
     * Display a listing of the claims.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('claim-list')) {
            $claimStatus = Claim::groupBy('status')->get(array('status'));
            $claimTypes = Claim::groupBy('claim_type')->get(array('claim_type'));
            return view('admin.claims.index', compact('claimStatus', 'claimTypes'));
        } else {
            $claimStatus = Claim::groupBy('status')->get(array('status'));
            $claimTypes = Claim::groupBy('claim_type')->get(array('claim_type'));
            return view('admin.claimsView.index', compact('claimStatus', 'claimTypes'));
        }
    }

    /**
     *
     *show the form for creating a new claim.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }


    /**
     *for storing reserve of specific claim.
     * @param int $id
     * @return view
     */
    public function storeReserve(Request $request, $id)
    {
        $data = $request->all();

        // If product_id is 7 or 8 and "combined_data" exists, override individual arrays
        if (in_array($data['product_id'], [7, 8]) && $request->has('combined_data')) {
            $combined = json_decode($request->input('combined_data'), true);
            if (is_array($combined)) {
                $data['coverage_id']   = $combined['coverage_id']   ?? [];
                $data['coverage_name'] = $combined['coverage_name'] ?? [];
                $data['coverageLimit'] = $combined['coverageLimit'] ?? [];
                $data['amt']           = $combined['amt']           ?? [];
            }
        }

        // dd($data);
        // Assuming `product_id` is passed as part of the request
        $productId = $data['product_id'];
        // Check validation only for product_id 7 and 8
        if (in_array($productId, [7, 8])) {
            $request->merge(['amt' => $data['amt']]);
            $request->merge(['coverage_id' => $data['coverage_id']]);
            $request->merge(['coverage_name' => $data['coverage_name']]);
            $request->merge(['coverageLimit' => $data['coverageLimit']]);

            $coverageLimits = $data['coverageLimit'];
            $amounts = $data['amt'];
            $coverageNames = $data['coverage_name'];
            $errors = [];

            foreach ($amounts as $index => $amt) {
                // if (!is_null($amt)) { // Check only if amt is not null
                if (is_numeric($amt) && $amt !== '') { // Check only if amt is numeric and not an empty string

                    // Remove formatting (like "P " and commas) from coverageLimit for numeric comparison
                    $coverageLimit = (float) str_replace([',', 'P '], '', $coverageLimits[$index]);

                    if ((float) $amt > $coverageLimit) {
                        $errors[] = "The amount for '{$coverageNames[$index]}' cannot exceed the coverage limit of {$coverageLimits[$index]}.";
                    }
                }
            }

            if (!empty($errors)) {
                return redirect()->back()->with('error', 'The amount cannot exceed the coverage limit.');
            }
        }
        // dd($request->amt);
        $claimReserves = new ClaimReserves();
        $claimReserves->claim_id = $id;
        $policyId = Claim::where('id', $id)->first()->policy_id;
        $policy = Policy::where('id', $policyId)->first();
        $claimReserves->date = $request->date;
        $claimReserves->product_id = $request->product_id;
        $claimReserves->transaction_type = $request->trans_type;
        $claimReserves->payee = $request->payee;
        $claimReserves->address = $request->address;
        $claimReserves->invoice_no = $request->invoiceNum;
        $claimReserves->memo = $request->memoOnCheck;
        $claimReserves->transaction_sub_type = isset($request->trans_subType) ? $request->trans_subType : $request->lossreserve_trans_subType;
        $claimReserves->invoice_date = $request->invoiceDate;
        $claimReserves->invoice_due_date = $request->dueDate;
        $claimReserves->description = $request->description;
        $claimReserves->credit_note = ($request->creditNote == NULL) ? 0 : 1;
        $claimReserves->include_vat = ($request->includeVat == NULL) ? 0 : 1;
        $saved = $claimReserves->save();
        if ($request->coverage_id != NULL) {
            $claimBalance = ClaimReservesCoverage::where('claim_id', $id)->orderBy('id', 'DESC')->first(array('balance'));
            if ($claimBalance == NULL)
                $balance = 0;
            else
                $balance = $claimBalance->balance;
            for ($i = 0; $i < count($request->coverage_id); $i++) {
                if ($request->amt[$i] != NULL && $request->amt[$i] != 0) {
                    $claimReservesCoverages = new ClaimReservesCoverage();
                    $claimReservesCoverages->reserve_id = $claimReserves->id;
                    $claimReservesCoverages->claim_id = $id;
                    $claimReservesCoverages->product_id = $request->product_id;
                    $claimReservesCoverages->coverage_id = $request->coverage_id[$i];
                    $claimReservesCoverages->coverage_name = $request->coverage_name[$i];
                    //transaction id 43 for loss payment and 44 for Reset Reserves from lookup data table
                    if ($request->trans_type == 43 || $request->trans_type == 44) {
                        // $claimReservesCoverages->payment_amt = $request->amt[$i];
                        // $balance = $balance - $request->amt[$i];

                        if (($request->trans_type == 43 && $request->trans_subType == 54) || ($request->trans_type == 43 && $request->trans_subType == 53) || ($request->trans_type == 43 && $request->trans_subType == 55)) { // on claims team request
                            $claimReservesCoverages->reserve_amt = $request->amt[$i];
                            $balance = $balance + $request->amt[$i];
                        } else {
                            $claimReservesCoverages->payment_amt = $request->amt[$i];
                            $balance = $balance - $request->amt[$i];
                        }
                    } else if ($request->trans_type == 89) { //TP Liability Reserves
                        $claimReservesCoverages->subrogation_reserve = $request->amt[$i];
                        $balance = $balance + $request->amt[$i];
                    } else if ($request->trans_type == 90) { //TP Liability Payment
                        $claimReservesCoverages->subrogation_payment = $request->amt[$i];
                        $balance = $balance - $request->amt[$i];
                    } else if ($request->trans_type == 91) { //Salvage Reserves
                        $claimReservesCoverages->salvage_reserve = $request->amt[$i];
                        $balance = $balance + $request->amt[$i];
                        // $balance = $balance - $request->amt[$i]; // changed on rethabile's request
                    } else if ($request->trans_type == 92) { //Salvage Payment
                        $claimReservesCoverages->salvage_payment = $request->amt[$i];
                        $balance = $balance - $request->amt[$i];
                        // $balance = $balance + $request->amt[$i]; // changed on rethabile's request
                    } else if ($request->trans_type == 43 || $request->trans_type == 44) {
                        // $claimReservesCoverages->payment_amt = $request->amt[$i];
                        // $balance = $balance - $request->amt[$i];

                        if (($request->trans_type == 43 && $request->trans_subType == 54) || ($request->trans_type == 43 && $request->trans_subType == 53) || ($request->trans_type == 43 && $request->trans_subType == 55)) { // on claims team request
                            $claimReservesCoverages->reserve_amt = $request->amt[$i];
                            $balance = $balance + $request->amt[$i];
                        } else {
                            $claimReservesCoverages->payment_amt = $request->amt[$i];
                            $balance = $balance - $request->amt[$i];
                        }
                    } else {
                        // $claimReservesCoverages->reserve_amt = $request->amt[$i];
                        // $balance = $balance + $request->amt[$i];

                        if (($request->trans_type == 42 && $request->lossreserve_trans_subType == 54) || ($request->trans_type == 42 && $request->lossreserve_trans_subType == 53) || ($request->trans_type == 42 && $request->lossreserve_trans_subType == 55)) { // on claims team request
                            $claimReservesCoverages->payment_amt = $request->amt[$i];
                            $balance = $balance - $request->amt[$i];
                        } else {
                            $claimReservesCoverages->reserve_amt = $request->amt[$i];
                            $balance = $balance + $request->amt[$i];
                        }

                    }
                    $claimReservesCoverages->balance = $balance;
                    $claimReservesCoverages->save();
                }
            }
        }
        session()->put('step', '10');
        if ($saved) {
            $sum = array_sum($request->amt);
            $vatInfo = $claimReserves->include_vat;
            Helper::ReserveLedgerStore($policy->customer_id, 'CLAIM', $id, $policy->product_id, 'CLAIMRESERVE', $sum, $vatInfo);
        }
        return redirect()->back()->with('success', 'Reserve stored Successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return specified claim page
     */
    public function show($id)
    {
        try {
            if (Auth::user()->hasPermissionTo('claim-edit')) {
                $newclaim = null;
                $bi = null;
                $allRisk = null;
                $dw = null;
                $burglary = null;
                $fg = null;
                $travelIns = null;
                $goodsInTransit = null;
                $fire = null;
                $mobileAndElectDev = null;

                $legal = null;
                $claimVehicle = null;
                $supplierQuotes = null;
                $suppliers = NULL;
                $partyBanking = NULL;
                $reportedByOpts = NULL;
                $partykyc = NULL;
                $transPayees = NULL;
                $eventNames = NULL;
                $otherparty = NULL;
                $otherparty_banking = NULL;
                $vehicleMakes = NULL;
                $vehicleModels = NULL;
                $claimAssessmentCount = NULL;
                $thirdparty = NULL;
                $glassClaim = NULL;
                $transTypes = NULL;
                $vehicleDetails = NULL;
                $salvage = NULL;
                $salvageyardRole = NULL;
                $attorneyRole = NULL;
                $attorney = NULL;
                $accidentPassenger = NULL;
                $accidentInjury = NULL;
                $claim_assessment = NULL;
                $accidentDriver = NULL;
                $ClaimRecoveryDetails = NULL;
                $claimAccident = NULL;
                $assessors = NULL;
                $userDetails = NULL;
                $user = NULL;
                $claimVehicleImages = NULL;
                $lossTypes = NULL;
                $claimSubTypes = NULL;
                $users = NULL;
                $transSubTypes = NULL;
                $coverages = NULL;
                $attachmentDataCount = NULL;
                $salvageUser = NULL;
                $userRole = NULL;
                $selectedSalvage = NULL;
                $productPlan = NULL;
                $otherPartyInsured = NULL;
                $hospitalCash = NULL;

                $claims = Claim::where('id', $id)->first();

                $claimAssessmentReport = ClaimAssessment::where('claim_id', $id)->first();
                $recipientKyc = RecipientKyc::where('claim_id', $id)->first();
                $policy = Policy::where('id', $claims->policy_id)->first();

                if ($policy->storeID != null) {
                    $store = Stores::where('id', $policy->storeID)->first();
                    if ($store && $store->name) {
                        $storeName = $store->name;
                    } else {
                        $storeName = null;
                    }
                } else {
                    $storeName = null;
                }

                $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                $members = PolicyMember::where('policy_id', $policy->id)->get();
                $fileNames = Lookup::where('key', 'file_type')->get(array('key', 'value'));
                $supplierTypes = Lookup::where('key', 'supplier_type')->get('value');
                $beneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();
                $product = Product::where('id', $policy->product_id)->first(array('id', 'name'));
                $productFactors = FactorMain::with('value')->where('product_id', $policy->product_id)->get(array('id', 'name', 'type'));
                foreach ($productFactors as $productFactor) {
                    $policyFactors = PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $productFactor->id)->get(array('factor_value_id', 'value_name', 'name'));
                    if ($productFactor->type == 'Input Field')
                        $productFactor->policyFactors = $policyFactors->pluck('value_name')->toArray();
                    else
                        $productFactor->policyFactors = $policyFactors->pluck('factor_value_id')->toArray();

                    $productFactor->policyFactorsValueName = $policyFactors->pluck('value_name')->toArray();
                }
                if ($policy->plan_id != NULL)
                    $productPlan = Productplan::where('id', $policy->plan_id)->first(array('name'));
                $policyMotorItems = PolicyMotorItems::where('policy_id', $policy->id)->get(array('id', 'item_name', 'item_value', 'policy_id'));
                $motor_items = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));
                $banking_details = CustomerBanking::where('claim_id', $claims->id)->first(array('customer_id', 'billingCell', 'bankName', 'branchCode', 'accountNumber'));
                $kyc = KYC::where('customer_id', $claims->customer_id)->first(array('customer_id', 'driving_license', 'omang', 'omangBack', 'proof_residence', 'proof_income', 'passport'));

                $policy_cellphone = PolicyCellPhone::where('policy_id', $policy->id)->get();

                if ($banking_details == NULL) {
                    $banking_details = CustomerBanking::where('policy_id', $policy->id)->first(array('billingCell', 'bankName', 'branchCode', 'accountNumber'));
                }
                // $policyCover = PolicyCoverage::where('policy_id', $policy->id)->get(array('main', 'coverage_value', 'discount', 'type', 'value', 'policy_id'));
                $vehicle = Vehicle::where('policy_id', $policy->id)->first();
                $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
                $life = NULL;
                $keyloss = NULL;
                $deathCauses = Lookup::where('key', 'cause_of_death')->get(array('id', 'value'));
                $claimVehicle = null;
                $supplierQuotes = null;
                $suppliers = NULL;
                $glassClaim = NULL;
                $vehicleDetails = NULL;
                $reasonName = NULL;
                $purposeName = NULL;
                $claimCellphone = NULL;
                $repairCenters = NULL;
                $stateName = NULL;
                $state = NULL;
                $isPOSent = NULL;
                $agent_broker = NULL;
                $coverageClaimData= NULL;
                $vehicle_model = NULL;
                $years = NULL;
                $vehicle_make = NULL;
                $isImported = 'NO';
                $company = NULL;
                $reasons = NULL;
                $groupedRiskAddCoverages = NULL;

                if ($claims) {

                    $payment_amt = ClaimReservesCoverage::where('claim_id', $id)->sum('payment_amt') - ClaimReservesCoverage::where('claim_id', $id)->sum('subrogation_payment') - ClaimReservesCoverage::where('claim_id', $id)->sum('salvage_payment');
                    $reserve_amt = ClaimReservesCoverage::where('claim_id', $id)->sum('reserve_amt') - ClaimReservesCoverage::where('claim_id', $id)->sum('subrogation_reserve') - ClaimReservesCoverage::where('claim_id', $id)->sum('salvage_reserve');
                    $balance_amt = $reserve_amt - $payment_amt;

                    $transTypes = Lookup::where('key', 'transaction_type')->where('value', '!=', 'Initial Reserves')->get(array('id', 'key', 'value'));
                    $transPayees = Supplier::get(array('id', 'supplierName'));
                    $transSubTypes = Lookup::where('key', 'transaction_sub_type')->get(array('id', 'key', 'value'));
                    $coverages = ProductCoverage::groupBy('product_coverage.coverage_id')
                        ->select('product_coverage.name', 'product_coverage.coverage_id')
                        ->where('product_coverage.product_id', $policyProduct->product_id)->get();

                     // For total payment amount
                        $totalPayment = ClaimReservesCoverage::where('claim_id', $id)->sum('payment_amt');

                        $totalPaymentVoided = ClaimReservesCoverage::where('claim_id', $id)->where('is_payment_voided','=',1)->sum('payment_amt');


                        // Subrogation and salvage deductions
                        $subrogationPayment = ClaimReservesCoverage::where('claim_id', $id)->sum('subrogation_payment');
                        $salvagePayment = ClaimReservesCoverage::where('claim_id', $id)->sum('salvage_payment');

                        // Deduction for transaction_type = 44
                        $transactionType44Payment = ClaimReservesCoverage::join('claim_reserves', 'claim_reserves.id', '=', 'claim_reserves_coverages.reserve_id')
                            ->where('claim_reserves_coverages.claim_id', $id)
                            ->where('claim_reserves.transaction_type', 44)
                            ->sum('claim_reserves_coverages.payment_amt');

                        // Final calculation
                        $payment_amt = $totalPayment - $subrogationPayment - $salvagePayment - $transactionType44Payment - $totalPaymentVoided;

                        // For total reserve amount
                        $totalReserve = ClaimReservesCoverage::where('claim_id', $id)->sum('reserve_amt');

                        // Subrogation and salvage reserve deductions
                        $subrogationReserve = ClaimReservesCoverage::where('claim_id', $id)->sum('subrogation_reserve');
                        $salvageReserve = ClaimReservesCoverage::where('claim_id', $id)->sum('salvage_reserve');

                        // Final reserve amount after deductions
                        $reserve_amt = $totalReserve - $subrogationReserve - $salvageReserve - $transactionType44Payment - $totalPaymentVoided;


                    if ($policy->product_id == 7 || $policy->product_id == 8) {

                        // // For total payment amount
                        // $totalPayment = ClaimReservesCoverage::where('claim_id', $id)->sum('payment_amt');

                        // $totalPaymentVoided = ClaimReservesCoverage::where('claim_id', $id)->where('is_payment_voided','=',1)->sum('payment_amt');


                        // // Subrogation and salvage deductions
                        // $subrogationPayment = ClaimReservesCoverage::where('claim_id', $id)->sum('subrogation_payment');
                        // $salvagePayment = ClaimReservesCoverage::where('claim_id', $id)->sum('salvage_payment');

                        // // Deduction for transaction_type = 44
                        // $transactionType44Payment = ClaimReservesCoverage::join('claim_reserves', 'claim_reserves.id', '=', 'claim_reserves_coverages.reserve_id')
                        //     ->where('claim_reserves_coverages.claim_id', $id)
                        //     ->where('claim_reserves.transaction_type', 44)
                        //     ->sum('claim_reserves_coverages.payment_amt');

                        // // Final calculation
                        // $payment_amt = $totalPayment - $subrogationPayment - $salvagePayment - $transactionType44Payment - $totalPaymentVoided;

                        // // For total reserve amount
                        // $totalReserve = ClaimReservesCoverage::where('claim_id', $id)->sum('reserve_amt');

                        // // Subrogation and salvage reserve deductions
                        // $subrogationReserve = ClaimReservesCoverage::where('claim_id', $id)->sum('subrogation_reserve');
                        // $salvageReserve = ClaimReservesCoverage::where('claim_id', $id)->sum('salvage_reserve');

                        // // Final reserve amount after deductions
                        // $reserve_amt = $totalReserve - $subrogationReserve - $salvageReserve - $transactionType44Payment;

                        // Policy Coverages Data Query
                        $policyCoveragesData = DB::table('policy_coverages as pc')
                            ->leftJoin('policy_coverage_detail as pcd', 'pc.id', '=', 'pcd.policy_coverage_id')
                            ->leftJoin('tb_cvgpccoverages', 'pcd.coverage_id', '=', 'tb_cvgpccoverages.id')
                            ->leftJoin('motor', function ($join) {
                                $join->on('motor.policy_coverage_id', '=', 'pc.id')
                                    ->whereIn('pc.coverage_id', [22, 27]);
                            })
                            ->leftJoin('risk_address', 'risk_address.id', '=', 'pc.risk_address_id')
                            ->where('pc.policy_id', $policy->id)
                            ->whereNull('pc.deleted_at')
                            ->select([
                                'pc.id as policiCoverageID',
                                'pcd.id as pcdID',
                                'pcd.policy_coverage_id as pcdPCID',
                                DB::raw("CASE
                                            WHEN pc.coverage_id IN (22, 27) THEN motor.id
                                            ELSE pcd.id
                                        END as coverage_id"),
                                DB::raw("CASE
                                            WHEN pc.coverage_id = 22 THEN CONCAT('Commercial Motor - ', motor.vehicle_name, ' (', motor.registration_no, ')')
                                            WHEN pc.coverage_id = 27 THEN CONCAT('Personal Motor - ', motor.vehicle_name, ' (', motor.registration_no, ')')
                                            ELSE CONCAT(tb_cvgpccoverages.s_ParentCoverageCode, ' - ', tb_cvgpccoverages.s_CoverageName)
                                        END as coverage_name"),
                                DB::raw("CASE
                                            WHEN pc.coverage_id IN (22, 27) THEN CONCAT('P ', FORMAT(motor.coverage_value_main, 2))
                                            ELSE CONCAT('P ', FORMAT(pcd.coverage_value, 2))
                                        END as coverage_limit"),
                                'risk_address.address_name as risk_address_name'
                            ])
                            ->get()
                            ->filter(function ($coverage) {
                                return !is_null($coverage->coverage_name) && !is_null($coverage->coverage_limit);
                            });

                            // Policy Miscellaneous Coverages Data Query
                            $policyMiscellaneousDetails = DB::table('policy_specified_items')
                                ->leftJoin('policy_coverages as pc', 'policy_specified_items.policy_coverage_id', '=', 'pc.id')
                                ->leftJoin('specified_coverage_items', 'policy_specified_items.specified_coverage_id', '=', 'specified_coverage_items.id')
                                ->leftJoin('risk_address', 'risk_address.id', '=', 'pc.risk_address_id')
                                ->leftJoin('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'pc.coverage_id')
                                ->where('pc.policy_id', $policy->id)
                                ->whereNull('pc.deleted_at')
                                ->select([
                                    'policy_specified_items.policy_coverage_id',
                                    'policy_specified_items.id as coverage_id',
                                    DB::raw("CONCAT(tb_cvgpccoverages.s_ScreenName, ' Miscellaneous - ', specified_coverage_items.specified_name) as coverage_name"),
                                    DB::raw("CONCAT('P ', FORMAT(policy_specified_items.sum_insured, 2)) as coverage_limit"),
                                    'risk_address.address_name as risk_address_name',
                                ])
                                ->get();

                            // Policy Extension Data Query
                            $policyExtentionDetails = DB::table('policy_extention_detail')
                                ->leftJoin('policy_coverages as pc', 'policy_extention_detail.policy_coverage_id', '=', 'pc.id')
                                ->leftJoin('risk_address', 'risk_address.id', '=', 'pc.risk_address_id')
                                ->where('pc.policy_id', $policy->id)
                                ->where('policy_extention_detail.s_ParentCoverageID','!=',0)
                                ->whereNotNull('policy_extention_detail.s_ParentCoverageID')
                                ->whereNull('pc.deleted_at')
                                ->select([
                                    'policy_extention_detail.policy_coverage_id',
                                    'policy_extention_detail.id as coverage_id',
                                    DB::raw("CONCAT(policy_extention_detail.s_ParentCoverageCode, ' Extension - ', policy_extention_detail.s_ScreenName) as coverage_name"),
                                    DB::raw("CONCAT('P ', FORMAT(policy_extention_detail.extention_coverage_value, 2)) as coverage_limit"),
                                    'risk_address.address_name as risk_address_name',
                                ])
                                ->get();


                            $policyMotorExtentionDetails = DB::table('policy_coverages as pc')
                            ->leftJoin('risk_address', 'risk_address.id', '=', 'pc.risk_address_id')
                            ->leftJoin('hardCoded_extension_details as hed', 'hed.coverage_id', '=', 'pc.coverage_id')
                            ->leftJoin('motor', function ($join) {
                                $join->on('motor.policy_coverage_id', '=', 'pc.id')
                                    ->whereIn('pc.coverage_id', [22, 27]);
                            })
                            ->leftJoin('motor_traders', function ($join) {
                                $join->on('motor_traders.policy_coverage_id', '=', 'pc.id')
                                    ->where('pc.coverage_id', 15);
                            })
                            ->leftJoin('motor_traders_internal', function ($join) {
                                $join->on('motor_traders_internal.policy_coverage_id', '=', 'pc.id')
                                    ->where('pc.coverage_id', 16);
                            })
                            ->where('pc.policy_id', $policy->id)
                            ->whereIn('pc.coverage_id', [22, 27, 15, 16])
                            ->whereNull('pc.deleted_at')
                            ->select([
                                'pc.id as policy_coverage_id',
                                'hed.id as coverage_id',
                                DB::raw("
                                    CASE
                                        WHEN hed.coverage_id IN (15,16) AND hed.id IN (57,58,59,60,61,62) THEN hed.name
                                        WHEN hed.coverage_id = 15 THEN CONCAT('Motor Traders External Extension - ', hed.name, ' - ', motor.vehicle_name, ' (', motor.registration_no, ')')
                                        WHEN hed.coverage_id = 16 THEN CONCAT('Motor Traders Internal Extension - ', hed.name, ' - ', motor.vehicle_name, ' (', motor.registration_no, ')')
                                        WHEN hed.coverage_id = 22 THEN CONCAT('Commercial Motor Extension - ', hed.name, ' - ', motor.vehicle_name, ' (', motor.registration_no, ')')
                                        WHEN hed.coverage_id = 27 THEN CONCAT('Personal Motor Extension - ', hed.name, ' - ', motor.vehicle_name, ' (', motor.registration_no, ')')
                                        ELSE hed.name
                                    END as coverage_name
                                "),
                                DB::raw("
                                    CASE
                                        WHEN hed.coverage_id = 15 THEN
                                            CASE
                                                WHEN hed.key = 'vehicle_lent_hire_coverage_value' THEN motor_traders.vehicle_lent_hire_coverage_value
                                                WHEN hed.key = 'social_domestic_pleasure_coverage_value' THEN motor_traders.social_domestic_pleasure_coverage_value
                                                WHEN hed.key = 'unauthoried_use_coverage_value' THEN motor_traders.unauthoried_use_coverage_value
                                                WHEN hed.key = 'windscreen_coverage_value' THEN motor_traders.windscreen_coverage_value
                                                WHEN hed.key = 'contigent_liability_coverage_value' THEN motor_traders.contigent_liability_coverage_value
                                                WHEN hed.key = 'wreckage_removal_coverage_value' THEN motor_traders.wreckage_removal_coverage_value
                                                WHEN hed.key = 'loss_of_key_coverage_value' THEN motor_traders.loss_of_key_coverage_value
                                                WHEN hed.key = 'Loss_of_use_of_customer_coverage_value' THEN motor_traders.Loss_of_use_of_customer_coverage_value
                                                WHEN hed.key = 'motor_cycle_motor_tricycle_coverage_value' THEN motor_traders.motor_cycle_motor_tricycle_coverage_value
                                                WHEN hed.key = 'passanger_liability_respect_of_motor_coverage_value' THEN motor_traders.passanger_liability_respect_of_motor_coverage_value
                                                WHEN hed.key = 'special_type_vehicle_coverage_value' THEN motor_traders.special_type_vehicle_coverage_value

                                                WHEN hed.key = 'loss_or_damage_coverage_value' THEN motor_traders.loss_or_damage_coverage_value
                                                WHEN hed.key = 'third_party_liability_coverage_value' THEN motor_traders.third_party_liability_coverage_value
                                                WHEN hed.key = 'medical_benefits_coverage_value' THEN motor_traders.medical_benefits_coverage_value
                                            END

                                        WHEN hed.coverage_id = 16 THEN
                                            CASE
                                                WHEN hed.key = 'vehicle_lent_hire_coverage_value' THEN motor_traders_internal.vehicle_lent_hire_coverage_value
                                                WHEN hed.key = 'social_domestic_pleasure_coverage_value' THEN motor_traders_internal.social_domestic_pleasure_coverage_value
                                                WHEN hed.key = 'unauthoried_use_coverage_value' THEN motor_traders_internal.unauthoried_use_coverage_value
                                                WHEN hed.key = 'windscreen_coverage_value' THEN motor_traders_internal.windscreen_coverage_value
                                                WHEN hed.key = 'contigent_liability_coverage_value' THEN motor_traders_internal.contigent_liability_coverage_value
                                                WHEN hed.key = 'wreckage_removal_coverage_value' THEN motor_traders_internal.wreckage_removal_coverage_value
                                                WHEN hed.key = 'loss_of_key_coverage_value' THEN motor_traders_internal.loss_of_key_coverage_value
                                                WHEN hed.key = 'Loss_of_use_of_customer_coverage_value' THEN motor_traders_internal.Loss_of_use_of_customer_coverage_value
                                                WHEN hed.key = 'motor_cycle_motor_tricycle_coverage_value' THEN motor_traders_internal.motor_cycle_motor_tricycle_coverage_value
                                                WHEN hed.key = 'passanger_liability_respect_of_motor_coverage_value' THEN motor_traders_internal.passanger_liability_respect_of_motor_coverage_value
                                                WHEN hed.key = 'special_type_vehicle_coverage_value' THEN motor_traders_internal.special_type_vehicle_coverage_value

                                                WHEN hed.key = 'loss_or_damage_coverage_value' THEN motor_traders_internal.loss_or_damage_coverage_value
                                                WHEN hed.key = 'third_party_liability_coverage_value' THEN motor_traders_internal.third_party_liability_coverage_value
                                                WHEN hed.key = 'medical_benefits_coverage_value' THEN motor_traders_internal.medical_benefits_coverage_value
                                            END

                                        WHEN hed.coverage_id = 22 THEN
                                            CASE
                                                WHEN hed.key = 'passenger_liability' THEN motor.passenger_liability
                                                WHEN hed.key = 'unorthorised_passanger_liability' THEN motor.unorthorised_passanger_liability
                                                WHEN hed.key = 'parking_facilities' THEN motor.parking_facilities
                                                WHEN hed.key = 'com_windscreen' THEN motor.com_windscreen
                                                WHEN hed.key = 'riot_strike' THEN motor.riot_strike
                                                WHEN hed.key = 'locks_keys' THEN motor.locks_keys
                                                WHEN hed.key = 'credit_shortfall' THEN motor.credit_shortfall
                                                WHEN hed.key = 'third_party_liability' THEN motor.third_party_liability
                                                WHEN hed.key = 'wreckage_removal' THEN motor.wreckage_removal
                                                WHEN hed.key = 'window_glass' THEN motor.window_glass
                                            END

                                        WHEN hed.coverage_id = 27 THEN
                                            CASE
                                                WHEN hed.key = 'wreckage_removal' THEN motor.wreckage_removal
                                                WHEN hed.key = 'window_glass' THEN motor.window_glass
                                                WHEN hed.key = 'locks_keys' THEN motor.locks_keys
                                                WHEN hed.key = 'parts_accessories' THEN motor.parts_accessories
                                                WHEN hed.key = 'audio_accessories' THEN motor.audio_accessories
                                                WHEN hed.key = 'riot_strike' THEN motor.riot_strike
                                                WHEN hed.key = 'car_hire_theft' THEN motor.car_hire_theft
                                                WHEN hed.key = 'credit_shortfall' THEN motor.credit_shortfall
                                                WHEN hed.key = 'insured_driver' THEN motor.insured_driver
                                                WHEN hed.key = 'insured_family' THEN motor.insured_family
                                                WHEN hed.key = 'medical_expenses' THEN motor.medical_expenses
                                                WHEN hed.key = 'passenger_liability' THEN motor.passenger_liability
                                                WHEN hed.key = 'third_party_liability' THEN motor.third_party_liability
                                                WHEN hed.key = 'specified_accessories' THEN motor.specified_accessories
                                            END

                                        ELSE 'N/A'
                                    END as coverage_limit
                                "),
                                'risk_address.address_name as risk_address_name', // Showing risk address name
                            ])
                            ->get();


                        // dd($policyMotorExtentionDetails);
                        // Merge all coverages into a single collection
                        $coverages = $policyCoveragesData
                            // ->merge($motorTradersCoverages)
                            // ->merge($motorTradersInternalCoverages)
                            ->merge($policyMiscellaneousDetails)
                            ->merge($policyMotorExtentionDetails)
                            ->merge($policyExtentionDetails);
                                // dd($coverages);
                                $groupedRiskAddCoverages = $policyCoveragesData
                                ->merge($policyMiscellaneousDetails)
                                ->merge($policyMotorExtentionDetails)
                                ->merge($policyExtentionDetails)->groupBy('risk_address_name');
                                // dd($groupedRiskAddCoverages);
                    }
                    // dd($coverages,$groupByRiskAddForAllCoverages);
                    // dd($coverages,$groupedRiskAddCoverages,$policyExtentionDetails,$allCoverages);
                    if ($policy->product_id == 7) {
                        $cust_profile = CustomerProfile::where('customer_id', $claims->customer_id)->first(['entity_type','company_id']);
                        $company = Company::where('id', $cust_profile->company_id)->first('name');
                    }
                    if ($policy->product_id == 8) {
                        $cust_profile = CustomerProfile::where('customer_id', $claims->customer_id)->first(['entity_type','company_id']);
                        if($cust_profile && $cust_profile->entity_type == 'Organisation' && $cust_profile->company_id) {
                            $companyData = Company::where('id', $cust_profile->company_id)->first('name');
                            if ($companyData && $companyData->name) {
                                $company = $companyData;
                            }
                        }
                    }

                    foreach ($coverages as $coverage) {
                        $coverage->payment_amt = ClaimReservesCoverage::where('coverage_id', $coverage->coverage_id)->where('claim_id', $id)->sum('payment_amt');
                        $coverage->reserve_amt = ClaimReservesCoverage::where('coverage_id', $coverage->coverage_id)->where('claim_id', $id)->sum('reserve_amt');
                    }

                    if ($claims->claim_type == 'Glass' && ($policy->product_id != 7 && $policy->product_id != 8)) {
                        $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                        $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();
                        $eventNames = Lookup::where('key', 'motor_claim_event')->get(array('value'));

                        if ($vehicle->is_imported == 0) {
                            $isImported = 'No';
                        } else {
                            $isImported = 'Yes';
                        }

                        $years = range(1990,Carbon::now()->year);
                        $vehicle_make = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($isImported);
                        if (isset($vehicle->make) && isset($isImported) && isset($vehicle->year)) {
                            $vehicle_model = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make, $isImported, $vehicle->year);
                        }
                    }elseif ($claims->claim_type == 'Legal') {
                        $legal = ClaimLegal::where('claim_id', $claims->id)->first();
                        //dd($legal);
                    }elseif ($claims->claim_type == 'Life') {
                        $life = ClaimLife::where('claim_id', $claims->id)->first();
                    }elseif ($claims->claim_type == 'Hospital CashBack') {
                        $hospitalCash = ClaimHospitalCash::where('claim_id', $claims->id)->first();
                    }elseif ($claims->claim_type == 'Cellphone') {
                        $claimCellphone = ClaimCellphone::where('claim_id', $claims->id)->first();
                        $claimAssessmentCount = ClaimAssessment::where('claim_id', $id)->get(array('id', 'policy_id', 'assessor_id', 'assessment_report', 'quotations_parts', 'valuation', 'notes', 'attached_file'));
                    } elseif ($claims->claim_type == 'Key Loss' && ($policy->product_id != 7 && $policy->product_id != 8)) {
                        $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                        $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();
                        $keyloss = ClaimKeyLoss::where('claim_id', $claims->id)->first();

                        if ($vehicle->is_imported == 0) {
                            $isImported = 'No';
                        } else {
                            $isImported = 'Yes';
                        }

                        $years = range(1990,Carbon::now()->year);
                        $vehicle_make = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($isImported);
                        if (isset($vehicle->make) && isset($isImported) && isset($vehicle->year)) {
                            $vehicle_model = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make, $isImported, $vehicle->year);
                        }

                        if (isset($keyloss->reason)) {
                            $reason = Lookup::where('id', $keyloss->reason)->first();
                            if ($reason && $reason->value) {
                                $reasonName = $reason->value;
                            } else {
                                $reasonName = null;
                            }
                        } else {
                            $reasonName = null;
                        }

                        if (isset($keyloss->purpose)) {
                            $purpose = Lookup::where('id', $keyloss->purpose)->first();
                            if ($purpose && $purpose->value) {
                                $purposeName = $purpose->value;
                            } else {
                                $purposeName = null;
                            }
                        } else {
                            $purposeName = null;
                        }
                    } elseif ($claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' ||
                            $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' ||
                            $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY' ||
                            $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS'
                            || $claims->claim_type == 'HOUSEHOLDERS' || $claims->claim_type == 'HOUSEOWNERS' || $claims->claim_type == 'HOUSEOWNER-BUILDINGS' || $claims->claim_type == 'HOUSEHOLDERS-CONTENTS') {

                        $newclaim = NewClaim::where('claim_number', $claims->claim_number)->orderBy('id','desc')->first();
                        if (isset($newclaim)) {
                            if ($claims->claim_type == 'BUSINESSINTERRUPTION') {
                                $bi =  BusinessInterruption::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            } elseif ($claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS') {
                                $allRisk = AllRiskAndElectronicEquipment::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            } elseif ($claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS') {
                                $coverageClaimData = WorkersCompensation::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            } elseif ($claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY') {
                                $burglary = Burglary::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            } elseif ($claims->claim_type == 'FIDELITYGUARANTEE') {
                                $fg = FidelityGuarantee::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                                if (isset($fg) && isset($fg->defaulting_employees_name)) {
                                    $fg->defaulting_employees_name = json_decode($fg->defaulting_employees_name);
                                }
                            } elseif ($claims->claim_type == 'TRAVELINSURANCE') {
                                $travelIns = TravelInsurance::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            } elseif ($claims->claim_type == 'GOODSINTRANSIT') {
                                $goodsInTransit = GoodsInTransit::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            } elseif ($claims->claim_type == 'FIRE') {
                                $fire = Fire::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            }elseif ($claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'HOUSEHOLDERS' || $claims->claim_type == 'HOUSEOWNERS' || $claims->claim_type == 'HOUSEOWNER-BUILDINGS' || $claims->claim_type == 'HOUSEHOLDERS-CONTENTS') {
                                $coverageClaimData = PropertyLossDamage::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            }elseif ($claims->claim_type == 'LIABILITY') {
                                $coverageClaimData = PublicLiability::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            }elseif ($claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS') {
                                $mobileAndElectDev = MobileAndElectronicDevices::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            }elseif ($claims->claim_type == 'DEFECTIVEWORKMANSHIP') {
                                $coverageClaimData = DefectiveWorkmanship::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
                            }elseif ($claims->claim_type == 'Glass' && ($policy->product_id == 7 || $policy->product_id == 8)) {
                                $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                                $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();
                            }

                            $agent_broker = User::where('id',$newclaim->reportedByBrokerAgent)->first(['firstName','lastName']);
                        }

                        $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
                        $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
                        $claimAssessmentCount = ClaimAssessment::where('claim_id', $id)->get(array('id', 'policy_id', 'assessor_id', 'assessment_report', 'quotations_parts', 'valuation', 'notes', 'attached_file'));
                        $assessors = User::role('Accessor')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                        $suppliers = Supplier::where('customer_selected', 0)->get(array('id', 'supplierName'));
                        $users = User::get(array('id', 'firstName', 'lastName'));
                    }elseif ($claims->claim_type == 'Glass' && ($policy->product_id == 7 || $policy->product_id == 8)) {

                        $newclaim = NewClaim::where('claim_number', $claims->claim_number)->orderBy('id','desc')->first();
                        if (isset($newclaim)) {
                            $agent_broker = User::where('id',$newclaim->reportedByBrokerAgent)->first(['firstName','lastName']);
                        }

                        $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                        $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();

                        $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
                        $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
                        $claimAssessmentCount = ClaimAssessment::where('claim_id', $id)->get(array('id', 'policy_id', 'assessor_id', 'assessment_report', 'quotations_parts', 'valuation', 'notes', 'attached_file'));
                        $assessors = User::role('Accessor')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                        $suppliers = Supplier::where('customer_selected', 0)->get(array('id', 'supplierName'));
                        $users = User::get(array('id', 'firstName', 'lastName'));
                    }elseif ($claims->claim_type == 'Key Loss' && ($policy->product_id == 7 || $policy->product_id == 8)) {

                        $newclaim = NewClaim::where('claim_number', $claims->claim_number)->orderBy('id','desc')->first();
                        if (isset($newclaim)) {
                            $agent_broker = User::where('id',$newclaim->reportedByBrokerAgent)->first(['firstName','lastName']);
                        }

                        $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                        $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();
                        $keyloss = ClaimKeyLoss::where('claim_id', $claims->id)->first();

                        if ($vehicle->is_imported == 0) {
                            $isImported = 'No';
                        } else {
                            $isImported = 'Yes';
                        }

                        $years = range(1990,Carbon::now()->year);
                        $vehicle_make = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($isImported);
                        if (isset($vehicle->make) && isset($isImported) && isset($vehicle->year)) {
                            $vehicle_model = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make, $isImported, $vehicle->year);
                        }

                        if (isset($keyloss->reason)) {
                            $reason = Lookup::where('id', $keyloss->reason)->first();
                            if ($reason && $reason->value) {
                                $reasonName = $reason->value;
                            } else {
                                $reasonName = null;
                            }
                        } else {
                            $reasonName = null;
                        }

                        if (isset($keyloss->purpose)) {
                            $purpose = Lookup::where('id', $keyloss->purpose)->first();
                            if ($purpose && $purpose->value) {
                                $purposeName = $purpose->value;
                            } else {
                                $purposeName = null;
                            }
                        } else {
                            $purposeName = null;
                        }

                        $reasons = Lookup::where('key', 'key_loss_claim_reason')->get(array('id', 'value'));

                        $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
                        $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
                        $claimAssessmentCount = ClaimAssessment::where('claim_id', $id)->get(array('id', 'policy_id', 'assessor_id', 'assessment_report', 'quotations_parts', 'valuation', 'notes', 'attached_file'));
                        $assessors = User::role('Accessor')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                        $suppliers = Supplier::where('customer_selected', 0)->get(array('id', 'supplierName'));
                        $users = User::get(array('id', 'firstName', 'lastName'));
                    }else {

                        if (($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL') && ($policy->product_id == 7 || $policy->product_id == 8)) {

                            $newclaim = NewClaim::where('claim_number', $claims->claim_number)->orderBy('id','desc')->first();

                            $agent_broker = User::where('id',$newclaim->reportedByBrokerAgent)->first(['firstName','lastName']);
                            // $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
                            // $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
                            // $claimAssessmentCount = ClaimAssessment::where('claim_id', $id)->get(array('id', 'policy_id', 'assessor_id', 'assessment_report', 'quotations_parts', 'valuation', 'notes', 'attached_file'));
                            // $assessors = User::role('Accessor')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                            // $suppliers = Supplier::where('customer_selected', 0)->get(array('id', 'supplierName'));
                            // $users = User::get(array('id', 'firstName', 'lastName'));
                        }

                        $claimAccident = ClaimAccident::where('claim_id', $claims->id)->first();
                        $otherparty_banking = ClaimAccidentOtherPartyBanking::where('claim_id', $claims->id)->get();
                        $leads = json_decode($claimAccident->approved_otherParty, true);
                        if (!empty($leads)) {
                            $partyBanking = ClaimAccidentOtherPartyBanking::whereIn('claim_thirdParty_id', $leads)->get();
                            $partykyc = ClaimAccidentOtherPartyKyc::whereIn('thirdParty_id', $leads)->get();
                        } else {
                            $partyBanking = $partykyc = '';
                        }
                        $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
                        $claimAssessmentCount = ClaimAssessment::where('claim_id', $id)->get(array('id', 'policy_id', 'assessor_id', 'assessment_report', 'quotations_parts', 'valuation', 'notes', 'attached_file'));
                        $vehicleMakes = DB::table('tb_prmotormakemodels')
                            ->selectRaw('DISTINCT s_Make')
                            ->pluck('s_Make');

                        $vehicleModels = DB::table('tb_prmotormakemodels')
                            ->selectRaw('DISTINCT s_Variant')
                            ->get('s_Variant');  //first(array('s_Variant'))
                        $otherparty = ClaimThirdParty::where('claim_id', $claims->id)->get(array('id', 'first_name', 'last_name'));

                        $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();
                        $thirdparty = ClaimThirdParty::where('claim_id', $claims->id)->get();
                        $otherPartyInsured = OtherPartyInsured::where('claim_id', $claims->id)->get();
                        $accidentPassenger = ClaimAccidentPassenger::where('claim_id', $claims->id)->get();

                        $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
                        $ClaimRecoveryDetails = ClaimRecoveryInvolved::where('claim_id', $claims->id)->first();
                        $suppliers = Supplier::where('customer_selected', 0)->get(array('id', 'supplierName'));
                        $attorneyRole = User::role('Attorney')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                        $attorney = User::whereIn('id', $attorneyRole->pluck('user_id'))->get(array('id', 'firstName', 'lastName'));
                        $accidentInjury = AccidentInjury::where('claim_id', $claims->id)->get();
                        $eventNames = Lookup::where('key', 'motor_claim_event')->get(array('value'));
                        $reportedByOpts = Lookup::where('key', 'claim_reported_by')->get(array('value'));
                        $lossTypes = Lookup::where('key', 'motor_claim_loss_type')->get(array('value'));
                        $claimSubTypes = ClaimSubType::where('claim_type', 'Motor accident')->get(array('sub_type'));
                        $users = User::get(array('id', 'firstName', 'lastName'));
                        $assessors = User::role('Accessor')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                        $salvageUser = User::role('Salvage Yard')->where('active', 1)->get(array('id', 'firstName', 'lastName'));

                        if ($claimAccident->salvage_yard_id != NULL) {
                            $selectedSalvage = User::where('id', $claimAccident->salvage_yard_id)->first(array('id', 'firstName', 'lastName'));
                        } else {
                            $selectedSalvage = NULL;
                        }
                        $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                    }
                }

                if ($claims->customer_selected && $claims->claim_type == 'Glass') {
                    $supplier = Supplier::where('id', $claims->supplier_id)->first();
                } else {
                    $supplier = NULL;
                }

                session()->forget('step');
                $userInfo = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();

                $passpostIssueCountry = Country::where('id', $userInfo->profile->countryId)->first(array('name'));

                if ($userInfo->profile->state != null) {
                    $state = State::where('id', $userInfo->profile->state)->first();
                    if ($state && $state->name) {
                        $stateName = $state->name;
                    } else {
                        $stateName = null;
                    }
                } else {
                    $stateName = null;
                }

                if ($userInfo->profile->city != null) {

                    if (is_numeric($userInfo->profile->city)) {
                        $city = City::where('id', $userInfo->profile->city)->first();
                        if ($city && $city->name) {
                            $cityName = $city->name;
                        } else {
                            $cityName = null;
                        }
                    } else {
                        $cityName = $userInfo->profile->city;
                    }
                } else {
                    $cityName = null;
                }

                if ($claims->claim_type == 'Cellphone') {
                    $repairCenters = RepairCenter::get(array('id', 'name'));
                    $supplierQuotes = ClaimQuote::with('repair_center')->where('claim_id', $claims->id)->get(array('id', 'supplier_id', 'total', 'status', 'addn_file'));

                } else {
                    $supplierQuotes = ClaimQuote::with('supplier')->where('claim_id', $claims->id)->get(array('id', 'supplier_id', 'total', 'status', 'claim_file', 'select_reason', 'invoice', 'invoice_notes'));
                }

                $lowestQuote = ClaimQuote::where('claim_id', $claims->id)->where('total', '!=', NULL)->orderBy('total')->first(array('id'));
                $isPOSent = ClaimQuote::where('claim_id', $claims->id)->where('status', 1)->count();

                $riskAddress = RiskAddress::where('policy_id',$policy->id)->get();
                $claimReportedBy = Lookup::where('key', 'claim_reported_by')->get(array('value'));

                $selectedReportedBy = NULL;
                if (isset($newclaim)) {
                    $selectedReportedBy = User::where('id', $newclaim->reportedByBrokerAgent)->where('active', 1)->first();
                    if (isset($selectedReportedBy)) {
                        $selectedReportedBy = $selectedReportedBy->id;
                    }

                    if (!$selectedReportedBy) {
                        // If not found in `User`, check in `Agency`
                        $selectedReportedBy = Agency::where('id', $newclaim->reportedByBrokerAgent)->where('status', 1)->first();
                        if (isset($selectedReportedBy)) {
                            $selectedReportedBy = $selectedReportedBy->id;
                        }
                    }
                }

                $agents = User::with('roles')->where('active',1)->get();
                $agencies = Agency::all();

                // Combine both agents and agencies with labels
                $agents_options = collect();

                foreach ($agents as $agent) {
                    $agents_options->push([
                        'id' => $agent->id,
                        'name' => $agent->firstName . ' ' . $agent->lastName,
                        'type' => 'Agent',
                    ]);
                }

                foreach ($agencies as $agency) {
                    $agents_options->push([
                        'id' => $agency->id,
                        'name' => $agency->name, // assuming the agency has a 'name' field
                        'type' => 'Agency',
                    ]);
                }

                $claimSubType = Lookup::where('key', $claims->claim_type)->get();

                $vehiclePlateNos = PolicyCoverage::join('motor', 'motor.policy_coverage_id', 'policy_coverages.id')->where('policy_coverages.policy_id',$policy->id)->get('motor.registration_no');

                return view('admin.claims.general', compact('groupedRiskAddCoverages','agent_broker','legal','passpostIssueCountry', 'payment_amt', 'reserve_amt', 'balance_amt', 'cityName', 'policy_cellphone', 'storeName', 'reasonName', 'purposeName', 'keyloss', 'partyBanking', 'reportedByOpts', 'partykyc', 'transPayees', 'eventNames',
                'userInfo', 'otherparty', 'otherparty_banking', 'vehicleMakes', 'vehicleModels', 'claimAssessmentCount', 'thirdparty', 'glassClaim', 'transTypes', 'vehicleDetails', 'vehicleDetails', 'salvage', 'salvageyardRole', 'attorneyRole', 'attorney', 'accidentPassenger', 'accidentInjury',
                'fileNames', 'accidentDriver', 'beneficiaries', 'claim_assessment', 'claimAccident', 'assessors', 'vehicle_purpose', 'productFactors', 'recipientKyc', 'deathCauses', 'supplier', 'supplierTypes', 'supplierQuotes', 'userDetails', 'policy', 'claimVehicle', 'claims', 'life', 'lowestQuote',
                'kyc', 'user', 'product', 'vehicle', 'policyMotorItems', 'members', 'banking_details', 'claimVehicleImages', 'productPlan', 'suppliers', 'motor_items', 'lossTypes', 'claimSubTypes', 'users', 'transSubTypes', 'coverages', 'policyProduct', 'attachmentDataCount', 'salvageUser',
                'userRole', 'claimAssessmentReport', 'selectedSalvage', 'claimCellphone', 'repairCenters', 'state', 'stateName', 'isPOSent', 'ClaimRecoveryDetails', 'otherPartyInsured', 'hospitalCash'
                ,'newclaim', 'bi', 'allRisk', 'dw', 'burglary','fg','agents','riskAddress','claimReportedBy','claimSubType','travelIns', 'goodsInTransit', 'fire','coverageClaimData','mobileAndElectDev','vehicle_model','years','vehicle_make','isImported','company','reasons','vehiclePlateNos','selectedReportedBy','agents_options' ));
            } else {
                return redirect()->back()->with('error', 'You dont have access to view this page');
            }
        } catch (\Exception $ex) {
            // dd($ex);
            return Redirect::back()->with('error', $ex->getMessage());
        }

    }

    /**
     * check assured limit the specified claim.
     *
     *
     * @return Json error' => 1, value=>$asssuredLimit->sum_assured
     */
    public function checkSumAssured(Request $request)
    {
        $asssuredLimit = Policy::where('id', $request->get('policy_id'))->whereNotIn('product_id', [7, 8])->first(array('sum_assured','product_id'));
        if ($request->get('claim_type') == 'BUSINESSALLRISKS' || $request->get('claim_type') == 'PERSONALALLRISKS' || $request->get('claim_type') == 'BUSINESSINTERRUPTION' || $request->get('claim_type') == 'THEFT' ||
        $request->get('claim_type') == 'MONEY' || $request->get('claim_type') == 'WORKERSCOMPENSATION' || $request->get('claim_type') == 'STATEDBENEFITS' || $request->get('claim_type') == 'FIDELITYGUARANTEE' || $request->get('claim_type') == 'TRAVELINSURANCE'
        || $request->get('claim_type') == 'GOODSINTRANSIT' || $request->get('claim_type') == 'FIRE'  || $request->get('claim_type') == 'LIABILITY' || $request->get('claim_type') == 'ELECTRONICEQUIPMENT' ||
        $request->get('claim_type') == 'PROPERTYDAMAGE' || $request->get('claim_type') == 'ACCIDENTALDAMAGE' || $request->get('claim_type') == 'DEFECTIVEWORKMANSHIP' || $request->get('claim_type') == 'MOBILEELECTRONICDEVICES'
        || $request->get('claim_type') == 'OFFICECONTENTS' || $request->get('claim_type') == 'HOUSEHOLDERS' || $request->get('claim_type') == 'HOUSEOWNERS' || $request->get('claim_type') == 'HOUSEOWNER-BUILDINGS' || $request->get('claim_type') == 'HOUSEHOLDERS-CONTENTS') {
            if (isset($request->transSubType) || isset($request->lossreserveTransSubType)) {
                if ($request->transSubType == 50 || $request->lossreserveTransSubType == 50) {
                    $asssuredLimit->sum_assured = $asssuredLimit->sum_assured + 20000;
                }
            }
        }

        if ($asssuredLimit->product_id == 3 && $request->coverage_id == 21) {
            $schedule = MotorComprehensiveSchedule::orderBy('id','desc')->first();

            $value = $schedule->third_party_liability_sum_insured;

            $numericValue = floatval(preg_replace('/[^\d.]/', '', str_replace(',', '', $value)));

            $asssuredLimit->sum_assured = $numericValue;
        }
        // // New Logic for Payment Allocation Validation
        // if ($asssuredLimit->product_id == 7 || $asssuredLimit->product_id == 8) {
        //     if (isset($request->payment_allocations) && is_array($request->payment_allocations)) {
        //         foreach ($request->payment_allocations as $allocation) {
        //             if (isset($allocation['product_id'], $allocation['coverage_limit'], $allocation['payment_allocation'])) {
        //                 if (in_array($allocation['product_id'], [7, 8])) {
        //                     if ($allocation['payment_allocation'] > $allocation['coverage_limit']) {
        //                         return response()->json([
        //                             'error' => 1,
        //                             'message' => "Payment Allocation cannot exceed the Coverage Limit for product_id {$allocation['product_id']}.",
        //                         ]);
        //                     }
        //                 }
        //             }
        //         }
        //     }
        // }
        // dd($request->all());
        if (isset($asssuredLimit) && $asssuredLimit->sum_assured < $request->get('sum'))
            return response()->json(['error' => 1, 'value' => $asssuredLimit->sum_assured]);
        else
            return response()->json(['error' => 0]);

    }

    /**
     * coverage data for the specified claim.
     *
     * @param int $claim_id
     * @return Json
     */
    public function coverageData($claim_id)
    {
        $coveragesData = ClaimReservesCoverage::Join('claim_reserves', 'claim_reserves_coverages.reserve_id', 'claim_reserves.id')
            ->where('claim_reserves_coverages.claim_id', $claim_id)
            ->get(array('claim_reserves_coverages.id', 'claim_reserves_coverages.reserve_id', 'claim_reserves_coverages.reserve_amt', 'claim_reserves_coverages.payment_amt', 'claim_reserves_coverages.balance',
            'claim_reserves_coverages.subrogation_reserve', 'claim_reserves_coverages.subrogation_payment', 'claim_reserves_coverages.salvage_reserve', 'claim_reserves_coverages.salvage_payment',
            'claim_reserves.transaction_type', 'claim_reserves.transaction_sub_type', 'claim_reserves.id as claim_res_id', 'claim_reserves.payee', 'claim_reserves.date','claim_reserves_coverages.is_payment_voided'));

        return DataTables::of($coveragesData)
            ->editColumn('payee', function ($coveragesData) {
                if ($coveragesData && $coveragesData->payee != null && $coveragesData->transaction_type != 86) {
                    $payeeName = Supplier::where('id', $coveragesData->payee)->first(array('supplierName'));
                    if ($payeeName != NULL) {
                        return $payeeName->supplierName;
                    } else{
                        return '-';
                    }
                } elseif ($coveragesData && $coveragesData->transaction_type == 86) {
                    $user = User::where('id', $coveragesData->payee)->first();
                    return $coveragesData ? $user->firstName . ' ' . $user->lastName : '-';
                } else {
                    return '-';
                }
            })
            ->editColumn('transaction_type', function ($coveragesData) {
                if ($coveragesData && $coveragesData->transaction_type != null) {
                    $transactionType = Lookup::where('id', $coveragesData->transaction_type)->first('value');
                    if($transactionType && $transactionType->value != null){
                        if (isset($coveragesData->is_payment_voided) && $coveragesData->is_payment_voided == 2) {
                            return $transactionType->value . ' - ' .'VOID';
                        } else {
                            return $transactionType->value;
                        }
                    }else{
                        '-';
                    }

                } else {
                    return '-';
                }
            })
            ->editColumn('transaction_sub_type', function ($coveragesData) {
                if ($coveragesData && $coveragesData->transaction_sub_type != null) {
                    $transactionSubType = Lookup::where('id', $coveragesData->transaction_sub_type)->first('value');
                    if($transactionSubType && $transactionSubType->value != null){
                        return $transactionSubType->value;
                    }else{
                        '-';
                    }

                } else {
                    return '-';
                }
            })
            ->addColumn('reserve_amts', function ($coveragesData) {
                if($coveragesData->transaction_type == 90) //TP Liability Payment
                    return '<span class="kt-font-bold">P' . $coveragesData->subrogation_payment . '</span>';
                else if($coveragesData->transaction_type == 92) //Salvage Payment
                    return '<span class="kt-font-bold">P' . $coveragesData->salvage_payment . '</span>';
                else if ($coveragesData && $coveragesData->reserve_amt != null)
                    return '<span class="kt-font-bold">P' . $coveragesData->reserve_amt . '</span>';
                else
                    return '<span class="kt-font-bold">P0.00</span>';

            })
            ->addColumn('payment_amts', function ($coveragesData) {
                if($coveragesData->transaction_type == 89) //TP Liability Reserve
                    return '<span class="kt-font-bold kt-font-danger">-P' . $coveragesData->subrogation_reserve . '</span>';
                else if($coveragesData->transaction_type == 91) //Salvage Reserve
                    return '<span class="kt-font-bold kt-font-danger">-P' . $coveragesData->salvage_reserve . '</span>';
                else if ($coveragesData && $coveragesData->payment_amt != null) {
                    if ($coveragesData->payment_amt == 0)
                        return '<span class="kt-font-bold">P' . $coveragesData->payment_amt . '</span>';
                    else
                        return '<span class="kt-font-bold kt-font-danger">-P' . $coveragesData->payment_amt . '</span>';
                } else {
                    return '-';
                }
            })
            ->addColumn('baln', function ($coveragesData) {
                if ($coveragesData && $coveragesData->balance != null) {
                    if ($coveragesData->balance < 0) {
                        $amt = '<span class="kt-font-bold">-P' . abs($coveragesData->balance) . '</span>';
                        return $amt;
                    } else {
                        $amt = '<span class="kt-font-bold">P' . $coveragesData->balance . '</span>';
                        return $amt;
                    }
                } else {
                    return '0.00';
                }
            })
            ->addColumn('actions',function($coveragesData) {
                $actions ='';
                if ($coveragesData && $coveragesData->transaction_type != null) {
                    $transactionType = Lookup::where('id', $coveragesData->transaction_type)->first('value');
                    if($transactionType && $transactionType->value != null && ($transactionType->value == 'Loss Payment' || $transactionType->value == 'TP Liability Payment' || $transactionType->value == 'Salvage Payment')){
                        // if ($coveragesData->is_payment_voided == 1) {
                            $actions .= '<button class="voidPaymentBtn btn btn-sm btn-elevate btn-primary btn-elevate" title="void payment"  data-row-id="' . $coveragesData->id . '">
                            <span class="kt-opacity-11" id="">Void Payment</span>
                            </button>';

                            // $actions .= '    ';

                            $actions .= '<button class="voidPaymentInfoBtn btn btn-sm btn-elevate btn-success btn-elevate" title="Void Payment Info" data-row-id="' . $coveragesData->id . '">
                                <span class="kt-opacity-11" id="">Void Info</span>
                            </button>';

                        // }
                    }
                }
                return $actions;
            })
            ->addColumn('highlightedRowId',function($coveragesData) {
                if (isset($coveragesData->is_payment_voided) && $coveragesData->is_payment_voided == 1) {
                    $highlightedRowId = $coveragesData->id;
                } else {
                    $highlightedRowId = 0;
                }
                return $highlightedRowId;
            })
            ->rawColumns(['baln', 'payee', 'transaction_type', 'transaction_sub_type', 'payment_amts', 'reserve_amts','actions','highlightedRowId'])
            ->make(true);
    }

    public function getVoidPaymentInfo($id) {
        try {
            $claimVoidLogs = ClaimVoidPaymentLogs::where('claim_reserves_coverages_id',$id)->orderBy('id','desc')->first();

            if (isset($claimVoidLogs)) {
                $claim = Claim::where('id',$claimVoidLogs->claim_id)->first();
                $claimVoidLogs['claimNumber'] = $claim->claim_number;

                if($claimVoidLogs->payment_void_by != null){
                    $user = User::where('id', $claimVoidLogs->payment_void_by)->first();
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

                    $claimVoidLogs['payment_void_by'] = $userfirstName . ' ' . $userlastName;
                }

                return response()->json([
                    'status' => 'True',
                    'success' => 'Data fetched successfully',
                    'data' => $claimVoidLogs
                ], 200);
            } else {
                return response()->json([
                    'status' => 'True',
                    'error' => 'Data not found for entry'
                ], 201);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'False',
                'error' => $ex->getMessage().' '.$ex->getLine(),
            ], 500);
        }
    }

    public function voidPayment(Request $request) {
        try {
            $id = $request->claimReserveCoverageId;

            $claimReserve = ClaimReservesCoverage::where('id',$id)->first();

            $amtCheck = $request->total_payment - $claimReserve->payment_amt;

            if ($amtCheck < 0) {
                return Redirect::back()->with('error', 'The payment amount cannot be greater than the total payment reserve.');
            }

            $claimReserve->is_payment_voided = 1;
            $claimReserve->save();

            $voidPaymentStore = new ClaimReservesCoverage();
            $voidPaymentStore->claim_id = $claimReserve->claim_id;
            $voidPaymentStore->reserve_id = $claimReserve->reserve_id;
            $voidPaymentStore->payment_amt = 0;
            $voidPaymentStore->reserve_amt = $claimReserve->reserve_amt + $claimReserve->payment_amt;
            $voidPaymentStore->balance = $claimReserve->balance + $claimReserve->payment_amt;
            $voidPaymentStore->coverage_id = $claimReserve->coverage_id;
            $voidPaymentStore->coverage_name = $claimReserve->coverage_name;
            $voidPaymentStore->is_payment_voided = 2; // voided payment entry means 2
            $voidPaymentStore->save();

            $voidPaymentLog = new ClaimVoidPaymentLogs();
            $voidPaymentLog->claim_reserves_coverages_id = $id;
            $voidPaymentLog->claim_id = $claimReserve->claim_id;
            $voidPaymentLog->payment_void_by = auth()->user()->id;
            $voidPaymentLog->payment_void_date = Carbon::now()->format("Y-m-d");
            $voidPaymentLog->amount = $claimReserve->payment_amt;
            $voidPaymentLog->reason_for_void = $request->reason_for_void;
            $voidPaymentLog->save();

            return Redirect::back()->with('success', 'Payment void successfully');

        } catch (\Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage().' '.$ex->getLine());
        }
    }

    function getReserveDeatils($id) {
        try {
            $getClaimReserve = ClaimReservesCoverage::Join('claim_reserves', 'claim_reserves_coverages.reserve_id', 'claim_reserves.id')
            ->where('claim_reserves_coverages.id', $id)
            ->first(array('claim_reserves_coverages.claim_id','claim_reserves_coverages.id', 'claim_reserves_coverages.reserve_id', 'claim_reserves_coverages.reserve_amt', 'claim_reserves_coverages.payment_amt', 'claim_reserves_coverages.balance',
            'claim_reserves_coverages.subrogation_reserve', 'claim_reserves_coverages.subrogation_payment', 'claim_reserves_coverages.salvage_reserve', 'claim_reserves_coverages.salvage_payment',
            'claim_reserves.transaction_type', 'claim_reserves.transaction_sub_type', 'claim_reserves.id as claim_res_id', 'claim_reserves.payee', 'claim_reserves.date',
            'claim_reserves.description','claim_reserves.memo'));

            // $getClaimReserve = ClaimReservesCoverage::where('id',$id)->first();
            if(isset($getClaimReserve)){
                $claim = Claim::where('id',$getClaimReserve->claim_id)->first();
                $getClaimReserve['claimNumber'] = $claim->claim_number;

                if ($getClaimReserve && $getClaimReserve->transaction_type != null) {
                    $transactionType = Lookup::where('id', $getClaimReserve->transaction_type)->first('value');
                    if($transactionType && $transactionType->value != null){
                        $getClaimReserve['transaction_type'] = $transactionType->value;
                    }else{
                        $getClaimReserve['transaction_type'] = '-';
                    }

                } else {
                    $getClaimReserve['transaction_type'] = '-';
                }

                if ($getClaimReserve && $getClaimReserve->transaction_sub_type != null) {
                    $transactionSubType = Lookup::where('id', $getClaimReserve->transaction_sub_type)->first('value');
                    if($transactionSubType && $transactionSubType->value != null){
                        $getClaimReserve['transaction_sub_type'] = $transactionSubType->value;
                    }else{
                        $getClaimReserve['transaction_sub_type'] = '-';
                    }

                } else {
                    $getClaimReserve['transaction_sub_type'] = '-';
                }

                if ($getClaimReserve && $getClaimReserve->payee != null && $getClaimReserve->transaction_type != 86) {
                    $payeeName = Supplier::where('id', $getClaimReserve->payee)->first(array('supplierName'));
                    if ($payeeName != NULL) {
                        $getClaimReserve['payee_name'] = $payeeName->supplierName;
                    } else{
                        $getClaimReserve['payee_name'] = '-';
                    }
                } elseif ($getClaimReserve && $getClaimReserve->transaction_type == 86) {
                    $user = User::where('id', $getClaimReserve->payee)->first();
                    $getClaimReserve['payee_name'] = $getClaimReserve ? $user->firstName . ' ' . $user->lastName : '-';
                } else {
                    $getClaimReserve['payee_name'] = '-';
                }

                return response()->json([
                    'status' => 'True',
                    'success' => 'Data fetched successfully',
                    'data' => $getClaimReserve
                ], 200);
            } else {
                return response()->json([
                    'status' => 'True',
                    'error' => 'Data not found for entry'
                ], 201);
            }

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'False',
                'error' => $ex->getMessage().' '.$ex->getLine(),
            ], 500);
        }
    }

    /**
     * method returns all the attachment data for specified claim.
     *
     * @param int $id (Claim id)
     * @return images
     */
    public function attachmentData($id)
    {
        $data = Attachments::where('claim_id', $id)->get(array('id', 'name', 'type', 'attachment', 'document_type_name'));

        return DataTables::of($data)
            ->addColumn('attachment', function ($data)
            {
                if ($data->attachment == NULL)
                {
                    $images = '<img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" height="auto" >';
                } else
                    {
                    $images = '';
                    foreach (unserialize($data->attachment, ['allowed_classes' => false]) as $file)
                    {
                        $images .= "<a style='padding-right: 10px;' href=" . \AlphaDirect\Helper::getCloudFrontURL($file) . " target='_blank' download>";
                        if (pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                        {
                            $images .= "<img src=" . asset('images/pdf.ico') . " width='200px' height='auto' >";
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                        {
                            $images .= "<img src=" . asset('images/word.ico') . " width='200px' height='auto' >";
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                        {
                            $images .= "<img src=" . asset('images/excel.png.') . " width='200px' height='auto' >";
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                        {
                            $images .= "<img src=" . \AlphaDirect\Helper::getCloudFrontURL($file) . " width='200px' height='auto' >";
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'gif')
                        {
                            $images .= "<img src=" . str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($file)) . " width='200px' height='auto' >";
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'eml')
                        {
                            $images .= "<img src=" . asset('images/eml.png') . " width='200px' height='auto' >";
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'zip')
                        {
                            $images .= "<img src=" . asset('images/zipp.png') . " width='200px' height='auto' >";
                        }
                        else
                        {
                            $images .= "<img src=" . asset('images/doc.png .') . " width='200px' height='auto' >";
                        }
                    }
                    $images .= "</a>";
                }
                return $images;
            })
            ->addColumn('actions',function($data) {
                $actions ='';
                if(auth::user()->hasPermissionTo('attachment-delete')) {
                    $actions .= '<a href="" value="'.$data->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                        <i class="la la-trash"></i>
                    </a>';
                }

                return $actions;
            })
            ->rawColumns(['attachment','actions'])
            ->make(true);
    }
    // public function attachmentData($id)
    // {
    //     $data = Attachments::where('claim_id', $id)->get(array('id', 'claim_id', 'name', 'type', 'attachment'));

    //     return DataTables::of($data)
    //         ->addColumn('attachment', function ($data, $id) {
    //             if ($data->attachment == NULL) {
    //                 $images = '<img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="115px" height="auto" >';
    //             } else {
    //                 $images = '';
    //                 foreach (unserialize($data->attachment) as $k => $file) {
    //                     $images .= "<a style='padding-right: 10px;' href=" . \AlphaDirect\Helper::getCloudFrontURL($file) . " target='_blank' download>";
    //                     if (pathinfo($file, PATHINFO_EXTENSION) == 'pdf') {
    //                         $images .= "<img src=" . asset('images/pdf.ico') . " style='width: 100px;height: auto;' data-claim-id='" . $id . "'  data-id='" . $k . "'>";
    //                     } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm') {
    //                         $images .= "<img src=" . asset('images/word.ico') . " style='width: 100px;height: auto;'  data-claim-id='" . $id . "'  data-id='" . $k . "'>";
    //                     } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv') {
    //                         $images .= "<img src=" . asset('images/excel.png.') . " style='width: 100px;height: auto;'  data-claim-id='" . $id . "'  data-id='" . $k . "'>";
    //                     } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png') {
    //                         $images .= "<img src=" . \AlphaDirect\Helper::getCloudFrontURL($file) . " style='width: 100px;height: auto;'  data-claim-id='" . $id . "'  data-id='" . $k . "'>";
    //                     } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'gif') {
    //                         $images .= "<img src=" . str_replace(env('AWS_URL'), env('AWS_CLOUDFRONT'), Storage::disk('s3')->url($file)) . " style='width: 100px;height: auto;'  data-claim-id='" . $id . "'  data-id='" . $k . "'>";
    //                     } else {
    //                         $images .= "<img src=" . asset('images/doc.png .') . " style='width: 100px;height: auto;'  data-claim-id='" . $id . "'  data-id='" . $k . "'>";
    //                     }
    //                 }
    //                 $images .= "</a>";
    //             }
    //             return $images;
    //         })
    //         ->addColumn('actions', function ($data) {
    //             $actions = '';
    //             if (Auth::user()->hasPermissionTo('attachment-delete')) {
    //                 $actions .= '<a href="" value="' . $data->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
    //                     <i class="la la-trash"></i>
    //                 </a>';
    //             }

    //             return $actions;
    //         })
    //         ->rawColumns(['attachment', 'actions'])
    //         ->make(true);
    // }

    public function getModalDelete(Request $request)
    {
        $check = Attachments::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves

        $body = 'Are you sure you want to delete the Attachments ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);

    }

    public function destroy($id)
    {
        try {
            activity('Claim Attachments')
                ->performedOn(Attachments::where('id', $id)->first())
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Attachment Deleted');
            $attachment = Attachments::where('id', $id)->delete();
            return redirect()->back()->with('success', 'Attachments Deleted Successfully');

        } catch (TeacherNotFoundException $e) {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }

    }


    /**
     * method stores all the attachment data for specified claim.
     *
     * @param int $claim_id (Claim id)
     * @return images
     */
    public function attachmentUpload(Request $request, $claim_id)
    {
        $data = array();
        $names = $request->name;
        $document_type_name = $request->document_type_name;
        $types = $request->type;
        $files = $request->attachment_file;
        // dd($request->all());
        foreach ($types as $key => $type) {
            $fileStore = array();
            $attachment = new Attachments();
            $attachment->name = $names[$key];
            $attachment->document_type_name = $document_type_name[$key];
            $attachment->claim_id = $claim_id;
            $attachment->type = $type;
            if ($files != NULL) {
                $fileStore = array();
                foreach ($files[$key] as $file) {
                    $name = preg_replace('/\s+/', '', $file->getClientOriginalName());
                    // BUGFIX — claims attachment cross-contamination.
                    // The S3 key was 'MIS/Claims/Attachment/<originalName>': no
                    // claim scope and no uniqueness. Two claims uploading a
                    // same-named file (invoice.pdf, police_report.pdf, ...) wrote
                    // to the SAME key — the later upload overwrote the earlier, so
                    // CloudFront served one object for every claim that stored that
                    // path, making one claim's documents appear on unrelated claims.
                    // Scope by claim_id and add a unique token so keys never collide.
                    // (Mirrors ClaimsV2Controller::uploadDocument, which already
                    // stores under a claim-scoped, hashed key.)
                    $filePath = 'MIS/' . 'Claims' . '/' . 'Attachment' . '/' . $claim_id . '/' . uniqid() . '_' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $fileStore[] = $filePath;
                }
            }
            $attachment->attachment = serialize($fileStore);
            $attachment->save();
        }
        session()->put('step', '8');
        activity('Claim Attachment')
            ->performedOn($attachment)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Attachment Stored Successfully');
        return redirect()->back()->with('success', 'Attachment stored Successfully');
    }

    /**
     * method updates specified claim status.
     *
     * @param int $id (Claim id)
     * @return claim general page
     */
    public function statusUpdate(Request $request, $id)
    {
        $claims = Claim::where('id', $id)->first();
        $claims->status = $request->claim_status;
        $claims->save();
        $policy = Policy::where('id', $claims->policy_id)->first();
        if ($claims->claim_type == "Accident") {
            $claimAccident = ClaimAccident::where('claim_id', $id)->first();
            $claimAccident->claim_allocated_on = Carbon::parse($request->claim_allocated_on)->format('Y-m-d');
            $claimAccident->claim_allocated_to = $request->claim_allocated_to;
            $claimAccident->reserve_amount = $request->reserve_amount;
            $claimAccident->paid_amount = $request->paid_amount;
            if ($request->claim_status == "Rejected") {
                $claimAccident->claim_status = $request->claim_status;
                $claimAccident->note = $request->note;
                $claimAccident->reason = $request->reason;
                $claimAccident->is_salvage_yard = NULL;
                $claimAccident->salvage_yard_id = NULL;
                $claimAccident->claim_sub_status = NULL;
                if ($request->hasFile('document')) {
                    $file = $request->file('document');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'Document' . '/File' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $claimAccident->document = $filePath;
                }
            } elseif ($request->claim_status == "Approved") {
                $claimAccident->claim_status = $request->claim_status;
                $claimAccident->note = NULL;
                $claimAccident->reason = NULL;
                if ($request->claim_sub_status == "Repair") {
                    $claimAccident->claim_sub_status = $request->claim_sub_status;
                    $claimAccident->is_salvage_yard = NULL;
                    $claimAccident->document = NULL;
                    $claimAccident->salvage_yard_id = NULL;
                } elseif ($request->claim_sub_status == "Cash In Lieu") {
                    $claimAccident->claim_sub_status = $request->claim_sub_status;
                    $claimAccident->is_salvage_yard = NULL;
                    $claimAccident->salvage_yard_id = NULL;
                    $otherparty = array();
                    foreach ($request->beneficiary_status_details as $key => $status_details) {
                        /*storing other party details */
                        if ($status_details['check_beneficiary'] == 'other_party') {
                            $c = ClaimAccidentOtherPartyBanking::where('claim_thirdParty_id', $status_details['otherparty_list'])->where('claim_id', $status_details['claim_id'])->first();
                            if ($c != NUll) {
                                /*updating other party bank records*/
                                $c->claim_id = $status_details['claim_id'];
                                $c->claim_thirdParty_id = $status_details['otherparty_list'];
                                $c->billingCell = $status_details['accident_billingCell'];
                                $c->bankName = $status_details['accident_bankName'];
                                $c->branchCode = $status_details['accident_branchCode'];
                                $c->accountNumber = $status_details['accident_accountNumber'];
                                /*                                    $c->bankAccountType = $status_details['accident_bankAccountType'];*/
                                $c->save();
                            } else {
                                /*adding new other party bank records*/
                                $c = new ClaimAccidentOtherPartyBanking();
                                $c->claim_id = $claims->id;
                                $c->claim_thirdParty_id = $status_details['otherparty_list'];
                                $c->billingCell = $status_details['accident_billingCell'];
                                $c->bankName = $status_details['accident_bankName'];
                                $c->branchCode = $status_details['accident_branchCode'];
                                $c->accountNumber = $status_details['accident_accountNumber'];
                                $c->save();
                            }

                            array_push($otherparty, $c->claim_thirdParty_id);

                            $p = ClaimAccidentOtherPartyKyc::where('thirdParty_id', $status_details['otherparty_list'])->where('claim_id', $status_details['claim_id'])->first();;
                            if ($p != NULL) {
                                $p->claim_id = $claims->id;
                                $p->thirdParty_id = $status_details['otherparty_list'];
                                if (!empty($status_details['driving_license'])) {
                                    $file = $status_details['driving_license'];
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/driving_license' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->driving_license = $filePath;
                                }

                                if (!empty($status_details['omang'])) {
                                    $file = ($status_details['omang']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/omang' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->omang_pic = $filePath;
                                }

                                if (!empty($status_details['proof_residence'])) {
                                    $file = ($status_details['proof_residence']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_residence' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_residence = $filePath;
                                }

                                if (!empty($status_details['proof_income'])) {
                                    $file = ($status_details['proof_income']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_income' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_income = $filePath;
                                }

                                if (!empty($status_details['passport'])) {
                                    $file = ($status_details['passport']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/passport' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->passport_pic = $filePath;
                                }
                                $p->save();
                            } else {
                                /*adding other party kyc records*/
                                $p = new ClaimAccidentOtherPartyKyc();
                                $p->claim_id = $status_details['claim_id'];
                                $p->thirdParty_id = $status_details['otherparty_list'];

                                if (!empty($status_details['driving_license'])) {
                                    $file = ($status_details['driving_license']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/driving_license' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->driving_license = $filePath;
                                }

                                if (!empty($status_details['omang'])) {
                                    $file = ($status_details['omang']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/omang' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->omang_pic = $filePath;
                                }

                                if (!empty($status_details['proof_residence'])) {
                                    $file = ($status_details['proof_residence']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_residence' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_residence = $filePath;
                                }

                                if (!empty($status_details['proof_income'])) {
                                    $file = ($status_details['proof_income']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_income' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_income = $filePath;
                                }

                                if (!empty($status_details['passport'])) {
                                    $file = ($status_details['passport']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/passport' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->passport_pic = $filePath;
                                }
                                $p->save();
                            }
                        } else {
                            $c = CustomerBanking::where('customer_id', $status_details['customer_id'])->where('claim_id', $status_details['claim_id'])->first();
                            if ($c != NULL) {
                                /*updating customer bank records*/
                                $c->customer_id = $status_details['customer_id'];
                                $c->policy_id = $claims->policy_id;
                                $c->claim_id = $status_details['claim_id'];
                                $c->billingCell = $status_details['accident_billingCell'];
                                $c->bankName = $status_details['accident_bankName'];
                                $c->branchCode = $status_details['accident_branchCode'];
                                $c->accountNumber = $status_details['accident_accountNumber'];
                                $c->save();
                            } else {
                                /*adding new customer bank records*/
                                $c = new CustomerBanking();
                                $c->customer_id = $status_details['customer_id'];
                                $c->policy_id = $claims->policy_id;
                                $c->claim_id = $status_details['claim_id'];
                                $c->billingCell = $status_details['accident_billingCell'];
                                $c->bankName = $status_details['accident_bankName'];
                                $c->branchCode = $status_details['accident_branchCode'];
                                $c->accountNumber = $status_details['accident_accountNumber'];
                                $c->save();
                            }
                            /* storing customer_id in claimAccident table */
                            $claimAccident_customer = ClaimAccident::where('claim_id', $status_details['claim_id'])->first();
                            $claimAccident_customer->approved_customer = $status_details['customer_id'];
                            $claimAccident_customer->save();

                            $p = KYC::where('customer_id', $status_details['customer_id'])->first();
                            if ($p != NULL) {
                                /*updating customer kyc records*/
                                $p->customer_id = $status_details['customer_id'];
                                if (!empty($status_details['driving_license'])) {
                                    $file = ($status_details['driving_license']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/driving_license' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->driving_license = $filePath;
                                }

                                if (!empty($status_details['omang'])) {
                                    $file = ($status_details['omang']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/omang' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->omang = $filePath;
                                }

                                if (!empty($status_details['proof_residence'])) {
                                    $file = ($status_details['proof_residence']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_residence' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_residence = $filePath;
                                }

                                if (!empty($status_details['proof_income'])) {
                                    $file = ($status_details['proof_income']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_income' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_income = $filePath;
                                }

                                if (!empty($status_details['passport'])) {
                                    $file = ($status_details['passport']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/passport' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->passport = $filePath;
                                }
                                $p->save();
                            } else {
                                /*adding customer new kyc records*/
                                $p = new KYC();
                                $p->customer_id = $status_details['customer_id'];

                                if (!empty($status_details['driving_license'])) {
                                    $file = ($status_details['driving_license']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/driving_license' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->driving_license = $filePath;
                                }

                                if (!empty($status_details['omang'])) {
                                    $file = ($status_details['omang']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/omang' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->omang = $filePath;
                                }

                                if (!empty($status_details['proof_residence'])) {
                                    $file = ($status_details['proof_residence']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_residence' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_residence = $filePath;
                                }

                                if (!empty($status_details['proof_income'])) {
                                    $file = ($status_details['proof_income']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_income' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->proof_income = $filePath;
                                }

                                if (!empty($status_details['passport'])) {
                                    $file = ($status_details['passport']);
                                    $name = $file->getClientOriginalName();
                                    $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/passport' . '/' . $name;
                                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $p->passport = $filePath;
                                }
                                $p->save();
                            }
                        }
                    }
                    /* storing otherPArty_id in claimAccident table */
                    $claimAccident_otherParty = ClaimAccident::where('claim_id', $claims->id)->first();
                    $claimAccident_otherParty->approved_otherParty = json_encode($otherparty);
                    $claimAccident_otherParty->save();

                    if ($request->hasFile('document')) {
                        $file = $request->file('document');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'Document' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claimAccident->document = $filePath;
                    }
                } elseif ($request->claim_sub_status == "Write Off") {
                    $claimAccident->claim_sub_status = $request->claim_sub_status;
                    $claimAccident->write_off_date = now();
                    if ($request->salvage == NULL) {
                        $claimAccident->is_salvage_yard = 0;
                    } else {
                        $claimAccident->is_salvage_yard = 1;
                    }
                    if ($request->salvage != NULL) {
                        $claimAccident->salvage_yard_id = $request->salvage_yard;
                        $user = User::where('id', $request->salvage_yard)->first(array('email'));
                        $data = new \stdClass();
                        $data->user_id = $request->salvage_yard;
                        $data->hook = 'salvage_yard';
                        $data->attachment = NULL;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));
                      //  Mail::to($user->email)->send(new MailTemplate($data));
                    }
                    $otherparty = array();
                    foreach ($request->beneficiary_status_details as $key => $status_details) {
                        if ($status_details['claim_id'] != NULL) {
                            /*storing other party details */
                            if ($status_details['check_beneficiary'] == 'other_party') {
                                $c = ClaimAccidentOtherPartyBanking::where('claim_thirdParty_id', $status_details['otherparty_list'])->where('claim_id', $status_details['claim_id'])->first();
                                if ($c != NUll) {
                                    /*updating other party bank records*/
                                    $c->claim_id = $status_details['claim_id'];
                                    $c->claim_thirdParty_id = $status_details['otherparty_list'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                } else {
                                    /*adding new other party bank records*/
                                    $c = new ClaimAccidentOtherPartyBanking();
                                    $c->claim_id = $claims->id;
                                    $c->claim_thirdParty_id = $status_details['otherparty_list'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                }
                                array_push($otherparty, $c->claim_thirdParty_id);
                                $p = ClaimAccidentOtherPartyKyc::where('thirdParty_id', $status_details['otherparty_list'])->where('claim_id', $status_details['claim_id'])->first();;
                                if ($p != NULL) {
                                    /*updating other party kyc records*/
                                    $p->claim_id = $claims->id;
                                    $p->thirdParty_id = $status_details['otherparty_list'];
                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang_pic = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }
                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport_pic = $filePath;
                                    }
                                    $p->save();
                                } else {
                                    /*adding other party kyc records*/
                                    $p = new ClaimAccidentOtherPartyKyc();
                                    $p->claim_id = $status_details['claim_id'];
                                    $p->thirdParty_id = $status_details['otherparty_list'];
                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang_pic = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }
                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport_pic = $filePath;
                                    }
                                    $p->save();
                                }
                            } else {
                                $c = CustomerBanking::where('customer_id', $status_details['customer_id'])->where('claim_id', $status_details['claim_id'])->first();
                                if ($c != NULL) {
                                    /*updating customer bank records*/
                                    $c->customer_id = $status_details['customer_id'];
                                    $c->policy_id = $claims->policy_id;
                                    $c->claim_id = $status_details['claim_id'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                } else {
                                    /*adding new customer bank records*/
                                    $c = new CustomerBanking();
                                    $c->customer_id = $status_details['customer_id'];
                                    $c->policy_id = $claims->policy_id;
                                    $c->claim_id = $status_details['claim_id'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                }
                                /* storing customer_id in claimAccident table */
                                $claimAccident_customer = ClaimAccident::where('claim_id', $status_details['claim_id'])->first();
                              //  $claimAccident_customer->approved_customer =23784;// $status_details['customer_id'];
                                $claimAccident_customer->save();

                                $p = KYC::where('customer_id', $status_details ['customer_id'])->first();
                                if ($p != NULL) {
                                    /*updating customer kyc records*/
                                    $p->customer_id = $status_details['customer_id'];
                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }
                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport = $filePath;
                                    }
                                    $p->save();
                                } else {
                                    /*adding customer new kyc records*/
                                    $p = new KYC();
                                    $p->customer_id = $status_details['customer_id'];

                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }

                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport = $filePath;
                                    }
                                    $p->save();
                                }
                            }
                        }
                    }
                    /* storing otherparty_id in claimAccident table */
                    $claimAccident_otherParty = ClaimAccident::where('claim_id', $claims->id)->first();
                  //  $claimAccident_otherParty->approved_otherParty = json_encode($otherparty);
                    $claimAccident_otherParty->save();
                    if ($request->hasFile('document')) {
                        $file = $request->file('document');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'Document' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claimAccident->document = $filePath;
                    }
                } elseif ($request->claim_sub_status == "Exgratia") {
                    $claimAccident->claim_sub_status = $request->claim_sub_status;
                    $claimAccident->is_salvage_yard = NULL;
                    $claimAccident->salvage_yard_id = NULL;
                    $otherparty = array();
                    foreach ($request->beneficiary_status_details as $key => $status_details) {
                        if ($status_details['claim_id'] != NULL) {
                            /*storing other party details */
                            if ($status_details['check_beneficiary'] == 'other_party') {
                                $c = ClaimAccidentOtherPartyBanking::where('claim_thirdParty_id', $status_details['otherparty_list'])->where('claim_id', $status_details['claim_id'])->first();
                                if ($c != NUll) {
                                    /*updating other party bank records*/
                                    $c->claim_id = $status_details['claim_id'];
                                    $c->claim_thirdParty_id = $status_details['otherparty_list'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                } else {
                                    /*adding new other party bank records*/
                                    $c = new ClaimAccidentOtherPartyBanking();
                                    $c->claim_id = $claims->id;
                                    $c->claim_thirdParty_id = $status_details['otherparty_list'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                }
                                array_push($otherparty, $c->claim_thirdParty_id);
                                $p = ClaimAccidentOtherPartyKyc::where('thirdParty_id', $status_details['otherparty_list'])->where('claim_id', $status_details['claim_id'])->first();;
                                if ($p != NULL) {
                                    /*updating other party kyc records*/
                                    $p->claim_id = $claims->id;
                                    $p->thirdParty_id = $status_details['otherparty_list'];
                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang_pic = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }
                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport_pic = $filePath;
                                    }
                                    $p->save();
                                } else {
                                    /*adding other party kyc records*/
                                    $p = new ClaimAccidentOtherPartyKyc();
                                    $p->claim_id = $status_details['claim_id'];
                                    $p->thirdParty_id = $status_details['otherparty_list'];
                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang_pic = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }
                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->thirdParty_id . '/' . 'OtherParty' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport_pic = $filePath;
                                    }
                                    $p->save();
                                }
                            } else {
                                $c = CustomerBanking::where('customer_id', $status_details['customer_id'])->where('claim_id', $status_details['claim_id'])->first();
                                if ($c != NULL) {
                                    /*updating customer bank records*/
                                    $c->customer_id = $status_details['customer_id'];
                                    $c->policy_id = $claims->policy_id;
                                    $c->claim_id = $status_details['claim_id'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                } else {
                                    /*adding new customer bank records*/
                                    $c = new CustomerBanking();
                                    $c->customer_id = $status_details['customer_id'];
                                    $c->policy_id = $claims->policy_id;
                                    $c->claim_id = $status_details['claim_id'];
                                    $c->billingCell = $status_details['accident_billingCell'];
                                    $c->bankName = $status_details['accident_bankName'];
                                    $c->branchCode = $status_details['accident_branchCode'];
                                    $c->accountNumber = $status_details['accident_accountNumber'];
                                    $c->save();
                                }
                                /* storing customer_id in claimAccident table */
                                $claimAccident_customer = ClaimAccident::where('claim_id', $status_details['claim_id'])->first();
                                $claimAccident_customer->approved_customer = $status_details['customer_id'];
                                $claimAccident_customer->save();

                                $p = KYC::where('customer_id', $status_details['customer_id'])->first();
                                if ($p != NULL) {
                                    /*updating customer kyc records*/
                                    $p->customer_id = $status_details['customer_id'];
                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }
                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport = $filePath;
                                    }
                                    $p->save();
                                } else {
                                    /*adding customer new kyc records*/
                                    $p = new KYC();
                                    $p->customer_id = $status_details['customer_id'];
                                    if (!empty($status_details['driving_license'])) {
                                        $file = ($status_details['driving_license']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/driving_license' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->driving_license = $filePath;
                                    }
                                    if (!empty($status_details['omang'])) {
                                        $file = ($status_details['omang']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/omang' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->omang = $filePath;
                                    }
                                    if (!empty($status_details['proof_residence'])) {
                                        $file = ($status_details['proof_residence']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_residence' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_residence = $filePath;
                                    }
                                    if (!empty($status_details['proof_income'])) {
                                        $file = ($status_details['proof_income']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/proof_income' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->proof_income = $filePath;
                                    }
                                    if (!empty($status_details['passport'])) {
                                        $file = ($status_details['passport']);
                                        $name = $file->getClientOriginalName();
                                        $filePath = 'MIS/' . $p->customer_id . '/' . 'customer' . '/passport' . '/' . $name;
                                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                        $p->passport = $filePath;
                                    }
                                    $p->save();
                                }
                            }
                        }
                    }
                    /* storing otherParty_id in claimAccident table */
                    $claimAccident_otherParty = ClaimAccident::where('claim_id', $claims->id)->first();
                    $claimAccident_otherParty->approved_otherParty = json_encode($otherparty);
                    $claimAccident_otherParty->save();

                    if ($request->hasFile('document')) {
                        $file = $request->file('document');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'Document' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claimAccident->document = $filePath;
                    }
                } elseif ($request->claim_sub_status == "Subrogation") {
                    $claimAccident->claim_sub_status = $request->claim_sub_status;

                    if ($request->hasFile('notice_of_demand')) {
                        $file = $request->file('notice_of_demand');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'notice_of_demand' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claimAccident->notice_of_demand = $filePath;
                    }
                      if ($request->hasFile('final_demand_letter')) {
                        $file = $request->file('final_demand_letter');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'final_demand_letter' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claimAccident->final_demand_letter = $filePath;
                    }
                    if ($request->hasFile('debt_acknowledgment')) {
                        $file = $request->file('debt_acknowledgment');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'debt_acknowledgment' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claimAccident->debt_acknowledgment = $filePath;
                    }
                      if ($request->hasFile('kyc_form')) {
                        $file = $request->file('kyc_form');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $claimAccident->claim_id . '/' . 'kyc_form' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $claimAccident->kyc_form = $filePath;
                    }
                }
            } else {
                $claimAccident->claim_status = $request->claim_status;
                $claimAccident->claim_sub_status = NULL;
                $claimAccident->is_salvage_yard = NULL;
                $claimAccident->salvage_yard_id = NULL;
                $claimAccident->document = NULL;
                $claimAccident->note = NULL;
                $claimAccident->reason = NULL;
            }
            //dd($claimAccident);
            $claimAccident->save();
            // monika code

            Log::info('Claim write-off check started', [
                'claim_accident_id' => $claimAccident->id,
                'claim_sub_status'  => $claimAccident->claim_sub_status
            ]);

            if (
                !empty($claimAccident->claim_sub_status) &&
                strtolower(trim($claimAccident->claim_sub_status)) === 'write off'
            ) {

                Log::info('Claim sub status is Write Off', [
                    'claim_accident_id' => $claimAccident->id
                ]);

                $data = DB::table('claim_accidents as ca')
                    ->join('new_claims as nc', 'nc.id', '=', 'ca.claim_id')
                    ->join('policies as p', 'p.policyNumber', '=', 'nc.policyNumber')
                    ->where('ca.claim_sub_status', 'Write Off')
                    ->where('ca.id', $claimAccident->id)
                    ->select(
                        'nc.vehicle_plate',
                        'ca.write_off_date',
                        'p.id as policy_id'
                    )
                    ->first();

                if (!$data) {
                    Log::warning('No claim/policy data found for write-off', [
                        'claim_accident_id' => $claimAccident->id
                    ]);
                    return;
                }

                Log::info('Claim policy data fetched', [
                    'policy_id'     => $data->policy_id,
                    'vehicle_plate' => $data->vehicle_plate,
                    'write_off_date'=> $data->write_off_date
                ]);

               $actions = PolicyAction::where('policy_id', $data->policy_id)
                            ->whereNull('deleted_at')
                            ->where(function ($q) use ($data) {
                                $q->where(function ($q2) use ($data) {
                                    $q2->whereDate('effective_from', '<=', $data->write_off_date)
                                    ->whereDate('effective_to', '>=', $data->write_off_date);
                                })
                                ->orWhereDate('effective_from', '>', $data->write_off_date);
                            })
                            ->orderBy('effective_from', 'ASC')
                            ->get();

                if ($actions->isEmpty()) {
                    Log::warning('No policy actions found after write-off date', [
                        'policy_id' => $data->policy_id,
                        'write_off_date' => $data->write_off_date
                    ]);
                    return;
                }

                Log::info('Policy actions found', [
                    'policy_id'   => $data->policy_id,
                    'action_count'=> $actions->count()
                ]);

                foreach ($actions as $action) {

                    Log::info('Processing policy action', [
                        'action_id' => $action->id
                    ]);

                    $lastMotor = Motor::join('policy_coverages as pc', 'pc.id', '=', 'motor.policy_coverage_id')
                        ->join('policy_actions as pa', 'pa.id', '=', 'pc.action_id')
                        ->where('motor.registration_no', $data->vehicle_plate)
                        ->where('pc.action_id', $action->id)
                        ->whereNull('motor.deleted_at')
                        //->where('motor.write_off', 1)
                        ->orderBy('pa.id', 'DESC')
                        ->select('motor.id')
                        ->first();

                    if (!$lastMotor) {
                        Log::info('No write-off motor found for action', [
                            'action_id' => $action->id,
                            'vehicle_plate' => $data->vehicle_plate
                        ]);
                        continue;
                    }

                    Log::info('Write-off motor found, updating', [
                        'motor_id' => $lastMotor->id,
                        'action_id'=> $action->id
                    ]);

                    Motor::where('id', $lastMotor->id)->update([
                        'deleted_at' => now(),
                        'write_off'  => 1,
                    ]);

                    Log::info('Motor updated successfully', [
                        'motor_id' => $lastMotor->id
                    ]);
                }
            } else {
                Log::info('Claim sub status is not Write Off, skipping', [
                    'claim_accident_id' => $claimAccident->id
                ]);
            }

        }
        activity('Claim Status')
            ->performedOn($claims)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log(' Claim Status Change to ' . $claimAccident->claim_status);
        return redirect()->back()->with('success', 'Claim Status Updated Successfully !');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, $id)
    {
        $claims = Claim::where('id', $id)->first();
        // For Hospital CashBack claims, don't update registered_claim (preserve existing value)
        // For other claim types, update if provided in request
        if (trim($claims->claim_type) != 'Hospital CashBack' && trim($claims->claim_type) != 'Hospital Cash') {
            if ($request->has('registered_claim') && $request->registered_claim) {
                try {
                    $claims->registered_claim = Carbon::parse($request->registered_claim)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep existing registered_claim if parsing fails
                }
            }
        }
        $claims->save();
        $policy = Policy::where('id', $claims->policy_id)->first();
        if ($claims->claim_type == "Accident" || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL') {

            $ServiceRepresentative = User::where('id',$request->ServiceRepresentative)->first(array('id', 'firstName', 'lastName'));
            if (isset($ServiceRepresentative)) {
                $ServiceRepresentative = $ServiceRepresentative->firstName . ' ' . $ServiceRepresentative->lastName;
            }

            $ClaimsAllocatedTo = User::where('id',$request->ClaimsAllocatedTo)->first(array('id', 'firstName', 'lastName'));
            if (isset($ClaimsAllocatedTo)) {
                $ClaimsAllocatedTo = $ClaimsAllocatedTo->firstName . ' ' . $ClaimsAllocatedTo->lastName;
            }

            $request['representative'] = $ServiceRepresentative;
            $request['loss_description'] = $request->description_of_loss;
            $request['catastrophe'] = $request->CatastropheLoss;
            $request['primary_attorney'] = $request->PrimaryAttorneyAssigned;
            $request['co_attorney'] = $request->CoAttorneyAssigned;
            $request['dfs_complaint'] = $request->DFSComplaint;
            $request['attorney_assigned_date'] = isset($request->p_a_AssignedDate) ? $request->p_a_AssignedDate : $request->c_a_AssignedDate;
            $request['claim_allocated_to'] = $ClaimsAllocatedTo;
            $request['claim_allocated_on'] = $request->claimsAllocatedOn;
            $request['first_visit'] = $request->DateFirstVisited;
            $request['reported_by'] = $request->ClaimReportedby;

            $claimAccident = ClaimAccident::where('claim_id', $id)->first();
            $claimAccident->recovery_involved = $request->pa_involved;
            $claimAccident->attorney_involved = $request->attorney_involved;
            $claimAccident->date_of_accident = $request->date_of_accident;
            $claimAccident->place_of_accident = $request->place_of_accident;
            $claimAccident->time_of_accident = $request->time_of_accident;

            if ($request->pa_involved == "on") {
                $recoveryDetails = ClaimRecoveryInvolved::where('claim_id', $id)->first();
                if ($recoveryDetails) {
                    $recoveryDetails->recovery_name = $request->recovery_name;
                    $recoveryDetails->recovery_address = $request->recovery_address;
                    $recoveryDetails->recovery_phone = $request->recovery_phone;
                    $recoveryDetails->recovery_email = $request->recovery_email;
                    $recoveryDetails->recovery_place_employment = $request->recovery_place_employment;
                    $recoveryDetails->recovery_work_phone = $request->recovery_work_phone;
                    $recoveryDetails->save();
                } else {
                    $recoveryDetails = new ClaimRecoveryInvolved();
                    $recoveryDetails->claim_id = $claims->id;
                    $recoveryDetails->recovery_name = $request->recovery_name;
                    $recoveryDetails->recovery_address = $request->recovery_address;
                    $recoveryDetails->recovery_phone = $request->recovery_phone;
                    $recoveryDetails->recovery_email = $request->recovery_email;
                    $recoveryDetails->recovery_place_employment = $request->recovery_place_employment;
                    $recoveryDetails->recovery_work_phone = $request->recovery_work_phone;
                    $recoveryDetails->save();
                }
            }

            if ($claimAccident->attorney_involved == "on") {
                $claimAccident->attorney_id = $request->attorney;
            } else {
                $claimAccident->attorney_id = null;
            }
            if ($request->third_party == NULL) {
                $claimAccident->third_party = "0";
            } else {
                $claimAccident->third_party = "1";
            }
            $claimAccident->relation = $request->reported_by;
            $claimAccident->claim_sub_type = $request->claim_sub_type;
            $claimAccident->loss_type = $request->loss_type;
            $claimAccident->representative = $request->representative;
            $claimAccident->catastrophe_loss = $request->catastrophe;
            $claimAccident->fault_party = $request->fault_party;
            $claimAccident->event_name = $request->event_name;
            $claimAccident->loss_description = $request->loss_description;
            $claimAccident->primary_attorney_assigned = $request->primary_attorney;
            $claimAccident->co_attorney_assigned = $request->co_attorney;
            $claimAccident->claim_number = $request->claim_number;
            $claimAccident->dfs_complaint = $request->dfs_complaint;
            // $claimAccident->incident_date = Carbon::parse($request->incident_date)->format('Y-m-d');
            $claimAccident->attorney_assigned_date = Carbon::parse($request->attorney_assigned_date)->format('Y-m-d');
            $claimAccident->co_attorney_assigned_date = Carbon::parse($request->co_attorney_assigned_date)->format('Y-m-d');
            $claimAccident->first_visit = Carbon::parse($request->first_visit)->format('Y-m-d');
            $claimAccident->save();

            /*storing third party details*/
            if ($policy->kyc_recipient == 1) {
                $recipientKyc = RecipientKyc::where('claim_id', $claims->id)->first();
                $recipientKyc->policy_id = $claims->policy_id;
                $recipientKyc->claim_id = $claims->id;
                if ($request->hasFile('driving_license')) {
                    $file = $request->file('driving_license');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->driving_license = $filePath;
                }
                if ($request->hasFile('omang')) {
                    $file = $request->file('omang');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->omang = $filePath;
                }
                if ($request->hasFile('proof_residence')) {
                    $file = $request->file('proof_residence');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->proof_residence = $filePath;
                }
                if ($request->hasFile('proof_income')) {
                    $file = $request->file('proof_income');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->proof_income = $filePath;
                }
                if ($request->hasFile('passport')) {
                    $file = $request->file('passport');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->passport = $filePath;
                }
                $recipientKyc->save();
            }

            // dd($request->all());
            if ($request->get('old_tp_insured_id') != NULL)
            {
                foreach ($request->get('old_tp_insured_id') as $key => $oldTpInsuredId)
                {
                    $OtherPartyInsured = OtherPartyInsured::where('id', $oldTpInsuredId)->first();
                    if (isset($OtherPartyInsured)) {
                        $OtherPartyInsured->claim_id = $claims->id;
                        $OtherPartyInsured->first_name_insured = $request->get('old_first_name_insured')[$key];
                        $OtherPartyInsured->last_name_insured = $request->get('old_last_name_insured')[$key];
                        $OtherPartyInsured->cellphone_insured = $request->get('old_cellphone_insured')[$key];
                        $OtherPartyInsured->email_insured = $request->get('old_email_insured')[$key];
                        $OtherPartyInsured->address_insured = $request->get('old_address_insured')[$key];
                        $OtherPartyInsured->save();
                    }
                }
            }

            if ($request->get('old_thirdMemberId') != NULL) {
                foreach ($request->get('old_thirdMemberId') as $key => $thirdMemberId) {
                    $thirdparty = ClaimThirdParty::where('id', $thirdMemberId)->first();
                    $thirdparty->claim_id = $claims->id;
                    $thirdparty->first_name = $request->get('old_thirdMemberName')[$key];
                    $thirdparty->last_name = $request->get('old_thirdMemberlast_Name')[$key];
                    $thirdparty->address = $request->get('old_thirdMemberAddress')[$key];
                    $thirdparty->cellphone = $request->get('old_thirdMemberNumber')[$key];
                    $thirdparty->registration_no = $request->get('old_thirdMemberReg')[$key];
                    $thirdparty->make = $request->get('old_thirdMemberMake')[$key];
                    $thirdparty->model = $request->get('old_thirdMemberModel')[$key];
                    $thirdparty->damage_details = $request->get('old_thirdMemberDetails')[$key];
                    $thirdparty->injured_name = $request->get('old_thirdMemberInjured_name')[$key];
                    $thirdparty->relationship = $request->get('old_thirdMemberInjured_relationship')[$key];
                    $thirdparty->hospital_name = $request->get('old_thirdMemberInjured_hospital')[$key];
                    $thirdparty->injured_details = $request->get('old_thirdMemberInjured_details')[$key];
                    $thirdparty->save();
                }
            }
            if ($request->get('thirdMember') != NULL) {
                foreach ($request->get('thirdMember') as $key => $thirdMember) {
                    if (isset($thirdMember['third_party_insured'])) {
                        foreach ($thirdMember['third_party_insured'] as $key => $value) {
                            $claimAccident->third_party_insured = "1";
                            $claimAccident->save();
                        }

                        if ($thirdMember['first_name_insured'] != null) {
                            $OtherPartyInsured = new OtherPartyInsured();
                            if ($claims->id != null) {
                                $OtherPartyInsured->claim_id = $claims->id;
                            }
                            if (!empty($thirdMember['first_name_insured'])) {
                                $OtherPartyInsured->first_name_insured = $thirdMember['first_name_insured'];
                            }else{
                                $OtherPartyInsured->first_name_insured = null;
                            }
                            if (!empty($thirdMember['last_name_insured'])) {
                                $OtherPartyInsured->last_name_insured = $thirdMember['last_name_insured'];
                            }else{
                                $OtherPartyInsured->last_name_insured = null;
                            }
                            if (!empty($thirdMember['cellphone_insured'])) {
                                $OtherPartyInsured->cellphone_insured = $thirdMember['cellphone_insured'];
                            }else{
                                $OtherPartyInsured->cellphone_insured = null;
                            }
                            if (!empty($thirdMember['email_insured'])) {
                                $OtherPartyInsured->email_insured = $thirdMember['email_insured'];
                            }else{
                                $OtherPartyInsured->email_insured = null;
                            }
                            if (!empty($thirdMember['address_insured'])) {
                                $OtherPartyInsured->address_insured = $thirdMember['address_insured'];
                            }else{
                                $OtherPartyInsured->address_insured = null;
                            }
                            // dd($request->all());
                            $OtherPartyInsured->save();
                        }

                    }

                    if ($thirdMember['thirdMemberName'] != NULL) {
                        $thirdparty = new ClaimThirdParty();
                        $thirdparty->claim_id = $claims->id;
                        $thirdparty->first_name = $thirdMember['thirdMemberName'];
                        $thirdparty->last_name = $thirdMember['thirdMemberlast_Name'];
                        $thirdparty->address = $thirdMember['thirdMemberAddress'];
                        $thirdparty->cellphone = $thirdMember['thirdMemberCellphone'];
                        $thirdparty->registration_no = $thirdMember['thirdMemberReg'];
                        $thirdparty->make = $thirdMember['thirdMemberMake'];
                        $thirdparty->model = $request['thirdMemberModel'][$key];
                        $thirdparty->damage_details = $thirdMember['thirdMemberDetail'];
                        $thirdparty->injured_name = $thirdMember['thirdMemberInjured_name'];
                        $thirdparty->relationship = $thirdMember['thirdMemberRelationship'];
                        $thirdparty->hospital_name = $thirdMember['thirdMemberHospital_name'];
                        $thirdparty->injured_details = $thirdMember['thirdMemberInjured_details'];
                        $thirdparty->save();
                    }
                }
            }

            /* storing driver details*/
            $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
            $accidentDriver->claim_id = $claims->id;
            $accidentDriver->name = $request->driver_name;
            $accidentDriver->address = $request->driver_address;
            $accidentDriver->dob = $request->driver_dob;
            $accidentDriver->license = $request->driver_license;
            $accidentDriver->cellphone = $request->driver_num;
            $accidentDriver->purpose = $request->driver_purpose;
            $accidentDriver->save();

            /*storing passenger injury*/
            if (is_array($request->get('old_passengerinjury'))) {
                for ($i = 0; $i < count($request->get('old_passengerinjury')); $i++) {
                    $p = ClaimAccidentPassenger::where('id', $request->get('old_passengerinjury')[$i])->first();
                    $p->name = $request->get('old_passengerinjuryName')[$i];
                    $p->address = $request->get('old_passengerinjuryAddress')[$i];
                    $p->injury = $request->get('old_passengerinjuryInjury')[$i];
                    $p->save();
                }
            }
            foreach ($request->get('passenger_injuries') as $key => $passenger) {
                if ($passenger['passenger_name'] != NULL) {
                    $p = new ClaimAccidentPassenger();
                    $p->claim_id = $claims->id;
                    $p->name = $passenger['passenger_name'];
                    $p->address = $passenger['passenger_address'];
                    $p->injury = $passenger['passenger_injury'];
                    $p->save();
                }
            }

            if (($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL') && ($policy->product_id == 7 || $policy->product_id == 8)) {
                $newClaimCon = new NewClaimController();
                $saved = $newClaimCon->update($request, $claims->id);
            }
            activity('Motor Accident Claim')
                ->performedOn($claims)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Motor Accident claim Updated');
        } elseif ($claims->claim_type == 'Legal') {
            $legalClaim = ClaimLegal::where('claim_id', $id)->first();
            $legalClaim->legal_firm = $request->legal_firm;
            $legalClaim->lawyer_name = $request->lawyer_name;
            $legalClaim->legal_tel = $request->legal_tel;
            $legalClaim->legal_email = $request->legal_email;

            $legalClaim->member_name = $request->member_name;
            $legalClaim->membership_id = $request->membership_id;
            $legalClaim->member_contact = $request->member_contact;
            $legalClaim->member_email = $request->member_email;
            $legalClaim->lossreported_date = $request->lossreported_date;
            $legalClaim->matter_relatesto = $request->matter_relatesto;
            $legalClaim->child_financial_dependent = $request->child_financial_dependent;
            $legalClaim->idforchild = $request->idforchild;
            $legalClaim->child_dob = $request->child_dob;
            $legalClaim->realestate_enquiry_from = $request->realestate_enquiry_from;
            $legalClaim->legaloption = htmlspecialchars(strip_tags($request->legaloption));
            $legalClaim->representing_member = htmlspecialchars(strip_tags($request->representing_member));
            $legalClaim->lawyer_tarrif = htmlspecialchars(strip_tags($request->lawyer_tarrif));
            $legalClaim->arose_date = $request->arose_date;
            $legalClaim->matter_quantum = $request->matter_quantum;
            $legalClaim->course_of_action = $request->course_of_action;
            $legalClaim->jurisdiction = $request->jurisdiction;
            $legalClaim->criminalmatter_detail = $request->criminalmatter_detail;
            $legalClaim->criminalmatter_charge = $request->criminalmatter_charge;
            $legalClaim->save();

        }elseif ($claims->claim_type == 'Glass') {
            $vehicleClaim = ClaimVehicle::where('claim_id', $claims->id)->first();
            $vehicleClaim->date_of_damage = Carbon::parse($request->date_of_damage)->format('Y-m-d');
            $vehicleClaim->damage_extent = $request->extent;
            $vehicleClaim->damage_cause = $request->cause;
            if ($request->hasFile('incidentFront')) {
                $file = $request->file('incidentFront');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $claims->customer_id . '/' . 'Claims' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->front_image = $filePath;
            }
            $vehicleClaim->front_image_description = $request->front_image_description;
            if ($request->hasFile('incidentBack')) {
                $file = $request->file('incidentBack');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $claims->customer_id . '/' . 'Claims' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->back_image = $filePath;
            }
            $vehicleClaim->back_image_description = $request->back_image_description;
            if ($request->hasFile('incidentRight')) {
                $file = $request->file('incidentRight');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $claims->customer_id . '/' . 'Claims' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->right_image = $filePath;
            }
            $vehicleClaim->right_image_description = $request->right_image_description;
            if ($request->hasFile('incidentLeft')) {
                $file = $request->file('incidentLeft');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $claims->customer_id . '/' . 'Claims' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->left_image = $filePath;
            }
            $vehicleClaim->left_image_description = $request->left_image_description;
            $vehicleClaim->save();

            /*storing third party details*/
            if ($policy->kyc_recipient == 1) {
                $recipientKyc = RecipientKyc::where('claim_id', $claims->id)->first();
                $recipientKyc->policy_id = $claims->policy_id;
                $recipientKyc->claim_id = $claims->id;
                if ($request->hasFile('driving_license')) {
                    $file = $request->file('driving_license');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->driving_license = $filePath;
                }
                if ($request->hasFile('omang')) {
                    $file = $request->file('omang');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->omang = $filePath;
                }
                if ($request->hasFile('proof_residence')) {
                    $file = $request->file('proof_residence');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->proof_residence = $filePath;
                }
                if ($request->hasFile('proof_income')) {
                    $file = $request->file('proof_income');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->proof_income = $filePath;
                }
                if ($request->hasFile('passport')) {
                    $file = $request->file('passport');
                    $name = $this->gen_uuid() . $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $recipientKyc->passport = $filePath;
                }
                $recipientKyc->save();
            }


            if ($claims->claim_type == 'Glass' && ($policy->product_id == 7 || $policy->product_id == 8)) {
                $newClaimCon = new NewClaimController();
                $saved = $newClaimCon->update($request, $claims->id);
            }

            activity('Glass Claim')
                ->performedOn($claims)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Glass Claim Updated');
        } elseif ($claims->claim_type == 'Cellphone') {
            $claimCellphone = ClaimCellphone::where('claim_id', $claims->id)->first();
            if ($request->hasFile('cell_phone_front')) {
                $file = $request->file('cell_phone_front');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->front = $filePath;
            }
            if ($request->hasFile('cell_phone_back')) {
                $file = $request->file('cell_phone_back');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->back = $filePath;
            }
            if ($request->hasFile('cell_phone_left')) {
                $file = $request->file('cell_phone_left');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->left = $filePath;
            }
            if ($request->hasFile('cell_phone_right')) {
                $file = $request->file('cell_phone_right');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->right = $filePath;
            }
            if ($request->hasFile('cell_phone_top')) {
                $file = $request->file('cell_phone_top');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->top = $filePath;
            }
            if ($request->hasFile('cell_phone_bottom')) {
                $file = $request->file('cell_phone_bottom');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->bottom = $filePath;
            }
            $claimCellphone->save();
            activity('Cellphone Claim')
                ->performedOn($claims)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Cellphone Claim Updated');
        } elseif (trim($claims->claim_type) == 'Hospital CashBack' || trim($claims->claim_type) == 'Hospital Cash') {
            $hospitalCash = ClaimHospitalCash::where('claim_id', $claims->id)->first();
            
            if (!$hospitalCash) {
                $hospitalCash = new ClaimHospitalCash();
                $hospitalCash->claim_id = $claims->id;
            }
            
            // Patient Information
            $hospitalCash->patient_name = htmlspecialchars(strip_tags($request->patient_name ?? ''));
            // Date of Birth
            if ($request->patient_dob) {
                try {
                    $hospitalCash->patient_dob = Carbon::parse($request->patient_dob)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->patient_dob = null;
                }
            } else {
                $hospitalCash->patient_dob = null;
            }
            // Identity Number - Handle Omang/Passport based on citizen status
            // Priority: patient_identity_number (from hidden field) > patient_omang > patient_passport
            if ($request->patient_identity_number) {
                $hospitalCash->patient_identity_number = htmlspecialchars(strip_tags($request->patient_identity_number));
            } elseif ($request->patient_omang) {
                $hospitalCash->patient_identity_number = htmlspecialchars(strip_tags($request->patient_omang));
            } elseif ($request->patient_passport) {
                $hospitalCash->patient_identity_number = htmlspecialchars(strip_tags($request->patient_passport));
            } else {
                $hospitalCash->patient_identity_number = null;
            }
            // Relationship
            $hospitalCash->relationship = htmlspecialchars(strip_tags($request->relationship ?? ''));
            // Relationship Other (if "Other" is selected)
            if ($request->relationship == 'Other') {
                $hospitalCash->relationship_other = htmlspecialchars(strip_tags($request->relationship_other ?? ''));
            } else {
                $hospitalCash->relationship_other = null;
            }
            if ($request->occupation_date) {
                try {
                    $hospitalCash->occupation_date = Carbon::parse($request->occupation_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->occupation_date = null;
                }
            } else {
                $hospitalCash->occupation_date = null;
            }
            
            // General Practitioner Information
            $hospitalCash->gp_name = htmlspecialchars(strip_tags($request->gp_name ?? ''));
            $hospitalCash->gp_postal_address = htmlspecialchars(strip_tags($request->gp_postal_address ?? ''));
            $hospitalCash->gp_cellular_no = htmlspecialchars(strip_tags($request->gp_cellular_no ?? ''));
            $hospitalCash->gp_telephone_no = htmlspecialchars(strip_tags($request->gp_telephone_no ?? ''));
            $hospitalCash->gp_fax_no = htmlspecialchars(strip_tags($request->gp_fax_no ?? ''));
            
            // Hospital Information
            $hospitalCash->hospital_name = htmlspecialchars(strip_tags($request->hospital_name ?? ''));
            $hospitalCash->hospital_tel_fax = htmlspecialchars(strip_tags($request->hospital_tel_fax ?? ''));
            $hospitalCash->admitting_doctor = htmlspecialchars(strip_tags($request->admitting_doctor ?? ''));
            $hospitalCash->admitting_doctor_tel_fax = htmlspecialchars(strip_tags($request->admitting_doctor_tel_fax ?? ''));
            if ($request->admission_date) {
                try {
                    $hospitalCash->admission_date = Carbon::parse($request->admission_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->admission_date = null;
                }
            } else {
                $hospitalCash->admission_date = null;
            }
            $hospitalCash->admission_time = htmlspecialchars(strip_tags($request->admission_time ?? ''));
            if ($request->discharge_date) {
                try {
                    $hospitalCash->discharge_date = Carbon::parse($request->discharge_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->discharge_date = null;
                }
            } else {
                $hospitalCash->discharge_date = null;
            }
            $hospitalCash->discharge_time = htmlspecialchars(strip_tags($request->discharge_time ?? ''));
            
            // Claim Information
            $hospitalCash->hospitalisation_type = htmlspecialchars(strip_tags($request->hospitalisation_type ?? ''));
            $hospitalCash->accident_reported = htmlspecialchars(strip_tags($request->accident_reported ?? ''));
            if ($request->symptoms_first_appeared) {
                try {
                    $hospitalCash->symptoms_first_appeared = Carbon::parse($request->symptoms_first_appeared)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->symptoms_first_appeared = null;
                }
            } else {
                $hospitalCash->symptoms_first_appeared = null;
            }
            // Pregnancy fields
            if ($request->pregnancy_conception_date) {
                try {
                    $hospitalCash->pregnancy_conception_date = Carbon::parse($request->pregnancy_conception_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->pregnancy_conception_date = null;
                }
            } else {
                $hospitalCash->pregnancy_conception_date = null;
            }
            if ($request->pregnancy_delivery_date) {
                try {
                    $hospitalCash->pregnancy_delivery_date = Carbon::parse($request->pregnancy_delivery_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->pregnancy_delivery_date = null;
                }
            } else {
                $hospitalCash->pregnancy_delivery_date = null;
            }
            if ($request->injury_date) {
                try {
                    $hospitalCash->injury_date = Carbon::parse($request->injury_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->injury_date = null;
                }
            } else {
                $hospitalCash->injury_date = null;
            }
            $hospitalCash->accident_circumstances = htmlspecialchars(strip_tags($request->accident_circumstances ?? ''));
            if ($request->first_consultation_date) {
                try {
                    $hospitalCash->first_consultation_date = Carbon::parse($request->first_consultation_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->first_consultation_date = null;
                }
            } else {
                $hospitalCash->first_consultation_date = null;
            }
            // Medical scheme fields
            if ($request->is_medical_scheme == 'yes') {
                $hospitalCash->medical_scheme_name = htmlspecialchars(strip_tags($request->medical_scheme_name ?? ''));
                $hospitalCash->medical_aid_number = htmlspecialchars(strip_tags($request->medical_aid_number ?? ''));
            } else {
                $hospitalCash->medical_scheme_name = null;
                $hospitalCash->medical_aid_number = null;
            }
            // Other insurance fields
            if ($request->has_other_insurance == 'yes') {
                $hospitalCash->other_insurance_company_name = htmlspecialchars(strip_tags($request->other_insurance_company_name ?? ''));
                $hospitalCash->other_insurance_policy_numbers = htmlspecialchars(strip_tags($request->other_insurance_policy_numbers ?? ''));
            } else {
                $hospitalCash->other_insurance_company_name = null;
                $hospitalCash->other_insurance_policy_numbers = null;
            }
            
            // Signature and Date
            $hospitalCash->signed_by = htmlspecialchars(strip_tags($request->signed_by ?? ''));
            if ($request->claim_date) {
                try {
                    $hospitalCash->claim_date = Carbon::parse($request->claim_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $hospitalCash->claim_date = null;
                }
            } else {
                $hospitalCash->claim_date = null;
            }
            
            $hospitalCash->save();
            
            activity('Hospital CashBack Claim')
                ->performedOn($claims)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Hospital CashBack Claim Updated');
            
            return Redirect::route('admin.claims.index')->with('success', 'Hospital CashBack claim updated Successfully');
        } elseif ($claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY' || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS' || $claims->claim_type == 'HOUSEHOLDERS' || $claims->claim_type == 'HOUSEOWNERS' || $claims->claim_type == 'HOUSEOWNER-BUILDINGS' || $claims->claim_type == 'HOUSEHOLDERS-CONTENTS') {
            $newClaimCon = new NewClaimController();
            $saved = $newClaimCon->update($request, $id);
        } else {
            $life = ClaimLife::where('claim_id', $claims->id)->first();
            $life->date_of_death = Carbon::parse($request->date_of_death)->format('Y-m-d');
            $life->cause_of_death = $request->cause;
            $life->description = $request->description;
            if (!$life) {
                $life = new ClaimLife();
                $life->claim_id = $claims->id;
            }
            if ($request->hasFile('death_certificate')) {
                $file = $request->file('death_certificate');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $claims->customer_id . '/' . 'Claims' . '/' . $claims->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $life->certificate = $filePath;
            }

            if ($life->save()) {
                if ($request->get('old_beneficiaryId') != NULL) {
                    foreach ($request->get('old_beneficiaryId') as $key => $beneficiaryId) {
                        $b = PolicyBeneficiary::where('id', $beneficiaryId)->first();
                        $b->first_name = $request->get('old_beneficiaryFName')[$key];
                        $b->last_name = $request->get('old_beneficiaryLName')[$key];
                        $b->dob = $request->get('old_beneficiaryDOB')[$key];
                        $b->gender = $request->get('old_beneficiaryGender')[$key];
                        $b->payment = $request->get('old_beneficiaryPayment')[$key];
                        $b->save();
                    }
                }
                if ($request->get('beneficiaries') != NULL) {
                    foreach ($request->get('beneficiaries') as $key => $beneficiaries) {
                        if ($beneficiaries['beneficiaryFName'] != NULL) {
                            $b = new PolicyBeneficiary();
                            $b->policy_id = $policy->id;
                            $b->first_name = $beneficiaries['beneficiaryFName'];
                            $b->last_name = $beneficiaries['beneficiaryLName'];
                            $b->dob = $beneficiaries['beneficiaryDOB'];
                            $b->gender = $beneficiaries['beneficiaryGender'];
                            $b->payment = $beneficiaries['beneficiaryPayment'];
                            $b->save();
                        }
                    }
                }
                if ($policy->kyc_recipient) {
                    $recipientKyc = RecipientKyc::where('claim_id', $claims->id)->first();
                    $recipientKyc->policy_id = $claims->policy_id;
                    $recipientKyc->claim_id = $claims->id;
                    if ($request->hasFile('driving_license')) {
                        $file = $request->file('driving_license');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKyc->driving_license = $filePath;
                    }
                    if ($request->hasFile('omang')) {
                        $file = $request->file('omang');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKyc->omang = $filePath;
                    }
                    if ($request->hasFile('proof_residence')) {
                        $file = $request->file('proof_residence');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKyc->proof_residence = $filePath;
                    }
                    if ($request->hasFile('proof_income')) {
                        $file = $request->file('proof_income');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKyc->proof_income = $filePath;
                    }
                    if ($request->hasFile('passport')) {
                        $file = $request->file('passport');
                        $name = $this->gen_uuid() . $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claims->id . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $recipientKyc->passport = $filePath;
                    }
                    $recipientKyc->save();
                }
                activity('Life Claim')
                    ->performedOn($claims)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Life Claim Updated');
            }
            // Redirect to the home page with success menu
            return Redirect::route('admin.claims.index')->with('success', 'Life claim updated Successfully');
        }
        return redirect()->back()->with('success', 'Claim Updated Successfully !');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     *
     *
     */
    public function beneficiaryNameDelete($id)
    {
        $beneficiaries = PolicyBeneficiary::find($id)->delete();
        activity('Beneficiaries')
            ->performedOn($beneficiaries)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Beneficiaries Deleted');
        return redirect()->back()->with('success', 'Beneficiary Deleted Successfully');
    }

    public function gen_uuid()
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

    /**
     * method returns all claims listings.
     *
     * @param
     * @return claim claim index
     */

    public function data(Request $request)
    {
        if (Auth::user()->hasPermissionTo('claim-list')) {

            $draw = $request->get('draw');
            $start = $request->get("start");
            $rowperpage = $request->get("length"); // Rows display per page

            $columnIndex_arr = $request->get('order');
            $columnName_arr = $request->get('columns');
            $order_arr = $request->get('order');
            $search_arr = $request->get('search');
            $pName = null;
            $columnIndex = $columnIndex_arr[0]['column']; // Column index
            $columnName = $columnName_arr[$columnIndex]['data']; // Column name
            $columnSortOrder = $order_arr[0]['dir']; // asc or desc
            $searchValue = trim($search_arr['value']); // Search value

            // Total records
            $totalRecords = Claim::select('count(*) as allcount')->count();
           // dd($totalRecords);

            $query = Claim::latest();
            $query->leftJoin('customer', 'customer.id', 'claims.customer_id');

            $query->leftJoin('policies', 'policies.customer_id', 'customer.id');


            //$query = Claim::latest();
            if ($searchValue != null) {
                $normalizedPlate = str_replace(' ', '', $searchValue);
                $query->where('claim_number', 'like', '%' . $searchValue . '%')
                ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
                ->orWhere('claims.vehicle_plate', 'like', '%' . $searchValue . '%')
                ->orWhere('claims.vehicle_plate', 'like', '%' . $normalizedPlate . '%')
                ->orWhereHas('policy.vehicle', function($q) use ($searchValue, $normalizedPlate) {
                    $q->where('vehiclePlate', 'like', "%{$searchValue}%")
                      ->orWhere('vehiclePlate', 'like', "%{$normalizedPlate}%");
                })
                ->orWhereHas('policy.riskAddress', function($q) use ($searchValue) {
                    $q->where('address_name', 'like', "%{$searchValue}%")
                      ->orWhere('physical_address', 'like', "%{$searchValue}%");
                });

            }
            if ($request->claimStatus_filter != -1 && $request->claimType_filter == -1) {
                $query->where('claims.status', $request->claimStatus_filter);
            }
            if ($request->claimType_filter != -1 && $request->claimStatus_filter == -1) {
                $query->where('claims.claim_type', $request->claimType_filter);
            }
            if ($request->claimStatus_filter != -1 && $request->claimType_filter != -1) {
                $query->where('claims.status', $request->claimStatus_filter);
            }

            if($request->FilterBy != -1 ){
                if ($request->FilterBy == 'cellphoneFilter' && $request->value_filter) { // filter Policy by cellphone number, policy_cellphone is the ID of the policy
                    $query->where('customer.cellphone', 'LIKE', '%'.$request->value_filter.'%');
                }
                if ($request->FilterBy == 'emailFilter' && $request->value_filter ) { // filter Policy by reference number
                    $query->where('customer.email', 'LIKE', '%'.$request->value_filter.'%');
                }
                if ($request->FilterBy == 'vehiclePlateFilter' && $request->value_filter ) { // filter Claims by vehicle plate number
                    $query->where(function($q) use ($request) {
                        $q->where('claims.vehicle_plate', 'LIKE', '%'.$request->value_filter.'%')
                          ->orWhereHas('policy.vehicle', function($subQ) use ($request) {
                              $subQ->where('vehiclePlate', 'LIKE', '%'.$request->value_filter.'%');
                          });
                    });
                }
            }

            $totalRecordswithFilter = $query->count();

            $query = $query->skip($start)
            ->take($rowperpage)
            ->groupBy('claims.claim_number')
            ->get( ['claims.id','claims.policy_id','claims.customer_id','claims.created_by','claims.claim_type','claims.status','claims.claim_number','claims.category','claims.created_at'

                ]);

            $query = json_decode($query);
            //dd($query);
            $data_arr = array();
            $sno = $start + 1;
            foreach ($query as $claims) {
                //dd($claims);

                $policyNumber = 'NA';
                if(isset($claims->policy_id) && $claims->policy_id != null){
                    $policy = Policy::where('id', $claims->policy_id)->first(array('policyNumber'));
                    if ($policy) {
                        $policyNumber = $policy->policyNumber;
                    }
                }

                $name = 'N/A';
                if(isset($claims->customer_id) && $claims->customer_id != null){
                    $customer = Customer::where('id', $claims->customer_id)->first(array('firstName', 'lastName', 'cellphone', 'customer_category'));
                    // $customer_name = "N/A";
                    // if ($policy->product_id == 7) {
                    //     if($policy->profile->entity_type=='Organisation') {
                    //         $customer_name = $policy->profile->company->name??"";
                    //     }
                    // }
                    if ($customer) {
                        $policy = Policy::where('id', $claims->policy_id)->first(array('product_id'));
                        $cust_profile = CustomerProfile::where('customer_id', $claims->customer_id)->first(['entity_type','company_id']);
                        $companyName = 'N/A';

                        if ($cust_profile && $cust_profile->company_id) {
                            $company = Company::where('id', $cust_profile->company_id)->first('name');
                            if ($company) {
                                $companyName = ucwords($company->name);
                            }
                        }
                        // $company = Company::where('id', $cust_profile->company_id)->first('name');

                        if ($customer->customer_category == 2) {
                            if ($policy->product_id == 7) {
                                if($cust_profile->entity_type=='Organisation') {
                                    $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($companyName) . '</a>';
                                } else {
                                    $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                                }
                            } elseif ($policy->product_id == 8) {
                                // DOMG policies - show company name if present, else customer name
                                if($cust_profile->entity_type=='Organisation') {
                                    $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($companyName) . '</a>';
                                } else {
                                    $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                                }
                            } else {
                                $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                            }

                        } elseif ($customer->customer_category == 1) {
                            if ($policy->product_id == 7) {
                                if($cust_profile->entity_type=='Organisation') {
                                    $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($companyName) . '</a>';
                                } else {
                                    $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                                }
                            } elseif ($policy->product_id == 8) {
                                // DOMG policies - show company name if present, else customer name
                                if($cust_profile->entity_type=='Organisation') {
                                    $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($companyName) . '</a>';
                                } else {
                                    $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                                }
                            } else {
                                $name = '<span class="graydot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                            }
                        } else {
                            if ($policy->product_id == 7) {
                                if($cust_profile->entity_type=='Organisation') {
                                    $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($companyName) . '</a>';
                                } else {
                                    $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                                }
                            } elseif ($policy->product_id == 8) {
                                // DOMG policies - show company name if present, else customer name
                                if($cust_profile->entity_type=='Organisation') {
                                    $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($companyName) . '</a>';
                                } else {
                                    $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                                }
                            } else {
                                $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $claims->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                            }
                        }
                    }
                }


                $claim_handler = 'N/A';
                if(isset($claims->created_by) && $claims->created_by != null){
                    $detailsUser = User::where('id', $claims->created_by)->first(array('firstName', 'lastName'));
                    if ($detailsUser) {
                        $claim_handler = $detailsUser->firstName . ' ' . $detailsUser->lastName;
                    }
                }

                if ($claims->status == 'Approved') {
                    $return = '<span class="kt-font-bold kt-font-accent">Approved</span>';
                } elseif ($claims->status == 'Pending') {
                    $return = '<span class="kt-font-bold kt-font-primary">Pending</span>';
                } elseif ($claims->status == 'Closed') {
                    $return = '<span class="kt-font-bold kt-font-accent">Closed</span>';
                } elseif ($claims->status == 'Reopen') {
                    $return = '<span class="kt-font-bold kt-font-accent">Reopen</span>';
                } else {
                    $return = '<span class="kt-font-bold kt-font-danger">Rejected</span>';
                }

                $actions = '';
                if (Auth::user()->can('claim-edit')) {
                    $actions .= '<a href="' . route('admin.claims.show', $claims->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                            <i class="flaticon2-copy"></i>
                           </a>';

                } else {
                    $actions .= '<a href="' . route('admin.claims.claimView', $claims->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                            <i class="flaticon-eye"></i>
                        </a>';
                }
                if ($claims->category != null) {
                    $claim_type = $claims->claim_type . ' (' . '<span class="kt-font-bold kt-font-danger">' . $claims->category . '</span>' . ')';
                } else {
                    $claim_type = $claims->claim_type;
                }




                $data_arr[] = array(
                    "id" => $claims->id,
                    "policy_id" => $policyNumber,
                    "claim_number"=>$claims->claim_number,
                    "name" => $name,
                    "claim_handler"  => $claim_handler,
                     "status"    => $return,
                    "actions"       => $actions,
                    "claim_type"          => $claim_type,

                   // "created_at"      => Carbon::parse($record['created_at'])->format('d-m-Y H:i:s'),

                );

            }
            $claimsdata = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data_arr
            );

            echo json_encode($claimsdata);

            exit;


        } else {
            return redirect()->back()->with('error', 'You do npot have access to view this page');
        }
    }

    /**
     * method sends quote request on claim approve status.
     *
     * @param
     * @return claim page
     */
    public function approved($id)
    {
        $claims = Claim::where('id', $id)->first();
        $claims->status = 'Approved';
        ClaimQuote::where('claim_id', $id)->delete();

        $policy = Policy::where('id', $claims->policy_id)->first('product_id');

        if ($policy && !in_array($policy->product_id, [7, 8])) {

            if ($claims->claim_type == 'Glass') {
                $vehicle = Vehicle::where('policy_id', $claims->policy_id)->first('vehicleRegistration');
                if ($vehicle->vehicleRegistration == NULL) {
                    session()->put('step', '2');
                    return redirect()->back()->with('error', 'Please Upload Vehicle Registration to send a Quote Request');
                }
                if ($claims->customer_selected == 1)
                    $suppliers = Supplier::where('id', $claims->supplier_id)->get();
                else
                    $suppliers = Supplier::where('customer_selected', 0)->get();
                foreach ($suppliers as $supplier) {
                    $quotes = new ClaimQuote();
                    $quotes->claim_id = $claims->id;
                    $quotes->policy_id = $claims->policy_id;
                    $quotes->supplier_id = $supplier->id;
                    $quotes->save();
                    $data = new \stdClass();
                    $data->quote_id = $quotes->id;
                    $data->supplier_id = $supplier->id;
                    $data->claim_id = $claims->id;
                    $data->vehicleRegistration[] = $vehicle->vehicleRegistration;
                    //Mail::to($supplier->email)->send(new SendQuote($data));

                    // below code was commented because the suppliers were receiving this email, so on call with claim team paul beka said to comment the code
                    // $emailTemplate = EmailBroadcasting::where('hook_slug', 'supplier_request')->first(array('subject'));
                    // $markdown = new SendQuote($data);
                    // $html = $markdown->render('Mail.sentQuoteRequest',['data'=>$data]);
                    // event(new \AlphaDirect\Events\SendMail($supplier->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'supplier_request']));
                }
            }
        }
        $claims->save();
        activity('Claim')
            ->performedOn($claims)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Claim Approved');
        return redirect()->back()->with('success', 'Claim Approved Successfully !');
    }

    /**
     * method turns status of specified claim rejected .
     *
     * @param
     * @return claim page
     */
    public function rejects($id)
    {
        $claims = Claim::where('id', $id)->first();
        $claims->status = 'Rejected';
        if ($claims->customer_selected == 0)
            $claims->supplier_id = null;
        $claims->save();
        $claimquote = ClaimQuote::where('claim_id', $claims->id)->delete();

        $email_address = Customer::where('id', $claims->customer_id)->first(array('email'));
        if ($email_address && $email_address->email == null) {
            return redirect()->back()->with('error', 'Email does not exist.');
        }
        $user = Customer::where('id', $claims->customer_id)->first(array('email', 'firstName', 'cellphone'));
        $data = new \stdClass();
        $data->customer_id = $claims->customer_id;
        $data->claim_id = $id;
        $data->policy_id = $claims->policy_id;

        $data->hook = 'claim_rejected';
        $data->attachment = NULL;
        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
        $markdown = new MailTemplate($data);
        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
        event(new \AlphaDirect\Events\SendMail($email_address->email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));
       // Mail::to($email_address->email)->send(new MailTemplate($data));


        $shortCodeArr = array('[[Customers Firstname_5]]', '[[Claim Number_7]]', '[[Policy Number_6]]');
        $replacementArr = array($user->firstName, $user, $user);
        $message = Sms::where('hook_slug', 'claim_rejected')->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        // $response = InfobipSms::send('+267' . $user->cellphone, $refinedMsg);
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $user->cellphone, $refinedMsg));
        session()->put('step', '2');
        activity('Claim Rejected')
            ->performedOn($claims)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Claim status changed to Rejected');
        return redirect()->back()->with('success', 'Claim Rejected Successfully !');
    }

    public function quote($id)
    {
        $quotes = ClaimQuote::where('id', $id)->first();
        if ($quotes->total) {
            return redirect(url('/'));
        }
        $claims = Claim::where('id', $quotes->claim_id)->first();
        $user = User::with(['profile', 'banking'])->where('id', $claims->customer_id)->first(array('id', 'firstName', 'lastName', 'email'));
        $vehicleDetails = Vehicle::where('policy_id', $claims->policy_id)->first();
        $claimVehicle = null;
        $supplierQuotes = null;
        if ($claims) {
            if ($claims->claim_type == 'Glass')
                $claimVehicle = ClaimVehicle::where('vehicle_id', $vehicleDetails->id)->first();
        }
        return view('admin.claims.quotes', compact('quotes', 'claims', 'user', 'claimVehicle', 'policy', 'vehicleDetails'));
    }

    public function updateQuote(Request $request, $id)
    {
        $quotes = ClaimQuote::where('id', $id)->first();
        $quotes->total = $request->quote_amount;
        $quotes->save();
        return view('admin.policy.index');
    }

    public function acceptQuote($quote_id, Request $request)
    {
        $quote = ClaimQuote::where('id', $quote_id)->first();
        $quote->status = 1;
        if ($request->reason != NULL) {
            $quote->select_reason = $request->reason;
        }
        $data = new \stdClass();
        $data->resent_note = NULL;
        if ($quote->invoice != NULL && $request->resent_note != NULL) {
            $quote->resent_invoice_notes = $request->resent_note;
            $data->resent_note = $request->resent_note;
        }
        $quote->save();
        $claims = Claim::where('id', $quote->claim_id)->first();
        if ($claims->claim_type == 'Cellphone') {
            $supplier = RepairCenter::where('id', $quote->supplier_id)->first();
        } else {
            $supplier = Supplier::where('id', $quote->supplier_id)->first();
        }
        $claims->supplier_id = $supplier->id;
        $claims->save();

        $data->supplier_id = $supplier->id;
        $data->claim_id = $quote->claim_id;
        $data->quote_id = $quote_id;
        $data->po = $claims->po;
       // Mail::to($supplier->email)->send(new SendPO($data));
        $emailTemplate = EmailBroadcasting::where('hook_slug', 'supplier_confirm')->first(array('subject'));
        $markdown = new SendPO($data);
        $html = $markdown->render('Mail.poSent',['data'=>$data]);
        event(new \AlphaDirect\Events\SendMail($supplier->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'supplier_confirm']));

        Session::flash('message', 'Client and Supplier have been notified.');
        session()->put('step', '3');
        activity('Claim Confirmation')
            ->performedOn($quote)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Claim Confirmation Sent');
        return redirect()->back()->with('success', 'Claim Confirmation Sent Successfully !');
    }

    public function supplierInvoice($id)
    {
        $quotesTotal = ClaimQuote::where('id', $id)->first();
        $quotes = Quote::where('qoute_total_id', $quotesTotal->id)->get();
        $vehicleDetails = Vehicle::find(ClaimVehicle::where('claim_id', $quotesTotal->claim_id)->first()->vehicle_id)->first();
        $glassClaim = Claim::where('id', $quotesTotal->claim_id)->orderBy('created_at', 'desc')->first();
        $supplierDetails = Supplier::where('id', $quotesTotal->supplier_id)->first();
        return view('admin.claims.supplier_invoice', compact('quotesTotal', 'quotes', 'vehicleDetails', 'glassClaim', 'supplierDetails'));
    }

    public function invoiceUpload(Request $request, $claim_id)
    {
        $claims = Claim::find($claim_id);
        $claims->note = $request->note;
        if ($request->hasFile('invoice')) {
            $file = $request->file('invoice');
            $name = $this->gen_uuid() . $file->getClientOriginalName();
            $filePath = 'MIS/' . 'Claims' . '/' . 'Invoice-' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $claims->invoice = $filePath;
        }
        $claims->save();
        if ($claims->claim_type == 'Cellphone') {
            $repaired_img = ClaimCellphone::where('claim_id', $claim_id)->first();
            if ($request->hasFile('after_repair_front')) {
                $file = $request->file('after_repair_front');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . 'Claims' . '/' . 'after_repair_front-' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_front = $filePath;
            }
            if ($request->hasFile('after_repair_back')) {
                $file = $request->file('after_repair_back');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . 'Claims' . '/' . 'after_repair_back-' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_back = $filePath;
            }
            if ($request->hasFile('after_repair_left')) {
                $file = $request->file('after_repair_left');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . 'Claims' . '/' . 'after_repair_left-' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_left = $filePath;
            }
            if ($request->hasFile('after_repair_right')) {
                $file = $request->file('after_repair_right');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . 'Claims' . '/' . 'after_repair_right-' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_right = $filePath;
            }
            if ($request->hasFile('after_repair_top')) {
                $file = $request->file('after_repair_top');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . 'Claims' . '/' . 'after_repair_top-' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_top = $filePath;
            }
            if ($request->hasFile('after_repair_bottom')) {
                $file = $request->file('after_repair_bottom');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . 'Claims' . '/' . 'after_repair_bottom-' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_bottom = $filePath;
            }
            $repaired_img->save();
        }

        session()->put('step', '4');
        activity('Invoice')
            ->performedOn($claims)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Invoice Saved');
        return redirect()->back()->with('success', 'Claim Invoice Uplaoded Successfully !');
    }

    public function poUpload(Request $request, $claim_id)
    {
        $claims = Claim::where('id',$claim_id)->first();

        //dd($request->all());
        if ($request->hasFile('po')) {
            $file = $request->file('po');
            $name = $this->gen_uuid() . $file->getClientOriginalName();
            $filePath = 'MIS/' . 'Claims' . '/' . 'PO' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $claims->po = $filePath;
        }
        //dd($claims->save());
        $claims->save();
       // dd($claims);
        if ($request->send_submit == 1) {
            $quote = ClaimQuote::where('id', $request->dynamicSupplierId)->first();
            $quote->status = 1;
            if ($request->reason != NULL)
                $quote->select_reason = $request->reason;

            $data = new \stdClass();
            $data->resent_note = NULL;
            if ($quote->invoice != NULL && $request->resent_note != NULL) {
                $quote->resent_invoice_notes = $request->resent_note;
                $data->resent_note = $request->resent_note;
            }
            $quote->save();
            $claims = Claim::where('id', $quote->claim_id)->first();
            if ($claims->claim_type == 'Cellphone') {
                $supplier = RepairCenter::where('id', $quote->supplier_id)->first();
            } else {
                $supplier = Supplier::where('id', $quote->supplier_id)->first();
            }

            $claims->supplier_id = $supplier->id;
            $claims->save();

            $data->supplier_id = $request->dynamicSupplierId;
            $data->claim_id = $quote->claim_id;
            $data->quote_id = $request->dynamicSupplierId;
            $data->po = $claims->po;
           // Mail::to($supplier->email)->send(new SendPO($data));
            $emailTemplate = EmailBroadcasting::where('hook_slug', 'supplier_confirm')->first(array('subject'));
            $markdown = new SendPO($data);
            $html = $markdown->render('Mail.poSent',['data'=>$data]);
            event(new \AlphaDirect\Events\SendMail($supplier->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'supplier_confirm']));

        }
        session()->put('step', '3');
        if ($request->send_submit == 0) {
            activity('Purchase Order')
                ->performedOn($claims)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('PO Uploaded');
            return redirect()->back()->with('success', 'PO Uploaded Successfully');
        } else {
            activity('Purchase Order')
                ->performedOn($claims)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('PO Uploaded and mail sent to supplier');
            return redirect()->back()->with('success', 'PO Uploaded and mail sent to supplier Successfully');
        }
    }

    public function accidentSupplier(Request $request, $claim_id)
    {
        $data = array();
        $claims = Claim::where('id', $claim_id)->first();
        $suppliers = $request->supplier_id;
        $files = $request->accident_file;

        foreach ($suppliers as $key => $supplier) {
            $fileStore = array();
            $quote = new ClaimQuote();
            $quote->claim_id = $claim_id;
            $quote->policy_id = $request->policy_id;
            $quote->supplier_id = $supplier;
            if (!empty($files)) {
                $fileStore = array();
                foreach ($files[$key] as $file) {
                    $name = preg_replace('/\s+/', '', $file->getClientOriginalName());
                    $filePath = 'MIS/' . 'Claims' . '/' . 'Accident' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $fileStore[] = $filePath;
                }
            }
            $quote->claim_file = serialize($fileStore);
            $quote->save();

            $data = new \stdClass();
            $data->quote_id = $quote->id;
            $data->supplier_id = $supplier;
            $data->claim_id = $claim_id;
            $claims = Claim::where('id', $claim_id)->first(array('claim_type'));
            if ($claims->claim_type != 'Cellphone') {
                $vehicle = Vehicle::where('policy_id', $request->policy_id)->first(array('vehicleRegistration'));
                if ($vehicle->vehicleRegistration == NULL) {
                    return redirect()->back()->with('error', 'Vehicle Registration is not Uploaded to make a quote request.');
                }
                array_push($fileStore, $vehicle->vehicleRegistration);
                $data->vehicleRegistration = $fileStore;
                $supEmail = Supplier::where('id', $supplier)->first(array('email'));
            } else {
                $supEmail = RepairCenter::where('id', $supplier)->first(array('email'));
            }

            // Mail::to($supEmail['email'])->send(new SendQuote($data));

            // below code was commented because the suppliers were receiving this email, so on call with claim team paul beka said to comment the code

            // $emailTemplate = EmailBroadcasting::where('hook_slug', 'supplier_request')->first(array('subject'));
            // $markdown = new SendQuote($data);
            // $html = $markdown->render('Mail.sentQuoteRequest',['data'=>$data]);
            // event(new \AlphaDirect\Events\SendMail($supEmail->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'supplier_request']));

        }
        session()->put('step', '7');
        activity('Supplier Quote Request')
            ->performedOn($supEmail)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Supplier Quote Request Sent');
        return redirect()->back()->with('success', 'Supplier Quote Request Sent Successfully');
    }


    public function accidentSupplierInvoice(Request $request, $claim_id)
    {
        $quote = ClaimQuote::where('claim_id', $claim_id)->where('supplier_id', $request->supplier_id)->first();
        $quote->invoice_notes = $request->invoice_note;
        if ($request->hasFile('invoice')) {
            $file = $request->file('invoice');
            $name = $this->gen_uuid() . $file->getClientOriginalName();
            $filePath = 'MIS/' . 'Claims/' . $claim_id . '/' . 'Supplier/' . $request->supplier_id . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $quote->invoice = $filePath;
        }
        $quote->save();
        session()->put('step', '4');
        activity('Supplier Invoice')
            ->performedOn($quote)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Supplier Invoice Updated');
        return redirect()->back()->with('success', 'Supplier Invoice Updated Successfully !');
    }


    public function assessorUpload(Request $request, $id)
    {
        $claims = Claim::where('id', $id)->first();
        if ($claims->claim_type == 'Accident') {
            $vehicle = Vehicle::where('policy_id', $claims->policy_id)->first();
            if ($vehicle->vehicleRegistration == NULL) {
                return redirect()->back()->with('error', 'Vehicle Registration is not Uploaded.');
            }
            $data = [
                'title' => 'Claim Details',
                'user' => Customer::with(['profile', 'banking'])->where('id', $claims->customer_id)->first(),
                'vehicle' => $vehicle,
                'vehicle_purpose' => Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value')),
                'thirdparty' => ClaimThirdParty::where('claim_id', $claims->id)->get(),
                'accident_passenger' => ClaimAccidentPassenger::where('claim_id', $claims->id)->get(),
                'accident_driver' => AccidentDriver::where('claim_id', $claims->id)->first(),
                'claimAccident' => ClaimAccident::where('claim_id', $claims->id)->first(),
                'claims' => $claims = Claim::where('id', $id)->first()
            ];

            $pdf = PDF::loadView('admin.claims.pdf_view', $data);
            $filePath = 'MIS/' . $claims->id . '/Customer/' . $claims->customer_id . '/assessment.pdf';
            Storage::disk('s3')->put($filePath, $pdf->output());
            $claims_accident = ClaimAccident::where('claim_id', $id)->first();
            if ($claims_accident) {
                DB::table('claim_accidents')->where('claim_id', $id)->update([
                    'pdf' => $filePath
                ]);
            }
            $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
            if ($claim_assessment == NULL)
                $claim_assessment = new ClaimAssessment();
            $claim_assessment->claim_id = $claims->id;
            $claim_assessment->policy_id = $claims->policy_id;
            $claim_assessment->assessor_id = $request->assessor;
            $attachment = array();
            if ($request->hasFile('attachFile')) {
                $file = $request->file('attachFile');
                $name = $file->getClientOriginalName();
                $filePath2 = 'MIS/' . $claim_assessment->claim_id . '/' . 'Attached' . '/File' . '/' . $name;
                Storage::disk('s3')->put($filePath2, file_get_contents($file), 'public');
                $claim_assessment->attached_file = $filePath2;
                $attachment[] = $filePath2;
            }
            $claim_assessment->save();
            array_push($attachment, $vehicle->vehicleRegistration);
            $data = new \stdClass();
            $data->assessment_id = $claim_assessment->id;
            $data->assessor_id = $request->assessor;
            $data->claim_id = $claims->id;
            $data->pdf = $filePath;
            $data->attachment = $attachment;
            $email_address = User::where('id', $claim_assessment->assessor_id)->first(array('email'));
            if ($email_address == null) {
                return redirect()->back()->with('error', 'Email does not exist for the selected assessor.');
            }
            // Mail::to($email_address->email)->queue(new AccidentAssessment($data, 0));

            $emailTemplate = EmailBroadcasting::where('hook_slug', 'accident_assessment')->first(array('subject'));
            $markdown = new AccidentAssessment($data, 0);
            $html = $markdown->render('Mail.assessorRequest',['data'=>$data]);
            event(new \AlphaDirect\Events\SendMail($email_address->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'accident_assessment']));

            if ($request->attorneyValue) {
                $emailAttorney = User::where('id', $claims_accident->attorney_id)->first(array('email'));
                if ($emailAttorney != null) {
                   // Mail::to($emailAttorney->email)->queue(new AccidentAssessment($data, 1));

                    $emailTemplate = EmailBroadcasting::where('hook_slug', 'accident_assessment')->first(array('subject'));
                    $markdown = new AccidentAssessment($data, 1);
                    $html = $markdown->render('Mail.assessorRequest',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($emailAttorney->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'accident_assessment']));

                } else {
                    return redirect()->back()->with('error', 'Email does not exist for the Attorney.');
                }
            }
            return redirect()->back()->with('success', 'Request sent to Assessor Successfully !');
        } elseif ($claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY' || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS' || $claims->claim_type == 'HOUSEHOLDERS' || $claims->claim_type == 'HOUSEOWNERS' || $claims->claim_type == 'HOUSEOWNER-BUILDINGS' || $claims->claim_type == 'HOUSEHOLDERS-CONTENTS') {
            $vehicle = Vehicle::where('policy_id', $claims->policy_id)->first();
            if ($vehicle->vehicleRegistration == NULL) {
                return redirect()->back()->with('error', 'Vehicle Registration is not Uploaded.');
            }
            $data = [
                'title' => 'Claim Details',
                'user' => Customer::with(['profile', 'banking'])->where('id', $claims->customer_id)->first(),
                'vehicle' => $vehicle,
                'vehicle_purpose' => Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value')),
                'thirdparty' => null,
                'accident_passenger' => null,
                'accident_driver' => null,
                'claimAccident' => null,
                'claims' => $claims = Claim::where('id', $id)->first()
            ];

            $pdf = PDF::loadView('admin.claims.pdf_view', $data);
            $filePath = 'MIS/' . $claims->id . '/Customer/' . $claims->customer_id . '/assessment.pdf';
            Storage::disk('s3')->put($filePath, $pdf->output());
            // $claims_accident = ClaimAccident::where('claim_id', $id)->first();
            // if ($claims_accident) {
            //     DB::table('claim_accidents')->where('claim_id', $id)->update([
            //         'pdf' => $filePath
            //     ]);
            // }
            $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
            if ($claim_assessment == NULL)
                $claim_assessment = new ClaimAssessment();
            $claim_assessment->claim_id = $claims->id;
            $claim_assessment->policy_id = $claims->policy_id;
            $claim_assessment->assessor_id = $request->assessor;
            $attachment = array();
            if ($request->hasFile('attachFile')) {
                $file = $request->file('attachFile');
                $name = $file->getClientOriginalName();
                $filePath2 = 'MIS/' . $claim_assessment->claim_id . '/' . 'Attached' . '/File' . '/' . $name;
                Storage::disk('s3')->put($filePath2, file_get_contents($file), 'public');
                $claim_assessment->attached_file = $filePath2;
                $attachment[] = $filePath2;
            }
            $claim_assessment->save();
            array_push($attachment, $vehicle->vehicleRegistration);
            $data = new \stdClass();
            $data->assessment_id = $claim_assessment->id;
            $data->assessor_id = $request->assessor;
            $data->claim_id = $claims->id;
            $data->pdf = $filePath;
            $data->attachment = $attachment;
            $email_address = User::where('id', $claim_assessment->assessor_id)->first(array('email'));
            if ($email_address == null) {
                return redirect()->back()->with('error', 'Email does not exist for the selected assessor.');
            }
            // Mail::to($email_address->email)->queue(new AccidentAssessment($data, 0));

            $emailTemplate = EmailBroadcasting::where('hook_slug', 'accident_assessment')->first(array('subject'));
            $markdown = new AccidentAssessment($data, 0);
            $html = $markdown->render('Mail.assessorRequest',['data'=>$data]);
            event(new \AlphaDirect\Events\SendMail($email_address->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'accident_assessment']));

            // if ($request->attorneyValue) {
            //     $emailAttorney = User::where('id', $claims_accident->attorney_id)->first(array('email'));
            //     if ($emailAttorney != null) {
            //        // Mail::to($emailAttorney->email)->queue(new AccidentAssessment($data, 1));

            //         $emailTemplate = EmailBroadcasting::where('hook_slug', 'accident_assessment')->first(array('subject'));
            //         $markdown = new AccidentAssessment($data, 1);
            //         $html = $markdown->render('Mail.assessorRequest',['data'=>$data]);
            //         event(new \AlphaDirect\Events\SendMail($emailAttorney->email,$emailTemplate->subject,"",$html,NULL,['hook' => 'accident_assessment']));

            //     } else {
            //         return redirect()->back()->with('error', 'Email does not exist for the Attorney.');
            //     }
            // }
            return redirect()->back()->with('success', 'Request sent to Assessor Successfully !');
        }
        $claims->save();
        session()->put('step', '3');
    }

    public function assessorView($id)
    {
        $claim_assessment = ClaimAssessment::where('id', $id)->first();
        return view('admin/assessor/assessorMainView', compact('claim_assessment'));
    }

    public function assessorCellphoneClaimView($claim_id, $repair_center_id)
    {
        $claim_assessment = ClaimAssessment::where('assessor_id', $repair_center_id)->where('claim_id', $claim_id)->first(array('id'));
        if ($claim_assessment == NULL) {
            $claim = Claim::where('id', $claim_id)->first(array('policy_id'));
            $claim_assessment = new ClaimAssessment();
            $claim_assessment->claim_id = $claim_id;
            $claim_assessment->policy_id = $claim->policy_id;
            $claim_assessment->assessor_id = $repair_center_id;
            $claim_assessment->save();
            $claim_assessment = ClaimAssessment::where('assessor_id', $repair_center_id)->where('claim_id', $claim_id)->first(array('id'));
        }

        return view('admin/assessor/assessorMainView', compact('claim_assessment', 'repair_center_id'));
    }

    public function assessorUpload2(Request $request, $id)
    {
        $claim_assessment = ClaimAssessment::find($id);

        if ($request->assessment_report != NULL) {
            $filePath = array();
            foreach ($request->assessment_report as $file) {
                $name = $file->getClientOriginalName();
                $filePath[] = 'MIS/' . $claim_assessment->claim_id . '/' . 'Assessment' . '/Report' . '/' . $name;
                Storage::disk('s3')->put(end($filePath), file_get_contents($file), 'public');
            }
            $claim_assessment->assessment_report = json_encode($filePath);
        }
        if ($request->quotations_parts != NULL) {
            $filePath = array();
            foreach ($request->quotations_parts as $file) {
                $name = $file->getClientOriginalName();
                $filePath[] = 'MIS/' . $claim_assessment->claim_id . '/' . 'Quotations' . '/Parts' . '/' . $name;
                Storage::disk('s3')->put(end($filePath), file_get_contents($file), 'public');
            }
            $claim_assessment->quotations_parts = json_encode($filePath);
        }

        if ($request->valuation != NULL) {
            $filePath = array();
            foreach ($request->valuation as $file) {
                $name = $file->getClientOriginalName();
                $filePath[] = 'MIS/' . $claim_assessment->claim_id . '/' . 'Valuation' . '/' . $name;
                Storage::disk('s3')->put(end($filePath), file_get_contents($file), 'public');
            }
            $claim_assessment->valuation = json_encode($filePath);
        }
        $claim_assessment->notes = $request->note;
        $saved = $claim_assessment->save();
        if ($saved) {
            $created_by = Claim::where('id', $claim_assessment->claim_id)->first(array('created_by', 'agent_id'));
            if ($created_by && $created_by->created_by != null) {
                $user = User::where('id', $created_by->created_by)->first(array('email'));
                $data = new \stdClass();
                $data->user_id = $created_by->created_by;
                $data->claim_id = $claim_assessment->claim_id;
                $data->policy_id = $claim_assessment->policy_id;
                $data->hook = 'assessment_report';
                $data->attachment = NULL;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));
               // Mail::to($user->email)->send(new MailTemplate($data));
            }
            if ($created_by->created_by != $created_by->agent_id) {
                $user = User::where('id', $created_by->agent_id)->first(array('email'));
                if ($user != NULL) {
                    $data = new \stdClass();
                    $data->user_id = $created_by->agent_id;
                    $data->claim_id = $claim_assessment->claim_id;
                    $data->policy_id = $claim_assessment->policy_id;
                    $data->hook = 'assessment_report';
                    $data->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));
                  //  Mail::to($user->email)->send(new MailTemplate($data));
                }
            }

        }
        return view("alphaFe.thankyou_cellphone");
    }

    public function ClaimsActivity($id)
    {
        $activity = \AlphaDirect\Activity::orderBy('created_at', 'desc')->where('subject_id', $id)->get(array('id', 'log_name', 'description', 'subject_id', 'subject_type', 'causer_id', 'created_at'));
        return DataTables::of($activity)
            ->editColumn('subject_type', function ($activity) {
                $type = explode("\\", $activity->subject_type);
                $modal_name = $activity->subject_type;
                if ($type[count($type) - 1] == 'Supplier')
                    $column = 'supplierName';
                elseif ($type[count($type) - 1] == 'Policy')
                    $column = 'policyNumber';
                elseif ($type[count($type) - 1] == 'Master')
                    $column = 'value';
                elseif ($type[count($type) - 1] == 'User')
                    $column = 'firstName';
                elseif ($type[count($type) - 1] == 'Customer')
                    $column = 'firstName';
                elseif ($type[count($type) - 1] == 'Claim')
                    $column = 'claim_number';
                elseif ($type[count($type) - 1] == 'Accounts')
                    $column = 'account_name';
                else
                    $column = 'name';
                $query = $modal_name::where('id', $activity->subject_id)->pluck($column);
                return $type[count($type) - 1] . ' ' . $query;
            })
            ->editColumn('causer_id', function ($activity) {
                $user = User::where('id', $activity->causer_id)->first();
                return $activity ? $user->firstName . ' ' . $user->lastName : '-';
            })
            ->editColumn('properties', function () {
                return '';
            })
            ->rawColumns(['causer_id', 'properties', 'subject_type'])
            ->make(true);
    }

    public function getCustomerData(Request $request)
    {
        $customerbanking = CustomerBanking::where('customer_id', $request->get('customer_id'))->where('claim_id', $request->get('claim_id'))->first();
        $kyc = KYC::where('customer_id', $request->get('customer_id'))->first();
        return response()->json(['customerbanking' => $customerbanking, 'kyc' => $kyc]);
    }

    public function getOtherPartyData(Request $request)
    {
        $otherPartyBanking = ClaimAccidentOtherPartyBanking::where('claim_thirdParty_id', $request->get('otherparty_id'))->where('claim_id', $request->get('claim_id'))->first();
        $kyc = ClaimAccidentOtherPartyKyc::where('thirdParty_id', $request->get('otherparty_id'))->where('claim_id', $request->get('claim_id'))->first();
        return response()->json(['otherPartyBanking' => $otherPartyBanking, 'kyc' => $kyc]);
    }


    public function checkreserve_amount(Request $request)
    {
        $policy = Policy::where('id', $request->policyId)->first('sum_assured');
        if ($policy->sum_assured > $request->reserve_amount) {
            return response()->json(['error' => 0, 'sum_assured' => $policy->sum_assured]);
        } else {
            return response()->json(['error' => 1, 'sum_assured' => $policy->sum_assured]);
        }
    }

    public function checkMakeModel(Request $request)
    {
        $variant = vehicleMake::where('s_Make', $request->get('make'))->pluck('s_Variant');
        return response()->json(['count' => $variant]);
    }

    public function get_oldMakeModel(Request $request)
    {
        $vehicleMakes = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->pluck('s_Make');
        return response()->json(['vehicle' => $vehicleMakes]);
    }

    public function getpreviousModel(Request $request)
    {
        $previous_make = vehicleMake::where('s_Make', $request->get('previous_make'))->pluck('s_Variant');
        return response()->json(['make_values' => $previous_make]);
    }

    public function getModelValues(Request $request)
    {
        $make = vehicleMake::where('s_Variant', $request->get('oldmodel'))->pluck('s_Make');
        $moddelvalues = VehicleMake::where("s_Make", $make)->pluck('s_Variant');
        return response()->json(['model_values' => $moddelvalues]);
    }

    public function checkAttorney(Request $request)
    {
        $attorneyCheck = ClaimAccident::where('claim_id', $request->get('claim_id'))->first();
        if ($attorneyCheck->attorney_id != NULL) {
            $attorneyName = User::where('id', $attorneyCheck->attorney_id)->first(array('firstName', 'lastName'));
            $msg = '<p style="color:cornflowerblue">Mail will be sent to :<span style="font-weight:bold"> ' . $attorneyName->firstName . ' ' . $attorneyName->lastName . '</span></p>';
            return response()->json(['error' => 1, 'msg' => $msg]);
        } else {
            return response()->json(['error' => 0]);
        }
    }

    public function claimView($id, Request $request)
    {
        $claims = Claim::where('id', $id)->first();
        $recipientKyc = RecipientKyc::where('claim_id', $id)->first();
        $policy = Policy::where('id', $claims->policy_id)->first();
        $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
        $members = PolicyMember::where('policy_id', $policy->id)->get();
        $fileNames = Lookup::where('key', 'file_type')->get(array('key', 'value'));
        $supplierTypes = Lookup::where('key', 'supplier_type')->get('value');
        $beneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();
        $product = Product::where('id', $policy->product_id)->first(array('id', 'name'));
        $productFactors = FactorMain::with('value')->where('product_id', $policy->product_id)->get(array('id', 'name', 'type'));
        foreach ($productFactors as $productFactor) {
            $policyFactors = PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $productFactor->id)->get(array('factor_value_id', 'value_name', 'name'));
            if ($productFactor->type == 'Input Field')
                $productFactor->policyFactors = $policyFactors->pluck('value_name')->toArray();
            else
                $productFactor->policyFactors = $policyFactors->pluck('factor_value_id')->toArray();

            $productFactor->policyFactorsValueName = $policyFactors->pluck('value_name')->toArray();
        }
        if ($policy->plan_id != NULL)
            $productPlan = Productplan::where('id', $policy->plan_id)->first(array('name'));
        $kyc = KYC::where('customer_id', $claims->customer_id)->first(array('driving_license', 'omang', 'proof_residence', 'proof_income', 'passport'));
        $policyMotorItems = PolicyMotorItems::where('policy_id', $policy->id)->get(array('id', 'item_name', 'item_value', 'policy_id'));
        $motor_items = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));
        $banking = CustomerBanking::where('claim_id', $id)->get();
        if ($banking == NULL) {
            $banking = CustomerBanking::where('policy_id', $policy->id)->get();
        }
        // $policyCover = PolicyCoverage::where('policy_id', $policy->id)->get(array('main', 'coverage_value', 'discount', 'type', 'value', 'policy_id'));
        $vehicle = Vehicle::where('policy_id', $policy->id)->first();
        $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
        $life = null;
        $legal = null;
        $deathCauses = Lookup::where('key', 'cause_of_death')->get(array('id', 'value'));
        $claimVehicle = null;
        $supplierQuotes = null;
        $suppliers = NULL;
        $glassClaim = NULL;
        $vehicleDetails = NULL;
        $salvage = NULL;
        $salvageyardRole = NULL;
        $userDetails = NULL;
        $user = NULL;
        $attachmentDataCount = NULL;
        $transPayees = NULL;
        $userRole = NULL;
        $claimVehicleImages = NULL;
        $eventNames = NULL;
        $reportedByOpts = NULL;
        $claimAccident = NULL;
        $userInfo = NULL;
        $otherparty = NULL;
        $otherparty_banking = NULL;
        $vehicleMakes = NULL;
        $vehicleModels = NULL;
        $claimAssessmentCount = NULL;
        $thirdparty = NULL;
        $glassClaim = NULL;
        $transTypes= NULL;
        $attorneyRole = NULL;
        $attorney = NULL;
        $accidentPassenger = NULL;
        $accidentInjury = NULL;
        $fileNames = NULL;
        $accidentDriver = NULL;
        $beneficiaries = NULL;
        $claim_assessment = NULL;
        $assessors = NULL;
        $lowestQuote = NULL;
        $lossTypes = NULL;
        $claimSubTypes = NULL;
        $transSubTypes = NULL;
        $coverages = NULL;
        $salvageUser = NULL;
        $users = NULL;
        $hospitalCash = NULL;

        if ($claims) {
            if ($claims->claim_type == 'Glass') {
                $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();

            }elseif ($claims->claim_type == 'Legal') {
                $legal = ClaimLegal::where('claim_id', $claims->id)->first();

            }elseif ($claims->claim_type == 'Life') {
                $life = ClaimLife::where('claim_id', $claims->id)->first();

            }elseif ($claims->claim_type == 'Hospital CashBack') {
                $hospitalCash = ClaimHospitalCash::where('claim_id', $claims->id)->first();

            }else {
                $claimAccident = ClaimAccident::where('claim_id', $claims->id)->first();
                $claim_assessment = ClaimAssessment::where('claim_id', $id)->first();
                $claimAssessmentCount = ClaimAssessment::where('claim_id', $id)->get(array('id', 'policy_id', 'assessor_id', 'assessment_report', 'quotations_parts', 'valuation', 'notes', 'attached_file'));

                $vehicleMakes = DB::table('tb_prmotormakemodels')
                    ->selectRaw('DISTINCT s_Make')
                    ->pluck('s_Make');

                $vehicleModels = DB::table('tb_prmotormakemodels')
                    ->selectRaw('DISTINCT s_Variant')
                    ->get('s_Variant');  //first(array('s_Variant'))
                $otherparty = ClaimThirdParty::where('claim_id', $claims->id)->get();
                $claimVehicle = ClaimVehicle::where('claim_id', $claims->id)->first();
                $thirdparty = ClaimThirdParty::where('claim_id', $claims->id)->get();
                $accidentPassenger = ClaimAccidentPassenger::where('claim_id', $claims->id)->get();
                $otherparty_banking = ClaimAccidentOtherPartyBanking::where('claim_id', $claims->id)->get();
                $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
                $suppliers = Supplier::where('customer_selected', 0)->get(array('id', 'supplierName'));
                $attorneyRole = User::role('Attorney')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                $attorney = User::whereIn('id', $attorneyRole->pluck('user_id'))->get(array('id', 'firstName', 'lastName'));
                $accidentInjury = AccidentInjury::where('claim_id', $claims->id)->get();
                $eventNames = Lookup::where('key', 'motor_claim_event')->get(array('value'));
                $reportedByOpts = Lookup::where('key', 'claim_reported_by')->get(array('value'));
                $lossTypes = Lookup::where('key', 'motor_claim_loss_type')->get(array('value'));
                $claimSubTypes = ClaimSubType::where('claim_type', 'Motor accident')->get(array('sub_type'));
                $users = User::get(array('id', 'firstName', 'lastName'));
                $assessors = User::role('Accessor')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
                $salvageUser = User::role('Salvage Yard')->where('active', 1)->get(array('id', 'firstName', 'lastName'));

                $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
                $coverages = ProductCoverage::groupBy('product_coverage.coverage_id')
                    ->select('product_coverage.name', 'product_coverage.coverage_id')
                    ->where('product_coverage.product_id', $policyProduct->product_id)->get();
                foreach ($coverages as $coverage) {
                    $coverage->payment_amt = ClaimReservesCoverage::where('coverage_id', $coverage->coverage_id)->where('claim_id', $id)->sum('payment_amt');
                    $coverage->reserve_amt = ClaimReservesCoverage::where('coverage_id', $coverage->coverage_id)->where('claim_id', $id)->sum('reserve_amt');
                }
            }
            $supplierQuotes = ClaimQuote::with('supplier')->where('claim_id', $claims->id)->get(array('id', 'supplier_id', 'total', 'status', 'claim_file', 'select_reason', 'invoice', 'invoice_notes'));
            $lowestQuote = ClaimQuote::where('claim_id', $claims->id)->where('total', '!=', NULL)->orderBy('total')->first(array('id'));
        }
        $transTypes = Lookup::where('key', 'transaction_type')->where('value', '!=', 'Initial Reserves')->get(array('id', 'key', 'value'));
        $transSubTypes = Lookup::where('key', 'transaction_sub_type')->get(array('id', 'key', 'value'));
        $transPayees = Supplier::get(array('id', 'supplierName'));
        if ($claims->customer_selected && $claims->claim_type == 'Glass')
            $supplier = Supplier::where('id', $claims->supplier_id)->first();
        else
            $supplier = NULL;
        session()->forget('step');
        $userInfo = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();
        return view('admin.claimsView.general', compact('legal','transPayees', 'eventNames', 'reportedByOpts', 'userInfo', 'otherparty', 'otherparty_banking', 'vehicleMakes', 'vehicleModels', 'claimAssessmentCount', 'thirdparty', 'glassClaim', 'transTypes', 'vehicleDetails', 'vehicleDetails', 'salvage', 'salvageyardRole', 'attorneyRole', 'attorney', 'accidentPassenger', 'accidentInjury', 'fileNames', 'accidentDriver', 'beneficiaries', 'claim_assessment', 'claimAccident', 'assessors', 'vehicle_purpose', 'productFactors', 'recipientKyc', 'deathCauses', 'supplier', 'supplierTypes', 'supplierQuotes', 'userDetails', 'policy', 'claimVehicle', 'claims', 'life', 'lowestQuote', 'kyc', 'user', 'product', 'vehicle', 'policyMotorItems', 'members', 'banking', 'claimVehicleImages', 'productPlan', 'suppliers', 'motor_items', 'lossTypes', 'claimSubTypes', 'users', 'transSubTypes', 'coverages', 'policyProduct', 'attachmentDataCount', 'salvageUser', 'userRole', 'hospitalCash'));

    }

    public function getThirdParty(Request $request)
    {
        $otherparty = ClaimThirdParty::where('claim_id', $request->get('claim_id'))->get();
        return response()->json(['otherparty' => $otherparty]);
    }

    public function closeClaimStore(Request $request, $claim_id)
    {
        $claims = Claim::find($claim_id);
        if ($request->hasFile('document_1')) {
            $file = $request->file('document_1');
            $name = $this->gen_uuid() . $file->getClientOriginalName();
            $filePath = 'MIS/' . 'Claims' . '/' . 'document_1' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $claims->document_1 = $filePath;
        }
        if ($request->hasFile('document_2')) {
            $file = $request->file('document_2');
            $name = $this->gen_uuid() . $file->getClientOriginalName();
            $filePath = 'MIS/' . 'Claims' . '/' . 'document_2' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $claims->document_2 = $filePath;
        }
        if ($request->hasFile('document_3')) {
            $file = $request->file('document_3');
            $name = $this->gen_uuid() . $file->getClientOriginalName();
            $filePath = 'MIS/' . 'Claims' . '/' . 'document_3' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $claims->document_3 = $filePath;
        }

        if ($request->closed_note != NULL) {
            $claims->closed_note = $request->closed_note;
        }

        $claims->status = 'Closed';
        $claims->save();

        activity('Claim')
            ->performedOn($claims)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Claim Closed');
        return redirect()->back()->with('success', 'Claim Closed Successfully !');
    }

    public function reopenStoreClaim(Request $request, $claim_id)
    {
        $claims = Claim::find($claim_id);

        if ($request->reopen_claim_substatus != NULL) {
            $claims->reopen_claim_sub_status = $request->reopen_claim_substatus;
        }

        $claims->status = 'Reopen';
        $claims->save();

        activity('Claim')
            ->performedOn($claims)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Claim Reopened');
        return redirect()->back()->with('success', 'Claim Reopened Successfully !');
    }

    //check the claim category
    public function getClaimCategory($id)
    {
        $claim = Claim::where('id', $id)->first(array('id', 'claim_type'));
        $incidentDate = '';
        $time = '';

        switch ($claim->claim_type) {
            case 'Accident':
                $accident = ClaimAccident::where('claim_id', $id)->first();
                $incidentDate = $accident->date_of_accident;
                $time = date("H:i", strtotime($accident->time_of_accident));
                break;
            case 'key_loss':
                $key = ClaimKeyLoss::where('claim_id', $id)->first();
                $incidentDate = $key->date_of_loss;
                break;
            case 'Glass':
                $glass = ClaimVehicle::where('claim_id', $id)->first();
                $incidentDate = $glass->date_of_damage;
                break;
        }

        $day = Carbon::createFromFormat('Y-m-d', Carbon::parse($incidentDate)->format('Y-m-d'))->format('l');

        if ($day == 'Friday' && $time >= '17:00') {
            $category = 'High Risk';
        } elseif ($day == 'Saturday') {
            $category = 'High Risk';
        } elseif ($day == 'Sunday' && $time <= '12:00') {
            $category = 'High Risk';
        } else {
            $category = null;
        }

        return $category;

    }
    public function getClaimsActivityLogRecordes($policy_id)
    {
         // $data = Audits::where('event','updated')->where('auditable_type','AlphaDirect\KYC')->orderBy('id', 'desc')->get(['id','old_values','new_values','user_id','agent_id','tags','updated_at']);

          $policydetails = Policy::where('id',$policy_id)->first();

          $activity = Audit::orderBy('created_at', 'desc')
         // ->where('event','updated')
          ->where('auditable_type','AlphaDirect\Claim')
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
              ->editColumn('id', function ($activity) {

                    return $activity->id;

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
              ->rawColumns(['id','user_id','old_values','new_values','tag','created_at'])
              ->make(true);
    }

    public function newClaimStatusUpdate(Request $request, $id)
    {
        $claims = Claim::where('id', $id)->first();

        $newclaim = NewClaim::where('claim_number',$claims->claim_number)->first();

        if (isset($claims) && isset($newclaim)) {
            $claims->status = $request->claim_status;
            $claims->save();

            $policy = Policy::where('id', $claims->policy_id)->first();

            $newclaim->claims_allocated_on = Carbon::parse($request->claim_allocated_on)->format('Y-m-d');
            $newclaim->claim_allocated_to = $request->claim_allocated_to;
            $newclaim->reserve_amount = $request->reserve_amount;
            $newclaim->paid_amount = $request->paid_amount;
                if ($request->claim_status == "Rejected") {
                    $newclaim->status = $request->claim_status;
                    $newclaim->note = $request->note;
                    $newclaim->reason = $request->reason;
                    $newclaim->is_salvage_yard = NULL;
                    $newclaim->salvage_yard_id = NULL;
                    $newclaim->claim_sub_status = NULL;
                    if ($request->hasFile('document')) {
                        $file = $request->file('document');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $newclaim->id . '/' . 'Document' . '/File' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $newclaim->document = $filePath;
                    }
                } elseif ($request->claim_status == "Approved") {
                    $newclaim->status = $request->claim_status;
                    $newclaim->note = NULL;
                    $newclaim->reason = NULL;
                    $newclaim->claim_sub_status = $request->claim_sub_status;
                    $newclaim->claim_approved = $request->claim_approved;
                } else {
                    $newclaim->status = $request->claim_status;
                    $newclaim->claim_sub_status = NULL;
                    $newclaim->is_salvage_yard = NULL;
                    $newclaim->salvage_yard_id = NULL;
                    $newclaim->document = NULL;
                    $newclaim->note = NULL;
                    $newclaim->reason = NULL;
                }
                $newclaim->save();

            activity('Claim Status')
                ->performedOn($claims)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log(' Claim Status Change to ' . $newclaim->status);
            return redirect()->back()->with('success', 'Claim Status Updated Successfully !');
        } else {
            return redirect()->back()->with('error', 'Claim Status Not Updated');
        }

    }

    public function storeComplaint(Request $request, $claim_id)
    {

        $claims = Claim::where('id',$claim_id)->first();

        $complaints = new ClaimComplaintLog();
        $complaints->policy_id = $request->policy_id;
        $complaints->claim_id = $request->claim_id;
        $complaints->complaint_of = $request->complaint_of;
        $complaints->complaint_details = $request->complaint_details;
        $complaints->added_by = auth()->user()->id;
        $complaints->save();

        activity('Claims complaint Log')
            ->performedOn($claims)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Claims complaint added');
        return redirect()->back()->with('success', 'Claims Complaint Added Successfully');
    }

    public function getClaimComplaintLogs($claim_id)
    {
        $claimComplaints = ClaimComplaintLog::where('claim_id',$claim_id)->orderBy('created_at', 'desc')->get();

        return DataTables::of($claimComplaints)
            ->editColumn('added_by', function ($claimComplaints) {
                if($claimComplaints->added_by != null){
                    $user = User::where('id', $claimComplaints->added_by)->first();
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

                    return $claimComplaints ? $userfirstName . ' ' . $userlastName : '-';
                }
            })

            ->editColumn('created_at', function ($claimComplaints) {
                return $claimComplaints->created_at->format('d-m-Y H:i');
            })

            ->rawColumns(['added_by','created_at'])
            ->make(true);
    }

}

