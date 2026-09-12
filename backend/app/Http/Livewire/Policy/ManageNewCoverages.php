<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Models\PolicyCoverageEntity;
use AlphaDirect\Models\PolicyCoverageNote;
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\Models\PolicyExcessesData;
use AlphaDirect\Models\PolicyBusiExcessesData;
use AlphaDirect\Models\Extention;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\TbCvgpcLimits;
use AlphaDirect\Models\TbValidOptions;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Vehicle;
use AlphaDirect\Models\Motor;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\MotorTraders;
use AlphaDirect\Models\MotorTradersInternal;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Models\MotorType;
use AlphaDirect\VehicleMake;
use Carbon\Carbon;
use AlphaDirect\Models\TheftGeneralQuestions;
use AlphaDirect\Models\User;
use AlphaDirect\PolicyTerm;
use Illuminate\Support\Facades\Crypt;

class ManageNewCoverages extends Component
{

    const RISKACCORDIONCOLLAPSE = 'show'; //show
    const COVERSGEACCORDIONCOLLAPSE = ''; //show

    public Policy $policy;
    public $termId;
    public $actionId;
    public PolicyCoverage $policyCoverage;
    public $editmode;
    public $dataShowForActionId;
    public $previousActionId;
    public $V_isUpdate = false;
    // public $editable = true;
    public $isPrevious = false;
    public $policyCoverageDetail = [];
    public $policyExtentionDetail = [];
    public $policyCoverageEntity = [];
    public $policyCoverageNote;
    public $policyCoverageBenefits;
    public $policyCoverageMemorandaWarranty;
    public $policyCoverageCashWarranty;
    public $policyCoverageBurglarWarranty;
    public $policyCoverageEndorsements;
    // public $s_LimitTypeCode;
    public $specifiedRow = [];
    public $specified_items;
    public $specifiedItemsWithRate = [];
    public $specifiedItems = [];
    public $selectedVehicleData;
    public $vehicleMake;
    public $vehicleData;
    public $vehicleSelected;
    public $vehicleModels;
    public $vechicleModels;
    public $selectedMake;
    public $coverageSelected;
    public $subCoverageSelected;
    public $coverType;
    public $coverTypeName;
    public $third_party_liability;

    public $motorVehicleData = [];
    public $motor = [];
    public $Comprehensive_section = false;
    public $third_party_only_section = false;
    public $Third_fire_and_theft_section = false;

    public $motorTradersExternal;
    public $motorTradersInternal;

    public $ComprehensiveMotorTradersExternal_section = false;
    public $TPMotorTradersExternal_section = false;
    public $TPFTMotorTradersExternal_section = false;

    public $ComprehensiveMotorTradersInternal_section = false;
    public $TPMotorTradersInternal_section = false;
    public $TPFTMotorTradersInternal_section = false;

    public $CovTypeVehicles;
    public $CovTypeVehicle = [];
    public $coverageIdForVehicle;
    public $vehiclePlateNo;

    # public $selectedOption = '';
    public $selectedDataDropdownValue = '';
    public $selectedOptionData;
    public $pcov_amount_to_be_guaranteed;
    public $pcov_premium;

    public $inputFields = [];
    public $posts = [];
    public $inputExcessesData = [];
    public $inputBusiExcessesData = [];
    public $i = 1;
    public $j = 1;
    public $k = 1;
    public $policyCoverageID;
    public $subpolicyCoverageID;

    public $publicliability_date;
    public $policyCoverage_id;

    public $PolicyCoveragesData = [];
    public $PolicyCoveragesNewData = [];
    public $PolicyCoveragesCoverNewData = [];
    public $PolExData = [];
    public $PolBusiExData = [];
    public $property_business_being;
    public $allRiskAddress = []; // Define the property
    public $selectedRiskAddress;

    public $theft = [
        'physical_protection_implemented' => '',
        'premises_alarmed' => '',
        'subscribe_armed_security' => '',
        'security_company' => '',
        'maintenance_contract' => '',
        'alarmed_installed_date' => '',
        'opening_closing_signals' => ''
    ];

    public function selectedOption($riskaddress, $policyCoverage, $value)
    {
        $cover_area = '';
        if ($value == 'Blanket') {
            $cover_area = 'All employees';
        }
        $data = [
            'cover_type' => $value,
            'cover_area' => $cover_area,
            'policyCoverageID' => $policyCoverage,
            'risk_address' => $riskaddress,
            'policy_id' => $this->policy->id
        ];
        PolicyCoveragesData::insert($data);
        return redirect(request()->header('Referer'));

    }
    public function addFidelityGuarantee($riskaddress, $policyCoverage, $value)
    {

        $data = [
            'cover_type' => $value,
            'policyCoverageID' => $policyCoverage,
            'risk_address' => $riskaddress,
            'policy_id' => $this->policy->id
        ];
        PolicyCoveragesData::insert($data);
        $this->emit('refreshFidelityGuarantee');

    }

    public function addExcessesFidelityGuarantee($k)
    {
        $k = $k + 1;
        $this->k = $k;
        array_push($this->inputExcessesData, $k);

    }
    public function addBusiExcessesFidelityGuarantee($i)
    {
        $i = $i + 1;
        $this->i = $i;
        array_push($this->inputBusiExcessesData, $i);

    }

    private function resetInputFields()
    {
        $this->excesses = '';
        $this->min_percent = '';
        $this->min_amt = '';

    }
    public function removeExcessesFidelityGuarantee($tId)
    {
        unset($this->inputExcessesData[$tId]);
    }

    public function removeBusiExcessesFidelityGuarantee($tId)
    {
        unset($this->inputBusiExcessesData[$tId]);
    }

    public function removeDBExcessesFidelityGuarantee($tId)
    {
        PolicyExcessesData::Where('id', $tId)->delete();
        unset($this->pExcessesData[$tId]);
    }

    public function removeFideltiyGuarantee($tId)
    {
        unset($this->inputFields[$tId]);
    }
    public function removeDBFideltiyGuarantee($tId)
    {
        PolicyCoveragesData::Where('id', $tId)->delete();
        // unset($this->posts[$tId]);
    }

    public function removeDBBusiExcessesFidelityGuarantee($tId)
    {
        unset($this->pBusiExcessesData[$tId]);
        PolicyBusiExcessesData::Where('id', $tId)->delete();
    }

    public function deleteCoverage($policyCoverageId)
    {
        // @todo delete all the data related to te policyCOverage endors_flag
        PolicyCoverage::where('id', $policyCoverageId)->update(['endors_flag' => '2']);

        PolicyCoverage::find($policyCoverageId)->delete();
    }

    public function cancelCoverage($policyCoverageId)
    {
        // @todo delete all the data related to te policyCOverage endors_flag
        PolicyCoverage::where('id', $policyCoverageId)->update(['status' => '1']);
    }

