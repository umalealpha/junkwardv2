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
use Illuminate\Support\Facades\Log;
use AlphaDirect\Customer;
use AlphaDirect\Models\CarCoverage as CarCoverageModel;
use AlphaDirect\Models\ParCoverage as ParCoverageModel;
use AlphaDirect\Models\EarCoverage as EarCoverageModel;
use AlphaDirect\Models\TravelCoverage as TravelCoverageModel;
use AlphaDirect\Models\ProfessionalIndemnityCoverage as ProfessionalIndemnityCoverageModel;
use AlphaDirect\Models\MedicalMalpracticeCoverage as MedicalMalpracticeCoverageModel;
use AlphaDirect\Models\DirectorsOfficersLiabilityCoverage as DOLiabilityModel;
use AlphaDirect\Models\MarineOnceOffCover as MarineOnceOffCoverModel;
use AlphaDirect\Models\MarineOpenCover as MarineOpenCoverModel;
use AlphaDirect\Models\MarineDirectorsOfficersCoverage as MarineDirectorsOfficersModel;
use AlphaDirect\Models\MachineryBreakdownCoverage as MachineryBreakdownModel;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use AlphaDirect\Models\RiskAddress as AlphaDirectRiskAddress;
class ManageCoverages extends Component
{
    use WithFileUploads;

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

    public $theft = [
        'physical_protection_implemented' => '',
        'premises_alarmed' => '',
        'subscribe_armed_security' => '',
        'security_company' => '',
        'maintenance_contract' => '',
        'alarmed_installed_date' => '',
        'opening_closing_signals' => ''
    ];
       // CONTRACTORSALLRISKS (CAR) Coverage
    public $carCoverage = [];
    public $carSection1Items = [];
    public $carSection2Items = [];
    public $carSection3Items = [];
    public $carSection3ContractWorks = [];
    public $carPlantListItems = [];
    public $carEndorsements = [];
    public $carPolicyWording;

    // PLANTALLRISKS (PAR) Coverage
    public $parCoverage = [];
    public $parPolicyWording;
    public $parInsuredItems = [];
    public $parSection2Items = [];

    // ERECTIONALLRISKSs (EAR) Coverage
    public $earCoverage = [];
    public $earPolicyWording;
    public $earSection1Items = [];
    public $earSection3Items = [];
    public $earEndorsements = [];

    // TRAVEL INSURANCE Coverage
    public $travelCoverage = [];
    public $travelBenefits = [];
    public $travelCustomBenefits = [];
    public $travelPolicyWording;
    // PROFESSIONAL INDEMNITY Coverage
    public $professionalIndemnity = [];
    public $professionalIndemnityDescriptions = [];
    public $professionalIndemnityInsuredPersons = [];
    public $professionalIndemnityExtensions = [];
    public $professionalIndemnityAdditionalExtensions = [];
    public $professionalIndemnityExcesses = [];
    public $professionalIndemnityLoaded = [];
    public $piPolicyWording;

    // MEDICAL MALPRACTICE Coverage
    public $medicalMalpractice = [];
    public $medicalMalpracticeExtensions = [];
    public $medicalMalpracticeSpecificDeductibles = [];
    public $medicalMalpracticeRiskDetails = [];
    public $medicalMalpracticePolicyWording;

    // DIRECTORS & OFFICERS LIABILITY Coverage
    public $directorsOfficersLiability = [];
    public $directorsOfficersLiabilityInsuringClauses = [];
    public $directorsOfficersLiabilityExtensions = [];
    public $directorsOfficersLiabilityCoverageExtensions = [];
    public $directorsOfficersLiabilityPolicyWording;

    // MARINE ONCE-OFF COVER
    public $marineOnceOffCover = [];
    public $marineOnceOffCoverClauses = [];
    public $marineOnceOffCoverPolicyWording;

    // MARINE OPEN COVER
    public $marineOpenCover = [];
    public $marineOpenCoverClauses = [];
    public $marineOpenCoverPolicyWording;
    public $marineOpenCoverMiscItems = [];

    // MARINE DIRECTORS & OFFICERS Coverage
    public $marineDirectorsOfficers = [];
    public $marineDirectorsOfficersSection1Items = [];
    public $marineDirectorsOfficersInsuredPersonsListing = [];
    public $marineDirectorsOfficersExtraCoverSection1 = [];
    public $marineDirectorsOfficersSection2Items = [];
    public $marineDirectorsOfficersExtraCoverSection2 = [];
    public $marineDirectorsOfficersSection3Items = [];
    public $marineDirectorsOfficersExtraCoverSection3 = [];
    public $marineDirectorsOfficersExtraCoverAllSections = [];
    public $marineDirectorsOfficersExcessDetails = [];
    public $marineDirectorsOfficersEndorsements = [];
    public $marineDirectorsOfficersMiscItems = [];
    public $marineDirectorsOfficersPolicyWording;

    // MACHINERY BREAKDOWN Coverage
    public $machineryBreakdown = [];
    public $machineryBreakdownPolicyWording;

    /**
     * Date fields stored on the car coverage table.
     * These are formatted for display (d/m/Y) and for DB storage (Y-m-d).
     *
     * @var array<int, string>
     */
    protected array $carCoverageDateFields = [
        'policy_inception_date',
        'policy_expiry_date',
        'today_date',
        'execution_date',
        'section3_period_insurance_from',
        'section3_period_insurance_to',
        'section3_scheduled_date_completion',
        'section3_scheduled_date_commencement',
    ];

    /**
     * Date fields stored on the par coverage table.
     * These are formatted for display (d/m/Y) and for DB storage (Y-m-d).
     *
     * @var array<int, string>
     */
    protected array $parCoverageDateFields = [
        'execution_date',
    ];

    /**
     * Date fields stored on the ear coverage table.
     * These are formatted for display (d/m/Y) and for DB storage (Y-m-d).
     *
     * @var array<int, string>
     */
    protected array $earCoverageDateFields = [
        'period_from',
        'period_to',
        'execution_date',
    ];

    /**
     * Date fields stored on the travel coverage table.
     * Date fields stored on the medical malpractice coverage table.
     * These are formatted for display (d/m/Y) and for DB storage (Y-m-d).
     *
     * @var array<int, string>
     */
    protected array $travelCoverageDateFields = [
        'effective_from',
        'expiry',
      ];
    protected array $medicalMalpracticeDateFields = [
        'policy_inception_date',
        'policy_expiry_date',
        'today_date',
        'anniversary_renewal_date',
        'retroactive_date',
    ];

    /**
     * Date fields stored on the directors & officers liability coverage table.
     */
    protected array $dolDateFields = [
        'inception_date',
        'expiry_date',
        'today_date',
        'backdated_continuity_date',
    ];

    /**
     * Date fields stored on the marine once-off cover table.
     */
    protected array $marineOnceOffCoverDateFields = [
        'today_date',
        'signing_date',
        'policy_period_from',
        'policy_period_to',
        'voyage_from',
        'voyage_to',
    ];

    /**
     * Date fields stored on the marine open cover table.
     */
    protected array $marineOpenCoverDateFields = [
        'policy_period_from',
        'policy_period_to',
        'today_date',
        'signing_date',
    ];

    /**
     * Date fields stored on the marine directors & officers coverage table.
     */
    protected array $marineDirectorsOfficersDateFields = [
        'inception_date',
        'expiry_date',
        'today_date',
        'backdated_continuity_date',
    ];

    /**
     * Date fields stored on the machinery breakdown coverage table.
     */
    protected array $machineryBreakdownDateFields = [
        'inception_date',
        'expiry_date',
        'today_date',
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
        PolicyCoveragesData::Where('policy_id', $this->policy->id)
            ->Where('risk_address', $riskaddress)->delete();
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

    }

    public function handleVehicleChange($vehicleId, $policyCoverageId, $sub_coverageId)
    {

        $this->vehicleSelected[$policyCoverageId] = $vehicleId;
        $this->coverageSelected = $policyCoverageId;
        $this->subCoverageSelected = $sub_coverageId;
        $this->getVehicleData($this->vehicleSelected[$policyCoverageId], $this->coverageSelected, $this->subCoverageSelected);
        $this->getVechicleModel($this->selectedVehicleData);

    }

    public function fetchedDataUpdated($data)
    {
        $this->selectedVehicleData = $data;
        $this->getVechicleModel($data);
    }

    public function getVehicleData($vehicleId, $policyCoverageId, $sub_coverageId)
    {

        foreach ($this->policyCoverages as $index => $policyCoverage) {
            if (isset($policyCoverage->coverage['subCoverage'])) {

                foreach ($policyCoverage->coverage['subCoverage'] as $subCoverage) {
                    $this->selectedVehicleData[$policyCoverageId][$subCoverage->id] = Vehicle::where('id', $vehicleId)->first(['id', 'make', 'model', 'year', 'chassisNo', 'vehiclePlate', 'engineNo', 'is_imported']);
                }
            }
        }

        // $this->selectedVehicleData[$policyCoverageId][$sub_coverageId] = Vehicle::where('id',$vehicleId)->first(['id','make','model','year','chassisNo','vehiclePlate','engineNo','is_imported']);
    }

