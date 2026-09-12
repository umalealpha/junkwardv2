<?php

namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
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
use AlphaDirect\Models\AllRiskAndElectronicEquipment;
use AlphaDirect\Models\Burglary;
use AlphaDirect\Models\ClaimLegal;
use AlphaDirect\OtherPartyInsured;
use AlphaDirect\Models\NewClaim;
use AlphaDirect\Models\BusinessInterruption;
use AlphaDirect\Models\DefectiveWorkmanship;
use AlphaDirect\Models\WorkersCompensation;
use AlphaDirect\Models\FidelityGuarantee;
use AlphaDirect\Models\Fire;
use AlphaDirect\Models\GoodsInTransit;
use AlphaDirect\Models\MobileAndElectronicDevices;
use AlphaDirect\Models\PropertyLossDamage;
use AlphaDirect\Models\PublicLiability;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\TravelInsurance;
use Log;


class NewClaimController extends Controller
{
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

    public function index()
    {
        if (Auth::user()->hasPermissionTo('claim-list')) {
            $claimStatus = Claim::groupBy('status')->get(array('status'));
            $claimTypes = Claim::groupBy('claim_type')->get(array('claim_type'));
            return view('admin.claims.newClaims.index', compact('claimStatus', 'claimTypes'));
        } else {
            $claimStatus = Claim::groupBy('status')->get(array('status'));
            $claimTypes = Claim::groupBy('claim_type')->get(array('claim_type'));
            return view('admin.claims.newClaims.index', compact('claimStatus', 'claimTypes'));
        }
    }

    public function prosess($id)
    {
        // $coverage =  PolicyCoverage::Join('dom_com_coverage_claims','dom_com_coverage_claims.coverage_id','policy_coverages.coverage_id')->where('policy_coverages.policy_id',$id)->distinct()->get();

        $coverage_ids = PolicyCoverage::where('policy_id', $id)
        ->groupBy('coverage_id')
        ->distinct()
        ->pluck('coverage_id')
        ->toArray();

        if (!empty($coverage_ids)) {
            $coverage_ids_string = implode(',', $coverage_ids);

            $coverage = DB::select(DB::raw('SELECT * FROM dom_com_coverage_claims WHERE coverage_id IN (' . $coverage_ids_string . ')'));

        }

        return view('admin.claims.newClaims.prosess', compact('coverage','id'));

    }
    public function claimprosess(Request $request ,$id){
        $claim_type = $request->claim_type;
         return redirect()->route('admin.newclaims.create',[$claim_type,$id]);
    }


    public function create($claimType,$id)
    {
        $stores = null;
        $policy = null;
        $agents = null;
        $reportedByOpts = null;
        $coverages = null;
        $claimSubTypes = null;
        $lossTypes = null;
        $eventNames = null;
        $attorney = null;
        $attorneyRole = null;
        $vehicleMakes = null;
        $life = NULL;
        $keyloss = NULL;
        $deathCauses = NULL;
        $vehicle_purpose = NULL;
        $reasons = NULL;
        $vehiclePlateNos = NULL;

        $claimType = $claimType;
        $claimSubType = Lookup::where('key', $claimType)->get();
        $policy = Policy::where('id',$id)->first();
        $riskAddress = RiskAddress::where('policy_id',$policy->id)->get();
        $claimReportedBy = Lookup::where('key', 'claim_reported_by')->get(array('value'));

        $agents = User::with('roles')->where('active', 1)->get();
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


        $vehicle = Vehicle::where('policy_id', $policy->id)->first();
        $supplierTypes = Lookup::where('key', 'supplier_type')->get(array('id', 'value'));
        if (isset($vehicle) && $vehicle->is_imported == 0) {
            $isImported = 'No';
        } else {
            $isImported = 'Yes';
        }

        $vehicle_model = NULL;
        $years = range(1990,Carbon::now()->year);
        $vehicle_make = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($isImported);
        if (isset($vehicle->make) && isset($isImported) && isset($vehicle->year)) {
            $vehicle_model = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make, $isImported, $vehicle->year);
        }

        if ($claimType == 'MOTORACCIDENT' || $claimType == 'MOTORTRADERSEXTERNAL' || $claimType == 'MOTORTRADERSINTERNAL' || $claimType == 'GLASS' || $claimType == 'LOCKSANDKEYS') {
            $vehicle = Vehicle::where('policy_id', $id)->first(array('front', 'back', 'right', 'left', 'vehicleRegistration'));
            $vehicleMakes = \Illuminate\Support\Facades\DB::table('tb_prmotormakemodels')
                ->selectRaw('DISTINCT s_Make')
                ->pluck('s_Make');
            $attorneyRole = User::role('Attorney')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
            $attorney = User::get(array('id', 'firstName', 'lastName'));
            $eventNames = Lookup::where('key', 'motor_claim_event')->get(array('value'));
            $lossTypes = Lookup::where('key', 'motor_claim_loss_type')->get(array('value'));
            $claimSubTypes = ClaimSubType::get(array('sub_type'));
            $coverages = ProductCoverage::select('id', 'product_id', 'coverage_id', 'name')->where('product_id', $policy->product_id)->get();
            $reportedByOpts = Lookup::where('key', 'claim_reported_by')->get(array('value'));

            $vehiclePlateNos = PolicyCoverage::join('motor', 'motor.policy_coverage_id', 'policy_coverages.id')
            // ->where('policy_coverages.coverage_id',22)
            ->where('policy_coverages.policy_id',$policy->id)->get('motor.registration_no');
            // ->where('policy_coverages.action_id',$actionId)
            // ->groupBy('type_of_cover_main')->get(array());
        }

        if ($claimType == 'LOCKSANDKEYS') {
            $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
            $deathCauses = Lookup::where('key', 'cause_of_death')->get(array('id', 'value'));
            $reasons = Lookup::where('key', 'key_loss_claim_reason')->get(array('id', 'value'));
        }