    public function reinstateCoverage($policyCoverageId)
    {
        // @todo delete all the data related to te policyCOverage endors_flag
        PolicyCoverage::where('id', $policyCoverageId)->update(['status' => '0']);
    }
     public function reinstateMisc($misc_id)
    {
        // update deleted at coloumn to reinstate
        // change code by snehal from restore to update on 25-10-25
        // PolicySpecifiedItem::where('id', $misc_id)->restore();
        PolicySpecifiedItem::withTrashed()
            ->where('id', $misc_id)
            ->update([
                'deleted_at' => null,
                'action_id' => $this->actionId,
                'endors_flag' => "1"
            ]);
    }
    // specified iteams code\ 
    // specified iteams code
    public function handleVehicleMakeChange($make, $coverageId, $sub_coverageId)
    {
        $data = [];
        if ($this->vehicleData->is_imported == 0) {
            $response = \Http::withToken(base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B' . ':' . '10415'))->get("http://api.realintel.co.za/json/trutrade/GetModels", [
                'make' => $make,
                // 'year' => $this->vehicleData->year
            ]);

            if ($response->successful()) {
                $collection = collect();
                foreach ($response['Variants'] as $v) {
                    $collection->push([
                        'id' => $v['Model'],
                        'name' => $v['Model']
                    ]);
                }
                $data = $collection->keyBy('id')->all();
            }
        } elseif ($this->vehicleData->is_imported == 1) {
            $data = \AlphaDirect\VehicleMake::where('s_Make', $make)
                ->groupBy('s_Variant')
                ->get()->keyBy('s_Variant')->map(function ($d) {
                    return [
                        'id' => $d->s_Variant,
                        'name' => $d->s_Variant
                    ];
                });
        }
        $this->vehicleModels[$coverageId] = $data;
        // dd($this->vehicleModels);
        // $this->dispatchBrowserEvent('dropdown-changed',[
        //     'key'=>'vehicleDataMake'.$this->coverageSelected,
        //     'data'=>$data
        // ]);
    }

    public function handleVehicleChange($vehicleId, $policyCoverageId, $sub_coverageId)
    {
        // dd($vehicleId,$policyCoverageId,$sub_coverageId);
        $this->vehicleSelected[$policyCoverageId] = $vehicleId;
        $this->coverageSelected = $policyCoverageId;
        $this->subCoverageSelected = $sub_coverageId;
        // $selectedVehicleId = $this->policyCoverageEntity[$policyCoverageId]['Vehicle'];
        // $this->getVehicleData($this->vehicleSelected[$policyCoverageId],$this->coverageSelected);
        $this->getVehicleData($this->vehicleSelected[$policyCoverageId], $this->coverageSelected, $this->subCoverageSelected);
        $this->getVechicleModel($this->selectedVehicleData);
        // $this->emit('fetchedDataUpdated', $this->selectedVehicleData);
    }

    public function fetchedDataUpdated($data)
    {
        $this->selectedVehicleData = $data;
        $this->getVechicleModel($data);
    }

    public function getVehicleData($vehicleId, $policyCoverageId, $sub_coverageId)
    {
        //   dd($policyCoverageId);
        foreach ($this->policyCoverages as $index => $policyCoverage) {
            if (isset($policyCoverage->coverage['subCoverage'])) {
                foreach ($policyCoverage->coverage['subCoverage'] as $subCoverage) {
                    $this->selectedVehicleData[$policyCoverageId][$subCoverage->id] = Vehicle::where('id', $vehicleId)->first(['id', 'make', 'model', 'year', 'chassisNo', 'vehiclePlate', 'engineNo', 'is_imported']);
                }
            }
        }
        // $this->selectedVehicleData[$policyCoverageId][$sub_coverageId] = Vehicle::where('id',$vehicleId)->first(['id','make','model','year','chassisNo','vehiclePlate','engineNo','is_imported']);
    }

    public function getVechicleMake()
    {
        $response = \Http::withToken(base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B' . ':' . '10415'))->get('http://api.realintel.co.za/json/trutrade/GetMakes');
        if ($response->successful()) {
            $collection = collect();
            foreach ($response['Makes'] as $v) {
                $collection->push([
                    'id' => $v['Make'],
                    'name' => $v['Make']
                ]);
            }
            return $collection->keyBy('id')->all();
        } else {
            return [];
        }
    }

    public function SumInsuredOfVehicles($value, $vehicle, $motor_policy_coverage_id)
    {
        $this->motorVehicleData['coverage_value'] = $value;
    }
    public function PremiumOfVehicles($value, $vehicle, $motor_policy_coverage_id)
    {
        $this->motorVehicleData['calculated_value'] = $value;
    }
    public function SummaryOfVehicles($value, $vehicle, $motor_policy_coverage_id)
    {
        $CovTypeVehicles = $vehicle;
        if ($value === "Comprehensive" || $value === "third_party_only" || $value === "Third_fire_and_theft") {
            $this->motorVehicleData['vehicle_name'] = $CovTypeVehicles['make'] . ' ' . $CovTypeVehicles['model'];
            $this->motorVehicleData['policy_coverage_id'] = $motor_policy_coverage_id;
            $this->motor['registration_no'] = $CovTypeVehicles['vehiclePlate'] ?? "";
            $this->motor['make'] = $CovTypeVehicles['make'] ?? "";
            $this->motor['model'] = $CovTypeVehicles['model'] ?? "";
            $this->motor['engine_number'] = $CovTypeVehicles['engineNo'] ?? "";
            $this->motor['chassis_number'] = $CovTypeVehicles['chassisNo'] ?? "";
            $this->motor['estimated_value'] = $CovTypeVehicles['estimated_value'] ?? "";

            //Already Exist
            $motor_specified_item = [];
            $this->specified_items = [];
            $this->specifiedRow = [];
            $this->specifiedRow[$motor_policy_coverage_id][] = [];
            //$this->policyCoverageNote[$motor_policy_coverage_id]=[];

            $personalMotorData = Motor::where('policy_coverage_id', $motor_policy_coverage_id)->where('registration_no', $CovTypeVehicles['vehiclePlate'])->orderBy('id', 'asc')->first();
            if (isset($personalMotorData)) {
                $motor_specified_item = DB::table('policy_specified_items')->where('policy_coverage_id', $motor_policy_coverage_id)->where('motor_id', $personalMotorData->id)->orderBy('id', 'asc')->get();
            }
            if ($personalMotorData) {
                // Vehicle
                // $this->motor['use'] = $personalMotorData->use  ?? "";
                $this->motor['use'] = $personalMotorData->use_main ?? "";
                $this->motorVehicleData['coverage_value'] = $personalMotorData->coverage_value ?? "";
                $this->motorVehicleData['calculated_value'] = $personalMotorData->calculated_value ?? "";
                $this->motor['tracking_device'] = $personalMotorData->tracking_device ?? "";
                $this->motor['security_features'] = $personalMotorData->security_features ?? "";
                $this->motorVehicleData['motor_id'] = $personalMotorData->id ?? "";
                $this->motorVehicleData['motor_policy_cov_id'] = $personalMotorData->policy_coverage_id ?? "";

                $this->specifiedRow = [];
                $key = isset($personalMotorData->id)
                    ? $personalMotorData->id . '_' . $motor_policy_coverage_id
                    : $motor_policy_coverage_id;
                $this->policyCoverageNote[$key] = [];
                if (isset($motor_specified_item) && count($motor_specified_item) > 0) {
                    foreach ($motor_specified_item as $k => $motor_specified_items) {
                        $this->specified_items[$motor_policy_coverage_id][$k] = [
                            'selected_item' => $motor_specified_items->specified_coverage_id ?? "",
                            'sum_insured' => $motor_specified_items->sum_insured ?? "",
                            'rate' => $this->specifiedItemsWithRate[$motor_specified_items->id] ?? 0,
                            'id' => $motor_specified_items->id ?? 0,
                            'motor_id' => $personalMotorData->id ?? 0

                        ];
                        if (!isset($this->specifiedRow[$motor_policy_coverage_id][$k])) {
                            $this->specifiedRow[$motor_policy_coverage_id][$k] = [];
                        }
                    }
                }
                $notesMotor = DB::table('policy_coverage_notes')
                    ->where('policy_coverage_id', $motor_policy_coverage_id)
                    ->where('motor_id', $personalMotorData->id)
                    ->orderBy('id', 'asc')->first();
                if ($notesMotor) {
                    $this->policyCoverageNote[$key] = $notesMotor->note ?? '';
                    //dd($this->policyCoverageNote[$key]);
                }
                if ($value === "Comprehensive" || $value === "Third_fire_and_theft") {
                    // Extension
                    $this->motor['wreckage_removal'] = $personalMotorData->wreckage_removal ?? 0;
                    $this->motor['window_glass'] = $personalMotorData->window_glass ?? 0;
                    $this->motor['locks_keys'] = $personalMotorData->locks_keys ?? 0;
                    $this->motor['parts_accessories'] = $personalMotorData->parts_accessories ?? 0;
                    $this->motor['audio_accessories'] = $personalMotorData->audio_accessories ?? 0;
                    $this->motor['riot_strike'] = $personalMotorData->riot_strike ?? 0;
                    $this->motor['car_hire_theft'] = $personalMotorData->car_hire_theft ?? 0;
                    $this->motor['credit_shortfall'] = $personalMotorData->credit_shortfall ?? 0;
                    $this->motor['insured_driver'] = $personalMotorData->insured_driver ?? 0;
                    $this->motor['insured_family'] = $personalMotorData->insured_family ?? 0;
                    $this->motor['medical_expenses'] = $personalMotorData->medical_expenses ?? 0;
                    $this->motor['passenger_liability'] = $personalMotorData->passenger_liability ?? 0;
                    $this->motor['third_party_liability'] = $personalMotorData->third_party_liability ?? 0;
                    $this->motor['specified_accessories'] = $personalMotorData->specified_accessories ?? 0;

                    // Premium extention
                    $this->motor['premium_wreckage_removal'] = $personalMotorData->premium_wreckage_removal ?? 0;
                    $this->motor['premium_window_glass'] = $personalMotorData->premium_window_glass ?? 0;
                    $this->motor['premium_locks_keys'] = $personalMotorData->premium_locks_keys ?? 0;
                    $this->motor['premium_parts_accessories'] = $personalMotorData->premium_parts_accessories ?? 0;
                    $this->motor['premium_audio_accessories'] = $personalMotorData->premium_audio_accessories ?? 0;
                    $this->motor['premium_riot_strike'] = $personalMotorData->premium_riot_strike ?? 0;
                    $this->motor['premium_car_hire_theft'] = $personalMotorData->premium_car_hire_theft ?? 0;
                    $this->motor['premium_credit_shortfall'] = $personalMotorData->premium_credit_shortfall ?? 0;
                    $this->motor['premium_insured_driver'] = $personalMotorData->premium_insured_driver ?? 0;
                    $this->motor['premium_insured_family'] = $personalMotorData->premium_insured_family ?? 0;
                    $this->motor['premium_medical_expenses'] = $personalMotorData->premium_medical_expenses ?? 0;
                    $this->motor['premium_passenger_liability'] = $personalMotorData->premium_passenger_liability ?? 0;
                    $this->motor['premium_third_party_liability'] = $personalMotorData->premium_third_party_liability ?? 0;
                    $this->motor['premium_specified_accessories'] = $personalMotorData->specified_accessories ?? 0;

                    // Excess
                    $this->motor['own_damage'] = $personalMotorData->own_damage ?? "";
                    $this->motor['own_damage_minimun_percent'] = $personalMotorData->own_damage_minimun_percent ?? 0;
                    $this->motor['own_damage_minimum_amount'] = $personalMotorData->own_damage_minimum_amount ?? 0;
                    $this->motor['windscreen'] = $personalMotorData->windscreen ?? "";
                    $this->motor['windscreen_minimun_percent'] = $personalMotorData->windscreen_minimun_percent ?? 0;
                    $this->motor['windscreen_minimum_amount'] = $personalMotorData->windscreen_minimum_amount ?? 0;
                    $this->motor['loss_of_keys'] = $personalMotorData->loss_of_keys ?? "";
                    $this->motor['loss_of_keys_minimun_percent'] = $personalMotorData->loss_of_keys_minimun_percent ?? 0;
                    $this->motor['loss_of_keys_minimum_amount'] = $personalMotorData->loss_of_keys_minimum_amount ?? 0;
                }
            }
        }

        if ($value === "Comprehensive") {

            $this->Comprehensive_section = true;
            $this->third_party_only_section = false;
            $this->Third_fire_and_theft_section = false;
            return $this->CovTypeVehicle = $CovTypeVehicles;
        } elseif ($value === "third_party_only") {
            $this->Comprehensive_section = false;
            $this->third_party_only_section = true;
            $this->Third_fire_and_theft_section = false;
            return $this->CovTypeVehicle = $CovTypeVehicles;
        } elseif ($value === "Third_fire_and_theft") {
            $this->Comprehensive_section = false;
            $this->third_party_only_section = false;
            $this->Third_fire_and_theft_section = true;
            return $this->CovTypeVehicle = $CovTypeVehicles;
        } else {
            $this->Comprehensive_section = false;
            $this->third_party_only_section = false;
            $this->Third_fire_and_theft_section = false;
            return $this->CovTypeVehicle = [];
        }
    }
    public function DeleteOfVehicles($value, $motor_id, $motor_policy_coverage_id)
    {

        //Motor::where('id',$motor_id)->delete();
        $updated = Motor::where('id', $motor_id)->update([
            'deleted_at' => now(),
            // added by snehal on 26-8-25
            'endors_flag' => 1,
            'previousActionIdCov' => $this->actionId
        ]);
        if ($updated) {
            $query = PolicySpecifiedItem::where('policy_coverage_id', $motor_policy_coverage_id)
                ->where('motor_id', $motor_id)->delete();

        }
        activity('Vehicle Deleted ' . $this->vehicleData->vehiclePlate)
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Status : ' . $this->vehicleData->vehiclePlate . ' Deleted');
    }
    public function ReinstateOfVehicles($value, $motor_id, $motor_policy_coverage_id)
    {
        //Motor::where('id', $motor_id)->restore();
        $updated = Motor::where('id', $motor_id)->update([
            'deleted_at' => NULL,
            // added by snehal on 26-8-25
            'endors_flag' => 0,
            'previousActionIdCov' => 0
        ]);

        if ($updated) {
            $query = PolicySpecifiedItem::withTrashed()
                ->where('policy_coverage_id', $motor_policy_coverage_id)
                ->where('motor_id', $motor_id)
                ->restore();
        }
        activity('Vehicle Reinstate ' . $this->vehicleData->vehiclePlate)
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Status : ' . $this->vehicleData->vehiclePlate . ' Restore');
    }

    public function MotorTradersExternal($value, $motor_policy_coverage_id)
    {
        if ($value === "ComprehensiveMotorTradersExternal" || $value === "TPMotorTradersExternal" || $value === "TPFTMotorTradersExternal") {
            $this->motorTradersExternal['policy_coverage_id'] = $motor_policy_coverage_id;
            //Already Exist
            $motorTradersExternalData = MotorTraders::where('policy_coverage_id', $motor_policy_coverage_id)->orderBy('id', 'asc')->first();
            if ($motorTradersExternalData) {
                if ($value === "ComprehensiveMotorTradersExternal" || $value === "TPFTMotorTradersExternal") {
                    $this->motorTradersExternal['type_of_cover'] = $motorTradersExternalData->type_of_cover ?? 0;
                    $this->motorTradersExternal['loss_or_damage_coverage_value'] = $motorTradersExternalData->loss_or_damage_coverage_value ?? 0;
                    $this->motorTradersExternal['loss_or_damage_calculated_value'] = $motorTradersExternalData->loss_or_damage_calculated_value ?? 0;
                    $this->motorTradersExternal['third_party_liability_coverage_value'] = $motorTradersExternalData->third_party_liability_coverage_value ?? 0;
                    $this->motorTradersExternal['third_party_liability_calculated_value'] = $motorTradersExternalData->third_party_liability_calculated_value ?? 0;
                    $this->motorTradersExternal['medical_benefits_coverage_value'] = $motorTradersExternalData->medical_benefits_coverage_value ?? 0;
                    $this->motorTradersExternal['medical_benefits_calculated_value'] = $motorTradersExternalData->medical_benefits_calculated_value ?? 0;
                    $this->motorTradersExternal['vehicle_lent_hire_coverage_value'] = $motorTradersExternalData->vehicle_lent_hire_coverage_value ?? 0;
                    $this->motorTradersExternal['vehicle_lent_hire_calculated_value'] = $motorTradersExternalData->vehicle_lent_hire_calculated_value ?? 0;
                    $this->motorTradersExternal['social_domestic_pleasure_coverage_value'] = $motorTradersExternalData->social_domestic_pleasure_coverage_value ?? 0;
                    $this->motorTradersExternal['social_domestic_pleasure_calculated_value'] = $motorTradersExternalData->social_domestic_pleasure_calculated_value ?? 0;
                    $this->motorTradersExternal['unauthoried_use_coverage_value'] = $motorTradersExternalData->unauthoried_use_coverage_value ?? 0;
                    $this->motorTradersExternal['unauthoried_use_calculated_value'] = $motorTradersExternalData->unauthoried_use_calculated_value ?? 0;
                    $this->motorTradersExternal['windscreen_coverage_value'] = $motorTradersExternalData->windscreen_coverage_value ?? 0;
                    $this->motorTradersExternal['windscreen_calculated_value'] = $motorTradersExternalData->windscreen_calculated_value ?? 0;
                    $this->motorTradersExternal['contigent_liability_coverage_value'] = $motorTradersExternalData->contigent_liability_coverage_value ?? 0;
                    $this->motorTradersExternal['contigent_liability_calculated_value'] = $motorTradersExternalData->contigent_liability_calculated_value ?? 0;
                    $this->motorTradersExternal['wreckage_removal_coverage_value'] = $motorTradersExternalData->wreckage_removal_coverage_value ?? 0;
                    $this->motorTradersExternal['wreckage_removal_calculated_value'] = $motorTradersExternalData->wreckage_removal_calculated_value ?? 0;
                    $this->motorTradersExternal['loss_of_key_coverage_value'] = $motorTradersExternalData->loss_of_key_coverage_value ?? 0;
                    $this->motorTradersExternal['loss_of_key_calculated_value'] = $motorTradersExternalData->loss_of_key_calculated_value ?? 0;
                    $this->motorTradersExternal['Loss_of_use_of_customer_coverage_value'] = $motorTradersExternalData->Loss_of_use_of_customer_coverage_value ?? 0;
                    $this->motorTradersExternal['Loss_of_use_of_customer_calculated_value'] = $motorTradersExternalData->Loss_of_use_of_customer_calculated_value ?? 0;
                    $this->motorTradersExternal['motor_cycle_motor_tricycle_coverage_value'] = $motorTradersExternalData->motor_cycle_motor_tricycle_coverage_value ?? 0;
                    $this->motorTradersExternal['motor_cycle_motor_tricycle_calculated_value'] = $motorTradersExternalData->motor_cycle_motor_tricycle_calculated_value ?? 0;
                    $this->motorTradersExternal['passanger_liability_respect_of_motor_coverage_value'] = $motorTradersExternalData->passanger_liability_respect_of_motor_coverage_value ?? 0;
                    $this->motorTradersExternal['passanger_liability_respect_of_motor_calculated_value'] = $motorTradersExternalData->passanger_liability_respect_of_motor_calculated_value ?? 0;
                    $this->motorTradersExternal['special_type_vehicle_coverage_value'] = $motorTradersExternalData->special_type_vehicle_coverage_value ?? 0;
                    $this->motorTradersExternal['special_type_vehicle_calculated_value'] = $motorTradersExternalData->special_type_vehicle_calculated_value ?? "";
                    $this->motorTradersExternal['own_damage_minimun_percent'] = $motorTradersExternalData->own_damage_minimun_percent ?? 0;
                    $this->motorTradersExternal['own_damage_minimum_amount'] = $motorTradersExternalData->own_damage_minimum_amount ?? 0;
                    $this->motorTradersExternal['windscreen_minimun_percent'] = $motorTradersExternalData->windscreen_minimun_percent ?? "";
                    $this->motorTradersExternal['windscreen_minimum_amount'] = $motorTradersExternalData->windscreen_minimum_amount ?? 0;
                }
                if ($value === "TPMotorTradersExternal") {
                    $this->motorTradersExternal['third_party_liability_coverage_value'] = $motorTradersExternalData->third_party_liability_coverage_value ?? 0;
                    $this->motorTradersExternal['third_party_liability_calculated_value'] = $motorTradersExternalData->third_party_liability_calculated_value ?? 0;
                }
            }
        }

        if ($value === "ComprehensiveMotorTradersExternal") {
            $this->ComprehensiveMotorTradersExternal_section = true;
            $this->TPMotorTradersExternal_section = false;
            $this->TPFTMotorTradersExternal_section = false;
        } elseif ($value === "TPMotorTradersExternal") {
            $this->ComprehensiveMotorTradersExternal_section = false;
            $this->TPMotorTradersExternal_section = true;
            $this->TPFTMotorTradersExternal_section = false;
        } elseif ($value === "TPFTMotorTradersExternal") {
            $this->ComprehensiveMotorTradersExternal_section = false;
            $this->TPMotorTradersExternal_section = false;
            $this->TPFTMotorTradersExternal_section = true;
        } else {
            $this->ComprehensiveMotorTradersExternal_section = false;
            $this->TPMotorTradersExternal_section = false;
            $this->TPFTMotorTradersExternal_section = false;
        }
    }

    public function MotorTradersInternal($value, $motor_policy_coverage_id)
    {

        if ($value === "ComprehensiveMotorTradersInternal" || $value === "TPMotorTradersInternal" || $value === "TPFTMotorTradersInternal") {
            $this->motorTradersInternal['policy_coverage_id'] = $motor_policy_coverage_id;
            //Already Exist
            $motorTradersInternalData = MotorTradersInternal::where('policy_coverage_id', $motor_policy_coverage_id)->orderBy('id', 'asc')->first();
            if ($motorTradersInternalData) {
                if ($value === "ComprehensiveMotorTradersInternal" || $value === "TPFTMotorTradersInternal") {
                    // Extension
                    $this->motorTradersInternal['type_of_cover'] = $motorTradersInternalData->type_of_cover ?? 0;
                    $this->motorTradersInternal['loss_or_damage_coverage_value'] = $motorTradersInternalData->loss_or_damage_coverage_value ?? 0;
                    $this->motorTradersInternal['loss_or_damage_calculated_value'] = $motorTradersInternalData->loss_or_damage_calculated_value ?? 0;
                    $this->motorTradersInternal['third_party_liability_coverage_value'] = $motorTradersInternalData->third_party_liability_coverage_value ?? 0;
                    $this->motorTradersInternal['third_party_liability_calculated_value'] = $motorTradersInternalData->third_party_liability_calculated_value ?? 0;
                    $this->motorTradersInternal['medical_benefits_coverage_value'] = $motorTradersInternalData->medical_benefits_coverage_value ?? 0;
                    $this->motorTradersInternal['medical_benefits_calculated_value'] = $motorTradersInternalData->medical_benefits_calculated_value ?? 0;
                    $this->motorTradersInternal['vehicle_lent_hire_coverage_value'] = $motorTradersInternalData->vehicle_lent_hire_coverage_value ?? 0;
                    $this->motorTradersInternal['vehicle_lent_hire_calculated_value'] = $motorTradersInternalData->vehicle_lent_hire_calculated_value ?? 0;
                    $this->motorTradersInternal['social_domestic_pleasure_coverage_value'] = $motorTradersInternalData->social_domestic_pleasure_coverage_value ?? 0;
                    $this->motorTradersInternal['social_domestic_pleasure_calculated_value'] = $motorTradersInternalData->social_domestic_pleasure_calculated_value ?? 0;
                    $this->motorTradersInternal['unauthoried_use_coverage_value'] = $motorTradersInternalData->unauthoried_use_coverage_value ?? 0;
                    $this->motorTradersInternal['unauthoried_use_calculated_value'] = $motorTradersInternalData->unauthoried_use_calculated_value ?? 0;
                    $this->motorTradersInternal['windscreen_coverage_value'] = $motorTradersInternalData->windscreen_coverage_value ?? 0;
                    $this->motorTradersInternal['windscreen_calculated_value'] = $motorTradersInternalData->windscreen_calculated_value ?? 0;
                    $this->motorTradersInternal['contigent_liability_coverage_value'] = $motorTradersInternalData->contigent_liability_coverage_value ?? 0;
                    $this->motorTradersInternal['contigent_liability_calculated_value'] = $motorTradersInternalData->contigent_liability_calculated_value ?? 0;
                    $this->motorTradersInternal['wreckage_removal_coverage_value'] = $motorTradersInternalData->wreckage_removal_coverage_value ?? 0;
                    $this->motorTradersInternal['wreckage_removal_calculated_value'] = $motorTradersInternalData->wreckage_removal_calculated_value ?? 0;
                    $this->motorTradersInternal['loss_of_key_coverage_value'] = $motorTradersInternalData->loss_of_key_coverage_value ?? 0;
                    $this->motorTradersInternal['loss_of_key_calculated_value'] = $motorTradersInternalData->loss_of_key_calculated_value ?? 0;
                    $this->motorTradersInternal['Loss_of_use_of_customer_coverage_value'] = $motorTradersInternalData->Loss_of_use_of_customer_coverage_value ?? 0;
                    $this->motorTradersInternal['Loss_of_use_of_customer_calculated_value'] = $motorTradersInternalData->Loss_of_use_of_customer_calculated_value ?? 0;
                    $this->motorTradersInternal['motor_cycle_motor_tricycle_coverage_value'] = $motorTradersInternalData->motor_cycle_motor_tricycle_coverage_value ?? 0;
                    $this->motorTradersInternal['motor_cycle_motor_tricycle_calculated_value'] = $motorTradersInternalData->motor_cycle_motor_tricycle_calculated_value ?? 0;
                    $this->motorTradersInternal['passanger_liability_respect_of_motor_coverage_value'] = $motorTradersInternalData->passanger_liability_respect_of_motor_coverage_value ?? 0;
                    $this->motorTradersInternal['passanger_liability_respect_of_motor_calculated_value'] = $motorTradersInternalData->passanger_liability_respect_of_motor_calculated_value ?? 0;
                    $this->motorTradersInternal['special_type_vehicle_coverage_value'] = $motorTradersInternalData->special_type_vehicle_coverage_value ?? 0;
                    $this->motorTradersInternal['special_type_vehicle_calculated_value'] = $motorTradersInternalData->special_type_vehicle_calculated_value ?? "";
                    $this->motorTradersInternal['own_damage_minimun_percent'] = $motorTradersInternalData->own_damage_minimun_percent ?? 0;
                    $this->motorTradersInternal['own_damage_minimum_amount'] = $motorTradersInternalData->own_damage_minimum_amount ?? 0;
                    $this->motorTradersInternal['windscreen_minimun_percent'] = $motorTradersInternalData->windscreen_minimun_percent ?? "";
                    $this->motorTradersInternal['windscreen_minimum_amount'] = $motorTradersInternalData->windscreen_minimum_amount ?? 0;
                }
                if ($value === "TPMotorTradersInternal") {
                    $this->motorTradersInternal['third_party_liability_coverage_value'] = $motorTradersInternalData->third_party_liability_coverage_value ?? 0;
                    $this->motorTradersInternal['third_party_liability_calculated_value'] = $motorTradersInternalData->third_party_liability_calculated_value ?? 0;
                }
            }
        }
        if ($value === "ComprehensiveMotorTradersInternal") {
            $this->ComprehensiveMotorTradersInternal_section = true;
            $this->TPMotorTradersInternal_section = false;
            $this->TPFTMotorTradersInternal_section = false;
        } elseif ($value === "TPMotorTradersInternal") {
            $this->ComprehensiveMotorTradersInternal_section = false;
            $this->TPMotorTradersInternal_section = true;
            $this->TPFTMotorTradersInternal_section = false;
        } elseif ($value === "TPFTMotorTradersInternal") {
            $this->ComprehensiveMotorTradersInternal_section = false;
            $this->TPMotorTradersInternal_section = false;
            $this->TPFTMotorTradersInternal_section = true;
        } else {
            $this->ComprehensiveMotorTradersInternal_section = false;
            $this->TPMotorTradersInternal_section = false;
            $this->TPFTMotorTradersInternal_section = false;
        }
    }

    public function getVechicleModel($vehicles)
    {
        $data = [];
        $vehicle = $vehicles[$this->coverageSelected][$this->subCoverageSelected];
        if ($vehicle['is_imported'] == 0) {
            $response = \Http::withToken(base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B' . ':' . '10415'))->get("http:///json/trutrade/GetModels", [
                'make' => $vehicle['make'],
                // 'year' => $this->vehicleData->year
            ]);

            if ($response->successful()) {
                $collection = collect();
                foreach ($response['Variants'] as $v) {
                    $collection->push([
                        'id' => $v['Model'],
                        'name' => $v['Model']
                    ]);
                }
                $data = $collection->keyBy('id')->all();
            }
        } elseif ($vehicle['is_imported'] == 1) {
            $data = \AlphaDirect\VehicleMake::where('s_Make', $vehicle['make'])
                ->groupBy('s_Variant')
                ->get()->keyBy('s_Variant')->map(function ($d) {
                    return [
                        'id' => $d->s_Variant,
                        'name' => $d->s_Variant
                    ];
                });
        }
        $this->vehicleModels[$this->coverageSelected] = $data;
        // $this->dispatchBrowserEvent('dropdown-changed',[
        //     'key'=>'vehicleDataMake',
        //     'data'=>$data
        // ]);
    }

    protected $rules = [
        'motorTradersExternal.policy_coverage_id' => '',
        'motorTradersExternal.loss_or_damage_coverage_value' => '',
        'motorTradersExternal.loss_or_damage_calculated_value' => '',
        'motorTradersExternal.third_party_liability_coverage_value' => '',
        'motorTradersExternal.third_party_liability_calculated_value' => '',
        'motorTradersExternal.medical_benefits_coverage_value' => '',
        'motorTradersExternal.medical_benefits_calculated_value' => '',
        'motorTradersExternal.vehicle_lent_hire_coverage_value' => '',
        'motorTradersExternal.vehicle_lent_hire_calculated_value' => '',
        'motorTradersExternal.social_domestic_pleasure_coverage_value' => '',
        'motorTradersExternal.social_domestic_pleasure_calculated_value' => '',
        'motorTradersExternal.unauthoried_use_coverage_value' => '',
        'motorTradersExternal.unauthoried_use_calculated_value' => '',
        'motorTradersExternal.windscreen_coverage_value' => '',
        'motorTradersExternal.windscreen_calculated_value' => '',
        'motorTradersExternal.contigent_liability_coverage_value' => '',
        'motorTradersExternal.contigent_liability_calculated_value' => '',
        'motorTradersExternal.wreckage_removal_coverage_value' => '',
        'motorTradersExternal.wreckage_removal_calculated_value' => '',
        'motorTradersExternal.loss_of_key_coverage_value' => '',
        'motorTradersExternal.loss_of_key_calculated_value' => '',
        'motorTradersExternal.Loss_of_use_of_customer_coverage_value' => '',
        'motorTradersExternal.Loss_of_use_of_customer_calculated_value' => '',
        'motorTradersExternal.motor_cycle_motor_tricycle_coverage_value' => '',
        'motorTradersExternal.motor_cycle_motor_tricycle_calculated_value' => '',
        'motorTradersExternal.passanger_liability_respect_of_motor_coverage_value' => '',
        'motorTradersExternal.passanger_liability_respect_of_motor_calculated_value' => '',
        'motorTradersExternal.special_type_vehicle_coverage_value' => '',
        'motorTradersExternal.special_type_vehicle_calculated_value' => '',
        'motorTradersExternal.own_damage_minimun_percent' => '',
        'motorTradersExternal.own_damage_minimum_amount' => '',
        'motorTradersExternal.windscreen_minimun_percent' => '',
        'motorTradersExternal.windscreen_minimum_amount' => '',
        'motorTradersExternal.type_of_cover_external_main_.*' => '',

        'motorTradersInternal.policy_coverage_id' => '',
        'motorTradersInternal.loss_or_damage_coverage_value' => '',
        'motorTradersInternal.loss_or_damage_calculated_value' => '',
        'motorTradersInternal.third_party_liability_coverage_value' => '',
        'motorTradersInternal.third_party_liability_calculated_value' => '',
        'motorTradersInternal.medical_benefits_coverage_value' => '',
        'motorTradersInternal.medical_benefits_calculated_value' => '',
        'motorTradersInternal.vehicle_lent_hire_coverage_value' => '',
        'motorTradersInternal.vehicle_lent_hire_calculated_value' => '',
        'motorTradersInternal.social_domestic_pleasure_coverage_value' => '',
        'motorTradersInternal.social_domestic_pleasure_calculated_value' => '',
        'motorTradersInternal.unauthoried_use_coverage_value' => '',
        'motorTradersInternal.unauthoried_use_calculated_value' => '',
        'motorTradersInternal.windscreen_coverage_value' => '',
        'motorTradersInternal.windscreen_calculated_value' => '',
        'motorTradersInternal.contigent_liability_coverage_value' => '',
        'motorTradersInternal.contigent_liability_calculated_value' => '',
        'motorTradersInternal.wreckage_removal_coverage_value' => '',
        'motorTradersInternal.wreckage_removal_calculated_value' => '',
        'motorTradersInternal.loss_of_key_coverage_value' => '',
        'motorTradersInternal.loss_of_key_calculated_value' => '',
        'motorTradersInternal.Loss_of_use_of_customer_coverage_value' => '',
        'motorTradersInternal.Loss_of_use_of_customer_calculated_value' => '',
        'motorTradersInternal.motor_cycle_motor_tricycle_coverage_value' => '',
        'motorTradersInternal.motor_cycle_motor_tricycle_calculated_value' => '',
        'motorTradersInternal.passanger_liability_respect_of_motor_coverage_value' => '',
        'motorTradersInternal.passanger_liability_respect_of_motor_calculated_value' => '',
        'motorTradersInternal.special_type_vehicle_coverage_value' => '',
        'motorTradersInternal.special_type_vehicle_calculated_value' => '',
        'motorTradersInternal.own_damage_minimun_percent' => '',
        'motorTradersInternal.own_damage_minimum_amount' => '',
        'motorTradersInternal.windscreen_minimun_percent' => '',
        'motorTradersInternal.windscreen_minimum_amount' => '',
        'motorTradersInternal.type_of_cover_internal_main_.*' => '',


        'motorVehicleData.use_main_.*' => '',
        'motorVehicleData.type_of_cover_main_.*' => '',
        'motorVehicleData.coverage_value_main_.*' => '',
        'motorVehicleData.calculated_value_main_.*' => '',
        'motorVehicleData.vehicle_name' => '',
        'motorVehicleData.policy_coverage_id' => '',
        'motorVehicleData.calculated_value' => '',
        'motorVehicleData.coverage_value' => '',

        // Commercial
        'motor.contigent_liability' => '',
        'motor.premium_contigent_liability' => '',

        'motor.unorthorised_passanger_liability' => '',
        'motor.premium_unorthorised_passanger_liability' => '',
        'motor.parking_facilities' => '',
        'motor.premium_parking_facilities' => '',
        'motor.com_windscreen' => '',
        'motor.premium_com_windscreen' => '',


        'motor.estimated_value' => '',
        'motor.use' => '',
        'motor.registration_no' => '',
        'motor.make' => '',
        'motor.model' => '',
        'motor.engine_number' => '',
        'motor.chassis_number' => '',
        'motor.tracking_device' => '',
        'motor.security_features' => '',
        'motor.type_of_cover' => '',

        'motor.wreckage_removal' => '',
        'motor.premium_wreckage_removal' => '',
        'motor.window_glass' => '',
        'motor.premium_window_glass' => '',
        'motor.locks_keys' => '',
        'motor.premium_locks_keys' => '',
        'motor.parts_accessories' => '',
        'motor.premium_parts_accessories' => '',
        'motor.riot_strike' => '',
        'motor.premium_riot_strike' => '',
        'motor.car_hire_theft' => '',
        'motor.premium_car_hire_theft' => '',
        'motor.credit_shortfall' => '',
        'motor.premium_credit_shortfall' => '',
        'motor.insured_driver' => '',
        'motor.premium_insured_driver' => '',
        'motor.insured_family' => '',
        'motor.premium_insured_family' => '',
        'motor.medical_expenses' => '',
        'motor.premium_medical_expenses' => '',
        'motor.passenger_liability' => '',
        'motor.premium_passenger_liability' => '',
        'motor.premium_third_party_liability' => '',
        'motor.specified_accessories' => '',
        'motor.premium_specified_accessories' => '',

        'motor.own_damage' => '',
        'motor.own_damage_minimun_percent' => '',
        'motor.own_damage_minimum_amount' => '',
        'motor.windscreen' => '',
        'motor.windscreen_minimun_percent' => '',
        'motor.windscreen_minimum_amount' => '',
        'motor.loss_of_keys' => '',
        'motor.loss_of_keys_minimun_percent' => '',
        'motor.loss_of_keys_minimum_amount' => '',

        'policyCoverage.coverage_id' => 'required',
        'policyCoverage.risk_address_id' => 'required',
        'policyCoverage.term_id' => 'required',
        'policyCoverage.action_id' => 'required',
        'policyCoverage.policy_id' => 'required',

        'policyCoverageDetail.*.*.coverage_value' => '',
        'policyCoverageDetail.*.*.coverage_value_string' => '',
        'policyCoverageDetail.*.*.discount_surcharge' => '',
        'policyCoverageDetail.*.*.discount_surcharge_type' => '',
        'policyCoverageDetail.*.*.discount_surcharge_value' => 'nullable',
        'policyCoverageDetail.*.*.rate' => '',
        'policyCoverageDetail.*.*.calculated_value' => '',

        'policyCoverageDetail.*.*.limit_id' => '',
        'policyCoverageDetail.*.*.ratefactor_type' => '',
        'policyCoverageDetail.*.*.ratefactor_value' => '',
        'policyCoverageDetail.*.*.ratefactor_value_check' => '',
        'policyCoverageDetail.*.*.ratefactor_AnnualWages' => '',
        'policyCoverageDetail.*.*.ratefactor_deposit_min_pre' => '',

        'policyExtentionDetail.*.*.extention_coverage_value' => '',
        'policyExtentionDetail.*.*.extention_excess_min_value' => '',
        'policyExtentionDetail.*.*.extention_excess_max_value' => '',
        'policyExtentionDetail.*.*.extention_text_value' => '',
        'policyExtentionDetail.*.*.extention_discount_surcharge' => '',
        'policyExtentionDetail.*.*.extention_discount_surcharge_type' => '',
        'policyExtentionDetail.*.*.extention_discount_surcharge_value' => 'nullable',
        'policyExtentionDetail.*.*.extention_calculated_value' => '',
        'policyExtentionDetail.*.*.extention_limit_id' => '',

        'policyCoverageEntity.*.*' => '',
        'policyCoverageNote.*' => '',
        'policyCoverageBenefits.*' => '',
        'policyCoverageMemorandaWarranty.*' => '',
        'policyCoverageCashWarranty.*' => '',
        'policyCoverageBurglarWarranty.*' => '',
        'policyCoverageEndorsements.*' => '',

        'PolicyCoveragesNewData.*.*.tID' => '',
        'PolicyCoveragesNewData.*.*.amount_to_be_guaranteed' => '',
        'PolicyCoveragesNewData.*.*.premium' => '',
        'PolicyCoveragesNewData.*.*.name_and_position' => '',
        'PolicyCoveragesNewData.*.*.designation' => '',
        'PolicyCoveragesNewData.*.*.length_of_service' => '',

        // 'theft.physical_protection_implemented' => 'required|string',
        // 'theft.premises_alarmed' => 'required|in:Yes,No',
        // 'theft.subscribe_armed_security' => 'required_if:theft.premises_alarmed,Yes|in:Yes,No',
        // 'theft.security_company' => 'required_if:theft.subscribe_armed_security,Yes|string',
        // 'theft.maintenance_contract' => 'required_if:theft.subscribe_armed_security,Yes|in:Yes,No',
        // 'theft.alarmed_installed_date' => 'required_if:theft.premises_alarmed,Yes|date',
        // 'theft.opening_closing_signals' => 'required_if:theft.premises_alarmed,Yes|in:Yes,No'

    ];

    protected $validationAttributes = [
        'policyCoverageDetail.*.*.calculated_value' => 'premium',
    ];
    protected $listeners = [
        'refreshParent',
        'fetchedDataUpdated',
        'refreshFidelityGuarantee',
        'riskAddressSelected'
    ];

    public function riskAddressSelected($value)
    {
        $this->riskAddressId = $value;
        $this->mount($value);
        // $this->render();
        // Fetch related data based on the selected address (optional)
    }

    public function refreshFidelityGuarantee($actionId = null)
    {
        $this->render();
    }
    public function refreshParent($actionId = null)
    {
        $this->mount();
        if ($actionId != null) {
            $this->actionId = $actionId;
        }
        $this->render();
    }

    public function render()
    {

        foreach ($this->policyCoverages as $index => $policyCoverage) {
            // $this->policyCoverage_id=['policyCoverage_id'=>$policyCoverage->id];
            // $this->policyCoverage_id['policyCoverage_id']=$policyCoverage->id;

            $this->policyCoverageBurglarWarranty[$policyCoverage->id] = "It is warranted that:
            (a) The intruder alarm installed in the premises described in the Schedule shall be set in operation at all times when the premises are left unoccupied.
            (b) The said alarm shall be maintained in efficient working order and shall be subject to a Maintenance Agreement.
            (c) The Insured shall immediately advise the Alarm Company by telephone, email, facsimile or a written communication if any defect is discovered in the said alarm system.
            (d) The Insured shall take all reasonable precautions and such additional precautions as are required by the insurers to safeguard the property insured during the period required to rectify any faults in the said system.
            (e) The alarm shall be linked to a Control Room.
            (f) Such alarm shall be maintained in proper working order but the insured shall be deemed to have discharged its liability in this regard if it has maintained its obligations under a contract with the suppliers or servicing engineers of the alarm system. This insurance shall not cover loss of or damage to the property following the use of the keys of the burglar alarm or an duplicate thereof belonging to the insured unless such keys have been obtained by violence or threat of violence to any person.";

            $this->policyCoverageBenefits[$policyCoverage->id] = "
            Death                          4x Annual Earnings max P 200,000
            Permanent Total Disablement    5x Annual Earnings Max P 250,000
            Temporary Total Disablement    66.66% of weekly earnings up to 26 weeks
            Medical Expenses               P 75,000";

            $this->policyCoverageMemorandaWarranty[$policyCoverage->id] = "Specified Limitations/Details
            Money contained in a locked safe or strongroom situated in a building at the insured premises outside the hours during which the commercial operations of the insured are conducted.

            1. In respect of the safe or strongroom described below: Description of Saferoom/strongroom
            2. In respect of any safe or strongroom not specified in the schedule above the limit shall be according to the grading of such safe or strongroom as

            a. No SABS grading  P 2,500.00
            b. SABS category 1 grading  P 5,000.00
            c. SABS category 2 grading  P 12,500.00
            d. SABS category 2 HD grading   P 25,000.00
            e. SABS category 2 ADM grading  P 50,000.00
            f. SABS category 2 ADM grading D3   P 75,000.00
            g. SABS category 3 grading  P 100,000.00
            h. SABS category 4 grading  P 200,000.00
            i. SABS category 5 grading  P 500,000.00

            Provided that the company's liability shall not exceed the limit of indemnity shown under the schedule for the respective premises.";

            $this->policyCoverageCashWarranty[$policyCoverage->id] = "It is warranted that the Company will not be liable to indemnify the Insured in respect of loss of money:-
            a) In transit unless such transit is uninterrupted between the Insured's premises and their Bank/Building Society.
            b) From any unattended vehicle.
            c) Where such money is in transit and the following precautions are not taken
            (i) Money up to P10 000 must be carried by one senior employee or principal
            (ii) Money between P10 000 and P20 000 must be carried by two senior employees or principals in a vehicle
            (iii) Money in excess of P20 000 must be carried by a professional armed security service organisation";

            foreach ($policyCoverage->coverage['subCoverage'] as $subCoverage) {
                $this->policyCoverageDetail[$policyCoverage->id][$subCoverage->id]['rate'] = $subCoverage['rate'] ?? 0;

                $propertyBusinessBeing = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $this->policyCoverage_id)->where('coverage_id', '10')->where('row_type', 'NEW')
                    ->orderBy('id', 'desc')->first(['property_business_being']);

                if (isset($propertyBusinessBeing)) {
                    $this->property_business_being = $propertyBusinessBeing->property_business_being;
                }

                // if ($subCoverage->s_CoverageCode == 'CLOTHING AND PERSONAL EFF') {
                //     $this->policyCoverageDetail[$policyCoverage->id][$subCoverage->id]['ratefactor_value'] = $subCoverage->s_ScreenName;
                // }
            }

            foreach ($policyCoverage->coverage['allExtention'] as $allExtention) {
                if ($allExtention->s_ScreenName == "Water leakage") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Television equipment maintenance") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Loss of money" && $allExtention->s_ParentCoverageCode != 'PERSONALALLRISKS') {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 3000;
                }
                if ($allExtention->s_ScreenName == "Refrigerator or deep freeze contents") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Veterinary fees") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 2000;
                }
                if ($allExtention->s_ScreenName == "Goods in the open") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Locks and keys") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Golfers hole-in-one" && $allExtention->s_ParentCoverageCode != 'PERSONALALLRISKS') {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 2000;
                }
                if ($allExtention->s_ScreenName == "Property of domestic employees") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Personal effects of guests") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Medical expenses") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "death by accident" || $allExtention->s_ScreenName == "Fatal injury - death by accident") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 10000;
                }
                if ($allExtention->s_ScreenName == "death by thieves or fire" || $allExtention->s_ScreenName == "Fatal injury - death by thieves or fire") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 15000;
                }
                if ($allExtention->s_ScreenName == "temporary repairs and other measures" || $allExtention->s_ScreenName == "Repairs and measures after a loss - temporary repairs and other measures") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "emergency accommodation" || $allExtention->s_ScreenName == "Repairs and measures after a loss - emergency accommodation") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Telephones") {
                    $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['extention_coverage_value'] = 2000;
                }


                $this->policyExtentionDetail[$policyCoverage->id][$allExtention->id]['rate'] = $allExtention['rate'] ?? 0;
            }
            foreach ($policyCoverage->coverage['allBurglarAlarmWarranty'] as $allBurglarAlarmWarranty) {
                $this->policyExtentionDetail[$policyCoverage->id][$allBurglarAlarmWarranty->id]['rate'] = $allBurglarAlarmWarranty['rate'] ?? 0;
            }
            foreach ($policyCoverage->entities as $entity) {
                $this->policyCoverageEntity[$policyCoverage->id][$entity->entity_type] = $entity->entity_id;
            }

            if ($extention->s_ParentCoverageID ?? $policyCoverage->coverage_id != 22 && $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id != 27) {
                $this->policyCoverageNote[$policyCoverage->id] = $policyCoverage->note->note ?? '';
            }

            $this->policyCoverageBenefits[$policyCoverage->id] = $policyCoverage->note->benefits_note ?? $this->policyCoverageBenefits[$policyCoverage->id];
            $this->policyCoverageMemorandaWarranty[$policyCoverage->id] = $policyCoverage->note->memoranda_warranty ?? $this->policyCoverageMemorandaWarranty[$policyCoverage->id];
            $this->policyCoverageCashWarranty[$policyCoverage->id] = $policyCoverage->note->cash_warranty ?? $this->policyCoverageCashWarranty[$policyCoverage->id];
            $this->policyCoverageBurglarWarranty[$policyCoverage->id] = $policyCoverage->note->burglar_warranty ?? $this->policyCoverageBurglarWarranty[$policyCoverage->id];
            $this->policyCoverageEndorsements[$policyCoverage->id] = $policyCoverage->note->endorsements ?? '';

            $PolicyCoveragesDataDetails = PolicyCoveragesData::Where('policy_id', $this->policy->id)
                ->Where('policyCoverageID', $policyCoverage->id)
                ->orderBy('id', 'asc')->get();

            if (isset($PolicyCoveragesDataDetails)) {
                foreach ($PolicyCoveragesDataDetails as $key => $value) {
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['name_and_position'] = ($value->name_and_position != null) ? $value->name_and_position : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['designation'] = ($value->designation != null) ? $value->designation : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['length_of_service'] = ($value->length_of_service != null) ? $value->length_of_service : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['policyCoverageID'] = ($value->policyCoverageID != null) ? $value->policyCoverageID : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['subpolicyCoverageID'] = ($value->subpolicyCoverageID != null) ? $value->subpolicyCoverageID : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['cover_type'] = ($value->cover_type != null) ? $value->cover_type : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['cover_area'] = ($value->cover_area != null) ? $value->cover_area : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['premium'] = ($value->premium != null) ? $value->premium : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['amount_to_be_guaranteed'] = ($value->amount_to_be_guaranteed != null) ? $value->amount_to_be_guaranteed : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['tId'] = ($value->id != null) ? $value->id : '';

                    $this->PolicyCoveragesCoverNewData[$policyCoverage->id]['cover_type'] = ($value->cover_type != null) ? $value->cover_type : '';
                    $this->PolicyCoveragesCoverNewData[$policyCoverage->id]['policyCoverageID'] = ($value->policyCoverageID != null) ? $value->policyCoverageID : '';
                }
            }


            $this->loadTheftData();
        }

        //dd( $this->motorVehicleData['policy_coverage_id']);
        if (isset($this->motorVehicleData['motor_policy_cov_id'])) {
            $this->motorVehicleData['policy_coverage_id'] = $this->motorVehicleData['motor_policy_cov_id'] ?? '';
        }

        $this->allRiskAddress = RiskAddress::where('term_id', $this->termId)
            ->where('action_id', $this->actionId)
            ->where('policy_id', $this->policy->id)
            ->select('id', 'address_name')
            ->get();


        return view('v2.livewire.policy.manage-new-coverages');
    }

    public function mount($riskAddressId = null)
    {

        $this->riskAddressId = $riskAddressId ?? $this->riskAddressId;

        $this->allRiskAddress = RiskAddress::where('term_id', $this->termId)
            ->where('action_id', $this->actionId)
            ->where('policy_id', $this->policy->id)
            ->select('id', 'address_name')
            ->get();

        $this->third_party_liability = 2500000;
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        $this->policyCoverage = new PolicyCoverage();
        $this->policyCoverage->term_id = $this->termId;
        $this->policyCoverage->action_id = $this->actionId;
        $this->policyCoverage->policy_id = $this->policy->id;
        $this->AllMainCoverages;

        $this->vehicleData = Vehicle::where('policy_id', $this->policy->id)->first();
        $this->selectedVehicleData = $this->getVehicleData($this->vehicleSelected, $this->coverageSelected, $this->subCoverageSelected);

        foreach ($this->policyCoverages as $policyCoverag) {
            $this->loadTheftData();
            $propertyBusinessBeing = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $policyCoverag->id)->where('coverage_id', '10')->where('row_type', 'NEW')
                ->orderBy('id', 'desc')->first(['property_business_being']);

            if (isset($propertyBusinessBeing)) {
                $this->property_business_being = $propertyBusinessBeing->property_business_being;
            }
            $personalMotorData = '';
            $this->coverageIdForVehicle = $policyCoverag->id;
            $this->vehicles = Vehicle::PolicyId($this->policy->id)->TermId($this->termId)->ActionId($this->actionId)->get();
            foreach ($this->vehicles as $vehicle) {
                $this->vehiclePlateNo = $vehicle->vehiclePlate;
                $personalMotorData = Motor::where('policy_coverage_id', $this->coverageIdForVehicle)->where('registration_no', $this->vehiclePlateNo)->orderBy('id', 'asc')->first();
                if (isset($personalMotorData)) {
                    $this->motorVehicleData['use_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->use_main;
                    $this->motorVehicleData['type_of_cover_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->type_of_cover_main;
                    $this->motorVehicleData['coverage_value_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->coverage_value_main;
                    $this->motorVehicleData['calculated_value_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->calculated_value_main;
                    //$this->motorVehicleData['policy_coverage_id'] = $this->coverageIdForVehicle;

                }
            }
            // Motor External Traders
            $motorTradersExternalData = MotorTraders::where('policy_coverage_id', $policyCoverag->id)->orderBy('id', 'asc')->first();
            if ($motorTradersExternalData != null) {
                $this->motorTradersExternal['type_of_cover_external_main_' . $policyCoverag->id] = $motorTradersExternalData->type_of_cover;
            } else {
                $this->motorTradersExternal['type_of_cover_external_main_' . $policyCoverag->id] = null;
            }

            // Motor Internal Traders
            $motorTradersInternalData = MotorTradersInternal::where('policy_coverage_id', $policyCoverag->id)->orderBy('id', 'asc')->first();
            if ($motorTradersInternalData != null) {
                $this->motorTradersInternal['type_of_cover_internal_main_' . $policyCoverag->id] = $motorTradersInternalData->type_of_cover;
            } else {
                $this->motorTradersInternal['type_of_cover_internal_main_' . $policyCoverag->id] = null;
            }

        }
        foreach ($this->policyCoverages as $index => $policyCoverage) {
            if ($extention->s_ParentCoverageID ?? $policyCoverage->coverage_id != 22 && $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id != 27) {

                foreach ($policyCoverage->specifedItems as $index => $specifedItemData) {
                    $this->specified_items[$policyCoverage->id][$index] = [
                        'selected_item' => $specifedItemData['specified_coverage_id'],
                        'sum_insured' => $specifedItemData['sum_insured'],
                        'rate' => $this->specifiedItemsWithRate[$specifedItemData['id']] ?? 0,
                        'id' => $specifedItemData['id'],
                    ];
                    if (!isset($this->specifiedRow[$policyCoverage->id][$index])) {
                        $this->specifiedRow[$policyCoverage->id][$index] = [];
                    }
                }
            }

            $this->policyCoverageID = ['policyCoverageID' => $policyCoverage->id];
            //echo "**".$policyCoverage->id;
            // if($personalMotorData=='' && !isset($personalMotorData)){
            $this->PolicyCoveragesData = PolicyCoveragesData::Where('policyCoverageID', $policyCoverage->id)
                ->Where('policy_id', $this->policy->id)
                ->get();
            //}
            //echo "**".$policyCoverage->id;
            $PolicyCoveragesDataDetails = PolicyCoveragesData::Where('policy_id', $this->policy->id)
                ->Where('policyCoverageID', $policyCoverage->id)
                ->orderBy('id', 'asc')->get();

            if (isset($PolicyCoveragesDataDetails)) {
                foreach ($PolicyCoveragesDataDetails as $key => $value) {
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['name_and_position'] = ($value->name_and_position != null) ? $value->name_and_position : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['designation'] = ($value->designation != null) ? $value->designation : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['length_of_service'] = ($value->length_of_service != null) ? $value->length_of_service : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['policyCoverageID'] = ($value->policyCoverageID != null) ? $value->policyCoverageID : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['subpolicyCoverageID'] = ($value->subpolicyCoverageID != null) ? $value->subpolicyCoverageID : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['cover_type'] = ($value->cover_type != null) ? $value->cover_type : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['cover_area'] = ($value->cover_area != null) ? $value->cover_area : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['premium'] = ($value->premium != null) ? $value->premium : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['amount_to_be_guaranteed'] = ($value->amount_to_be_guaranteed != null) ? $value->amount_to_be_guaranteed : '';
                    $this->PolicyCoveragesNewData[$policyCoverage->id][$key]['tId'] = ($value->id != null) ? $value->id : '';

                    $this->PolicyCoveragesCoverNewData[$policyCoverage->id]['cover_type'] = ($value->cover_type != null) ? $value->cover_type : '';
                    $this->PolicyCoveragesCoverNewData[$policyCoverage->id]['policyCoverageID'] = ($value->policyCoverageID != null) ? $value->policyCoverageID : '';
                }
            }

            //$this->posts = $this->PolicyCoveragesNewData;
            $this->selectedDataDropdownValue = isset($PolicyCoveragesDataDetails->cover_type) ? $PolicyCoveragesDataDetails->cover_type : 'null';
            $this->selectedOption = '';
            //dd($this->posts);

            $PolicyExcessesDataDetails = PolicyExcessesData::Where('policy_id', $this->policy->id)->orderBy('id', 'asc')->get();
            foreach ($PolicyExcessesDataDetails as $pKey => $pValue) {
                $this->PolExData[$pKey]['excesses'] = $pValue->excesses;
                $this->PolExData[$pKey]['min_percent'] = $pValue->min_percent;
                $this->PolExData[$pKey]['min_amt'] = $pValue->min_amt;
                $this->PolExData[$pKey]['id'] = $pValue->id;
            }
            $this->pExcessesData = $this->PolExData;

            $PolicyBusiExcessesDataDetails = PolicyBusiExcessesData::Where('policy_id', $this->policy->id)->orderBy('id', 'asc')->get();
            foreach ($PolicyBusiExcessesDataDetails as $pKey => $pValue) {
                $this->PolBusiExData[$pKey]['excesses'] = $pValue->excesses;
                $this->PolBusiExData[$pKey]['min_percent'] = $pValue->min_percent;
                $this->PolBusiExData[$pKey]['min_amt'] = $pValue->min_amt;
                $this->PolBusiExData[$pKey]['id'] = $pValue->id;
            }
            $this->pBusiExcessesData = $this->PolBusiExData;
            if (isset($policyCoverage) && $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id == 21) {

                $this->policyCoverage_id['policyCoverage_id'] = $policyCoverage->id;
                $this->publicliability_date = ['publicliability_date' => $policyCoverage->publicliability_date != null ? Carbon::createFromFormat('Y-m-d', $policyCoverage->publicliability_date)->format('d/m/Y') : ''];

            }

            foreach ($policyCoverage->coverageDetail as $coverageDetail) {

                // dump($this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['coverage_value'] = $coverageDetail['coverage_value'] ?? 0);
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['limit_id'] = $coverageDetail['limit_id'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['coverage_value'] = $coverageDetail['coverage_value'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['coverage_value_string'] = $coverageDetail['coverage_value_string'] ?? null;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['ratefactor_type'] = $coverageDetail['ratefactor_type'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['ratefactor_value'] = $coverageDetail['ratefactor_value'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['ratefactor_value_check'] = $coverageDetail['ratefactor_value_check'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['ratefactor_AnnualWages'] = $coverageDetail['ratefactor_AnnualWages'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['ratefactor_deposit_min_pre'] = $coverageDetail['ratefactor_deposit_min_pre'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['discount_surcharge'] = $coverageDetail['discount_surcharge'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['discount_surcharge_type'] = $coverageDetail['discount_surcharge_type'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['discount_surcharge_value'] = $coverageDetail['discount_surcharge_value'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['calculated_value'] = $coverageDetail['calculated_value'] ?? 0;
                $this->calculatedValuePast = $coverageDetail->coverage_id . '_' . $coverageDetail['calculated_value'];

                $this->subpolicyCoverageID = ['subpolicyCoverageID' => $coverageDetail->coverage_id];


                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['get_coverage_id'] = $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? 0;


            }
            foreach ($policyCoverage->extentionDetail as $extentionDetail) {
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_sum_insured'] = $extentionDetail['extention_sum_insured'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_limit_id'] = $extentionDetail['extention_limit_id'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_text_value'] = $extentionDetail['extention_text_value'] ?? null;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_coverage_value'] = $extentionDetail['extention_coverage_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_excess_min_value'] = $extentionDetail['extention_excess_min_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_excess_max_value'] = $extentionDetail['extention_excess_max_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_discount_surcharge'] = $extentionDetail['extention_discount_surcharge'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_discount_surcharge_type'] = $extentionDetail['extention_discount_surcharge_type'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_discount_surcharge_value'] = $extentionDetail['extention_discount_surcharge_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_calculated_value'] = $extentionDetail['extention_calculated_value'] ?? 0;
            }
        }
        // dd($this->PolicyCoveragesCoverNewData);
        $this->policyAction = PolicyAction::where('id', $this->actionId)->first();

    }

    public function submit()
    {

        $latestAction = PolicyAction::where('id', $this->actionId)
            ->orderBy('id', 'desc')
            ->first();

        $previousAction = PolicyAction::where('id', '<', $this->actionId)->where('policy_id', $this->policy->id)->orderBy('id', 'desc')->first();


        $this->rules = [

            'motorTradersExternal.policy_coverage_id' => '',
            'motorTradersExternal.loss_or_damage_coverage_value' => '',
            'motorTradersExternal.loss_or_damage_calculated_value' => '',
            'motorTradersExternal.third_party_liability_coverage_value' => '',
            'motorTradersExternal.third_party_liability_calculated_value' => '',
            'motorTradersExternal.medical_benefits_coverage_value' => '',
            'motorTradersExternal.medical_benefits_calculated_value' => '',
            'motorTradersExternal.vehicle_lent_hire_coverage_value' => '',
            'motorTradersExternal.vehicle_lent_hire_calculated_value' => '',
            'motorTradersExternal.social_domestic_pleasure_coverage_value' => '',
            'motorTradersExternal.social_domestic_pleasure_calculated_value' => '',
            'motorTradersExternal.unauthoried_use_coverage_value' => '',
            'motorTradersExternal.unauthoried_use_calculated_value' => '',
            'motorTradersExternal.windscreen_coverage_value' => '',
            'motorTradersExternal.windscreen_calculated_value' => '',
            'motorTradersExternal.contigent_liability_coverage_value' => '',
            'motorTradersExternal.contigent_liability_calculated_value' => '',
            'motorTradersExternal.wreckage_removal_coverage_value' => '',
            'motorTradersExternal.wreckage_removal_calculated_value' => '',
            'motorTradersExternal.loss_of_key_coverage_value' => '',
            'motorTradersExternal.loss_of_key_calculated_value' => '',
            'motorTradersExternal.Loss_of_use_of_customer_coverage_value' => '',
            'motorTradersExternal.Loss_of_use_of_customer_calculated_value' => '',
            'motorTradersExternal.motor_cycle_motor_tricycle_coverage_value' => '',
            'motorTradersExternal.motor_cycle_motor_tricycle_calculated_value' => '',
            'motorTradersExternal.passanger_liability_respect_of_motor_coverage_value' => '',
            'motorTradersExternal.passanger_liability_respect_of_motor_calculated_value' => '',
            'motorTradersExternal.special_type_vehicle_coverage_value' => '',
            'motorTradersExternal.special_type_vehicle_calculated_value' => '',
            'motorTradersExternal.own_damage_minimun_percent' => '',
            'motorTradersExternal.own_damage_minimum_amount' => '',
            'motorTradersExternal.windscreen_minimun_percent' => '',
            'motorTradersExternal.windscreen_minimum_amount' => '',
            'motorTradersExternal.type_of_cover_external_main_.*' => '',

            'motorTradersInternal.policy_coverage_id' => '',
            'motorTradersInternal.loss_or_damage_coverage_value' => '',
            'motorTradersInternal.loss_or_damage_calculated_value' => '',
            'motorTradersInternal.third_party_liability_coverage_value' => '',
            'motorTradersInternal.third_party_liability_calculated_value' => '',
            'motorTradersInternal.medical_benefits_coverage_value' => '',
            'motorTradersInternal.medical_benefits_calculated_value' => '',
            'motorTradersInternal.vehicle_lent_hire_coverage_value' => '',
            'motorTradersInternal.vehicle_lent_hire_calculated_value' => '',
            'motorTradersInternal.social_domestic_pleasure_coverage_value' => '',
            'motorTradersInternal.social_domestic_pleasure_calculated_value' => '',
            'motorTradersInternal.unauthoried_use_coverage_value' => '',
            'motorTradersInternal.unauthoried_use_calculated_value' => '',
            'motorTradersInternal.windscreen_coverage_value' => '',
            'motorTradersInternal.windscreen_calculated_value' => '',
            'motorTradersInternal.contigent_liability_coverage_value' => '',
            'motorTradersInternal.contigent_liability_calculated_value' => '',
            'motorTradersInternal.wreckage_removal_coverage_value' => '',
            'motorTradersInternal.wreckage_removal_calculated_value' => '',
            'motorTradersInternal.loss_of_key_coverage_value' => '',
            'motorTradersInternal.loss_of_key_calculated_value' => '',
            'motorTradersInternal.Loss_of_use_of_customer_coverage_value' => '',
            'motorTradersInternal.Loss_of_use_of_customer_calculated_value' => '',
            'motorTradersInternal.motor_cycle_motor_tricycle_coverage_value' => '',
            'motorTradersInternal.motor_cycle_motor_tricycle_calculated_value' => '',
            'motorTradersInternal.passanger_liability_respect_of_motor_coverage_value' => '',
            'motorTradersInternal.passanger_liability_respect_of_motor_calculated_value' => '',
            'motorTradersInternal.special_type_vehicle_coverage_value' => '',
            'motorTradersInternal.special_type_vehicle_calculated_value' => '',
            'motorTradersInternal.own_damage_minimun_percent' => '',
            'motorTradersInternal.own_damage_minimum_amount' => '',
            'motorTradersInternal.windscreen_minimun_percent' => '',
            'motorTradersInternal.windscreen_minimum_amount' => '',
            'motorTradersInternal.type_of_cover_internal_main_.*' => '',

            'motorVehicleData.use_main_.*' => '',
            'motorVehicleData.type_of_cover_main_.*' => '',
            'motorVehicleData.coverage_value_main_.*' => '',
            'motorVehicleData.calculated_value_main_.*' => '',
            'motorVehicleData.vehicle_name' => '',
            'motorVehicleData.policy_coverage_id' => '',
            'motorVehicleData.calculated_value' => '',
            'motorVehicleData.coverage_value' => '',

            'motor.estimated_value' => '',
            'motor.use' => '',
            'motor.registration_no' => '',
            'motor.make' => '',
            'motor.model' => '',
            'motor.engine_number' => '',
            'motor.chassis_number' => '',
            'motor.tracking_device' => '',
            'motor.security_features' => '',
            'motor.type_of_cover' => '',

            'motor.wreckage_removal' => '',
            'motor.premium_wreckage_removal' => '',
            'motor.window_glass' => '',
            'motor.premium_window_glass' => '',
            'motor.locks_keys' => '',
            'motor.premium_locks_keys' => '',
            'motor.parts_accessories' => '',
            'motor.premium_parts_accessories' => '',
            'motor.riot_strike' => '',
            'motor.premium_riot_strike' => '',
            'motor.car_hire_theft' => '',
            'motor.premium_car_hire_theft' => '',
            'motor.credit_shortfall' => '',
            'motor.premium_credit_shortfall' => '',
            'motor.insured_driver' => '',
            'motor.premium_insured_driver' => '',
            'motor.insured_family' => '',
            'motor.premium_insured_family' => '',
            'motor.medical_expenses' => '',
            'motor.premium_medical_expenses' => '',
            'motor.passenger_liability' => '',
            'motor.premium_passenger_liability' => '',
            'motor.third_party_liability' => '',
            'motor.premium_third_party_liability' => '',
            'motor.specified_accessories' => '',
            'motor.premium_specified_accessories' => '',

            'motor.own_damage' => '',
            'motor.own_damage_minimun_percent' => '',
            'motor.own_damage_minimum_amount' => '',
            'motor.windscreen' => '',
            'motor.windscreen_minimun_percent' => '',
            'motor.windscreen_minimum_amount' => '',
            'motor.loss_of_keys' => '',
            'motor.loss_of_keys_minimun_percent' => '',
            'motor.loss_of_keys_minimum_amount' => '',

            'motor.unorthorised_passanger_liability' => '',
            'motor.premium_unorthorised_passanger_liability' => '',
            'motor.parking_facilities' => '',
            'motor.premium_parking_facilities' => '',
            'motor.com_windscreen' => '',
            'motor.premium_com_windscreen' => '',

            'policyCoverageDetail.*.*.coverage_value' => '',
            'policyCoverageDetail.*.*.coverage_value_string' => '',
            'policyCoverageDetail.*.*.limit_id' => '',
            'policyCoverageDetail.*.*.ratefactor_type' => '',
            'policyCoverageDetail.*.*.ratefactor_value' => '',
            'policyCoverageDetail.*.*.ratefactor_value_check' => '',
            'policyCoverageDetail.*.*.ratefactor_AnnualWages' => '',
            'policyCoverageDetail.*.*.ratefactor_deposit_min_pre' => '',
            'policyCoverageDetail.*.*.discount_surcharge' => '',
            'policyCoverageDetail.*.*.discount_surcharge_type' => '',
            'policyCoverageDetail.*.*.discount_surcharge_value' => 'nullable',
            // 'policyCoverageDetail.*.*.calculated_value' => 'numeric|min:0',
            'policyCoverageDetail.*.*.calculated_value' => '',
            'policyCoverageDetail.*.*.rate' => '',

            'policyExtentionDetail.*.*.extention_coverage_value' => '',
            'policyExtentionDetail.*.*.extention_excess_min_value' => '',
            'policyExtentionDetail.*.*.extention_excess_max_value' => '',
            'policyExtentionDetail.*.*.extention_text_value' => '',
            'policyExtentionDetail.*.*.extention_limit_id' => '',
            'policyExtentionDetail.*.*.extention_discount_surcharge' => '',
            'policyExtentionDetail.*.*.extention_discount_surcharge_type' => '',
            'policyExtentionDetail.*.*.extention_discount_surcharge_value' => 'nullable',
            'policyExtentionDetail.*.*.extention_calculated_value' => '',
            'publicliability_date.*.*.publicliability_date' => 'required',

            // 'theft.physical_protection_implemented' => 'required|string',
            // 'theft.premises_alarmed' => 'required|in:Yes,No',
            // 'theft.subscribe_armed_security' => 'required_if:theft.premises_alarmed,Yes|in:Yes,No',
            // 'theft.security_company' => 'required_if:theft.subscribe_armed_security,Yes|string',
            // 'theft.maintenance_contract' => 'required_if:theft.subscribe_armed_security,Yes|in:Yes,No',
            // 'theft.alarmed_installed_date' => 'required_if:theft.premises_alarmed,Yes|date',
            // 'theft.opening_closing_signals' => 'required_if:theft.premises_alarmed,Yes|in:Yes,No'

        ];
        $this->validate();


        foreach ($this->PolicyCoveragesNewData as $coverId => $pCoverageData) {
            foreach ($pCoverageData as $key => $data) {

                $currentValue = (float) str_replace(',', '', ($data['premium'] ?? '')) ?? 0;
                $fidactionId = 0;
                $endors_flag = 0;
                $pro_rate_premium = 0; // default = full value if new
                $checkOldFidelity = DB::table('policy_coverages_data')
                    ->join('policy_coverages', 'policy_coverages_data.policyCoverageID', '=', 'policy_coverages.id')
                    ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                    ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id') // join risk_address
                    ->where('policy_coverages_data.policy_id', $this->policy->id)
                    ->where('policy_coverages_data.policyCoverageID', $coverId)
                    ->whereNull('policy_coverages.deleted_at')
                    ->whereNull('policy_actions.deleted_at')
                    ->orderBy('policy_coverages_data.policyCoverageID', 'desc')
                    ->select(
                        'policy_coverages_data.*',
                        'policy_coverages.action_id',
                        'risk_address.address_name as risk_address_name'
                    )
                    ->first();


                if ($latestAction->transaction_type == 'ENDORSE') {

                    if (!$checkOldFidelity) {
                        // Case 1: First time endorsement → full premium
                        $fidactionId = $latestAction->id;
                        $endors_flag = 1;
                        $pro_rate_premium = $currentValue;
                    } else {
                        $oldValue = $checkOldFidelity->premium ?? 0;

                        if ($latestAction->id == $checkOldFidelity->previousActionIdCov) {
                            // Case 2: Same action (multiple edits) → use value from previous action
                            $previousDetail = DB::table('policy_coverages_data')
                                ->join('policy_coverages', 'policy_coverages_data.policyCoverageID', '=', 'policy_coverages.id')
                                ->join('policies', 'policy_coverages_data.policy_id', '=', 'policies.id')
                                ->join('policy_actions', 'policies.id', '=', 'policy_actions.policy_id')
                                ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id') // join risk_address
                                ->where('policy_coverages_data.policy_id', $this->policy->id)
                                ->where('policy_actions.id', $previousAction['id'])
                                ->where('risk_address.address_name', $checkOldFidelity->risk_address_name) // check risk address name
                                ->whereNull('policy_coverages.deleted_at')
                                ->whereNull('policy_actions.deleted_at')
                                ->orderBy('policy_coverages_data.id', 'desc')
                                ->select(
                                    'policy_coverages_data.premium',
                                    'risk_address.address_name as risk_address_name'
                                )
                                ->first();


                            $baseOldValue = $previousDetail->premium ?? 0;
                        } else {
                            // Case 3: Different action → use stored old premium
                            $baseOldValue = $oldValue;
                        }

                        // Case 4: Compare and calculate pro_rate_premium
                        if ($currentValue > $baseOldValue) {
                            $fidactionId = $latestAction->id;
                            $endors_flag = 1;
                            $pro_rate_premium = $currentValue - $baseOldValue;
                        } elseif ($currentValue < $baseOldValue) {
                            $fidactionId = $latestAction->id;
                            $endors_flag = 1;
                            $pro_rate_premium = $currentValue - $baseOldValue; // can be negative
                        } else {
                            // Case 5: No change → keep old mapping
                            $fidactionId = $checkOldFidelity->previousActionIdCov;
                            $endors_flag = $checkOldFidelity->endors_flag;
                            $pro_rate_premium = $currentValue;
                        }
                    }
                }

                // Save new or updated fidelity record
                $dataInput = [
                    'name_and_position' => $data['name_and_position'] ?? null,
                    'designation' => $data['designation'] ?? null,
                    'length_of_service' => $data['length_of_service'] ?? null,
                    'premium' => $currentValue,
                    'amount_to_be_guaranteed' => (float) (str_replace(',', '', ($data['amount_to_be_guaranteed'] ?? ''))) ?? 0,
                    'policy_id' => $this->policy->id,
                    'cover_type' => $data['cover_type'] ?? null,
                    'cover_area' => $data['cover_area'] ?? null,
                    'previousActionIdCov' => $fidactionId,
                ];

                // Endorsement fields: real values on ENDORSE, 0 on new business / other types
                $dataInput['pro_rate_premium'] = 0;
                $dataInput['endors_flag'] = 0;
                if ($latestAction->transaction_type == 'ENDORSE') {
                    $dataInput['pro_rate_premium'] = $pro_rate_premium;
                    $dataInput['endors_flag'] = $endors_flag;
                }

                DB::table('policy_coverages_data')
                    ->where('policy_id', $this->policy->id)
                    ->where('id', $data['tId'])
                    ->update($dataInput);


            }
        }



        if (isset($this->inputBusiExcessesData) && count($this->inputBusiExcessesData) > 0) {
            foreach ($this->inputBusiExcessesData as $coverId => $pBusiExcessesData) {
                if ($pBusiExcessesData['excesses'] != null) {
                    $dataInput = [
                        'excesses' => $pBusiExcessesData['excesses'] ?? 0,
                        'min_percent' => $pBusiExcessesData['min_percent'] ?? 0,
                        'min_amt' => (float) (str_replace(',', '', ($pBusiExcessesData['min_amt'] ?? ''))) ?? 0,
                        'policy_id' => $this->policy->id,
                    ];
                    $data = PolicyBusiExcessesData::updateOrCreate($dataInput);
                }

            }
        }

        if (isset($this->inputExcessesData) && count($this->inputExcessesData) > 0) {
            foreach ($this->inputExcessesData as $coverId => $pExcessesData) {

                $dataInput = [
                    'excesses' => $pExcessesData['excesses'] ?? null,
                    'min_percent' => $pExcessesData['min_percent'] ?? 0,
                    'min_amt' => (float) (str_replace(',', '', ($pExcessesData['min_amt'] ?? ''))) ?? 0,
                    'policy_id' => $this->policy->id,
                ];
                $data = PolicyExcessesData::updateOrCreate($dataInput);


            }
        }
        if (isset($this->policyCoverage_id['policyCoverage_id'])) {
            $policyCovChkPublicdate = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $this->policyCoverage_id['policyCoverage_id'])->orderBy('id', 'desc')->first('coverage_id');
            // if(isset($policyCovChkPublicdate) && $policyCovChkPublicdate->coverage_id==21){
            //     if(isset($this->publicliability_date['publicliability_date']) && $this->publicliability_date['publicliability_date']!=''){
            //         DB::table('policy_coverages')->where('id',$this->policyCoverage_id['policyCoverage_id'])
            //         ->update([
            //             'publicliability_date' =>Carbon::createFromFormat('d/m/Y', $this->publicliability_date['publicliability_date'])->format('Y-m-d'),
            //         ]);
            //     }
            // } commented as per bonnos request
        }

        foreach ($this->policyCoverageDetail as $policyCoverageId => $policyCoverageData) {

            foreach ($policyCoverageData as $coverage_id => $coverageData) {

                if (isset($coverageData['coverage_value']) || isset($coverageData['limit_id']) || isset($coverageData['coverage_value_string']) || isset($coverageData['ratefactor_value']) || isset($coverageData['ratefactor_value_check'])) {
                    $coverageData['ratefactor_type'] = $coverageData['ratefactor_type'] ?? null;
                    $coverageData['ratefactor_value'] = $coverageData['ratefactor_value'] ?? null;
                    $coverageData['ratefactor_value_check'] = $coverageData['ratefactor_value_check'] ?? null;
                    $coverageData['ratefactor_AnnualWages'] = $coverageData['ratefactor_AnnualWages'] ?? null;
                    $coverageData['ratefactor_deposit_min_pre'] = $coverageData['ratefactor_deposit_min_pre'] ?? null;

                    $coverageData['limit_id'] = $coverageData['limit_id'] ?? null;
                    $coverageData['coverage_value'] = (float) (str_replace(',', '', ($coverageData['coverage_value'] ?? ''))) ?? 0;
                    $coverageData['coverage_value_string'] = $coverageData['coverage_value_string'] ?? null;
                    $coverageData['discount_surcharge_value'] = (float) (str_replace(',', '', ($coverageData['discount_surcharge_value'] ?? ''))) ?? 0;
                    // $calculated_value = $coverageData['coverage_value'] ?? 0; changed cause value was not showing in form
                    $calculated_value = (float) (str_replace(',', '', ($coverageData['calculated_value'] ?? ''))) ?? 0;

                    /* Commented as par bonnos request, would not rate
                    if (isset($coverageData['rate'])){
                        $calculated_value = ((float)($calculated_value)*(float)($coverageData['rate']))/100;

                      }*/

                    if (isset($coverageData['discount_surcharge']) and isset($coverageData['discount_surcharge_type']) and isset($coverageData['discount_surcharge_value'])) {
                        if ($coverageData['discount_surcharge'] == "Discount") {
                            if ($coverageData['discount_surcharge_type'] == "Flat") {
                                $calculated_value = (float) ($calculated_value) - (float) ($coverageData['discount_surcharge_value']);
                            }
                            if ($coverageData['discount_surcharge_type'] == "Percentage") {
                                $calculated_value = (float) ($calculated_value) - (((float) ($calculated_value) * (float) ($coverageData['discount_surcharge_value'])) / 100);
                            }
                        }
                        if ($coverageData['discount_surcharge'] == "Surcharge") {
                            if ($coverageData['discount_surcharge_type'] == "Flat") {
                                $calculated_value = (float) ($calculated_value) + (float) ($coverageData['discount_surcharge_value']);
                            }
                            if ($coverageData['discount_surcharge_type'] == "Percentage") {
                                $calculated_value = (float) ($calculated_value) + (((float) ($calculated_value) * (float) ($coverageData['discount_surcharge_value'])) / 100);
                            }
                        }
                    }
                    // else{
                    //     $coverageData['discount_surcharge'] = null;
                    //     $coverageData['discount_surcharge_type'] = null;
                    //     $coverageData['discount_surcharge_value'] = null;
                    // }
                    $coverageData['calculated_value'] = $calculated_value;
                    $this->policyCoverageDetail[$policyCoverageId][$coverage_id] = $coverageData;
                }
            }
        }

        // For extention

        foreach ($this->policyExtentionDetail as $policyCoverageId => $policyCoverageData) {
            foreach ($policyCoverageData as $extentions_id => $extentionData) {
                if (isset($extentionData['extention_coverage_value']) || isset($extentionData['extention_sum_insured']) || isset($extentionData['extention_text_value']) || isset($extentionData['extention_limit_id'])) {
                    $extentionData['extention_limit_id'] = $extentionData['extention_limit_id'] ?? null;
                    $extentionData['extention_sum_insured'] = (float) (str_replace(',', '', ($extentionData['extention_sum_insured'] ?? ''))) ?? 0;
                    $extentionData['extention_text_value'] = $extentionData['extention_text_value'] ?? null;

                    $extentionData['extention_excess_min_value'] = $extentionData['extention_excess_min_value'] ?? null;
                    $extentionData['extention_excess_max_value'] = $extentionData['extention_excess_max_value'] ?? null;

                    $extentionData['extention_coverage_value'] = (float) (str_replace(',', '', ($extentionData['extention_coverage_value'] ?? ''))) ?? 0;
                    $extentionData['extention_discount_surcharge_value'] = (float) (str_replace(',', '', ($extentionData['extention_discount_surcharge_value'] ?? ''))) ?? 0;
                    $calculated_value = (float) (str_replace(',', '', ($extentionData['extention_calculated_value'] ?? ''))) ?? 0;

                    /* Commented as par bonnos request, would not rate
                    if(isset($extentionData['extention_limit_id']) || isset($extentionData['extention_text_value'])){
                        $extentionData['extention_rate'] = 0.00;
                        if (isset($extentionData['extention_rate'])){
                          #  $calculated_value = ((float)($extentionData['extention_rate']))/100;
                        }
                    }else{
                        if (isset($extentionData['extention_rate'])){
                          #  $calculated_value = ((float)($calculated_value)*(float)($extentionData['extention_rate']))/100;
                        }
                    }
                            */
                    if (isset($extentionData['extention_discount_surcharge']) and isset($extentionData['extention_discount_surcharge_type']) and isset($coverageData['extention_discount_surcharge_value'])) {
                        if ($extentionData['extention_discount_surcharge'] == "Discount") {
                            if ($extentionData['extention_discount_surcharge_type'] == "Flat") {
                                $calculated_value = (float) ($calculated_value) - (float) ($extentionData['extention_discount_surcharge_value']);
                            }
                            if ($extentionData['extention_discount_surcharge_type'] == "Percentage") {
                                $calculated_value = (float) ($calculated_value) - (((float) ($calculated_value) * (float) ($extentionData['extention_discount_surcharge_value'])) / 100);
                            }
                        }
                        if ($extentionData['extention_discount_surcharge'] == "Surcharge") {
                            if ($extentionData['extention_discount_surcharge_type'] == "Flat") {
                                $calculated_value = (float) ($calculated_value) + (float) ($extentionData['extention_discount_surcharge_value']);
                            }
                            if ($extentionData['extention_discount_surcharge_type'] == "Percentage") {
                                $calculated_value = (float) ($calculated_value) + (((float) ($calculated_value) * (float) ($extentionData['extention_discount_surcharge_value'])) / 100);
                            }
                        }
                    }
                    $extentionData['extention_calculated_value'] = $calculated_value;
                    $this->policyExtentionDetail[$policyCoverageId][$extentions_id] = $extentionData;
                }
            }
        }

        $policyTerm = PolicyTerm::where('id', $this->termId)
            //->where('status','Active')
            ->first();
        $diff_in_days_new_coverage = 0;
        // $diff_in_days_main = Carbon::parse($policyTerm->term_end_date)->diffInDays(Carbon::parse($policy->term_start_date));
        $datetime1 = strtotime($policyTerm->term_start_date); // convert to timestamps
        $datetime2 = strtotime($policyTerm->term_end_date); // convert to timestamps
        $diff_in_days_main = (int) (($datetime2 - $datetime1) / 86400) + 1;

        // save coverage detail
        foreach ($this->policyCoverageDetail as $policyCoverageId => $policyCoverageData) {

            foreach ($policyCoverageData as $coverage_id => $coverageData) {
                if (
                    isset($coverageData['coverage_value']) ||
                    isset($coverageData['limit_id']) ||
                    isset($coverageData['coverage_value_string']) ||
                    isset($coverageData['ratefactor_value']) ||
                    isset($coverageData['ratefactor_value_check'])
                ) {
                    if ($latestAction->transaction_type == 'ENDORSE') {
                        $existingDetail = DB::table('policy_coverage_detail')
                            ->join('policy_coverages', 'policy_coverage_detail.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                            ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id') // join risk_address
                            ->where('policy_actions.policy_id', $this->policy->id)
                            ->where('policy_coverage_detail.coverage_id', $coverage_id)
                            ->where('policy_coverage_detail.policy_coverage_id', $policyCoverageId)
                            ->whereNull('policy_actions.deleted_at')
                            ->whereNull('policy_coverages.deleted_at')
                            ->orderBy('policy_coverage_detail.id', 'desc')
                            ->select(
                                'policy_coverage_detail.*',
                                'risk_address.address_name as risk_address_name'
                            )
                            ->first();
                           // dd($existingDetail);
                        $currentValue = $coverageData['calculated_value'];
                        $previousActionIdCov = 0;
                        $endors_flag = 0;
                        $proRatePremium = 0;

                        // ---------------------------
                        // Case 1: New Coverage
                        // ---------------------------
                        if (!$existingDetail) {
                            $proRatePremium = $currentValue; // full premium for new coverage
                            $previousActionIdCov = $latestAction->id;
                            $endors_flag = 1;
                        } else {
                            $oldValue = $existingDetail->calculated_value;

                            // ---------------------------
                            // Case 2: Existing Coverage
                            // ---------------------------
                            if ($latestAction->id == $existingDetail->previousActionIdCov) {
                                // Same action, multiple edits → fetch from true previous action
                                $previousDetail = DB::table('policy_coverage_detail')
                                    ->join('policy_coverages', 'policy_coverage_detail.policy_coverage_id', '=', 'policy_coverages.id')
                                    ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                                    ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                                    ->where('policy_actions.policy_id', $this->policy->id)
                                    ->where('policy_coverage_detail.coverage_id', $coverage_id)
                                    ->where('risk_address.address_name', $existingDetail->risk_address_name) // <-- check risk address name matches
                                   // ->where('policy_coverage_detail.policy_coverage_id', $policyCoverageId)
                                    ->whereNull('policy_actions.deleted_at')
                                    ->whereNull('policy_coverages.deleted_at')
                                    ->where('policy_actions.id', $previousAction['id'])
                                    ->orderBy('policy_coverage_detail.id', 'desc')
                                    ->select('policy_coverage_detail.calculated_value')
                                    ->first();
                                $baseOldValue = $previousDetail->calculated_value ?? 0;
                            } else {
                                // Different action → use existing old value
                                $baseOldValue = $oldValue;
                            }
                            // ---------------------------
                            // Case 3: Compare Values
                            // ---------------------------
                            if ($currentValue > $baseOldValue) {
                                $proRatePremium = $currentValue - $baseOldValue;
                                $previousActionIdCov = $latestAction->id;
                                $endors_flag = 1;
                            } elseif ($currentValue < $baseOldValue) {
                                $proRatePremium = $currentValue - $baseOldValue; // negative difference allowed
                                $previousActionIdCov = $latestAction->id;
                                $endors_flag = 1;
                            } else {
                                // ---------------------------
                                // Case 4 + 5: No Change / Reverted
                                // ---------------------------
                                $proRatePremium = 0;
                                $previousActionIdCov = $existingDetail->previousActionIdCov;
                                $endors_flag = $existingDetail->endors_flag;
                            }
                        }

                        // Save final result in coverage data
                        $coverageData['pro_rate_premium'] = $proRatePremium;
                    }

                    // ---------------------------
                    // Save / Update Record
                    // ---------------------------
                    $ratefactor_value = $coverageData['ratefactor_value'] ?? null;

                    if ($ratefactor_value !== null) {
                        // If numeric with commas, remove commas
                        if (is_numeric(str_replace(',', '', $ratefactor_value))) {
                            $ratefactor_value = str_replace(',', '', $ratefactor_value);
                        }
                        // else it's a normal string → keep it unchanged
                    }
                    // Endorsement fields: real values on ENDORSE, 0 on new business / other types
                    $endorseFields = [
                        'pro_rate_premium' => 0,
                        'endors_flag' => 0,
                    ];
                    if ($latestAction->transaction_type == 'ENDORSE') {
                        $endorseFields = [
                            'pro_rate_premium' => $coverageData['pro_rate_premium'] ?? 0,
                            'endors_flag' => $endors_flag ?? 0,
                        ];
                    }
                    PolicyCoverageDetail::withTrashed()->updateOrCreate(
                        [
                            'policy_coverage_id' => $policyCoverageId,
                            'coverage_id' => $coverage_id
                        ],
                        [
                            'coverage_value' => $coverageData['coverage_value'] ?? 0,
                            'coverage_value_string' => $coverageData['coverage_value_string'] ?? null,
                            'limit_id' => $coverageData['limit_id'] ?? null,
                            'ratefactor_type' => $coverageData['ratefactor_type'] ?? null,
                            'ratefactor_AnnualWages' => $coverageData['ratefactor_AnnualWages'] ?? null,
                            'ratefactor_deposit_min_pre' => $coverageData['ratefactor_deposit_min_pre'] ?? null,
                            'ratefactor_value' => $ratefactor_value,
                            'ratefactor_value_check' => $coverageData['ratefactor_value_check'] ?? null,
                            'discount_surcharge' => $coverageData['discount_surcharge'] ?? 0,
                            'discount_surcharge_type' => $coverageData['discount_surcharge_type'] ?? 0,
                            'discount_surcharge_value' => $coverageData['discount_surcharge_value'] ?? 0,
                            'rate' => $coverageData['rate'] ?? 0,
                            'calculated_value' => $coverageData['calculated_value'] ?? 0,
                            'previousActionIdCov' => $previousActionIdCov ?? 0,
                            ...$endorseFields,
                            'deleted_at' => null,
                        ]
                    );
                }


                if (isset($this->property_business_being) && $this->property_business_being != '') {
                    DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $policyCoverageId)->where('coverage_id', '10')->where('row_type', 'NEW')
                        ->update([
                            'property_business_being' => $this->property_business_being,
                        ]);

                }

                if (isset($this->theft) && (count($this->theft) > 0)) {
                    $policyCov = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $policyCoverageId)->orderBy('id', 'desc')->first('coverage_id');
                    if (isset($policyCov) && $policyCov->coverage_id == 6) {

                        TheftGeneralQuestions::updateOrCreate(
                            [
                                'policy_coverage_id' => $policyCoverageId,
                                'coverage_id' => $policyCov->coverage_id
                            ],
                            [
                                'physical_protection_implemented' => $this->theft['physical_protection_implemented'],
                                'premises_alarmed' => $this->theft['premises_alarmed'],
                                'subscribe_armed_security' => $this->theft['subscribe_armed_security'],
                                'security_company' => $this->theft['security_company'],
                                'maintenance_contract' => $this->theft['maintenance_contract'],
                                'alarmed_installed_date' => $this->theft['alarmed_installed_date'],
                                'opening_closing_signals' => $this->theft['opening_closing_signals']
                            ]
                        );
                    }
                }
            }
        }
        $coverage_value = 0;
        $calculated_value = 0;
        $own_damage_minimum_amount = 0;
        $windscreen_minimum_amount = 0;
        $loss_of_keys_minimum_amount = 0;
        $premium_wreckage_removal = 0;
        $premium_window_glass = 0;
        $premium_locks_keys = 0;
        $premium_parts_accessories = 0;
        $premium_audio_accessories = 0;
        $premium_riot_strike = 0;
        $premium_car_hire_theft = 0;
        $premium_credit_shortfall = 0;
        $premium_insured_driver = 0;
        $premium_insured_family = 0;
        $premium_medical_expenses = 0;
        $premium_passenger_liability = 0;
        $premium_third_party_liability = 0;
        $premium_specified_accessories = 0;

        $premium_unorthorised_passanger_liability = 0;
        $premium_parking_facilities = 0;
        $premium_com_windscreen = 0;

        if (isset($this->motor['premium_unorthorised_passanger_liability'])) {
            $premium_unorthorised_passanger_liability = (float) str_replace(',', '', $this->motor['premium_unorthorised_passanger_liability']) ?? 0;
        } else {
            $premium_unorthorised_passanger_liability = 0;
        }

        if (isset($this->motor['premium_parking_facilities'])) {
            $premium_parking_facilities = (float) str_replace(',', '', $this->motor['premium_parking_facilities']) ?? 0;
        } else {
            $premium_parking_facilities = 0;
        }

        if (isset($this->motor['premium_com_windscreen'])) {
            $premium_com_windscreen = (float) str_replace(',', '', $this->motor['premium_com_windscreen']) ?? 0;
        } else {
            $premium_com_windscreen = 0;
        }

        if (isset($this->motorVehicleData['coverage_value'])) {
            $coverage_value = (float) str_replace(',', '', $this->motorVehicleData['coverage_value']) ?? 0;
        } else {
            $coverage_value = 0;
        }

        if (isset($this->motorVehicleData['calculated_value'])) {
            $calculated_value = (float) str_replace(',', '', $this->motorVehicleData['calculated_value']) ?? 0;
        } else {
            $calculated_value = 0;
        }

        if (isset($this->motor['premium_wreckage_removal'])) {
            $premium_wreckage_removal = (float) str_replace(',', '', $this->motor['premium_wreckage_removal']) ?? 0;
        } else {
            $premium_wreckage_removal = 0;
        }

        if (isset($this->motor['premium_window_glass'])) {
            $premium_window_glass = (float) str_replace(',', '', $this->motor['premium_window_glass']) ?? 0;
        } else {
            $premium_window_glass = 0;
        }

        if (isset($this->motor['premium_locks_keys'])) {
            $premium_locks_keys = (float) str_replace(',', '', $this->motor['premium_locks_keys']) ?? 0;
        } else {
            $premium_locks_keys = 0;
        }

        if (isset($this->motor['premium_parts_accessories'])) {
            $premium_parts_accessories = (float) str_replace(',', '', $this->motor['premium_parts_accessories']) ?? 0;
        } else {
            $premium_parts_accessories = 0;
        }

        if (isset($this->motor['premium_audio_accessories'])) {
            $premium_audio_accessories = (float) str_replace(',', '', $this->motor['premium_audio_accessories']) ?? 0;
        } else {
            $premium_audio_accessories = 0;
        }

        if (isset($this->motor['premium_riot_strike'])) {
            $premium_riot_strike = (float) str_replace(',', '', $this->motor['premium_riot_strike']) ?? 0;
        } else {
            $premium_riot_strike = 0;
        }

        if (isset($this->motor['premium_car_hire_theft'])) {
            $premium_car_hire_theft = (float) str_replace(',', '', $this->motor['premium_car_hire_theft']) ?? 0;
        } else {
            $premium_car_hire_theft = 0;
        }

        if (isset($this->motor['premium_credit_shortfall'])) {
            $premium_credit_shortfall = (float) str_replace(',', '', $this->motor['premium_credit_shortfall']) ?? 0;
        } else {
            $premium_credit_shortfall = 0;
        }

        if (isset($this->motor['premium_insured_driver'])) {
            $premium_insured_driver = (float) str_replace(',', '', $this->motor['premium_insured_driver']) ?? 0;
        } else {
            $premium_insured_driver = 0;
        }

        if (isset($this->motor['premium_insured_family'])) {
            $premium_insured_family = (float) str_replace(',', '', $this->motor['premium_insured_family']) ?? 0;
        } else {
            $premium_insured_family = 0;
        }

        if (isset($this->motor['premium_medical_expenses'])) {
            $premium_medical_expenses = (float) str_replace(',', '', $this->motor['premium_medical_expenses']) ?? 0;
        } else {
            $premium_medical_expenses = 0;
        }

        if (isset($this->motor['premium_passenger_liability'])) {
            $premium_passenger_liability = (float) str_replace(',', '', $this->motor['premium_passenger_liability']) ?? 0;
        } else {
            $premium_passenger_liability = 0;
        }

        if (isset($this->motor['premium_third_party_liability'])) {
            $premium_third_party_liability = (float) str_replace(',', '', $this->motor['premium_third_party_liability']) ?? 0;
        } else {
            $premium_third_party_liability = 0;
        }

        if (isset($this->motor['premium_specified_accessories'])) {
            $premium_specified_accessories = (float) str_replace(',', '', $this->motor['premium_specified_accessories']) ?? 0;
        } else {
            $premium_specified_accessories = 0;
        }

        if (isset($this->motor['own_damage_minimum_amount'])) {
            $own_damage_minimum_amount = (float) str_replace(',', '', $this->motor['own_damage_minimum_amount']) ?? 0;
        } else {
            $own_damage_minimum_amount = 0;
        }

        if (isset($this->motor['windscreen_minimum_amount'])) {
            $windscreen_minimum_amount = (float) str_replace(',', '', $this->motor['windscreen_minimum_amount']) ?? 0;
        } else {
            $windscreen_minimum_amount = 0;
        }

        if (isset($this->motor['loss_of_keys_minimum_amount'])) {
            $loss_of_keys_minimum_amount = (float) str_replace(',', '', $this->motor['loss_of_keys_minimum_amount']) ?? 0;
        } else {
            $loss_of_keys_minimum_amount = 0;
        }

        if (isset($this->motor['registration_no'])) {
            if (isset($this->motorVehicleData['coverage_value_main_' . $this->motor['registration_no'] . '_' . $this->motorVehicleData['policy_coverage_id']])) {
                $coverage_value_main = (float) str_replace(',', '', $this->motorVehicleData['coverage_value_main_' . $this->motor['registration_no'] . '_' . $this->motorVehicleData['policy_coverage_id']]) ?? 0;
            } else {
                $coverage_value_main = 0;
            }
            if (isset($this->motorVehicleData['calculated_value_main_' . $this->motor['registration_no'] . '_' . $this->motorVehicleData['policy_coverage_id']])) {
                $calculated_value_main = (float) str_replace(',', '', $this->motorVehicleData['calculated_value_main_' . $this->motor['registration_no'] . '_' . $this->motorVehicleData['policy_coverage_id']]) ?? 0;
            } else {
                $calculated_value_main = 0;
            }
        } else {
            $coverage_value_main = 0;
            $calculated_value_main = 0;
        }

        $motor_id = null;
        if (isset($this->motorVehicleData['policy_coverage_id']) && $this->motorVehicleData['policy_coverage_id'] != '') {
            $policyCovChk = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $this->motorVehicleData['policy_coverage_id'])->orderBy('id', 'desc')->first('coverage_id');

            if (isset($policyCovChk) && ($policyCovChk->coverage_id == 22 || $policyCovChk->coverage_id == 27)) {
                $past_calculate = DB::table('motor')
                    ->join('policy_coverages', 'motor.policy_coverage_id', '=', 'policy_coverages.id')
                    ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                    ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id') // join risk_address
                    ->where('policy_actions.policy_id', $this->policy->id)
                    ->where('motor.registration_no', $this->motor['registration_no'])
                    ->where('motor.policy_coverage_id', $this->motorVehicleData['policy_coverage_id'])
                    ->whereNull('policy_actions.deleted_at')
                    ->whereNull('motor.deleted_at')
                    ->whereNull('policy_coverages.deleted_at')
                    ->orderBy('motor.id', 'desc')
                    ->select('motor.*', 'risk_address.address_name as risk_address_name')
                    ->first();

                $currentValue = $calculated_value;   // from your logic
                $previousActionIdCov = 0;
                $endors_flag = 0;
                $pro_rate_premium_motor = 0;

                if ($latestAction->transaction_type == 'ENDORSE') {

                    // ---------------------------
                    // Case 1: New Motor Record
                    // ---------------------------
                    if (!$past_calculate) {
                        $pro_rate_premium_motor = $currentValue; // full premium
                        $previousActionIdCov = $latestAction->id;
                        $endors_flag = 1;
                    } else {
                        $oldValue = $past_calculate->calculated_value ?? 0;

                        // ---------------------------
                        // Case 2: Existing Motor Record
                        // ---------------------------
                        if ($latestAction->id == $past_calculate->previousActionIdCov) {
                            // Same action, multiple edits → fetch from true previous action
                            $previousDetail = DB::table('motor')
                                ->join('policy_coverages', 'motor.policy_coverage_id', '=', 'policy_coverages.id')
                                ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                                ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                                ->where('policy_actions.policy_id', $this->policy->id)
                                ->where('motor.registration_no', $this->motor['registration_no'])
                                ->where('risk_address.address_name', $past_calculate->risk_address_name) // check risk address name
                                ->whereNull('policy_actions.deleted_at')
                                ->whereNull('policy_coverages.deleted_at')
                                ->where('policy_actions.id', $previousAction['id'])
                                ->orderBy('motor.id', 'desc')
                                ->select('motor.calculated_value')
                                ->first();

                            $baseOldValue = $previousDetail->calculated_value ?? 0;
                        } else {
                            // Different action → use existing old value
                            $baseOldValue = $oldValue;
                        }

                        // ---------------------------
                        // Case 3: Compare Values
                        // ---------------------------
                        if ($currentValue > $baseOldValue) {
                            $pro_rate_premium_motor = $currentValue - $baseOldValue;
                            $previousActionIdCov = $latestAction->id;
                            $endors_flag = 1;
                        } elseif ($currentValue < $baseOldValue) {
                            $pro_rate_premium_motor = $currentValue - $baseOldValue; // allow negative
                            $previousActionIdCov = $latestAction->id;
                            $endors_flag = 1;
                        } else {
                            // ---------------------------
                            // Case 4 + 5: No Change / Reverted
                            // ---------------------------
                            $pro_rate_premium_motor = 0;
                            $previousActionIdCov = $past_calculate->previousActionIdCov;
                            $endors_flag = $past_calculate->endors_flag;
                        }
                    }
                } else {
                    // Non-ENDORSE transaction → just take full premium
                    $pro_rate_premium_motor = $currentValue;
                    $previousActionIdCov = $latestAction->id;
                    $endors_flag = 0;
                }

                // if(!isset($past_calculate) || isset($past_calculate['calculated_value']) && ($past_calculate['calculated_value']!=$calculated_value ||$past_calculate['calculated_value']==$calculated_value)){
                if (isset($this->motorVehicleData) && (count($this->motorVehicleData) > 0)) {
                    if (isset($this->motor['registration_no'])) {

                        // Endorsement fields: real values on ENDORSE, 0 on new business / other types
                        $endorseFields = [
                            'endors_flag' => 0,
                            'pro_rate_premium' => 0,
                        ];
                        if ($latestAction->transaction_type == 'ENDORSE') {
                            $endorseFields = [
                                'endors_flag' => $endors_flag ?? 0,
                                'pro_rate_premium' => $pro_rate_premium_motor ?? 0,
                            ];
                        }
                        $motors = Motor::updateOrCreate(
                            [
                                'policy_coverage_id' => $this->motorVehicleData['policy_coverage_id'],
                                'registration_no' => $this->motor['registration_no'],
                            ],
                            [
                                'use_main' => $this->motorVehicleData['use_main_' . $this->motor['registration_no'] . '_' . $this->motorVehicleData['policy_coverage_id']] ?? 0,
                                'type_of_cover_main' => $this->motorVehicleData['type_of_cover_main_' . $this->motor['registration_no'] . '_' . $this->motorVehicleData['policy_coverage_id']] ?? 0,
                                'coverage_value_main' => $coverage_value_main,
                                'calculated_value_main' => $calculated_value_main,
                                'vehicle_name' => $this->motorVehicleData['vehicle_name'] ?? '',
                                'coverage_value' => $coverage_value,
                                'calculated_value' => $calculated_value,
                                'previousActionIdCov' => $previousActionIdCov ?? 0,
                                ...$endorseFields,
                                'estimated_value' => $this->motor['estimated_value'] ?? null,
                                'use' => $this->motor['use'] ?? null,
                                'make' => $this->motor['make'] ?? null,
                                'model' => $this->motor['model'] ?? null,
                                'engine_number' => $this->motor['engine_number'] ?? null,
                                'chassis_number' => $this->motor['chassis_number'] ?? null,
                                'tracking_device' => $this->motor['tracking_device'] ?? null,
                                'security_features' => $this->motor['security_features'] ?? null,
                                'type_of_cover' => $this->motorVehicleData['type_of_cover_main_' . $this->motor['registration_no'] . '_' . $this->motorVehicleData['policy_coverage_id']] ?? null,

                                // extention
                                'unorthorised_passanger_liability' => $this->motor['unorthorised_passanger_liability'] ?? 0,
                                'parking_facilities' => $this->motor['parking_facilities'] ?? 0,
                                'com_windscreen' => $this->motor['com_windscreen'] ?? 0,

                                'wreckage_removal' => $this->motor['wreckage_removal'] ?? 0,
                                'window_glass' => $this->motor['window_glass'] ?? 0,
                                'locks_keys' => $this->motor['locks_keys'] ?? 0,
                                'parts_accessories' => $this->motor['parts_accessories'] ?? 0,
                                'audio_accessories' => $this->motor['audio_accessories'] ?? 0,
                                'riot_strike' => $this->motor['riot_strike'] ?? 0,
                                'car_hire_theft' => $this->motor['car_hire_theft'] ?? 0,
                                'credit_shortfall' => $this->motor['credit_shortfall'] ?? 0,
                                'insured_driver' => $this->motor['insured_driver'] ?? 0,
                                'insured_family' => $this->motor['insured_family'] ?? 0,
                                'medical_expenses' => $this->motor['medical_expenses'] ?? 0,
                                'passenger_liability' => $this->motor['passenger_liability'] ?? 0,
                                'third_party_liability' => $this->third_party_liability ?? 0,
                                'specified_accessories' => $this->motor['specified_accessories'] ?? 0,

                                // extention premium
                                'premium_unorthorised_passanger_liability' => $premium_unorthorised_passanger_liability,
                                'premium_parking_facilities' => $premium_parking_facilities,
                                'premium_com_windscreen' => $premium_com_windscreen,

                                'premium_wreckage_removal' => $premium_wreckage_removal,
                                'premium_window_glass' => $premium_window_glass,
                                'premium_locks_keys' => $premium_locks_keys,
                                'premium_parts_accessories' => $premium_parts_accessories,
                                'premium_audio_accessories' => $premium_audio_accessories,
                                'premium_riot_strike' => $premium_riot_strike,
                                'premium_car_hire_theft' => $premium_car_hire_theft,
                                'premium_credit_shortfall' => $premium_credit_shortfall,
                                'premium_insured_driver' => $premium_insured_driver,
                                'premium_insured_family' => $premium_insured_family,
                                'premium_medical_expenses' => $premium_medical_expenses,
                                'premium_passenger_liability' => $premium_passenger_liability,
                                'premium_third_party_liability' => $premium_third_party_liability,
                                'premium_specified_accessories' => $premium_specified_accessories,

                                // excess
                                'own_damage' => $this->motor['own_damage'] ?? null,
                                'own_damage_minimun_percent' => $this->motor['own_damage_minimun_percent'] ?? 0,
                                'own_damage_minimum_amount' => $own_damage_minimum_amount,
                                'windscreen' => $this->motor['windscreen'] ?? null,
                                'windscreen_minimun_percent' => $this->motor['windscreen_minimun_percent'] ?? 0,
                                'windscreen_minimum_amount' => $windscreen_minimum_amount,
                                'loss_of_keys' => $this->motor['loss_of_keys'] ?? null,
                                'loss_of_keys_minimun_percent' => $this->motor['loss_of_keys_minimun_percent'] ?? 0,
                                'loss_of_keys_minimum_amount' => $loss_of_keys_minimum_amount,
                            ]
                        );
                        $motor_id = $motors->id;
                        if ($motor_id != null) {
                            $latestAction = PolicyAction::where('id', $this->actionId)
                                ->orderBy('id', 'desc')
                                ->first();
                            // if( $latestAction->transaction_type=='ENDORSE'){
                            //     DB::table('motor')->where('id',$motor_id)->where('policy_coverage_id',$this->motorVehicleData['policy_coverage_id'])
                            //     ->update([
                            //         'endors_flag' => 1,
                            //     ]);
                            // }

                            if ($this->specified_items) {
                                foreach ($this->specified_items as $this->motorVehicleData['policy_coverage_id'] => $specifiedItemDatas) {


                                    foreach ($specifiedItemDatas as $specifiedItemData) {
                                        if (isset($specifiedItemData['selected_item']) && isset($specifiedItemData['sum_insured'])) {
                                            // PolicySpecifiedItem::create([
                                            //     'policy_coverage_id' => $this->motorVehicleData['policy_coverage_id'],
                                            //     'specified_coverage_id' => $specifiedItemData['selected_item'],
                                            //     'rate' => $this->specifiedItemsWithRate[$specifiedItemData['selected_item']],
                                            //     'sum_insured' => (float)(str_replace(',', '', ($specifiedItemData['sum_insured'] ?? '')) ?? 0),
                                            //     'motor_id' => $motor_id,    // added by Monika
                                            //     'action_id' => $specifiedItemData['action_id'] ?? $this->actionId,   // added by snehal
                                            // ]);     

                                            // added by snehal on 21-07-2025 for motor 
                                            $policyCoverageId = $this->motorVehicleData['policy_coverage_id'];

                                            foreach ($specifiedItemDatas as $specifiedItemData) {
                                                $where = [
                                                    'policy_coverage_id' => $policyCoverageId,
                                                    'action_id' => $specifiedItemData['action_id'] ?? $this->actionId,
                                                ];

                                                if (!empty($specifiedItemData['id'])) {
                                                    $where['id'] = $specifiedItemData['id'];
                                                }

                                                $item = PolicySpecifiedItem::withTrashed()->where($where)->first();
                                                $rate = $this->specifiedItemsWithRate[$specifiedItemData['selected_item']];
                                                $sum_insured = (float) str_replace(',', '', $specifiedItemData['sum_insured'] ?? '') ?? 0;
                                                $calculated_values = ($sum_insured * $rate) / 100;
                                                $data = [
                                                    'specified_coverage_id' => $specifiedItemData['selected_item'],
                                                    'rate' => $rate,
                                                    'sum_insured' => $sum_insured,
                                                    'calculated_value' => $calculated_values,
                                                    'motor_id' => $motor_id,
                                                    'endors_flag' => '1',

                                                ];
                                                // checking for id exist for perviously added data
                                                if (array_key_exists('id', $specifiedItemData)) {

                                                    if ($item) {
                                                        $item->update($data);
                                                    }
                                                } elseif (!array_key_exists('id', $specifiedItemData)) {
                                                    $existing = PolicySpecifiedItem::where([
                                                        ['specified_coverage_id', '=', $specifiedItemData['selected_item']],
                                                        ['policy_coverage_id', '=', $policyCoverageId],
                                                        ['motor_id', '=', $motor_id]
                                                    ])->first();
                                                    if (!$existing) {
                                                        PolicySpecifiedItem::create(array_merge(
                                                            $where,
                                                            $data
                                                        ));
                                                    }

                                                }

                                            }
                                        }
                                    }

                                }
                            }
                            //new code by Monika for note
                            // STORE note
                            //dd($this->policyCoverageNote);
                          if(isset($this->policyCoverageNote) && count($this->policyCoverageNote) > 0){
                            foreach ($this->policyCoverageNote as $key => $note) {
                                   if (is_array($note)) {
                                        $note = implode(' ', array_filter($note)); // or set null if you prefer
                                    }

                                if (str_contains($key, '_')) {                  // EDIT case
                                    [$motor_id, $coverage_id] = explode('_', $key);
                                } else {                                        // NEW case
                                    //$motor_id = $motor_id;
                                    $coverage_id = $key;
                                }

                                $policyCov = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $coverage_id)->orderBy('id', 'desc')->first('coverage_id');
                                if (($policyCov->coverage_id == 22 || $policyCov->coverage_id == 27)) {

                                    //dd($note,$motor_id,$coverage_id);
                                     if ($note === null || trim($note) === '') {

                                        if (isset($motor_id)) {
                                            DB::table('policy_coverage_notes')
                                                ->where('motor_id', $motor_id)
                                                ->delete();
                                        }

                                    }
                                    if ($note != null && trim($note) != "") {
                                        // Delete previous only if same motor exists
                                        if (isset($motor_id)) {
                                            DB::table('policy_coverage_notes')->where('motor_id', $motor_id)->delete();
                                        }

                                        PolicyCoverageNote::create([
                                            'motor_id' => $motor_id ?? null,
                                            'policy_coverage_id' => $coverage_id,
                                            'note' => $note,
                                        ]);
                                    }
                                }
                            }
                            }
                        }
                    }
                }
                //}
            }
        }
        // motorTradersExternal
        if (isset($this->motorTradersExternal['loss_or_damage_coverage_value'])) {
            $loss_or_damage_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['loss_or_damage_coverage_value']) ?? 0;
        } else {
            $loss_or_damage_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['loss_or_damage_calculated_value'])) {
            $loss_or_damage_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['loss_or_damage_calculated_value']) ?? 0;
        } else {
            $loss_or_damage_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['third_party_liability_coverage_value'])) {
            $third_party_liability_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['third_party_liability_coverage_value']) ?? 0;
        } else {
            $third_party_liability_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['third_party_liability_calculated_value'])) {
            $third_party_liability_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['third_party_liability_calculated_value']) ?? 0;
        } else {
            $third_party_liability_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['medical_benefits_coverage_value'])) {
            $medical_benefits_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['medical_benefits_coverage_value']) ?? 0;
        } else {
            $medical_benefits_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['medical_benefits_calculated_value'])) {
            $medical_benefits_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['medical_benefits_calculated_value']) ?? 0;
        } else {
            $medical_benefits_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['vehicle_lent_hire_coverage_value'])) {
            $vehicle_lent_hire_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['vehicle_lent_hire_coverage_value']) ?? 0;
        } else {
            $vehicle_lent_hire_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['vehicle_lent_hire_calculated_value'])) {
            $vehicle_lent_hire_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['vehicle_lent_hire_calculated_value']) ?? 0;
        } else {
            $vehicle_lent_hire_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['social_domestic_pleasure_coverage_value'])) {
            $social_domestic_pleasure_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['social_domestic_pleasure_coverage_value']) ?? 0;
        } else {
            $social_domestic_pleasure_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['social_domestic_pleasure_calculated_value'])) {
            $social_domestic_pleasure_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['social_domestic_pleasure_calculated_value']) ?? 0;
        } else {
            $social_domestic_pleasure_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['unauthoried_use_coverage_value'])) {
            $unauthoried_use_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['unauthoried_use_coverage_value']) ?? 0;
        } else {
            $unauthoried_use_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['unauthoried_use_calculated_value'])) {
            $unauthoried_use_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['unauthoried_use_calculated_value']) ?? 0;
        } else {
            $unauthoried_use_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['windscreen_coverage_value'])) {
            $windscreen_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['windscreen_coverage_value']) ?? 0;
        } else {
            $windscreen_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['windscreen_calculated_value'])) {
            $windscreen_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['windscreen_calculated_value']) ?? 0;
        } else {
            $windscreen_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['contigent_liability_coverage_value'])) {
            $contigent_liability_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['contigent_liability_coverage_value']) ?? 0;
        } else {
            $contigent_liability_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['contigent_liability_calculated_value'])) {
            $contigent_liability_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['contigent_liability_calculated_value']) ?? 0;
        } else {
            $contigent_liability_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['wreckage_removal_coverage_value'])) {
            $wreckage_removal_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['wreckage_removal_coverage_value']) ?? 0;
        } else {
            $wreckage_removal_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['wreckage_removal_calculated_value'])) {
            $wreckage_removal_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['wreckage_removal_calculated_value']) ?? 0;
        } else {
            $wreckage_removal_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['loss_of_key_coverage_value'])) {
            $loss_of_key_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['loss_of_key_coverage_value']) ?? 0;
        } else {
            $loss_of_key_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['loss_of_key_calculated_value'])) {
            $loss_of_key_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['loss_of_key_calculated_value']) ?? 0;
        } else {
            $loss_of_key_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['Loss_of_use_of_customer_coverage_value'])) {
            $Loss_of_use_of_customer_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['Loss_of_use_of_customer_coverage_value']) ?? 0;
        } else {
            $Loss_of_use_of_customer_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['Loss_of_use_of_customer_calculated_value'])) {
            $Loss_of_use_of_customer_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['Loss_of_use_of_customer_calculated_value']) ?? 0;
        } else {
            $Loss_of_use_of_customer_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['motor_cycle_motor_tricycle_coverage_value'])) {
            $motor_cycle_motor_tricycle_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['motor_cycle_motor_tricycle_coverage_value']) ?? 0;
        } else {
            $motor_cycle_motor_tricycle_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['motor_cycle_motor_tricycle_calculated_value'])) {
            $motor_cycle_motor_tricycle_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['motor_cycle_motor_tricycle_calculated_value']) ?? 0;
        } else {
            $motor_cycle_motor_tricycle_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['passanger_liability_respect_of_motor_coverage_value'])) {
            $passanger_liability_respect_of_motor_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['passanger_liability_respect_of_motor_coverage_value']) ?? 0;
        } else {
            $passanger_liability_respect_of_motor_coverage_value = 0;
        }
        if (isset($this->motorTradersExternal['passanger_liability_respect_of_motor_calculated_value'])) {
            $passanger_liability_respect_of_motor_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['passanger_liability_respect_of_motor_calculated_value']) ?? 0;
        } else {
            $passanger_liability_respect_of_motor_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['special_type_vehicle_coverage_value'])) {
            $special_type_vehicle_coverage_value = (float) str_replace(',', '', $this->motorTradersExternal['special_type_vehicle_coverage_value']) ?? 0;
        } else {
            $special_type_vehicle_coverage_value = 0;
        }

        if (isset($this->motorTradersExternal['special_type_vehicle_calculated_value'])) {
            $special_type_vehicle_calculated_value = (float) str_replace(',', '', $this->motorTradersExternal['special_type_vehicle_calculated_value']) ?? 0;
        } else {
            $special_type_vehicle_calculated_value = 0;
        }
        if (isset($this->motorTradersExternal['own_damage_minimun_percent'])) {
            $own_damage_minimun_percent = (float) str_replace(',', '', $this->motorTradersExternal['own_damage_minimun_percent']) ?? 0;
        } else {
            $own_damage_minimun_percent = 0;
        }
        if (isset($this->motorTradersExternal['own_damage_minimum_amount'])) {
            $own_damage_minimum_amount = (float) str_replace(',', '', $this->motorTradersExternal['own_damage_minimum_amount']) ?? 0;
        } else {
            $own_damage_minimum_amount = 0;
        }
        if (isset($this->motorTradersExternal['windscreen_minimun_percent'])) {
            $windscreen_minimun_percent = (float) str_replace(',', '', $this->motorTradersExternal['windscreen_minimun_percent']) ?? 0;
        } else {
            $windscreen_minimun_percent = 0;
        }
        if (isset($this->motorTradersExternal['windscreen_minimum_amount'])) {
            $windscreen_minimum_amount = (float) str_replace(',', '', $this->motorTradersExternal['windscreen_minimum_amount']) ?? 0;
        } else {
            $windscreen_minimum_amount = 0;
        }
        // motorTradersExternal
        if (isset($this->motorTradersExternal) && $this->motorTradersExternal != null && isset($this->motorTradersExternal['policy_coverage_id'])) {
            $previousActionIdCov = 0;
            $endors_flag = 0;
            $pro_rate_premium_ext = 0;
            if ($latestAction->transaction_type == 'ENDORSE') {
                $existingDetail = DB::table('motor_traders')
                    ->join('policy_coverages', 'motor_traders.policy_coverage_id', '=', 'policy_coverages.id')
                    ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                    ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                    ->where('policy_actions.policy_id', $this->policy->id)
                    ->where('motor_traders.policy_coverage_id', $this->motorTradersExternal['policy_coverage_id'])
                    ->whereNull('policy_actions.deleted_at')
                    ->whereNull('policy_coverages.deleted_at')
                    ->orderBy('motor_traders.id', 'desc')
                    ->select(
                        'motor_traders.*',
                        'risk_address.address_name as risk_address_name',
                        DB::raw('
                                (
                                    IFNULL(loss_or_damage_calculated_value, 0) +
                                    IFNULL(third_party_liability_calculated_value, 0) +
                                    IFNULL(medical_benefits_calculated_value, 0)
                                ) as calculated_value
                            ')
                    )
                    ->first();

                $currentValue = (float) $loss_or_damage_calculated_value
                    + (float) $third_party_liability_calculated_value
                    + (float) $medical_benefits_calculated_value;
                // ---------------------------
                // Case 1: New Coverage (no previous OR risk address changed)
                // ---------------------------
                if (!$existingDetail) {
                    $pro_rate_premium_ext = $currentValue;
                    $previousActionIdCov = $latestAction->id;
                    $endors_flag = 1;
                } else {
                    $oldValue = (float) $existingDetail->calculated_value;

                    // ---------------------------
                    // Case 2: Multiple edits in same action
                    // ---------------------------
                    if ($latestAction->id == $existingDetail->previousActionIdCov) {
                        $previousDetail = DB::table('motor_traders')
                            ->join('policy_coverages', 'motor_traders.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                            ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                            ->where('policy_actions.policy_id', $this->policy->id)
                            ->where('risk_address.address_name', $existingDetail->risk_address_name) // <-- check risk address name matches
                            ->whereNull('policy_actions.deleted_at')
                            ->whereNull('policy_coverages.deleted_at')
                            ->where('policy_actions.id', $previousAction['id'])
                            ->orderBy('motor_traders.id', 'desc')
                            ->select(
                                'risk_address.address_name as risk_address_name',
                                DB::raw('
                                        (
                                            IFNULL(loss_or_damage_calculated_value, 0) +
                                            IFNULL(third_party_liability_calculated_value, 0) +
                                            IFNULL(medical_benefits_calculated_value, 0)
                                        ) as calculated_value
                                    ')
                            )
                            ->first();

                        $baseOldValue = (float) ($previousDetail->calculated_value ?? 0);
                    } else {
                        $baseOldValue = $oldValue;
                    }

                    // ---------------------------
                    // Case 3: Compare Values
                    // ---------------------------
                    if ($currentValue > $baseOldValue) {
                        $pro_rate_premium_ext = $currentValue - $baseOldValue;
                        $previousActionIdCov = $latestAction->id;
                        $endors_flag = 1;
                    } elseif ($currentValue < $baseOldValue) {
                        $pro_rate_premium_ext = $currentValue - $baseOldValue; // negative allowed
                        $previousActionIdCov = $latestAction->id;
                        $endors_flag = 1;
                    } else {
                        // ---------------------------
                        // Case 4 + 5: No Change / Reverted
                        // ---------------------------
                        $pro_rate_premium_ext = 0;
                        $previousActionIdCov = $existingDetail->previousActionIdCov;
                        $endors_flag = $existingDetail->endors_flag;
                    }
                }
            }

            // Endorsement fields: real values on ENDORSE, 0 on new business / other types
            $endorseFields = [
                'endors_flag' => 0,
                'pro_rate_premium' => 0,
            ];
            if ($latestAction->transaction_type == 'ENDORSE') {
                $endorseFields = [
                    'endors_flag' => $endors_flag,
                    'pro_rate_premium' => $pro_rate_premium_ext ?? 0,
                ];
            }
            // 🔹 Finally update or create Motor Traders row
            MotorTraders::updateOrCreate(
                [
                    'policy_coverage_id' => $this->motorTradersExternal['policy_coverage_id'],
                ],
                [
                    'type_of_cover' => $this->motorTradersExternal['type_of_cover_external_main_' . $this->motorTradersExternal['policy_coverage_id']] ?? null,
                    'loss_or_damage_coverage_value' => $loss_or_damage_coverage_value,
                    'loss_or_damage_calculated_value' => $loss_or_damage_calculated_value,
                    'third_party_liability_coverage_value' => $third_party_liability_coverage_value,
                    'third_party_liability_calculated_value' => $third_party_liability_calculated_value,
                    'medical_benefits_coverage_value' => $medical_benefits_coverage_value,
                    'medical_benefits_calculated_value' => $medical_benefits_calculated_value,
                    'vehicle_lent_hire_coverage_value' => $vehicle_lent_hire_coverage_value,
                    'vehicle_lent_hire_calculated_value' => $vehicle_lent_hire_calculated_value,
                    'social_domestic_pleasure_coverage_value' => $social_domestic_pleasure_coverage_value,
                    'social_domestic_pleasure_calculated_value' => $social_domestic_pleasure_calculated_value,
                    'unauthoried_use_coverage_value' => $unauthoried_use_coverage_value,
                    'unauthoried_use_calculated_value' => $unauthoried_use_calculated_value,
                    'windscreen_coverage_value' => $windscreen_coverage_value,
                    'windscreen_calculated_value' => $windscreen_calculated_value,
                    'contigent_liability_coverage_value' => $contigent_liability_coverage_value,
                    'contigent_liability_calculated_value' => $contigent_liability_calculated_value,
                    'wreckage_removal_coverage_value' => $wreckage_removal_coverage_value,
                    'wreckage_removal_calculated_value' => $wreckage_removal_calculated_value,
                    'loss_of_key_coverage_value' => $loss_of_key_coverage_value,
                    'loss_of_key_calculated_value' => $loss_of_key_calculated_value,
                    'Loss_of_use_of_customer_coverage_value' => $Loss_of_use_of_customer_coverage_value,
                    'Loss_of_use_of_customer_calculated_value' => $Loss_of_use_of_customer_calculated_value,
                    'motor_cycle_motor_tricycle_coverage_value' => $motor_cycle_motor_tricycle_coverage_value,
                    'motor_cycle_motor_tricycle_calculated_value' => $motor_cycle_motor_tricycle_calculated_value,
                    'passanger_liability_respect_of_motor_coverage_value' => $passanger_liability_respect_of_motor_coverage_value,
                    'passanger_liability_respect_of_motor_calculated_value' => $passanger_liability_respect_of_motor_calculated_value,
                    'special_type_vehicle_coverage_value' => $special_type_vehicle_coverage_value,
                    'special_type_vehicle_calculated_value' => $special_type_vehicle_calculated_value,

                    // excess
                    'own_damage_minimun_percent' => $this->motorTradersExternal['own_damage_minimun_percent'] ?? 0,
                    'own_damage_minimum_amount' => $own_damage_minimum_amount,
                    'windscreen_minimun_percent' => $this->motorTradersExternal['windscreen_minimun_percent'] ?? 0,
                    'windscreen_minimum_amount' => $windscreen_minimum_amount,
                    'previousActionIdCov' => $previousActionIdCov,
                    ...$endorseFields,
                ]
            );
        }

        // // motorTradersInternal
        if (isset($this->motorTradersInternal) && $this->motorTradersInternal != null && isset($this->motorTradersInternal['policy_coverage_id'])) {
            $previousActionIdCov = 0;
            $endors_flag = 0;
            $pro_rate_premium_int = 0;

            if ($latestAction->transaction_type == 'ENDORSE') {
                $existingDetail = DB::table('motor_traders_internal')
                    ->join('policy_coverages', 'motor_traders_internal.policy_coverage_id', '=', 'policy_coverages.id')
                    ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                    ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                    ->where('policy_actions.policy_id', $this->policy->id)
                    ->where('motor_traders_internal.policy_coverage_id', $this->motorTradersInternal['policy_coverage_id'])
                    ->whereNull('policy_actions.deleted_at')
                    ->whereNull('policy_coverages.deleted_at')
                    ->orderBy('motor_traders_internal.id', 'desc')
                    ->select(
                        'motor_traders_internal.*',
                        'risk_address.address_name as risk_address_name',
                        DB::raw('
                            (
                                IFNULL(loss_or_damage_calculated_value, 0) +
                                IFNULL(third_party_liability_calculated_value, 0) +
                                IFNULL(medical_benefits_calculated_value, 0)
                            ) as calculated_value
                        ')
                    )
                    ->first();

                // Current calculated value
                $loss_or_damage_calculated_value = isset($this->motorTradersInternal['loss_or_damage_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['loss_or_damage_calculated_value']) : 0;
                $third_party_liability_calculated_value = isset($this->motorTradersInternal['third_party_liability_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['third_party_liability_calculated_value']) : 0;
                $medical_benefits_calculated_value = isset($this->motorTradersInternal['medical_benefits_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['medical_benefits_calculated_value']) : 0;

                $currentValue = $loss_or_damage_calculated_value + $third_party_liability_calculated_value + $medical_benefits_calculated_value;

                // ---------------------------
                // Case 1: New Coverage (no previous OR risk address changed)
                // ---------------------------
                if (!$existingDetail) {
                    $pro_rate_premium_int = $currentValue;
                    $previousActionIdCov = $latestAction->id;
                    $endors_flag = 1;
                } else {
                    $oldValue = (float) $existingDetail->calculated_value;

                    // ---------------------------
                    // Case 2: Multiple edits in same action
                    // ---------------------------
                    if ($latestAction->id == $existingDetail->previousActionIdCov) {
                        $previousDetail = DB::table('motor_traders_internal')
                            ->join('policy_coverages', 'motor_traders_internal.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                            ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                            ->where('policy_actions.policy_id', $this->policy->id)
                            ->where('risk_address.address_name', $existingDetail->risk_address_name) // match risk address
                            ->whereNull('policy_actions.deleted_at')
                            ->whereNull('policy_coverages.deleted_at')
                            ->where('policy_actions.id', $previousAction['id'])
                            ->orderBy('motor_traders_internal.id', 'desc')
                            ->select(
                                'risk_address.address_name as risk_address_name',
                                DB::raw('
                                    (
                                        IFNULL(loss_or_damage_calculated_value, 0) +
                                        IFNULL(third_party_liability_calculated_value, 0) +
                                        IFNULL(medical_benefits_calculated_value, 0)
                                    ) as calculated_value
                                ')
                            )
                            ->first();

                        $baseOldValue = (float) ($previousDetail->calculated_value ?? 0);
                    } else {
                        $baseOldValue = $oldValue;
                    }

                    // ---------------------------
                    // Case 3: Compare Values
                    // ---------------------------
                    if ($currentValue > $baseOldValue) {
                        $pro_rate_premium_int = $currentValue - $baseOldValue;
                        $previousActionIdCov = $latestAction->id;
                        $endors_flag = 1;
                    } elseif ($currentValue < $baseOldValue) {
                        $pro_rate_premium_int = $currentValue - $baseOldValue; // negative allowed
                        $previousActionIdCov = $latestAction->id;
                        $endors_flag = 1;
                    } else {
                        // ---------------------------
                        // Case 4 + 5: No Change / Reverted
                        // ---------------------------
                        $pro_rate_premium_int = 0;
                        $previousActionIdCov = $existingDetail->previousActionIdCov;
                        $endors_flag = $existingDetail->endors_flag;
                    }
                }
            }

            // Endorsement fields: real values on ENDORSE, 0 on new business / other types
            $endorseFields = [
                'endors_flag' => 0,
                'pro_rate_premium' => 0,
            ];
            if ($latestAction->transaction_type == 'ENDORSE') {
                $endorseFields = [
                    'endors_flag' => $endors_flag,
                    'pro_rate_premium' => $pro_rate_premium_int,
                ];
            }
            // 🔹 Finally update or create Motor Traders Internal row
            MotorTradersInternal::withTrashed()->updateOrCreate(
                [
                    'policy_coverage_id' => $this->motorTradersInternal['policy_coverage_id'],
                ],
                [
                    'type_of_cover' => $this->motorTradersInternal['type_of_cover_internal_main_' . $this->motorTradersInternal['policy_coverage_id']],
                    'loss_or_damage_coverage_value' => isset($this->motorTradersInternal['loss_or_damage_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['loss_or_damage_coverage_value']) : 0,
                    'loss_or_damage_calculated_value' => isset($this->motorTradersInternal['loss_or_damage_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['loss_or_damage_calculated_value']) : 0,
                    'third_party_liability_coverage_value' => isset($this->motorTradersInternal['third_party_liability_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['third_party_liability_coverage_value']) : 0,
                    'third_party_liability_calculated_value' => isset($this->motorTradersInternal['third_party_liability_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['third_party_liability_calculated_value']) : 0,
                    'medical_benefits_coverage_value' => isset($this->motorTradersInternal['medical_benefits_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['medical_benefits_coverage_value']) : 0,
                    'medical_benefits_calculated_value' => isset($this->motorTradersInternal['medical_benefits_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['medical_benefits_calculated_value']) : 0,
                    'vehicle_lent_hire_coverage_value' => isset($this->motorTradersInternal['vehicle_lent_hire_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['vehicle_lent_hire_coverage_value']) : 0,
                    'vehicle_lent_hire_calculated_value' => isset($this->motorTradersInternal['vehicle_lent_hire_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['vehicle_lent_hire_calculated_value']) : 0,
                    'social_domestic_pleasure_coverage_value' => isset($this->motorTradersInternal['social_domestic_pleasure_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['social_domestic_pleasure_coverage_value']) : 0,
                    'social_domestic_pleasure_calculated_value' => isset($this->motorTradersInternal['social_domestic_pleasure_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['social_domestic_pleasure_calculated_value']) : 0,
                    'unauthoried_use_coverage_value' => isset($this->motorTradersInternal['unauthoried_use_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['unauthoried_use_coverage_value']) : 0,
                    'unauthoried_use_calculated_value' => isset($this->motorTradersInternal['unauthoried_use_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['unauthoried_use_calculated_value']) : 0,
                    'windscreen_coverage_value' => isset($this->motorTradersInternal['windscreen_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['windscreen_coverage_value']) : 0,
                    'windscreen_calculated_value' => isset($this->motorTradersInternal['windscreen_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['windscreen_calculated_value']) : 0,
                    'contigent_liability_coverage_value' => isset($this->motorTradersInternal['contigent_liability_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['contigent_liability_coverage_value']) : 0,
                    'contigent_liability_calculated_value' => isset($this->motorTradersInternal['contigent_liability_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['contigent_liability_calculated_value']) : 0,
                    'wreckage_removal_coverage_value' => isset($this->motorTradersInternal['wreckage_removal_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['wreckage_removal_coverage_value']) : 0,
                    'wreckage_removal_calculated_value' => isset($this->motorTradersInternal['wreckage_removal_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['wreckage_removal_calculated_value']) : 0,
                    'loss_of_key_coverage_value' => isset($this->motorTradersInternal['loss_of_key_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['loss_of_key_coverage_value']) : 0,
                    'loss_of_key_calculated_value' => isset($this->motorTradersInternal['loss_of_key_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['loss_of_key_calculated_value']) : 0,
                    'Loss_of_use_of_customer_coverage_value' => isset($this->motorTradersInternal['Loss_of_use_of_customer_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['Loss_of_use_of_customer_coverage_value']) : 0,
                    'Loss_of_use_of_customer_calculated_value' => isset($this->motorTradersInternal['Loss_of_use_of_customer_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['Loss_of_use_of_customer_calculated_value']) : 0,
                    'motor_cycle_motor_tricycle_coverage_value' => isset($this->motorTradersInternal['motor_cycle_motor_tricycle_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['motor_cycle_motor_tricycle_coverage_value']) : 0,
                    'motor_cycle_motor_tricycle_calculated_value' => isset($this->motorTradersInternal['motor_cycle_motor_tricycle_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['motor_cycle_motor_tricycle_calculated_value']) : 0,
                    'passanger_liability_respect_of_motor_coverage_value' => isset($this->motorTradersInternal['passanger_liability_respect_of_motor_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['passanger_liability_respect_of_motor_coverage_value']) : 0,
                    'passanger_liability_respect_of_motor_calculated_value' => isset($this->motorTradersInternal['passanger_liability_respect_of_motor_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['passanger_liability_respect_of_motor_calculated_value']) : 0,
                    'special_type_vehicle_coverage_value' => isset($this->motorTradersInternal['special_type_vehicle_coverage_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['special_type_vehicle_coverage_value']) : 0,
                    'special_type_vehicle_calculated_value' => isset($this->motorTradersInternal['special_type_vehicle_calculated_value']) ? (float) str_replace(',', '', $this->motorTradersInternal['special_type_vehicle_calculated_value']) : 0,

                    // excess
                    'own_damage_minimun_percent' => $this->motorTradersInternal['own_damage_minimun_percent'] ?? 0,
                    'own_damage_minimum_amount' => isset($this->motorTradersInternal['own_damage_minimum_amount']) ? (float) str_replace(',', '', $this->motorTradersInternal['own_damage_minimum_amount']) : 0,
                    'windscreen_minimun_percent' => $this->motorTradersInternal['windscreen_minimun_percent'] ?? 0,
                    'windscreen_minimum_amount' => isset($this->motorTradersInternal['windscreen_minimum_amount']) ? (float) str_replace(',', '', $this->motorTradersInternal['windscreen_minimum_amount']) : 0,
                    ...$endorseFields,
                    'previousActionIdCov' => $previousActionIdCov,
                ]
            );
        }


        // save Extention detail
        foreach ($this->policyExtentionDetail as $policyCoverageId => $policyExtentionData) {


            foreach ($policyExtentionData as $extentions_id => $extentionData) {
                // Fetch main coverage record to get parent coverage ID
                $policyCoverage = DB::table('policy_coverages')
                    ->where('id', $policyCoverageId)
                    ->whereNull('deleted_at')
                    ->first(['id', 'coverage_id', 'policy_id']);

                if (!$policyCoverage) {
                    continue;
                }

                // Load Extention master data directly from database
                $extention = DB::table('extentions')
                    ->where('id', $extentions_id)
                    ->first();

                if (!$extention) {
                    continue;
                }

                if (
                    isset($extentionData['extention_coverage_value']) ||
                    isset($extentionData['extention_text_value']) ||
                    isset($extentionData['extention_limit_id'])
                ) {
                    $proRatePremium = 0;
                    $previousActionIdCov = 0;
                    $endors_flag = 0;

                    if ($latestAction->transaction_type == 'ENDORSE') {
                        $existingDetail = DB::table('policy_extention_detail')
                            ->join('policy_coverages', 'policy_extention_detail.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                            ->join('risk_address as ra1', 'policy_coverages.risk_address_id', '=', 'ra1.id')
                            ->where('policy_actions.policy_id', $this->policy->id)
                            ->where('policy_extention_detail.extentions_id', $extentions_id)
                            ->where('policy_extention_detail.policy_coverage_id', $policyCoverageId)
                            ->whereNull('policy_actions.deleted_at')
                            ->whereNull('policy_coverages.deleted_at')
                            ->orderBy('policy_extention_detail.id', 'desc')
                            ->select(
                                'policy_extention_detail.*',
                                'ra1.address_name as risk_address_name',
                                DB::raw('IFNULL(extention_calculated_value,0) as calculated_value')
                            )
                            ->first();

                        $currentValue = (float) ($extentionData['extention_calculated_value'] ?? 0);

                        // ---------------------------
                        // Case 1: New Coverage (no previous OR risk address changed)
                        // ---------------------------
                        if (!$existingDetail) {
                            $proRatePremium = $currentValue;
                            $previousActionIdCov = $latestAction->id;
                            $endors_flag = 1;
                        } else {
                            $oldValue = (float) $existingDetail->calculated_value;

                            // ---------------------------
                            // Case 2: Multiple edits in same action
                            // ---------------------------
                            if ($latestAction->id == $existingDetail->previousActionIdCov) {
                                $previousDetail = DB::table('policy_extention_detail')
                                    ->join('policy_coverages', 'policy_extention_detail.policy_coverage_id', '=', 'policy_coverages.id')
                                    ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                                    ->join('risk_address as ra2', 'policy_coverages.risk_address_id', '=', 'ra2.id')
                                    ->where('policy_actions.policy_id', $this->policy->id)
                                    ->where('policy_extention_detail.extentions_id', $extentions_id)
                                    ->where('policy_extention_detail.policy_coverage_id', $policyCoverageId)
                                    ->where('ra2.address_name', $existingDetail->risk_address_name) // check risk address name matches
                                    ->where('policy_actions.id', $previousAction['id'])
                                    ->whereNull('policy_actions.deleted_at')
                                    ->whereNull('policy_coverages.deleted_at')
                                    ->orderBy('policy_extention_detail.id', 'desc')
                                    ->select(
                                        'ra2.address_name as risk_address_name',
                                        DB::raw('IFNULL(extention_calculated_value,0) as calculated_value')
                                    )
                                    ->first();

                                $baseOldValue = (float) ($previousDetail->calculated_value ?? 0);
                            } else {
                                $baseOldValue = $oldValue;
                            }

                            // ---------------------------
                            // Case 3: Compare Values
                            // ---------------------------
                            if ($currentValue > $baseOldValue) {
                                $proRatePremium = $currentValue - $baseOldValue;
                                $previousActionIdCov = $latestAction->id;
                                $endors_flag = 1;
                            } elseif ($currentValue < $baseOldValue) {
                                $proRatePremium = $currentValue - $baseOldValue; // negative allowed
                                $previousActionIdCov = $latestAction->id;
                                $endors_flag = 1;
                            } else {
                                // ---------------------------
                                // Case 4 + 5: No Change / Reverted
                                // ---------------------------
                                $proRatePremium = 0;
                                $previousActionIdCov = $existingDetail->previousActionIdCov;
                                $endors_flag = $existingDetail->endors_flag;
                            }
                        }
                    }

                    // Endorsement fields: real values on ENDORSE, 0 on new business / other types
                    $endorseFields = [
                        'pro_rate_premium' => 0,
                        'endors_flag' => 0,
                    ];
                    if ($latestAction->transaction_type == 'ENDORSE') {
                        $endorseFields = [
                            'pro_rate_premium' => $proRatePremium ?? 0,
                            'endors_flag' => $endors_flag ?? 0,
                        ];
                    }
                    // 🔹 Save Extention Detail - Using direct DB for reliability
                    DB::table('policy_extention_detail')->updateOrInsert(
                        [
                            'policy_coverage_id' => $policyCoverageId,
                            'extentions_id' => $extentions_id
                        ],
                        [
                            's_ParentCoverageID' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? '',
                            's_SubCoverageID' => $extention->s_SubCoverageID ?? '',
                            's_ParentCoverageCode' => $extention->s_ParentCoverageCode ?? '',
                            'type' => $extention->type ?? '',
                            'extention_type' => $extention->extention_type ?? '',
                            's_CoverageName' => $extention->s_CoverageName ?? '',
                            's_CoverageCode' => $extention->s_CoverageCode ?? '',
                            's_ScreenName' => $extention->s_ScreenName ?? '',
                            's_CoverageDesc' => $extention->s_CoverageDesc ?? '',
                            's_ExtensionsGroupName' => $extention->s_ExtensionsGroupName ?? '',
                            'n_DisplaySequence' => $extention->n_DisplaySequence ?? '',
                            'extention_coverage_value' => $extentionData['extention_coverage_value'] ?? '',
                            'extention_text_value' => $extentionData['extention_text_value'] ?? null,
                            'extention_excess_min_value' => $extentionData['extention_excess_min_value'] ?? null,
                            'extention_excess_max_value' => $extentionData['extention_excess_max_value'] ?? null,
                            'extention_limit_id' => $extentionData['extention_limit_id'] ?? null,
                            'extention_discount_surcharge' => $extentionData['extention_discount_surcharge'] ?? 0,
                            'extention_discount_surcharge_type' => $extentionData['extention_discount_surcharge_type'] ?? 0,
                            'extention_discount_surcharge_value' => $extentionData['extention_discount_surcharge_value'] ?? 0,
                            'extention_rate' => $extentionData['extention_rate'] ?? '',
                            'extention_calculated_value' => $extentionData['extention_calculated_value'] ?? '',
                            'extention_sum_insured' => $extentionData['extention_sum_insured'] ?? '',
                            'deleted_at' => null,
                            ...$endorseFields,
                            'previousActionIdCov' => $previousActionIdCov ?? 0,
                            'updated_at' => now()
                        ]
                    );
                }
            }
        }

        if (isset($policyCoverageId)) {
            $policyCov = DB::table(table: 'policy_coverages')->where('policy_id', $this->policy->id)->where('id', $policyCoverageId)->orderBy('id', 'desc')->first('coverage_id');
            if (($policyCov->coverage_id != 22 || $policyCov->coverage_id != 27)) {

                // save entity detail
                foreach ($this->policyCoverageEntity as $policyCoverageId => $entityData) {
                    PolicyCoverageEntity::PolicyCoverage($policyCoverageId)->delete();
                    foreach ($entityData as $entityType => $entityId) {
                        PolicyCoverageEntity::withTrashed()->updateOrCreate([
                            'policy_coverage_id' => $policyCoverageId,
                            'entity_type' => $entityType
                        ], [
                            'entity_id' => $entityId,
                            'deleted_at' => null
                        ]);
                    }
                }

                if (isset($policyCoverageId)) {
                    $policyCov = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $policyCoverageId)->orderBy('id', 'desc')->first('coverage_id');
                    if (($policyCov->coverage_id != 22 || $policyCov->coverage_id != 27)) {
                        // save notes
                        foreach ($this->policyCoverageNote ?? [] as $policyCoverageId => $note) {
                            PolicyCoverageNote::PolicyCoverage($policyCoverageId)->delete(); /// delete note
                            if ($note != null) {
                                PolicyCoverageNote::withTrashed()->updateOrCreate([
                                    'policy_coverage_id' => $policyCoverageId,
                                ], [
                                    'note' => $note,
                                    'deleted_at' => null
                                ]);

                                // DB::table('policy_coverages')->where('policy_id',$this->policy->id)->where('id',$policyCoverageId)
                                // ->update([
                                //             'covShow' => 1,
                                //         ]);
                                activity('Policy Coverage Note')
                                    ->performedOn($this->policy)
                                    ->causedBy(User::where('id', auth()->user()->id)->first())
                                    ->log('Policy Coverage Note Added');
                            }
                        }
                    }
                }

                foreach ($this->policyCoverageBenefits ?? [] as $policyCoverageId => $benefits_note) {
                    if (!empty(trim($benefits_note))) {
                        PolicyCoverageNote::withTrashed()->updateOrCreate([
                            'policy_coverage_id' => $policyCoverageId,
                        ], [
                            'benefits_note' => $benefits_note,
                            'deleted_at' => null
                        ]);
                    }
                }

                foreach ($this->policyCoverageMemorandaWarranty ?? [] as $policyCoverageId => $memoranda_warranty) {
                    if (!empty(trim($memoranda_warranty))) {
                        PolicyCoverageNote::withTrashed()->updateOrCreate([
                            'policy_coverage_id' => $policyCoverageId,
                        ], [
                            'memoranda_warranty' => $memoranda_warranty,
                            'deleted_at' => null
                        ]);
                    }
                }
                foreach ($this->policyCoverageCashWarranty ?? [] as $policyCoverageId => $cash_warranty) {
                    if (!empty(trim($cash_warranty))) {
                        PolicyCoverageNote::withTrashed()->updateOrCreate([
                            'policy_coverage_id' => $policyCoverageId,
                        ], [
                            'cash_warranty' => $cash_warranty,
                            'deleted_at' => null
                        ]);
                    }
                }

                //endorsements
                foreach ($this->policyCoverageEndorsements ?? [] as $policyCoverageId => $endorsements) {
                    if (!empty(trim($endorsements))) {
                        PolicyCoverageNote::withTrashed()->updateOrCreate([
                            'policy_coverage_id' => $policyCoverageId,
                        ], [
                            'endorsements' => $endorsements,
                            'deleted_at' => null
                        ]);
                    }
                }

                // Burglar Warranty

                foreach ($this->policyCoverageBurglarWarranty ?? [] as $policyCoverageId => $burglar_warranty) {
                    if (!empty(trim($burglar_warranty))) {
                        PolicyCoverageNote::withTrashed()->updateOrCreate([
                            'policy_coverage_id' => $policyCoverageId,
                        ], [
                            'burglar_warranty' => $burglar_warranty,
                            'deleted_at' => null
                        ]);
                    }
                }
            }
        }

        // save specified coverage
        // $policyCov = DB::table('policy_coverages')->where('id',$policyCoverageId)->where('id',$policyCoverageId)->orderBy('id','desc')->first('coverage_id');

        // $this->specified_items = PolicySpecifiedItem::where('policy_coverage_id', $policyCoverageId)->get();

        if ($this->specified_items) {

            foreach ($this->specified_items as $policyCoverageId => $specifiedItemDatas) {

                $policyCov = DB::table('policy_coverages')
                    ->where('id', $policyCoverageId)
                    ->first(['coverage_id']);

                // Only run logic if NOT 22 or 27
                if ($policyCov->coverage_id != 22 && $policyCov->coverage_id != 27) {

                    if ($latestAction->transaction_type == 'ENDORSE') {

                        // ---------------------------------------------
                        // STEP 1: Get existing items for old premium calc
                        // ---------------------------------------------
                        $existingItems = PolicySpecifiedItem::where('policy_coverage_id', $policyCoverageId)
                            ->orderBy('id', 'desc')
                            ->get();

                        $previousPremium = $existingItems->sum(function ($item) {
                            return ($item->rate * $item->sum_insured) / 100;
                        });

                        // ---------------------------------------------
                        // STEP 2: Calculate new premium
                        // ---------------------------------------------
                        $newPremium = 0;

                        foreach ($specifiedItemDatas as $row) {
                            if (isset($row['selected_item']) && isset($row['sum_insured'])) {

                                $rate = $this->specifiedItemsWithRate[$row['selected_item']];
                                $si = (float) str_replace(',', '', $row['sum_insured']);

                                $newPremium += ($rate * $si) / 100;
                            }
                        }

                        $roundedPrevious = round($previousPremium, 2);
                        $roundedNew = round($newPremium, 2);

                        // ---------------------------------------------
                        // STEP 3: ENDORSE FLAG LOGIC (overall)
                        // ---------------------------------------------
                        if ($roundedPrevious == $roundedNew) {
                            $endorse_flag_master = 0;
                        } else {
                            $endorse_flag_master = 1;
                        }

                        // ---------------------------------------------
                        // STEP 4: LOOP EACH ROW (like coverage detail)
                        // ---------------------------------------------
                        foreach ($specifiedItemDatas as $row) {

                            $selected = $row['selected_item'];
                            $rate = round($this->specifiedItemsWithRate[$selected], 4);
                            $sumInsured = (float) str_replace(',', '', ($row['sum_insured'] ?? 0));
                            $calculatedNew = ($sumInsured * $rate) / 100;

                            //------------------------------------------
                            // Fetch existing record
                            //------------------------------------------
                            $existingDetail = DB::table('policy_specified_items')
                                ->join('policy_coverages', 'policy_specified_items.policy_coverage_id', '=', 'policy_coverages.id')
                                ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                                ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                                ->where('policy_actions.policy_id', $this->policy->id)
                                ->where('policy_specified_items.specified_coverage_id', $selected)
                                ->where('policy_specified_items.policy_coverage_id', $policyCoverageId)
                                ->whereNull('policy_actions.deleted_at')
                                ->whereNull('policy_coverages.deleted_at')
                                ->orderBy('policy_specified_items.id', 'desc')
                                ->select('policy_specified_items.*', 'risk_address.address_name')
                                ->first();

                            //------------------------------------------
                            // CASE 1: New Item
                            //------------------------------------------
                            if (!$existingDetail) {
                                $endorse_flag = "1";
                                $action_id = $latestAction->id;
                                $baseOld = 0;
                            } else {

                                $oldValue = (float) $existingDetail->calculated_value;

                                //------------------------------------------
                                // CASE 2: Same action → use TRUE previous action
                                //------------------------------------------
                                // dd($existingDetail->action_id,$latestAction->id,$oldValue);
                                if ($existingDetail->action_id == $latestAction->id) {

                                    $previousDetail = DB::table('policy_specified_items')
                                        ->join('policy_coverages', 'policy_specified_items.policy_coverage_id', '=', 'policy_coverages.id')
                                        ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                                        ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                                        ->where('policy_actions.policy_id', $this->policy->id)
                                        ->where('policy_specified_items.specified_coverage_id', $selected)
                                        # ->where('policy_specified_items.policy_coverage_id', $policyCoverageId)
                                        ->where('risk_address.address_name', $existingDetail->address_name)
                                        ->where('policy_actions.id', $previousAction['id'])
                                        ->whereNull('policy_actions.deleted_at')
                                        ->whereNull('policy_coverages.deleted_at')
                                        ->orderBy('policy_specified_items.id', 'desc')
                                        ->select('policy_specified_items.calculated_value')
                                        ->first();
                                    //                               
                                    $baseOld = (float) ($previousDetail->calculated_value ?? 0);

                                } else {
                                    //------------------------------------------
                                    // CASE 3: Old value from different action
                                    //------------------------------------------
                                    $baseOld = $oldValue;
                                }

                                //------------------------------------------
                                // CASE 4–5: compare old vs new
                                //------------------------------------------
                                //  dd(round($calculatedNew, 4),"--",round($baseOld, 4),$existingDetail->action_id,$latestAction->id);
                                if (round($calculatedNew, 4) != round($baseOld, 4)) {
                                    $endorse_flag = "1";
                                    $action_id = $latestAction->id;
                                } else {
                                    $endorse_flag = "0";
                                    $action_id = $existingDetail->action_id;
                                }
                            }

                            //------------------------------------------
                            // Save/Update Record
                            //------------------------------------------
                            $actionId = $row['action_id'] ?? $this->actionId;

                            $where = [
                                'policy_coverage_id' => $policyCoverageId,
                                'specified_coverage_id' => $selected,
                                'action_id' => $actionId
                            ];

                            $data = [
                                'rate' => $rate,
                                'sum_insured' => $sumInsured,
                                'calculated_value' => $calculatedNew,
                                'endors_flag' => $endorse_flag,
                                'action_id' => $this->actionId,
                                'specified_coverage_id' => $selected,

                            ];
                            //dd($data,$where );
                            if (!empty($row['id'])) {

                                PolicySpecifiedItem::withTrashed()
                                    ->where('id', $row['id'])
                                    ->update($data);

                            } else {

                                $already = PolicySpecifiedItem::where($where)->first();
                                if (!$already) {
                                    PolicySpecifiedItem::create(array_merge($where, $data));

                                }

                            }
                        }
                    }

                    // ---------------------------------------------
                    // NON-ENDORSE NORMAL CREATE/UPDATE
                    // ---------------------------------------------
                    else {

                        foreach ($specifiedItemDatas as $row) {

                            $actionId = $row['action_id'] ?? $this->actionId;

                            $rate = $this->specifiedItemsWithRate[$row['selected_item']];
                            $sumInsured = (float) str_replace(',', '', ($row['sum_insured'] ?? 0));
                            $calculatedNew = ($sumInsured * $rate) / 100;

                            $data = [
                                'specified_coverage_id' => $row['selected_item'],
                                'rate' => $rate,
                                'sum_insured' => $sumInsured,
                                'calculated_value' => $calculatedNew,
                                'endors_flag' => "0",
                                'action_id' => $actionId
                            ];

                            $where = [
                                'policy_coverage_id' => $policyCoverageId,
                                'specified_coverage_id' => $row['selected_item'],
                                'action_id' => $actionId
                            ];

                            if (!empty($row['id'])) {
                                $query = PolicySpecifiedItem::withTrashed()
                                    ->where('id', $row['id'])
                                    ->update($data);


                            } else {
                                $existing = PolicySpecifiedItem::where($where)->first();
                                if (!$existing) {
                                    PolicySpecifiedItem::create(array_merge($where, $data));


                                }
                            }
                        }
                    }
                }
            }
        }

        activity('Policy Coverage')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Policy Coverages Updated');

        $this->resetInputFields();

        if ($this->editmode) {
            $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Coverage Details Updated Successfully!']);
        } else {
            $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Coverage Details Added Successfully!']);
        }
        // Update policy_action table updated_at field for cheking v2 quote sheet action
        if ($this->actionId) {
            PolicyAction::where('id', $this->actionId)
                ->update(['updated_at' => now()]);
        }
        $this->emitUp('updateHasStatus');
    }

    public function addMainCoverage()
    {
        $policyAction = PolicyAction::where('id', $this->actionId)->first();
        if (($policyAction->transaction_type == 'ENDORSE' && $policyAction->transaction_reason == 'ADDCOVG')) {
            $this->policyCoverage->endors_flag = 1;
        }
        $this->policyCoverage->risk_address_id = $this->riskAddressId ?? $riskAddressId;
        $this->policyCoverage->term_id = $this->termId;
        $this->policyCoverage->action_id = $this->actionId;
        $this->policyCoverage->policy_id = $this->policy->id;

        // One-coverage-per-address rule for the 10 specialist coverages
        // (CAR/EAR/PAR/MB/MM/PI/D&O/Marine Once-Off/Marine Open/Travel).
        // Scoped to the customer's full portfolio.
        $onePerAddressLabel = PolicyCoverage::onePerAddressGroupLabel($this->policyCoverage->coverage_id ?? null);
        if ($onePerAddressLabel) {
            $conflict = PolicyCoverage::findOnePerAddressConflict(
                $this->policyCoverage->coverage_id,
                $this->policyCoverage->risk_address_id
            );
            if ($conflict) {
                $this->dispatchBrowserEvent('alert', [
                    'type'    => 'error',
                    'message' => "This risk address already has a {$onePerAddressLabel} coverage. Only one {$onePerAddressLabel} coverage is allowed per risk address.",
                ]);
                return;
            }
        }

        $this->validate();
        $this->policyCoverage->save();
        $this->policyCoverage = $this->policyCoverage->replicate();
    }

    // public function getPolicyCoveragesProperty(){
    //     return PolicyCoverage::with([
    //         'coverage' => function($query){
    //             $query->with(['subCoverage'=>function($q){
    //                 $q->where(function($q1) {
    //                     $q1->where('policy_id', null)
    //                       ->orWhere('policy_id', $this->policy->id);
    //                 })
    //                 ->orderBy('s_CoverageGroupName');
    //             }]);
    //             $query->with(['allExtention'=>function($e){
    //                 $e->orderBy('n_DisplaySequence','asc');
    //             }]);
    //             $query->with(['allBurglarAlarmWarranty'=>function($f){
    //                 $f->orderBy('s_CoverageName');
    //             }]);
    //         },
    //         'coverageDetail','extentionDetail'
    //     ])->select('id','risk_address_id','coverage_id','publicliability_date')
    //         ->Policy($this->policy->id)->Term($this->termId)->Action($this->actionId)
    //         ->orderBy('risk_address_id')
    //         ->get();
    // }

    public function getPolicyCoveragesProperty()
    {
        return PolicyCoverage::with([
            'coverage' => function ($query) {
                $query->with([
                    'subCoverage' => function ($q) {
                        $q->where(function ($q1) {
                            $q1->where('policy_id', null)
                                ->orWhere('policy_id', $this->policy->id);
                        })->orderBy('s_CoverageGroupName');
                    }
                ]);
                $query->with([
                    'allExtention' => function ($e) {
                        $e->orderBy('n_DisplaySequence', 'asc');
                    }
                ]);
                $query->with([
                    'allBurglarAlarmWarranty' => function ($f) {
                        $f->orderBy('s_CoverageName');
                    }
                ]);
            },
            'coverageDetail',
            'extentionDetail'
        ])
            ->select('id', 'risk_address_id', 'coverage_id', 'publicliability_date')
            ->when($this->riskAddressId, function ($query) {
                $query->where('risk_address_id', $this->riskAddressId);
            })
            ->Policy($this->policy->id)
            ->Term($this->termId)
            ->Action($this->actionId)
            ->orderBy('risk_address_id')
            ->get();
    }

    public function getAllRiskAddressProperty()
    {
        return RiskAddress::Policy($this->policy->id)->Action($this->dataShowForActionId)->get()->keyBy('id')->map(function ($riskaddress) {
            return [
                'id' => $riskaddress->id,
                'name' => $riskaddress->address_name
            ];
        });
    }

    public function getAllMainCoveragesProperty()
    {
        $data = ($this->policy?->product?->coverageMaster()
            ->select('tb_cvgpccoverages.id', 's_CoverageCode')?->with([
                    'specifiedCoverages' => function ($query) {
                        $query->EffectiveItemOnly();
                    }
                ])
            ->whereNotIn('product_coverage.id', [217, 218, 219, 220, 221, 222, 223, 224, 225, 226, 227, 228])
            ->orderBy('tb_cvgpccoverages.n_DisplaySequence', 'asc')
            ->get()
        ) ?? [];

        foreach ($data as $coverageData) {
            $specifiedItem = $coverageData->specifiedCoverages;
            $this->specifiedItems[$coverageData->id] = $specifiedItem->keyBy('id')->map(function ($specifiedItem) {
                return $specifiedItem->specified_name;
            });
            $specifiedItemsWithRate = $specifiedItem->keyBy('id')->map(function ($specifiedItem) {
                return $specifiedItem->rate;
            });
            foreach ($specifiedItemsWithRate as $id => $rate) {
                $this->specifiedItemsWithRate[$id] = $rate;
            }
        }
        return $data->keyBy('id')->map(function ($coverage) {
            return [
                'id' => $coverage->id,
                'name' => $coverage->s_CoverageCode
            ];
        });
    }

    public function getallVehicles()
    {
        return Vehicle::PolicyId($this->policy->id)->TermId($this->termId)->ActionId($this->actionId)->whereNull('deleted_at')->get();
    }

    public function getAllVehiclesProperty()
    {
        return Vehicle::select('vehiclePlate', 'id')->PolicyId($this->policy->id)->ActionId($this->dataShowForActionId)->whereNull('deleted_at')
            ->get()->pluck('vehiclePlate', 'id')->toArray();
    }

    public function getallDevicesProperty()
    {
        return PolicyCellPhone::Policy($this->policy->id)->action($this->dataShowForActionId)->get()->pluck('device_type', 'id')->toArray();
    }

    public function getallBeneficiariesProperty()
    {
        return PolicyBeneficiary::selectRaw("id,concat(first_name,' ',middle_name,' ',last_name) as name")->Policy($this->policy->id)->action($this->dataShowForActionId)->get()->pluck('name', 'id')->toArray();
    }

    // specified iteams code

    public function addSpecifiedRow($policyCoverageId)
    {
        $this->specifiedRow[$policyCoverageId][] = [];
    }
  public function removeRow($policyCoverageId, $key, $specifiedId = 0)
    {

        if ($specifiedId != 0 && $specifiedId != "") {

            // $query = PolicySpecifiedItem::where('id', $specifiedId)->delete();

            PolicySpecifiedItem::where('id', $specifiedId)
                ->update([
                    'deleted_at' => now(),
                    'action_id' => $this->actionId,
                    'endors_flag' => "1"
                ]);

        } else {
            unset($this->specifiedRow[$policyCoverageId][$key]);

            unset($this->specified_items[$policyCoverageId][$key]);
        }
    }


    public function backToStep2()
    {
        $this->emitUp('backToStep2');
    }

    public function handleDropdownChange($cover, $coverage_id)
    {
        $this->coverType = null;
        $this->coverTypeName = null;
        if ($coverage_id == 27) {
            if ($cover == 38) {
                $this->coverTypeName = 'Comprehensive';
                $this->coverType = CoverageMaster::with(['subCoverage', 'allExtention', 'allBurglarAlarmWarranty'])->where('id', 349)->first();
            } elseif ($cover == 39) {
                $this->coverTypeName = 'Third party only';
                $this->coverType = CoverageMaster::with(['subCoverage', 'allExtention', 'allBurglarAlarmWarranty'])->where('id', 351)->first();
                // $this->coverType = PolicyCoverage::with(['coverage','coverageDetail','extentionDetail'])->where('coverage_id',351)->first();
            } elseif ($cover == 40) {
                $this->coverTypeName = 'Third party, fire and theft';
                $this->coverType = CoverageMaster::with(['subCoverage', 'allExtention', 'allBurglarAlarmWarranty'])->where('id', 350)->first();
                // $this->coverType = PolicyCoverage::with(['coverage','coverageDetail','extentionDetail'])->where('coverage_id',350)->first();
            }
        }
        // dd($this->coverType);
    }

    public function handleSubVehicleChange($vehicleId, $coverTypeId, $sub_coverageId)
    {
        $this->vehicleSelected[$coverTypeId] = $vehicleId;
        $this->coverageSelected = $coverTypeId;
        $this->subCoverageSelected = $sub_coverageId;
        // $selectedVehicleId = $this->policyCoverageEntity[$coverTypeId]['Vehicle'];
        $this->getSubVehicleData($this->vehicleSelected[$coverTypeId], $this->coverageSelected, $this->subCoverageSelected);
        $this->getVechicleModel($this->selectedVehicleData);
        // $this->emit('fetchedDataUpdated', $this->selectedVehicleData);

    }

    public function getSubVehicleData($vehicleId, $policyCoverageId, $sub_coverageId)
    {
        $coverageCover = CoverageMaster::with(['subCoverage', 'allExtention', 'allBurglarAlarmWarranty'])->where('id', $policyCoverageId)->first();

        foreach ($coverageCover->subCoverage as $subCoverage) {
            $this->selectedVehicleData[$policyCoverageId][$subCoverage->id] = Vehicle::where('id', $vehicleId)->first(['id', 'make', 'model', 'year', 'chassisNo', 'vehiclePlate', 'engineNo', 'is_imported']);
        }
        $this->selectedVehicleData[$policyCoverageId][$sub_coverageId] = Vehicle::where('id', $vehicleId)->first(['id', 'make', 'model', 'year', 'chassisNo', 'vehiclePlate', 'engineNo', 'is_imported']);
    }

    public function repeateSubCoverage($sub_coverageId)
    {

        $sub_coverage = CoverageMaster::with('tbValidOptionsCoverage')->find($sub_coverageId);

        if ($sub_coverage) {
            // Create a new instance of the model
            $clonedModel = $sub_coverage->replicate();
            //$clonedModel->policy_id = $this->policy->id;
            $clonedModel->save();

            // Clone each relation
            if ($sub_coverage->tbValidOptionsCoverage) {
                $tbValidOptionsCoverage = $sub_coverage->tbValidOptionsCoverage->replicate();
                $tbValidOptionsCoverage->n_SourceOneFK = $clonedModel->id; // Assign new model ID
                $tbValidOptionsCoverage->n_ValidOptions_PK = null;
                $tbValidOptionsCoverage->save();
            }

            $this->render();
        }
    }
    public function removeSubCoverage($policyCoverageId, $sub_coverageId)
    {
        $coverageMaster = CoverageMaster::where('id', $sub_coverageId)->where('policy_id', $this->policy->id)->delete();
        $policyCoverageDetail = PolicyCoverageDetail::where('policy_coverage_id', $policyCoverageId)->where('coverage_id', $sub_coverageId)->delete();
        // $tbValidOptions = TbValidOptions::where('n_SourceOneFK',$sub_coverageId)->delete();
    }

    public function loadTheftData()
    {
        $existingData = TheftGeneralQuestions::where('policy_coverage_id', $this->policyCoverageID)->first();

        if ($existingData) {
            $this->theft = [
                'physical_protection_implemented' => $existingData->physical_protection_implemented,
                'premises_alarmed' => $existingData->premises_alarmed,
                'subscribe_armed_security' => $existingData->subscribe_armed_security,
                'security_company' => $existingData->security_company,
                'maintenance_contract' => $existingData->maintenance_contract,
                'alarmed_installed_date' => $existingData->alarmed_installed_date,
                'opening_closing_signals' => $existingData->opening_closing_signals
            ];
        }
    }

}