    /**
     * Format coverage date fields to Y-m-d before persisting to the database.
     */
    protected function formatCoverageDatesForDb(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                // Handle invalid dates like "0000-00-00" or empty strings
                if (empty($data[$field]) || 
                    (is_string($data[$field]) && (strpos($data[$field], '0000-00-00') !== false || $data[$field] === '0000-00-00'))) {
                    $data[$field] = null;
                    continue;
                }
                
                $formatted = $this->formatDateForDb($data[$field]);
                if ($formatted) {
                    $data[$field] = $formatted;
                } else {
                    // If formatting fails, set to null
                    $data[$field] = null;
                }
            }
        }

        return $data;
    }

    /**
     * Format coverage date fields for display (d/m/Y) when loading from the database.
     */
    protected function formatCoverageDatesForDisplay(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                // Handle invalid dates like "0000-00-00"
                if (is_string($data[$field]) && (strpos($data[$field], '0000-00-00') !== false || $data[$field] === '0000-00-00')) {
                    $data[$field] = null;
                    continue;
                }
                
                if (!empty($data[$field])) {
                    $formatted = $this->formatDateForDisplay($data[$field]);
                    if ($formatted) {
                        $data[$field] = $formatted;
                    } else {
                        // If formatting returns null, set to null
                        $data[$field] = null;
                    }
                }
            }
        }

        return $data;
    }

    protected function formatDateForDb($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e2) {
                Log::warning('Unable to format date for DB', ['value' => $value, 'error' => $e2->getMessage()]);
                return null;
            }
        }
    }

    protected function formatDateForDisplay($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Handle invalid dates like "0000-00-00"
        if (is_string($value) && (strpos($value, '0000-00-00') !== false || $value === '0000-00-00')) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
            // Check if the parsed date is valid (not 0000-00-00)
            if ($parsed->year == 0) {
                return null;
            }
            return $parsed->format('d/m/Y');
        } catch (\Exception $e) {
            Log::warning('Unable to format date for display', ['value' => $value, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get policy term dates (Effective From and Effective To)
     * Returns array with 'term_start_date' and 'term_end_date' formatted as d/m/Y
     */
    protected function getPolicyTermDates(): ?array
    {
        if (!$this->termId) {
            return null;
        }

        $policyTerm = PolicyTerm::where('id', $this->termId)->first();
        
        if (!$policyTerm || !$policyTerm->term_start_date || !$policyTerm->term_end_date) {
            return null;
        }

        return [
            'term_start_date' => $this->formatDateForDisplay($policyTerm->term_start_date),
            'term_end_date' => $this->formatDateForDisplay($policyTerm->term_end_date),
        ];
    }

    /**
     * Calculate months between two dates
     * Returns the number of months (rounded up)
     */
    protected function calculateMonthsBetweenDates($startDate, $endDate): ?int
    {
        if (empty($startDate) || empty($endDate)) {
            return null;
        }

        try {
            // Parse dates - handle both d/m/Y and Y-m-d formats
            $start = null;
            $end = null;

            // Try d/m/Y format first
            try {
                $start = Carbon::createFromFormat('d/m/Y', $startDate);
                $end = Carbon::createFromFormat('d/m/Y', $endDate);
            } catch (\Exception $e) {
                // Try Y-m-d format
                try {
                    $start = Carbon::parse($startDate);
                    $end = Carbon::parse($endDate);
                } catch (\Exception $e2) {
                    Log::warning('Unable to parse dates for month calculation', [
                        'start' => $startDate,
                        'end' => $endDate,
                        'error' => $e2->getMessage()
                    ]);
                    return null;
                }
            }

            if (!$start || !$end) {
                return null;
            }

            // Calculate difference in months (rounded up)
            $months = $start->diffInMonths($end);
            
            // If there are additional days beyond full months, add 1
            $daysRemaining = $start->copy()->addMonths($months)->diffInDays($end);
            if ($daysRemaining > 0) {
                $months += 1;
            }

            return $months;
        } catch (\Exception $e) {
            Log::warning('Error calculating months between dates', [
                'start' => $startDate,
                'end' => $endDate,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get policy period months based on calculated months
     * Returns '12', '24', or '36' based on the number of months
     */
    protected function getPolicyPeriodMonths($months): ?string
    {
        if ($months === null) {
            return null;
        }

        if ($months <= 12) {
            return '12';
        } elseif ($months <= 24) {
            return '24';
        } elseif ($months <= 36) {
            return '36';
        } else {
            // For periods longer than 36 months, default to 36
            return '36';
        }
    }

    /**
     * Clean numeric/money values by removing commas, currency symbols, and formatting
     * Returns cleaned numeric value as string for database storage
     */
    protected function cleanNumericValue($value)
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return null;
        }

        // Convert to string first to handle any numeric types
        // If it's already a float that was incorrectly converted (like 10.0 from "10,000"),
        // we can't recover it, so we need to ensure it's a string BEFORE any conversion
        $value = (string) $value;
        
        // Remove commas, currency symbols, and whitespace
        // This regex keeps only digits, dots, and minus signs
        $cleaned = preg_replace('/[^\d.-]/', '', $value);
        
        // Return null if empty after cleaning, otherwise return the cleaned value
        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Clean numeric fields in coverage data before saving to database
     */
    protected function cleanNumericFieldsForDb(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = $this->cleanNumericValue($data[$field]);
            }
        }

        return $data;
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
    /**
     * Drop empty rows from a repeater array, or null when nothing is left.
     *
     * Returns the ARRAY, never a JSON string. Every caller feeds a column that
     * CarCoverage casts to 'array' (section1_items, section2_items,
     * section3_items, section3_contract_works, plant_list_items) and saves via
     * CarCoverageModel::updateOrCreate(), so Eloquent's cast does the encoding.
     * This used to return json_encode($filtered), which the cast then encoded a
     * SECOND time — storing "[{\"a\":1}]" (a JSON string scalar) instead of
     * [{"a":1}]. The CAR schedule PDF hid that with a double-decode guard, but
     * the V2 specialist form only accepts values starting with '[', so every
     * CAR items grid rendered EMPTY while the data sat in the column.
     * Do not re-add json_encode here.
     */
    public function jsonOrNull($data)
        {
            if (!is_array($data)) {
                return null;
            }

            // Remove empty rows like [], {}, null
            $filtered = array_values(array_filter($data, function ($item) {
                return is_array($item) && !empty(array_filter($item, fn($v) => $v !== '' && $v !== null));
            }));

            return empty($filtered) ? null : $filtered;
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

            $personalMotorData = Motor::where('policy_coverage_id', $motor_policy_coverage_id)->where('registration_no', $CovTypeVehicles['vehiclePlate'])->whereNull('deleted_at')->orderBy('id', 'asc')->first();
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
        'policyCoverageDetail.*.*.coverage_value_string' => '',
        'policyCoverageDetail.*.*.coverage_value' => '',

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
        'saveCarCoverage',
        'saveParCoverage',
        'saveEarCoverage',
        'saveProfessionalIndemnity',
        'saveMedicalMalpracticeCoverage',
        'saveDirectorsOfficersLiabilityCoverage',
        'saveMarineOnceOffCover',
        'saveMarineOpenCover',
        'saveMarineDirectorsOfficersCoverage',
        'saveMachineryBreakdownCoverage',
    ];
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
                $policyCoverageId = $policyCoverage->id;
                $allExtentionId = $allExtention->id;
                // Initialize extension array if not exists
                if (!isset($this->policyExtentionDetail[$policyCoverageId])) {
                    $this->policyExtentionDetail[$policyCoverageId] = [];
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$allExtentionId])) {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId] = [
                        'extention_coverage_value' => '',
                        'extention_text_value' => '',
                        'extention_limit_id' => '',
                        'extention_sum_insured' => '',
                        'extention_excess_min_value' => '',
                        'extention_excess_max_value' => '',
                        'extention_discount_surcharge' => '',
                        'extention_discount_surcharge_type' => '',
                        'extention_discount_surcharge_value' => '',
                        'extention_calculated_value' => '',
                        'extention_rate' => '',
                    ];
                }

                if ($allExtention->s_ScreenName == "Water leakage") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Television equipment maintenance") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Loss of money" && $allExtention->s_ParentCoverageCode != 'PERSONALALLRISKS') {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 3000;
                }
                if ($allExtention->s_ScreenName == "Refrigerator or deep freeze contents") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Veterinary fees") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 2000;
                }
                if ($allExtention->s_ScreenName == "Goods in the open") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Locks and keys") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Golfers hole-in-one" && $allExtention->s_ParentCoverageCode != 'PERSONALALLRISKS') {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 2000;
                }
                if ($allExtention->s_ScreenName == "Property of domestic employees") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Personal effects of guests") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Medical expenses") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "death by accident" || $allExtention->s_ScreenName == "Fatal injury - death by accident") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 10000;
                }
                if ($allExtention->s_ScreenName == "death by thieves or fire" || $allExtention->s_ScreenName == "Fatal injury - death by thieves or fire") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 15000;
                }
                if ($allExtention->s_ScreenName == "temporary repairs and other measures" || $allExtention->s_ScreenName == "Repairs and measures after a loss - temporary repairs and other measures") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "emergency accommodation" || $allExtention->s_ScreenName == "Repairs and measures after a loss - emergency accommodation") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 5000;
                }
                if ($allExtention->s_ScreenName == "Telephones") {
                    $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['extention_coverage_value'] = 2000;
                }

                $this->policyExtentionDetail[$policyCoverageId][$allExtentionId]['rate'] = $allExtention['rate'] ?? 0;
            }
            foreach ($policyCoverage->coverage['allBurglarAlarmWarranty'] as $allBurglarAlarmWarranty) {
                $policyCoverageId = $policyCoverage->id;
                $burglarAlarmId = $allBurglarAlarmWarranty->id;
                if (!isset($this->policyExtentionDetail[$policyCoverageId])) {
                    $this->policyExtentionDetail[$policyCoverageId] = [];
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$burglarAlarmId])) {
                    $this->policyExtentionDetail[$policyCoverageId][$burglarAlarmId] = [];
                }
                $this->policyExtentionDetail[$policyCoverageId][$burglarAlarmId]['rate'] = $allBurglarAlarmWarranty['rate'] ?? 0;
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
            $this->loadTravelCoverageData($policyCoverage->id);
            $this->loadProfessionalIndemnityData($policyCoverage->id);
            $this->loadMedicalMalpracticeData($policyCoverage->id);

            // Load existing extension data from database
            $existingExtensions = DB::table('policy_extention_detail')
                ->where('policy_coverage_id', $policyCoverage->id)
                ->get();

            $policyCoverageId = $policyCoverage->id;
            if (!isset($this->policyExtentionDetail[$policyCoverageId])) {
                $this->policyExtentionDetail[$policyCoverageId] = [];
            }

            foreach ($existingExtensions as $extData) {
                $extentionId = $extData->extentions_id;
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId] = [];
                }
                $this->policyExtentionDetail[$policyCoverageId][$extentionId] = [
                    'extention_coverage_value' => $extData->extention_coverage_value ?? '',
                    'extention_text_value' => $extData->extention_text_value ?? '',
                    'extention_limit_id' => $extData->extention_limit_id ?? '',
                    'extention_discount_surcharge' => $extData->extention_discount_surcharge ?? '',
                    'extention_discount_surcharge_type' => $extData->extention_discount_surcharge_type ?? '',
                    'extention_discount_surcharge_value' => $extData->extention_discount_surcharge_value ?? '',
                    'extention_calculated_value' => $extData->extention_calculated_value ?? '',
                    'extention_sum_insured' => $extData->extention_sum_insured ?? '',
                    'extention_excess_min_value' => $extData->extention_excess_min_value ?? '',
                    'extention_excess_max_value' => $extData->extention_excess_max_value ?? '',
                    'extention_rate' => $extData->extention_rate ?? '',
                ];
            }
        }
        if (isset($this->motorVehicleData['motor_policy_cov_id'])) {
            $this->motorVehicleData['policy_coverage_id'] = $this->motorVehicleData['motor_policy_cov_id'] ?? '';
        }


        return view('v2.livewire.policy.manage-coverages');
    }

    public function mount()
    {


        $this->third_party_liability = 2500000;
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        $this->policyCoverage = new PolicyCoverage();
        $this->policyCoverage->term_id = $this->termId;
        $this->policyCoverage->action_id = $this->actionId;
        // $this->policyCoverage->risk_address_id = $this->riskAddressId;
        $this->policyCoverage->policy_id = $this->policy->id;
        $this->publicliability_date = ['publicliability_date' => ''];
        $this->policyCoverage_id = ['policyCoverage_id' => ''];
        $this->AllMainCoverages;

        $this->vehicleData = Vehicle::where('policy_id', $this->policy->id)->first();

        $this->selectedVehicleData = $this->getVehicleData($this->vehicleSelected, $this->coverageSelected, $this->subCoverageSelected);

        foreach ($this->policyCoverages as $policyCoverag) {
            $this->loadTheftData($policyCoverag->id);
            $this->loadCarCoverageData($policyCoverag->id);
            $this->loadParCoverageData($policyCoverag->id);
            $this->loadEarCoverageData($policyCoverag->id);
            $this->loadTravelCoverageData($policyCoverag->id);
            $this->loadProfessionalIndemnityData($policyCoverag->id);
            $this->loadMedicalMalpracticeData($policyCoverag->id);
            $this->loadDirectorsOfficersLiabilityData($policyCoverag->id);
            $this->loadMarineOnceOffCoverData($policyCoverag->id);
            $this->loadMarineOpenCoverData($policyCoverag->id);
            $this->loadMarineDirectorsOfficersData($policyCoverag->id);
            $this->loadMachineryBreakdownData($policyCoverag->id);
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
                $personalMotorData = Motor::where('policy_coverage_id', $this->coverageIdForVehicle)->where('registration_no', $this->vehiclePlateNo)->whereNull('deleted_at')->orderBy('id', 'asc')->first();
                if (isset($personalMotorData)) {
                    $this->motorVehicleData['use_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->use_main;
                    $this->motorVehicleData['type_of_cover_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->type_of_cover_main;
                    $this->motorVehicleData['coverage_value_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->coverage_value_main;
                    $this->motorVehicleData['calculated_value_main_' . $this->vehiclePlateNo . '_' . $this->coverageIdForVehicle] = $personalMotorData->calculated_value_main;
                    //$this->motorVehicleData['policy_coverage_id'] = $this->coverageIdForVehicle;

                }
            }
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
                        'action_id' => $specifedItemData['action_id'] ?? $this->actionId,
                    ];
                    if (!isset($this->specifiedRow[$policyCoverage->id][$index])) {
                        $this->specifiedRow[$policyCoverage->id][$index] = [];
                    }
                }
            }

            $this->policyCoverageID = ['policyCoverageID' => $policyCoverage->id];
         
            // if($personalMotorData=='' && !isset($personalMotorData)){
            $this->PolicyCoveragesData = PolicyCoveragesData::Where('policyCoverageID', $policyCoverage->id)
                ->Where('policy_id', $this->policy->id)
                ->get();
            //}

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
            $this->selectedDataDropdownValue = isset($PolicyCoveragesDataDetails->cover_type) ? $PolicyCoveragesDataDetails->cover_type : 'null';
            $this->selectedOption = '';
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
            $policyCoverageId = $policyCoverage->id;
            if (!isset($this->policyExtentionDetail[$policyCoverageId])) {
                $this->policyExtentionDetail[$policyCoverageId] = [];
            }

            foreach ($policyCoverage->extentionDetail as $extentionDetail) {
                $extentionId = $extentionDetail->extentions_id;
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId] = [];
                }
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_sum_insured'] = $extentionDetail['extention_sum_insured'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_limit_id'] = $extentionDetail['extention_limit_id'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_text_value'] = $extentionDetail['extention_text_value'] ?? null;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_coverage_value'] = $extentionDetail['extention_coverage_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_excess_min_value'] = $extentionDetail['extention_excess_min_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_excess_max_value'] = $extentionDetail['extention_excess_max_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge'] = $extentionDetail['extention_discount_surcharge'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge_type'] = $extentionDetail['extention_discount_surcharge_type'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge_value'] = $extentionDetail['extention_discount_surcharge_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_calculated_value'] = $extentionDetail['extention_calculated_value'] ?? 0;
            }

            // Initialize empty arrays for all available master extensions without saved data
            // so they display in the edit form even with no values entered
            try {
                // Load all extensions for this coverage
                $allExtensions = Extention::where('s_ParentCoverageCode', $policyCoverage->coverage->s_CoverageCode)
                    ->where('type', 'Extention')
                    ->where('s_DISPLAYTOUSER', '1')
                    ->orderBy('n_DisplaySequence', 'asc')
                    ->get();

                $savedExtensionIds = $policyCoverage->extentionDetail->pluck('extentions_id')->toArray();
                foreach ($allExtensions as $extension) {
                    if (!in_array($extension->id, $savedExtensionIds)) {
                        $extensionId = $extension->id;
                        if (!isset($this->policyExtentionDetail[$policyCoverageId][$extensionId])) {
                            $this->policyExtentionDetail[$policyCoverageId][$extensionId] = [
                                'extention_sum_insured' => 0,
                                'extention_limit_id' => 0,
                                'extention_text_value' => null,
                                'extention_coverage_value' => 0,
                                'extention_excess_min_value' => 0,
                                'extention_excess_max_value' => 0,
                                'extention_discount_surcharge' => 0,
                                'extention_discount_surcharge_type' => 0,
                                'extention_discount_surcharge_value' => 0,
                                'extention_calculated_value' => 0,
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error if extension loading fails
                \Log::warning('Failed to load extensions in mount: ' . $e->getMessage());
            }
        }

        $this->policyAction = PolicyAction::where('id', $this->actionId)->first();

    }

    /**
     * Livewire hook that fires when a property is updated
     * - policy_period_months: set is_renewable to "No" when not 12
     * - rate fields: recalculate premium so response has both rate and premium (avoids revert after loading)
     */
    public function updated($propertyName)
    {
        // Check if the updated property is carCoverage policy_period_months
        if (preg_match('/^carCoverage\.(\d+)\.policy_period_months$/', $propertyName, $matches)) {
            $policyCoverageId = $matches[1];
            $policyPeriod = $this->carCoverage[$policyCoverageId]['policy_period_months'] ?? '';
            
            // If policy period is not 12, automatically set is_renewable to "No"
            if (!empty($policyPeriod) && $policyPeriod !== '12') {
                $this->carCoverage[$policyCoverageId]['is_renewable'] = 'No';
            }
        }

        // When rate is updated, recalculate premium so server response has both (avoids revert after loading)
        if (preg_match('/^carSection1Items\.(\d+)\.(\d+)\.rate$/', $propertyName, $matches)) {
            $this->calculateCarSection1ItemPremium($matches[1], (int) $matches[2]);
        }
        if (preg_match('/^carSection2Items\.(\d+)\.(\d+)\.rate$/', $propertyName, $matches)) {
            $this->calculateCarSection2ItemPremium($matches[1], (int) $matches[2]);
        }
        if (preg_match('/^carCoverage\.(\d+)\.section3_gross_profit_rate$/', $propertyName, $matches)) {
            $this->calculateCarSection3GrossProfitPremium($matches[1]);
        }
        if (preg_match('/^carCoverage\.(\d+)\.section3_increased_cost_rate$/', $propertyName, $matches)) {
            $this->calculateCarSection3IncreasedCostPremium($matches[1]);
        }
        if (preg_match('/^earSection1Items\.(\d+)\.(\d+)\.rate$/', $propertyName, $matches)) {
            $this->calculateEarSection1ItemPremium($matches[1], (int) $matches[2]);
        }
        if (preg_match('/^earSection3Items\.(\d+)\.(\d+)\.rate$/', $propertyName, $matches)) {
            $this->calculateEarSection3ItemPremium($matches[1], (int) $matches[2]);
        }
        if (preg_match('/^parInsuredItems\.(\d+)\.(\d+)\.rate$/', $propertyName, $matches)) {
            $this->calculateParItemPremium($matches[1], (int) $matches[2]);
        }
        if (preg_match('/^parSection2Items\.(\d+)\.(\d+)\.rate$/', $propertyName, $matches)) {
            $this->calculateParSection2ItemPremium($matches[1], (int) $matches[2]);
        }
        
        // Check if the updated property is earCoverage policy_period_months
        if (preg_match('/^earCoverage\.(\d+)\.policy_period_months$/', $propertyName, $matches)) {
            $policyCoverageId = $matches[1];
            $policyPeriod = $this->earCoverage[$policyCoverageId]['policy_period_months'] ?? '';
            
            // If policy period is not 12, automatically set is_renewable to "No"
            if (!empty($policyPeriod) && $policyPeriod !== '12') {
                $this->earCoverage[$policyCoverageId]['is_renewable'] = 'No';
            }
        }
        
        // Check if the updated property is parCoverage policy_period_months
        if (preg_match('/^parCoverage\.(\d+)\.policy_period_months$/', $propertyName, $matches)) {
            $policyCoverageId = $matches[1];
            $policyPeriod = $this->parCoverage[$policyCoverageId]['policy_period_months'] ?? '';
            
            // If policy period is not 12, automatically set is_renewable to "No"
            if (!empty($policyPeriod) && $policyPeriod !== '12') {
                $this->parCoverage[$policyCoverageId]['is_renewable'] = 'No';
            }
        }
    }

private function isBlankOrZero($value): bool
{
    if ($value === null || $value === '') {
        return true;
    }

    // Remove commas and spaces
    $normalized = str_replace(',', '', trim((string) $value));

    // Check numeric zero in all forms: 0, 0.0, 0.00, 00.00
    if (is_numeric($normalized) && (float) $normalized == 0.0) {
        return true;
    }

    return false;
}

    /**
     * Normalize extension data from form inputs into policyExtentionDetail
     * Ensures that all extension data is properly structured with composite keys
     * (policyCoverage_id_extention_id) for database persistence.
     */
    private function normalizeExtensionData()
    {
        // Ensure all policy coverages have their extensions properly mapped
        // NOTE: Only initialize FORM INPUT fields, NOT master data fields
        // Master data fields should be populated from extentions table during save
        foreach ($this->policyCoverages as $policyCoverage) {
            if (!isset($policyCoverage->coverage['allExtention'])) {
                continue;
            }

            foreach ($policyCoverage->coverage['allExtention'] as $extention) {
                $policyCoverageId = $policyCoverage->id;
                $extentionId = $extention->id;

                // Initialize if not exists
                if (!isset($this->policyExtentionDetail[$policyCoverageId])) {
                    $this->policyExtentionDetail[$policyCoverageId] = [];
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId] = [];
                }

                // Only initialize FORM INPUT fields, NOT master data fields
                // This prevents overwriting master data with empty strings
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_coverage_value'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_coverage_value'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_text_value'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_text_value'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_limit_id'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_limit_id'] = null;
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_sum_insured'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_sum_insured'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_excess_min_value'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_excess_min_value'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_excess_max_value'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_excess_max_value'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge_type'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge_type'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge_value'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_discount_surcharge_value'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_calculated_value'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['extention_calculated_value'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['coverage_value_string'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['coverage_value_string'] = '';
                }
                if (!isset($this->policyExtentionDetail[$policyCoverageId][$extentionId]['coverage_value'])) {
                    $this->policyExtentionDetail[$policyCoverageId][$extentionId]['coverage_value'] = '';
                }

                // DO NOT initialize master data fields here:
                // s_CoverageName, s_CoverageDesc, s_ExtensionsGroupName, n_DisplaySequence, etc.
                // These are populated from the extentions table during save operation
            }
        }
    }

    /**
     * COM/DOM endorsement pro-rata day factor.
     * factor = remainingDays(effective_from -> end of current billing period) / periodDays(by freq)
     * freq: 1=Monthly(30) 2=ThreeInstalments(122) 5=Quarterly(91) 3=Annual(365)
     */
    private function proRataDayFactor()
    {
        if (!isset($this->policy)) return 1.0;

        $periodDays = $this->periodDaysForFreq((int) ($this->policy->premium_freq ?? 3));
        if ($periodDays <= 0) return 1.0;

        $policyTerm = PolicyTerm::where('id', $this->termId)->first();
        if (!$policyTerm) return 1.0;

        $latestAction = PolicyAction::where('id', $this->actionId)->first();
        $effFrom = $latestAction->effective_from
            ?? ($latestAction->transaction_date ?? $policyTerm->term_start_date);

        try {
            $eff       = Carbon::parse($effFrom);
            $termStart = Carbon::parse($policyTerm->term_start_date);
            $termEnd   = Carbon::parse($policyTerm->term_end_date);
        } catch (\Throwable $e) {
            return 1.0;
        }

        if ($eff->lt($termStart)) $eff = $termStart->copy();
        if ($eff->gt($termEnd))   $eff = $termEnd->copy();

        $periodStart = $termStart->copy();
        while ($periodStart->copy()->addDays($periodDays)->lte($eff)) {
            $periodStart->addDays($periodDays);
        }
        $periodEnd = $periodStart->copy()->addDays($periodDays);
        if ($periodEnd->gt($termEnd)) $periodEnd = $termEnd->copy();

        $remaining = $eff->diffInDays($periodEnd) + 1;
        if ($remaining < 0) $remaining = 0;
        if ($remaining > $periodDays) $remaining = $periodDays;

        return round($remaining / $periodDays, 6);
    }

    private function periodDaysForFreq($freq)
    {
        switch ((int) $freq) {
            case 1: return 30;
            case 2: return 122;
            case 5: return 91;
            case 3:
            default: return 365;
        }
    }

    private function isComDomPolicy()
    {
        // 7,16,17 = Commercial variants; 8,18,19 = Domestic variants
        return in_array((int) ($this->policy->product_id ?? 0), [7, 8, 16,17,18,20,22,23,24], true);
    }

    public function submit()
    {
        // Log extension data for debugging
        Log::info('ManageCoverages::submit() - Extension data received', [
            'policyExtentionDetail_count' => count($this->policyExtentionDetail),
            'policyExtentionDetail_keys' => array_keys($this->policyExtentionDetail),
            'sample_extension' => array_slice($this->policyExtentionDetail, 0, 1)
        ]);

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
            'policyExtentionDetail.*.*.coverage_value_string' => 'nullable',
            'policyExtentionDetail.*.*.coverage_value' => 'nullable',
            'publicliability_date.*.*.publicliability_date' => 'required',

            // 'theft.physical_protection_implemented' => 'required|string',
            // 'theft.premises_alarmed' => 'required|in:Yes,No',
            // 'theft.subscribe_armed_security' => 'required_if:theft.premises_alarmed,Yes|in:Yes,No',
            // 'theft.security_company' => 'required_if:theft.subscribe_armed_security,Yes|string',
            // 'theft.maintenance_contract' => 'required_if:theft.subscribe_armed_security,Yes|in:Yes,No',
            // 'theft.alarmed_installed_date' => 'required_if:theft.premises_alarmed,Yes|date',
            // 'theft.opening_closing_signals' => 'required_if:theft.premises_alarmed,Yes|in:Yes,No'
 
            'carCoverage.*.*' => 'nullable',
            'carSection1Items.*.*.*' => 'nullable',
            'carSection2Items.*.*.*' => 'nullable',
            'carSection3Items.*.*.*' => 'nullable',
 
            // Plant All Risks (PAR) Coverage validation rules
            'parCoverage.*.*' => 'nullable',
            'parInsuredItems.*.*.*' => 'nullable',
            'parSection2Items.*.*.*' => 'nullable',

            // Erection All Risks (EAR) Coverage validation rules
            'earCoverage.*.*' => 'nullable',

            // Medical Malpractice Coverage validation rules
            'medicalMalpractice.*.*' => 'nullable',
            'medicalMalpracticeExtensions.*.*.*' => 'nullable',
            'medicalMalpracticeSpecificDeductibles.*.*.*' => 'nullable',
            'medicalMalpracticeRiskDetails.*.*.*' => 'nullable',
            'earSection1Items.*.*.*' => 'nullable',
            'earSection3Items.*.*.*' => 'nullable',
            'earEndorsements.*.*.*' => 'nullable',

            // Directors & Officers Liability validation rules
            'directorsOfficersLiability.*.*' => 'nullable',
            'directorsOfficersLiabilityInsuringClauses.*.*.*' => 'nullable',
            'directorsOfficersLiabilityExtensions.*.*.*' => 'nullable',
            'directorsOfficersLiabilityCoverageExtensions.*.*.*' => 'nullable',

            // Marine Once-Off Cover validation rules
            'marineOnceOffCover.*.*' => 'nullable',
            'marineOnceOffCoverClauses.*.*' => 'nullable',

            // Marine Open Cover validation rules
            'marineOpenCover.*.*' => 'nullable',
            'marineOpenCoverClauses.*.*' => 'nullable',
            'marineOpenCoverMiscItems.*.*.*' => 'nullable',

            // Marine Directors & Officers validation rules
            'marineDirectorsOfficers.*.*' => 'nullable',
            'marineDirectorsOfficersSection1Items.*.*.*' => 'nullable',
            'marineDirectorsOfficersInsuredPersonsListing.*.*.*' => 'nullable',
            'marineDirectorsOfficersExtraCoverSection1.*.*.*' => 'nullable',
            'marineDirectorsOfficersSection2Items.*.*.*' => 'nullable',
            'marineDirectorsOfficersExtraCoverSection2.*.*.*' => 'nullable',
            'marineDirectorsOfficersSection3Items.*.*.*' => 'nullable',
            'marineDirectorsOfficersExtraCoverSection3.*.*.*' => 'nullable',
            'marineDirectorsOfficersExtraCoverAllSections.*.*.*' => 'nullable',
            'marineDirectorsOfficersExcessDetails.*.*.*' => 'nullable',
            'marineDirectorsOfficersEndorsements.*' => 'nullable',
            'marineDirectorsOfficersMiscItems.*.*.*' => 'nullable',
        ];
        $this->validate();
        if (isset($this->policyCoverage_id['policyCoverage_id'])) {
         
            $policyCovChkPublicdate = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $this->policyCoverage_id['policyCoverage_id'])->orderBy('id', 'desc')->first('coverage_id');
     
          if (isset($policyCovChkPublicdate) && $policyCovChkPublicdate->coverage_id == 21) {

    $dateValue = $this->publicliability_date['publicliability_date'] ?? '';

    // CASE 1: Date is provided → validate format
    if ($dateValue !== '') {
        try {
            $date = Carbon::createFromFormat('d/m/Y', $dateValue);

            // strict validation (prevents 32/01/2025 etc.)
            if ($date->format('d/m/Y') !== $dateValue) {
                throw new \Exception('Invalid date');
            }

            // Update with valid date
            DB::table('policy_coverages')
                ->where('id', $this->policyCoverage_id['policyCoverage_id'])
                ->update([
                    'publicliability_date' => $date->format('Y-m-d'),
                ]);

        } catch (\Exception $e) {
            $this->dispatchBrowserEvent('alert', [
                'type' => 'error',
                'message' => 'Please enter a valid date in dd/mm/yyyy format!'
            ]);
            return false;
        }
    }
    // CASE 2: Date NOT provided → update blank
    else {
   //     dd("IN else");
        DB::table('policy_coverages')
            ->where('id', $this->policyCoverage_id['policyCoverage_id'])
            ->update([
                'publicliability_date' => null, // or '' if column allows
            ]);
    }
}
        }


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
    
      
        // if (isset($this->policyCoverage_id['policyCoverage_id'])) {
         
        //     $policyCovChkPublicdate = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $this->policyCoverage_id['policyCoverage_id'])->orderBy('id', 'desc')->first('coverage_id');
        // //   dd($policyCovChkPublicdate->coverage_id,$this->policyCoverage_id['policyCoverage_id']);
        //     if(isset($policyCovChkPublicdate) && $policyCovChkPublicdate->coverage_id==21){
        //         if(isset($this->publicliability_date['publicliability_date']) && $this->publicliability_date['publicliability_date']!=''){
        //             DB::table('policy_coverages')->where('id',$this->policyCoverage_id['policyCoverage_id'])
        //             ->update([
        //                 'publicliability_date' =>Carbon::createFromFormat('d/m/Y', $this->publicliability_date['publicliability_date'])->format('Y-m-d'),
        //             ]);
        //         }
        //     } 
        //     //uncommented as per bonnos request- on 10-12-25 
        // }

        foreach ($this->policyCoverageDetail as $policyCoverageId => $policyCoverageData) {

            foreach ($policyCoverageData as $coverage_id => $coverageData) {

                if (isset($coverageData['coverage_value']) || isset($coverageData['limit_id']) || isset($coverageData['coverage_value_string']) || isset($coverageData['ratefactor_value']) || isset($coverageData['ratefactor_value_check'])) {
                    $coverageData['ratefactor_type'] = $coverageData['ratefactor_type'] ?? null;
                    // Remove commas from ratefactor_value for numeric fields
                    $coverageData['ratefactor_value'] = !empty($coverageData['ratefactor_value'] ?? null)
                        ? (float) (str_replace(',', '', ($coverageData['ratefactor_value'] ?? '')))
                        : null;
                    $coverageData['ratefactor_value_check'] = !empty($coverageData['ratefactor_value_check'] ?? null)
                        ? (float) (str_replace(',', '', ($coverageData['ratefactor_value_check'] ?? '')))
                        : null;
                    $coverageData['ratefactor_AnnualWages'] = !empty($coverageData['ratefactor_AnnualWages'] ?? null)
                        ? (float) (str_replace(',', '', ($coverageData['ratefactor_AnnualWages'] ?? '')))
                        : null;
                    $coverageData['ratefactor_deposit_min_pre'] = !empty($coverageData['ratefactor_deposit_min_pre'] ?? null)
                        ? (float) (str_replace(',', '', ($coverageData['ratefactor_deposit_min_pre'] ?? '')))
                        : null;

                    $coverageData['limit_id'] = $coverageData['limit_id'] ?? null;
                    // Remove commas from Sum Insured (coverage_value)
                    $coverageData['coverage_value'] = (float) (str_replace(',', '', ($coverageData['coverage_value'] ?? ''))) ?? 0;
                    $coverageData['coverage_value_string'] = $coverageData['coverage_value_string'] ?? null;
                    // Remove commas from discount/surcharge value
                    $coverageData['discount_surcharge_value'] = (float) (str_replace(',', '', ($coverageData['discount_surcharge_value'] ?? ''))) ?? 0;
                    // Remove commas from Premium (calculated_value)
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
        foreach ($this->policyExtentionDetail as $extKey => $extentionData) {
            if (isset($extentionData['extention_coverage_value']) || isset($extentionData['extention_sum_insured']) || isset($extentionData['extention_text_value']) || isset($extentionData['extention_limit_id']) || isset($extentionData['extention_calculated_value']) || isset($extentionData['extention_excess_min_value']) || isset($extentionData['extention_excess_max_value'])) {
                $extentionData['extention_limit_id'] = $extentionData['extention_limit_id'] ?? null;
                // Remove commas from Extension Sum Insured
                $extentionData['extention_sum_insured'] = (float) (str_replace(',', '', ($extentionData['extention_sum_insured'] ?? ''))) ?? 0;
                $extentionData['extention_text_value'] = $extentionData['extention_text_value'] ?? null;

                // Remove commas from Extension Excess values (Min and Max)
                $extentionData['extention_excess_min_value'] = !empty($extentionData['extention_excess_min_value'] ?? null)
                    ? (float) (str_replace(',', '', ($extentionData['extention_excess_min_value'] ?? '')))
                    : null;
                $extentionData['extention_excess_max_value'] = !empty($extentionData['extention_excess_max_value'] ?? null)
                    ? (float) (str_replace(',', '', ($extentionData['extention_excess_max_value'] ?? '')))
                    : null;

                // Remove commas from Extension Coverage Value (Sum Insured)
                $extentionData['extention_coverage_value'] = (float) (str_replace(',', '', ($extentionData['extention_coverage_value'] ?? ''))) ?? 0;
                // Remove commas from Extension Discount/Surcharge value
                $extentionData['extention_discount_surcharge_value'] = (float) (str_replace(',', '', ($extentionData['extention_discount_surcharge_value'] ?? ''))) ?? 0;
                // Remove commas from Extension Premium (calculated_value)
                $calculated_value = (float) (str_replace(',', '', ($extentionData['extention_calculated_value'] ?? ''))) ?? 0;

                if (isset($extentionData['extention_discount_surcharge']) and isset($extentionData['extention_discount_surcharge_type']) and isset($extentionData['extention_discount_surcharge_value'])) {
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
                $this->policyExtentionDetail[$extKey] = $extentionData;
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
                   
                    // Normalize values for checking
                    // $coverage_value = isset($coverageData['coverage_value']) ? (float) str_replace(',', '', (string)$coverageData['coverage_value']) : 0;
                    // $coverage_value_string = isset($coverageData['coverage_value_string']) ? trim((string)$coverageData['coverage_value_string']) : '';
                    // $limit_id = isset($coverageData['limit_id']) ? $coverageData['limit_id'] : null;
                    // $ratefactor_value = isset($coverageData['ratefactor_value']) ? trim((string)$coverageData['ratefactor_value']) : '';
                    // $ratefactor_value_check = isset($coverageData['ratefactor_value_check']) ? trim((string)$coverageData['ratefactor_value_check']) : '';
                    // $calculated_value = isset($coverageData['calculated_value']) ? (float) str_replace(',', '', (string)$coverageData['calculated_value']) : 0;

                    // $allFieldsEmpty =
                    //         ($this->isBlankOrZero($coverage_value) &&
                    //         $this->isBlankOrZero($coverage_value_string) &&
                    //         $this->isBlankOrZero($limit_id) &&
                    //         $this->isBlankOrZero($ratefactor_value) &&
                    //         $this->isBlankOrZero($ratefactor_value_check) &&
                    //         $this->isBlankOrZero($calculated_value));
  
                    // If all fields are empty, skip saving and soft-delete if exists
                    // if ($allFieldsEmpty) {
                    //     $existingRecord = PolicyCoverageDetail::withTrashed()
                    //         ->where('policy_coverage_id', $policyCoverageId)
                    //         ->where('coverage_id', $coverage_id)
                    //         ->first();

                    //     if ($existingRecord && !$existingRecord->trashed()) {
                    //         $existingRecord->delete();
                    //     }

                         /*
                        |--------------------------------------------------------------------------
                        | 2. HARD delete CoverageMaster
                        |--------------------------------------------------------------------------
                       
                        */
                    //     $coverageMaster = CoverageMaster::find($coverage_id);

                    //     if ($coverageMaster) {

                    //         // 🔐 Safety: ensure at least 1 row remains per combination
                    //         $duplicateCount = CoverageMaster::where('s_CoverageCode', $coverageMaster->s_CoverageCode)
                    //             ->where('s_ParentCoverageCode', $coverageMaster->s_ParentCoverageCode)
                    //             ->where('s_CoverageGroupName', $coverageMaster->s_CoverageGroupName)
                    //             ->count();
                          
                    //         if ($duplicateCount > 1) {
                    //             $coverageMaster->delete(); // HARD delete
                    //         }
                    //     }
                    //     continue; // Skip to next iteration
                    // }
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
                                    #->where('policy_coverage_detail.policy_coverage_id', $policyCoverageId)
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

                        // COM/DOM endorsement: apply frequency-based day factor on top of delta
                        if ($this->isComDomPolicy()) {
                            $proRatePremium = round((float) $proRatePremium * $this->proRataDayFactor(), 2);
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
                // else{
               
                     
                      /*
                        |--------------------------------------------------------------------------
                        | 2. HARD delete CoverageMaster
                        |--------------------------------------------------------------------------
                       
                        */
                    //     $coverageMaster = CoverageMaster::find($coverage_id);

                    //     if ($coverageMaster) {

                    //         // 🔐 Safety: ensure at least 1 row remains per combination
                    //         $duplicateCount = CoverageMaster::where('s_CoverageCode', $coverageMaster->s_CoverageCode)
                    //             ->where('s_ParentCoverageCode', $coverageMaster->s_ParentCoverageCode)
                    //             ->where('s_CoverageGroupName', $coverageMaster->s_CoverageGroupName)
                    //             ->count();

                    //         if ($duplicateCount > 1) {
                    //             $coverageMaster->delete(); // HARD delete
                    //         }
                    //     }
                    //        continue; // Skip to next iteration
                    // }
                   
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
                //}
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
                        // Motor has SoftDeletes disabled, so a stale soft-deleted row
                        // (cancel/reinstate or replicated duplicate) with the same
                        // policy_coverage_id + registration_no can shadow the live row.
                        // Resolve the LIVE row first so the edit lands on it; only
                        // fall back to the create-by-attributes path for a new vehicle.
                        $liveMotorId = Motor::where('policy_coverage_id', $this->motorVehicleData['policy_coverage_id'])
                            ->where('registration_no', $this->motor['registration_no'])
                            ->whereNull('deleted_at')
                            ->orderBy('id', 'asc')
                            ->value('id');

                        $motors = Motor::updateOrCreate(
                            $liveMotorId
                                ? ['id' => $liveMotorId]
                                : [
                                    'policy_coverage_id' => $this->motorVehicleData['policy_coverage_id'],
                                    'registration_no' => $this->motor['registration_no'],
                                ],
                            [
                                'policy_coverage_id' => $this->motorVehicleData['policy_coverage_id'],
                                'registration_no' => $this->motor['registration_no'],
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



                            // foreach ($this->policyCoverageNote ?? [] as $policyCoverageId  => $note){

                            //     if($this->motorVehicleData['policy_coverage_id']==$policyCoverageId){

                            //         if ($note!=null && $note!=""){
                            //             if(isset($this->motorVehicleData['motor_id']) && $this->motorVehicleData['motor_id'] == $motor_id){
                            //                 DB::table('policy_coverage_notes')->where('motor_id',$motor_id)->delete(); /// delete note
                            //             }
                            //             PolicyCoverageNote::withTrashed()->create([
                            //                 'motor_id' =>  $motor_id,
                            //                 'policy_coverage_id' =>  $this->motorVehicleData['policy_coverage_id'],
                            //                 'note' => $note,
                            //                 'deleted_at' => null
                            //             ]);

                            //             activity('Policy Coverage Note')
                            //                     ->performedOn($this->policy)
                            //                     ->causedBy(User::where('id', auth()->user()->id)->first())
                            //                         ->log('Policy Coverage Note Added');
                            //         }  
                            //     }       
                            // }

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


        // Normalize extension data: ensure all extension data is in policyExtentionDetail
        $this->normalizeExtensionData();

        // save Extention detail
        // Direct insert: fetch master data and save to DB with endorsement logic
        foreach ($this->policyExtentionDetail as $policyCoverageId => $policyCoverageData) {
            foreach ($policyCoverageData as $extentions_id => $extentionData) {
                // Check if any field has value
                if (!isset($extentionData['extention_coverage_value']) &&
                    !isset($extentionData['extention_text_value']) &&
                    !isset($extentionData['extention_limit_id'])) {
                    continue;
                }

                // Get policy coverage - ensure we get all fields needed
                $policyCoverage = DB::table('policy_coverages')
                    ->where('id', $policyCoverageId)
                    ->whereNull('deleted_at')
                    ->first(); // Get ALL fields to debug

                if (!$policyCoverage) {
                    continue;
                }

                // Fallback: if coverage_id is NULL, try to get it from coverages table via join
                if (empty($extention->s_ParentCoverageID ?? $policyCoverage->coverage_id)) {
                    $coverageRecord = DB::table('policy_coverages')
                        ->join('coverages', 'policy_coverages.coverage_id', '=', 'coverages.id')
                        ->where('policy_coverages.id', $policyCoverageId)
                        ->select('policy_coverages.coverage_id', 'coverages.id as coverages_id')
                        ->first();

                    if ($coverageRecord) {
                        $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id = $coverageRecord->coverage_id ?? $coverageRecord->coverages_id;
                    }
                }

                // Get master extension data - fetch ALL columns
                $extention = DB::table('extentions')
                    ->where('id', $extentions_id)
                    ->first(); // No column selection = get all

                if (!$extention) {
                    // Extension not found - CREATE a minimal record from form data
                    // This shouldn't happen but let's handle it
                    $extention = (object)[
                        's_SubCoverageID' => '',
                        's_ParentCoverageCode' => '',
                        'type' => 'Extention',
                        'extention_type' => '',
                        's_CoverageName' => '',
                        's_CoverageCode' => '',
                        's_ScreenName' => '',
                        's_CoverageDesc' => '',
                        's_ExtensionsGroupName' => '',
                        'n_DisplaySequence' => '',
                        'rate' => '',
                        'd_EffectiveDt' => null,
                        'd_ExpirationDt' => null,
                        's_RatingMethod' => '',
                        's_DISPLAYTOUSER' => ''
                    ];
                }

                // Calculate endorsement fields if this is an ENDORSE transaction
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

                    if (!$existingDetail) {
                        // Case 1: New extension (no previous)
                        $proRatePremium = $currentValue;
                        $previousActionIdCov = $latestAction->id;
                        $endors_flag = 1;
                    } else {
                        $oldValue = (float) $existingDetail->calculated_value;

                        // Case 2: Multiple edits in same action
                        if ($latestAction->id == $existingDetail->previousActionIdCov) {
                            $previousDetail = DB::table('policy_extention_detail')
                                ->join('policy_coverages', 'policy_extention_detail.policy_coverage_id', '=', 'policy_coverages.id')
                                ->join('policy_actions', 'policy_coverages.action_id', '=', 'policy_actions.id')
                                ->join('risk_address as ra2', 'policy_coverages.risk_address_id', '=', 'ra2.id')
                                ->where('policy_actions.policy_id', $this->policy->id)
                                ->where('policy_extention_detail.extentions_id', $extentions_id)
                                ->where('policy_extention_detail.policy_coverage_id', $policyCoverageId)
                                ->where('ra2.address_name', $existingDetail->risk_address_name)
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

                        // Case 3: Compare values
                        if ($currentValue > $baseOldValue) {
                            $proRatePremium = $currentValue - $baseOldValue;
                            $previousActionIdCov = $latestAction->id;
                            $endors_flag = 1;
                        } elseif ($currentValue < $baseOldValue) {
                            $proRatePremium = $currentValue - $baseOldValue;
                            $previousActionIdCov = $latestAction->id;
                            $endors_flag = 1;
                        } else {
                            // Case 4 + 5: No change / reverted
                            $proRatePremium = 0;
                            $previousActionIdCov = $existingDetail->previousActionIdCov;
                            $endors_flag = $existingDetail->endors_flag;
                        }
                    }

                    // Apply COM/DOM pro-rata factor if applicable
                    if ($this->isComDomPolicy()) {
                        $proRatePremium = round(
                            (float) ($proRatePremium ?? 0) * $this->proRataDayFactor(),
                            2
                        );
                    }
                }

                // Build save data with master extension fields and endorsement data
                $saveData = [
                    's_ParentCoverageID' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id,
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
                    'rate' => $extention->rate ?? '',
                    'd_EffectiveDt' => $extention->d_EffectiveDt ?? null,
                    'd_ExpirationDt' => $extention->d_ExpirationDt ?? null,
                    's_RatingMethod' => $extention->s_RatingMethod ?? '',
                    's_DISPLAYTOUSER' => $extention->s_DISPLAYTOUSER ?? '',
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
                    'coverage_value_string' => $extentionData['coverage_value_string'] ?? '',
                    'coverage_value' => $extentionData['coverage_value'] ?? '',
                    'deleted_at' => null,
                    'pro_rate_premium' => $proRatePremium ?? 0,
                    'endors_flag' => $endors_flag ?? 0,
                    'previousActionIdCov' => $previousActionIdCov ?? 0,
                    'updated_at' => now()
                ];

                // Endorsement fields: real values on ENDORSE, 0 on new business / other types
                // ($proRatePremium / $endors_flag are already 0 unless this is an ENDORSE)
                $endorseSetSql = 'pro_rate_premium = ?, endors_flag = ?,';
                $endorseUpdateParams = [$proRatePremium ?? 0, $endors_flag ?? 0];
                $endorseInsertCols = 'pro_rate_premium, endors_flag, ';
                $endorseInsertPlaceholders = '?, ?, ';
                $endorseInsertParams = [$proRatePremium ?? 0, $endors_flag ?? 0];

                // Use raw SQL to absolutely ensure master data is saved
                // This bypasses any ORM filtering or interception
                $exists = DB::table('policy_extention_detail')
                    ->where('policy_coverage_id', $policyCoverageId)
                    ->where('extentions_id', $extentions_id)
                    ->exists();

                if ($exists) {
                    // Update existing record
                    DB::statement('
                        UPDATE policy_extention_detail
                        SET
                            s_ParentCoverageID = ?, /* from extentions table */
                            s_SubCoverageID = ?,
                            s_CoverageName = ?,
                            s_CoverageCode = ?,
                            s_ScreenName = ?,
                            s_CoverageDesc = ?,
                            s_ExtensionsGroupName = ?,
                            n_DisplaySequence = ?,
                            extention_type = ?,
                            s_ParentCoverageCode = ?,
                            type = ?,
                            rate = ?,
                            d_EffectiveDt = ?,
                            d_ExpirationDt = ?,
                            s_RatingMethod = ?,
                            s_DISPLAYTOUSER = ?,
                            extention_coverage_value = ?,
                            extention_text_value = ?,
                            extention_limit_id = ?,
                            extention_discount_surcharge = ?,
                            extention_discount_surcharge_type = ?,
                            extention_discount_surcharge_value = ?,
                            extention_calculated_value = ?,
                            extention_sum_insured = ?,
                            coverage_value_string = ?,
                            coverage_value = ?,
                            ' . $endorseSetSql . '
                            previousActionIdCov = ?,
                            deleted_at = NULL,
                            updated_at = NOW()
                        WHERE policy_coverage_id = ? AND extentions_id = ?
                    ', array_merge([
                        $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id,
                        $extention->s_SubCoverageID ?? '',
                        $extention->s_CoverageName ?? '',
                        $extention->s_CoverageCode ?? '',
                        $extention->s_ScreenName ?? '',
                        $extention->s_CoverageDesc ?? '',
                        $extention->s_ExtensionsGroupName ?? '',
                        $extention->n_DisplaySequence ?? '',
                        $extention->extention_type ?? '',
                        $extention->s_ParentCoverageCode ?? '',
                        $extention->type ?? '',
                        $extention->rate ?? '',
                        $extention->d_EffectiveDt ?? null,
                        $extention->d_ExpirationDt ?? null,
                        $extention->s_RatingMethod ?? '',
                        $extention->s_DISPLAYTOUSER ?? '',
                        $extentionData['extention_coverage_value'] ?? '',
                        $extentionData['extention_text_value'] ?? null,
                        $extentionData['extention_limit_id'] ?? null,
                        $extentionData['extention_discount_surcharge'] ?? 0,
                        $extentionData['extention_discount_surcharge_type'] ?? 0,
                        $extentionData['extention_discount_surcharge_value'] ?? 0,
                        $extentionData['extention_calculated_value'] ?? '',
                        $extentionData['extention_sum_insured'] ?? '',
                        $extentionData['coverage_value_string'] ?? '',
                        $extentionData['coverage_value'] ?? '',
                    ], $endorseUpdateParams, [
                        $previousActionIdCov ?? 0,
                        $policyCoverageId,
                        $extentions_id
                    ]));
                } else {
                    // Insert new record
                    DB::statement('
                        INSERT INTO policy_extention_detail (
                            policy_coverage_id, extentions_id,
                            s_ParentCoverageID, s_SubCoverageID, s_CoverageName, s_CoverageCode,
                            s_ScreenName, s_CoverageDesc, s_ExtensionsGroupName, n_DisplaySequence,
                            extention_type, s_ParentCoverageCode, type, rate,
                            d_EffectiveDt, d_ExpirationDt, s_RatingMethod, s_DISPLAYTOUSER,
                            extention_coverage_value, extention_text_value, extention_limit_id,
                            extention_discount_surcharge, extention_discount_surcharge_type, extention_discount_surcharge_value,
                            extention_calculated_value, extention_sum_insured, coverage_value_string, coverage_value,
                            ' . $endorseInsertCols . 'previousActionIdCov,
                            deleted_at, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . $endorseInsertPlaceholders . '?, NULL, NOW(), NOW())
                    ', array_merge([
                        $policyCoverageId, $extentions_id,
                        $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id,
                        $extention->s_SubCoverageID ?? '',
                        $extention->s_CoverageName ?? '',
                        $extention->s_CoverageCode ?? '',
                        $extention->s_ScreenName ?? '',
                        $extention->s_CoverageDesc ?? '',
                        $extention->s_ExtensionsGroupName ?? '',
                        $extention->n_DisplaySequence ?? '',
                        $extention->extention_type ?? '',
                        $extention->s_ParentCoverageCode ?? '',
                        $extention->type ?? '',
                        $extention->rate ?? '',
                        $extention->d_EffectiveDt ?? null,
                        $extention->d_ExpirationDt ?? null,
                        $extention->s_RatingMethod ?? '',
                        $extention->s_DISPLAYTOUSER ?? '',
                        $extentionData['extention_coverage_value'] ?? '',
                        $extentionData['extention_text_value'] ?? null,
                        $extentionData['extention_limit_id'] ?? null,
                        $extentionData['extention_discount_surcharge'] ?? 0,
                        $extentionData['extention_discount_surcharge_type'] ?? 0,
                        $extentionData['extention_discount_surcharge_value'] ?? 0,
                        $extentionData['extention_calculated_value'] ?? '',
                        $extentionData['extention_sum_insured'] ?? '',
                        $extentionData['coverage_value_string'] ?? '',
                        $extentionData['coverage_value'] ?? '',
                    ], $endorseInsertParams, [
                        $previousActionIdCov ?? 0
                    ]));
                }
            }
        }


        if (isset($policyCoverageId)) {
            $policyCov = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $policyCoverageId)->whereNull('deleted_at')->orderBy('id', 'desc')->first('coverage_id');
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
                    $policyCov = DB::table('policy_coverages')->where('policy_id', $this->policy->id)->where('id', $policyCoverageId)->whereNull('deleted_at')->orderBy('id', 'desc')->first('coverage_id');
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
        //specified item code-- 
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
                            //                           if($policyCov->coverage_id == 21 && $policy_coverage_id!=18312){
//   dd("emdorse",$existingDetail); }
                            //------------------------------------------
                            // CASE 1: New Item
                            //------------------------------------------
                            if (!$existingDetail) {
                                $endorse_flag = "1";
                                $action_id = $latestAction->id;
                                $baseOld = 0;
                                $prevActionIdCov = $latestAction->id;
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
                                    $prevActionIdCov = $latestAction->id;
                                } else {
                                    $endorse_flag = "0";
                                    $action_id = $existingDetail->action_id;
                                    $prevActionIdCov = (int) ($existingDetail->previousActionIdCov ?? $existingDetail->action_id);
                                }
                            }

                            // Pro-rata: delta vs baseOld, factored by frequency for COM/DOM
                            $proRatePremium = ($endorse_flag === "1")
                                ? ((float) $calculatedNew - (float) $baseOld)
                                : 0;
                            if ($this->isComDomPolicy()) {
                                $proRatePremium = round($proRatePremium * $this->proRataDayFactor(), 2);
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
                            if (\Schema::hasColumn('policy_specified_items', 'pro_rate_premium')) {
                                $data['pro_rate_premium'] = $proRatePremium;
                            }
                            if (\Schema::hasColumn('policy_specified_items', 'previousActionIdCov')) {
                                $data['previousActionIdCov'] = $prevActionIdCov;
                            }
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

          // Save CAR, PAR, and EAR Coverage Data (Separate from PolicyCoveragesNewData)
          foreach ($this->policyCoverages as $policyCoverage) {
            $policyCoverageId = $policyCoverage->id;
            // Safely get coverage code - check if coverage relationship exists
            $coverageCode = '';
            if (isset($policyCoverage->coverage) && $policyCoverage->coverage) {
                $coverageCode = $policyCoverage->coverage->s_CoverageCode ?? '';
            }

            // Save CAR Coverage
            if (
                in_array($coverageCode, ['CONTRACTORSALLRISKS', 'CAR']) &&
                (isset($this->carCoverage[$policyCoverageId]) ||
                    isset($this->carSection1Items[$policyCoverageId]) ||
                    isset($this->carSection2Items[$policyCoverageId]) ||
                    isset($this->carSection3Items[$policyCoverageId]))
            ) {

                \Log::info('Saving CAR Coverage', [
                    'policy_coverage_id' => $policyCoverageId,
                    'carCoverage' => $this->carCoverage[$policyCoverageId] ?? [],
                    'section1' => $this->carSection1Items[$policyCoverageId] ?? [],
                    'section2' => $this->carSection2Items[$policyCoverageId] ?? [],
                    'section3' => $this->carSection3Items[$policyCoverageId] ?? []
                ]);

                // Calculate premiums for section items
                $totalSumInsured = 0;
                $totalPremium = 0;
                if (isset($this->carSection1Items[$policyCoverageId])) {
                    foreach ($this->carSection1Items[$policyCoverageId] as $index => $item) {
                        if (isset($item['sum_insured']) && isset($item['rate'])) {
                            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                            $rate = (float) $item['rate'];
                            $premium = ($sumInsured * $rate) / 100;
                            $this->carSection1Items[$policyCoverageId][$index]['premium'] = $premium;
                            $totalSumInsured += $sumInsured;
                            $totalPremium += $premium;
                        } elseif (isset($item['sum_insured'])) {
                            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                            $totalSumInsured += $sumInsured;
                        }
                        if (isset($item['premium'])) {
                            $premium = (float) str_replace(',', '', $item['premium']);
                            $totalPremium += $premium;
                        }
                    }
                }

                // Set totals in carCoverage array
                if (!isset($this->carCoverage[$policyCoverageId])) {
                    $this->carCoverage[$policyCoverageId] = [];
                }
                $this->carCoverage[$policyCoverageId]['section1_total_sum_insured'] = number_format($totalSumInsured, 2, '.', '');
                $this->carCoverage[$policyCoverageId]['section1_total_premium'] = number_format($totalPremium, 2, '.', '');

                // Calculate Section 2 totals
                $section2TotalLimit = 0;
                $section2TotalPremium = 0;
                if (isset($this->carSection2Items[$policyCoverageId])) {
                    foreach ($this->carSection2Items[$policyCoverageId] as $index => $item) {
                        if (isset($item['limit_of_indemnity']) && isset($item['rate'])) {
                            $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                            $rate = (float) $item['rate'];
                            $premium = ($limit * $rate) / 100;
                            $this->carSection2Items[$policyCoverageId][$index]['premium'] = $premium;
                            $section2TotalLimit += $limit;
                            $section2TotalPremium += $premium;
                        } elseif (isset($item['limit_of_indemnity'])) {
                            $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                            $section2TotalLimit += $limit;
                        }
                        if (isset($item['premium'])) {
                            $premium = (float) str_replace(',', '', $item['premium']);
                            $section2TotalPremium += $premium;
                        }
                    }
                }

                // Calculate Section 3 totals - using direct fields from carCoverage
                $section3TotalAnnualSum = 0;
                $section3TotalPremium = 0;
                
                // Calculate Gross Profit premium from annual sum insured and rate if both are set
                if (isset($this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured']) && 
                    isset($this->carCoverage[$policyCoverageId]['section3_gross_profit_rate'])) {
                    $annualSum = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured']);
                    $rate = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_gross_profit_rate']);
                    $premium = ($annualSum * $rate) / 100;
                    $this->carCoverage[$policyCoverageId]['section3_gross_profit_premium'] = number_format($premium, 2, '.', '');
                    $section3TotalAnnualSum += $annualSum;
                    $section3TotalPremium += $premium;
                } elseif (isset($this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured'])) {
                    $annualSum = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured']);
                    $section3TotalAnnualSum += $annualSum;
                }
                
                // If Gross Profit premium is set directly, use it
                if (isset($this->carCoverage[$policyCoverageId]['section3_gross_profit_premium'])) {
                    $premium = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_gross_profit_premium']);
                    $section3TotalPremium += $premium;
                }
                
                // Calculate Increased Cost of Working premium from sum insured and rate if both are set
                if (isset($this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured']) && 
                    isset($this->carCoverage[$policyCoverageId]['section3_increased_cost_rate'])) {
                    $sumInsured = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured']);
                    $rate = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_increased_cost_rate']);
                    $premium = ($sumInsured * $rate) / 100;
                    $this->carCoverage[$policyCoverageId]['section3_increased_cost_premium'] = number_format($premium, 2, '.', '');
                    $section3TotalAnnualSum += $sumInsured;
                    $section3TotalPremium += $premium;
                } elseif (isset($this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured'])) {
                    $sumInsured = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured']);
                    $section3TotalAnnualSum += $sumInsured;
                }
                
                // If Increased Cost of Working premium is set directly, use it
                if (isset($this->carCoverage[$policyCoverageId]['section3_increased_cost_premium'])) {
                    $premium = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_increased_cost_premium']);
                    $section3TotalPremium += $premium;
                }
                
                // Legacy support: Calculate premium from old section3_annual_sum_insured and section3_rate if both are set
                if (isset($this->carCoverage[$policyCoverageId]['section3_annual_sum_insured']) && 
                    isset($this->carCoverage[$policyCoverageId]['section3_rate'])) {
                    $annualSum = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_annual_sum_insured']);
                    $rate = (float) $this->carCoverage[$policyCoverageId]['section3_rate'];
                    $premium = ($annualSum * $rate) / 100;
                    $this->carCoverage[$policyCoverageId]['section3_premium'] = number_format($premium, 2, '.', '');
                    $section3TotalAnnualSum += $annualSum;
                    $section3TotalPremium += $premium;
                } elseif (isset($this->carCoverage[$policyCoverageId]['section3_annual_sum_insured'])) {
                    $annualSum = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_annual_sum_insured']);
                    $section3TotalAnnualSum += $annualSum;
                }
                
                // Legacy support: If premium is set directly, use it
                if (isset($this->carCoverage[$policyCoverageId]['section3_premium'])) {
                    $premium = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_premium']);
                    $section3TotalPremium += $premium;
                }
                
                // Also check for legacy array format (for backward compatibility)
                if (isset($this->carSection3Items[$policyCoverageId])) {
                    foreach ($this->carSection3Items[$policyCoverageId] as $index => $item) {
                        if (isset($item['annual_sum_insured']) && isset($item['rate'])) {
                            $annualSum = (float) str_replace(',', '', $item['annual_sum_insured']);
                            $rate = (float) $item['rate'];
                            $premium = ($annualSum * $rate) / 100;
                            $this->carSection3Items[$policyCoverageId][$index]['premium'] = $premium;
                            $section3TotalAnnualSum += $annualSum;
                            $section3TotalPremium += $premium;
                        } elseif (isset($item['annual_sum_insured'])) {
                            $annualSum = (float) str_replace(',', '', $item['annual_sum_insured']);
                            $section3TotalAnnualSum += $annualSum;
                        }
                        if (isset($item['premium'])) {
                            $premium = (float) str_replace(',', '', $item['premium']);
                            $section3TotalPremium += $premium;
                        }
                    }
                }

                $this->carCoverage[$policyCoverageId]['section2_total_limit'] = number_format($section2TotalLimit, 2, '.', '');
                $this->carCoverage[$policyCoverageId]['section2_total_premium'] = number_format($section2TotalPremium, 2, '.', '');
                $this->carCoverage[$policyCoverageId]['section3_total_annual_sum'] = number_format($section3TotalAnnualSum, 2, '.', '');
                $this->carCoverage[$policyCoverageId]['section3_total_premium'] = number_format($section3TotalPremium, 2, '.', '');

                $carCoveragePayload = $this->carCoverage[$policyCoverageId] ?? [];
                $carCoveragePayload = $this->formatCoverageDatesForDb($carCoveragePayload, $this->carCoverageDateFields);
                
                // Store all endorsements as JSON in endorsement_1 for unlimited endorsements
                $endorsements = $this->carEndorsements[$policyCoverageId] ?? [];
                // Filter out empty endorsements
                $endorsements = array_filter($endorsements, function($endorsement) {
                    return !empty($endorsement['text'] ?? '');
                });
                // Re-index array to ensure sequential keys
                $endorsements = array_values($endorsements);
                
                // Store as JSON in endorsement_1, clear other columns
                $carCoveragePayload['endorsement_1'] = !empty($endorsements) ? json_encode($endorsements, JSON_UNESCAPED_UNICODE) : '';
                $carCoveragePayload['endorsement_2'] = '';
                $carCoveragePayload['endorsement_3'] = '';
                $carCoveragePayload['endorsement_4'] = '';
                
                // Clean numeric fields (limit of indemnity, premiums, etc.)
                // Note: Deductible fields are NOT cleaned as they can contain text
                $numericFields = [
                    'section1_earthquake_limit_indemnity',
                    'section1_storm_limit_indemnity',
                    'section1_earthquake_premium',
                    'section1_storm_premium',
                    'section3_maximum_indemnity',
                    'section3_gross_profit_annual_sum_insured',
                    'section3_gross_profit_rate',
                    'section3_gross_profit_premium',
                    'section3_increased_cost_sum_insured',
                    'section3_increased_cost_rate',
                    'section3_increased_cost_premium',
                ];
                $carCoveragePayload = $this->cleanNumericFieldsForDb($carCoveragePayload, $numericFields);

                $carData = array_merge(
                    [
                        'policy_id' => $this->policy->id,
                        'policy_coverage_id' => $policyCoverageId,
                        'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                    ],
                    $carCoveragePayload,
                    [
                        'section1_items' => $this->jsonOrNull($this->carSection1Items[$policyCoverageId] ?? null),
                        'section2_items' => $this->jsonOrNull($this->carSection2Items[$policyCoverageId] ?? null),
                        'section3_items' => $this->jsonOrNull($this->carSection3Items[$policyCoverageId] ?? null),
                        'section3_contract_works' => $this->jsonOrNull($this->carSection3ContractWorks[$policyCoverageId] ?? null),
                        'plant_list_items' => $this->jsonOrNull($this->carPlantListItems[$policyCoverageId] ?? null),
                    ]
                );
               // dd($carCoveragePayload);
                $savedCarCoverage = CarCoverageModel::updateOrCreate(
                    [
                        'policy_coverage_id' => $policyCoverageId,
                        'policy_id' => $this->policy->id
                    ],
                    $carData
                );

                \Log::info('CAR Coverage SAVED Successfully!', [
                    'saved_id' => $savedCarCoverage->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'saved_data' => $savedCarCoverage->toArray()
                ]);
            }

            // Save PAR Coverage
            if (
                in_array($coverageCode, ['PLANTALLRISKS', 'PAR']) &&
                (isset($this->parCoverage[$policyCoverageId]) ||
                    isset($this->parInsuredItems[$policyCoverageId]) ||
                    isset($this->parSection2Items[$policyCoverageId]))
            ) {

                // Calculate totals
                $parTotalSumInsured = 0;
                $parTotalPremium = 0;
                if (isset($this->parInsuredItems[$policyCoverageId])) {
                    foreach ($this->parInsuredItems[$policyCoverageId] as $item) {
                        if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
                            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                            $parTotalSumInsured += $sumInsured;
                        }
                        if (isset($item['premium']) && !empty($item['premium'])) {
                            $premium = (float) str_replace(',', '', $item['premium']);
                            $parTotalPremium += $premium;
                        }
                    }
                }

                // Calculate Section 2 totals
                $parSection2TotalLimit = 0;
                $parSection2TotalPremium = 0;
                if (isset($this->parSection2Items[$policyCoverageId])) {
                    foreach ($this->parSection2Items[$policyCoverageId] as $item) {
                        if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
                            $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                            $parSection2TotalLimit += $limit;
                        }
                        if (isset($item['premium']) && !empty($item['premium'])) {
                            $premium = (float) str_replace(',', '', $item['premium']);
                            $parSection2TotalPremium += $premium;
                        }
                    }
                }

                $parCoverageData = $this->parCoverage[$policyCoverageId] ?? [];
                $parCoverageData['total_sum_insured'] = number_format($parTotalSumInsured, 2, '.', '');
                $parCoverageData['total_premium'] = number_format($parTotalPremium, 2, '.', '');
                $parCoverageData['section2_total_limit'] = number_format($parSection2TotalLimit, 2, '.', '');
                $parCoverageData['section2_total_premium'] = number_format($parSection2TotalPremium, 2, '.', '');
                $parCoverageData = $this->formatCoverageDatesForDb($parCoverageData, $this->parCoverageDateFields);

                $parData = array_merge(
                    [
                        'policy_id' => $this->policy->id,
                        'policy_coverage_id' => $policyCoverageId,
                        'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                    ],
                    $parCoverageData,
                    [
                        'action_id' => $this->actionId,
                        'term_id' => $this->termId,
                        // ParCoverage casts these columns as 'array' — Eloquent
                        // json_encodes on save. Pre-encoding here double-encodes
                        // (DB ends up with a JSON string-of-a-string), which then
                        // fails to render as rows on the edit page. Pass raw arrays.
                        'insured_items' => $this->parInsuredItems[$policyCoverageId] ?? [],
                        'section2_items' => $this->parSection2Items[$policyCoverageId] ?? [],
                    ]
                );

                ParCoverageModel::updateOrCreate(
                    [
                        'policy_coverage_id' => $policyCoverageId,
                        'policy_id' => $this->policy->id,
                        'action_id' => $this->actionId,
                        'term_id' => $this->termId,
                    ],
                    $parData
                );
            }

            // Save EAR Coverage
            if (
                in_array($coverageCode, ['ERECTIONALLRISKS', 'EAR']) &&
                (isset($this->earCoverage[$policyCoverageId]) ||
                    isset($this->earSection1Items[$policyCoverageId]) ||
                    isset($this->earSection3Items[$policyCoverageId]) ||
                    isset($this->earEndorsements[$policyCoverageId]))
            ) {

                $earCoverageData = $this->earCoverage[$policyCoverageId] ?? [];
                $earCoverageData = $this->formatCoverageDatesForDb($earCoverageData, $this->earCoverageDateFields);
                
                // Store all endorsements as JSON in endorsement_1 for unlimited endorsements
                $endorsements = $this->earEndorsements[$policyCoverageId] ?? [];
                // Filter out empty endorsements
                $endorsements = array_filter($endorsements, function($endorsement) {
                    return !empty($endorsement['text'] ?? '');
                });
                // Re-index array to ensure sequential keys
                $endorsements = array_values($endorsements);
                
                // Store as JSON in endorsement_1, clear other columns
                $earCoverageData['endorsement_1'] = !empty($endorsements) ? json_encode($endorsements, JSON_UNESCAPED_UNICODE) : '';
                $earCoverageData['endorsement_2'] = '';
                $earCoverageData['endorsement_3'] = '';
                $earCoverageData['endorsement_4'] = '';
                
                // Clean numeric fields (limit of indemnity, premiums, etc.)
                $numericFields = [
                    'risk_earthquake_limit_indemnity',
                    'risk_storm_limit_indemnity',
                    'risk_earthquake_premium',
                    'risk_storm_premium',
                ];
                $earCoverageData = $this->cleanNumericFieldsForDb($earCoverageData, $numericFields);

                $earData = array_merge(
                    [
                        'policy_id' => $this->policy->id,
                        'policy_coverage_id' => $policyCoverageId,
                        'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                    ],
                    $earCoverageData,
                    [
                        // EarCoverage casts these as 'array' — same double-encode
                        // hazard as the PAR fix above. Pass raw arrays.
                        'section1_items' => $this->earSection1Items[$policyCoverageId] ?? [],
                        'section3_items' => $this->earSection3Items[$policyCoverageId] ?? [],
                    ]
                );

                EarCoverageModel::updateOrCreate(
                    [
                        'policy_coverage_id' => $policyCoverageId,
                        'policy_id' => $this->policy->id
                    ],
                    $earData
                );
            }

            // Save Travel Coverage
            if (
                in_array($coverageCode, ['TRAVEL', 'TRAVELINSURANCE']) &&
                (isset($this->travelCoverage[$policyCoverageId]) || isset($this->travelBenefits[$policyCoverageId]))
            ) {
                \Log::info('Saving Travel Coverage', [
                    'policy_coverage_id' => $policyCoverageId,
                    'travelCoverage' => $this->travelCoverage[$policyCoverageId] ?? [],
                    'benefits' => $this->travelBenefits[$policyCoverageId] ?? []
                ]);

                $travelCoverageData = $this->travelCoverage[$policyCoverageId] ?? [];
                
                // Calculate total (policy_amount + vat)
                if (isset($travelCoverageData['policy_amount']) && isset($travelCoverageData['vat'])) {
                    $policyAmount = (float) str_replace(',', '', $travelCoverageData['policy_amount'] ?? '0');
                    $vat = (float) str_replace(',', '', $travelCoverageData['vat'] ?? '0');
                    $travelCoverageData['total'] = number_format($policyAmount + $vat, 2, '.', '');
                }
                
                // Format date fields before saving
                $travelCoverageData = $this->formatCoverageDatesForDb($travelCoverageData, $this->travelCoverageDateFields);
                
                // Clean numeric fields
                $numericFields = [
                    'policy_amount',
                    'vat',
                    'total',
                ];
                $travelCoverageData = $this->cleanNumericFieldsForDb($travelCoverageData, $numericFields);

            // Handle policy wording file upload
            if ($this->travelPolicyWording) {
                $wordingPath = $this->handlePolicyWordingUpload($policyCoverageId, 'TRAVEL');
                if ($wordingPath) {
                    $travelCoverageData['policy_wording_path'] = $wordingPath;
                }
            }

                $travelData = array_merge(
                    [
                        'policy_id' => $this->policy->id,
                        'policy_coverage_id' => $policyCoverageId,
                        'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                    ],
                    $travelCoverageData,
                    [
                        // TravelCoverage casts these as 'array' — see the PAR/CAR/EAR
                        // double-encode note elsewhere in this file. Pass raw arrays.
                        'benefits' => $this->travelBenefits[$policyCoverageId] ?? [],
                        'custom_benefits' => $this->travelCustomBenefits[$policyCoverageId] ?? [],
                    ]
                );

                $savedTravelCoverage = TravelCoverageModel::updateOrCreate(
                    [
                        'policy_coverage_id' => $policyCoverageId,
                        'policy_id' => $this->policy->id
                    ],
                    $travelData
                );

                \Log::info('Travel Coverage SAVED Successfully!', [
                    'saved_id' => $savedTravelCoverage->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'saved_data' => $savedTravelCoverage->toArray()
                ]);
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
            //return redirect()->route('policy');
            return redirect()->route('policy.edit', \Crypt::encrypt($this->policy->id));

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

        $policyId = $this->policy->id;
        $policyProduct = $this->policy->product_id;

        // One-coverage-per-address rule for the 10 specialist coverages
        // (CAR/EAR/PAR/MB/MM/PI/D&O/Marine Once-Off/Marine Open/Travel).
        // Scoped to the customer's full portfolio so the duplicate is
        // caught across policies, not just within the current action.
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

        // Strict one-to-one rule (business request 2026-06-01): for the 10
        // specialist coverages a risk address may be linked to only ONE
        // coverage record, of any type. This runs in addition to (and is
        // stricter than) the same-type guard above — both are independent,
        // so neither needs modifying. Scoped portfolio-wide.
        $exclusiveConflict = PolicyCoverage::findExclusiveAddressConflict(
            $this->policyCoverage->coverage_id ?? null,
            $this->policyCoverage->risk_address_id
        );
        if ($exclusiveConflict) {
            $existingName = $exclusiveConflict->coverage->s_ScreenName
                ?? $exclusiveConflict->coverage->s_CoverageCode
                ?? 'an existing';
            $this->dispatchBrowserEvent('alert', [
                'type'    => 'error',
                'message' => "This risk address is already linked to {$existingName} coverage. A risk address can be linked to only one coverage — remove the existing coverage or choose a different risk address before adding another.",
            ]);
            return;
        }

        if($policyProduct == 16){
            $coverageExists = PolicyCoverage::where('policy_id', $policyId)
            ->where('action_id', $this->actionId)
            ->where('risk_address_id', $this->policyCoverage->risk_address_id)
            ->exists();

            if ($coverageExists) {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Only one coverage is allowed for each risk address']);
                return;
            }
        }
        if($policyProduct == 17){
            $coverageExists = PolicyCoverage::where('policy_id', $policyId)
            ->where('action_id', $this->actionId)
            ->where('risk_address_id', $this->policyCoverage->risk_address_id)
            ->exists();
            if ($coverageExists) {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Only one coverage is allowed for each risk address']);
                return;
            }
        }

        $PolicyTerm = PolicyTerm::where('id', $this->termId)->first();
        if ($PolicyTerm) {
        $policyInceptionDate = $PolicyTerm->term_start_date ? \Carbon\Carbon::parse($PolicyTerm->term_start_date)->format('Y-m-d') : '';
            $policyExpiryDate = $PolicyTerm->term_end_date ? \Carbon\Carbon::parse($PolicyTerm->term_end_date)->format('Y-m-d') : '';
            $todayDate = date('Y-m-d');
            $this->medicalMalpractice[$this->policyCoverage->id]['policy_inception_date'] = $policyInceptionDate;
            $this->medicalMalpractice[$this->policyCoverage->id]['policy_expiry_date'] = $policyExpiryDate;
            $this->medicalMalpractice[$this->policyCoverage->id]['today_date'] = $todayDate;
            $this->professionalIndemnity[$this->policyCoverage->id]['policy_inception_date'] = $policyInceptionDate;
            $this->professionalIndemnity[$this->policyCoverage->id]['policy_expiry_date'] = $policyExpiryDate;
            $this->professionalIndemnity[$this->policyCoverage->id]['today_date'] = $todayDate;
        }
        $policyAction = PolicyAction::where('id', $this->actionId)->first();

        if (($policyAction->transaction_type == 'ENDORSE' && $policyAction->transaction_reason == 'ADDCOVG')) {
            $this->policyCoverage->endors_flag = 1;
        }
        $this->policyCoverage->term_id = $this->termId;
        $this->policyCoverage->action_id = $this->actionId;
        $this->policyCoverage->policy_id = $this->policy->id;
        $this->validate();
        $this->policyCoverage->save();
         $this->policyCoverage_id['policyCoverage_id'] = $this->policyCoverage->id;
        DB::commit();
        $customer = Customer::where('id', auth()->user()->id)->first();
        activity('Coverage Added')
            ->performedOn($customer)
            ->causedBy(Customer::where('id', auth()->user()->id)->first())
            ->tap(function ($activity) {
                $activity->subject_id = $this->policy->id;
            })
            ->log('Coverage Added - ' . $this->policyCoverage->id);

        $newPolicyCoverageId = $this->policyCoverage->id;
        $coverageCode = $this->policyCoverage->coverage->s_CoverageCode ?? '';
        if (in_array($coverageCode, ['ERECTIONALLRISKS', 'EAR'])) {
            $this->loadEarCoverageData($newPolicyCoverageId);
        }
        if (in_array($coverageCode, ['CONTRACTORSALLRISKS', 'CAR'])) {
            $this->loadCarCoverageData($newPolicyCoverageId);
        }

        $this->policyCoverage = $this->policyCoverage->replicate();
    }

    public function getPolicyCoveragesProperty()
    {
        return PolicyCoverage::with([
            'coverage' => function ($query) {
                $query->with([
                    'subCoverage' => function ($q) {
                        $q->where(function ($q1) {
                            $q1->where('policy_id', null)
                                ->orWhere('policy_id', $this->policy->id);
                        })
                            ->orderBy('s_CoverageGroupName');
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
        ])->select('id', 'risk_address_id', 'coverage_id', 'publicliability_date', 'status')
            ->Policy($this->policy->id)->Term($this->termId)->Action($this->actionId)
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
            ->select('tb_cvgpccoverages.id', 's_CoverageCode','s_ScreenName')?->with([
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
                'name' => $coverage->s_ScreenName ?? $coverage->s_CoverageCode
            ];
        });
    }

    public function getallVehicles($riskAddressId = null)
    {

        return Vehicle::PolicyId($this->policy->id)->TermId($this->termId)->ActionId($this->actionId)
            // Show vehicles risk-address-wise: each risk accordion lists only its own vehicles.
            ->when(!is_null($riskAddressId), function ($q) use ($riskAddressId) {
                $q->where('risk_id', $riskAddressId);
            })
            ->whereNull('deleted_at')->get();
    }

    public function getAllVehiclesProperty()
    {
        return Vehicle::select('vehiclePlate', 'id')->PolicyId($this->policy->id)->ActionId($this->dataShowForActionId)
            ->whereNull('deleted_at')->get()->pluck('vehiclePlate', 'id')->toArray();

    }

    public function getallDevicesProperty()
    {
        return PolicyCellPhone::Policy($this->policy->id)->action($this->dataShowForActionId)->get()->pluck('device_type', 'id')->toArray();
    }

    public function getallBeneficiariesProperty()
    {
        return PolicyBeneficiary::selectRaw("id,concat(first_name,' ',middle_name,' ',last_name) as name")->Policy($this->policy->id)->action($this->dataShowForActionId)->get()->pluck('name', 'id')->toArray();
    }
    public function deleteCoverage($policyCoverageId)
    {
        $customer = Customer::where('id', auth()->user()->id)->first();
        activity('Coverage Deleted')
            ->performedOn($customer)
            ->causedBy($customer)
            ->tap(function ($activity) {
                $activity->subject_id = $this->policy->id;
            })
            ->log('Coverage Deleted - ' . $policyCoverageId);

        PolicyCoverage::where('id', $policyCoverageId)->update(['endors_flag' => '2']);
        PolicyCoverage::find($policyCoverageId)->delete();

        PolicyCoverageDetail::where('policy_coverage_id', $policyCoverageId)->delete();
        PolicyExtentionDetails::where('policy_coverage_id', $policyCoverageId)->delete();
        PolicySpecifiedItem::where('policy_coverage_id', $policyCoverageId)->delete();
    }

    public function cancelCoverage($policyCoverageId)
    {
        $latestAction = PolicyAction::where('id', $this->actionId)->first();
        $isEndorse    = $latestAction && $latestAction->transaction_type === 'ENDORSE';
        $applyRefund  = $isEndorse && $this->isComDomPolicy();
        $factor       = $applyRefund ? $this->proRataDayFactor() : 0.0;

        PolicyCoverage::where('id', $policyCoverageId)
            ->update(['status' => '1', 'updated_at' => now()]);
        PolicyCoverage::find($policyCoverageId)->delete();

        $details = PolicyCoverageDetail::where('policy_coverage_id', $policyCoverageId)->get();
        foreach ($details as $d) {
            if ($applyRefund) {
                $refund = -1 * round(((float) $d->calculated_value) * $factor, 2);
                $d->update([
                    'pro_rate_premium'    => $refund,
                    'endors_flag'         => 1,
                    'previousActionIdCov' => $this->actionId,
                ]);
            }
            $d->delete();
        }

        $exts = PolicyExtentionDetails::where('policy_coverage_id', $policyCoverageId)->get();
        foreach ($exts as $e) {
            if ($applyRefund) {
                $refund = -1 * round(((float) $e->extention_calculated_value) * $factor, 2);
                $e->update([
                    'pro_rate_premium'    => $refund,
                    'endors_flag'         => 1,
                    'previousActionIdCov' => $this->actionId,
                ]);
            }
            $e->delete();
        }

        if ($isEndorse) {
            PolicySpecifiedItem::where('policy_coverage_id', $policyCoverageId)
                ->update(['endors_flag' => '1', 'action_id' => $this->actionId]);
        }
        PolicySpecifiedItem::where('policy_coverage_id', $policyCoverageId)->delete();

        // Fidelity Guarantee (coverage_id 9) and any other coverage whose
        // premium lives in policy_coverages_data: this method previously left
        // that table untouched, so a cancelled Fidelity carried NO refund into
        // the endorse rate calc. Record the refund like the detail block above.
        // NOTE: PolicyCoveragesData has no SoftDeletes — do NOT delete the row
        // (a hard delete would lose the data and break reinstate). The parent
        // coverage is already soft-deleted, which hides it from live views.
        $fidelityData = PolicyCoveragesData::where('policyCoverageID', $policyCoverageId)->get();
        foreach ($fidelityData as $fd) {
            if ($applyRefund) {
                $refund = -1 * round(((float) $fd->premium) * $factor, 2);
                $fd->update([
                    'pro_rate_premium'    => $refund,
                    'endors_flag'         => 1,
                    'previousActionIdCov' => $this->actionId,
                ]);
            }
        }

        activity('Coverage Cancelled')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Coverage Cancelled - ' . $policyCoverageId);
    }

    public function reinstateCoverage($policyCoverageId)
    {
        $latestAction = PolicyAction::where('id', $this->actionId)->first();
        $isEndorse    = $latestAction && $latestAction->transaction_type === 'ENDORSE';
        $applyReapply = $isEndorse && $this->isComDomPolicy();
        $factor       = $applyReapply ? $this->proRataDayFactor() : 0.0;

        PolicyCoverage::withTrashed()->where('id', $policyCoverageId)
            ->update(['status' => '0', 'deleted_at' => null, 'updated_at' => now()]);

        $details = PolicyCoverageDetail::withTrashed()->where('policy_coverage_id', $policyCoverageId)->get();
        foreach ($details as $d) {
            $update = ['deleted_at' => null];
            if ($applyReapply) {
                $update['pro_rate_premium']    = round(((float) $d->calculated_value) * $factor, 2);
                $update['endors_flag']         = 1;
                $update['previousActionIdCov'] = $this->actionId;
            }
            $d->update($update);
        }

        $exts = PolicyExtentionDetails::withTrashed()->where('policy_coverage_id', $policyCoverageId)->get();
        foreach ($exts as $e) {
            $update = ['deleted_at' => null];
            if ($applyReapply) {
                $update['pro_rate_premium']    = round(((float) $e->extention_calculated_value) * $factor, 2);
                $update['endors_flag']         = 1;
                $update['previousActionIdCov'] = $this->actionId;
            }
            $e->update($update);
        }

        $reUpdate = ['deleted_at' => null];
        if ($isEndorse) {
            $reUpdate['endors_flag'] = '1';
            $reUpdate['action_id']   = $this->actionId;
        }
        PolicySpecifiedItem::withTrashed()->where('policy_coverage_id', $policyCoverageId)
            ->update($reUpdate);

        // Symmetric with cancelCoverage(): re-charge the Fidelity Guarantee /
        // policy_coverages_data premium on reinstate (positive pro_rate),
        // mirroring the detail block above. The parent coverage's deleted_at
        // is cleared above, so the COVERAGECANCEL rate calc no longer sums it.
        if ($applyReapply) {
            $fidelityData = PolicyCoveragesData::where('policyCoverageID', $policyCoverageId)->get();
            foreach ($fidelityData as $fd) {
                $fd->update([
                    'pro_rate_premium'    => round(((float) $fd->premium) * $factor, 2),
                    'endors_flag'         => 1,
                    'previousActionIdCov' => $this->actionId,
                ]);
            }
        }

        activity('Coverage Reinstated')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Coverage Reinstated - ' . $policyCoverageId);
    }
    public function cancelRiskAddress($riskAddressId)
    {
           AlphaDirectRiskAddress::where('id', $riskAddressId)
        ->update([
            'status' => 1,
            'updated_at' => now(),
        ]);

        PolicyCoverage::where('risk_address_id', $riskAddressId)
            ->whereNull('deleted_at') // optional safety
            ->update([
                'status' => '1',          // 1 = Cancelled
                'updated_at' => now(),
            ]);

        $this->dispatchBrowserEvent('notify', [
            'type' => 'success',
            'message' => 'Risk address cancelled successfully.'
        ]);
         activity('Risk Address')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Risk Address Cancelled');

    }

    public function reinstateRiskAddress($riskAddressId)
    {
           AlphaDirectRiskAddress::where('id', $riskAddressId)
        ->update([
            'status' => '0',
            'updated_at' => now(),
        ]);

        PolicyCoverage::where('risk_address_id', $riskAddressId)
            ->whereNull('deleted_at') // optional safety
            ->update([
                'status' => '0',          // 0 = Active
                'updated_at' => now(),
            ]);

        $this->dispatchBrowserEvent('notify', [
            'type' => 'success',
            'message' => 'Risk address reinstated successfully.'
        ]);
         activity('Risk Address')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Risk Address Reinstated');
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
        // Clone the master coverage definition so the description carries over
        $sub_coverage = CoverageMaster::with('tbValidOptionsCoverage')->find($sub_coverageId);

        if ($sub_coverage) {
            // Create a new instance of the model
            $clonedModel = $sub_coverage->replicate();
            $clonedModel->save();

            // Clone each relation
            if ($sub_coverage->tbValidOptionsCoverage) {
                $tbValidOptionsCoverage = $sub_coverage->tbValidOptionsCoverage->replicate();
                $tbValidOptionsCoverage->n_SourceOneFK = $clonedModel->id;
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

    public function loadCarCoverageData($policyCoverageId)
    {
        $existingData = CarCoverageModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        if ($existingData) {
            $this->carCoverage[$policyCoverageId] = $this->formatCoverageDatesForDisplay(
                $existingData->toArray(),
                $this->carCoverageDateFields
            );
            unset($this->carCoverage[$policyCoverageId]['id']);
            unset($this->carCoverage[$policyCoverageId]['created_at']);
            unset($this->carCoverage[$policyCoverageId]['updated_at']);

            // Set today's date if empty
            if (empty($this->carCoverage[$policyCoverageId]['today_date'])) {
                $this->carCoverage[$policyCoverageId]['today_date'] = date('d/m/Y');
            }

            // Set default value for section3_insured_interest if empty
            if (empty($this->carCoverage[$policyCoverageId]['section3_insured_interest'])) {
                $this->carCoverage[$policyCoverageId]['section3_insured_interest'] = 'Gross profit and increased cost of working';
            }

            // Auto-populate insured_name from the Organisation company name only.
            // Individual policies are left untouched (parity with the frontend
            // Organisation-only gate) so an edit-mode reload never overwrites the
            // Insured Name with the policyholder's personal name.
            if (empty($this->carCoverage[$policyCoverageId]['insured_name'])) {
                $organisationInsuredName = $this->getOrganisationInsuredName();
                if ($organisationInsuredName !== '') {
                    $this->carCoverage[$policyCoverageId]['insured_name'] = $organisationInsuredName;
                }
            }

            // Auto-populate address fields if empty
            if (empty($this->carCoverage[$policyCoverageId]['insured_street'])) {
                $customerAddress = $this->getCustomerAddress();
                $this->carCoverage[$policyCoverageId]['insured_street'] = $customerAddress;
            }
            if (empty($this->carCoverage[$policyCoverageId]['insured_postal_code'])) {
                $customerPostalCode = $this->getCustomerPostalCode();
                $this->carCoverage[$policyCoverageId]['insured_postal_code'] = $customerPostalCode;
            }
            if (empty($this->carCoverage[$policyCoverageId]['risk_street'])) {
                $customerAddress = $this->getCustomerAddress();
                $this->carCoverage[$policyCoverageId]['risk_street'] = $customerAddress;
            }
            if (empty($this->carCoverage[$policyCoverageId]['risk_postal_code'])) {
                $customerPostalCode = $this->getCustomerPostalCode();
                $this->carCoverage[$policyCoverageId]['risk_postal_code'] = $customerPostalCode;
            }
            if (empty($this->carCoverage[$policyCoverageId]['city_town_village'])) {
                $this->carCoverage[$policyCoverageId]['city_town_village'] = $this->getCustomerPostalCode();
            }

            // Load section items from JSON
            if (!empty($existingData->section1_items)) {
                $this->carSection1Items[$policyCoverageId] = json_decode($existingData->section1_items, true) ?? [0 => []];
            } else {
                $this->carSection1Items[$policyCoverageId] = [0 => []];
            }

            if (!empty($existingData->section2_items)) {
                $decodedItems = json_decode($existingData->section2_items, true);
                // Backward compatibility: if items have 'description' but no 'item_type', use description as item_type
                if (is_array($decodedItems)) {
                    foreach ($decodedItems as $key => $item) {
                        if (isset($item['description']) && !isset($item['item_type'])) {
                            $decodedItems[$key]['item_type'] = $item['description'];
                        }
                    }
                }
                $this->carSection2Items[$policyCoverageId] = !empty($decodedItems) ? $decodedItems : [0 => ['item_type' => '']];
            } else {
                $this->carSection2Items[$policyCoverageId] = [0 => ['item_type' => '']];
            }

            if (!empty($existingData->section3_items)) {
                $this->carSection3Items[$policyCoverageId] = json_decode($existingData->section3_items, true) ?? [0 => []];
            } else {
                $this->carSection3Items[$policyCoverageId] = [0 => []];
            }

            // Load section 3 contract works from JSON
            if (!empty($existingData->section3_contract_works)) {
                $this->carSection3ContractWorks[$policyCoverageId] = json_decode($existingData->section3_contract_works, true) ?? [0 => []];
            } else {
                $this->carSection3ContractWorks[$policyCoverageId] = [0 => []];
            }

            // Load plant list items from JSON
            if (!empty($existingData->plant_list_items)) {
                $this->carPlantListItems[$policyCoverageId] = json_decode($existingData->plant_list_items, true) ?? [0 => []];
            } else {
                $this->carPlantListItems[$policyCoverageId] = [0 => []];
            }

            // Format numeric fields for display (limit of indemnity, premiums, etc.)
            $numericFieldsForDisplay = [
                'section1_earthquake_limit_indemnity',
                'section1_storm_limit_indemnity',
                'section1_earthquake_premium',
                'section1_storm_premium',
                'section3_gross_profit_annual_sum_insured',
                'section3_gross_profit_premium',
                'section3_increased_cost_sum_insured',
                'section3_increased_cost_premium',
            ];
            
            foreach ($numericFieldsForDisplay as $field) {
                if (isset($this->carCoverage[$policyCoverageId][$field])) {
                    $value = $this->carCoverage[$policyCoverageId][$field];
                    
                    // Skip if already formatted (contains comma) or is empty
                    if (empty($value) || $value === null || $value === '') {
                        continue;
                    }
                    
                    // If already contains comma, it's likely already formatted
                    if (strpos($value, ',') !== false) {
                        continue;
                    }
                    
                    // Clean and format the value
                    $cleaned = preg_replace('/[^\d.-]/', '', (string) $value);
                    if ($cleaned !== '' && $cleaned !== null) {
                        $floatValue = (float) $cleaned;
                        if ($floatValue > 0 || $floatValue == 0) {
                            $this->carCoverage[$policyCoverageId][$field] = number_format($floatValue, 2, '.', ',');
                        } else {
                            $this->carCoverage[$policyCoverageId][$field] = '';
                        }
                    } else {
                        $this->carCoverage[$policyCoverageId][$field] = '';
                    }
                }
            }

            // Initialize checkbox values if not set
            if (!isset($this->carCoverage[$policyCoverageId]['reinsurance_fire_treaty'])) {
                $this->carCoverage[$policyCoverageId]['reinsurance_fire_treaty'] = false;
            }

            // Load endorsements from JSON (stored in endorsement_1) or individual columns (backward compatibility)
            $endorsements = [];
            
            // Try to load from JSON first (new format)
            if (!empty($existingData->endorsement_1)) {
                $decoded = json_decode($existingData->endorsement_1, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Valid JSON - use it
                    $endorsements = $decoded;
                } else {
                    // Not JSON - backward compatibility: load from individual columns
                    if (!empty($existingData->endorsement_1)) {
                        $endorsements[] = ['text' => $existingData->endorsement_1];
                    }
                    if (!empty($existingData->endorsement_2)) {
                        $endorsements[] = ['text' => $existingData->endorsement_2];
                    }
                    if (!empty($existingData->endorsement_3)) {
                        $endorsements[] = ['text' => $existingData->endorsement_3];
                    }
                    if (!empty($existingData->endorsement_4)) {
                        // Check if endorsement_4 contains concatenated endorsements (separated by ';')
                        $endorsement4Text = $existingData->endorsement_4;
                        if (strpos($endorsement4Text, ';') !== false) {
                            // Split by semicolon and add each as separate endorsement
                            $parts = explode(';', $endorsement4Text);
                            foreach ($parts as $part) {
                                $part = trim($part);
                                if (!empty($part)) {
                                    $endorsements[] = ['text' => $part];
                                }
                            }
                        } else {
                            $endorsements[] = ['text' => $endorsement4Text];
                        }
                    }
                }
            }
            
            // If no endorsements found, initialize with one empty row
            if (empty($endorsements)) {
                $endorsements = [0 => ['text' => '']];
            }
            $this->carEndorsements[$policyCoverageId] = $endorsements;
        } else {
            // Initialize with policy defaults for new coverage
            $customerAddress = $this->getCustomerAddress();
            $customerPostalCode = $this->getCustomerPostalCode();
            
            // Get policy term dates
            $termDates = $this->getPolicyTermDates();
            
            // Calculate months and policy period
            $months = null;
            $policyPeriodMonths = null;
            if ($termDates) {
                $months = $this->calculateMonthsBetweenDates($termDates['term_start_date'], $termDates['term_end_date']);
                $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
            }
            
            $this->carCoverage[$policyCoverageId] = [
                'policy_no' => $this->policy->policyNumber ?? '',
                // Organisation-only gate (parity with the frontend): blank for
                // Individuals so the operator types the trading/project name.
                'insured_name' => $this->getOrganisationInsuredName(),
                'insured_street' => $customerAddress,
                'insured_postal_code' => $customerPostalCode,
                'risk_street' => $customerAddress,
                'risk_postal_code' => $customerPostalCode,
                'city_town_village' => $customerPostalCode,
                'currency' => 'BWP',
                'today_date' => date('d/m/Y'),
                'section3_insured_interest' => 'Gross profit and increased cost of working',
                'policy_inception_date' => $termDates['term_start_date'] ?? '',
                'policy_expiry_date' => $termDates['term_end_date'] ?? '',
                'policy_period_months' => $policyPeriodMonths ?? '',
            ];

            // Initialize with one empty row for each section
            $this->carSection1Items[$policyCoverageId] = [0 => []];
            $this->carSection2Items[$policyCoverageId] = [0 => ['item_type' => '']];
            $this->carSection3Items[$policyCoverageId] = [0 => []];
            $this->carSection3ContractWorks[$policyCoverageId] = [0 => []];
            $this->carPlantListItems[$policyCoverageId] = [0 => []];
            $this->carEndorsements[$policyCoverageId] = [0 => ['text' => '']];

            // Initialize checkbox values
            if (!isset($this->carCoverage[$policyCoverageId]['reinsurance_fire_treaty'])) {
                $this->carCoverage[$policyCoverageId]['reinsurance_fire_treaty'] = false;
            }
        }
        
        // Auto-populate dates and policy period if not already set (for existing data)
        if ($existingData) {
            $termDates = $this->getPolicyTermDates();
            
            // Auto-populate policy dates if empty
            if (empty($this->carCoverage[$policyCoverageId]['policy_inception_date']) && $termDates) {
                $this->carCoverage[$policyCoverageId]['policy_inception_date'] = $termDates['term_start_date'];
            }
            if (empty($this->carCoverage[$policyCoverageId]['policy_expiry_date']) && $termDates) {
                $this->carCoverage[$policyCoverageId]['policy_expiry_date'] = $termDates['term_end_date'];
            }
            
            // Auto-populate policy period if empty
            if (empty($this->carCoverage[$policyCoverageId]['policy_period_months']) && $termDates) {
                $months = $this->calculateMonthsBetweenDates(
                    $this->carCoverage[$policyCoverageId]['policy_inception_date'] ?? $termDates['term_start_date'],
                    $this->carCoverage[$policyCoverageId]['policy_expiry_date'] ?? $termDates['term_end_date']
                );
                $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
                if ($policyPeriodMonths) {
                    $this->carCoverage[$policyCoverageId]['policy_period_months'] = $policyPeriodMonths;
                }
            }
        }
    }

    public function loadParCoverageData($policyCoverageId)
    {
        $existingData = ParCoverageModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->where('action_id', $this->actionId)
            ->where('term_id', $this->termId)
            ->first();

        if ($existingData) {
            $this->parCoverage[$policyCoverageId] = $this->formatCoverageDatesForDisplay(
                $existingData->toArray(),
                $this->parCoverageDateFields
            );
            unset($this->parCoverage[$policyCoverageId]['id']);
            unset($this->parCoverage[$policyCoverageId]['created_at']);
            unset($this->parCoverage[$policyCoverageId]['updated_at']);

            // Load insured items from JSON
            if (!empty($existingData->insured_items)) {
                $this->parInsuredItems[$policyCoverageId] = json_decode($existingData->insured_items, true) ?? [0 => []];
            } else {
                $this->parInsuredItems[$policyCoverageId] = [0 => []];
            }

            if (!empty($existingData->section2_items)) {
                $decodedItems = json_decode($existingData->section2_items, true);
                $this->parSection2Items[$policyCoverageId] = !empty($decodedItems) ? $decodedItems : [0 => ['item_type' => 'Bodily Injury']];
            } else {
                $this->parSection2Items[$policyCoverageId] = [0 => ['item_type' => 'Bodily Injury']];
            }

            // Initialize checkbox values for existing data
            if (!isset($this->parCoverage[$policyCoverageId]['reinsurance_fire_treaty'])) {
                $this->parCoverage[$policyCoverageId]['reinsurance_fire_treaty'] = false;
            }
        } else {
            // Initialize with policy defaults for new coverage
            // Get policy term dates
            $termDates = $this->getPolicyTermDates();
            
            // Calculate months and policy period
            $months = null;
            $policyPeriodMonths = null;
            if ($termDates) {
                $months = $this->calculateMonthsBetweenDates($termDates['term_start_date'], $termDates['term_end_date']);
                $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
            }
            
            $this->parCoverage[$policyCoverageId] = [
                'policy_period_months' => $policyPeriodMonths ?? '',
            ];

            // Initialize with one empty row
            $this->parInsuredItems[$policyCoverageId] = [0 => []];
            $this->parSection2Items[$policyCoverageId] = [0 => ['item_type' => 'Bodily Injury']];

            // Initialize checkbox values
            if (!isset($this->parCoverage[$policyCoverageId]['reinsurance_fire_treaty'])) {
                $this->parCoverage[$policyCoverageId]['reinsurance_fire_treaty'] = false;
            }
        }
        
        // Auto-populate policy period if not already set (for existing data)
        if ($existingData) {
            $termDates = $this->getPolicyTermDates();
            
            // Auto-populate policy period if empty
            if (empty($this->parCoverage[$policyCoverageId]['policy_period_months']) && $termDates) {
                $months = $this->calculateMonthsBetweenDates($termDates['term_start_date'], $termDates['term_end_date']);
                $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
                if ($policyPeriodMonths) {
                    $this->parCoverage[$policyCoverageId]['policy_period_months'] = $policyPeriodMonths;
                }
            }
        }
    }

    public function loadEarCoverageData($policyCoverageId)
    {
        $existingData = EarCoverageModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        if ($existingData) {
            $this->earCoverage[$policyCoverageId] = $this->formatCoverageDatesForDisplay(
                $existingData->toArray(),
                $this->earCoverageDateFields
            );
            unset($this->earCoverage[$policyCoverageId]['id']);
            unset($this->earCoverage[$policyCoverageId]['created_at']);
            unset($this->earCoverage[$policyCoverageId]['updated_at']);

            // Load section items from JSON
            if (!empty($existingData->section1_items)) {
                $this->earSection1Items[$policyCoverageId] = json_decode($existingData->section1_items, true) ?? [0 => []];
            } else {
                $this->earSection1Items[$policyCoverageId] = [0 => []];
            }

            if (!empty($existingData->section3_items)) {
                $decodedItems = json_decode($existingData->section3_items, true);
                $this->earSection3Items[$policyCoverageId] = !empty($decodedItems) ? $decodedItems : [0 => ['item_type' => 'Bodily Injury']];
            } else {
                $this->earSection3Items[$policyCoverageId] = [0 => ['item_type' => 'Bodily Injury']];
            }

            // Load endorsements from JSON (stored in endorsement_1) or individual columns (backward compatibility)
            $endorsements = [];
            
            // Try to load from JSON first (new format)
            if (!empty($existingData->endorsement_1)) {
                $decoded = json_decode($existingData->endorsement_1, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Valid JSON - use it
                    $endorsements = $decoded;
                } else {
                    // Not JSON - backward compatibility: load from individual columns
                    if (!empty($existingData->endorsement_1)) {
                        $endorsements[] = ['text' => $existingData->endorsement_1];
                    }
                    if (!empty($existingData->endorsement_2)) {
                        $endorsements[] = ['text' => $existingData->endorsement_2];
                    }
                    if (!empty($existingData->endorsement_3)) {
                        $endorsements[] = ['text' => $existingData->endorsement_3];
                    }
                    if (!empty($existingData->endorsement_4)) {
                        // Check if endorsement_4 contains concatenated endorsements (separated by ';')
                        $endorsement4Text = $existingData->endorsement_4;
                        if (strpos($endorsement4Text, ';') !== false) {
                            // Split by semicolon and add each as separate endorsement
                            $parts = explode(';', $endorsement4Text);
                            foreach ($parts as $part) {
                                $part = trim($part);
                                if (!empty($part)) {
                                    $endorsements[] = ['text' => $part];
                                }
                            }
                        } else {
                            $endorsements[] = ['text' => $endorsement4Text];
                        }
                    }
                }
            }
            
            // If no endorsements found, initialize with one empty row
            if (empty($endorsements)) {
                $endorsements = [0 => ['text' => '']];
            }
            $this->earEndorsements[$policyCoverageId] = $endorsements;

            // Format numeric fields for display (limit of indemnity, premiums, etc.)
            $numericFieldsForDisplay = [
                'risk_earthquake_limit_indemnity',
                'risk_storm_limit_indemnity',
                'risk_earthquake_premium',
                'risk_storm_premium',
            ];
            
            foreach ($numericFieldsForDisplay as $field) {
                if (isset($this->earCoverage[$policyCoverageId][$field])) {
                    $value = $this->earCoverage[$policyCoverageId][$field];
                    
                    // Skip if already formatted (contains comma) or is empty
                    if (empty($value) || $value === null || $value === '') {
                        continue;
                    }
                    
                    // If already contains comma, it's likely already formatted
                    if (strpos($value, ',') !== false) {
                        continue;
                    }
                    
                    // Clean and format the value
                    $cleaned = preg_replace('/[^\d.-]/', '', (string) $value);
                    if ($cleaned !== '' && $cleaned !== null) {
                        $floatValue = (float) $cleaned;
                        if ($floatValue > 0 || $floatValue == 0) {
                            $this->earCoverage[$policyCoverageId][$field] = number_format($floatValue, 2, '.', ',');
                        } else {
                            $this->earCoverage[$policyCoverageId][$field] = '';
                        }
                    } else {
                        $this->earCoverage[$policyCoverageId][$field] = '';
                    }
                }
            }

            // Initialize checkbox values for existing data
            if (!isset($this->earCoverage[$policyCoverageId]['reinsurance_fire_treaty'])) {
                $this->earCoverage[$policyCoverageId]['reinsurance_fire_treaty'] = false;
            }

            // Auto-populate name_of_insured for Organisation customers only
            // (parity with CAR/MB and the frontend gate). Individuals are left
            // blank for the operator to type the project/trading name.
            $organisationInsuredName = $this->getOrganisationInsuredName();
            if (empty($this->earCoverage[$policyCoverageId]['name_of_insured']) && $organisationInsuredName !== '') {
                $this->earCoverage[$policyCoverageId]['name_of_insured'] = $organisationInsuredName;
            }
            
            // Auto-populate policy period if not already set
            $termDates = $this->getPolicyTermDates();
            if (empty($this->earCoverage[$policyCoverageId]['policy_period_months']) && $termDates) {
                $months = $this->calculateMonthsBetweenDates($termDates['term_start_date'], $termDates['term_end_date']);
                $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
                if ($policyPeriodMonths) {
                    $this->earCoverage[$policyCoverageId]['policy_period_months'] = $policyPeriodMonths;
                }
            }

            // Auto-populate Period of Insurance (period_from / period_to) from policy term dates if empty
            if ($termDates) {
                if (empty($this->earCoverage[$policyCoverageId]['period_from'])) {
                    $this->earCoverage[$policyCoverageId]['period_from'] = $termDates['term_start_date'];
                }
                if (empty($this->earCoverage[$policyCoverageId]['period_to'])) {
                    $this->earCoverage[$policyCoverageId]['period_to'] = $termDates['term_end_date'];
                }
            }
        } else {
            // Initialize with policy defaults for new coverage.
            // Insured Name is Organisation-only (parity with CAR/MB); blank for Individuals.
            $organisationInsuredName = $this->getOrganisationInsuredName();
            
            // Get policy term dates
            $termDates = $this->getPolicyTermDates();
            
            // Calculate months and policy period
            $months = null;
            $policyPeriodMonths = null;
            if ($termDates) {
                $months = $this->calculateMonthsBetweenDates($termDates['term_start_date'], $termDates['term_end_date']);
                $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
            }
            
            $this->earCoverage[$policyCoverageId] = [
                'name_of_insured' => $organisationInsuredName,
                'policy_period_months' => $policyPeriodMonths ?? '',
                'period_from' => $termDates['term_start_date'] ?? '',
                'period_to' => $termDates['term_end_date'] ?? '',
            ];

            // Initialize with one empty row for each section
            $this->earSection1Items[$policyCoverageId] = [0 => []];
            $this->earSection3Items[$policyCoverageId] = [0 => ['item_type' => 'Bodily Injury']];
            $this->earEndorsements[$policyCoverageId] = [0 => ['text' => '']];

            // Initialize checkbox values
            if (!isset($this->earCoverage[$policyCoverageId]['reinsurance_fire_treaty'])) {
                $this->earCoverage[$policyCoverageId]['reinsurance_fire_treaty'] = false;
            }
        }
    }

    public function loadDirectorsOfficersLiabilityData($policyCoverageId)
    {
        $existing = DOLiabilityModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        if ($existing) {
            $row = $this->formatCoverageDatesForDisplay(
                $existing->toArray(),
                $this->dolDateFields
            );
            unset($row['id'], $row['created_at'], $row['updated_at'], $row['deleted_at']);
            unset($row['insuring_clauses'], $row['extensions'], $row['coverage_extensions']);

            $this->directorsOfficersLiability[$policyCoverageId] = $row;

            $clauses = $existing->insuring_clauses;
            if (is_string($clauses)) {
                $clauses = json_decode($clauses, true);
            }
            $this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId] = is_array($clauses) ? array_values($clauses) : [];

            $exts = $existing->extensions;
            if (is_string($exts)) {
                $exts = json_decode($exts, true);
            }
            $this->directorsOfficersLiabilityExtensions[$policyCoverageId] = is_array($exts) ? array_values($exts) : [];

            $covExts = $existing->coverage_extensions;
            if (is_string($covExts)) {
                $covExts = json_decode($covExts, true);
            }
            $this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId] = is_array($covExts) ? array_values($covExts) : [];
        } else {
            $this->directorsOfficersLiability[$policyCoverageId] = [];
            $this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId] = [];
            $this->directorsOfficersLiabilityExtensions[$policyCoverageId] = [];
            $this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId] = [];
        }
    }

    public function addDirectorsOfficersLiabilityInsuringClause($policyCoverageId)
    {
        if (!isset($this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId])) {
            $this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId] = [];
        }
        $this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId][] = [
            'section' => '',
            'name' => '',
            'included' => '',
            'limit_of_liability' => '',
            'retention' => '',
        ];
    }

    public function removeDirectorsOfficersLiabilityInsuringClause($policyCoverageId, $index)
    {
        if (isset($this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId][$index])) {
            unset($this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId][$index]);
            $this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId] = array_values(
                $this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId]
            );
        }
    }

    public function addDirectorsOfficersLiabilityExtension($policyCoverageId)
    {
        if (!isset($this->directorsOfficersLiabilityExtensions[$policyCoverageId])) {
            $this->directorsOfficersLiabilityExtensions[$policyCoverageId] = [];
        }
        $this->directorsOfficersLiabilityExtensions[$policyCoverageId][] = [
            'section' => '',
            'name' => '',
            'included' => '',
            'limit' => '',
            'retention' => '',
        ];
    }

    public function removeDirectorsOfficersLiabilityExtension($policyCoverageId, $index)
    {
        if (isset($this->directorsOfficersLiabilityExtensions[$policyCoverageId][$index])) {
            unset($this->directorsOfficersLiabilityExtensions[$policyCoverageId][$index]);
            $this->directorsOfficersLiabilityExtensions[$policyCoverageId] = array_values(
                $this->directorsOfficersLiabilityExtensions[$policyCoverageId]
            );
        }
    }

    public function addDirectorsOfficersLiabilityCoverageExtension($policyCoverageId)
    {
        if (!isset($this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId])) {
            $this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId] = [];
        }
        $this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId][] = [
            'section' => '',
            'name' => '',
            'included' => '',
        ];
    }

    public function removeDirectorsOfficersLiabilityCoverageExtension($policyCoverageId, $index)
    {
        if (isset($this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId][$index])) {
            unset($this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId][$index]);
            $this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId] = array_values(
                $this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId]
            );
        }
    }

    public function loadMarineOnceOffCoverData($policyCoverageId)
    {
        $existing = MarineOnceOffCoverModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        if ($existing) {
            $row = $this->formatCoverageDatesForDisplay(
                $existing->toArray(),
                $this->marineOnceOffCoverDateFields
            );
            unset($row['id'], $row['created_at'], $row['updated_at'], $row['deleted_at']);
            unset($row['clauses']);

            $this->marineOnceOffCover[$policyCoverageId] = $row;

            $clauses = $existing->clauses;
            if (is_string($clauses)) {
                $clauses = json_decode($clauses, true);
            }
            $this->marineOnceOffCoverClauses[$policyCoverageId] = is_array($clauses) ? $clauses : [];
        } else {
            $this->marineOnceOffCover[$policyCoverageId] = [];
            $this->marineOnceOffCoverClauses[$policyCoverageId] = [];
        }

        $termDates = $this->getPolicyTermDates();
        $customerName = $this->getCustomerName();
        if (empty($this->marineOnceOffCover[$policyCoverageId]['assured_name']) && $customerName !== '') {
            $this->marineOnceOffCover[$policyCoverageId]['assured_name'] = $customerName;
        }
        if (empty($this->marineOnceOffCover[$policyCoverageId]['policy_period_from']) && $termDates) {
            $this->marineOnceOffCover[$policyCoverageId]['policy_period_from'] = $termDates['term_start_date'];
        }
        if (empty($this->marineOnceOffCover[$policyCoverageId]['policy_period_to']) && $termDates) {
            $this->marineOnceOffCover[$policyCoverageId]['policy_period_to'] = $termDates['term_end_date'];
        }
    }

    public function loadMarineOpenCoverData($policyCoverageId)
    {
        $existing = MarineOpenCoverModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        if ($existing) {
            $row = $this->formatCoverageDatesForDisplay(
                $existing->toArray(),
                $this->marineOpenCoverDateFields
            );
            unset($row['id'], $row['created_at'], $row['updated_at'], $row['deleted_at']);
            unset($row['clauses'], $row['misc_items']);

            $this->marineOpenCover[$policyCoverageId] = $row;

            $clauses = $existing->clauses;
            if (is_string($clauses)) {
                $clauses = json_decode($clauses, true);
            }
            $this->marineOpenCoverClauses[$policyCoverageId] = is_array($clauses) ? $clauses : [];

            $miscItems = $existing->misc_items;
            if (is_string($miscItems)) {
                $miscItems = json_decode($miscItems, true);
            }
            $this->marineOpenCoverMiscItems[$policyCoverageId] = is_array($miscItems) ? $miscItems : [];
        } else {
            $this->marineOpenCover[$policyCoverageId] = [];
            $this->marineOpenCoverClauses[$policyCoverageId] = [];
            $this->marineOpenCoverMiscItems[$policyCoverageId] = [];
        }
    }

    public function loadMarineDirectorsOfficersData($policyCoverageId)
    {
        $existing = MarineDirectorsOfficersModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        $defaultSection1Items = [
            ['name' => 'Damage to insured property (per occurrence)', 'limit_status' => '', 'premium' => ''],
            ['name' => 'Cost of replacing undamaged non-compatible parts', 'limit_status' => '', 'premium' => ''],
            ['name' => 'Total insured value', 'limit_status' => '', 'premium' => ''],
        ];
        $defaultExtraCoverS1 = [
            ['name' => 'Contamination', 'limit' => ''],
            ['name' => 'Emergency Services', 'limit' => ''],
            ['name' => 'Energy Efficiency Improvements', 'limit' => ''],
            ['name' => 'Hazardous Substances', 'limit' => ''],
            ['name' => 'Hire Charges for Substitute Equipment', 'limit' => ''],
            ['name' => 'Lifted Goods', 'limit' => ''],
            ['name' => 'Movement of Insured Property', 'limit' => ''],
            ['name' => 'Newly Acquired Property', 'limit' => ''],
            ['name' => 'Own Surrounding Property', 'limit' => ''],
            ['name' => 'Public Authorities Requirements', 'limit' => ''],
            ['name' => 'Removing Debris', 'limit' => ''],
            ['name' => 'Storage Tank Contents', 'limit' => ''],
            ['name' => 'Temporary and Fast-Tracked Repair', 'limit' => ''],
            ['name' => 'Temporary Plant', 'limit' => ''],
        ];
        $defaultSection2Items = [
            ['name' => 'Deterioration of insured stock', 'value' => '', 'rate' => '', 'premium' => ''],
            ['name' => 'Type of cold chamber / Location / Max value of insured stock', 'value' => '', 'rate' => '', 'premium' => ''],
        ];
        $defaultExtraCoverS2 = [
            ['name' => 'Cleaning and Disinfection', 'limit' => ''],
            ['name' => 'Disposal of Insured Stock', 'limit' => ''],
            ['name' => 'Refrigerated Vehicles', 'limit' => ''],
        ];
        $defaultSection3Items = [
            ['name' => 'Financial loss during indemnity period (per occurrence)', 'value' => '', 'rate' => '', 'premium' => ''],
            ['name' => 'Estimated Gross Income', 'value' => '', 'rate' => '', 'premium' => ''],
            ['name' => 'Indemnity Period', 'value' => '', 'rate' => '', 'premium' => ''],
        ];
        $defaultExtraCoverS3 = [
            ['name' => 'Anchor Location', 'limit' => ''],
            ['name' => 'Brands and Labels', 'limit' => ''],
            ['name' => "Claims Preparation and Accountants' Fees", 'limit' => ''],
            ['name' => "Customer's Extension", 'limit' => ''],
            ['name' => 'Deterioration', 'limit' => ''],
            ['name' => 'Public Relations Costs', 'limit' => ''],
            ['name' => 'Public Utilities', 'limit' => ''],
            ['name' => 'Reinstatement of Data', 'limit' => ''],
            ['name' => "Supplier's Extension", 'limit' => ''],
        ];
        $defaultExtraCoverAll = [
            ['name' => 'Investigation Cost (per occurrence)', 'limit' => ''],
            ['name' => 'Loss Prevention Measures (per occurrence)', 'limit' => ''],
        ];
        $defaultExcessDetails = [
            ['name' => 'Equipment Damage and Breakdown (per occurrence)', 'value' => '', 'rate' => ''],
            ['name' => "Extra Cover 'Lifted Goods'", 'value' => '', 'rate' => ''],
            ['name' => 'Deterioration of Stock', 'value' => '', 'rate' => ''],
            ['name' => 'Loss of Income (time excess)', 'value' => '', 'rate' => ''],
            ['name' => 'Extra Cover Public Utilities - Franchise', 'value' => '', 'rate' => ''],
        ];

        if ($existing) {
            $row = $this->formatCoverageDatesForDisplay(
                $existing->toArray(),
                $this->marineDirectorsOfficersDateFields
            );
            unset($row['id'], $row['created_at'], $row['updated_at'], $row['deleted_at']);
            unset(
                $row['section1_items'],
                $row['insured_persons_listing'],
                $row['extra_cover_section1'],
                $row['section2_items'],
                $row['extra_cover_section2'],
                $row['section3_items'],
                $row['extra_cover_section3'],
                $row['extra_cover_all_sections'],
                $row['excess_details'],
                $row['misc_items'],
                $row['endorsements']
            );

            $this->marineDirectorsOfficers[$policyCoverageId] = $row;
            $this->marineDirectorsOfficersEndorsements[$policyCoverageId] = $existing->endorsements ?? '';

            $sec1 = $existing->section1_items;
            if (is_string($sec1)) { $sec1 = json_decode($sec1, true); }
            $this->marineDirectorsOfficersSection1Items[$policyCoverageId] = (is_array($sec1) && !empty($sec1)) ? array_values($sec1) : $defaultSection1Items;

            $listing = $existing->insured_persons_listing;
            if (is_string($listing)) { $listing = json_decode($listing, true); }
            $this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId] = is_array($listing) ? array_values($listing) : [];

            $ec1 = $existing->extra_cover_section1;
            if (is_string($ec1)) { $ec1 = json_decode($ec1, true); }
            $this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId] = (is_array($ec1) && !empty($ec1)) ? array_values($ec1) : $defaultExtraCoverS1;

            $sec2 = $existing->section2_items;
            if (is_string($sec2)) { $sec2 = json_decode($sec2, true); }
            $this->marineDirectorsOfficersSection2Items[$policyCoverageId] = (is_array($sec2) && !empty($sec2)) ? array_values($sec2) : $defaultSection2Items;

            $ec2 = $existing->extra_cover_section2;
            if (is_string($ec2)) { $ec2 = json_decode($ec2, true); }
            $this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId] = (is_array($ec2) && !empty($ec2)) ? array_values($ec2) : $defaultExtraCoverS2;

            $sec3 = $existing->section3_items;
            if (is_string($sec3)) { $sec3 = json_decode($sec3, true); }
            $this->marineDirectorsOfficersSection3Items[$policyCoverageId] = (is_array($sec3) && !empty($sec3)) ? array_values($sec3) : $defaultSection3Items;

            $ec3 = $existing->extra_cover_section3;
            if (is_string($ec3)) { $ec3 = json_decode($ec3, true); }
            $this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId] = (is_array($ec3) && !empty($ec3)) ? array_values($ec3) : $defaultExtraCoverS3;

            $ecAll = $existing->extra_cover_all_sections;
            if (is_string($ecAll)) { $ecAll = json_decode($ecAll, true); }
            $this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId] = (is_array($ecAll) && !empty($ecAll)) ? array_values($ecAll) : $defaultExtraCoverAll;

            $excess = $existing->excess_details;
            if (is_string($excess)) { $excess = json_decode($excess, true); }
            $this->marineDirectorsOfficersExcessDetails[$policyCoverageId] = (is_array($excess) && !empty($excess)) ? array_values($excess) : $defaultExcessDetails;

            $misc = $existing->misc_items;
            if (is_string($misc)) { $misc = json_decode($misc, true); }
            $this->marineDirectorsOfficersMiscItems[$policyCoverageId] = is_array($misc) ? array_values($misc) : [];
        } else {
            $this->marineDirectorsOfficers[$policyCoverageId] = [];
            $this->marineDirectorsOfficersEndorsements[$policyCoverageId] = '';
            $this->marineDirectorsOfficersSection1Items[$policyCoverageId] = $defaultSection1Items;
            $this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId] = [];
            $this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId] = $defaultExtraCoverS1;
            $this->marineDirectorsOfficersSection2Items[$policyCoverageId] = $defaultSection2Items;
            $this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId] = $defaultExtraCoverS2;
            $this->marineDirectorsOfficersSection3Items[$policyCoverageId] = $defaultSection3Items;
            $this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId] = $defaultExtraCoverS3;
            $this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId] = $defaultExtraCoverAll;
            $this->marineDirectorsOfficersExcessDetails[$policyCoverageId] = $defaultExcessDetails;
            $this->marineDirectorsOfficersMiscItems[$policyCoverageId] = [];
        }

        // Auto-fill the header fields from the policy customer/term when blank.
        // Company Name (Policyholder) fills for BOTH customer types: the
        // company name for Organisations, or the person's full name for
        // Individuals. Company Address fills from the policy customer;
        // Inception and Expiry default to the policy term dates. Existing
        // saved values are never overwritten.
        $policyholderName = $this->getCustomerName();
        $customerAddress = $this->getCustomerAddress();
        $termDates = $this->getPolicyTermDates();

        if (empty($this->marineDirectorsOfficers[$policyCoverageId]['company_name']) && $policyholderName !== '') {
            $this->marineDirectorsOfficers[$policyCoverageId]['company_name'] = $policyholderName;
        }
        if (empty($this->marineDirectorsOfficers[$policyCoverageId]['company_address']) && $customerAddress !== '') {
            $this->marineDirectorsOfficers[$policyCoverageId]['company_address'] = $customerAddress;
        }
        if (empty($this->marineDirectorsOfficers[$policyCoverageId]['inception_date']) && $termDates) {
            $this->marineDirectorsOfficers[$policyCoverageId]['inception_date'] = $termDates['term_start_date'];
        }
        if (empty($this->marineDirectorsOfficers[$policyCoverageId]['expiry_date']) && $termDates) {
            $this->marineDirectorsOfficers[$policyCoverageId]['expiry_date'] = $termDates['term_end_date'];
        }
        if (empty($this->marineDirectorsOfficers[$policyCoverageId]['policy_number'])) {
            $this->marineDirectorsOfficers[$policyCoverageId]['policy_number'] = $this->policy->policyNumber ?? '';
        }
        if (empty($this->marineDirectorsOfficers[$policyCoverageId]['today_date'])) {
            $this->marineDirectorsOfficers[$policyCoverageId]['today_date'] = date('d/m/Y');
        }
        if (empty($this->marineDirectorsOfficers[$policyCoverageId]['currency'])) {
            $this->marineDirectorsOfficers[$policyCoverageId]['currency'] = 'BWP';
        }
    }

    public function loadMachineryBreakdownData($policyCoverageId)
    {
        $existing = MachineryBreakdownModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        if ($existing) {
            $row = $this->formatCoverageDatesForDisplay(
                $existing->toArray(),
                $this->machineryBreakdownDateFields
            );
            unset($row['id'], $row['created_at'], $row['updated_at'], $row['deleted_at']);
            $this->machineryBreakdown[$policyCoverageId] = $row;
        } else {
            $this->machineryBreakdown[$policyCoverageId] = [];
        }

        $termDates = $this->getPolicyTermDates();
        $organisationInsuredName = $this->getOrganisationInsuredName();
        $customerAddress = $this->getCustomerAddress();

        if (empty($this->machineryBreakdown[$policyCoverageId]['policy_number'])) {
            $this->machineryBreakdown[$policyCoverageId]['policy_number'] = $this->policy->policyNumber ?? '';
        }
        // Organisation-only gate (parity with the frontend): the Insured Name
        // (company_name) autofills for Organisation customers only; Individuals
        // are left blank for the operator to complete. company_address below is
        // unaffected and still fills from the policy customer for both types.
        if (empty($this->machineryBreakdown[$policyCoverageId]['company_name']) && $organisationInsuredName !== '') {
            $this->machineryBreakdown[$policyCoverageId]['company_name'] = $organisationInsuredName;
        }
        if (empty($this->machineryBreakdown[$policyCoverageId]['company_address']) && $customerAddress !== '') {
            $this->machineryBreakdown[$policyCoverageId]['company_address'] = $customerAddress;
        }
        if (empty($this->machineryBreakdown[$policyCoverageId]['inception_date']) && $termDates) {
            $this->machineryBreakdown[$policyCoverageId]['inception_date'] = $termDates['term_start_date'];
        }
        if (empty($this->machineryBreakdown[$policyCoverageId]['expiry_date']) && $termDates) {
            $this->machineryBreakdown[$policyCoverageId]['expiry_date'] = $termDates['term_end_date'];
        }
        if (empty($this->machineryBreakdown[$policyCoverageId]['today_date'])) {
            $this->machineryBreakdown[$policyCoverageId]['today_date'] = date('d/m/Y');
        }
        if (empty($this->machineryBreakdown[$policyCoverageId]['currency'])) {
            $this->machineryBreakdown[$policyCoverageId]['currency'] = 'BWP';
        }
    }

    // Marine Directors & Officers Add/Remove Methods
    public function addMarineDirectorsOfficersMachineryItem($policyCoverageId)
    {
        if (!isset($this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId])) {
            $this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId] = [];
        }
        $count = count($this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId]);
        $this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId][] = [
            'item_no' => $count + 1,
            'quantity' => '',
            'description' => '',
            'year_of_manufacture' => '',
            'sum_insured' => '',
            'deductible' => '',
            'rate' => '',
            'premium' => '',
        ];
    }

    public function removeMarineDirectorsOfficersMachineryItem($policyCoverageId, $index)
    {
        if (isset($this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId][$index])) {
            unset($this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId][$index]);
            $this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId] = array_values(
                $this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId]
            );
        }
    }

    public function addMarineDirectorsOfficersExtraCoverSection1($policyCoverageId)
    {
        if (!isset($this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId])) {
            $this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId] = [];
        }
        $this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId][] = ['name' => '', 'limit' => ''];
    }

    public function removeMarineDirectorsOfficersExtraCoverSection1($policyCoverageId, $index)
    {
        if (isset($this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId][$index])) {
            unset($this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId][$index]);
            $this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId] = array_values(
                $this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId]
            );
        }
    }

    public function addMarineDirectorsOfficersExtraCoverSection2($policyCoverageId)
    {
        if (!isset($this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId])) {
            $this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId] = [];
        }
        $this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId][] = ['name' => '', 'limit' => ''];
    }

    public function removeMarineDirectorsOfficersExtraCoverSection2($policyCoverageId, $index)
    {
        if (isset($this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId][$index])) {
            unset($this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId][$index]);
            $this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId] = array_values(
                $this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId]
            );
        }
    }

    public function addMarineDirectorsOfficersExtraCoverSection3($policyCoverageId)
    {
        if (!isset($this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId])) {
            $this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId] = [];
        }
        $this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId][] = ['name' => '', 'limit' => ''];
    }

    public function removeMarineDirectorsOfficersExtraCoverSection3($policyCoverageId, $index)
    {
        if (isset($this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId][$index])) {
            unset($this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId][$index]);
            $this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId] = array_values(
                $this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId]
            );
        }
    }

    public function addMarineDirectorsOfficersExtraCoverAllSections($policyCoverageId)
    {
        if (!isset($this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId])) {
            $this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId] = [];
        }
        $this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId][] = ['name' => '', 'limit' => ''];
    }

    public function removeMarineDirectorsOfficersExtraCoverAllSections($policyCoverageId, $index)
    {
        if (isset($this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId][$index])) {
            unset($this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId][$index]);
            $this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId] = array_values(
                $this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId]
            );
        }
    }

    public function addMarineDirectorsOfficersExcessDetail($policyCoverageId)
    {
        if (!isset($this->marineDirectorsOfficersExcessDetails[$policyCoverageId])) {
            $this->marineDirectorsOfficersExcessDetails[$policyCoverageId] = [];
        }
        $this->marineDirectorsOfficersExcessDetails[$policyCoverageId][] = ['name' => '', 'value' => '', 'rate' => ''];
    }

    public function removeMarineDirectorsOfficersExcessDetail($policyCoverageId, $index)
    {
        if (isset($this->marineDirectorsOfficersExcessDetails[$policyCoverageId][$index])) {
            unset($this->marineDirectorsOfficersExcessDetails[$policyCoverageId][$index]);
            $this->marineDirectorsOfficersExcessDetails[$policyCoverageId] = array_values(
                $this->marineDirectorsOfficersExcessDetails[$policyCoverageId]
            );
        }
    }

    public function addMarineDirectorsOfficersMiscItem($policyCoverageId)
    {
        if (!isset($this->marineDirectorsOfficersMiscItems[$policyCoverageId])) {
            $this->marineDirectorsOfficersMiscItems[$policyCoverageId] = [];
        }
        $this->marineDirectorsOfficersMiscItems[$policyCoverageId][] = [
            'description' => '',
            'sum_insured' => '',
            'premium' => '',
        ];
    }

    public function removeMarineDirectorsOfficersMiscItem($policyCoverageId, $index)
    {
        if (isset($this->marineDirectorsOfficersMiscItems[$policyCoverageId][$index])) {
            unset($this->marineDirectorsOfficersMiscItems[$policyCoverageId][$index]);
            $this->marineDirectorsOfficersMiscItems[$policyCoverageId] = array_values(
                $this->marineDirectorsOfficersMiscItems[$policyCoverageId]
            );
            $this->recalculateMarineDirectorsOfficersPremium($policyCoverageId);
        }
    }

    public function recalculateMarineDirectorsOfficersPremium($policyCoverageId)
    {
        $total = 0;
        $sources = [
            $this->marineDirectorsOfficersSection1Items[$policyCoverageId] ?? [],
            $this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId] ?? [],
            $this->marineDirectorsOfficersSection2Items[$policyCoverageId] ?? [],
            $this->marineDirectorsOfficersSection3Items[$policyCoverageId] ?? [],
            $this->marineDirectorsOfficersMiscItems[$policyCoverageId] ?? [],
        ];
        foreach ($sources as $rows) {
            foreach ($rows as $row) {
                if (!empty($row['premium'])) {
                    $total += (float) str_replace(',', '', $row['premium']);
                }
            }
        }
        $this->marineDirectorsOfficers[$policyCoverageId]['premium'] = number_format($total, 2, '.', ',');
    }

    // CAR Section 1 Add/Remove Functions
    public function addCarSection1Row($policyCoverageId)
    {
        if (!isset($this->carSection1Items[$policyCoverageId])) {
            $this->carSection1Items[$policyCoverageId] = [];
        }
        $this->carSection1Items[$policyCoverageId][] = [];
    }

    public function removeCarSection1Row($policyCoverageId, $index)
    {
        unset($this->carSection1Items[$policyCoverageId][$index]);
        $this->carSection1Items[$policyCoverageId] = array_values($this->carSection1Items[$policyCoverageId]);
    }

    // CAR Section 2 Add/Remove Functions
    public function addCarSection2Row($policyCoverageId)
    {
        if (!isset($this->carSection2Items[$policyCoverageId])) {
            $this->carSection2Items[$policyCoverageId] = [];
        }
        $this->carSection2Items[$policyCoverageId][] = [];
    }

    public function removeCarSection2Row($policyCoverageId, $index)
    {
        unset($this->carSection2Items[$policyCoverageId][$index]);
        $this->carSection2Items[$policyCoverageId] = array_values($this->carSection2Items[$policyCoverageId]);
    }

    // CAR Section 3 Add/Remove Functions
    public function addCarSection3Row($policyCoverageId)
    {
        if (!isset($this->carSection3Items[$policyCoverageId])) {
            $this->carSection3Items[$policyCoverageId] = [];
        }
        $this->carSection3Items[$policyCoverageId][] = [];
    }

    public function removeCarSection3Row($policyCoverageId, $index)
    {
        unset($this->carSection3Items[$policyCoverageId][$index]);
        $this->carSection3Items[$policyCoverageId] = array_values($this->carSection3Items[$policyCoverageId]);
    }

    // CAR Section 3 Contract Works Add/Remove Functions
    public function addCarSection3ContractWorksRow($policyCoverageId)
    {
        if (!isset($this->carSection3ContractWorks[$policyCoverageId])) {
            $this->carSection3ContractWorks[$policyCoverageId] = [];
        }
        $this->carSection3ContractWorks[$policyCoverageId][] = [];
    }

    public function removeCarSection3ContractWorksRow($policyCoverageId, $index)
    {
        unset($this->carSection3ContractWorks[$policyCoverageId][$index]);
        $this->carSection3ContractWorks[$policyCoverageId] = array_values($this->carSection3ContractWorks[$policyCoverageId]);
    }

    // CAR Plant List Add/Remove Functions
    public function addCarPlantListItem($policyCoverageId)
    {
        if (!isset($this->carPlantListItems[$policyCoverageId])) {
            $this->carPlantListItems[$policyCoverageId] = [];
        }
        $this->carPlantListItems[$policyCoverageId][] = [];
    }

    public function removeCarPlantListItem($policyCoverageId, $index)
    {
        unset($this->carPlantListItems[$policyCoverageId][$index]);
        $this->carPlantListItems[$policyCoverageId] = array_values($this->carPlantListItems[$policyCoverageId]);
    }

    // CAR Endorsements Add/Remove Functions
    public function addCarEndorsementRow($policyCoverageId)
    {
        if (!isset($this->carEndorsements[$policyCoverageId])) {
            $this->carEndorsements[$policyCoverageId] = [];
        }
        $this->carEndorsements[$policyCoverageId][] = ['text' => ''];
    }

    public function removeCarEndorsementRow($policyCoverageId, $index)
    {
        unset($this->carEndorsements[$policyCoverageId][$index]);
        $this->carEndorsements[$policyCoverageId] = array_values($this->carEndorsements[$policyCoverageId]);
    }

    // PAR Add/Remove Functions
    public function addParInsuredItemRow($policyCoverageId)
    {
        if (!isset($this->parInsuredItems[$policyCoverageId])) {
            $this->parInsuredItems[$policyCoverageId] = [];
        }
        $this->parInsuredItems[$policyCoverageId][] = [];
    }

    public function removeParInsuredItemRow($policyCoverageId, $index)
    {
        unset($this->parInsuredItems[$policyCoverageId][$index]);
        $this->parInsuredItems[$policyCoverageId] = array_values($this->parInsuredItems[$policyCoverageId]);
    }

    public function calculateParItemPremium($policyCoverageId, $index)
    {
        if (!isset($this->parInsuredItems[$policyCoverageId][$index])) {
            return;
        }

        $item = &$this->parInsuredItems[$policyCoverageId][$index];
        
        // Get sum insured and rate values
        $sumInsured = 0;
        $rate = 0;
        
        if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
        }
        
        if (isset($item['rate']) && !empty($item['rate'])) {
            $rate = (float) $item['rate'];
        }
        
        // Calculate premium: Premium = (Sum Insured × Rate) / 100
        $premium = 0;
        if ($sumInsured > 0 && $rate > 0) {
            $premium = ($sumInsured * $rate) / 100;
        }
        
        // Update premium in the item
        // If sum insured has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $item['premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($sumInsured > 0 && $rate == 0) {
            $item['premium'] = '0';
        } else {
            $item['premium'] = '';
        }
    }

    /** When premium is changed: calculate rate = (Premium / Sum Insured) × 100 */
    public function calculateParItemRateFromPremium($policyCoverageId, $index)
    {
        if (!isset($this->parInsuredItems[$policyCoverageId][$index])) {
            return;
        }
        $item = &$this->parInsuredItems[$policyCoverageId][$index];
        $sumInsured = 0;
        $premium = 0;
        if (isset($item['sum_insured']) && $item['sum_insured'] !== '' && $item['sum_insured'] !== null) {
            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
        }
        if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
            $premium = (float) str_replace(',', '', $item['premium']);
        }
        if ($sumInsured > 0 && $premium > 0) {
            $rate = ($premium / $sumInsured) * 100;
            $item['rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    public function calculateParSection2ItemPremium($policyCoverageId, $index)
    {
        if (!isset($this->parSection2Items[$policyCoverageId][$index])) {
            return;
        }

        $item = &$this->parSection2Items[$policyCoverageId][$index];
        
        // Get limit of indemnity and rate values
        $limitOfIndemnity = 0;
        $rate = 0;
        
        if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
            $limitOfIndemnity = (float) str_replace(',', '', $item['limit_of_indemnity']);
        }
        
        if (isset($item['rate']) && !empty($item['rate'])) {
            $rate = (float) $item['rate'];
        }
        
        // Calculate premium: Premium = (Limit of Indemnity × Rate) / 100
        $premium = 0;
        if ($limitOfIndemnity > 0 && $rate > 0) {
            $premium = ($limitOfIndemnity * $rate) / 100;
        }
        
        // Update premium in the item
        // If limit of indemnity has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $item['premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($limitOfIndemnity > 0 && $rate == 0) {
            $item['premium'] = '0';
        } else {
            $item['premium'] = '';
        }
    }

    /** When premium is changed: calculate rate = (Premium / Limit of Indemnity) × 100 */
    public function calculateParSection2ItemRateFromPremium($policyCoverageId, $index)
    {
        if (!isset($this->parSection2Items[$policyCoverageId][$index])) {
            return;
        }
        $item = &$this->parSection2Items[$policyCoverageId][$index];
        $limitOfIndemnity = 0;
        $premium = 0;
        if (isset($item['limit_of_indemnity']) && $item['limit_of_indemnity'] !== '' && $item['limit_of_indemnity'] !== null) {
            $limitOfIndemnity = (float) str_replace(',', '', $item['limit_of_indemnity']);
        }
        if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
            $premium = (float) str_replace(',', '', $item['premium']);
        }
        if ($limitOfIndemnity > 0 && $premium > 0) {
            $rate = ($premium / $limitOfIndemnity) * 100;
            $item['rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    public function addParSection2Row($policyCoverageId)
    {
        if (!isset($this->parSection2Items[$policyCoverageId])) {
            $this->parSection2Items[$policyCoverageId] = [];
        }
        $this->parSection2Items[$policyCoverageId][] = [];
    }

    public function removeParSection2Row($policyCoverageId, $index)
    {
        unset($this->parSection2Items[$policyCoverageId][$index]);
        $this->parSection2Items[$policyCoverageId] = array_values($this->parSection2Items[$policyCoverageId]);
    }

    // EAR Section 1 Add/Remove Functions
    public function addEarSection1Row($policyCoverageId)
    {
        if (!isset($this->earSection1Items[$policyCoverageId])) {
            $this->earSection1Items[$policyCoverageId] = [];
        }
        $this->earSection1Items[$policyCoverageId][] = [];
    }

    public function removeEarSection1Row($policyCoverageId, $index)
    {
        unset($this->earSection1Items[$policyCoverageId][$index]);
        $this->earSection1Items[$policyCoverageId] = array_values($this->earSection1Items[$policyCoverageId]);
    }

    public function calculateEarSection1ItemPremium($policyCoverageId, $index)
    {
        if (!isset($this->earSection1Items[$policyCoverageId][$index])) {
            return;
        }

        $item = &$this->earSection1Items[$policyCoverageId][$index];
        
        // Get sum insured and rate values
        $sumInsured = 0;
        $rate = 0;
        
        if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
        }
        
        if (isset($item['rate']) && !empty($item['rate'])) {
            $rate = (float) $item['rate'];
        }
        
        // Calculate premium: Premium = (Sum Insured × Rate) / 100
        $premium = 0;
        if ($sumInsured > 0 && $rate > 0) {
            $premium = ($sumInsured * $rate) / 100;
        }
        
        // Update premium in the item
        // If sum insured has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $item['premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($sumInsured > 0 && $rate == 0) {
            $item['premium'] = '0';
        } else {
            $item['premium'] = '';
        }
        
        // Recalculate total premium
        $this->calculateEarTotalPremium($policyCoverageId);
    }

    /** When premium is changed: calculate rate = (Premium / Sum Insured) × 100 */
    public function calculateEarSection1ItemRateFromPremium($policyCoverageId, $index)
    {
        if (!isset($this->earSection1Items[$policyCoverageId][$index])) {
            return;
        }
        $item = &$this->earSection1Items[$policyCoverageId][$index];
        $sumInsured = 0;
        $premium = 0;
        if (isset($item['sum_insured']) && $item['sum_insured'] !== '' && $item['sum_insured'] !== null) {
            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
        }
        if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
            $premium = (float) str_replace(',', '', $item['premium']);
        }
        if ($sumInsured > 0 && $premium > 0) {
            $rate = ($premium / $sumInsured) * 100;
            $item['rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    public function calculateCarSection1ItemPremium($policyCoverageId, $index)
    {
        if (!isset($this->carSection1Items[$policyCoverageId][$index])) {
            return;
        }

        $item = &$this->carSection1Items[$policyCoverageId][$index];
        
        // Get sum insured and rate values
        $sumInsured = 0;
        $rate = 0;
        
        if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
        }
        
        if (isset($item['rate']) && !empty($item['rate'])) {
            $rate = (float) $item['rate'];
        }
        
        // Calculate premium: Premium = (Sum Insured × Rate) / 100
        $premium = 0;
        if ($sumInsured > 0 && $rate > 0) {
            $premium = ($sumInsured * $rate) / 100;
        }
        
        // Update premium in the item
        // If sum insured has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $item['premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($sumInsured > 0 && $rate == 0) {
            $item['premium'] = '0';
        } else {
            $item['premium'] = '';
        }
    }

    /** When premium is changed: calculate rate = (Premium / Sum Insured) × 100 */
    public function calculateCarSection1ItemRateFromPremium($policyCoverageId, $index)
    {
        if (!isset($this->carSection1Items[$policyCoverageId][$index])) {
            return;
        }
        $item = &$this->carSection1Items[$policyCoverageId][$index];
        $sumInsured = 0;
        $premium = 0;
        if (isset($item['sum_insured']) && $item['sum_insured'] !== '' && $item['sum_insured'] !== null) {
            $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
        }
        if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
            $premium = (float) str_replace(',', '', $item['premium']);
        }
        if ($sumInsured > 0 && $premium > 0) {
            $rate = ($premium / $sumInsured) * 100;
            $item['rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    public function calculateCarSection2ItemPremium($policyCoverageId, $index)
    {
        if (!isset($this->carSection2Items[$policyCoverageId][$index])) {
            return;
        }

        $item = &$this->carSection2Items[$policyCoverageId][$index];
        
        // Get limit of indemnity and rate values
        $limitOfIndemnity = 0;
        $rate = 0;
        
        if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
            $limitOfIndemnity = (float) str_replace(',', '', $item['limit_of_indemnity']);
        }
        
        if (isset($item['rate']) && !empty($item['rate'])) {
            $rate = (float) $item['rate'];
        }
        
        // Calculate premium: Premium = (Limit of Indemnity × Rate) / 100
        $premium = 0;
        if ($limitOfIndemnity > 0 && $rate > 0) {
            $premium = ($limitOfIndemnity * $rate) / 100;
        }
        
        // Update premium in the item
        // If limit of indemnity has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $item['premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($limitOfIndemnity > 0 && $rate == 0) {
            $item['premium'] = '0';
        } else {
            $item['premium'] = '';
        }
    }

    /** When premium is changed: calculate rate = (Premium / Limit of Indemnity) × 100 */
    public function calculateCarSection2ItemRateFromPremium($policyCoverageId, $index)
    {
        if (!isset($this->carSection2Items[$policyCoverageId][$index])) {
            return;
        }
        $item = &$this->carSection2Items[$policyCoverageId][$index];
        $limitOfIndemnity = 0;
        $premium = 0;
        if (isset($item['limit_of_indemnity']) && $item['limit_of_indemnity'] !== '' && $item['limit_of_indemnity'] !== null) {
            $limitOfIndemnity = (float) str_replace(',', '', $item['limit_of_indemnity']);
        }
        if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
            $premium = (float) str_replace(',', '', $item['premium']);
        }
        if ($limitOfIndemnity > 0 && $premium > 0) {
            $rate = ($premium / $limitOfIndemnity) * 100;
            $item['rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    public function calculateCarSection3GrossProfitPremium($policyCoverageId)
    {
        if (!isset($this->carCoverage[$policyCoverageId])) {
            return;
        }

        $coverage = &$this->carCoverage[$policyCoverageId];
        
        // Get annual sum insured and rate values
        $annualSumInsured = 0;
        $rate = 0;
        
        if (isset($coverage['section3_gross_profit_annual_sum_insured']) && !empty($coverage['section3_gross_profit_annual_sum_insured'])) {
            $annualSumInsured = (float) str_replace(',', '', $coverage['section3_gross_profit_annual_sum_insured']);
        }
        
        if (isset($coverage['section3_gross_profit_rate']) && !empty($coverage['section3_gross_profit_rate'])) {
            $rate = (float) $coverage['section3_gross_profit_rate'];
        }
        
        // Calculate premium: Premium = (Annual Sum Insured × Rate) / 100
        $premium = 0;
        if ($annualSumInsured > 0 && $rate > 0) {
            $premium = ($annualSumInsured * $rate) / 100;
        }
        
        // Update premium
        // If annual sum insured has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $coverage['section3_gross_profit_premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($annualSumInsured > 0 && $rate == 0) {
            $coverage['section3_gross_profit_premium'] = '0';
        } else {
            $coverage['section3_gross_profit_premium'] = '';
        }
    }

    /** When premium is changed: calculate rate = (Premium / Annual Sum Insured) × 100 */
    public function calculateCarSection3GrossProfitRateFromPremium($policyCoverageId)
    {
        if (!isset($this->carCoverage[$policyCoverageId])) {
            return;
        }
        $coverage = &$this->carCoverage[$policyCoverageId];
        $annualSumInsured = 0;
        $premium = 0;
        if (!empty($coverage['section3_gross_profit_annual_sum_insured'])) {
            $annualSumInsured = (float) str_replace(',', '', $coverage['section3_gross_profit_annual_sum_insured']);
        }
        if (!empty($coverage['section3_gross_profit_premium'])) {
            $premium = (float) str_replace(',', '', $coverage['section3_gross_profit_premium']);
        }
        if ($annualSumInsured > 0 && $premium > 0) {
            $rate = ($premium / $annualSumInsured) * 100;
            $coverage['section3_gross_profit_rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    public function calculateCarSection3IncreasedCostPremium($policyCoverageId)
    {
        if (!isset($this->carCoverage[$policyCoverageId])) {
            return;
        }

        $coverage = &$this->carCoverage[$policyCoverageId];
        
        // Get sum insured and rate values
        $sumInsured = 0;
        $rate = 0;
        
        if (isset($coverage['section3_increased_cost_sum_insured']) && !empty($coverage['section3_increased_cost_sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $coverage['section3_increased_cost_sum_insured']);
        }
        
        if (isset($coverage['section3_increased_cost_rate']) && !empty($coverage['section3_increased_cost_rate'])) {
            $rate = (float) $coverage['section3_increased_cost_rate'];
        }
        
        // Calculate premium: Premium = (Sum Insured × Rate) / 100
        $premium = 0;
        if ($sumInsured > 0 && $rate > 0) {
            $premium = ($sumInsured * $rate) / 100;
        }
        
        // Update premium
        // If sum insured has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $coverage['section3_increased_cost_premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($sumInsured > 0 && $rate == 0) {
            $coverage['section3_increased_cost_premium'] = '0';
        } else {
            $coverage['section3_increased_cost_premium'] = '';
        }
    }

    /** When premium is changed: calculate rate = (Premium / Sum Insured) × 100 */
    public function calculateCarSection3IncreasedCostRateFromPremium($policyCoverageId)
    {
        if (!isset($this->carCoverage[$policyCoverageId])) {
            return;
        }
        $coverage = &$this->carCoverage[$policyCoverageId];
        $sumInsured = 0;
        $premium = 0;
        if (!empty($coverage['section3_increased_cost_sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $coverage['section3_increased_cost_sum_insured']);
        }
        if (!empty($coverage['section3_increased_cost_premium'])) {
            $premium = (float) str_replace(',', '', $coverage['section3_increased_cost_premium']);
        }
        if ($sumInsured > 0 && $premium > 0) {
            $rate = ($premium / $sumInsured) * 100;
            $coverage['section3_increased_cost_rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    public function calculateEarSection3ItemPremium($policyCoverageId, $index)
    {
        if (!isset($this->earSection3Items[$policyCoverageId][$index])) {
            return;
        }

        $item = &$this->earSection3Items[$policyCoverageId][$index];
        
        // Get limit of indemnity and rate values
        $limitOfIndemnity = 0;
        $rate = 0;
        
        if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
            $limitOfIndemnity = (float) str_replace(',', '', $item['limit_of_indemnity']);
        }
        
        if (isset($item['rate']) && !empty($item['rate'])) {
            $rate = (float) $item['rate'];
        }
        
        // Calculate premium: Premium = (Limit of Indemnity × Rate) / 100
        $premium = 0;
        if ($limitOfIndemnity > 0 && $rate > 0) {
            $premium = ($limitOfIndemnity * $rate) / 100;
        }
        
        // Update premium in the item
        // If limit of indemnity has value and rate is 0, set premium to "0" (not empty)
        if ($premium > 0) {
            $item['premium'] = number_format(round($premium, 2), 2, '.', ',');
        } elseif ($limitOfIndemnity > 0 && $rate == 0) {
            $item['premium'] = '0';
        } else {
            $item['premium'] = '';
        }
        
        // Recalculate total premium
        $this->calculateEarTotalPremium($policyCoverageId);
    }

    /** When premium is changed: calculate rate = (Premium / Limit of Indemnity) × 100 */
    public function calculateEarSection3ItemRateFromPremium($policyCoverageId, $index)
    {
        if (!isset($this->earSection3Items[$policyCoverageId][$index])) {
            return;
        }
        $item = &$this->earSection3Items[$policyCoverageId][$index];
        $limitOfIndemnity = 0;
        $premium = 0;
        if (isset($item['limit_of_indemnity']) && $item['limit_of_indemnity'] !== '' && $item['limit_of_indemnity'] !== null) {
            $limitOfIndemnity = (float) str_replace(',', '', $item['limit_of_indemnity']);
        }
        if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
            $premium = (float) str_replace(',', '', $item['premium']);
        }
        if ($limitOfIndemnity > 0 && $premium > 0) {
            $rate = ($premium / $limitOfIndemnity) * 100;
            $item['rate'] = rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
        }
    }

    // EAR Section 3 Add/Remove Functions
    public function addEarSection3Row($policyCoverageId)
    {
        if (!isset($this->earSection3Items[$policyCoverageId])) {
            $this->earSection3Items[$policyCoverageId] = [];
        }
        $this->earSection3Items[$policyCoverageId][] = [];
    }

    public function removeEarSection3Row($policyCoverageId, $index)
    {
        unset($this->earSection3Items[$policyCoverageId][$index]);
        $this->earSection3Items[$policyCoverageId] = array_values($this->earSection3Items[$policyCoverageId]);
    }

    // EAR Endorsements Add/Remove Functions
    public function addEarEndorsementRow($policyCoverageId)
    {
        if (!isset($this->earEndorsements[$policyCoverageId])) {
            $this->earEndorsements[$policyCoverageId] = [];
        }
        $this->earEndorsements[$policyCoverageId][] = ['text' => ''];
    }

    public function removeEarEndorsementRow($policyCoverageId, $index)
    {
        unset($this->earEndorsements[$policyCoverageId][$index]);
        $this->earEndorsements[$policyCoverageId] = array_values($this->earEndorsements[$policyCoverageId]);
    }

    public function loadTheftData($policyCoverageId = null)
    {
        $id = $policyCoverageId ?? ($this->policyCoverageID['policyCoverageID'] ?? null);
        if (!$id) {
            return;
        }
        $existingData = TheftGeneralQuestions::where('policy_coverage_id', $id)->first();

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

    // Save handlers for CAR, PAR, EAR coverage sections
    public function saveCarCoverage($policyCoverageId)
    {
        // Validate dates before saving
        if (!$this->validateDateRange($policyCoverageId, 'CAR')) {
            return; // Stop saving - error message already shown
        }
        
        $this->saveCoverageData($policyCoverageId, 'CAR');
        $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'CAR Coverage saved successfully!']);
    }

    public function saveParCoverage($policyCoverageId)
    {
        // Validate dates before saving
        if (!$this->validateDateRange($policyCoverageId, 'PAR')) {
            return; // Stop saving - error message already shown
        }
        
        $this->saveCoverageData($policyCoverageId, 'PAR');
        $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'PAR Coverage saved successfully!']);
    }

    public function saveEarCoverage($policyCoverageId)
    {
        // Validate dates before saving
        if (!$this->validateDateRange($policyCoverageId, 'EAR')) {
            return; // Stop saving - error message already shown
        }

        $this->saveCoverageData($policyCoverageId, 'EAR');
        $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'EAR Coverage saved successfully!']);
    }

    public function saveDirectorsOfficersLiabilityCoverage($policyCoverageId)
    {
        $this->saveCoverageData($policyCoverageId, 'DOLIABILITY');
        $this->dispatchBrowserEvent('alert', [
            'type' => 'success',
            'message' => 'Directors & Officers Liability Coverage saved successfully!',
        ]);
    }

    public function saveMarineOnceOffCover($policyCoverageId)
    {
        $this->saveCoverageData($policyCoverageId, 'MARINEONCEOFFCOVER');
        $this->dispatchBrowserEvent('alert', [
            'type' => 'success',
            'message' => 'Marine Once-Off Cover saved successfully!',
        ]);
    }

    public function saveMarineOpenCover($policyCoverageId)
    {
        $this->saveCoverageData($policyCoverageId, 'MARINEOPENCOVER');
        $this->dispatchBrowserEvent('alert', [
            'type' => 'success',
            'message' => 'Marine Open Cover saved successfully!',
        ]);
    }

    public function saveMarineDirectorsOfficersCoverage($policyCoverageId)
    {
        $this->saveCoverageData($policyCoverageId, 'MARINEDIRECTORSOFFICERS');
        $this->dispatchBrowserEvent('alert', [
            'type' => 'success',
            'message' => 'Marine Directors & Officers Coverage saved successfully!',
        ]);
    }

    public function saveMachineryBreakdownCoverage($policyCoverageId)
    {
        $policyCoverage = PolicyCoverage::find($policyCoverageId);
        if (!$policyCoverage) {
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Policy coverage not found.']);
            return;
        }

        $row = $this->machineryBreakdown[$policyCoverageId] ?? [];
        $payload = [
            'policy_id'          => $this->policy->id,
            'policy_coverage_id' => $policyCoverageId,
            'coverage_id'        => $policyCoverage->coverage_id,
            'action_id'          => $this->actionId,
            'term_id'            => $this->termId,
            'policy_number'      => $row['policy_number']   ?? null,
            'company_name'       => $row['company_name']    ?? null,
            'company_address'    => $row['company_address'] ?? null,
            'inception_date'     => $this->formatDateForDb($row['inception_date'] ?? null),
            'expiry_date'        => $this->formatDateForDb($row['expiry_date']    ?? null),
            'today_date'         => $this->formatDateForDb($row['today_date']     ?? null),
            'is_renewable'       => $row['is_renewable']    ?? null,
            'currency'           => $row['currency']        ?? null,
            'premium'            => $row['premium']         ?? null,
            'notes'              => $row['notes']           ?? null,
        ];

        if ($this->machineryBreakdownPolicyWording) {
            $originalName = $this->machineryBreakdownPolicyWording->getClientOriginalName();
            $path = $this->machineryBreakdownPolicyWording->storeAs(
                'policy-wordings/machinery-breakdown',
                time() . '_' . $originalName,
                'public'
            );
            $payload['policy_wording_path'] = $path;
            $payload['policy_wording_filename'] = $originalName;
            $this->machineryBreakdown[$policyCoverageId]['policy_wording_path'] = $path;
            $this->machineryBreakdown[$policyCoverageId]['policy_wording_filename'] = $originalName;
            $this->machineryBreakdownPolicyWording = null;
        }

        MachineryBreakdownModel::updateOrCreate(
            ['policy_coverage_id' => $policyCoverageId, 'policy_id' => $this->policy->id],
            $payload
        );

        $this->dispatchBrowserEvent('alert', [
            'type' => 'success',
            'message' => 'Machinery Breakdown Policy Wording saved successfully!',
        ]);
    }
    
    /**
     * Validate that expiry date is not more than 3 years from inception date
     */
    private function validateDateRange($policyCoverageId, $coverageType)
    {
        $inceptionDate = null;
        $expiryDate = null;

        if ($coverageType === 'CAR') {
            if (isset($this->carCoverage[$policyCoverageId])) {
                $inceptionDateStr = $this->carCoverage[$policyCoverageId]['policy_inception_date'] ?? '';
                $expiryDateStr = $this->carCoverage[$policyCoverageId]['policy_expiry_date'] ?? '';

                if (!empty($inceptionDateStr) && !empty($expiryDateStr)) {
                    try {
                        $inceptionDate = \Carbon\Carbon::createFromFormat('d/m/Y', $inceptionDateStr);
                    } catch (\Exception $e) {
                        try {
                            $inceptionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $inceptionDateStr);
                        } catch (\Exception $e2) {
                            try {
                                $inceptionDate = \Carbon\Carbon::parse($inceptionDateStr);
                            } catch (\Exception $e3) {
                                return true; // Skip validation if can't parse
                            }
                        }
                    }

                    try {
                        $expiryDate = \Carbon\Carbon::createFromFormat('d/m/Y', $expiryDateStr);
                    } catch (\Exception $e) {
                        try {
                            $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $expiryDateStr);
                        } catch (\Exception $e2) {
                            try {
                                $expiryDate = \Carbon\Carbon::parse($expiryDateStr);
                            } catch (\Exception $e3) {
                                return true; // Skip validation if can't parse
                            }
                        }
                    }
                }
            }
        } elseif ($coverageType === 'EAR') {
            if (isset($this->earCoverage[$policyCoverageId])) {
                $inceptionDateStr = $this->earCoverage[$policyCoverageId]['period_from'] ?? '';
                $expiryDateStr = $this->earCoverage[$policyCoverageId]['period_to'] ?? '';

                if (!empty($inceptionDateStr) && !empty($expiryDateStr)) {
                    try {
                        $inceptionDate = \Carbon\Carbon::createFromFormat('d/m/Y', $inceptionDateStr);
                    } catch (\Exception $e) {
                        try {
                            $inceptionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $inceptionDateStr);
                        } catch (\Exception $e2) {
                            try {
                                $inceptionDate = \Carbon\Carbon::parse($inceptionDateStr);
                            } catch (\Exception $e3) {
                                return true;
                            }
                        }
                    }

                    try {
                        $expiryDate = \Carbon\Carbon::createFromFormat('d/m/Y', $expiryDateStr);
                    } catch (\Exception $e) {
                        try {
                            $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $expiryDateStr);
                        } catch (\Exception $e2) {
                            try {
                                $expiryDate = \Carbon\Carbon::parse($expiryDateStr);
                            } catch (\Exception $e3) {
                                return true;
                            }
                        }
                    }
                }
            }
        } elseif ($coverageType === 'PAR') {
            $termDates = $this->getPolicyTermDates();
            if ($termDates && isset($termDates['term_start_date']) && isset($termDates['term_end_date'])) {
                $inceptionDateStr = $termDates['term_start_date'] ?? '';
                $expiryDateStr = $termDates['term_end_date'] ?? '';

                if (!empty($inceptionDateStr) && !empty($expiryDateStr)) {
                    try {
                        $inceptionDate = \Carbon\Carbon::createFromFormat('d/m/Y', $inceptionDateStr);
                    } catch (\Exception $e) {
                        try {
                            $inceptionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $inceptionDateStr);
                        } catch (\Exception $e2) {
                            try {
                                $inceptionDate = \Carbon\Carbon::parse($inceptionDateStr);
                            } catch (\Exception $e3) {
                                return true;
                            }
                        }
                    }

                    try {
                        $expiryDate = \Carbon\Carbon::createFromFormat('d/m/Y', $expiryDateStr);
                    } catch (\Exception $e) {
                        try {
                            $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $expiryDateStr);
                        } catch (\Exception $e2) {
                            try {
                                $expiryDate = \Carbon\Carbon::parse($expiryDateStr);
                            } catch (\Exception $e3) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        // Check if expiry date is more than 3 years from inception date
        if ($inceptionDate && $expiryDate) {
            // Calculate the maximum allowed expiry date (3 years from inception)
            $maxExpiryDate = $inceptionDate->copy()->addYears(3);
            
            // Check if expiry date is after the maximum allowed date
            if ($expiryDate->gt($maxExpiryDate)) {
                $errorMessage = 'Policy expiry date cannot be more than 3 years from policy inception date.';
                
                if ($coverageType === 'EAR') {
                    $errorMessage = 'Policy expiry date (To) cannot be more than 3 years from policy inception date (From).';
                }
                
                $this->dispatchBrowserEvent('alert', [
                    'type' => 'error',
                    'message' => $errorMessage
                ]);
                
                return false;
            }
        }

        return true;
    }
    // PROFESSIONAL INDEMNITY Coverage
    public function saveProfessionalIndemnity($policyCoverageId)
    {
        try {
            $policyCoverage = $this->policyCoverages->firstWhere('id', $policyCoverageId);
            if (!$policyCoverage) {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Policy coverage not found!']);
                return;
            }

            $coverageCode = '';
            if (isset($policyCoverage->coverage) && $policyCoverage->coverage) {
                $coverageCode = $policyCoverage->coverage->s_CoverageCode ?? '';
            }

            // Check if this is a Professional Indemnity coverage
            if (!in_array(strtoupper($coverageCode), ['PROFESSIONALINDEMNITY', 'PROFESSIONAL_INDEMNITY', 'PI'])) {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'This is not a Professional Indemnity coverage!']);
                return;
            }

            // Prepare data
            $data = [
                'policy_id' => $this->policy->id,
                'policy_coverage_id' => $policyCoverageId,
                'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null,
            ];

            // Add basic information
            if (isset($this->professionalIndemnity[$policyCoverageId])) {
                $piData = $this->professionalIndemnity[$policyCoverageId];
                $data['insured'] = $piData['insured'] ?? null;
                $data['policy_inception_date'] = $piData['policy_inception_date'] ?? null;
                $data['policy_expiry_date'] = $piData['policy_expiry_date'] ?? null;
                $data['today_date'] = $piData['today_date'] ?? null;
                $data['profession_business'] = $piData['profession_business'] ?? null;
                $data['basis_of_cover'] = $piData['basis_of_cover'] ?? null;
                $data['period_of_insurance'] = $piData['period_of_insurance'] ?? null;
                $data['free_text_area'] = $piData['free_text_area'] ?? null;
                $data['notes'] = $piData['notes'] ?? null;
                $data['approved_by'] = $piData['approved_by'] ?? null;
                $data['approved_at'] = $piData['approved_at'] ?? null;
                $data['new_altered'] = $piData['new_altered'] ?? null;
                $data['is_renewable'] = $piData['is_renewable'] ?? null;
                $data['is_project_specific'] = $piData['is_project_specific'] ?? null;
                $data['premium'] = (float) str_replace(',', '', $piData['premium'] ?? 0);
                
                // Handle retroactive_date
                if (isset($piData['retroactive_date']) && !empty($piData['retroactive_date'])) {
                    try {
                        $data['retroactive_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $piData['retroactive_date'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        try {
                            $data['retroactive_date'] = \Carbon\Carbon::parse($piData['retroactive_date'])->format('Y-m-d');
                        } catch (\Exception $e2) {
                            $data['retroactive_date'] = null;
                        }
                    }
                }
            }

            // Add JSON fields
            if (isset($this->professionalIndemnityDescriptions[$policyCoverageId])) {
                $data['descriptions'] = array_values($this->professionalIndemnityDescriptions[$policyCoverageId]);
            }

            if (isset($this->professionalIndemnityInsuredPersons[$policyCoverageId])) {
                $data['insured_persons'] = array_values(
                    array_map(function ($person) {
                        if (isset($person['limit_of_liability'])) {
                            // Remove commas from numbers like "10,000"
                            $person['limit_of_liability'] = str_replace(',', '', $person['limit_of_liability']);
                        }
                        return $person;
                    }, $this->professionalIndemnityInsuredPersons[$policyCoverageId])
                );
            }
            if(isset($this->professionalIndemnityExtensions[$policyCoverageId])) {
                $data['extensions'] = array_values(
                    array_map(function ($extension) {
                        if(isset($extension['premium'])) {
                            // Remove commas from numbers like "10,000"
                            $extension['premium'] = str_replace(',', '', $extension['premium']);
                        }
                        if (isset($extension['limit_of_liability'])) {
                            // Remove commas from numbers like "10,000"
                            $extension['limit_of_liability'] = str_replace(',', '', $extension['limit_of_liability']);
                        }
                        return $extension;
                    }, $this->professionalIndemnityExtensions[$policyCoverageId])
                );
            }

            if (isset($this->professionalIndemnityAdditionalExtensions[$policyCoverageId])) {
                $data['additional_extensions'] = array_values(
                    array_map(function ($additionalExtension) {
                        if(isset($additionalExtension['premium'])) {
                            // Remove commas from numbers like "10,000"
                            $additionalExtension['premium'] = str_replace(',', '', $additionalExtension['premium']);
                        }
                        if(isset($additionalExtension['limit_of_liability'])) {
                            // Remove commas from numbers like "10,000"
                            $additionalExtension['limit_of_liability'] = str_replace(',', '', $additionalExtension['limit_of_liability']);
                        }
                        return $additionalExtension;
                    }, $this->professionalIndemnityAdditionalExtensions[$policyCoverageId])
                );
            }

            if (isset($this->professionalIndemnityExcesses[$policyCoverageId])) {

                $cleanNumbers = function ($data) use (&$cleanNumbers) {
            
                    if (is_array($data)) {
                        foreach ($data as $key => $value) {
                            $data[$key] = $cleanNumbers($value);
                        }
                        return $data;
                    }
            
                    // If value is a numeric string (with or without commas)
                    if (is_string($data) && preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $data)) {
                        return str_replace(',', '', $data);
                    }
            
                    return $data;
                };
            
                $data['excesses'] = $cleanNumbers(
                    $this->professionalIndemnityExcesses[$policyCoverageId]
                );
            }
            

            // if (isset($this->professionalIndemnityExcesses[$policyCoverageId])) {
            //     $data['excesses'] = $this->professionalIndemnityExcesses[$policyCoverageId];
            // }
            

            // Handle policy wording file upload
            if ($this->piPolicyWording) {                
                $originalName = $this->piPolicyWording->getClientOriginalName();
                $path = $this->piPolicyWording->storeAs(
                    'policy-wordings/professional-indemnity/',
                    time().'_'.$this->piPolicyWording->getClientOriginalName(),
                    'public' // or s3
                );
                $data['policy_wording_path'] = $path;
                $data['policy_wording_filename'] = basename($path);
                $data['policy_wording'] = $originalName;
            }            
            
            // Update or create
            ProfessionalIndemnityCoverageModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id
                ],
                $data
            );

            if (!empty($this->specified_items[$policyCoverageId])) {
                foreach ($this->specified_items[$policyCoverageId] as $row) {
                    $selectedItem = $row['selected_item'] ?? null;
                    $sumInsuredRaw = $row['sum_insured'] ?? null;

                    if (empty($selectedItem) || $sumInsuredRaw === null || trim((string) $sumInsuredRaw) === '') {
                        continue;
                    }

                    $actionId = $row['action_id'] ?? $this->actionId;
                    $rate = $this->specifiedItemsWithRate[$selectedItem] ?? 0;
                    $sumInsured = (float) str_replace(',', '', $sumInsuredRaw);
                    $calculatedValue = ($sumInsured * $rate) / 100;

                    $data = [
                        'specified_coverage_id' => $selectedItem,
                        'rate' => $rate,
                        'sum_insured' => $sumInsured,
                        'calculated_value' => $calculatedValue,
                        'endors_flag' => "0",
                        'action_id' => $actionId
                    ];

                    $where = [
                        'policy_coverage_id' => $policyCoverageId,
                        'specified_coverage_id' => $selectedItem,
                        'action_id' => $actionId
                    ];

                    if (!empty($row['id'])) {
                        PolicySpecifiedItem::withTrashed()
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

            $note = $this->policyCoverageNote[$policyCoverageId] ?? null;
            if (is_array($note)) {
                $note = implode(' ', array_filter($note));
            }

            if ($note === null || trim($note) === '') {
                PolicyCoverageNote::PolicyCoverage($policyCoverageId)->delete();
            } else {
                PolicyCoverageNote::withTrashed()->updateOrCreate(
                    [
                        'policy_coverage_id' => $policyCoverageId,
                    ],
                    [
                        'note' => $note,
                        'deleted_at' => null,
                    ]
                );

                activity('Policy Coverage Note')
                    ->performedOn($this->policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy Coverage Note Added');
            }

            $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Professional Indemnity Coverage saved successfully!']);
        } catch (\Exception $e) {
            \Log::error('Error saving Professional Indemnity Coverage: ' . $e->getMessage());
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to save Professional Indemnity Coverage. Please try again.']);
        }
    }

    

    public function addProfessionalIndemnityExtension($policyCoverageId)
    {
        if (!isset($this->professionalIndemnityAdditionalExtensions[$policyCoverageId])) {
            $this->professionalIndemnityAdditionalExtensions[$policyCoverageId] = [];
        }
        $this->professionalIndemnityAdditionalExtensions[$policyCoverageId][] = [
            'extension' => '',
            'limit_of_liability' => '',
            'premium' => ''
        ];
    }

    public function removeProfessionalIndemnityExtension($policyCoverageId, $index)
    {
        if (isset($this->professionalIndemnityAdditionalExtensions[$policyCoverageId][$index])) {
            unset($this->professionalIndemnityAdditionalExtensions[$policyCoverageId][$index]);
            $this->professionalIndemnityAdditionalExtensions[$policyCoverageId] = array_values($this->professionalIndemnityAdditionalExtensions[$policyCoverageId]);
        }
    }

    public function removeProfessionalIndemnityBaseExtension($policyCoverageId, $index)
    {
        if (isset($this->professionalIndemnityExtensions[$policyCoverageId][$index])) {
            unset($this->professionalIndemnityExtensions[$policyCoverageId][$index]);
            $this->professionalIndemnityExtensions[$policyCoverageId] = array_values($this->professionalIndemnityExtensions[$policyCoverageId]);
        }
    }

    public function addProfessionalIndemnityInsuredPerson($policyCoverageId)
    {
        if (!isset($this->professionalIndemnityInsuredPersons[$policyCoverageId])) {
            $this->professionalIndemnityInsuredPersons[$policyCoverageId] = [];
        }

        $this->professionalIndemnityInsuredPersons[$policyCoverageId][] = [
            'description' => '',
            'insured_person' => '',
            'length_of_service' => '',
            'limit_of_liability' => '',
            'designation' => ''
        ];
    }

    public function removeProfessionalIndemnityInsuredPerson($policyCoverageId, $index)
    {
        if (isset($this->professionalIndemnityInsuredPersons[$policyCoverageId][$index])) {
            unset($this->professionalIndemnityInsuredPersons[$policyCoverageId][$index]);
            $this->professionalIndemnityInsuredPersons[$policyCoverageId] = array_values($this->professionalIndemnityInsuredPersons[$policyCoverageId]);
        }
    }

    public function loadProfessionalIndemnityData($policyCoverageId, $force = false)
    {
        if (!$force && isset($this->professionalIndemnityLoaded[$policyCoverageId])) {
            return;
        }

        $piCoverage = ProfessionalIndemnityCoverageModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();
        $policyTerms = PolicyTerm::where('policy_id', $this->policy->id)->first();
        $customerName = $this->getCustomerName();
       
        if ($piCoverage) {
            // Load basic information
            $this->professionalIndemnity[$policyCoverageId] = [
                'insured' => $piCoverage->insured ?? $customerName,
                'profession_business' => $piCoverage->profession_business ?? '',
                'basis_of_cover' => $piCoverage->basis_of_cover ?? '',
                'period_of_insurance' => $piCoverage->period_of_insurance ?? '',
                'retroactive_date' => $piCoverage->retroactive_date ? \Carbon\Carbon::parse($piCoverage->retroactive_date)->format('d/m/Y') : '',
                'free_text_area' => $piCoverage->free_text_area ?? '',
                'notes' => $piCoverage->notes ?? '',
                'policy_wording_path' => $piCoverage->policy_wording_path ?? '',
                'policy_wording_filename' => $piCoverage->policy_wording_filename ?? '',
                'policy_wording' => $piCoverage->policy_wording ?? '',
                'approved_by' => $piCoverage->approved_by ?? null,
                'approved_at' => $piCoverage->approved_at ?? null,
                'new_altered' => $piCoverage->new_altered ?? null,
                'is_renewable' => $piCoverage->is_renewable ?? null,
                'is_project_specific' => $piCoverage->is_project_specific ?? null,
                'policy_inception_date' => $policyTerms->term_start_date ? \Carbon\Carbon::parse($policyTerms->term_start_date)->format('d/m/Y') : '',
                'policy_expiry_date' => $policyTerms->term_end_date ? \Carbon\Carbon::parse($policyTerms->term_end_date)->format('d/m/Y') : '',
                'today_date' => date('d/m/Y'),
                'premium' => $piCoverage->premium ?? '',
            ];

            // Load JSON fields
            $this->professionalIndemnityDescriptions[$policyCoverageId] = $piCoverage->descriptions ?? [];
            $this->professionalIndemnityInsuredPersons[$policyCoverageId] = $piCoverage->insured_persons ?? [];
            $this->professionalIndemnityExtensions[$policyCoverageId] = $piCoverage->extensions ?? [];
            $this->professionalIndemnityAdditionalExtensions[$policyCoverageId] = $piCoverage->additional_extensions ?? [];
            $this->professionalIndemnityExcesses[$policyCoverageId] = $piCoverage->excesses ?? [];
        } else {
            // Initialize with default values
            $this->professionalIndemnity[$policyCoverageId] = [
                'insured' => $customerName,
                'profession_business' => '',
                'basis_of_cover' => '',
                'period_of_insurance' => '',
                'retroactive_date' => '',
                'free_text_area' => '',
                'notes' => '',
                'policy_wording_path' => '',
                'policy_wording_filename' => '',
                'policy_wording' => '',
                'approved_by' => null,
                'approved_at' => null,
                'new_altered' => null,
                'is_renewable' => null,
                'is_project_specific' => null,
                'policy_inception_date' => $policyTerms->term_start_date ? \Carbon\Carbon::parse($policyTerms->term_start_date)->format('d/m/Y') : '',
                'policy_expiry_date' => $policyTerms->term_end_date ? \Carbon\Carbon::parse($policyTerms->term_end_date)->format('d/m/Y') : '',
                'today_date' => date('d/m/Y'),
                'premium' => '',
            ];

            $this->professionalIndemnityDescriptions[$policyCoverageId] = [
                0 => ['description' => ''],
                1 => ['description' => ''],
                2 => ['description' => '']
            ];

            $this->professionalIndemnityInsuredPersons[$policyCoverageId] = [
                0 => ['description' => '', 'insured_person' => '', 'length_of_service' => '', 'limit_of_liability' => '', 'designation' => '']
                
            ];

            $this->professionalIndemnityExtensions[$policyCoverageId] = [
                ['extension' => 'Sub Contracted Duties', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Liability Following Employee Dishonesty', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Mitigation of Loss', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Computer Crime', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Defamation', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Criminal and Statutory Defence Costs', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Loss Of Documents', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Fee Recovery', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Business Identity Theft', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Claims Preparation Costs', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Commercial Crime', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'Directors & Officers Liability', 'limit_of_liability' => '', 'premium' => ''],
                ['extension' => 'General Public Liability', 'limit_of_liability' => '', 'premium' => '']
            ];

            $this->professionalIndemnityAdditionalExtensions[$policyCoverageId] = [];
            $this->professionalIndemnityExcesses[$policyCoverageId] = [
                'basic' => ['percent' => '', 'minimum_excess' => ''],
                'others' => [
                    ['description' => '', 'percent' => '', 'minimum_excess' => '']
                ]
            ];
        }
        
        $this->professionalIndemnityLoaded[$policyCoverageId] = true;
    }

    public function addProfessionalIndemnityOtherExcess($policyCoverageId)
    {
        if (!isset($this->professionalIndemnityExcesses[$policyCoverageId])) {
            $this->professionalIndemnityExcesses[$policyCoverageId] = [
                'basic' => ['percent' => '', 'minimum_excess' => ''],
                'others' => [
                    ['description' => '', 'percent' => '', 'minimum_excess' => '']
                ]
            ];
        }

        $others = $this->professionalIndemnityExcesses[$policyCoverageId]['others'] ?? [];
        if (isset($others['percent']) || isset($others['minimum_excess']) || isset($others['description'])) {
            $others = [$others];
        }

        $others[] = ['description' => '', 'percent' => '', 'minimum_excess' => ''];
        $this->professionalIndemnityExcesses[$policyCoverageId]['others'] = $others;
    }

    public function removeProfessionalIndemnityOtherExcess($policyCoverageId, $otherIndex)
    {
        $others = $this->professionalIndemnityExcesses[$policyCoverageId]['others'] ?? [];
        if (isset($others['percent']) || isset($others['minimum_excess']) || isset($others['description'])) {
            $others = [$others];
        }

        if (!array_key_exists($otherIndex, $others)) {
            return;
        }

        unset($others[$otherIndex]);
        $others = array_values($others);

        if (empty($others)) {
            $others = [
                ['description' => '', 'percent' => '', 'minimum_excess' => '']
            ];
        }

        $this->professionalIndemnityExcesses[$policyCoverageId]['others'] = $others;
    }

    // MEDICAL MALPRACTICE Coverage
    public function saveMedicalMalpracticeCoverage($policyCoverageId)
    {
        try {
            $policyCoverage = $this->policyCoverages->firstWhere('id', $policyCoverageId);
            if (!$policyCoverage) {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Policy coverage not found!']);
                return;
            }

            $coverageCode = '';
            if (isset($policyCoverage->coverage) && $policyCoverage->coverage) {
                $coverageCode = $policyCoverage->coverage->s_CoverageCode ?? '';
            }

            if (!in_array(strtoupper($coverageCode), ['MEDICAMALPRACTICEINSURANCE', 'MM'])) {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'This is not a Medical Malpractice coverage!']);
                return;
            }

            $data = [
                'policy_id' => $this->policy->id,
                'policy_coverage_id' => $policyCoverageId,
                'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null,
            ];

            if (isset($this->medicalMalpractice[$policyCoverageId])) {
                $mmData = $this->medicalMalpractice[$policyCoverageId];
                $data['policy_number'] = $mmData['policy_number'] ?? null;
                $data['type_of_document'] = $mmData['type_of_document'] ?? null;
                $data['insured'] = $mmData['insured'] ?? null;
                $data['insured_vat_number'] = $mmData['insured_vat_number'] ?? null;
                $data['company_registration_number'] = $mmData['company_registration_number'] ?? null;
                $data['insured_business_description'] = $mmData['insured_business_description'] ?? null;
                $data['insured_postal_address'] = $mmData['insured_postal_address'] ?? null;
                $data['intermediary'] = $mmData['intermediary'] ?? null;
                $data['period_of_insurance'] = $mmData['period_of_insurance'] ?? null;
                $data['approved_by'] = $mmData['approved_by'] ?? null;
                $data['approved_at'] = $mmData['approved_at'] ?? null;
                $data['policy_inception_date'] = $mmData['policy_inception_date'] ?? null;
                $data['policy_expiry_date'] = $mmData['policy_expiry_date'] ?? null;
                $data['today_date'] = $mmData['today_date'] ?? null;
                $data['new_altered'] = $mmData['new_altered'] ?? null;
                $data['is_renewable'] = $mmData['is_renewable'] ?? null;
                $data['is_project_specific'] = $mmData['is_project_specific'] ?? null;
                $data['anniversary_renewal_date'] = $mmData['anniversary_renewal_date'] ?? null;
                $data['retroactive_date'] = $mmData['retroactive_date'] ?? null;
                $data['type_of_contract'] = $mmData['type_of_contract'] ?? null;
                $data['payment_frequency'] = $mmData['payment_frequency'] ?? null;
                $data['annual_premium'] = isset($mmData['annual_premium'])
                    ? str_replace(',', '', $mmData['annual_premium'])
                    : null;
                $data['limit_of_indemnity'] = isset($mmData['limit_of_indemnity'])
                    ? str_replace(',', '', $mmData['limit_of_indemnity'])
                    : null;
                $data['basis_of_limit'] = $mmData['basis_of_limit'] ?? null;
                $data['cumulative_limit'] = isset($mmData['cumulative_limit'])
                    ? str_replace(',', '', $mmData['cumulative_limit'])
                    : null;
                $data['automatic_reinstatement'] = $mmData['automatic_reinstatement'] ?? null;
                $data['additional_reporting_period'] = $mmData['additional_reporting_period'] ?? null;
                $data['standard_policy_conditions'] = $mmData['standard_policy_conditions'] ?? null;
                $data['notes'] = $mmData['notes'] ?? null;

                $policyWordingPath = $mmData['policy_wording_path'] ?? $mmData['policy_wording'] ?? null;
                if (!empty($policyWordingPath)) {
                    $data['policy_wording'] = $policyWordingPath;
                    $data['policy_wording_path'] = $policyWordingPath;
                }
                if (!empty($mmData['policy_wording_filename'])) {
                    $data['policy_wording_filename'] = $mmData['policy_wording_filename'];
                }

                $data = $this->formatCoverageDatesForDb($data, $this->medicalMalpracticeDateFields);
            }

            if (isset($this->medicalMalpracticeRiskDetails[$policyCoverageId])) {

                $data['risk_details'] = array_values(
                    array_map(function ($item) {
            
                        if (isset($item['value'])) {
                            // Remove commas from numbers like "10,000"
                            $item['value'] = str_replace(',', '', $item['value']);
                        }
            
                        return $item;
            
                    }, $this->medicalMalpracticeRiskDetails[$policyCoverageId])
                );
            }
            if (isset($this->medicalMalpracticeExtensions[$policyCoverageId])) {
                $data['extensions'] = array_values(
                    array_map(function ($item) {
            
                        if (isset($item['limit_of_indemnity'])) {
                            // Remove commas from numbers like "10,000"
                            $item['limit_of_indemnity'] = str_replace(',', '', $item['limit_of_indemnity']);
                        }
                        if (isset($item['deductible'])) {
                            // Remove commas from numbers like "10,000"
                            $item['deductible'] = str_replace(',', '', $item['deductible']);
                        }
                        return $item;
            
                    }, $this->medicalMalpracticeExtensions[$policyCoverageId])
                );
            }

            if (isset($this->medicalMalpracticeSpecificDeductibles[$policyCoverageId])) {
                $data['specific_deductibles'] = array_values(
                    array_map(function ($item) {
                        if (isset($item['deductible'])) {   
                            // Remove commas from numbers like "10,000"
                            $item['deductible'] = str_replace(',', '', $item['deductible']);
                        }
                        if (isset($item['limit_of_indemnity'])) {
                            // Remove commas from numbers like "10,000"
                            $item['limit_of_indemnity'] = str_replace(',', '', $item['limit_of_indemnity']);
                        }
                        return $item;
                    }, $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId])
                );
            }
            
            

            if ($this->medicalMalpracticePolicyWording) {                
                $originalName = $this->medicalMalpracticePolicyWording->getClientOriginalName();
                $path = $this->medicalMalpracticePolicyWording->storeAs(
                    'policy-wordings/medical/',
                    time().'_'.$this->medicalMalpracticePolicyWording->getClientOriginalName(),
                    'public'
                );
                $data['policy_wording_path'] = $path;
                $data['policy_wording_filename'] = basename($path);
                $data['policy_wording'] = $originalName;
            }  
            MedicalMalpracticeCoverageModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id
                ],
                $data
            );

            if (!empty($this->specified_items[$policyCoverageId])) {
                foreach ($this->specified_items[$policyCoverageId] as $row) {
                    $selectedItem = $row['selected_item'] ?? null;
                    $sumInsuredRaw = $row['sum_insured'] ?? null;

                    if (empty($selectedItem) || $sumInsuredRaw === null || trim((string) $sumInsuredRaw) === '') {
                        continue;
                    }

                    $actionId = $row['action_id'] ?? $this->actionId;
                    $rate = $this->specifiedItemsWithRate[$selectedItem] ?? 0;
                    $sumInsured = (float) str_replace(',', '', $sumInsuredRaw);
                    $calculatedValue = ($sumInsured * $rate) / 100;

                    $data = [
                        'specified_coverage_id' => $selectedItem,
                        'rate' => $rate,
                        'sum_insured' => $sumInsured,
                        'calculated_value' => $calculatedValue,
                        'endors_flag' => "0",
                        'action_id' => $actionId
                    ];

                    $where = [
                        'policy_coverage_id' => $policyCoverageId,
                        'specified_coverage_id' => $selectedItem,
                        'action_id' => $actionId
                    ];

                    if (!empty($row['id'])) {
                        PolicySpecifiedItem::withTrashed()
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

            $note = $this->policyCoverageNote[$policyCoverageId] ?? null;
            if (is_array($note)) {
                $note = implode(' ', array_filter($note));
            }

            if ($note === null || trim($note) === '') {
                PolicyCoverageNote::PolicyCoverage($policyCoverageId)->delete();
            } else {
                PolicyCoverageNote::withTrashed()->updateOrCreate(
                    [
                        'policy_coverage_id' => $policyCoverageId,
                    ],
                    [
                        'note' => $note,
                        'deleted_at' => null,
                    ]
                );

                activity('Policy Coverage Note')
                    ->performedOn($this->policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy Coverage Note Added');
            }

            $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Medical Malpractice Coverage saved successfully!']);
        } catch (\Exception $e) {
            \Log::error('Error saving Medical Malpractice Coverage: ' . $e->getMessage());
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to save Medical Malpractice Coverage. Please try again.']);
        }
    }

    /**
     * Update period_of_insurance for Medical Malpractice coverage when the select is changed (e.g. on change/blur).
     * Persists to DB then refreshes via loadMedicalMalpracticeData.
     */
    public function updateMedicalMalpracticePeriodOfInsurance($policyCoverageId)
    {
        $policyCoverage = $this->policyCoverages->firstWhere('id', $policyCoverageId);
        if (!$policyCoverage) {
            return;
        }
        $coverageCode = $policyCoverage->coverage->s_CoverageCode ?? '';
        if (!in_array(strtoupper($coverageCode), ['MEDICAMALPRACTICEINSURANCE', 'MM'])) {
            return;
        }
        $value = $this->medicalMalpractice[$policyCoverageId]['period_of_insurance'] ?? null;
        MedicalMalpracticeCoverageModel::updateOrCreate(
            [
                'policy_coverage_id' => $policyCoverageId,
                'policy_id' => $this->policy->id,
            ],
            [
                'policy_coverage_id' => $policyCoverageId,
                'policy_id' => $this->policy->id,
                'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null,
                'period_of_insurance' => $value,
            ]
        );
        $this->loadMedicalMalpracticeData($policyCoverageId);
    }

    public function loadMedicalMalpracticeData($policyCoverageId)
    {
        $mmCoverage = MedicalMalpracticeCoverageModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();
        $policyDetails = Policy::where('id', $this->policy->id)->first();

        $customerName = $policyDetails->profile->company->name ?? trim(($policyDetails->customer->firstName ?? '') . ' ' . ($policyDetails->customer->middleName ?? '') . ' ' . ($policyDetails->customer->lastName ?? '')) ?: '';
        $companyRegistrationNumber = $policyDetails->profile->company->company_registration_number ?? '';
        $companyVatRegistrationNumber = $policyDetails->profile->company->VAT_registration_number ?? '';

        $policyTerms = PolicyTerm::where('policy_id', $this->policy->id)->first();
        $policyInceptionDate = $policyTerms->term_start_date ? \Carbon\Carbon::parse($policyTerms->term_start_date)->format('d/m/Y') : '';
        $policyExpiryDate = $policyTerms->term_end_date ? \Carbon\Carbon::parse($policyTerms->term_end_date)->format('d/m/Y') : '';
        $premiumFreq = $policyDetails->premium_freq ?? '';
        $policyNumber = $policyDetails->policyNumber ?? '';
        $defaultRiskDetails = [
            ['risk_detail' => 'Limit of Indemnity', 'value' => ''],
            ['risk_detail' => 'Basis of Limit', 'value' => ''],
            ['risk_detail' => 'Cumulative Limit', 'value' => ''],
            ['risk_detail' => 'Automatic Reinstatement', 'value' => ''],
            ['risk_detail' => 'Additional Reporting Period', 'value' => ''],
        ];

        if ($mmCoverage) {
            
            // Load basic information
            $this->medicalMalpractice[$policyCoverageId] = [
                'policy_number' => $policyNumber ?? '',
                'policy_inception_date' => $mmCoverage->policy_inception_date ? \Carbon\Carbon::parse($mmCoverage->policy_inception_date)->format('d/m/Y') : $policyInceptionDate,
                'policy_expiry_date' => $mmCoverage->policy_expiry_date ? \Carbon\Carbon::parse($mmCoverage->policy_expiry_date)->format('d/m/Y') : $policyExpiryDate,
                'today_date' => $mmCoverage->today_date ? \Carbon\Carbon::parse($mmCoverage->today_date)->format('d/m/Y') : '',
                'type_of_document' => $mmCoverage->type_of_document ?? '',
                'insured' => $mmCoverage->insured ?? $customerName ?? '',
                'insured_vat_number' => $mmCoverage->insured_vat_number ?? '',
                'company_registration_number' => $mmCoverage->company_registration_number ?? $companyRegistrationNumber ?? '',
                'insured_business_description' => $mmCoverage->insured_business_description ?? '',
                'insured_postal_address' => $mmCoverage->insured_postal_address ?? '',
                'intermediary' => $mmCoverage->intermediary ?? '',
                'period_of_insurance' => $mmCoverage->period_of_insurance ?? $this->medicalMalpractice[$policyCoverageId]['period_of_insurance'] ?? '',
                'new_altered' => $mmCoverage->new_altered ?? '',
                'is_renewable' => $mmCoverage->is_renewable ?? '',
                'is_project_specific' => $mmCoverage->is_project_specific ?? '',
                'anniversary_renewal_date' => $mmCoverage->anniversary_renewal_date ? \Carbon\Carbon::parse($mmCoverage->anniversary_renewal_date)->format('d/m/Y') : '',
                'retroactive_date' => $mmCoverage->retroactive_date ? \Carbon\Carbon::parse($mmCoverage->retroactive_date)->format('d/m/Y') : '',
                'type_of_contract' => $mmCoverage->type_of_contract ?? '',
                'payment_frequency' => $mmCoverage->payment_frequency ?? '',
                'annual_premium' => $mmCoverage->annual_premium ?? '',
                'premium_freq' => $premiumFreq ?? '',
                'limit_of_indemnity' => $mmCoverage->limit_of_indemnity ?? '',
                'basis_of_limit' => $mmCoverage->basis_of_limit ?? '',
                'cumulative_limit' => $mmCoverage->cumulative_limit ?? '',
                'automatic_reinstatement' => $mmCoverage->automatic_reinstatement ?? '',
                'additional_reporting_period' => $mmCoverage->additional_reporting_period ?? '',
                'standard_policy_conditions' => $mmCoverage->standard_policy_conditions ?? '',
                'policy_wording' => $mmCoverage->policy_wording_path ?? $mmCoverage->policy_wording ?? '',
                'policy_wording_path' => $mmCoverage->policy_wording_path ?? $mmCoverage->policy_wording ?? '',
                'policy_wording_filename' => $mmCoverage->policy_wording_filename ?? '',
                'notes' => $mmCoverage->notes ?? '',
                'approved_by' => $mmCoverage->approved_by ?? null,
                'approved_at' => $mmCoverage->approved_at ?? null,
            ];
            
            // Load JSON fields: merge database extensions with existing (new) extensions
            $savedExtensions = $mmCoverage->extensions ?? [];
            $existingExtensions = $this->medicalMalpracticeExtensions[$policyCoverageId] ?? [];
            $mergedExtensions = array_merge($savedExtensions, $existingExtensions);
            $uniqueExtensions = [];
            $seenSections = [];
            foreach ($mergedExtensions as $item) {
                $section = is_array($item) ? trim((string)($item['section_name'] ?? '')) : '';
                $dedupeKey = $section !== '' ? 'section:' . strtolower($section) : 'item:' . md5(json_encode($item));
                if (isset($seenSections[$dedupeKey])) {
                    continue;
                }
                $seenSections[$dedupeKey] = true;
                $uniqueExtensions[] = $item;
            }
            $this->medicalMalpracticeExtensions[$policyCoverageId] = array_values($uniqueExtensions);

            // Merge database specific_deductibles with existing (new) specific deductibles
            $savedDeductibles = $mmCoverage->specific_deductibles ?? [];
            $existingDeductibles = $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId] ?? [];
            $mergedDeductibles = array_merge($savedDeductibles, $existingDeductibles);
            $uniqueDeductibles = [];
            $seenDeductibles = [];
            foreach ($mergedDeductibles as $item) {
                $section = is_array($item) ? trim((string)($item['section_name'] ?? '')) : '';
                $dedupeKey = $section !== '' ? 'section:' . strtolower($section) : 'item:' . md5(json_encode($item));
                if (isset($seenDeductibles[$dedupeKey])) {
                    continue;
                }
                $seenDeductibles[$dedupeKey] = true;
                $uniqueDeductibles[] = $item;
            }
            $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId] = array_values($uniqueDeductibles);

            $existingRiskDetails = $this->medicalMalpracticeRiskDetails[$policyCoverageId] ?? [];
           
            $savedRiskDetails = $mmCoverage->risk_details ?? [];
            $mergedRiskDetails = array_merge($savedRiskDetails, $existingRiskDetails);
            $uniqueRiskDetails = [];
            $seenRiskDetails = [];
            foreach ($mergedRiskDetails as $item) {
                $riskDetail = is_array($item) ? trim((string)($item['risk_detail'] ?? '')) : '';
                $value = is_array($item) ? trim((string)($item['value'] ?? '')) : '';
                if ($riskDetail !== '') {
                    $dedupeKey = 'risk_detail:' . strtolower($riskDetail);
                } else {
                    $dedupeKey = 'item:' . md5(json_encode($item));
                }
                if (isset($seenRiskDetails[$dedupeKey])) {
                    continue;
                }
                $seenRiskDetails[$dedupeKey] = true;
                $uniqueRiskDetails[] = $item;
            }
            $this->medicalMalpracticeRiskDetails[$policyCoverageId] = array_values($uniqueRiskDetails);
        } else {
            // Initialize with default values
            $this->medicalMalpractice[$policyCoverageId] = [
                'policy_number' => $policyNumber ?? '',
                'policy_inception_date' => $policyInceptionDate ?? '',
                'policy_expiry_date' => $policyExpiryDate ?? '',
                'today_date' => date('d/m/Y'),
                'type_of_document' => '',
                'insured' => $customerName ?? '',
                'insured_vat_number' => $companyVatRegistrationNumber ?? '',
                'company_registration_number' => $companyRegistrationNumber ?? '',
                'insured_business_description' => '',
                'insured_postal_address' => '',
                'intermediary' => '',
                'period_of_insurance' => '',
                'new_altered' => '',
                'is_renewable' => '',
                'is_project_specific' => '',
                'anniversary_renewal_date' => '',
                'retroactive_date' => '',
                'type_of_contract' => '',
                'payment_frequency' => '',
                'annual_premium' =>  '',
                'premium_freq' => $premiumFreq ?? 'Premium Frequency',
                'limit_of_indemnity' => '',
                'basis_of_limit' => '',
                'cumulative_limit' => '',
                'automatic_reinstatement' => '',
                'additional_reporting_period' => '',
                'standard_policy_conditions' => 'The Policy Wording, together with this Schedule and its endorsements as agreed to by the Insurer from time to time, shall be read together as one contract. This Schedule provides a summary of the cover under this policy, but it also includes additional terms and conditions of cover in the endorsements. Please read The Policy Wording together with all the endorsements on this Schedule carefully.',
                'policy_wording' => '',
                'policy_wording_path' => '',
                'policy_wording_filename' => '',
                'notes' => '',
                'approved_by' => null,
                'approved_at' => null,
            ];

            // Initialize extensions with default values
            $this->medicalMalpracticeExtensions[$policyCoverageId] = [
                ['section_name' => 'Medical Malpractice', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Professional Indemnity', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Public Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Pollution Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Products Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Employers Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Breach of Confidentiality', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Business Identity Theft', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Defamation', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Documents', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Statutory Defence Costs (Sub-Limit of Public Liability Section)', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                ['section_name' => 'Wrongful Arrest (Sub-Limit of Public Liability Section)', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
            ];

            // Initialize specific deductibles
            $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId] = [
                ['section_name' => 'Online Therapy', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => '']
            ];

            $this->medicalMalpracticeRiskDetails[$policyCoverageId] = $defaultRiskDetails;
        }
    }

    public function addMedicalMalpracticeRiskDetail($policyCoverageId)
    {
        if (empty($this->medicalMalpracticeRiskDetails[$policyCoverageId])) {
            $mmCoverage = MedicalMalpracticeCoverageModel::where('policy_coverage_id', $policyCoverageId)
                ->where('policy_id', $this->policy->id)
                ->first();

            $this->medicalMalpracticeRiskDetails[$policyCoverageId] = $mmCoverage->risk_details ?? $defaultRiskDetails;
        }

        $this->medicalMalpracticeRiskDetails[$policyCoverageId][] = [
            'risk_detail' => '',
            'value' => ''
        ];
    }

    public function removeMedicalMalpracticeRiskDetail($policyCoverageId, $index)
    {
        if (!isset($this->medicalMalpracticeRiskDetails[$policyCoverageId][$index])) {
            return;
        }
        $details = $this->medicalMalpracticeRiskDetails[$policyCoverageId];

        unset($details[$index]);
        $details = array_values($details);
        if (empty($details)) {
            $details = [
                ['risk_detail' => '', 'value' => '']
            ];
        }
        $this->medicalMalpracticeRiskDetails[$policyCoverageId] = $details;
        // Force Livewire to detect the nested array change so the view updates
        $this->medicalMalpracticeRiskDetails = $this->medicalMalpracticeRiskDetails;

        // Persist to database: update $mmCoverage->risk_details
        $mmCoverage = MedicalMalpracticeCoverageModel::where('policy_coverage_id', $policyCoverageId)->first();
        if ($mmCoverage) {
            $riskDetailsForDb = array_values(
                array_map(function ($item) {
                    if (isset($item['value'])) {
                        $item['value'] = str_replace(',', '', $item['value']);
                    }
                    return $item;
                }, $details)
            );
            $mmCoverage->risk_details = $riskDetailsForDb;
            $mmCoverage->save();
        }
    }

    public function addMedicalMalpracticeExtension($policyCoverageId)
    {
        if (!isset($this->medicalMalpracticeExtensions[$policyCoverageId])) {
            $this->medicalMalpracticeExtensions[$policyCoverageId] = [];
        }

        $this->medicalMalpracticeExtensions[$policyCoverageId][] = [
            'section_name' => '',
            'limit_of_indemnity' => '',
            'basis_of_limit' => '',
            'deductible' => '',
            'basis_of_deductible' => ''
        ];
    }

    public function removeMedicalMalpracticeExtension($policyCoverageId, $index)
    {
        if (!isset($this->medicalMalpracticeExtensions[$policyCoverageId][$index])) {
            return;
        }
        $extensions = $this->medicalMalpracticeExtensions[$policyCoverageId];

        unset($extensions[$index]);
        $extensions = array_values($extensions);
        $this->medicalMalpracticeExtensions[$policyCoverageId] = $extensions;
        // Force Livewire to detect the nested array change so the view updates
        $this->medicalMalpracticeExtensions = $this->medicalMalpracticeExtensions;

        // Persist to database: update $mmCoverage->extensions
        $mmCoverage = MedicalMalpracticeCoverageModel::where('policy_coverage_id', $policyCoverageId)->first();
        if ($mmCoverage) {
            $extensionsForDb = array_values(
                array_map(function ($item) {
                    if (isset($item['limit_of_indemnity'])) {
                        $item['limit_of_indemnity'] = str_replace(',', '', $item['limit_of_indemnity']);
                    }
                    if (isset($item['deductible'])) {
                        $item['deductible'] = str_replace(',', '', $item['deductible']);
                    }
                    return $item;
                }, $extensions)
            );
            $mmCoverage->extensions = $extensionsForDb;
            $mmCoverage->save();
        }
    }

    public function addMedicalMalpracticeSpecificDeductible($policyCoverageId)
    {
        if (!isset($this->medicalMalpracticeSpecificDeductibles[$policyCoverageId])) {
            $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId] = [];
        }

        $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId][] = [
            'section_name' => '',
            'limit_of_indemnity' => '',
            'basis_of_limit' => '',
            'deductible' => '',
            'basis_of_deductible' => ''
        ];
    }

    public function removeMedicalMalpracticeSpecificDeductible($policyCoverageId, $index)
    {
        if (!isset($this->medicalMalpracticeSpecificDeductibles[$policyCoverageId][$index])) {
            return;
        }
        $deductibles = $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId];

        unset($deductibles[$index]);
        $deductibles = array_values($deductibles);
        if (empty($deductibles)) {
            $deductibles = [
                ['section_name' => 'Online Therapy', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => '']
            ];
        }
        $this->medicalMalpracticeSpecificDeductibles[$policyCoverageId] = $deductibles;
        // Force Livewire to detect the nested array change so the view updates
        $this->medicalMalpracticeSpecificDeductibles = $this->medicalMalpracticeSpecificDeductibles;

        // Persist to database: update $mmCoverage->specific_deductibles
        $mmCoverage = MedicalMalpracticeCoverageModel::where('policy_coverage_id', $policyCoverageId)->first();
        if ($mmCoverage) {
            $specificDeductiblesForDb = array_values(
                array_map(function ($item) {
                    if (isset($item['deductible'])) {
                        $item['deductible'] = str_replace(',', '', $item['deductible']);
                    }
                    if (isset($item['limit_of_indemnity'])) {
                        $item['limit_of_indemnity'] = str_replace(',', '', $item['limit_of_indemnity']);
                    }
                    return $item;
                }, $deductibles)
            );
            $mmCoverage->specific_deductibles = $specificDeductiblesForDb;
            $mmCoverage->save();
        }
    }

    public function loadTravelCoverageData($policyCoverageId)
    {
        $existingData = TravelCoverageModel::where('policy_coverage_id', $policyCoverageId)
            ->where('policy_id', $this->policy->id)
            ->first();

        if ($existingData) {
            $this->travelCoverage[$policyCoverageId] = $this->formatCoverageDatesForDisplay(
                $existingData->toArray(),
                $this->travelCoverageDateFields
            );
            unset($this->travelCoverage[$policyCoverageId]['id']);
            unset($this->travelCoverage[$policyCoverageId]['created_at']);
            unset($this->travelCoverage[$policyCoverageId]['updated_at']);

            // Load benefits (string JSON or already-decoded array)
            if (!empty($existingData->benefits)) {
                $benefitsRaw = $existingData->benefits;
                if (is_array($benefitsRaw)) {
                    $this->travelBenefits[$policyCoverageId] = $benefitsRaw;
                } elseif (is_string($benefitsRaw)) {
                    $this->travelBenefits[$policyCoverageId] = json_decode($benefitsRaw, true) ?? [];
                } else {
                    $this->travelBenefits[$policyCoverageId] = [];
                }
            } else {
                $this->travelBenefits[$policyCoverageId] = [];
            }
            
            // Load custom benefits from JSON
            // Don't initialize with empty row - let user click "Add More Benefits" button
            if (!empty($existingData->custom_benefits)) {
                $customBenefitsRaw = $existingData->custom_benefits;
                // `custom_benefits` is cast to array on the model, but support string JSON too
                if (is_array($customBenefitsRaw)) {
                    $this->travelCustomBenefits[$policyCoverageId] = $customBenefitsRaw;
                } elseif (is_string($customBenefitsRaw)) {
                    $this->travelCustomBenefits[$policyCoverageId] = json_decode($customBenefitsRaw, true) ?? [];
                } else {
                    $this->travelCustomBenefits[$policyCoverageId] = [];
                }
            } else {
                // Start with empty array - no default rows
                $this->travelCustomBenefits[$policyCoverageId] = [];
            }
            
            // Initialize all benefit descriptions and auto-populate Sum Insured/Excess
            $this->initializeTravelBenefits($policyCoverageId);

            // Auto-populate policyholder with customer name if empty
            if (empty($this->travelCoverage[$policyCoverageId]['policyholder'])) {
                $customerName = $this->getCustomerName();
                $this->travelCoverage[$policyCoverageId]['policyholder'] = $customerName;
            }

            // Auto-populate policy number if empty (like CAR coverage)
            if (empty($this->travelCoverage[$policyCoverageId]['policy_number'])) {
                $this->travelCoverage[$policyCoverageId]['policy_number'] = $this->policy->policyNumber ?? '';
            }

            // Auto-populate policy period if not already set (similar to CAR coverage)
            $termDates = $this->getPolicyTermDates();
            if (empty($this->travelCoverage[$policyCoverageId]['policy_period_months'])) {
                $effectiveFrom = $this->travelCoverage[$policyCoverageId]['effective_from'] ?? null;
                $expiry = $this->travelCoverage[$policyCoverageId]['expiry'] ?? null;
                
                // Use coverage dates if available, otherwise use term dates
                $startDate = $effectiveFrom ?? ($termDates['term_start_date'] ?? null);
                $endDate = $expiry ?? ($termDates['term_end_date'] ?? null);
                
                if ($startDate && $endDate) {
                    $months = $this->calculateMonthsBetweenDates($startDate, $endDate);
                    $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
                    if ($policyPeriodMonths) {
                        $this->travelCoverage[$policyCoverageId]['policy_period_months'] = $policyPeriodMonths;
                    }
                } elseif ($termDates) {
                    // Fallback to term dates if coverage dates not set
                    $months = $this->calculateMonthsBetweenDates($termDates['term_start_date'], $termDates['term_end_date']);
                    $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
                    if ($policyPeriodMonths) {
                        $this->travelCoverage[$policyCoverageId]['policy_period_months'] = $policyPeriodMonths;
                    }
                }
            }

            // Auto-calculate VAT and Total if policy_amount is set but VAT is empty
            if (!empty($this->travelCoverage[$policyCoverageId]['policy_amount']) && empty($this->travelCoverage[$policyCoverageId]['vat'])) {
                $this->calculateTravelVatAndTotal($policyCoverageId);
            }
        } else {
            // Initialize with defaults for new coverage (similar to CAR coverage)
            $customerName = $this->getCustomerName();
            
            // Get policy term dates
            $termDates = $this->getPolicyTermDates();
            
            // Calculate months and policy period
            $months = null;
            $policyPeriodMonths = null;
            if ($termDates) {
                $months = $this->calculateMonthsBetweenDates($termDates['term_start_date'], $termDates['term_end_date']);
                $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
            }
            
            $this->travelCoverage[$policyCoverageId] = [
                'policyholder' => $customerName,
                'policy_number' => $this->policy->policyNumber ?? '',
                'destination_area' => 'Worldwide - Provides Worldwide cover excluding the country of residence',
                'effective_from' => $termDates['term_start_date'] ?? '',
                'expiry' => $termDates['term_end_date'] ?? '',
                'policy_period_months' => $policyPeriodMonths ?? '',
            ];
            $this->travelBenefits[$policyCoverageId] = [];
            
            // Initialize all benefit descriptions and auto-populate Sum Insured/Excess
            $this->initializeTravelBenefits($policyCoverageId);
        }
    }
    
    /**
     * Initialize all travel benefit descriptions and auto-populate Sum Insured/Excess
     */
    private function initializeTravelBenefits($policyCoverageId)
    {
        $benefitDefinitions = [
            'personal_assistance' => ['description' => 'PERSONAL ASSISTANCE'],
            'relay_urgent_messages' => ['description' => 'RELAY OF URGENT MESSAGES'],
            'dispatch_medication' => ['description' => 'DISPATCH OF MEDICATION'],
            'general_information' => ['description' => 'GENERAL INFORMATION INCLUDED -'],
            'hijack' => ['description' => 'HIJACK'],
            'medical_transportation_repatriation' => ['description' => 'MEDICAL TRANSPORTATION AND REPATRIATION'],
            'medical_transportation_or_repatriation' => ['description' => 'MEDICAL TRANSPORTATION OR REPATRIATION'],
            'transport_person_hospitalisation' => ['description' => 'TRANSPORT OF A PERSON DUE TO THE HOSPITALISATION OF THE INSURED RETURN TICKETS IN ECONOMY CLASS AND'],
            'max_10_days_excess' => ['description' => 'MAX 10 DAYS/EXCESS'],
            'transportation_repatriation_accompanying' => ['description' => 'TRANSPORTATION OR REPATRIATION OF THE ACCOMPANYING INSUREDS'],
            'medical_expenses' => ['description' => 'MEDICAL EXPENSES'],
            'medical_expenses_abroad_covid' => ['description' => 'MEDICAL EXPENSES ABROAD (COVID-19 INCLUDED)/'],
            'compulsory_quarantine_covid' => ['description' => 'COMPULSORY QUARANTINE DUE TO DIAGNOSED COVID-19'],
            'repatriation_mortal_remains' => ['description' => 'REPATRIATION OF MORTAL REMAINS'],
            'transport_repatriation_deceased' => ['description' => 'TRANSPORT OR REPATRIATION OF THE DECEASED INSURED'],
            'baggage' => ['description' => 'BAGGAGE'],
            'indemnity_checked_luggage' => ['description' => 'INDEMNITY DUE TO PROBLEMS WITH THE CHECKED-IN LUGGAGE & TRAVEL DOCUMENTS (ACCIDENTAL DAMAGE, LOSS, ROBBERY)'],
            'compensation_baggage_delay' => ['description' => 'COMPENSATION FOR BAGGAGE DELAY', 'excess' => 'Time Excess'],
            'location_forwarding_baggage' => ['description' => 'LOCATION AND FORWARDING OF BAGGAGE AND PERSONAL BELONGINGS ACTUAL COST'],
            'cancellation' => ['description' => 'CANCELLATION'],
            'reimbursement_cancellation_expenses' => ['description' => 'REIMBURSEMENT OF THE CANCELLATION EXPENSES OF THE TRIP'],
            'curtailment' => ['description' => 'CURTAILMENT'],
            'curtailment_expenses' => ['description' => 'CURTAILMENT EXPENSES'],
            'early_return_family_matter' => ['description' => 'EARLY RETURN DUE TO SERIOUS FAMILY MATTER SAME CLASS TICKET'],
            'personal_accident' => ['description' => 'PERSONAL ACCIDENT'],
            'permanent_accidental_disability' => ['description' => 'PERMANENT ACCIDENTAL DISABILITY (MEANS OF TRANSPORT)'],
            'accidental_death_means_transport' => ['description' => 'ACCIDENTAL DEATH MEANS OF TRANSPORT'],
            'personal_liability' => ['description' => 'PERSONAL LIABILITY'],
            'personal_liability_material_damages' => ['description' => 'PERSONAL LIABILITY DUE TO MATERIAL DAMAGES TO THIRD-PARTIES'],
            'legal_defence_not_traffic' => ['description' => 'LEGAL DEFENCE (NOT TRAFFIC)'],
            'deposit_legal_costs' => ['description' => 'DEPOSIT FOR LEGAL COSTS AND EXPENSES'],
            'personal_liability_physical_damages' => ['description' => 'PERSONAL LIABILITY DUE TO PHYSICAL DAMAGES TO THIRD-PARTIES'],
            'medical_complementary_services' => ['description' => 'MEDICAL COMPLEMENTARY SERVICES'],
            'hospital_compensation' => ['description' => 'HOSPITAL COMPENSATION'],
            'cards' => ['description' => 'CARDS'],
            'replacement_passport_driving_licence' => ['description' => 'REPLACEMENT OF THE PASSPORT AND THE DRIVING LICENCE BY EMERGENCY DOCUMENTS'],
            'delays' => ['description' => 'DELAYS'],
            'indemnity_transport_departure_delay' => ['description' => 'INDEMNITY DUE TO THE TRANSPORT DEPARTURE DELAY'],
            'missed_connections' => ['description' => 'MISSED CONNECTIONS'],
        ];
        
        if (!isset($this->travelBenefits[$policyCoverageId])) {
            $this->travelBenefits[$policyCoverageId] = [];
        }
        
        foreach ($benefitDefinitions as $key => $definition) {
            if (!isset($this->travelBenefits[$policyCoverageId][$key])) {
                $this->travelBenefits[$policyCoverageId][$key] = [
                    'description' => ucwords(strtolower($definition['description'])),
                    'sum_insured' => $definition['sum_insured'] ?? '',
                    'excess' => $definition['excess'] ?? '',
                    '_removed' => false,
                ];
            } else {
                // If row was removed, don't auto-populate values back
                if (($this->travelBenefits[$policyCoverageId][$key]['_removed'] ?? false) === true) {
                    // Ensure description is still set for data integrity
                    if (empty($this->travelBenefits[$policyCoverageId][$key]['description'])) {
                        $this->travelBenefits[$policyCoverageId][$key]['description'] = $definition['description'];
                    }
                    continue;
                }

                // Ensure description is always set
                if (empty($this->travelBenefits[$policyCoverageId][$key]['description'])) {
                    $this->travelBenefits[$policyCoverageId][$key]['description'] = $definition['description'];
                }
                // Auto-populate Sum Insured if empty and definition has it
                if (empty($this->travelBenefits[$policyCoverageId][$key]['sum_insured']) && isset($definition['sum_insured'])) {
                    $this->travelBenefits[$policyCoverageId][$key]['sum_insured'] = $definition['sum_insured'];
                }
                // Auto-populate Excess if empty and definition has it
                if (empty($this->travelBenefits[$policyCoverageId][$key]['excess']) && isset($definition['excess'])) {
                    $this->travelBenefits[$policyCoverageId][$key]['excess'] = $definition['excess'];
                }
            }
        }
    }

    public function addTravelBenefit($policyCoverageId)
    {
        if (!isset($this->travelBenefits[$policyCoverageId])) {
            $this->travelBenefits[$policyCoverageId] = [];
        }
        $this->travelBenefits[$policyCoverageId][] = [
            'description' => '',
            'sum_insured' => '',
            'excess' => ''
        ];
    }

    /**
     * Auto-populate Sum Insured and Excess fields based on selected benefit (key-based)
     */
    public function updateTravelBenefitFields($policyCoverageId, $key)
    {
        if (!isset($this->travelBenefits[$policyCoverageId][$key])) {
            return;
        }

        $description = $this->travelBenefits[$policyCoverageId][$key]['description'] ?? '';

        // Auto-populate Sum Insured based on benefit type
        switch ($description) {
            case 'RELAY OF URGENT MESSAGES':
            case 'DISPATCH OF MEDICATION':
                $this->travelBenefits[$policyCoverageId][$key]['sum_insured'] = 'INCLUDED - SERVICE ONLY';
                break;
            case 'GENERAL INFORMATION INCLUDED -':
                $this->travelBenefits[$policyCoverageId][$key]['sum_insured'] = 'SERVICE ONLY';
                break;
            default:
                // Don't auto-populate for other benefits
                break;
        }

        // Auto-populate Excess based on benefit type
        switch ($description) {
            case 'COMPENSATION FOR BAGGAGE DELAY':
                $this->travelBenefits[$policyCoverageId][$key]['excess'] = 'Time Excess';
                break;
            default:
                // Don't auto-populate for other benefits
                break;
        }
    }

    /**
     * Remove (hide) a fixed Travel benefit row from UI.
     * We keep the data with a `_removed` flag so it stays removed after reload.
     */
    public function removeTravelBenefitRow($policyCoverageId, $benefitKey)
    {
        if (!isset($this->travelBenefits[$policyCoverageId][$benefitKey])) {
            return;
        }

        $this->travelBenefits[$policyCoverageId][$benefitKey]['sum_insured'] = '';
        $this->travelBenefits[$policyCoverageId][$benefitKey]['excess'] = '';
        $this->travelBenefits[$policyCoverageId][$benefitKey]['_removed'] = true;
    }

    public function removeTravelBenefit($policyCoverageId, $index)
    {
        if (isset($this->travelBenefits[$policyCoverageId][$index])) {
            unset($this->travelBenefits[$policyCoverageId][$index]);
            $this->travelBenefits[$policyCoverageId] = array_values($this->travelBenefits[$policyCoverageId]);
        }
    }

    public function saveTravelCoverage($policyCoverageId)
    {
        $this->saveCoverageData($policyCoverageId, 'TRAVEL');
        $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Travel Coverage saved successfully!']);
    }

    /**
     * Calculate travel policy period based on effective_from and expiry dates
     */
    public function calculateTravelPolicyPeriod($policyCoverageId)
    {
        if (!isset($this->travelCoverage[$policyCoverageId])) {
            return;
        }

        $effectiveFrom = $this->travelCoverage[$policyCoverageId]['effective_from'] ?? null;
        $expiry = $this->travelCoverage[$policyCoverageId]['expiry'] ?? null;

        if (empty($effectiveFrom) || empty($expiry)) {
            $this->travelCoverage[$policyCoverageId]['policy_period_months'] = '';
            return;
        }

        $months = $this->calculateMonthsBetweenDates($effectiveFrom, $expiry);
        if ($months !== null) {
            $policyPeriodMonths = $this->getPolicyPeriodMonths($months);
            if ($policyPeriodMonths) {
                $this->travelCoverage[$policyCoverageId]['policy_period_months'] = $policyPeriodMonths;
            } else {
                $this->travelCoverage[$policyCoverageId]['policy_period_months'] = '';
            }
        } else {
            $this->travelCoverage[$policyCoverageId]['policy_period_months'] = '';
        }
    }

    /**
     * Calculate VAT and Total for travel coverage based on policy amount
     */
    public function calculateTravelVatAndGrandTotalPremium($policyCoverageId)
    {
        if (!isset($this->travelCoverage[$policyCoverageId])) {
            return;
        }

        $policyAmount = $this->travelCoverage[$policyCoverageId]['policy_amount'] ?? '0';
        $policyAmount = (float) str_replace(',', '', $policyAmount);
        if ($policyAmount <= 0) {
            $this->travelCoverage[$policyCoverageId]['vat'] = '';
            $this->travelCoverage[$policyCoverageId]['total'] = '';
            return;
        }
        $regionVat = \AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
        $vat = $policyAmount * ($regionVat/100);
        $total = $policyAmount + $vat;
        $this->travelCoverage[$policyCoverageId]['vat'] = number_format($vat, 2, '.', ',');
        $this->travelCoverage[$policyCoverageId]['total'] = number_format($total, 2, '.', ',');
        $data = [
            'policy_amount' => $policyAmount,
            'vat' => $vat,
            'total' => $total,
        ];
        TravelCoverageModel::where('policy_coverage_id', $policyCoverageId)->update($data);
    }

    public function calculateTravelVatAndTotal($policyCoverageId)
    {
        if (!isset($this->travelCoverage[$policyCoverageId])) {
            return;
        }

        $policyAmount = $this->travelCoverage[$policyCoverageId]['total'] ?? '0';
        
        // Clean the policy amount (remove commas, currency symbols, etc.)
        $policyAmount = (float) str_replace(',', '', $policyAmount);
        
        if ($policyAmount <= 0) {
            $this->travelCoverage[$policyCoverageId]['vat'] = '';
            $this->travelCoverage[$policyCoverageId]['policy_amount'] = '';
            return;
        }

        // Get VAT rate from Region (using region id 7 as default, similar to quotesheet)
        try {
            $regionVat = \AlphaDirect\Region::where('id', 7)->first('vat')?->vat;   
            $premiumExcludingVAT =  ($policyAmount) / (1 + ($regionVat/100));
            $vat = $premiumExcludingVAT * ($regionVat/100);
            
            // Format values with 2 decimal places
            $this->travelCoverage[$policyCoverageId]['vat'] = number_format($vat, 2, '.', ',');
            $this->travelCoverage[$policyCoverageId]['policy_amount'] = number_format($premiumExcludingVAT, 2, '.', ',');
            $data = [
                'policy_amount' => $premiumExcludingVAT,
                'vat' => $vat,
                'total' => $policyAmount
            ];
            TravelCoverageModel::where('policy_coverage_id', $policyCoverageId)->update($data);
            
        } catch (\Exception $e) {
            \Log::error('Error calculating travel VAT and Total', [
                'policy_coverage_id' => $policyCoverageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add a new custom benefit row for travel coverage
     */
    public function addTravelCustomBenefitRow($policyCoverageId)
    {
        if (!isset($this->travelCustomBenefits[$policyCoverageId])) {
            $this->travelCustomBenefits[$policyCoverageId] = [];
        }
        $this->travelCustomBenefits[$policyCoverageId][] = [
            'description' => '',
            'sum_insured' => '',
            'excess' => ''
        ];
    }

    /**
     * Remove a custom benefit row for travel coverage
     */
    public function removeTravelCustomBenefitRow($policyCoverageId, $index)
    {
        unset($this->travelCustomBenefits[$policyCoverageId][$index]);
        $this->travelCustomBenefits[$policyCoverageId] = array_values($this->travelCustomBenefits[$policyCoverageId]);
    }

    /**
     * Recalculate Total when VAT is manually changed (though VAT should be readonly)
     */
    public function calculateTravelTotal($policyCoverageId)
    {
        if (!isset($this->travelCoverage[$policyCoverageId])) {
            return;
        }

        $policyAmount = $this->travelCoverage[$policyCoverageId]['policy_amount'] ?? '0';
        $vat = $this->travelCoverage[$policyCoverageId]['vat'] ?? '0';

        // Clean the values
        $policyAmount = (float) str_replace(',', '', $policyAmount);
        $vat = (float) str_replace(',', '', $vat);

        // Calculate Total: Policy Amount + VAT
        $total = $policyAmount + $vat;

        // Format total with 2 decimal places
        $this->travelCoverage[$policyCoverageId]['total'] = number_format($total, 2, '.', ',');
    }

    // Approve policy period for 24/36 months
    public function approvePolicyPeriod($policyCoverageId, $coverageType)
    {
        // Ensure permission exists in database, create if it doesn't
        Permission::findOrCreate('policy_period_authorization', 'web');
       
        // Check if user has permission to approve policy period
        if (!auth()->user()->hasPermissionTo('policy_period_authorization')) {
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'You do not have permission to approve policy period.']);
            return;
        }

        $userId = auth()->user()->id;
        $now = now();

        try {
           
            if ($coverageType === 'CAR') {
                CarCoverageModel::where('policy_coverage_id', $policyCoverageId)
                    ->where('policy_id', $this->policy->id)
                    ->update([
                        'approved_by' => $userId,
                        'approved_at' => $now
                    ]);
                
                // Reload the data to get updated approval info
                $this->loadCarCoverageData($policyCoverageId);
            } elseif ($coverageType === 'EAR') {
                EarCoverageModel::where('policy_coverage_id', $policyCoverageId)
                    ->where('policy_id', $this->policy->id)
                    ->update([
                        'approved_by' => $userId,
                        'approved_at' => $now
                    ]);
                
                // Reload the data to get updated approval info
                $this->loadEarCoverageData($policyCoverageId);
            } elseif ($coverageType === 'PAR') {
                ParCoverageModel::where('policy_coverage_id', $policyCoverageId)
                    ->where('policy_id', $this->policy->id)
                    ->update([
                        'approved_by' => $userId,
                        'approved_at' => $now
                    ]);
                
                // Reload the data to get updated approval info
                $this->loadParCoverageData($policyCoverageId);
            } elseif ($coverageType === 'TRAVEL') {
               
                TravelCoverageModel::where('policy_coverage_id', $policyCoverageId)
                    ->where('policy_id', $this->policy->id)
                    ->update([
                        'approved_by' => $userId,
                        'approved_at' => $now
                    ]);
                
                // Reload the data to get updated approval info
                $this->loadTravelCoverageData($policyCoverageId);
            } elseif ($coverageType === 'PI') {
                ProfessionalIndemnityCoverageModel::where('policy_coverage_id', $policyCoverageId)
                    ->where('policy_id', $this->policy->id)
                    ->update([
                        'approved_by' => $userId,
                        'approved_at' => $now
                    ]);
                
                // Reload the data to get updated approval info
                $this->loadTravelCoverageData($policyCoverageId);

                // Reload the data to get updated approval info
                $this->loadProfessionalIndemnityData($policyCoverageId, true);
            } elseif ($coverageType === 'MM') {
                MedicalMalpracticeCoverageModel::where('policy_coverage_id', $policyCoverageId)
                    ->where('policy_id', $this->policy->id)
                    ->update([
                        'approved_by' => $userId,
                        'approved_at' => $now
                    ]);

                $this->loadMedicalMalpracticeData($policyCoverageId);
            } else {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Invalid coverage type.']);
                return;
            }

            $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Policy period approved successfully!']);
        } catch (\Exception $e) {
            \Log::error('Error approving policy period: ' . $e->getMessage());
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to approve policy period. Please try again.']);
        }
    }

    /**
     * Validate sum insured and premium fields
     * Rules:
     * - If sum_insured/limit_of_indemnity has value, premium must have value (unless rate is 0, then premium should be 0)
     * - If premium has value, sum_insured/limit_of_indemnity must have value
     * - If rate is 0 and sum_insured has value, set premium to 0
     */
    private function validateSumInsuredAndPremium($policyCoverageId, $coverageType)
    {
        $errors = [];

        if ($coverageType === 'CAR') {
            // Validate CAR Section 1 Items
            if (isset($this->carSection1Items[$policyCoverageId])) {
                foreach ($this->carSection1Items[$policyCoverageId] as $index => $item) {
                    $sumInsured = trim($item['sum_insured'] ?? '');
                    $premium = trim($item['premium'] ?? '');
                    $rate = (float) str_replace(',', '', $item['rate'] ?? '0');
                    $sumInsuredValue = (float) str_replace(',', '', $sumInsured);
                    $premiumValue = (float) str_replace(',', '', $premium);
                    
                    // If sum insured has value (and not 0)
                    if (!empty($sumInsured) && $sumInsuredValue != 0) {
                        // If rate is 0, premium should be 0
                        if ($rate == 0) {
                            $this->carSection1Items[$policyCoverageId][$index]['premium'] = '0';
                        } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["carSection1Items.{$policyCoverageId}.{$index}.premium"] = 'Premium is required when Sum Insured has a value.';
                        }
                    }
                    
                    // If premium has value (and not 0), sum insured must have value
                    if (!empty($premium) && $premiumValue != 0 && (empty($sumInsured) || $sumInsuredValue == 0)) {
                        $errors["carSection1Items.{$policyCoverageId}.{$index}.sum_insured"] = 'Sum Insured is required when Premium has a value.';
                    }
                }
            }

            // Validate CAR Section 2 Items
            if (isset($this->carSection2Items[$policyCoverageId])) {
                foreach ($this->carSection2Items[$policyCoverageId] as $index => $item) {
                    $limitOfIndemnity = trim($item['limit_of_indemnity'] ?? '');
                    $premium = trim($item['premium'] ?? '');
                    $rate = (float) str_replace(',', '', $item['rate'] ?? '0');
                    $limitValue = (float) str_replace(',', '', $limitOfIndemnity);
                    $premiumValue = (float) str_replace(',', '', $premium);
                    
                    // If limit of indemnity has value (and not 0)
                    if (!empty($limitOfIndemnity) && $limitValue != 0) {
                        // If rate is 0, premium should be 0
                        if ($rate == 0) {
                            $this->carSection2Items[$policyCoverageId][$index]['premium'] = '0';
                        } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["carSection2Items.{$policyCoverageId}.{$index}.premium"] = 'Premium is required when Limit of Indemnity has a value.';
                        }
                    }
                    
                    if (!empty($premium) && $premiumValue != 0 && (empty($limitOfIndemnity) || $limitValue == 0)) {
                        $errors["carSection2Items.{$policyCoverageId}.{$index}.limit_of_indemnity"] = 'Limit of Indemnity is required when Premium has a value.';
                    }
                }
            }

            // Validate CAR Section 3 - Gross Profit (Annual Sum Insured and Premium)
            if (isset($this->carCoverage[$policyCoverageId])) {
                $annualSumInsured = trim($this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured'] ?? '');
                $premium = trim($this->carCoverage[$policyCoverageId]['section3_gross_profit_premium'] ?? '');
                $rate = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_gross_profit_rate'] ?? '0');
                $annualSumValue = (float) str_replace(',', '', $annualSumInsured);
                $premiumValue = (float) str_replace(',', '', $premium);
                
                // If annual sum insured has value (and not 0)
                if (!empty($annualSumInsured) && $annualSumValue != 0) {
                    // If rate is 0, premium should be 0
                    if ($rate == 0) {
                        $this->carCoverage[$policyCoverageId]['section3_gross_profit_premium'] = '0';
                    } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                        // Calculate premium if sum insured and rate are present but premium is missing
                        if ($rate > 0) {
                            $calculatedPremium = ($annualSumValue * $rate) / 100;
                            $this->carCoverage[$policyCoverageId]['section3_gross_profit_premium'] = number_format($calculatedPremium, 2, '.', '');
                        } else {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["carCoverage.{$policyCoverageId}.section3_gross_profit_premium"] = 'Premium is required when Annual Sum Insured has a value.';
                        }
                    }
                }
                
                if (!empty($premium) && $premiumValue != 0 && (empty($annualSumInsured) || $annualSumValue == 0)) {
                    $errors["carCoverage.{$policyCoverageId}.section3_gross_profit_annual_sum_insured"] = 'Annual Sum Insured is required when Premium has a value.';
                }
            }
            
            // Validate CAR Section 3 - Increased Cost of Working (Sum Insured and Premium)
            if (isset($this->carCoverage[$policyCoverageId])) {
                $sumInsured = trim($this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured'] ?? '');
                $premium = trim($this->carCoverage[$policyCoverageId]['section3_increased_cost_premium'] ?? '');
                $rate = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_increased_cost_rate'] ?? '0');
                $sumInsuredValue = (float) str_replace(',', '', $sumInsured);
                $premiumValue = (float) str_replace(',', '', $premium);
                
                // If sum insured has value (and not 0)
                if (!empty($sumInsured) && $sumInsuredValue != 0) {
                    // If rate is 0, premium should be 0
                    if ($rate == 0) {
                        $this->carCoverage[$policyCoverageId]['section3_increased_cost_premium'] = '0';
                    } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                        // Calculate premium if sum insured and rate are present but premium is missing
                        if ($rate > 0) {
                            $calculatedPremium = ($sumInsuredValue * $rate) / 100;
                            $this->carCoverage[$policyCoverageId]['section3_increased_cost_premium'] = number_format($calculatedPremium, 2, '.', '');
                        } else {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["carCoverage.{$policyCoverageId}.section3_increased_cost_premium"] = 'Premium is required when Sum Insured for Maximum Indemnity Period has a value.';
                        }
                    }
                }
                
                if (!empty($premium) && $premiumValue != 0 && (empty($sumInsured) || $sumInsuredValue == 0)) {
                    $errors["carCoverage.{$policyCoverageId}.section3_increased_cost_sum_insured"] = 'Sum Insured for Maximum Indemnity Period is required when Premium has a value.';
                }
            }
            
            // Legacy validation for old Section 3 fields (for backward compatibility)
            if (isset($this->carCoverage[$policyCoverageId])) {
                $annualSumInsured = trim($this->carCoverage[$policyCoverageId]['section3_annual_sum_insured'] ?? '');
                $premium = trim($this->carCoverage[$policyCoverageId]['section3_premium'] ?? '');
                $rate = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_rate'] ?? '0');
                $annualSumValue = (float) str_replace(',', '', $annualSumInsured);
                $premiumValue = (float) str_replace(',', '', $premium);
                
                // If annual sum insured has value (and not 0)
                if (!empty($annualSumInsured) && $annualSumValue != 0) {
                    // If rate is 0, premium should be 0
                    if ($rate == 0) {
                        $this->carCoverage[$policyCoverageId]['section3_premium'] = '0';
                    } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                        // Premium = 0 is valid, only check if actually empty (not "0")
                        $errors["carCoverage.{$policyCoverageId}.section3_premium"] = 'Premium is required when Annual Sum Insured has a value.';
                    }
                }
                
                if (!empty($premium) && $premiumValue != 0 && (empty($annualSumInsured) || $annualSumValue == 0)) {
                    $errors["carCoverage.{$policyCoverageId}.section3_annual_sum_insured"] = 'Annual Sum Insured is required when Premium has a value.';
                }
            }

            // Validate CAR Risk Section (Earthquake and Storm)
            if (isset($this->carCoverage[$policyCoverageId])) {
                // Earthquake
                $earthquakeLimit = trim($this->carCoverage[$policyCoverageId]['section1_earthquake_limit_indemnity'] ?? '');
                $earthquakePremium = trim($this->carCoverage[$policyCoverageId]['section1_earthquake_premium'] ?? '');
                $earthquakeLimitValue = (float) str_replace(',', '', $earthquakeLimit);
                $earthquakePremiumValue = (float) str_replace(',', '', $earthquakePremium);
                
                // If limit of indemnity has value (and not 0), premium must not be empty (0 is valid)
                if (!empty($earthquakeLimit) && $earthquakeLimitValue != 0) {
                    if ($earthquakePremium === '' || $earthquakePremium === null || strtolower($earthquakePremium) === 'premium') {
                        // Premium = 0 is valid, only check if actually empty (not "0")
                        $errors["carCoverage.{$policyCoverageId}.section1_earthquake_premium"] = 'Premium is required when Limit of Indemnity has a value.';
                    }
                }
                
                // If premium has value, limit must have value
                if (!empty($earthquakePremium) && $earthquakePremiumValue != 0 && (empty($earthquakeLimit) || $earthquakeLimitValue == 0)) {
                    $errors["carCoverage.{$policyCoverageId}.section1_earthquake_limit_indemnity"] = 'Limit of Indemnity is required when Premium has a value.';
                }

                // Storm
                $stormLimit = trim($this->carCoverage[$policyCoverageId]['section1_storm_limit_indemnity'] ?? '');
                $stormPremium = trim($this->carCoverage[$policyCoverageId]['section1_storm_premium'] ?? '');
                $stormLimitValue = (float) str_replace(',', '', $stormLimit);
                $stormPremiumValue = (float) str_replace(',', '', $stormPremium);
                
                // If limit of indemnity has value (and not 0), premium must not be empty (0 is valid)
                if (!empty($stormLimit) && $stormLimitValue != 0) {
                    if ($stormPremium === '' || $stormPremium === null || strtolower($stormPremium) === 'premium') {
                        // Premium = 0 is valid, only check if actually empty (not "0")
                        $errors["carCoverage.{$policyCoverageId}.section1_storm_premium"] = 'Premium is required when Limit of Indemnity has a value.';
                    }
                }
                
                // If premium has value, limit must have value
                if (!empty($stormPremium) && $stormPremiumValue != 0 && (empty($stormLimit) || $stormLimitValue == 0)) {
                    $errors["carCoverage.{$policyCoverageId}.section1_storm_limit_indemnity"] = 'Limit of Indemnity is required when Premium has a value.';
                }
            }

            // Validate CAR Plant List Items
            if (isset($this->carPlantListItems[$policyCoverageId])) {
                foreach ($this->carPlantListItems[$policyCoverageId] as $index => $item) {
                    $sumInsured = trim($item['sum_insured'] ?? '');
                    $premium = trim($item['premium'] ?? '');
                    $sumInsuredValue = (float) str_replace(',', '', $sumInsured);
                    $premiumValue = (float) str_replace(',', '', $premium);
                    
                    // If sum insured has value (and not 0), premium must not be empty (0 is valid)
                    if (!empty($sumInsured) && $sumInsuredValue != 0) {
                        // Check if premium is actually empty (not "0" - "0" is valid)
                        // empty() returns true for "0", so we need to check explicitly
                        if ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                            $errors["carPlantListItems.{$policyCoverageId}.{$index}.premium"] = 'Premium is required when Sum Insured has a value.';
                        }
                    }
                    
                    // If premium has value (and not 0), sum insured must have value
                    if (!empty($premium) && $premiumValue != 0 && (empty($sumInsured) || $sumInsuredValue == 0)) {
                        $errors["carPlantListItems.{$policyCoverageId}.{$index}.sum_insured"] = 'Sum Insured is required when Premium has a value.';
                    }
                }
            }
        }

        if ($coverageType === 'PAR') {
            // Validate PAR Insured Items
            if (isset($this->parInsuredItems[$policyCoverageId])) {
                foreach ($this->parInsuredItems[$policyCoverageId] as $index => $item) {
                    $sumInsured = trim($item['sum_insured'] ?? '');
                    $premium = trim($item['premium'] ?? '');
                    $rate = (float) str_replace(',', '', $item['rate'] ?? '0');
                    $sumInsuredValue = (float) str_replace(',', '', $sumInsured);
                    $premiumValue = (float) str_replace(',', '', $premium);
                    
                    // Validate Year of Manufacture - must be exactly 4 digits
                    $yearOfManufacture = trim($item['year_of_manufacture'] ?? '');
                    if (!empty($yearOfManufacture)) {
                        // Check if it's exactly 4 digits
                        if (!preg_match('/^\d{4}$/', $yearOfManufacture)) {
                            $errors["parInsuredItems.{$policyCoverageId}.{$index}.year_of_manufacture"] = 'Year of manufacture must be exactly 4 digits.';
                        }
                    }
                    
                    // If sum insured has value (and not 0)
                    if (!empty($sumInsured) && $sumInsuredValue != 0) {
                        // If rate is 0, premium should be 0
                        if ($rate == 0) {
                            $this->parInsuredItems[$policyCoverageId][$index]['premium'] = '0';
                        } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["parInsuredItems.{$policyCoverageId}.{$index}.premium"] = 'Premium is required when Sum Insured has a value.';
                        }
                    }
                    
                    // If premium has value, sum insured must have value
                    if (!empty($premium) && $premiumValue != 0 && (empty($sumInsured) || $sumInsuredValue == 0)) {
                        $errors["parInsuredItems.{$policyCoverageId}.{$index}.sum_insured"] = 'Sum Insured is required when Premium has a value.';
                    }
                }
            }

            // Validate PAR Section 2 Items
            if (isset($this->parSection2Items[$policyCoverageId])) {
                foreach ($this->parSection2Items[$policyCoverageId] as $index => $item) {
                    $limitOfIndemnity = trim($item['limit_of_indemnity'] ?? '');
                    $premium = trim($item['premium'] ?? '');
                    $rate = (float) str_replace(',', '', $item['rate'] ?? '0');
                    $limitValue = (float) str_replace(',', '', $limitOfIndemnity);
                    $premiumValue = (float) str_replace(',', '', $premium);
                    
                    // If limit of indemnity has value (and not 0)
                    if (!empty($limitOfIndemnity) && $limitValue != 0) {
                        // If rate is 0, premium should be 0
                        if ($rate == 0) {
                            $this->parSection2Items[$policyCoverageId][$index]['premium'] = '0';
                        } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["parSection2Items.{$policyCoverageId}.{$index}.premium"] = 'Premium is required when Limit of Indemnity has a value.';
                        }
                    }
                    
                    // If premium has value, limit of indemnity must have value
                    if (!empty($premium) && $premiumValue != 0 && (empty($limitOfIndemnity) || $limitValue == 0)) {
                        $errors["parSection2Items.{$policyCoverageId}.{$index}.limit_of_indemnity"] = 'Limit of Indemnity is required when Premium has a value.';
                    }
                }
            }
        }

        if ($coverageType === 'EAR') {
            // Validate EAR Section 1 Items
            if (isset($this->earSection1Items[$policyCoverageId])) {
                foreach ($this->earSection1Items[$policyCoverageId] as $index => $item) {
                    $sumInsured = trim($item['sum_insured'] ?? '');
                    $premium = trim($item['premium'] ?? '');
                    $rate = (float) str_replace(',', '', $item['rate'] ?? '0');
                    $sumInsuredValue = (float) str_replace(',', '', $sumInsured);
                    $premiumValue = (float) str_replace(',', '', $premium);
                    
                    // If sum insured has value (and not 0)
                    if (!empty($sumInsured) && $sumInsuredValue != 0) {
                        // If rate is 0, premium should be 0
                        if ($rate == 0) {
                            $this->earSection1Items[$policyCoverageId][$index]['premium'] = '0';
                        } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["earSection1Items.{$policyCoverageId}.{$index}.premium"] = 'Premium is required when Sum Insured has a value.';
                        }
                    }
                    
                    // If premium has value, sum insured must have value
                    if (!empty($premium) && $premiumValue != 0 && (empty($sumInsured) || $sumInsuredValue == 0)) {
                        $errors["earSection1Items.{$policyCoverageId}.{$index}.sum_insured"] = 'Sum Insured is required when Premium has a value.';
                    }
                }
            }

            // Validate EAR Section 3 Items
            if (isset($this->earSection3Items[$policyCoverageId])) {
                foreach ($this->earSection3Items[$policyCoverageId] as $index => $item) {
                    $limitOfIndemnity = trim($item['limit_of_indemnity'] ?? '');
                    $premium = trim($item['premium'] ?? '');
                    $rate = (float) str_replace(',', '', $item['rate'] ?? '0');
                    $limitValue = (float) str_replace(',', '', $limitOfIndemnity);
                    $premiumValue = (float) str_replace(',', '', $premium);
                    
                    // If limit of indemnity has value (and not 0)
                    if (!empty($limitOfIndemnity) && $limitValue != 0) {
                        // If rate is 0, premium should be 0
                        if ($rate == 0) {
                            $this->earSection3Items[$policyCoverageId][$index]['premium'] = '0';
                        } elseif ($premium === '' || $premium === null || strtolower($premium) === 'premium') {
                            // Premium = 0 is valid, only check if actually empty (not "0")
                            $errors["earSection3Items.{$policyCoverageId}.{$index}.premium"] = 'Premium is required when Limit of Indemnity has a value.';
                        }
                    }
                    
                    // If premium has value, limit of indemnity must have value
                    if (!empty($premium) && $premiumValue != 0 && (empty($limitOfIndemnity) || $limitValue == 0)) {
                        $errors["earSection3Items.{$policyCoverageId}.{$index}.limit_of_indemnity"] = 'Limit of Indemnity is required when Premium has a value.';
                    }
                }
            }

            // Validate EAR Risk Section (Earthquake and Storm)
            if (isset($this->earCoverage[$policyCoverageId])) {
                // Earthquake
                $earthquakeLimit = trim($this->earCoverage[$policyCoverageId]['risk_earthquake_limit_indemnity'] ?? '');
                $earthquakePremium = trim($this->earCoverage[$policyCoverageId]['risk_earthquake_premium'] ?? '');
                $earthquakeLimitValue = (float) str_replace(',', '', $earthquakeLimit);
                $earthquakePremiumValue = (float) str_replace(',', '', $earthquakePremium);
                
                // If limit of indemnity has value (and not 0), premium must not be empty (0 is valid)
                if (!empty($earthquakeLimit) && $earthquakeLimitValue != 0) {
                    if (empty($earthquakePremium) || strtolower($earthquakePremium) === 'premium') {
                        // Premium = 0 is valid, only check if empty
                        $errors["earCoverage.{$policyCoverageId}.risk_earthquake_premium"] = 'Premium is required when Limit of Indemnity has a value.';
                    }
                }
                
                // If premium has value, limit must have value
                if (!empty($earthquakePremium) && $earthquakePremiumValue != 0 && (empty($earthquakeLimit) || $earthquakeLimitValue == 0)) {
                    $errors["earCoverage.{$policyCoverageId}.risk_earthquake_limit_indemnity"] = 'Limit of Indemnity is required when Premium has a value.';
                }

                // Storm
                $stormLimit = trim($this->earCoverage[$policyCoverageId]['risk_storm_limit_indemnity'] ?? '');
                $stormPremium = trim($this->earCoverage[$policyCoverageId]['risk_storm_premium'] ?? '');
                $stormLimitValue = (float) str_replace(',', '', $stormLimit);
                $stormPremiumValue = (float) str_replace(',', '', $stormPremium);
                
                // If limit of indemnity has value (and not 0), premium must not be empty (0 is valid)
                if (!empty($stormLimit) && $stormLimitValue != 0) {
                    if (empty($stormPremium) || strtolower($stormPremium) === 'premium') {
                        // Premium = 0 is valid, only check if empty
                        $errors["earCoverage.{$policyCoverageId}.risk_storm_premium"] = 'Premium is required when Limit of Indemnity has a value.';
                    }
                }
                
                // If premium has value, limit must have value
                if (!empty($stormPremium) && $stormPremiumValue != 0 && (empty($stormLimit) || $stormLimitValue == 0)) {
                    $errors["earCoverage.{$policyCoverageId}.risk_storm_limit_indemnity"] = 'Limit of Indemnity is required when Premium has a value.';
                }
            }
        }

        // If there are validation errors, add them and return false
        if (!empty($errors)) {
            foreach ($errors as $field => $message) {
                $this->addError($field, $message);
            }
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Please fill in all required fields. Sum Insured and Premium must both have values, or both be empty.']);
            return false;
        }

        return true;
    }

    // Helper method to save specific coverage data
    /**
     * Handle policy wording file upload to S3
     */
    private function handlePolicyWordingUpload($policyCoverageId, $coverageType)
    {
        $filePropertyMap = [
            'PI' => 'piPolicyWording',
            'MM' => 'medicalMalpracticePolicyWording',
            'CAR' => 'carPolicyWording',
            'PAR' => 'parPolicyWording',
            'EAR' => 'earPolicyWording',
            'DOLIABILITY' => 'directorsOfficersLiabilityPolicyWording',
            'MARINEONCEOFFCOVER' => 'marineOnceOffCoverPolicyWording',
            'MARINEOPENCOVER' => 'marineOpenCoverPolicyWording',
            'MARINEDIRECTORSOFFICERS' => 'marineDirectorsOfficersPolicyWording',
        ];
        $fileProperty = $filePropertyMap[$coverageType] ?? (strtolower($coverageType) . 'PolicyWording');
        $file = $this->{$fileProperty} ?? null;
        if (!$file) {
            return null;
        }
        
        try {
            // Validate that it's a PDF file
            $extension = $file->getClientOriginalExtension();
            if (strtolower($extension) !== 'pdf') {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Only PDF files are allowed for policy wording.']);
                return null;
            }
            
            // Validate PDF DPI (maximum 600 DPI)
            $dpiValidation = $this->validatePdfDpi($file);
            if (!$dpiValidation['valid']) {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => $dpiValidation['message']]);
                return null;
            }
            
            // Generate unique filename
            $timestamp = time();
            $originalName = $file->getClientOriginalName();
            $filename = $coverageType . '_' . $policyCoverageId . '_' . $timestamp . '.pdf';
            
            // Upload path
            $path = 'policy-wordings/' . strtolower($coverageType) . '/' . $filename;
            
            // Normalize PDF to ensure compatibility with PDFMerger
            // Use Ghostscript to reprocess the PDF and remove incompatible compression
            $tempInputPath = $file->getRealPath();
            $tempOutputPath = storage_path('app/temp/' . $filename);
            
            // Create temp directory if it doesn't exist
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            // Use Ghostscript to normalize the PDF (remove compression issues)
            $gsCommand = sprintf(
                'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/default -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
                escapeshellarg($tempOutputPath),
                escapeshellarg($tempInputPath)
            );
            
            exec($gsCommand, $output, $returnCode);
            
            // If Ghostscript processing fails or not available, use original file
            if ($returnCode !== 0 || !file_exists($tempOutputPath)) {
                \Log::warning('Ghostscript processing failed or not available, using original PDF', [
                    'return_code' => $returnCode,
                    'output' => $output
                ]);
                // Upload original file
                Storage::disk('s3')->put($path, file_get_contents($tempInputPath), 'public');
            } else {
                // Upload normalized PDF
                Storage::disk('s3')->put($path, file_get_contents($tempOutputPath), 'public');
                \Log::info('PDF normalized successfully for ' . $coverageType . ' coverage');
                
                // Clean up temporary file
                if (file_exists($tempOutputPath)) {
                    @unlink($tempOutputPath);
                }
            }
            
            // Return the path
            return $path;
        } catch (\Exception $e) {
            \Log::error('Error uploading policy wording: ' . $e->getMessage());
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to upload policy wording file.']);
            return null;
        }
    }


    /**
     * Validate PDF DPI (maximum 600 DPI allowed)
     * Returns array with 'valid' boolean and 'message' string
     */
    private function validatePdfDpi($file)
    {
        try {
            $filePath = $file->getRealPath();
            
            // Use pdfinfo command to check PDF resolution/DPI
            // First, try to get image resolution from embedded images
            $command = sprintf('pdfimages -list %s 2>&1', escapeshellarg($filePath));
            exec($command, $output, $returnCode);
            
            // If pdfimages is available and successful
            if ($returnCode === 0 && count($output) > 2) {
                // Parse output to find maximum DPI
                $maxDpi = 0;
                
                // Skip header lines and parse data
                for ($i = 2; $i < count($output); $i++) {
                    $line = trim($output[$i]);
                    if (empty($line)) continue;
                    
                    // Split by whitespace
                    $parts = preg_split('/\s+/', $line);
                    
                    // Look for x-ppi and y-ppi columns (typically columns 8 and 9)
                    if (count($parts) >= 10) {
                        $xDpi = isset($parts[8]) ? intval($parts[8]) : 0;
                        $yDpi = isset($parts[9]) ? intval($parts[9]) : 0;
                        
                        $imageDpi = max($xDpi, $yDpi);
                        if ($imageDpi > $maxDpi) {
                            $maxDpi = $imageDpi;
                        }
                    }
                }
                
                // If we found DPI information and it exceeds 600
                if ($maxDpi > 600) {
                    return [
                        'valid' => false,
                        'message' => "PDF DPI is too high ({$maxDpi} DPI). Maximum allowed DPI is 600. Please reduce the DPI of your PDF file."
                    ];
                }
                
                // If DPI was found and is acceptable
                if ($maxDpi > 0) {
                    \Log::info("PDF DPI validation passed: {$maxDpi} DPI");
                    return [
                        'valid' => true,
                        'message' => "PDF DPI is acceptable ({$maxDpi} DPI)"
                    ];
                }
            }
            
            // Alternative method: Check PDF file size as a proxy
            // High DPI PDFs are typically very large
            $fileSizeInMB = $file->getSize() / (1024 * 1024);
            
            // If file is larger than 50MB, it's likely high DPI
            if ($fileSizeInMB > 50) {
                return [
                    'valid' => false,
                    'message' => "PDF file size is too large ({$fileSizeInMB} MB). This typically indicates high DPI images. Please reduce the DPI to 600 or lower and try again."
                ];
            }
            
            // If we can't determine DPI but file size is reasonable, allow it
            \Log::info("PDF DPI validation: Could not determine exact DPI, but file size is acceptable ({$fileSizeInMB} MB)");
            return [
                'valid' => true,
                'message' => 'PDF validation passed'
            ];
            
        } catch (\Exception $e) {
            \Log::warning('PDF DPI validation error: ' . $e->getMessage());
            // If validation fails, allow the upload (fail-open for better UX)
            return [
                'valid' => true,
                'message' => 'PDF validation could not be completed, proceeding with upload'
            ];
        }
    }

    private function saveCoverageData($policyCoverageId, $coverageType)
    {
        $policyCoverage = $this->policyCoverages->firstWhere('id', $policyCoverageId);
        if (!$policyCoverage) {
            return;
        }

        $coverageCode = '';
        if (isset($policyCoverage->coverage) && $policyCoverage->coverage) {
            $coverageCode = $policyCoverage->coverage->s_CoverageCode ?? '';
        }

        // Validate sum insured and premium fields before saving
        if (!$this->validateSumInsuredAndPremium($policyCoverageId, $coverageType)) {
            return; // Stop saving if validation fails
        }

        // Save CAR Coverage
        if (
            $coverageType === 'CAR' && in_array($coverageCode, ['CONTRACTORSALLRISKS', 'CAR']) &&
            (isset($this->carCoverage[$policyCoverageId]) ||
                isset($this->carSection1Items[$policyCoverageId]) ||
                isset($this->carSection2Items[$policyCoverageId]) ||
                isset($this->carSection3Items[$policyCoverageId]))
        ) {

            // Use stored premium values only — do not recalculate premium from rate on save
            $totalSumInsured = 0;
            $totalPremium = 0;
            if (isset($this->carSection1Items[$policyCoverageId])) {
                foreach ($this->carSection1Items[$policyCoverageId] as $index => $item) {
                    if (isset($item['sum_insured'])) {
                        $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                        $totalSumInsured += $sumInsured;
                    }
                    // Use premium as stored (user input); only derive from rate when premium is missing
                    if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
                        $premium = (float) str_replace(',', '', $item['premium']);
                        $totalPremium += $premium;
                    } elseif (isset($item['sum_insured'], $item['rate'])) {
                        $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                        $rate = (float) $item['rate'];
                        $premium = ($rate == 0) ? 0 : (($sumInsured * $rate) / 100);
                        $this->carSection1Items[$policyCoverageId][$index]['premium'] = ($premium == 0 && $rate == 0) ? '0' : $premium;
                        $totalPremium += $premium;
                    }
                }
            }

            // Section 2: use stored premium only; derive from rate only when premium is missing
            $section2TotalLimit = 0;
            $section2TotalPremium = 0;
            if (isset($this->carSection2Items[$policyCoverageId])) {
                foreach ($this->carSection2Items[$policyCoverageId] as $index => $item) {
                    if (isset($item['limit_of_indemnity'])) {
                        $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                        $section2TotalLimit += $limit;
                    }
                    if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
                        $premium = (float) str_replace(',', '', $item['premium']);
                        $section2TotalPremium += $premium;
                    } elseif (isset($item['limit_of_indemnity'], $item['rate'])) {
                        $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                        $rate = (float) $item['rate'];
                        $premium = ($rate == 0) ? 0 : (($limit * $rate) / 100);
                        $this->carSection2Items[$policyCoverageId][$index]['premium'] = ($premium == 0 && $rate == 0) ? '0' : $premium;
                        $section2TotalPremium += $premium;
                    }
                }
            }

            // Section 3: use stored premium only; derive from rate only when premium is missing
            $section3TotalAnnualSum = 0;
            $section3TotalPremium = 0;
            if (!isset($this->carCoverage[$policyCoverageId])) {
                $this->carCoverage[$policyCoverageId] = [];
            }
            $cov = &$this->carCoverage[$policyCoverageId];

            if (isset($cov['section3_gross_profit_annual_sum_insured'])) {
                $annualSum = (float) str_replace(',', '', $cov['section3_gross_profit_annual_sum_insured']);
                $section3TotalAnnualSum += $annualSum;
            }
            if (isset($cov['section3_gross_profit_premium']) && $cov['section3_gross_profit_premium'] !== '' && $cov['section3_gross_profit_premium'] !== null) {
                $premium = (float) str_replace(',', '', $cov['section3_gross_profit_premium']);
                $section3TotalPremium += $premium;
            } elseif (isset($cov['section3_gross_profit_annual_sum_insured'], $cov['section3_gross_profit_rate'])) {
                $annualSum = (float) str_replace(',', '', $cov['section3_gross_profit_annual_sum_insured']);
                $rate = (float) $cov['section3_gross_profit_rate'];
                $premium = ($rate == 0) ? 0 : (($annualSum * $rate) / 100);
                $cov['section3_gross_profit_premium'] = ($premium == 0 && $rate == 0) ? '0' : number_format($premium, 2, '.', '');
                $section3TotalPremium += $premium;
            }

            if (isset($cov['section3_increased_cost_sum_insured'])) {
                $sumInsured = (float) str_replace(',', '', $cov['section3_increased_cost_sum_insured']);
                $section3TotalAnnualSum += $sumInsured;
            }
            if (isset($cov['section3_increased_cost_premium']) && $cov['section3_increased_cost_premium'] !== '' && $cov['section3_increased_cost_premium'] !== null) {
                $premium = (float) str_replace(',', '', $cov['section3_increased_cost_premium']);
                $section3TotalPremium += $premium;
            } elseif (isset($cov['section3_increased_cost_sum_insured'], $cov['section3_increased_cost_rate'])) {
                $sumInsured = (float) str_replace(',', '', $cov['section3_increased_cost_sum_insured']);
                $rate = (float) $cov['section3_increased_cost_rate'];
                $premium = ($rate == 0) ? 0 : (($sumInsured * $rate) / 100);
                $cov['section3_increased_cost_premium'] = ($premium == 0 && $rate == 0) ? '0' : number_format($premium, 2, '.', '');
                $section3TotalPremium += $premium;
            }

            if (isset($cov['section3_annual_sum_insured'])) {
                $annualSum = (float) str_replace(',', '', $cov['section3_annual_sum_insured']);
                $section3TotalAnnualSum += $annualSum;
            }
            if (isset($cov['section3_premium']) && $cov['section3_premium'] !== '' && $cov['section3_premium'] !== null) {
                $premium = (float) str_replace(',', '', $cov['section3_premium']);
                $section3TotalPremium += $premium;
            } elseif (isset($cov['section3_annual_sum_insured'], $cov['section3_rate'])) {
                $annualSum = (float) str_replace(',', '', $cov['section3_annual_sum_insured']);
                $rate = (float) $cov['section3_rate'];
                $premium = ($rate == 0) ? 0 : (($annualSum * $rate) / 100);
                $cov['section3_premium'] = ($premium == 0 && $rate == 0) ? '0' : number_format($premium, 2, '.', '');
                $section3TotalPremium += $premium;
            }

            if (isset($this->carSection3Items[$policyCoverageId])) {
                foreach ($this->carSection3Items[$policyCoverageId] as $index => $item) {
                    if (isset($item['annual_sum_insured'])) {
                        $annualSum = (float) str_replace(',', '', $item['annual_sum_insured']);
                        $section3TotalAnnualSum += $annualSum;
                    }
                    if (isset($item['premium']) && $item['premium'] !== '' && $item['premium'] !== null) {
                        $premium = (float) str_replace(',', '', $item['premium']);
                        $section3TotalPremium += $premium;
                    } elseif (isset($item['annual_sum_insured'], $item['rate'])) {
                        $annualSum = (float) str_replace(',', '', $item['annual_sum_insured']);
                        $rate = (float) $item['rate'];
                        $premium = ($rate == 0) ? 0 : (($annualSum * $rate) / 100);
                        $this->carSection3Items[$policyCoverageId][$index]['premium'] = ($premium == 0 && $rate == 0) ? '0' : $premium;
                        $section3TotalPremium += $premium;
                    }
                }
            }

            // Set totals in carCoverage array
            if (!isset($this->carCoverage[$policyCoverageId])) {
                $this->carCoverage[$policyCoverageId] = [];
            }
            $this->carCoverage[$policyCoverageId]['section1_total_sum_insured'] = number_format($totalSumInsured, 2, '.', '');
            $this->carCoverage[$policyCoverageId]['section1_total_premium'] = number_format($totalPremium, 2, '.', '');
            $this->carCoverage[$policyCoverageId]['section2_total_limit'] = number_format($section2TotalLimit, 2, '.', '');
            $this->carCoverage[$policyCoverageId]['section2_total_premium'] = number_format($section2TotalPremium, 2, '.', '');
            $this->carCoverage[$policyCoverageId]['section3_total_annual_sum'] = number_format($section3TotalAnnualSum, 2, '.', '');
            $this->carCoverage[$policyCoverageId]['section3_total_premium'] = number_format($section3TotalPremium, 2, '.', '');

            $carCoveragePayload = $this->carCoverage[$policyCoverageId] ?? [];
            
            // Enforce renewable policy rule: if policy_period_months is not 12, set is_renewable to "No"
            if (isset($carCoveragePayload['policy_period_months']) && 
                !empty($carCoveragePayload['policy_period_months']) && 
                $carCoveragePayload['policy_period_months'] !== '12') {
                $carCoveragePayload['is_renewable'] = 'No';
            }
            
            $carCoveragePayload = $this->formatCoverageDatesForDb($carCoveragePayload, $this->carCoverageDateFields);
            
            // Handle policy wording file upload
            if ($this->carPolicyWording) {
                $wordingPath = $this->handlePolicyWordingUpload($policyCoverageId, 'CAR');
                if ($wordingPath) {
                    $carCoveragePayload['policy_wording_path'] = $wordingPath;
                    $this->carCoverage[$policyCoverageId]['policy_wording_path'] = $wordingPath;
                    $this->carPolicyWording = null;
                }
            }
            
            // Convert endorsements array back to individual columns
            $endorsements = $this->carEndorsements[$policyCoverageId] ?? [];
            $carCoveragePayload['endorsement_1'] = isset($endorsements[0]) ? ($endorsements[0]['text'] ?? '') : '';
            $carCoveragePayload['endorsement_2'] = isset($endorsements[1]) ? ($endorsements[1]['text'] ?? '') : '';
            $carCoveragePayload['endorsement_3'] = isset($endorsements[2]) ? ($endorsements[2]['text'] ?? '') : '';
            $carCoveragePayload['endorsement_4'] = isset($endorsements[3]) ? ($endorsements[3]['text'] ?? '') : '';
            // Handle more than 4 endorsements by concatenating them
            if (count($endorsements) > 4) {
                $additionalEndorsements = [];
                for ($i = 4; $i < count($endorsements); $i++) {
                    if (!empty($endorsements[$i]['text'])) {
                        $additionalEndorsements[] = $endorsements[$i]['text'];
                    }
                }
                if (!empty($additionalEndorsements)) {
                    $carCoveragePayload['endorsement_4'] = ($carCoveragePayload['endorsement_4'] ?? '') . 
                        (!empty($carCoveragePayload['endorsement_4']) ? '; ' : '') . 
                        implode('; ', $additionalEndorsements);
                }
            }
            
            // Clean numeric fields (limit of indemnity, premiums, etc.)
            // Note: Deductible fields are NOT cleaned as they can contain text
            $numericFields = [
                'section1_earthquake_limit_indemnity',
                'section1_storm_limit_indemnity',
                'section1_earthquake_premium',
                'section1_storm_premium',
                'section3_maximum_indemnity',
            ];
            $carCoveragePayload = $this->cleanNumericFieldsForDb($carCoveragePayload, $numericFields);

            $carData = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                ],
                $carCoveragePayload,
                [
                    // CarCoverage casts these as 'array' — same double-encode
                    // hazard as the PAR/EAR fixes above. Pass raw arrays.
                    'section1_items' => $this->carSection1Items[$policyCoverageId] ?? [],
                    'section2_items' => $this->carSection2Items[$policyCoverageId] ?? [],
                    'section3_items' => $this->carSection3Items[$policyCoverageId] ?? [],
                    'section3_contract_works' => $this->carSection3ContractWorks[$policyCoverageId] ?? [],
                    'plant_list_items' => $this->carPlantListItems[$policyCoverageId] ?? [],
                ]
            );

            CarCoverageModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id
                ],
                $carData
            );
        }

        // Save PAR Coverage
        if (
            $coverageType === 'PAR' && in_array($coverageCode, ['PLANTALLRISKS', 'PAR']) &&
            (isset($this->parCoverage[$policyCoverageId]) ||
                isset($this->parInsuredItems[$policyCoverageId]) ||
                isset($this->parSection2Items[$policyCoverageId]))
        ) {

            // Calculate totals
            $parTotalSumInsured = 0;
            $parTotalPremium = 0;
            if (isset($this->parInsuredItems[$policyCoverageId])) {
                foreach ($this->parInsuredItems[$policyCoverageId] as $item) {
                    if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
                        $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                        $parTotalSumInsured += $sumInsured;
                    }
                    if (isset($item['premium']) && !empty($item['premium'])) {
                        $premium = (float) str_replace(',', '', $item['premium']);
                        $parTotalPremium += $premium;
                    }
                }
            }

            $parSection2TotalLimit = 0;
            $parSection2TotalPremium = 0;
            if (isset($this->parSection2Items[$policyCoverageId])) {
                foreach ($this->parSection2Items[$policyCoverageId] as $item) {
                    if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
                        $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                        $parSection2TotalLimit += $limit;
                    }
                    if (isset($item['premium']) && !empty($item['premium'])) {
                        $premium = (float) str_replace(',', '', $item['premium']);
                        $parSection2TotalPremium += $premium;
                    }
                }
            }

            $parCoverageData = $this->parCoverage[$policyCoverageId] ?? [];
            
            // Enforce renewable policy rule: if policy_period_months is not 12, set is_renewable to "No"
            if (isset($parCoverageData['policy_period_months']) && 
                !empty($parCoverageData['policy_period_months']) && 
                $parCoverageData['policy_period_months'] !== '12') {
                $parCoverageData['is_renewable'] = 'No';
            }
            
            $parCoverageData['total_sum_insured'] = number_format($parTotalSumInsured, 2, '.', '');
            $parCoverageData['total_premium'] = number_format($parTotalPremium, 2, '.', '');
            $parCoverageData['section2_total_limit'] = number_format($parSection2TotalLimit, 2, '.', '');
            $parCoverageData['section2_total_premium'] = number_format($parSection2TotalPremium, 2, '.', '');
            $parCoverageData = $this->formatCoverageDatesForDb($parCoverageData, $this->parCoverageDateFields);
            
            // Handle policy wording file upload
            if ($this->parPolicyWording) {
                $wordingPath = $this->handlePolicyWordingUpload($policyCoverageId, 'PAR');
                if ($wordingPath) {
                    $parCoverageData['policy_wording_path'] = $wordingPath;
                    if (!isset($this->parCoverage[$policyCoverageId])) {
                        $this->parCoverage[$policyCoverageId] = [];
                    }
                    $this->parCoverage[$policyCoverageId]['policy_wording_path'] = $wordingPath;
                    $this->parPolicyWording = null;
                }
            }

            $parData = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                ],
                $parCoverageData,
                [
                    'action_id' => $this->actionId,
                    'term_id' => $this->termId,
                    // See array-cast double-encode note above.
                    'insured_items' => $this->parInsuredItems[$policyCoverageId] ?? [],
                    'section2_items' => $this->parSection2Items[$policyCoverageId] ?? [],
                ]
            );

            ParCoverageModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id,
                    'action_id' => $this->actionId,
                    'term_id' => $this->termId,
                ],
                $parData
            );
        }

        // Save EAR Coverage
        if (
            $coverageType === 'EAR' && in_array($coverageCode, ['ERECTIONALLRISKS', 'EAR']) &&
            (isset($this->earCoverage[$policyCoverageId]) ||
                isset($this->earSection1Items[$policyCoverageId]) ||
                isset($this->earSection3Items[$policyCoverageId]) ||
                isset($this->earEndorsements[$policyCoverageId]))
        ) {

            // Calculate Section 1 totals
            $earSection1TotalSumInsured = 0;
            $earSection1TotalPremium = 0;
            if (isset($this->earSection1Items[$policyCoverageId])) {
                foreach ($this->earSection1Items[$policyCoverageId] as $item) {
                    if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
                        $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                        $earSection1TotalSumInsured += $sumInsured;
                    }
                    if (isset($item['premium']) && !empty($item['premium'])) {
                        $premium = (float) str_replace(',', '', $item['premium']);
                        $earSection1TotalPremium += $premium;
                    }
                }
            }

            // Calculate Section 3 totals
            $earSection3TotalLimit = 0;
            $earSection3TotalPremium = 0;
            if (isset($this->earSection3Items[$policyCoverageId])) {
                foreach ($this->earSection3Items[$policyCoverageId] as $item) {
                    if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
                        $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                        $earSection3TotalLimit += $limit;
                    }
                    if (isset($item['premium']) && !empty($item['premium'])) {
                        $premium = (float) str_replace(',', '', $item['premium']);
                        $earSection3TotalPremium += $premium;
                    }
                }
            }

            $earCoverageData = $this->earCoverage[$policyCoverageId] ?? [];
            
            // Enforce renewable policy rule: if policy_period_months is not 12, set is_renewable to "No"
            if (isset($earCoverageData['policy_period_months']) && 
                !empty($earCoverageData['policy_period_months']) && 
                $earCoverageData['policy_period_months'] !== '12') {
                $earCoverageData['is_renewable'] = 'No';
            }
            
            $earCoverageData['section1_total_sum_insured'] = number_format($earSection1TotalSumInsured, 2, '.', '');
            $earCoverageData['section1_total_premium'] = number_format($earSection1TotalPremium, 2, '.', '');
            $earCoverageData['section3_total_limit'] = number_format($earSection3TotalLimit, 2, '.', '');
            $earCoverageData['section3_total_premium'] = number_format($earSection3TotalPremium, 2, '.', '');
            
            // Recalculate and update total premium before saving
            $this->calculateEarTotalPremium($policyCoverageId);
            // Ensure total_premium is included in earCoverageData
            if (isset($this->earCoverage[$policyCoverageId]['total_premium'])) {
                $earCoverageData['total_premium'] = $this->earCoverage[$policyCoverageId]['total_premium'];
            }

            // Format date fields before saving
            $earCoverageData = $this->formatCoverageDatesForDb($earCoverageData, $this->earCoverageDateFields);
            
            // Handle policy wording file upload
            if ($this->earPolicyWording) {
                $wordingPath = $this->handlePolicyWordingUpload($policyCoverageId, 'EAR');
                if ($wordingPath) {
                    $earCoverageData['policy_wording_path'] = $wordingPath;
                    if (!isset($this->earCoverage[$policyCoverageId])) {
                        $this->earCoverage[$policyCoverageId] = [];
                    }
                    $this->earCoverage[$policyCoverageId]['policy_wording_path'] = $wordingPath;
                    $this->earPolicyWording = null;
                }
            }
            
            // Store all endorsements as JSON in endorsement_1 for unlimited endorsements
            $endorsements = $this->earEndorsements[$policyCoverageId] ?? [];
            // Filter out empty endorsements
            $endorsements = array_filter($endorsements, function($endorsement) {
                return !empty($endorsement['text'] ?? '');
            });
            // Re-index array to ensure sequential keys
            $endorsements = array_values($endorsements);
            
            // Store as JSON in endorsement_1, clear other columns
            $earCoverageData['endorsement_1'] = !empty($endorsements) ? json_encode($endorsements, JSON_UNESCAPED_UNICODE) : '';
            $earCoverageData['endorsement_2'] = '';
            $earCoverageData['endorsement_3'] = '';
            $earCoverageData['endorsement_4'] = '';
            
            // Clean numeric fields (limit of indemnity, premiums, etc.)
            $numericFields = [
                'risk_earthquake_limit_indemnity',
                'risk_storm_limit_indemnity',
                'risk_earthquake_premium',
                'risk_storm_premium',
            ];
            $earCoverageData = $this->cleanNumericFieldsForDb($earCoverageData, $numericFields);

            $earData = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                ],
                $earCoverageData,
                [
                    // See array-cast double-encode note above.
                    'section1_items' => $this->earSection1Items[$policyCoverageId] ?? [],
                    'section3_items' => $this->earSection3Items[$policyCoverageId] ?? [],
                ]
            );

            EarCoverageModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id
                ],
                $earData
            );
        }

        // Save Directors & Officers Liability Coverage
        if (
            $coverageType === 'DOLIABILITY' && strtoupper($coverageCode) === 'DIRECTORSOFFICERSLIABILITY' &&
            (isset($this->directorsOfficersLiability[$policyCoverageId]) ||
                isset($this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId]) ||
                isset($this->directorsOfficersLiabilityExtensions[$policyCoverageId]) ||
                isset($this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId]))
        ) {
            $dolData = $this->directorsOfficersLiability[$policyCoverageId] ?? [];

            // Format date fields for DB
            $dolData = $this->formatCoverageDatesForDb($dolData, $this->dolDateFields);

            // Clean numeric fields
            $numericFields = ['premium', 'limit_of_liability'];
            $dolData = $this->cleanNumericFieldsForDb($dolData, $numericFields);

            // Handle policy wording file upload (simple local public disk per spec 4b)
            if ($this->directorsOfficersLiabilityPolicyWording) {
                $originalName = $this->directorsOfficersLiabilityPolicyWording->getClientOriginalName();
                $path = $this->directorsOfficersLiabilityPolicyWording->storeAs(
                    'policy-wordings/directors-officers-liability',
                    time() . '_' . $originalName,
                    'public'
                );
                $dolData['policy_wording_path'] = $path;
                $dolData['policy_wording_filename'] = $originalName;
                $this->directorsOfficersLiability[$policyCoverageId]['policy_wording_path'] = $path;
                $this->directorsOfficersLiability[$policyCoverageId]['policy_wording_filename'] = $originalName;
                $this->directorsOfficersLiabilityPolicyWording = null;
            }

            $dolPayload = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null,
                    'action_id' => $this->actionId,
                    'term_id' => $this->termId,
                ],
                $dolData,
                [
                    'insuring_clauses' => json_encode(array_values($this->directorsOfficersLiabilityInsuringClauses[$policyCoverageId] ?? []), JSON_UNESCAPED_UNICODE),
                    'extensions' => json_encode(array_values($this->directorsOfficersLiabilityExtensions[$policyCoverageId] ?? []), JSON_UNESCAPED_UNICODE),
                    'coverage_extensions' => json_encode(array_values($this->directorsOfficersLiabilityCoverageExtensions[$policyCoverageId] ?? []), JSON_UNESCAPED_UNICODE),
                ]
            );

            DOLiabilityModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id,
                ],
                $dolPayload
            );

            // Persist note
            if (isset($this->policyCoverageNote[$policyCoverageId])) {
                \AlphaDirect\Models\PolicyCoverageNote::updateOrCreate(
                    ['policy_coverage_id' => $policyCoverageId],
                    ['note' => $this->policyCoverageNote[$policyCoverageId]]
                );
            }
        }

        // Save Marine Once-Off Cover (matches both new MARINEONCEOFFCOVER and existing MARINECARGOONCEOFF codes)
        if (
            $coverageType === 'MARINEONCEOFFCOVER' && in_array(strtoupper($coverageCode), ['MARINEONCEOFFCOVER', 'MARINECARGOONCEOFF']) &&
            (isset($this->marineOnceOffCover[$policyCoverageId]) ||
                isset($this->marineOnceOffCoverClauses[$policyCoverageId]))
        ) {
            $marineData = $this->marineOnceOffCover[$policyCoverageId] ?? [];

            // Format date fields for DB
            $marineData = $this->formatCoverageDatesForDb($marineData, $this->marineOnceOffCoverDateFields);

            // Clean numeric fields
            $numericFields = [
                'annual_estimated_turnover',
                'location_limit',
                'premium_rate',
                'sum_insured',
                'premium',
                'per_conveyance_rail',
                'per_conveyance_road',
                'per_conveyance_air',
                'per_conveyance_post',
                'per_conveyance_vessel',
            ];
            $marineData = $this->cleanNumericFieldsForDb($marineData, $numericFields);

            // Handle policy wording file upload (local public disk)
            if ($this->marineOnceOffCoverPolicyWording) {
                $originalName = $this->marineOnceOffCoverPolicyWording->getClientOriginalName();
                $path = $this->marineOnceOffCoverPolicyWording->storeAs(
                    'policy-wordings/marine-onceoff-cover',
                    time() . '_' . $originalName,
                    'public'
                );
                $marineData['policy_wording_path'] = $path;
                $marineData['policy_wording_filename'] = $originalName;
                $this->marineOnceOffCover[$policyCoverageId]['policy_wording_path'] = $path;
                $this->marineOnceOffCover[$policyCoverageId]['policy_wording_filename'] = $originalName;
                $this->marineOnceOffCoverPolicyWording = null;
            }

            $marinePayload = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null,
                    'action_id' => $this->actionId,
                    'term_id' => $this->termId,
                ],
                $marineData,
                [
                    // MarineCargoOnceOffCoverage casts 'clauses' as 'array' — see the
                    // PAR/CAR/EAR double-encode note elsewhere in this file.
                    'clauses' => $this->marineOnceOffCoverClauses[$policyCoverageId] ?? [],
                ]
            );

            MarineOnceOffCoverModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id,
                ],
                $marinePayload
            );

            // Persist note
            if (isset($this->policyCoverageNote[$policyCoverageId])) {
                \AlphaDirect\Models\PolicyCoverageNote::updateOrCreate(
                    ['policy_coverage_id' => $policyCoverageId],
                    ['note' => $this->policyCoverageNote[$policyCoverageId]]
                );
            }
        }

        // Save Marine Open Cover
        if (
            $coverageType === 'MARINEOPENCOVER' && strtoupper($coverageCode) === 'MARINEOPENCOVER' &&
            (isset($this->marineOpenCover[$policyCoverageId]) ||
                isset($this->marineOpenCoverClauses[$policyCoverageId]))
        ) {
            $openData = $this->marineOpenCover[$policyCoverageId] ?? [];

            // Format date fields for DB
            $openData = $this->formatCoverageDatesForDb($openData, $this->marineOpenCoverDateFields);

            // Clean numeric fields
            $numericFields = [
                'annual_estimated_turnover',
                'location_limit',
                'premium_rate',
                'sum_insured',
                'premium',
                'per_conveyance_rail',
                'per_conveyance_road',
                'per_conveyance_air',
                'per_conveyance_post',
                'per_conveyance_vessel',
            ];
            $openData = $this->cleanNumericFieldsForDb($openData, $numericFields);

            // Handle policy wording file upload (local public disk)
            if ($this->marineOpenCoverPolicyWording) {
                $originalName = $this->marineOpenCoverPolicyWording->getClientOriginalName();
                $path = $this->marineOpenCoverPolicyWording->storeAs(
                    'policy-wordings/marine-open-cover',
                    time() . '_' . $originalName,
                    'public'
                );
                $openData['policy_wording_path'] = $path;
                $openData['policy_wording_filename'] = $originalName;
                $this->marineOpenCover[$policyCoverageId]['policy_wording_path'] = $path;
                $this->marineOpenCover[$policyCoverageId]['policy_wording_filename'] = $originalName;
                $this->marineOpenCoverPolicyWording = null;
            }

            $miscItems = $this->marineOpenCoverMiscItems[$policyCoverageId] ?? [];
            $miscItems = array_values(array_filter($miscItems, function ($item) {
                return !empty($item['description']) || !empty($item['sum_insured']) || !empty($item['premium']);
            }));
            foreach ($miscItems as &$miscItem) {
                if (isset($miscItem['sum_insured'])) {
                    $miscItem['sum_insured'] = (float) str_replace(',', '', $miscItem['sum_insured']);
                }
                if (isset($miscItem['premium'])) {
                    $miscItem['premium'] = (float) str_replace(',', '', $miscItem['premium']);
                }
            }
            unset($miscItem);

            $openPayload = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null,
                    'action_id' => $this->actionId,
                    'term_id' => $this->termId,
                ],
                $openData,
                [
                    // MarineCargoOpenCoverage casts 'clauses' as 'array' — see the
                    // PAR/CAR/EAR double-encode note elsewhere in this file.
                    'clauses' => $this->marineOpenCoverClauses[$policyCoverageId] ?? [],
                    'misc_items' => json_encode($miscItems, JSON_UNESCAPED_UNICODE),
                ]
            );

            MarineOpenCoverModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id,
                ],
                $openPayload
            );

            // Persist note
            if (isset($this->policyCoverageNote[$policyCoverageId])) {
                \AlphaDirect\Models\PolicyCoverageNote::updateOrCreate(
                    ['policy_coverage_id' => $policyCoverageId],
                    ['note' => $this->policyCoverageNote[$policyCoverageId]]
                );
            }
        }

        // Save Marine Directors & Officers Coverage
        if (
            $coverageType === 'MARINEDIRECTORSOFFICERS' && strtoupper($coverageCode) === 'MARINEDIRECTORSOFFICERS' &&
            (isset($this->marineDirectorsOfficers[$policyCoverageId]) ||
                isset($this->marineDirectorsOfficersSection1Items[$policyCoverageId]) ||
                isset($this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId]))
        ) {
            $mdoData = $this->marineDirectorsOfficers[$policyCoverageId] ?? [];

            $mdoData = $this->formatCoverageDatesForDb($mdoData, $this->marineDirectorsOfficersDateFields);

            $numericFields = [
                'limit_of_liability',
                'sum_insured',
                'premium',
            ];
            $mdoData = $this->cleanNumericFieldsForDb($mdoData, $numericFields);

            // Handle policy wording file upload
            if ($this->marineDirectorsOfficersPolicyWording) {
                $originalName = $this->marineDirectorsOfficersPolicyWording->getClientOriginalName();
                $path = $this->marineDirectorsOfficersPolicyWording->storeAs(
                    'policy-wordings/marine-directors-officers',
                    time() . '_' . $originalName,
                    'public'
                );
                $mdoData['policy_wording_path'] = $path;
                $mdoData['policy_wording_filename'] = $originalName;
                $this->marineDirectorsOfficers[$policyCoverageId]['policy_wording_path'] = $path;
                $this->marineDirectorsOfficers[$policyCoverageId]['policy_wording_filename'] = $originalName;
                $this->marineDirectorsOfficersPolicyWording = null;
            }

            $miscItems = $this->marineDirectorsOfficersMiscItems[$policyCoverageId] ?? [];
            $miscItems = array_values(array_filter($miscItems, function ($item) {
                return !empty($item['description']) || !empty($item['sum_insured']) || !empty($item['premium']);
            }));
            foreach ($miscItems as &$miscItem) {
                if (isset($miscItem['sum_insured'])) {
                    $miscItem['sum_insured'] = (float) str_replace(',', '', $miscItem['sum_insured']);
                }
                if (isset($miscItem['premium'])) {
                    $miscItem['premium'] = (float) str_replace(',', '', $miscItem['premium']);
                }
            }
            unset($miscItem);

            $mdoPayload = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null,
                    'action_id' => $this->actionId,
                    'term_id' => $this->termId,
                ],
                $mdoData,
                [
                    // MarineDirectorsOfficersCoverage casts these as 'array' — see the
                    // PAR/CAR/EAR double-encode note elsewhere in this file.
                    'section1_items' => array_values($this->marineDirectorsOfficersSection1Items[$policyCoverageId] ?? []),
                    'insured_persons_listing' => array_values($this->marineDirectorsOfficersInsuredPersonsListing[$policyCoverageId] ?? []),
                    'extra_cover_section1' => array_values($this->marineDirectorsOfficersExtraCoverSection1[$policyCoverageId] ?? []),
                    'section2_items' => array_values($this->marineDirectorsOfficersSection2Items[$policyCoverageId] ?? []),
                    'extra_cover_section2' => array_values($this->marineDirectorsOfficersExtraCoverSection2[$policyCoverageId] ?? []),
                    'section3_items' => array_values($this->marineDirectorsOfficersSection3Items[$policyCoverageId] ?? []),
                    'extra_cover_section3' => array_values($this->marineDirectorsOfficersExtraCoverSection3[$policyCoverageId] ?? []),
                    'extra_cover_all_sections' => array_values($this->marineDirectorsOfficersExtraCoverAllSections[$policyCoverageId] ?? []),
                    'excess_details' => array_values($this->marineDirectorsOfficersExcessDetails[$policyCoverageId] ?? []),
                    'misc_items' => $miscItems,
                    'endorsements' => $this->marineDirectorsOfficersEndorsements[$policyCoverageId] ?? null,
                ]
            );

            MarineDirectorsOfficersModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id,
                ],
                $mdoPayload
            );

            // Persist note (uses notes column on the same row already; skip separate PolicyCoverageNote)
            if (isset($this->policyCoverageNote[$policyCoverageId])) {
                \AlphaDirect\Models\PolicyCoverageNote::updateOrCreate(
                    ['policy_coverage_id' => $policyCoverageId],
                    ['note' => $this->policyCoverageNote[$policyCoverageId]]
                );
            }
        }

        // Save Travel Coverage
        if (
            $coverageType === 'TRAVEL' && in_array($coverageCode, ['TRAVEL', 'TRAVELINSURANCE']) &&
            (isset($this->travelCoverage[$policyCoverageId]) || isset($this->travelBenefits[$policyCoverageId]))
        ) {
            $travelCoverageData = $this->travelCoverage[$policyCoverageId] ?? [];
            
            // Calculate total (policy_amount + vat)
            if (isset($travelCoverageData['policy_amount']) && isset($travelCoverageData['vat'])) {
                $policyAmount = (float) str_replace(',', '', $travelCoverageData['policy_amount'] ?? '0');
                $vat = (float) str_replace(',', '', $travelCoverageData['vat'] ?? '0');
                $travelCoverageData['total'] = number_format($policyAmount + $vat, 2, '.', '');
            }
            
            // Format date fields before saving
            $travelCoverageData = $this->formatCoverageDatesForDb($travelCoverageData, $this->travelCoverageDateFields);
            
            // Clean numeric fields
            $numericFields = [
                'policy_amount',
                'vat',
                'total',
            ];
            $travelCoverageData = $this->cleanNumericFieldsForDb($travelCoverageData, $numericFields);

            // Handle policy wording file upload
            // if ($this->travelPolicyWording) {
            //     $wordingPath = $this->handlePolicyWordingUpload($policyCoverageId, 'TRAVEL');
            //     if ($wordingPath) {
            //         $travelCoverageData['policy_wording_path'] = $wordingPath;
            //     }
            // }
            if ($this->travelPolicyWording) {                
                $originalName = $this->travelPolicyWording->getClientOriginalName();
                $path = $this->travelPolicyWording->storeAs('policy-wordings/travel', time().'_'.$this->travelPolicyWording->getClientOriginalName(),
                    'public'
                );
                $travelCoverageData['policy_wording_path'] = $path;                
                $travelCoverageData['policy_wording_filename'] = basename($path);;
            }

            $travelData = array_merge(
                [
                    'policy_id' => $this->policy->id,
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? null
                ],
                $travelCoverageData,
                [
                    // See array-cast double-encode note above.
                    'benefits' => $this->travelBenefits[$policyCoverageId] ?? [],
                    'custom_benefits' => $this->travelCustomBenefits[$policyCoverageId] ?? [],
                ]
            );

            TravelCoverageModel::updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverageId,
                    'policy_id' => $this->policy->id
                ],
                $travelData
            );
        }

        // Update policy_action table updated_at field
        if ($this->actionId) {
            PolicyAction::where('id', $this->actionId)
                ->update(['updated_at' => now()]);
        }
    }

    // Add miscellaneous item functions
    public function addParMiscellaneousItem($policyCoverageId)
    {
        if (!isset($this->parInsuredItems[$policyCoverageId])) {
            $this->parInsuredItems[$policyCoverageId] = [];
        }
        $this->parInsuredItems[$policyCoverageId][] = [
            'description' => 'Miscellaneous',
            'item_no' => count($this->parInsuredItems[$policyCoverageId]) + 1
        ];
    }

    public function addParSection2MiscItem($policyCoverageId)
    {
        if (!isset($this->parSection2Items[$policyCoverageId])) {
            $this->parSection2Items[$policyCoverageId] = [];
        }
        $this->parSection2Items[$policyCoverageId][] = [
            'item_type' => 'Other',
            'description' => 'Miscellaneous Item'
        ];
    }

    public function addEarMiscellaneousItem($policyCoverageId)
    {
        if (!isset($this->earSection1Items[$policyCoverageId])) {
            $this->earSection1Items[$policyCoverageId] = [];
        }
        $this->earSection1Items[$policyCoverageId][] = [
            'item_type' => 'Other',
            'description' => 'Miscellaneous Item'
        ];
    }

    public function addMarineOpenCoverMiscItem($policyCoverageId)
    {
        if (!isset($this->marineOpenCoverMiscItems[$policyCoverageId])) {
            $this->marineOpenCoverMiscItems[$policyCoverageId] = [];
        }
        $this->marineOpenCoverMiscItems[$policyCoverageId][] = [
            'description' => '',
            'sum_insured' => '',
            'premium' => '',
        ];
    }

    public function removeMarineOpenCoverMiscItem($policyCoverageId, $index)
    {
        if (isset($this->marineOpenCoverMiscItems[$policyCoverageId][$index])) {
            unset($this->marineOpenCoverMiscItems[$policyCoverageId][$index]);
            $this->marineOpenCoverMiscItems[$policyCoverageId] = array_values($this->marineOpenCoverMiscItems[$policyCoverageId]);
        }
    }

    public function calculateMarineOpenCoverPremium($policyCoverageId)
    {
        $rate = (float) str_replace(',', '', $this->marineOpenCover[$policyCoverageId]['premium_rate'] ?? 0);
        $sumInsured = (float) str_replace(',', '', $this->marineOpenCover[$policyCoverageId]['sum_insured'] ?? 0);
        $premium = ($sumInsured * $rate) / 100;
        $this->marineOpenCover[$policyCoverageId]['premium'] = number_format($premium, 2, '.', ',');
    }

    // Calculate total sum insured for CAR Section 1
    public function calculateCarSection1TotalSumInsured($policyCoverageId)
    {
        $total = 0;
        if (isset($this->carSection1Items[$policyCoverageId])) {
            foreach ($this->carSection1Items[$policyCoverageId] as $item) {
                if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
                    $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                    $total += $sumInsured;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for CAR Section 1
    public function calculateCarSection1TotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->carSection1Items[$policyCoverageId])) {
            foreach ($this->carSection1Items[$policyCoverageId] as $item) {
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total limit of indemnity for CAR Section 2
    public function calculateCarSection2TotalLimit($policyCoverageId)
    {
        $total = 0;
        if (isset($this->carSection2Items[$policyCoverageId])) {
            foreach ($this->carSection2Items[$policyCoverageId] as $item) {
                if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
                    $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                    $total += $limit;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for CAR Section 2
    public function calculateCarSection2TotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->carSection2Items[$policyCoverageId])) {
            foreach ($this->carSection2Items[$policyCoverageId] as $item) {
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total annual sum insured for CAR Section 3
    public function calculateCarSection3TotalAnnualSum($policyCoverageId)
    {
        $total = 0;
        
        // Check for Gross Profit fields
        if (isset($this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured']) && 
            !empty($this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_gross_profit_annual_sum_insured']);
            $total += $sumInsured;
        }
        
        // Check for Increased Cost of Working fields
        if (isset($this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured']) && 
            !empty($this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_increased_cost_sum_insured']);
            $total += $sumInsured;
        }
        
        // Legacy support: Check for old direct field format
        if (isset($this->carCoverage[$policyCoverageId]['section3_annual_sum_insured']) && 
            !empty($this->carCoverage[$policyCoverageId]['section3_annual_sum_insured'])) {
            $sumInsured = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_annual_sum_insured']);
            $total += $sumInsured;
        }
        
        // Also check for legacy array format (for backward compatibility)
        if (isset($this->carSection3Items[$policyCoverageId])) {
            foreach ($this->carSection3Items[$policyCoverageId] as $item) {
                if (isset($item['annual_sum_insured']) && !empty($item['annual_sum_insured'])) {
                    $sumInsured = (float) str_replace(',', '', $item['annual_sum_insured']);
                    $total += $sumInsured;
                }
            }
        }
        
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for CAR Section 3
    public function calculateCarSection3TotalPremium($policyCoverageId)
    {
        $total = 0;
        
        // Check for Gross Profit premium
        if (isset($this->carCoverage[$policyCoverageId]['section3_gross_profit_premium']) && 
            !empty($this->carCoverage[$policyCoverageId]['section3_gross_profit_premium'])) {
            $premium = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_gross_profit_premium']);
            $total += $premium;
        }
        
        // Check for Increased Cost of Working premium
        if (isset($this->carCoverage[$policyCoverageId]['section3_increased_cost_premium']) && 
            !empty($this->carCoverage[$policyCoverageId]['section3_increased_cost_premium'])) {
            $premium = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_increased_cost_premium']);
            $total += $premium;
        }
        
        // Legacy support: Check for old direct field format
        if (isset($this->carCoverage[$policyCoverageId]['section3_premium']) && 
            !empty($this->carCoverage[$policyCoverageId]['section3_premium'])) {
            $premium = (float) str_replace(',', '', $this->carCoverage[$policyCoverageId]['section3_premium']);
            $total += $premium;
        }
        
        // Also check for legacy array format (for backward compatibility)
        if (isset($this->carSection3Items[$policyCoverageId])) {
            foreach ($this->carSection3Items[$policyCoverageId] as $item) {
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        
        return number_format($total, 2, '.', ',');
    }

    // Calculate total sum insured for PAR
    public function calculateParTotalSumInsured($policyCoverageId)
    {
        $total = 0;
        if (isset($this->parInsuredItems[$policyCoverageId])) {
            foreach ($this->parInsuredItems[$policyCoverageId] as $item) {
                if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
                    $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                    $total += $sumInsured;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for PAR
    public function calculateParTotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->parInsuredItems[$policyCoverageId])) {
            foreach ($this->parInsuredItems[$policyCoverageId] as $index => $item) {
                // Recalculate premium if sum_insured and rate are set but premium is missing
                if (isset($item['sum_insured']) && !empty($item['sum_insured']) && 
                    isset($item['rate']) && !empty($item['rate']) && 
                    (empty($item['premium']) || $item['premium'] === '')) {
                    $this->calculateParItemPremium($policyCoverageId, $index);
                    $item = $this->parInsuredItems[$policyCoverageId][$index];
                }
                
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    public function calculateParSection2TotalLimit($policyCoverageId)
    {
        $total = 0;
        if (isset($this->parSection2Items[$policyCoverageId])) {
            foreach ($this->parSection2Items[$policyCoverageId] as $item) {
                if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
                    $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                    $total += $limit;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    public function calculateParSection2TotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->parSection2Items[$policyCoverageId])) {
            foreach ($this->parSection2Items[$policyCoverageId] as $index => $item) {
                // Recalculate premium if limit_of_indemnity and rate are set but premium is missing
                if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity']) && 
                    isset($item['rate']) && !empty($item['rate']) && 
                    (empty($item['premium']) || $item['premium'] === '')) {
                    $this->calculateParSection2ItemPremium($policyCoverageId, $index);
                    $item = $this->parSection2Items[$policyCoverageId][$index];
                }
                
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total sum insured for EAR Section 1
    public function calculateEarSection1TotalSumInsured($policyCoverageId)
    {
        $total = 0;
        if (isset($this->earSection1Items[$policyCoverageId])) {
            foreach ($this->earSection1Items[$policyCoverageId] as $item) {
                if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
                    $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                    $total += $sumInsured;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for EAR Section 1
    public function calculateEarSection1TotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->earSection1Items[$policyCoverageId])) {
            foreach ($this->earSection1Items[$policyCoverageId] as $index => $item) {
                // Recalculate premium if sum_insured and rate are set but premium is missing
                if (isset($item['sum_insured']) && !empty($item['sum_insured']) && 
                    isset($item['rate']) && !empty($item['rate']) && 
                    (empty($item['premium']) || $item['premium'] === '')) {
                    $this->calculateEarSection1ItemPremium($policyCoverageId, $index);
                    $item = $this->earSection1Items[$policyCoverageId][$index];
                }
                
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total limit of indemnity for EAR Section 3
    public function calculateEarSection3TotalLimit($policyCoverageId)
    {
        $total = 0;
        if (isset($this->earSection3Items[$policyCoverageId])) {
            foreach ($this->earSection3Items[$policyCoverageId] as $item) {
                if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity'])) {
                    $limit = (float) str_replace(',', '', $item['limit_of_indemnity']);
                    $total += $limit;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for EAR Section 3
    public function calculateEarSection3TotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->earSection3Items[$policyCoverageId])) {
            foreach ($this->earSection3Items[$policyCoverageId] as $index => $item) {
                // Recalculate premium if limit_of_indemnity and rate are set but premium is missing
                if (isset($item['limit_of_indemnity']) && !empty($item['limit_of_indemnity']) && 
                    isset($item['rate']) && !empty($item['rate']) && 
                    (empty($item['premium']) || $item['premium'] === '')) {
                    $this->calculateEarSection3ItemPremium($policyCoverageId, $index);
                    $item = $this->earSection3Items[$policyCoverageId][$index];
                }
                
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total sum insured for CAR Plant List
    public function calculateCarPlantListTotalSumInsured($policyCoverageId)
    {
        $total = 0;
        if (isset($this->carPlantListItems[$policyCoverageId])) {
            foreach ($this->carPlantListItems[$policyCoverageId] as $item) {
                if (isset($item['sum_insured']) && !empty($item['sum_insured'])) {
                    $sumInsured = (float) str_replace(',', '', $item['sum_insured']);
                    $total += $sumInsured;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for CAR Plant List
    public function calculateCarPlantListTotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->carPlantListItems[$policyCoverageId])) {
            foreach ($this->carPlantListItems[$policyCoverageId] as $item) {
                if (isset($item['premium']) && !empty($item['premium'])) {
                    $premium = (float) str_replace(',', '', $item['premium']);
                    $total += $premium;
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for EAR (Section 1 + Section 2 + Section 3)
    public function calculateEarTotalPremium($policyCoverageId)
    {
        $total = 0;
        
        // Add Section 1 total premium
        $section1Total = $this->calculateEarSection1TotalPremium($policyCoverageId);
        $total += (float) str_replace(',', '', $section1Total);
        
        // Add Section 2 risk premiums
        if (isset($this->earCoverage[$policyCoverageId]['risk_earthquake_premium']) && 
            !empty($this->earCoverage[$policyCoverageId]['risk_earthquake_premium'])) {
            $premium = (float) str_replace(',', '', $this->earCoverage[$policyCoverageId]['risk_earthquake_premium']);
            $total += $premium;
        }
        
        if (isset($this->earCoverage[$policyCoverageId]['risk_storm_premium']) && 
            !empty($this->earCoverage[$policyCoverageId]['risk_storm_premium'])) {
            $premium = (float) str_replace(',', '', $this->earCoverage[$policyCoverageId]['risk_storm_premium']);
            $total += $premium;
        }
        
        // Add Section 3 total premium
        $section3Total = $this->calculateEarSection3TotalPremium($policyCoverageId);
        $total += (float) str_replace(',', '', $section3Total);
        
        // Update the total_premium in earCoverage array
        if (!isset($this->earCoverage[$policyCoverageId])) {
            $this->earCoverage[$policyCoverageId] = [];
        }
        $this->earCoverage[$policyCoverageId]['total_premium'] = number_format($total, 2, '.', ',');
        
        return number_format($total, 2, '.', ',');
    }

    // Helper function to clean and parse numeric values
    // Removes commas and converts to float, returns 0 if empty or invalid
    // Calculate total sum insured for generic subcoverages (handles comma-separated numbers)
    public function getSubCoverageTotalSumInsured($policyCoverageId)
    {
        $total = 0;
        if (isset($this->policyCoverageDetail[$policyCoverageId])) {
            foreach ($this->policyCoverageDetail[$policyCoverageId] as $subCoverageData) {
                // Try multiple possible field names for sum insured
                $sumInsuredValue = $subCoverageData['coverage_value'] ??
                                  $subCoverageData['ratefactor_value'] ??
                                  $subCoverageData['sum_insured'] ?? null;

                if ($sumInsuredValue !== null && !empty($sumInsuredValue)) {
                    $total += $this->parseNumericValue($sumInsuredValue);
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Calculate total premium for generic subcoverages (handles comma-separated numbers)
    public function getSubCoverageTotalPremium($policyCoverageId)
    {
        $total = 0;
        if (isset($this->policyCoverageDetail[$policyCoverageId])) {
            foreach ($this->policyCoverageDetail[$policyCoverageId] as $subCoverageData) {
                // Try multiple possible field names for premium
                $premiumValue = $subCoverageData['calculated_value'] ??
                               $subCoverageData['premium'] ?? null;

                if ($premiumValue !== null && !empty($premiumValue)) {
                    $total += $this->parseNumericValue($premiumValue);
                }
            }
        }
        return number_format($total, 2, '.', ',');
    }

    // Get count of filled subcoverages
    public function getSubCoverageFilledCount($policyCoverageId)
    {
        $count = 0;
        if (isset($this->policyCoverageDetail[$policyCoverageId])) {
            foreach ($this->policyCoverageDetail[$policyCoverageId] as $subCoverageData) {
                if ((isset($subCoverageData['coverage_value']) && !empty($subCoverageData['coverage_value'])) ||
                    (isset($subCoverageData['calculated_value']) && !empty($subCoverageData['calculated_value']))) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Get customer name following the same logic as policy index page
     * Returns company name for Organisation, or firstName + middleName + lastName for individuals
     */
    /**
     * Organisation-only Insured Name.
     *
     * Mirrors the frontend SpecialistCoveragePage gate: the EAR/CAR/MB
     * Insured-Name fields autofill ONLY for Organisation customers
     * (entity_type === 'Organisation' with a company name on file).
     * Individual policies return '' so the form is never seeded with the
     * policyholder's personal name — an operator types the trading/project
     * name explicitly. Use this (instead of getCustomerName()) wherever the
     * Insured-Name field is auto-populated.
     */
    private function getOrganisationInsuredName()
    {
        if (!$this->policy || empty($this->policy->profile)) {
            return '';
        }

        if ($this->policy->profile->entity_type !== "Organisation") {
            return '';
        }

        return trim((string) ($this->policy->profile->company?->name ?? ''));
    }

    private function getCustomerName()
    {
        if (!$this->policy) {
            return '';
        }

        // Organisation customers prefer the company name. Fall through to the
        // customer firstName/lastName fallback below when company name is
        // missing — otherwise an Organisation with a null company->name leaves
        // Name of insured blank on the CAR coverage form.
        if (!empty($this->policy->profile) && $this->policy->profile->entity_type == "Organisation") {
            $companyName = trim((string) ($this->policy->profile->company?->name ?? ''));
            if ($companyName !== '') {
                return $companyName;
            }
        }

        // Individual customers (and Organisations missing a company name).
        if (!empty($this->policy->customer)) {
            $customer = $this->policy->customer;
            $name = trim(($customer->firstName ?? '') . ' ' . ($customer->middleName ?? '') . ' ' . ($customer->lastName ?? ''));
            return $name;
        }

        return '';
    }

    /**
     * Get customer address from profile
     * Returns address from customer profile
     */
    private function getCustomerAddress()
    {
        if (!$this->policy || !$this->policy->customer) {
            return '';
        }

        // Get address from customer profile
        if (!empty($this->policy->customer->profile) && !empty($this->policy->customer->profile->address)) {
            return $this->policy->customer->profile->address;
        }

        return '';
    }

    /**
     * Get customer postal code and city
     * Returns city name from customer profile
     */
    private function getCustomerPostalCode()
    {
        if (!$this->policy || !$this->policy->customer) {
            return '';
        }

        // Get city name from customer profile
        $profile = $this->policy->customer->profile ?? null;
        if ($profile && !empty($profile->city)) {
            // Get city name from the relationship
            $cityName = $profile->cities?->name ?? '';
            return $cityName;
        }

        return '';
    }
    public function parseNumericValue($value)
    {
        if ($value === null || $value === false || $value === '') return 0;

        if (is_numeric($value) && !is_string($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '-') return 0;

        $value = str_replace(',', '', $value);
        $value = str_replace(['P','p','$','€','£',' ','R'], '', $value);

        $cleaned = preg_replace('/[^0-9.-]/', '', $value);

        if (empty($cleaned) || in_array($cleaned, ['-', '.', '-.'])) return 0;

        $result = (float) $cleaned;

        if (is_nan($result) || is_infinite($result)) return 0;

        return $result;
    }

}