        return view('admin.claims.newClaims.main', compact('agents_options','policy','agents','riskAddress','claimType','claimReportedBy','claimSubType','vehicle','vehicle_model','years','vehicle_make','supplierTypes','vehicleMakes','attorneyRole','attorney','eventNames','lossTypes','claimSubTypes','coverages','reportedByOpts','vehicle_purpose','deathCauses','reasons','vehiclePlateNos'));
    }
    public function store(Request $request,$claim_id)
    {

        // try {
            //dd($request->all());
            //  $validatedData = $request->validate([
            //     'policy_id' => 'required',
            //     'policyNumber' => 'required',
            //     'Location' => 'required',
            //    // 'Isthismotorclaim' => 'required|boolean',

            //     'PAInvolved' => 'required|boolean',
            //     'AttorneyInvolved' => 'required|boolean',
            //     'ClaimReportedby' => 'required',
            //     'ClaimType' => 'required',
            //     'ClaimSubType' => 'required',

            //     'TypeofLoss' => 'required',
            //     'DateofLoss' => 'required|date',
            //     'ServiceRepresentative' => 'required',
            //     'CatastropheLoss' => 'required|boolean',
            //     'EventName' => 'nullable',
            //     'PrimaryAttorneyAssigned' => 'nullable',
            //     'CoAttorneyAssigned' => 'nullable',
            //     'AssignedDate' => 'nullable|date',
            //     'DFSComplaint' => 'required|boolean',
            //     'ClaimsAllocatedTo' => 'nullable',
            //     'ClaimsAllocatedOn' => 'nullable|date',
            //    // 'ClaimApproved' => 'required|boolean',
            //    // 'ClaimStatus' => 'required',
            //    // 'ClaimsubStatus' => 'nullable',

            // ]);
            //

            $policy = Policy::where('id', $request->policy_id)->first();
            $claim = Claim::where('id', $claim_id)->first();
            Log::info("claim");
            // Process the data

                    $newclaim = new NewClaim; // Replace with your actual model
                    $newclaim->policyNumber = $policy->policyNumber;
                    $newclaim->claim_number = $claim->claim_number;
                    $newclaim->location_id = $request->Location;
                    $newclaim->is_motor_claim = isset($request->Isthismotorclaim) ? $request->Isthismotorclaim : null;
                    $newclaim->vehicle_plate = isset($request->vehiclePlate) ? $request->vehiclePlate:null;
                    $newclaim->co_attorney_involved = $request->co_attorney_involved;
                    $newclaim->attorney_involved = $request->AttorneyInvolved;
                    $newclaim->claim_reported_by = $request->ClaimReportedby;
                    $newclaim->claim_type = $request->get('type');
                    $newclaim->claim_sub_type_id = $request->ClaimSubType;
                    $newclaim->type_of_loss = $request->TypeofLoss;
                    $newclaim->date_of_loss = Carbon::parse($request->DateofLoss)->format('Y-m-d');
                    $newclaim->service_representative_id = $request->ServiceRepresentative;
                    $newclaim->catastrophe_loss = $request->CatastropheLoss;
                    $newclaim->event_name = $request->EventName;
                    $newclaim->description_of_loss = $request->description_of_loss;

                    $newclaim->primary_attorney_assigned_id = $request->PrimaryAttorneyAssigned;
                    $newclaim->p_a_assigned_date = Carbon::parse($request->p_a_AssignedDate)->format('Y-m-d');
                    $newclaim->co_attorney_assigned_id = $request->CoAttorneyAssigned;
                    $newclaim->c_a_assigned_date = Carbon::parse($request->c_a_AssignedDate)->format('Y-m-d');
                    $newclaim->dfs_complaint = $request->DFSComplaint;
                    $newclaim->claim_allocated_to = $request->ClaimsAllocatedTo;

                    $newclaim->claims_allocated_on = Carbon::parse($request->claimsAllocatedOn)->format('Y-m-d');
                    $newclaim->date_first_visited = Carbon::parse($request->DateFirstVisited)->format('Y-m-d');
                    $newclaim->reserve_amount = $request->reserve_amount;
                    $newclaim->paid_amount = $request->paid_amount;
                    $newclaim->reportedByBrokerAgent = $request->reportedByBrokerAgent;
                    $newclaim->driver_as_insured = $request->driver_as_insured;
                    //$newclaim->claim_approved = $request->ClaimApproved;
                    //$newclaim->status = 'Pending';
                // $newclaim->claim_sub_status = $request->ClaimsubStatus;
                    $newclaim->created_by  = auth()->user()->id;
                    $newclaim->third_party_insured_elsewhere = $request->third_party_insured_elsewhere;
                    if ($request->third_party_insured_elsewhere == 1) {
                        $newclaim->tp_insured_elsewhere_email = $request->tp_insured_elsewhere_email;

                        $data = new \stdClass();
                        $data->customer_id = null;
                        $data->claim_id = $claim->id;
                        $data->policy_id = $claim->policy_id;

                        $data->hook = 'claim_tp_insured_elsewhere';
                        $data->attachment = NULL;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($request->tp_insured_elsewhere_email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));

                    }

                    $newclaim->save();
                    Log::info("claim");
                    $id = $newclaim->id;

                    $coverageId = '';
                    $coverageName = '';
                    if ($request->has('coverage') && isset($request->coverage)) {
                        $coverage = explode(':', $request->coverage);
                        if (count($coverage) > 0) {
                            if(isset($coverage)) {
                                $coverageId = $coverage[0];
                                $coverageName = $coverage[1];
                            }
                        }
                    }

                    /*Entries for Initial Reserve*/
                    $claimReserve = new ClaimReserves();
                    $claimReserve->claim_id = $claim->id;
                    $claimReserve->date = Carbon::now()->format("Y-m-d");
                    $claimReserve->transaction_type = 86; /*Id 86 is from look up data,trans_type: initial reserves*/
                    $claimReserve->payee = auth()->user()->id;
                    $claimReserve->save();

                    /*Entries for Initial Reserve*/
                    $claimReserveCoverage = new ClaimReservesCoverage();
                    $claimReserveCoverage->claim_id = $claim->id;
                    $claimReserveCoverage->reserve_id = $claimReserve->id;
                    $claimReserveCoverage->reserve_amt = $request->reserve_amount;
                    $claimReserveCoverage->balance = $request->reserve_amount;
                    $claimReserveCoverage->coverage_id = $coverageId;
                    $claimReserveCoverage->coverage_name = $coverageName;
                    $claimReserveCoverage->save();

                    // /* storing driver details*/
                    // if ($request->driver_as_insured == 0) {
                    //     $accidentDriver = new AccidentDriver();
                    //     $accidentDriver->claim_id = $claim->id;
                    //     $accidentDriver->name = $request->driver_name;
                    //     $accidentDriver->address = $request->driver_address;
                    //     $accidentDriver->dob = $request->driver_dob;
                    //     $accidentDriver->cellphone = $request->driver_num;
                    //     $accidentDriver->purpose = $request->driver_purpose;
                    //     $accidentDriver->license = $request->driver_license;
                    //     $accidentDriver->save();
                    // }

                if($request->get('type') == "BUSINESSINTERRUPTION"){
                        $this->BusinessInterruptionstore($id,$request);
                    } elseif ($request->get('type') == "BUSINESSALLRISKS" || $request->get('type') == 'ELECTRONICEQUIPMENT' || $request->get('type') == 'PERSONALALLRISKS') {
                        $this->AllRiskAndElectronicEquipmentstore($id, $request);
                    } elseif ($request->get('type') == "WORKERSCOMPENSATION" || $request->get('type') == "STATEDBENEFITS") {
                        $this->WorkersCompensationStore($id, $request);
                    } elseif ($request->get('type') == "DEFECTIVEWORKMANSHIP") {
                        $this->DefectiveWorkmanshipStore($id, $request);
                    } elseif ($request->get('type') == "THEFT" || $request->get('type') == 'MONEY') {
                        $this->Burglarystore($id, $request);
                    } elseif ($request->get('type') == "FIDELITYGUARANTEE") {
                        $this->FidelityGuaranteeStore($id, $request);
                    } elseif ($request->get('type') == "TRAVELINSURANCE") {
                        $this->TravelInsuranceStore($id, $request);
                    } elseif ($request->get('type') == "GOODSINTRANSIT") {
                        $this->GoodsInTransitStore($id, $request);
                    } elseif ($request->get('type') == "FIRE") {
                        $this->FireStore($id, $request);
                    }elseif ($request->get('type') == "PROPERTYDAMAGE" || $request->get('type') == 'ACCIDENTALDAMAGE' || $request->get('type') == "HOUSEHOLDERS" || $request->get('type') == "HOUSEOWNERS" || $request->get('type') == "HOUSEOWNER-BUILDINGS" || $request->get('type') == "HOUSEHOLDERS-CONTENTS") {
                        $this->PropertyLossDamageStore($id, $request);
                    }elseif ($request->get('type') == "LIABILITY") {
                        $this->PublicLiabilityStore($id, $request);
                    }elseif ($request->get('type') == "MOBILEELECTRONICDEVICES" || $request->get('type') == "OFFICECONTENTS") {
                        $this->MobileAndElectronicDeviceStore($id, $request);
                    }

            Log::info("claim");
            return redirect()->to('/admin/claims/')->with('success', 'Claim saved successfully!');
        // } catch (\Exception $e) {

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => $e->getMessage()]);
        // }

    }
    public function BusinessInterruptionstore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            $bi = new BusinessInterruption();
            $bi->policyNumber = $request->policyNumber;
            $bi->newclaim_id = $id;
            $bi->claim_sub_type_id = $request->ClaimSubType;
            $bi->nature_of_interruption = $request->natureinterruption;
            $bi->details_and_estimated_amount_of_loss = $request->details_and_estimated_amount_of_loss;
            $bi->previously_suffered_loss = $request->previously_loss;
            $bi->other_party_interest = $request->other_party_interest;
            $bi->other_insurance_covering = $request->other_insurance_covering;
            $bi->save();

            // DB::commit();

            return $bi->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function FireStore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            $fire = new Fire();
            $fire->policyNumber = $request->policyNumber;
            $fire->newclaim_id = $id;
            $fire->claim_sub_type_id = $request->ClaimSubType;
            $fire->address_of_theft_occurred = $request->address_of_theft_occurred;
            $fire->location_article_stolen_removed = $request->location_article_stolen_removed;
            $fire->property_last_seen = $request->property_last_seen;
            $fire->date_time_of_theft = $request->date_time_of_theft;
            $fire->date_time_loss_discovered = $request->date_time_loss_discovered;
            $fire->brief_description_incident = $request->brief_description_incident;
            $fire->date_time_police_advised	= $request->date_time_police_advised;
            $fire->police_station_name	= $request->police_station_name;
            $fire->anyone_during_burglary	= $request->anyone_during_burglary;
            $fire->details_during_burglary	= $request->details_during_burglary;
            $fire->days_premises_unoccupied	= $request->days_premises_unoccupied;
            $fire->premises_guarded_by_watchman	= $request->premises_guarded_by_watchman;
            $fire->name_of_guard	= $request->name_of_guard;
            $fire->telephone_of_guard	= $request->telephone_of_guard;
            $fire->guard_during_fire	= $request->guard_during_fire;
            $fire->name_of_security_agent	= $request->name_of_security_agent;
            $fire->contract_of_agreement	= $request->contract_of_agreement;
            $fire->premises_properly_secured	= $request->premises_properly_secured;
            $fire->suspect_any_person	= $request->suspect_any_person;
            $fire->suspect_person_details	= $request->suspect_person_details;
            $fire->total_value_premises_buildings	= $request->total_value_premises_buildings;
            $fire->other_insurance_against_fire	= $request->other_insurance_against_fire;
            $fire->insurance_against_fire_details	= $request->insurance_against_fire_details;
            $fire->estimated_amount_of_damaged	= $request->estimated_amount_of_damaged;
            $fire->details_of_previous_loss	= $request->details_of_previous_loss;
            $fire->save();

            // DB::commit();

            return $fire->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function GoodsInTransitStore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            $goodsInTransit = new GoodsInTransit();
            $goodsInTransit->policyNumber = $request->policyNumber;
            $goodsInTransit->newclaim_id = $id;
            $goodsInTransit->claim_sub_type_id = $request->ClaimSubType;
            $goodsInTransit->address_of_premises_loss = $request->address_of_premises_loss;
            $goodsInTransit->details_of_driver = $request->details_of_driver;
            $goodsInTransit->property_last_seen = $request->property_last_seen;
            $goodsInTransit->date_time_of_loss = $request->date_time_of_loss;
            $goodsInTransit->brief_description_incident = $request->brief_description_incident;
            $goodsInTransit->date_time_police_advised = $request->date_time_police_advised;
            $goodsInTransit->police_station_name	= $request->police_station_name;
            $goodsInTransit->witnesses_name	= $request->witnesses_name;
            $goodsInTransit->witnesses_mobile_number	= $request->witnesses_mobile_number;
            $goodsInTransit->total_value_of_loss	= $request->total_value_of_loss;
            $goodsInTransit->consignment_transported_to	= $request->consignment_transported_to;
            $goodsInTransit->consignment_from	= $request->consignment_from;
            $goodsInTransit->vehicle_registration_number	= $request->vehicle_registration_number;
            $goodsInTransit->is_carrier_contracted	= $request->is_carrier_contracted;
            if ($request->hasFile('copy_of_contract')) {
                $file = $request->file('copy_of_contract');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'Claims' . '/' . $request->newclaim_id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $goodsInTransit->copy_of_contract = $filePath;
            }
            // $goodsInTransit->copy_of_contract	= $request->copy_of_contract;
            $goodsInTransit->carrier_has_own_GIT_ins	= $request->carrier_has_own_GIT_ins;
            $goodsInTransit->other_insurance_against_theft	= $request->other_insurance_against_theft;
            $goodsInTransit->insurance_against_theft_details	= $request->insurance_against_theft_details;
            $goodsInTransit->details_of_previous_loss_records	= $request->details_of_previous_loss_records;
            $goodsInTransit->save();

            // DB::commit();

            return $goodsInTransit->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function TravelInsuranceStore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            $travelIns = new TravelInsurance();
            $travelIns->policyNumber = $request->policyNumber;
            $travelIns->newclaim_id = $id;
            $travelIns->claim_sub_type_id = $request->ClaimSubType;
            $travelIns->title = $request->title;
            $travelIns->other_title = $request->other_title;
            $travelIns->surname = $request->surname;
            $travelIns->forename = $request->forename;
            $travelIns->dob = $request->dob;
            $travelIns->passport_no = $request->passport_no;
            $travelIns->nationality	= $request->nationality;
            $travelIns->telephone	= $request->telephone;
            $travelIns->post_code	= $request->post_code;
            $travelIns->mobile	= $request->mobile;
            $travelIns->email	= $request->email;
            $travelIns->home_address	= $request->home_address;
            $travelIns->policy_number	= $request->policy_number;
            $travelIns->issued_by	= $request->issued_by;
            $travelIns->issued_on	= $request->issued_on;
            $travelIns->valid_from	= $request->valid_from;
            $travelIns->valid_to	= $request->valid_to;
            $travelIns->beneficiary	= $request->beneficiary;
            $travelIns->bank_name	= $request->bank_name;
            $travelIns->bank_address	= $request->bank_address;
            $travelIns->account_number	= $request->account_number;
            $travelIns->iban	= $request->iban;
            $travelIns->swift_code	= $request->swift_code;
            $travelIns->bic_code	= $request->bic_code;
            $travelIns->other_insurance_policy	= $request->other_insurance_policy;
            $travelIns->name_insurance_company	= $request->name_insurance_company;
            $travelIns->address	= $request->address;
            $travelIns->phone_number	= $request->phone_number;
            $travelIns->type_of_refund	= $request->type_of_refund;
            $travelIns->type_of_refund_other	= $request->type_of_refund_other;
            $travelIns->compulsory_doc_all_claims	= $request->compulsory_doc_all_claims;
            $travelIns->medical_dental_care	= $request->medical_dental_care;
            $travelIns->claim_delayed_luggage	= $request->claim_delayed_luggage;
            $travelIns->claim_loss_personal_doc	= $request->claim_loss_personal_doc;
            $travelIns->claim_lost_luggage	= $request->claim_lost_luggage;
            $travelIns->claim_trip_cancel	= $request->claim_trip_cancel;
            $travelIns->claim_delayed_flight	= $request->claim_delayed_flight;
            $travelIns->save();

            // DB::commit();
            return $travelIns->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function DefectiveWorkmanshipStore($id, $request)
    {

        // DB::beginTransaction();

        // try {
            $dw = new DefectiveWorkmanship();
            $dw->policyNumber = $request->policyNumber;
            $dw->newclaim_id = $id;
            $dw->location_of_accident = $request->Location_of_accident;
            $dw->accident_date_time = $request->accident_date_time;
            $dw->owners_name = $request->owners_name;
            $dw->telephone_number = $request->telephone_number;
            $dw->mobile_number = $request->mobile_number;
            $dw->address = $request->address;
            $dw->make = $request->make;
            $dw->model = $request->model;
            $dw->registration = $request->registration;
            $dw->vehicle_drivable = $request->vehicle_drivable;
            $dw->vehicle_handed_claimant = $request->vehicle_handed_claimant;
            $dw->when_vehicle_handed = $request->when_vehicle_handed;
            $dw->allegations_received = $request->allegations_received;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function WorkersCompensationStore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            $dw = new WorkersCompensation();
            $dw->newclaim_id = $id;
            $dw->policyNumber = $request->policyNumber;
            $dw->employer_name = $request->employer_name;
            $dw->employer_policy_no = $request->employer_policy_no;
            $dw->employer_Address = $request->employer_Address;
            $dw->employer_phone_no = $request->employer_phone_no;
            $dw->employer_trade_or_business = $request->employer_trade_or_business;
            $dw->injured_name = $request->injured_name;
            $dw->injured_age = $request->injured_age;
            $dw->injured_address = $request->injured_address;
            $dw->injured_status = $request->injured_status;
            $dw->injured_occupation = $request->injured_occupation;
            $dw->injured_nationality = $request->injured_nationality;
            $dw->injured_service_period = $request->injured_service_period;
            $dw->your_direct_employ = $request->your_direct_employ;
            $dw->address_of_contractor = $request->address_of_contractor;
            $dw->time_of_accident = $request->time_of_accident;
            if(!empty($request->date)){
                    $dw->date = Carbon::parse($request->date)->format('Y-m-d');
                }else{
                    $dw->date = NULL;
                }
            $dw->time = $request->time;
            $dw->place = $request->place;
            $dw->injured_person_ceased_work = $request->injured_person_ceased_work;
            $dw->how_accident_occur = $request->how_accident_occur;
            $dw->first_report_accident = $request->first_report_accident;
            $dw->machine_state_part_causing = $request->machine_state_part_causing;
            $dw->state_names_witness = $request->state_names_witness;
            $dw->state_nature_injuries = $request->state_nature_injuries;
            $dw->influence_drugs_or_drink = $request->influence_drugs_or_drink;
            $dw->please_explain = $request->please_explain;
            $dw->anyone_negligence = $request->anyone_negligence;
            $dw->give_particulars = $request->give_particulars;
            $dw->perform_any_part_duties = $request->perform_any_part_duties;
            $dw->period_of_disablement = $request->period_of_disablement;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function PropertyLossDamageStore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            $dw = new PropertyLossDamage();
            $dw->newclaim_id = $id;
            $dw->policyNumber = $request->policyNumber;
            $dw->broker_agent = $request->broker_agent;
            $dw->policy_no = $request->policy_no;
            $dw->id_number = $request->id_number;
            $dw->name_occupation = $request->name_occupation;
            $dw->address_tele_no = $request->address_tele_no;
            $dw->date_time_of_loss_damage = $request->date_time_of_loss_damage;
            $dw->loss_damage_discovered = $request->loss_damage_discovered;
            $dw->loss_damage_occurred = $request->loss_damage_occurred;
            $dw->premises_occupied = $request->premises_occupied;
            $dw->last_occupied = $request->last_occupied;
            $dw->purpose_of_occupation = $request->purpose_of_occupation;
            $dw->nature_interruption = $request->nature_interruption;
            $dw->loss_for_each_item = $request->loss_for_each_item;
            $dw->previously_suffered_loss = $request->previously_suffered_loss;
            $dw->give_details = $request->give_details;
            $dw->name_of_insurer = $request->name_of_insurer;
            $dw->reference_no_station = $request->reference_no_station;
            $dw->interest_insured_property = $request->interest_insured_property;
            $dw->other_insurance_covering = $request->other_insurance_covering;
            $dw->give_name_insurer = $request->give_name_insurer;
            $dw->value_all_property = $request->value_all_property;
            $dw->when_last_valued = $request->when_last_valued;
            $dw->name_of_bank = $request->name_of_bank;
            $dw->branch = $request->branch;
            $dw->id_number = $request->id_number;
            $dw->name_of_account = $request->name_of_account;
            $dw->account_number = $request->account_number;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function PublicLiabilityStore($id, $request)
    {
        // DB::beginTransaction();

        // try {
                //    dd($request);
            $dw = new PublicLiability();
            $dw->newclaim_id = $id;
            $dw->policyNumber = $request->policyNumber;
            $dw->insured_name = $request->insured_name;
            $dw->insured_treding_name = $request->insured_treding_name;
            $dw->insured_postal_address = $request->insured_postal_address;
            $dw->insured_email = $request->insured_email;
            $dw->insured_telephone_no = $request->insured_telephone_no;
            $dw->insured_facsimile = $request->insured_facsimile;
            $dw->insured_mobile_no = $request->insured_mobile_no;
                if(!empty($request->date)){
                    $dw->accident_date = Carbon::parse($request->accident_date)->format('Y-m-d');
                }else{
                    $dw->accident_date = NULL;
                }
            $dw->accident_time = $request->accident_time;
            $dw->accident_incident = $request->accident_incident;
            $dw->accident_injuries = $request->accident_injuries;
            $dw->accident_liability = $request->accident_liability;
            $dw->accident_person = $request->accident_person;
            $dw->accident_contacted = $request->accident_contacted;
            $dw->attach_contractor = $request->attach_contractor;
            $dw->attach_employee = $request->attach_employee;
            $dw->attach_employed = $request->attach_employed;
            $dw->attach_blame = $request->attach_blame;
            $dw->attach_circumstances = $request->attach_circumstances;
            $dw->attach_property = $request->attach_property;
            $dw->attach_details = $request->attach_details;
            $dw->attach_owner = $request->attach_owner;
            $dw->attach_damage = $request->attach_damage;
            $dw->claim_name = $request->claim_name;
            $dw->claim_telephone = $request->claim_telephone;
            $dw->claim_mobile = $request->claim_mobile;
            $dw->claim_postal = $request->claim_postal;
            $dw->claim_solicitor = $request->claim_solicitor;
            $dw->witness1_name = $request->witness1_name;
            $dw->witness1_telephone = $request->witness1_telephone;
            $dw->witness1_mobile = $request->witness1_mobile;
            $dw->witness1_postal = $request->witness1_postal;
            $dw->witness1_relationship = $request->witness1_relationship;
            $dw->witness2_telephone = $request->witness2_telephone;
            $dw->witness2_mobile = $request->witness2_mobile;
            $dw->witness2_postal = $request->witness2_postal;
            $dw->witness2_relationship = $request->witness2_relationship;
            $dw->witness2_damage = $request->witness2_damage;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function FidelityGuaranteeStore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            //dd( $request->all());
            $fg = new FidelityGuarantee();
            $fg->policyNumber = $request->policyNumber;
            $fg->newclaim_id = $id;

            if (isset($request->defaulting_employees_name) && $request->defaulting_employees_name != null) {
                $employees = [];
                foreach ($request->defaulting_employees_name as $key => $name) {
                    $employees[$name] = $request->defaulting_employees_position[$key];
                }
                $jsonFormatted = json_encode($employees, JSON_PRETTY_PRINT);
                $fg->defaulting_employees_name = $jsonFormatted;
            }
            $fg->employees_been_involved = $request->employees_been_involved;
            $fg->circumstances = $request->circumstances;
            $fg->save();

            // DB::commit();
            return $fg->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function MobileAndElectronicDeviceStore($id, $request)
    {
        // DB::beginTransaction();
        // try {
            $mobileAndElectDev = new MobileAndElectronicDevices();
            $mobileAndElectDev->policyNumber = $request->policyNumber;
            $mobileAndElectDev->newclaim_id = $id;
            $mobileAndElectDev->claim_sub_type_id = $request->ClaimSubType;
            $mobileAndElectDev->insured_name = $request->insured_name;
            $mobileAndElectDev->email_address = $request->email_address;
            $mobileAndElectDev->address = $request->address;
            $mobileAndElectDev->telephone_no = $request->telephone_no;
            $mobileAndElectDev->property_stolen_damaged = $request->property_stolen_damaged;
            $mobileAndElectDev->premises_address = $request->premises_address;
            $mobileAndElectDev->date_time_loss_discovered = $request->date_time_loss_discovered;
            $mobileAndElectDev->whom_discovered = $request->whom_discovered;
            $mobileAndElectDev->articles_last_seen = $request->articles_last_seen;
            $mobileAndElectDev->whom_last_seen_and_where = $request->whom_last_seen_and_where;
            $mobileAndElectDev->when_police_notified = $request->when_police_notified;
            $mobileAndElectDev->police_station_name = $request->police_station_name;
            $mobileAndElectDev->officer_name = $request->officer_name;
            $mobileAndElectDev->report_number = $request->report_number;
            $mobileAndElectDev->circumstances_loss_damage = $request->circumstances_loss_damage;
            $mobileAndElectDev->thorough_search_made_for_article = $request->thorough_search_made_for_article;
            $mobileAndElectDev->loss_cause = $request->loss_cause;
            $mobileAndElectDev->loss_by_other_cause = $request->loss_by_other_cause;
            $mobileAndElectDev->property_insured_against = $request->property_insured_against;
            $mobileAndElectDev->preinspection_images_uploaded = $request->preinspection_images_uploaded;
            $mobileAndElectDev->sole_owner_of_property = $request->sole_owner_of_property;
            $mobileAndElectDev->is_sole_owner_of_property = $request->is_sole_owner_of_property;
            $mobileAndElectDev->save();

            // DB::commit();

            return $mobileAndElectDev->id;

        // } catch (\Exception $e) {
                // DB::rollBack();
        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => $e->getMessage()]);
        // }
    }

    public function AllRiskAndElectronicEquipmentstore($id, $request)
    {
        // DB::beginTransaction();

        // try {
                Log::info("AllRiskAndElectronicEquipment");
            $allRisk = new AllRiskAndElectronicEquipment();
            $allRisk->policyNumber = $request->policyNumber;
            $allRisk->newclaim_id = $id;
            $allRisk->claim_sub_type_id = $request->ClaimSubType;
            $allRisk->property_stolen_damaged = $request->property_stolen_damaged;
            $allRisk->circumstances_loss_damage = $request->circumstances_loss_damage;
            $allRisk->thorough_search_made_for_article = $request->thorough_search_made_for_article;
            $allRisk->loss_cause = $request->loss_cause;
            $allRisk->loss_by_other_cause = $request->loss_by_other_cause;
            $allRisk->stolenfromcar_unlockedpremises = $request->stolenfromcar_unlockedpremises;
            $allRisk->sole_owner_of_property = $request->sole_owner_of_property;
            $allRisk->is_sole_owner_of_property = $request->is_sole_owner_of_property;
            $allRisk->save();

            // DB::commit();
            return $allRisk->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function Burglarystore($id, $request)
    {
        // DB::beginTransaction();

        // try {
            $burglary = new Burglary();
            $burglary->policyNumber = $request->policyNumber;
            $burglary->newclaim_id = $id;
            $burglary->claim_sub_type_id = $request->ClaimSubType;
            $burglary->address_of_premises = $request->address_of_premises;
            $burglary->description_of_incident = $request->description_of_incident;
            $burglary->date_time_police_advised = $request->date_time_police_advised ? \Carbon\Carbon::parse($request->date_time_police_advised)->format('Y-m-d') : null;
            $burglary->anyone_on_premises = $request->anyone_on_premises;
            $burglary->anyone_on_premises_brief = $request->anyone_on_premises_brief;
            $burglary->guarded_by_watchman = $request->guarded_by_watchman;
            $burglary->premises_properly_secured = $request->premises_properly_secured;
            $burglary->total_value_contents_of_premises = $request->total_value_contents_of_premises;
            $burglary->stock_books_records_located = $request->stock_books_records_located;
            $burglary->save();

            // DB::commit();
            return $burglary->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function BusinessInterruptionUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $bi = BusinessInterruption::where('id',$request->bi_id)->first();
            $bi->claim_sub_type_id = $request->ClaimSubType;
            $bi->nature_of_interruption = $request->natureinterruption;
            $bi->details_and_estimated_amount_of_loss = $request->details_and_estimated_amount_of_loss;
            $bi->previously_suffered_loss = $request->previously_loss;
            $bi->other_party_interest = $request->other_party_interest;
            $bi->other_insurance_covering = $request->other_insurance_covering;
            $bi->save();

            // DB::commit();
            return true;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function TravelInsuranceUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $travelIns = TravelInsurance::where('id',$request->travel_ins_id)->first();
            $travelIns->claim_sub_type_id = $request->ClaimSubType;
            $travelIns->title = $request->title;
            $travelIns->other_title = $request->other_title;
            $travelIns->surname = $request->surname;
            $travelIns->forename = $request->forename;
            $travelIns->dob = $request->dob;
            $travelIns->passport_no = $request->passport_no;
            $travelIns->nationality	= $request->nationality;
            $travelIns->telephone	= $request->telephone;
            $travelIns->post_code	= $request->post_code;
            $travelIns->mobile	= $request->mobile;
            $travelIns->email	= $request->email;
            $travelIns->home_address	= $request->home_address;
            $travelIns->policy_number	= $request->policy_number;
            $travelIns->issued_by	= $request->issued_by;
            $travelIns->issued_on	= $request->issued_on;
            $travelIns->valid_from	= $request->valid_from;
            $travelIns->valid_to	= $request->valid_to;
            $travelIns->beneficiary	= $request->beneficiary;
            $travelIns->bank_name	= $request->bank_name;
            $travelIns->bank_address	= $request->bank_address;
            $travelIns->account_number	= $request->account_number;
            $travelIns->iban	= $request->iban;
            $travelIns->swift_code	= $request->swift_code;
            $travelIns->bic_code	= $request->bic_code;
            $travelIns->other_insurance_policy	= $request->other_insurance_policy;
            $travelIns->name_insurance_company	= $request->name_insurance_company;
            $travelIns->address	= $request->address;
            $travelIns->phone_number	= $request->phone_number;
            $travelIns->type_of_refund	= $request->type_of_refund;
            $travelIns->type_of_refund_other	= $request->type_of_refund_other;
            $travelIns->compulsory_doc_all_claims	= $request->compulsory_doc_all_claims;
            $travelIns->medical_dental_care	= $request->medical_dental_care;
            $travelIns->claim_delayed_luggage	= $request->claim_delayed_luggage;
            $travelIns->claim_loss_personal_doc	= $request->claim_loss_personal_doc;
            $travelIns->claim_lost_luggage	= $request->claim_lost_luggage;
            $travelIns->claim_trip_cancel	= $request->claim_trip_cancel;
            $travelIns->claim_delayed_flight	= $request->claim_delayed_flight;
            $travelIns->save();

            // DB::commit();
            return true;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function DefectiveWorkmanshipUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $dw = DefectiveWorkmanship::where('id',$request->dw_id)->first();
            $dw->location_of_accident = $request->Location_of_accident;
            $dw->accident_date_time = $request->accident_date_time;
            $dw->owners_name = $request->owners_name;
            $dw->telephone_number = $request->telephone_number;
            $dw->mobile_number = $request->mobile_number;
            $dw->address = $request->address;
            $dw->make = $request->make;
            $dw->model = $request->model;
            $dw->registration = $request->registration;
            $dw->vehicle_drivable = $request->vehicle_drivable;
            $dw->vehicle_handed_claimant = $request->vehicle_handed_claimant;
            $dw->when_vehicle_handed = $request->when_vehicle_handed;
            $dw->allegations_received = $request->allegations_received;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }
    public function WorkersCompensationUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $dw = WorkersCompensation::find($request->coverage_claim_id);
            $dw->employer_name = $request->employer_name;
            $dw->employer_policy_no = $request->employer_policy_no;
            $dw->employer_Address = $request->employer_Address;
            $dw->employer_phone_no = $request->employer_phone_no;
            $dw->employer_trade_or_business = $request->employer_trade_or_business;
            $dw->injured_name = $request->injured_name;
            $dw->injured_age = $request->injured_age;
            $dw->injured_address = $request->injured_address;
            $dw->injured_status = $request->injured_status;
            $dw->injured_occupation = $request->injured_occupation;
            $dw->injured_nationality = $request->injured_nationality;
            $dw->injured_service_period = $request->injured_service_period;
            $dw->your_direct_employ = $request->your_direct_employ;
            $dw->address_of_contractor = $request->address_of_contractor;
            $dw->time_of_accident = $request->time_of_accident;
            if(!empty($request->date)){
                    $dw->date = Carbon::parse($request->date)->format('Y-m-d');
            }else{
                    $dw->date = NULL;
            }
            $dw->time = $request->time;
            $dw->place = $request->place;
            $dw->injured_person_ceased_work = $request->injured_person_ceased_work;
            $dw->how_accident_occur = $request->how_accident_occur;
            $dw->first_report_accident = $request->first_report_accident;
            $dw->machine_state_part_causing = $request->machine_state_part_causing;
            $dw->state_names_witness = $request->state_names_witness;
            $dw->state_nature_injuries = $request->state_nature_injuries;
            $dw->influence_drugs_or_drink = $request->influence_drugs_or_drink;
            $dw->please_explain = $request->please_explain;
            $dw->anyone_negligence = $request->anyone_negligence;
            $dw->give_particulars = $request->give_particulars;
            $dw->perform_any_part_duties = $request->perform_any_part_duties;
            $dw->period_of_disablement = $request->period_of_disablement;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function PropertyLossDamageUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $dw = PropertyLossDamage::find($request->coverage_claim_id);
            $dw->broker_agent = $request->broker_agent;
            $dw->policy_no = $request->policy_no;
            $dw->id_number = $request->id_number;
            $dw->name_occupation = $request->name_occupation;
            $dw->address_tele_no = $request->address_tele_no;
            $dw->date_time_of_loss_damage = $request->date_time_of_loss_damage;
            $dw->loss_damage_discovered = $request->loss_damage_discovered;
            $dw->loss_damage_occurred = $request->loss_damage_occurred;
            $dw->premises_occupied = $request->premises_occupied;
            $dw->last_occupied = $request->last_occupied;
            $dw->purpose_of_occupation = $request->purpose_of_occupation;
            $dw->nature_interruption = $request->nature_interruption;
            $dw->loss_for_each_item = $request->loss_for_each_item;
            $dw->previously_suffered_loss = $request->previously_suffered_loss;
            $dw->give_details = $request->give_details;
            $dw->name_of_insurer = $request->name_of_insurer;
            $dw->reference_no_station = $request->reference_no_station;
            $dw->interest_insured_property = $request->interest_insured_property;
            $dw->other_insurance_covering = $request->other_insurance_covering;
            $dw->give_name_insurer = $request->give_name_insurer;
            $dw->value_all_property = $request->value_all_property;
            $dw->when_last_valued = $request->when_last_valued;
            $dw->name_of_bank = $request->name_of_bank;
            $dw->branch = $request->branch;
            $dw->id_number = $request->id_number;
            $dw->name_of_account = $request->name_of_account;
            $dw->account_number = $request->account_number;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function PublicLiabilityUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $dw = PublicLiability::find($request->coverage_claim_id);
            $dw->insured_name = $request->insured_name;
            $dw->insured_treding_name = $request->insured_treding_name;
            $dw->insured_postal_address = $request->insured_postal_address;
            $dw->insured_email = $request->insured_email;
            $dw->insured_telephone_no = $request->insured_telephone_no;
            $dw->insured_facsimile = $request->insured_facsimile;
            $dw->insured_mobile_no = $request->insured_mobile_no;
                if(!empty($request->date)){
                        $dw->accident_date = Carbon::parse($request->accident_date)->format('Y-m-d');
                }else{
                        $dw->accident_date = NULL;
                }
            $dw->accident_time = $request->accident_time;
            $dw->accident_incident = $request->accident_incident;
            $dw->accident_injuries = $request->accident_injuries;
            $dw->accident_liability = $request->accident_liability;
            $dw->accident_person = $request->accident_person;
            $dw->accident_contacted = $request->accident_contacted;
            $dw->attach_contractor = $request->attach_contractor;
            $dw->attach_employee = $request->attach_employee;
            $dw->attach_employed = $request->attach_employed;
            $dw->attach_blame = $request->attach_blame;
            $dw->attach_circumstances = $request->attach_circumstances;
            $dw->attach_property = $request->attach_property;
            $dw->attach_details = $request->attach_details;
            $dw->attach_owner = $request->attach_owner;
            $dw->attach_damage = $request->attach_damage;
            $dw->claim_name = $request->claim_name;
            $dw->claim_telephone = $request->claim_telephone;
            $dw->claim_mobile = $request->claim_mobile;
            $dw->claim_postal = $request->claim_postal;
            $dw->claim_solicitor = $request->claim_solicitor;
            $dw->witness1_name = $request->witness1_name;
            $dw->witness1_telephone = $request->witness1_telephone;
            $dw->witness1_mobile = $request->witness1_mobile;
            $dw->witness1_postal = $request->witness1_postal;
            $dw->witness1_relationship = $request->witness1_relationship;
            $dw->witness2_telephone = $request->witness2_telephone;
            $dw->witness2_mobile = $request->witness2_mobile;
            $dw->witness2_postal = $request->witness2_postal;
            $dw->witness2_relationship = $request->witness2_relationship;
            $dw->witness2_damage = $request->witness2_damage;
            $dw->save();

            // DB::commit();
            return $dw->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function BurglaryUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $burglary = Burglary::where('id',$request->burglary_id)->first();
            $burglary->claim_sub_type_id = $request->ClaimSubType;
            $burglary->address_of_premises = $request->address_of_premises;
            $burglary->description_of_incident = $request->description_of_incident;
            $burglary->date_time_police_advised = $request->date_time_police_advised ? \Carbon\Carbon::parse($request->date_time_police_advised)->format('Y-m-d') : null;
            $burglary->anyone_on_premises = $request->anyone_on_premises;
            $burglary->anyone_on_premises_brief = $request->anyone_on_premises_brief;
            $burglary->guarded_by_watchman = $request->guarded_by_watchman;
            $burglary->premises_properly_secured = $request->premises_properly_secured;
            $burglary->total_value_contents_of_premises = $request->total_value_contents_of_premises;
            $burglary->stock_books_records_located = $request->stock_books_records_located;
            $burglary->save();

            // DB::commit();
            return true;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function AllRiskAndElectronicEquipmentUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $allRisk = AllRiskAndElectronicEquipment::where('id',$request->allrisk_id)->first();
            $allRisk->claim_sub_type_id = $request->ClaimSubType;
            $allRisk->property_stolen_damaged = $request->property_stolen_damaged;
            $allRisk->circumstances_loss_damage = $request->circumstances_loss_damage;
            $allRisk->thorough_search_made_for_article = $request->thorough_search_made_for_article;
            $allRisk->loss_cause = $request->loss_cause;
            $allRisk->loss_by_other_cause = $request->loss_by_other_cause;
            $allRisk->stolenfromcar_unlockedpremises = $request->stolenfromcar_unlockedpremises;
            $allRisk->sole_owner_of_property = $request->sole_owner_of_property;
            $allRisk->is_sole_owner_of_property = $request->is_sole_owner_of_property;
            $allRisk->save();

            // DB::commit();
            return true;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }
    public function FidelityGuaranteeUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            //dd( $request->all());
            $fg = FidelityGuarantee::where('id',$request->fg_id)->first();


            if (isset($request->defaulting_employees_name) && $request->defaulting_employees_name != null) {
                $employees = [];
                foreach ($request->defaulting_employees_name as $key => $name) {
                    $employees[$name] = $request->defaulting_employees_position[$key];
                }
                $jsonFormatted = json_encode($employees, JSON_PRETTY_PRINT);
                $fg->defaulting_employees_name = $jsonFormatted;
            }
            $fg->employees_been_involved = $request->employees_been_involved;
            $fg->circumstances = $request->circumstances;
            $fg->save();

            // DB::commit();
            return $fg->id;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function GoodsInTransitUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $goodsInTransit = GoodsInTransit::where('id',$request->goods_in_transit_id)->first();
            $goodsInTransit->claim_sub_type_id = $request->ClaimSubType;
            $goodsInTransit->address_of_premises_loss = $request->address_of_premises_loss;
            $goodsInTransit->details_of_driver = $request->details_of_driver;
            $goodsInTransit->property_last_seen = $request->property_last_seen;
            $goodsInTransit->date_time_of_loss = $request->date_time_of_loss;
            $goodsInTransit->brief_description_incident = $request->brief_description_incident;
            $goodsInTransit->date_time_police_advised = $request->date_time_police_advised;
            $goodsInTransit->police_station_name	= $request->police_station_name;
            $goodsInTransit->witnesses_name	= $request->witnesses_name;
            $goodsInTransit->witnesses_mobile_number	= $request->witnesses_mobile_number;
            $goodsInTransit->total_value_of_loss	= $request->total_value_of_loss;
            $goodsInTransit->consignment_transported_to	= $request->consignment_transported_to;
            $goodsInTransit->consignment_from	= $request->consignment_from;
            $goodsInTransit->vehicle_registration_number	= $request->vehicle_registration_number;
            $goodsInTransit->is_carrier_contracted	= $request->is_carrier_contracted;
            if ($request->hasFile('copy_of_contract')) {
                $file = $request->file('copy_of_contract');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'Claims' . '/' . $request->newclaim_id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $goodsInTransit->copy_of_contract = $filePath;
            }
            // $goodsInTransit->copy_of_contract	= $request->copy_of_contract;
            $goodsInTransit->carrier_has_own_GIT_ins	= $request->carrier_has_own_GIT_ins;
            $goodsInTransit->other_insurance_against_theft	= $request->other_insurance_against_theft;
            $goodsInTransit->insurance_against_theft_details	= $request->insurance_against_theft_details;
            $goodsInTransit->details_of_previous_loss_records	= $request->details_of_previous_loss_records;
            $goodsInTransit->save();

            // DB::commit();
            return true;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function FireUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $fire = Fire::where('id',$request->fire_id)->first();
            $fire->claim_sub_type_id = $request->ClaimSubType;
            $fire->address_of_theft_occurred = $request->address_of_theft_occurred;
            $fire->location_article_stolen_removed = $request->location_article_stolen_removed;
            $fire->property_last_seen = $request->property_last_seen;
            $fire->date_time_of_theft = $request->date_time_of_theft;
            $fire->date_time_loss_discovered = $request->date_time_loss_discovered;
            $fire->brief_description_incident = $request->brief_description_incident;
            $fire->date_time_police_advised	= $request->date_time_police_advised;
            $fire->police_station_name	= $request->police_station_name;
            $fire->anyone_during_burglary	= $request->anyone_during_burglary;
            $fire->details_during_burglary	= $request->details_during_burglary;
            $fire->days_premises_unoccupied	= $request->days_premises_unoccupied;
            $fire->premises_guarded_by_watchman	= $request->premises_guarded_by_watchman;
            $fire->name_of_guard	= $request->name_of_guard;
            $fire->telephone_of_guard	= $request->telephone_of_guard;
            $fire->guard_during_fire	= $request->guard_during_fire;
            $fire->name_of_security_agent	= $request->name_of_security_agent;
            $fire->contract_of_agreement	= $request->contract_of_agreement;
            $fire->premises_properly_secured	= $request->premises_properly_secured;
            $fire->suspect_any_person	= $request->suspect_any_person;
            $fire->suspect_person_details	= $request->suspect_person_details;
            $fire->total_value_premises_buildings	= $request->total_value_premises_buildings;
            $fire->other_insurance_against_fire	= $request->other_insurance_against_fire;
            $fire->insurance_against_fire_details	= $request->insurance_against_fire_details;
            $fire->estimated_amount_of_damaged	= $request->estimated_amount_of_damaged;
            $fire->details_of_previous_loss	= $request->details_of_previous_loss;
            $fire->save();

            // DB::commit();
            return true;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function MobileAndElectronicDeviceUpdate($request)
    {
        // DB::beginTransaction();

        // try {
            $mobileAndElectDev = MobileAndElectronicDevices::where('id',$request->mobileAndElectDev_id)->first();
            $mobileAndElectDev->claim_sub_type_id = $request->ClaimSubType;
            $mobileAndElectDev->insured_name = $request->insured_name;
            $mobileAndElectDev->email_address = $request->email_address;
            $mobileAndElectDev->address = $request->address;
            $mobileAndElectDev->telephone_no = $request->telephone_no;
            $mobileAndElectDev->property_stolen_damaged = $request->property_stolen_damaged;
            $mobileAndElectDev->premises_address = $request->premises_address;
            $mobileAndElectDev->date_time_loss_discovered = $request->date_time_loss_discovered;
            $mobileAndElectDev->whom_discovered = $request->whom_discovered;
            $mobileAndElectDev->articles_last_seen = $request->articles_last_seen;
            $mobileAndElectDev->whom_last_seen_and_where = $request->whom_last_seen_and_where;
            $mobileAndElectDev->when_police_notified = $request->when_police_notified;
            $mobileAndElectDev->police_station_name = $request->police_station_name;
            $mobileAndElectDev->officer_name = $request->officer_name;
            $mobileAndElectDev->report_number = $request->report_number;
            $mobileAndElectDev->circumstances_loss_damage = $request->circumstances_loss_damage;
            $mobileAndElectDev->thorough_search_made_for_article = $request->thorough_search_made_for_article;
            $mobileAndElectDev->loss_cause = $request->loss_cause;
            $mobileAndElectDev->loss_by_other_cause = $request->loss_by_other_cause;
            $mobileAndElectDev->property_insured_against = $request->property_insured_against;
            $mobileAndElectDev->preinspection_images_uploaded = $request->preinspection_images_uploaded;
            $mobileAndElectDev->sole_owner_of_property = $request->sole_owner_of_property;
            $mobileAndElectDev->is_sole_owner_of_property = $request->is_sole_owner_of_property;
            $mobileAndElectDev->save();

            // DB::commit();
            return true;

        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }
    }

    public function claimView($id, Request $request)
    {
        $claims = NewClaim::where('id', $id)->first();
        $recipientKyc = RecipientKyc::where('claim_id', $id)->first();
        $policy = Policy::where('id', $claims->policy_id)->first();
        $policyProduct = Policy::where('id', $claims->policy_id)->first('product_id');
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
        $banking = CustomerBanking::where('claim_id', $id)->get();
        if ($banking == NULL) {
            $banking = CustomerBanking::where('policy_id', $policy->id)->get();
        }
        $userInfo = NULL;
        $fg = null;
        $bi = null;
        $dw = null;
        $agents = null;
        $allRisk = null;
        $burglary = null;
        $stateName = NULL;
        $storeName = NULL;

        if ($claims) {
            if($claims->claim_type == "BUSINESSINTERRUPTION"){
                $bi =  BusinessInterruption::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
            }else if($claims->claim_type == "BUSINESSALLRISKS"){
                $allRisk = AllRiskAndElectronicEquipment::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
            }else if($claims->claim_type == "WORKERSCOMPENSATION" || $claims->claim_type == "STATEDBENEFITS"){
                // $dw = DefectiveWorkmanship::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
                $dw = WorkersCompensation::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
            }else if($claims->claim_type == "THEFT"){
                $burglary = Burglary::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
            }else if($claims->claim_type == "FIDELITYGUARANTEE"){
                $fg = FidelityGuarantee::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
            }else if($claims->claim_type == "PROPERTYLOSSDAMAGE"){
                $fg = PropertyLossDamage::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
            }else if($claims->claim_type == "PUBLICLIABILITY"){
                $fg = PublicLiability::where('newclaim_id',$claims->id)->orderBy('id','desc')->first();
            }
        }

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

        $agents = User::with('roles')->where('active',1)->get();

        return view('admin.claims.newClaims.general', compact('policy','agents','bi','allRisk','claims','dw','fg','burglary','userInfo','recipientKyc','policyProduct','product','productFactors','kyc','banking','cityName','stateName','passpostIssueCountry','productPlan','storeName'));

    }

    public function edit($claimType,$claim_number)
    {
        $fg = null;
        $bi = null;
        $dw = null;
        $stores = null;
        $policy = null;
        $agents = null;
        $allRisk = null;
        $burglary = null;
        $claimType = $claimType;
        $stores = Stores::where('status',1)->get();

        $agents = User::with('roles')->where('active',1)->get();
        $newclaim = NewClaim::where('claim_number',$claim_number)->orderBy('id','desc')->first();
        $policy = Policy::where('policyNumber',$newclaim->policyNumber)->first();
        if($claimType == "BUSINESSINTERRUPTION"){
            $bi =  BusinessInterruption::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
        }else if($claimType == "BUSINESSALLRISKS"){
            $allRisk = AllRiskAndElectronicEquipment::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
        }else if($claimType == "WORKERSCOMPENSATION" || $claimType == "STATEDBENEFITS"){
            // $dw = DefectiveWorkmanship::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
            $dw = WorkersCompensation::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
        }else if($claimType == "THEFT"){
            $burglary = Burglary::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
        }else if($claimType == "FIDELITYGUARANTEE"){
            $fg = FidelityGuarantee::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
        }else if($claimType == "PROPERTYLOSSDAMAGE"){
            $fg = PropertyLossDamage::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
        }else if($claimType == "PUBLICLIABILITY"){
            $fg = PublicLiability::where('newclaim_id',$newclaim->id)->orderBy('id','desc')->first();
        }


        return view('admin.claims.newClaims.edit', compact('policy','agents','stores','claimType','bi','allRisk','newclaim','dw','fg','burglary'));

    }
    public function update(Request $request, $id)
    {
        // DB::beginTransaction();

        // try {
            // $validatedData = $request->validate([
            //     // 'policyNumber' => 'required',
            //     'Location' => 'required',
            //     //'Isthismotorclaim' => 'required|boolean',

            //     'PAInvolved' => 'required|boolean',
            //     'AttorneyInvolved' => 'required|boolean',
            //     'ClaimReportedby' => 'required',
            //     // 'ClaimType' => 'required',
            //     'ClaimSubType' => 'required',

            //     'TypeofLoss' => 'required',
            //     'DateofLoss' => 'required|date',
            //     'ServiceRepresentative' => 'required',
            //     'CatastropheLoss' => 'required|boolean',
            //     'EventName' => 'nullable',
            //     'PrimaryAttorneyAssigned' => 'nullable',
            //     'CoAttorneyAssigned' => 'nullable',
            //     'AssignedDate' => 'nullable|date',
            //     'DFSComplaint' => 'required|boolean',
            //     // 'ClaimsAllocatedTo' => 'nullable',
            //     // 'ClaimsAllocatedOn' => 'nullable|date',
            //     // 'ClaimApproved' => 'required|boolean',
            //     // 'ClaimStatus' => 'required',
            //     // 'ClaimsubStatus' => 'nullable',

            // ]);

            // Process the data
                    $claims = Claim::where('id', $id)->first();
                    $newclaim = NewClaim::where('id',$request->newclaim_id)->first(); // Replace with your actual model

                    $newclaim->location_id = $request->Location;
                    $newclaim->is_motor_claim = isset($request->Isthismotorclaim) ? $request->Isthismotorclaim : null;
                    $newclaim->vehicle_plate = isset($request->vehiclePlate) ? $request->vehiclePlate:null;
                    $newclaim->co_attorney_involved = $request->co_attorney_involved;
                    $newclaim->attorney_involved = $request->AttorneyInvolved;
                    $newclaim->claim_reported_by = $request->ClaimReportedby;
                    // $newclaim->claim_type = $request->ClaimType;
                    $newclaim->claim_sub_type_id = $request->ClaimSubType;
                    $newclaim->type_of_loss = $request->TypeofLoss;
                    $newclaim->date_of_loss = Carbon::parse($request->DateofLoss)->format('Y-m-d');
                    $newclaim->service_representative_id = $request->ServiceRepresentative;
                    $newclaim->catastrophe_loss = $request->CatastropheLoss;
                    $newclaim->event_name = $request->EventName;
                    $newclaim->description_of_loss = $request->description_of_loss;

                    $newclaim->primary_attorney_assigned_id = $request->PrimaryAttorneyAssigned;
                    $newclaim->p_a_assigned_date = Carbon::parse($request->p_a_AssignedDate)->format('Y-m-d');
                    $newclaim->co_attorney_assigned_id = $request->CoAttorneyAssigned;
                    $newclaim->c_a_assigned_date = Carbon::parse($request->c_a_AssignedDate)->format('Y-m-d');
                    $newclaim->dfs_complaint = $request->DFSComplaint;
                    $newclaim->claim_allocated_to = $request->ClaimsAllocatedTo;

                    $newclaim->claims_allocated_on = Carbon::parse($request->claimsAllocatedOn)->format('Y-m-d');
                    $newclaim->date_first_visited = Carbon::parse($request->DateFirstVisited)->format('Y-m-d');
                    $newclaim->driver_as_insured = $request->driver_as_insured;
                    // $newclaim->claim_approved = $request->ClaimApproved;
                    // $newclaim->status = $request->ClaimStatus;
                    $newclaim->claim_sub_status = $request->ClaimsubStatus;
                    $newclaim->reportedByBrokerAgent = $request->reportedByBrokerAgent;
                    $newclaim->created_by  = auth()->user()->id;
                    $newclaim->third_party_insured_elsewhere = $request->third_party_insured_elsewhere;

                    $newclaimdata = NewClaim::where('id',$request->newclaim_id)->first();
                    if ($request->third_party_insured_elsewhere == 1 && $newclaimdata->third_party_insured_elsewhere != 1) {
                        $newclaim->tp_insured_elsewhere_email = $request->tp_insured_elsewhere_email;

                        $data = new \stdClass();
                        $data->customer_id = null;
                        $data->claim_id = $claims->id;
                        $data->policy_id = $claims->policy_id;

                        $data->hook = 'claim_tp_insured_elsewhere';
                        $data->attachment = NULL;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($request->tp_insured_elsewhere_email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));

                    } else {
                        $newclaim->tp_insured_elsewhere_email = $request->tp_insured_elsewhere_email;
                    }
                    $newclaim->save();


                    // /* storing driver details*/
                    // if ($request->driver_as_insured == 0) {
                    //     $accidentDriver = AccidentDriver::where('claim_id', $claims->id)->first();
                    //     $accidentDriver->claim_id = $claims->id;
                    //     $accidentDriver->name = $request->driver_name;
                    //     $accidentDriver->address = $request->driver_address;
                    //     $accidentDriver->dob = $request->driver_dob;
                    //     $accidentDriver->license = $request->driver_license;
                    //     $accidentDriver->cellphone = $request->driver_num;
                    //     $accidentDriver->purpose = $request->driver_purpose;
                    //     $accidentDriver->save();
                    // }

                    if($claims->claim_type == "BUSINESSINTERRUPTION"){
                        $this->BusinessInterruptionUpdate($request);
                    } else if($claims->claim_type == "BUSINESSALLRISKS" || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS') {
                        $this->AllRiskAndElectronicEquipmentUpdate($request);
                    } else if($claims->claim_type == "WORKERSCOMPENSATION" || $claims->claim_type == "STATEDBENEFITS") {
                        $this->WorkersCompensationUpdate($request);
                    } else if($claims->claim_type == "DEFECTIVEWORKMANSHIP") {
                        $this->DefectiveWorkmanshipUpdate($request);
                    } else if($claims->claim_type == "THEFT" || $request->get('type') == 'MONEY') {
                        $this->BurglaryUpdate($request);
                    } else if($claims->claim_type == "FIDELITYGUARANTEE") {
                        $this->FidelityGuaranteeUpdate($request);
                    } elseif ($claims->claim_type == "TRAVELINSURANCE") {
                        $this->TravelInsuranceUpdate($request);
                    } elseif ($claims->claim_type == "GOODSINTRANSIT") {
                        $this->GoodsInTransitUpdate($request);
                    } elseif ($claims->claim_type == "FIRE") {
                        $this->FireUpdate($request);
                    }elseif ($claims->claim_type == "PROPERTYDAMAGE" || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == "HOUSEHOLDERS" || $claims->claim_type == "HOUSEOWNERS" || $claims->claim_type == "HOUSEOWNER-BUILDINGS" || $claims->claim_type == "HOUSEHOLDERS-CONTENTS") {
                        $this->PropertyLossDamageUpdate($request);
                    }elseif ($claims->claim_type == "LIABILITY") {
                        $this->PublicLiabilityUpdate($request);
                    }elseif ($claims->claim_type == "MOBILEELECTRONICDEVICES" || $claims->claim_type == "OFFICECONTENTS") {
                        $this->MobileAndElectronicDeviceUpdate($request);
                    }

                    // DB::commit();

                    return redirect()->back()->with('success', 'Claim Updated Successfully !');
            // return redirect()->route('admin.newclaims.index')->with('success', 'Claim update successfully!');
        // } catch (\Exception $e) {
        //     DB::rollBack();

        //     Log::error('Claim submission failed', [
        //         'error' => $e->getMessage(),
        //         'user_id' => auth()->id(),
        //         'input' => $request->all(),
        //     ]);

        //     return Redirect::back()->withInput()->withErrors(['error' => 'Something went wrong while creating the claim. Please try again.']);
        // }

    }
    public function show($id)
    {
        //
    }

    public function data(Request $request)
    {
        if (Auth::user()->hasPermissionTo('claim-list')) {
            $query = NewClaim::latest();

            // Apply status and type filters
            if ($request->claimStatus_filter != -1 && $request->claimType_filter == -1) {
                $query->where('status', $request->claimStatus_filter);
            }
            if ($request->claimType_filter != -1 && $request->claimStatus_filter == -1) {
                $query->where('claim_type', $request->claimType_filter);
            }
            if ($request->claimStatus_filter != -1 && $request->claimType_filter != -1) {
                $query->where('status', $request->claimStatus_filter);
            }

            // Apply global search across claim number, policy number, customer name, vehicle plate, and property address
            if ($request->has('search') && !empty($request->search['value'])) {
                $searchValue = trim($request->search['value']);
                $normalizedPlate = str_replace(' ', '', $searchValue);

                $query->where(function($q) use ($searchValue, $normalizedPlate) {
                    $q->where('claim_number', 'like', '%' . $searchValue . '%')
                      ->orWhere('policyNumber', 'like', '%' . $searchValue . '%')
                      ->orWhere('vehicle_plate', 'like', '%' . $searchValue . '%')
                      ->orWhere('vehicle_plate', 'like', '%' . $normalizedPlate . '%')
                      ->orWhereHas('policy.vehicle', function($subQ) use ($searchValue, $normalizedPlate) {
                          $subQ->where('vehiclePlate', 'like', '%' . $searchValue . '%')
                               ->orWhere('vehiclePlate', 'like', '%' . $normalizedPlate . '%');
                      })
                      ->orWhereHas('policy.riskAddress', function($subQ) use ($searchValue) {
                          $subQ->where('address_name', 'like', '%' . $searchValue . '%')
                               ->orWhere('physical_address', 'like', '%' . $searchValue . '%');
                      });
                });
            }

            $claims = $query->get();
            return DataTables::of($claims)

                ->addColumn('name', function ($claims) {
                    $name = 'N/A';
                    $policy = Policy::where('policyNumber',$claims->policyNumber)->first();
                    $customer = Customer::where('id', $policy->customer_id)->first(array('firstName', 'lastName', 'cellphone', 'customer_category'));
                    if ($customer) {
                        if ($customer->customer_category == 2) {
                            $name = '<span class="blackdot"></span><a href="' . route('admin.customer.edit', $policy->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                        } elseif ($customer->customer_category == 1) {

                            $name = '<span class="graydot"></span><a href="' . route('admin.customer.edit', $policy->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                        } else {
                            $name = '<span class="greendot"></span><a href="' . route('admin.customer.edit', $policy->customer_id) . '" target="_blank"> ' . ucwords($customer->firstName) . ' ' . ucwords($customer->lastName) . '</a>';
                        }
                    }
                    return $name;
                })
                ->addColumn('claim_handler', function ($claims) {
                    $claim_handler = 'N/A';
                    $detailsUser = User::where('id', $claims->created_by)->first(array('firstName', 'lastName'));
                    if ($detailsUser) {
                        $claim_handler = $detailsUser->firstName . ' ' . $detailsUser->lastName;
                    }
                    return $claim_handler;
                })
                ->addColumn('status', function ($claims) {
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

                    return $return;
                })
                ->addColumn('actions', function ($claims) {
                    $actions = '';
                   // if (Auth::user()->can('claim-edit')) {
                        if($claims->claim_type == "9"){
                             $claimType = "BUSINESSINTERRUPTION";
                        }else if($claims->claim_type == "8"){
                             $claimType = "BUSINESSALLRISKS";
                        }else if($claims->claim_type == "10"){
                                $claimType = "WORKERSCOMPENSATION";
                        }else if($claims->claim_type == "5"){
                            $claimType = "THEFT";
                        }else if($claims->claim_type == "6"){
                                $claimType = "FIDELITYGUARANTEE";
                        }else{
                            $claimType = "BUSINESSINTERRUPTION";
                        }
                        if($claims->claim_number == null){
                            $actions = '';
                            $actions .= '<a href="#" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-archive" value="'.$claims->id.'" title="Archive">
                            <i class="la la-archive"></i>
                        </a>';
                        }else{
                            $actions .= '<a href="' . route('admin.newclaims.claimView', $claims->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                         <i class="flaticon-eye"></i>
                                   </a>';
                         $actions .= '<a href="' . route('admin.newclaims.edit', [$claimType, $claims->claim_number]) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>';
                            $actions .= '<a href="#" class="btn  btn-sm btn-clean btn-icon btn-icon-md confirm-archive" value="'.$claims->id.'" title="Archive">
                            <i class="la la-archive"></i>
                        </a>';
                        }



                    // } else {
                    //     $actions .= '<a href="' . route('admin.claims.claimView', $claims->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                    //             <i class="flaticon-eye"></i>
                    //         </a>';
                    // }
                    return $actions;
                })
                ->addColumn('claim_type', function ($claims) {
                    if ($claims->claim_type == "9") {
                        return "Business Interruption";
                    }else if($claims->claim_type == "8") {
                        return "All Risk and Electronic Equipment";
                    } else if($claims->claim_type == "10"){
                        return "Accidental Damage";
                    } elseif ($claims->claim_type == "5") {
                        return "Burglary";
                    } else if($claims->claim_type == "6"){
                        return "Fidelity Guarantee";
                    } else {
                        return "-";
                    }
                })
                ->rawColumns(['actions', 'claim_type', 'status', 'name', 'claim_handler'])
                ->make(true);
        } else {
            return redirect()->back()->with('error', 'You do npot have access to view this page');
        }
    }
    public function confirmArchive(Request $request){
        $NewClaim = NewClaim::where('id',$request->get('id'))->first();
        $body = 'Are you sure you want to archive Claim # '. $NewClaim->claim_number .' ? You can not revert this process.';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
   }
   public function Archive($id){

       $NewClaim = NewClaim::where('id',$id)->first();
       if($NewClaim->claim_type == 9){
        BusinessInterruption::where('newclaim_id',$NewClaim->id)->orderBy('id','desc')->delete();
       }else if($NewClaim->claim_type == 8){
        AllRiskAndElectronicEquipment::where('newclaim_id',$NewClaim->id)->orderBy('id','desc')->delete();
       }else if($NewClaim->claim_type == 10){
        // DefectiveWorkmanship::where('newclaim_id',$NewClaim->id)->orderBy('id','desc')->delete();
        WorkersCompensation::where('newclaim_id',$NewClaim->id)->orderBy('id','desc')->delete();
       }else if($NewClaim->claim_type == 5){
        Burglary::where('newclaim_id',$NewClaim->id)->orderBy('id','desc')->delete();

       }else if($NewClaim->claim_type == 6){
        FidelityGuarantee::where('newclaim_id',$NewClaim->id)->orderBy('id','desc')->delete();
       }

       $NewClaim->delete();

       return redirect()->route('admin.newclaims.index')->with('success', 'Claim delete successfully!');

   }


}
