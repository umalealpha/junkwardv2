<?php

namespace AlphaDirect\Imports\Sheets;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Models\PolicyCoverageEntity;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\Motor;
use AlphaDirect\Models\MotorTraders;
use AlphaDirect\Models\MotorTradersInternal;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Vehicle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Events\BeforeImport;
use  DB;

class CoverageImport implements WithEvents, WithHeadingRow, OnEachRow
{
    public $policy;
    public $termId;
    public $actionId;

    public $sheetName;
    public $mainCoverage;
    public $specifiedCoverages = [];
    public $vehicles = [];
    public $member = [];
    public $device = [];

    public $subCoverageCodeAndId;
    public $riskAddressAndId;

    public $selectedRiskAddress = null;
    public $selectedPolicyCoverages = null; // Allow null to handle empty sheets
    // Cache for PolicyCoverage by risk address and coverage combination
    public $policyCoverageCache = [];
    // Track occurrence count per sub-coverage combination (s_ScreenName + s_ParentCoverageCode)
    public $subCoverageOccurrenceCount = [];
    // Track row index per policyCoverageID for FIDELITYGUARANTEE
    public $fidelityRowIndex = [];
    // Track row index per policyCoverageID for COMMERCIALMOTOR
    public $motorRowIndex = [];
    // Track row index per policyCoverageID for MOTORTRADERSEXTERNAL
    public $motorTradersRowIndex = [];
    // Track row index per policyCoverageID for MOTORTRADERSINTERNAL
    public $motorTradersInternalRowIndex = [];
    // Track row index per policyCoverageID and coverage_id for regular coverage details
    public $coverageDetailRowIndex = [];

    public function __construct($policy, $termId, $actionId, $policyCoverageCode, $policyCoverages)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;

        $this->subCoverageCodeAndId = CoverageMaster::select('id', 's_ScreenName')
            ->OnlyChiled()
            ->get()
            ->pluck('id', 's_ScreenName');

        $this->riskAddressAndId = RiskAddress::select('id', 'address_name')
            ->Policy($this->policy->id)
            ->Term($this->termId)
            ->Action($this->actionId)
            ->get()
            ->mapWithKeys(function ($item) {
                return [trim($item->address_name) => $item->id];
            })
            ->toArray();
    }

    /**
     * Runs for each Excel row
     */
    public function onRow(Row $row)
    {
        // Skip if mainCoverage is not set (empty sheet was detected)
        if (!isset($this->mainCoverage) || !$this->mainCoverage) {
            return;
        }                    
        
        $rowData = $row->toArray();

        // Debug (keep while testing)
        \Log::info('Coverage Import Row', $rowData);

        // Skip empty rows
        if (empty(array_filter($rowData))) {
            return;
        }

        /** ---------------- RISK ADDRESS ---------------- */
        // Check for risk address in multiple possible column name formats
        $riskAddressValue = $rowData['risk_address'] ?? $rowData['Risk Address'] ?? $rowData['risk address'] ?? '';
        $riskAddressValue = trim($riskAddressValue); // Trim risk address value
        
        if (!empty($riskAddressValue)) {
            if (!isset($this->riskAddressAndId[$riskAddressValue])) {
                $errorMessage = "Sheet: {$this->sheetName} - risk address '{$riskAddressValue}' does not exist for this policy";
                \Log::error($errorMessage, [
                    'sheet' => $this->sheetName,
                    'risk_address' => $riskAddressValue
                ]);
                throw new \Exception($errorMessage);
            }

            $this->selectedRiskAddress = $this->riskAddressAndId[$riskAddressValue];
            
            // Get or create PolicyCoverage for this combination (policy_id, term_id, action_id, risk_address_id, coverage_id)
            $this->selectedPolicyCoverages = $this->getPolicyCoverage();
            // dd(  $this->selectedPolicyCoverages);
            // Restore if soft-deleted
            if ($this->selectedPolicyCoverages->trashed()) {
                $this->selectedPolicyCoverages->restore();
            }
        } elseif (!isset($this->selectedPolicyCoverages) || !$this->selectedPolicyCoverages) {
            // If no risk address in this row and selectedPolicyCoverages is not set,
            // try to use the first available risk address
            if (!empty($this->riskAddressAndId)) {
                // Get the first risk address name (key) and its ID (value)
                // Convert to array if it's a Collection
                $riskAddressArray = is_array($this->riskAddressAndId) ? $this->riskAddressAndId : $this->riskAddressAndId->toArray();
                $firstRiskAddressName = array_key_first($riskAddressArray);
                $this->selectedRiskAddress = $riskAddressArray[$firstRiskAddressName];
                $this->selectedPolicyCoverages = $this->getPolicyCoverage();
                if ($this->selectedPolicyCoverages->trashed()) {
                    $this->selectedPolicyCoverages->restore();
                }
            } else {
                \Log::warning('No risk address available and selectedPolicyCoverages not set', [
                    'sheet' => $this->sheetName,
                    'row' => $rowData
                ]);
                return; // Cannot proceed without risk address
            }
        }
// dd($this->sheetName);
        /** ---------------- SUB COVERAGE ---------------- */
        // Check if this is FIDELITYGUARANTEE coverage - use different table with dynamic fields
        if (strtoupper($this->sheetName) === 'FIDELITYGUARANTEE') {
            // For FIDELITYGUARANTEE, save to policy_coverages_data table
            // Check if we have required data (cover_type or other fields)
            if (!empty($rowData['cover_type']) || !empty($rowData['amount_to_be_guaranteed']) || !empty($rowData['premium'])) {
                // Ensure selectedPolicyCoverages is set
                if (!isset($this->selectedPolicyCoverages) || !$this->selectedPolicyCoverages) {
                    \Log::error('selectedPolicyCoverages not set for FIDELITYGUARANTEE', ['row' => $rowData]);
                    return;
                }
                // Track row index for this policyCoverageID to identify which record to update
                $policyCoverageId = $this->selectedPolicyCoverages->id;
                if (!isset($this->fidelityRowIndex[$policyCoverageId])) {
                    $this->fidelityRowIndex[$policyCoverageId] = 0;
                } else {
                    $this->fidelityRowIndex[$policyCoverageId]++;
                }
                $rowIndex = $this->fidelityRowIndex[$policyCoverageId];
                
                $this->saveFidelityGuaranteeData($rowData, $rowIndex);
            }
        } elseif (strtoupper($this->sheetName) === 'COMMERCIALMOTOR' || strtoupper($this->sheetName) === 'COMMERCIAL MOTOR') {
            // For COMMERCIALMOTOR, only registration number is required
            // All other vehicle details will be fetched from Vehicle table
            $registrationNo = $rowData['registration_no'] ?? $rowData['Registration No'] ?? 
                             $rowData['registration no'] ?? $rowData['vehicle_plate'] ?? 
                             $rowData['Vehicle Plate'] ?? $rowData['vehiclePlate'] ?? '';
            
            if (empty($registrationNo)) {
                \Log::warning('Registration number is required for COMMERCIALMOTOR import', [
                    'sheet' => $this->sheetName,
                    'row' => $rowData
                ]);
                return; // Skip row if no registration number
            }
            
            // Ensure selectedPolicyCoverages is set
            if (!isset($this->selectedPolicyCoverages) || !$this->selectedPolicyCoverages) {
                \Log::error('selectedPolicyCoverages not set for COMMERCIALMOTOR', ['row' => $rowData]);
                return;
            }
            
            // Track row index for this policyCoverageID to identify which record to update
            $policyCoverageId = $this->selectedPolicyCoverages->id;
            if (!isset($this->motorRowIndex[$policyCoverageId])) {
                $this->motorRowIndex[$policyCoverageId] = 0;
            } else {
                $this->motorRowIndex[$policyCoverageId]++;
            }
            $rowIndex = $this->motorRowIndex[$policyCoverageId];
            
            $this->saveMotorData($rowData, $rowIndex);
            // Return early to avoid processing as regular coverage
            return;
        } elseif (strtoupper($this->sheetName) === 'MOTORTRADERSEXTERNAL' || strtoupper($this->sheetName) === 'MOTOR TRADERS EXTERNAL') {
            // For MOTORTRADERSEXTERNAL, save to motor_traders table
            // Ensure selectedPolicyCoverages is set
            if (!isset($this->selectedPolicyCoverages) || !$this->selectedPolicyCoverages) {
                \Log::error('selectedPolicyCoverages not set for MOTORTRADERSEXTERNAL', ['row' => $rowData]);
                return;
            }
            
            // Track row index for this policyCoverageID to identify which record to update
            $policyCoverageId = $this->selectedPolicyCoverages->id;
            if (!isset($this->motorTradersRowIndex[$policyCoverageId])) {
                $this->motorTradersRowIndex[$policyCoverageId] = 0;
            } else {
                $this->motorTradersRowIndex[$policyCoverageId]++;
            }
            $rowIndex = $this->motorTradersRowIndex[$policyCoverageId];
            
            $this->saveMotorTradersData($rowData, $rowIndex);
            // Return early to avoid processing as regular coverage
            return;
        } elseif (strtoupper($this->sheetName) === 'MOTORTRADERSINTERNAL' || strtoupper($this->sheetName) === 'MOTOR TRADERS INTERNAL') {
            // For MOTORTRADERSINTERNAL, save to motor_traders_internal table
            // Ensure selectedPolicyCoverages is set
            if (!isset($this->selectedPolicyCoverages) || !$this->selectedPolicyCoverages) {
                \Log::error('selectedPolicyCoverages not set for MOTORTRADERSINTERNAL', ['row' => $rowData]);
                return;
            }
            
            // Track row index for this policyCoverageID to identify which record to update
            $policyCoverageId = $this->selectedPolicyCoverages->id;
            if (!isset($this->motorTradersInternalRowIndex[$policyCoverageId])) {
                $this->motorTradersInternalRowIndex[$policyCoverageId] = 0;
            } else {
                $this->motorTradersInternalRowIndex[$policyCoverageId]++;
            }
            $rowIndex = $this->motorTradersInternalRowIndex[$policyCoverageId];
            
            $this->saveMotorTradersInternalData($rowData, $rowIndex);
            // Return early to avoid processing as regular coverage
            return;
        } elseif (!empty($rowData['coverage']) || !empty($rowData['Coverage']) || !empty($rowData['Sub Coverage']) || !empty($rowData['sub_coverage'])) {
            // For other coverages, use sub-coverage logic
            // Ensure selectedPolicyCoverages is set
            if (!isset($this->selectedPolicyCoverages) || !$this->selectedPolicyCoverages) {
                \Log::error('selectedPolicyCoverages not set for coverage detail', ['row' => $rowData]);
                return;
            }
            
            // Get coverage value from multiple possible column name formats
            $coverageValue = trim($rowData['coverage'] ?? $rowData['Coverage'] ?? $rowData['Sub Coverage'] ?? $rowData['sub_coverage'] ?? '');
            
            if (empty($coverageValue)) {
                return;
            }
            
            // Track occurrence count per risk address for this sub-coverage combination
            // This allows each risk address to independently use coverage records starting from the 1st one
            $subCoverageKey = $coverageValue . '_' . $this->sheetName . '_' . $this->selectedRiskAddress;
            if (!isset($this->subCoverageOccurrenceCount[$subCoverageKey])) {
                $this->subCoverageOccurrenceCount[$subCoverageKey] = 0;
            } else {
                $this->subCoverageOccurrenceCount[$subCoverageKey]++;
            }
            $occurrenceIndex = $this->subCoverageOccurrenceCount[$subCoverageKey];
            
            // Get or create the nth sub-coverage for this combination
            // Note: We always start from index 0 for each risk address, so same coverage records can be reused
            $subCoverage = $this->getOrCreateNthSubCoverage($coverageValue, $occurrenceIndex);
            
            if (!$subCoverage) {
                $errorMessage = "Sheet: {$this->sheetName} - sub-coverage '{$coverageValue}' does not exist for this coverage";
                \Log::error($errorMessage, [
                    'sheet' => $this->sheetName,
                    'sub_coverage' => $coverageValue,
                    'occurrence' => $occurrenceIndex
                ]);
                throw new \Exception($errorMessage);
            }

            // Track row index for this policyCoverageID and coverage_id combination to identify which record to update
            $policyCoverageId = $this->selectedPolicyCoverages->id;
            $coverageId = $subCoverage->id;
            $detailKey = $policyCoverageId . '_' . $coverageId;
            
            if (!isset($this->coverageDetailRowIndex[$detailKey])) {
                $this->coverageDetailRowIndex[$detailKey] = 0;
            } else {
                $this->coverageDetailRowIndex[$detailKey]++;
            }
            $rowIndex = $this->coverageDetailRowIndex[$detailKey];
            
            // Get existing records for this policy_coverage_id and coverage_id, ordered by id
            $existingDetails = PolicyCoverageDetail::withTrashed()
                ->where('policy_coverage_id', $policyCoverageId)
                ->where('coverage_id', $coverageId)
                ->orderBy('id', 'asc')
                ->get();
            
            // Get coverage_value_string value
            $coverageValueString = $rowData['coverage_string'] ?? $rowData['coverage string'] ?? $rowData['Coverage String'] ?? '';
            
            // Get ratefactor_type and ratefactor_value
            $ratefactorType = $rowData['ratefactor_type'] ?? $rowData['ratefactor type'] ?? $rowData['Ratefactor Type'] ?? '';
            $ratefactorValue = $rowData['ratefactor_value'] ?? $rowData['ratefactor value'] ?? $rowData['Ratefactor Value'] ?? '';
            
            // For WORKERSCOMPENSATION, handle ratefactor fields and coverage_value_string
            if (strtoupper($this->sheetName) === 'WORKERSCOMPENSATION' || strtoupper($this->sheetName) === 'WORKERS COMPENSATION') {
                // If coverage_value_string is empty string, set to null
                $coverageValueString = (trim($coverageValueString) === '') ? null : trim($coverageValueString);
                // If ratefactor_type is not entered (empty string or whitespace), set to null
                $ratefactorType = (trim($ratefactorType) === '') ? null : trim($ratefactorType);
                // If ratefactor_value is not entered (empty string, null, or whitespace), set to null
                if (trim($ratefactorValue) === '' || $ratefactorValue === null) {
                    $ratefactorValue = null;
                } else {
                    $ratefactorValue = $this->removeCommasAndConvertToNumeric($ratefactorValue);
                }
            }
            
            // For FIRE coverage, handle ratefactor fields
            if (strtoupper($this->sheetName) === 'FIRE') {
                // If ratefactor_type is not entered (empty string), set to null
                $ratefactorType = ($ratefactorType === '') ? null : $ratefactorType;
                // If ratefactor_value is not entered (empty string or null), set to 0
                if ($ratefactorValue === '' || $ratefactorValue === null) {
                    $ratefactorValue = 0;
                } else {
                    $ratefactorValue = $this->removeCommasAndConvertToNumeric($ratefactorValue);
                }
            }
            
            // For ELECTRONICS EQUIPMENT coverage, handle ratefactor fields and coverage_value_string
            $sheetNameUpper = strtoupper($this->sheetName);
            if ($sheetNameUpper === 'ELECTRONICS EQUIPMENT' || 
                $sheetNameUpper === 'ELECTRONICSEQUIPMENT' || 
                $sheetNameUpper === 'ELECTRONICEQUIPMENT' ||
                strpos($sheetNameUpper, 'ELECTRONIC') !== false && strpos($sheetNameUpper, 'EQUIPMENT') !== false) {
                // If ratefactor_type is not entered (empty string or whitespace), set to null
                $ratefactorType = (trim($ratefactorType) === '') ? null : trim($ratefactorType);
                // If ratefactor_value is not entered (empty string, null, or whitespace), set to null
                if (trim($ratefactorValue) === '' || $ratefactorValue === null) {
                    $ratefactorValue = null;
                } else {
                    $ratefactorValue = $this->removeCommasAndConvertToNumeric($ratefactorValue);
                }
                // If coverage_value_string is not entered (empty string or whitespace), set to null
                $coverageValueString = (trim($coverageValueString) === '') ? null : trim($coverageValueString);
            }
            
            // Validate numeric fields
            $limitValue = $rowData['limit'] ?? $rowData['Limit'] ?? '';
            $premiumValue = $rowData['premium'] ?? $rowData['Premium'] ?? '';
            $this->validateNumericField($limitValue, 'Limit', $this->sheetName);
            $this->validateNumericField($premiumValue, 'Premium', $this->sheetName);

            // If the nth record exists, update it; otherwise create new one
            if ($existingDetails->count() > $rowIndex) {
                // Update existing record at rowIndex
                $existingDetail = $existingDetails[$rowIndex];
                $existingDetail->coverage_value = $this->removeCommasAndConvertToNumeric($limitValue);
                $existingDetail->coverage_value_string = $coverageValueString;
                $existingDetail->ratefactor_type = $ratefactorType;
                $existingDetail->ratefactor_value = $ratefactorValue;
                $existingDetail->ratefactor_AnnualWages = $this->getRatefactorAnnualWagesValue($rowData);
                $existingDetail->ratefactor_deposit_min_pre = $rowData['ratefactor_deposit_min_pre'] ?? $rowData['ratefactor deposit min pre'] ?? $rowData['Ratefactor Deposit Min Pre'] ?? '';
                $existingDetail->discount_surcharge = $rowData['discount_surcharge'] ?? $rowData['discount surcharge'] ?? $rowData['Discount Surcharge'] ?? '';
                $existingDetail->discount_surcharge_type = $rowData['discount_surcharge_type'] ?? $rowData['discount surcharge type'] ?? $rowData['Discount Surcharge Type'] ?? '';
                $existingDetail->discount_surcharge_value = $rowData['discount_surcharge_value'] ?? $rowData['discount surcharge value'] ?? $rowData['Discount Surcharge Value'] ?? '';
                $existingDetail->calculated_value = $this->removeCommasAndConvertToNumeric($premiumValue);
                $existingDetail->deleted_at = null; // Restore if soft-deleted
                $existingDetail->save();
            } else {
                // Create new record
                PolicyCoverageDetail::create([
                    'policy_coverage_id' => $policyCoverageId,
                    'coverage_id' => $coverageId,
                    'coverage_value' => $this->removeCommasAndConvertToNumeric($limitValue),
                    'coverage_value_string' => $coverageValueString,
                    'ratefactor_type' => $ratefactorType,
                    'ratefactor_value' => $ratefactorValue,
                    'ratefactor_AnnualWages' => $this->getRatefactorAnnualWagesValue($rowData),
                    'ratefactor_deposit_min_pre' => $rowData['ratefactor_deposit_min_pre'] ?? $rowData['ratefactor deposit min pre'] ?? $rowData['Ratefactor Deposit Min Pre'] ?? '',
                    'discount_surcharge' => $rowData['discount_surcharge'] ?? $rowData['discount surcharge'] ?? $rowData['Discount Surcharge'] ?? '',
                    'discount_surcharge_type' => $rowData['discount_surcharge_type'] ?? $rowData['discount surcharge type'] ?? $rowData['Discount Surcharge Type'] ?? '',
                    'discount_surcharge_value' => $rowData['discount_surcharge_value'] ?? $rowData['discount surcharge value'] ?? $rowData['Discount Surcharge Value'] ?? '',
                    'calculated_value' => $this->removeCommasAndConvertToNumeric($premiumValue),
                ]);
            }
        }

// dd($rowData,"outer");

        /** ---------------- VEHICLE ---------------- */
        if (!empty($rowData['vehicle'])) {
            PolicyCoverageEntity::withTrashed()->updateOrCreate(
                [
                    'policy_coverage_id' => $this->selectedPolicyCoverages->id,
                    'entity_type' => 'Vehicle',
                ],
                [
                    'entity_id' => array_search($rowData['vehicle'], $this->vehicles),
                    'policy_id' => $this->policy->id,
                    'risk_address_id' => $this->selectedRiskAddress,
                    'term_id' => $this->termId,
                    'action_id' => $this->actionId,
                    'coverage_id' => $this->mainCoverage->id,
                    'deleted_at' => null,
                ]
            );
        }

        /** ---------------- MEMBER ---------------- */
        if (!empty($rowData['member'])) {
            PolicyCoverageEntity::withTrashed()->updateOrCreate(
                [
                    'policy_coverage_id' => $this->selectedPolicyCoverages->id,
                    'entity_type' => 'Member',
                ],
                [
                    'entity_id' => array_search($rowData['member'], $this->member),
                    'policy_id' => $this->policy->id,
                    'risk_address_id' => $this->selectedRiskAddress,
                    'term_id' => $this->termId,
                    'action_id' => $this->actionId,
                    'coverage_id' => $this->mainCoverage->id,
                    'deleted_at' => null,
                ]
            );
        }

        /** ---------------- DEVICE ---------------- */
        if (!empty($rowData['device'])) {
            PolicyCoverageEntity::withTrashed()->updateOrCreate(
                [
                    'policy_coverage_id' => $this->selectedPolicyCoverages->id,
                    'entity_type' => 'Device',
                ],
                [
                    'entity_id' => array_search($rowData['device'], $this->device),
                    'policy_id' => $this->policy->id,
                    'risk_address_id' => $this->selectedRiskAddress,
                    'term_id' => $this->termId,
                    'action_id' => $this->actionId,
                    'coverage_id' => $this->mainCoverage->id,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * Sheet-level setup
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                // Reset state for each new sheet (important: state persists across sheets)
                $this->mainCoverage = null;
                $this->selectedPolicyCoverages = null;
                $this->selectedRiskAddress = null;
                $this->policyCoverageCache = [];
                $this->subCoverageOccurrenceCount = [];
                $this->fidelityRowIndex = [];
                $this->motorRowIndex = [];
                $this->motorTradersRowIndex = [];
                $this->motorTradersInternalRowIndex = [];
                $this->coverageDetailRowIndex = [];
                
                try {
                    $this->sheetName = $event->getSheet()->getDelegate()->getTitle();
                    
                    // Check if sheet has data rows (not just headers)
                    $sheet = $event->getSheet()->getDelegate();
                    $highestRow = $sheet->getHighestRow();
                    
                    // If sheet only has header row (row 1) or is empty, skip processing
                    if ($highestRow <= 1) {
                        \Log::info('Skipping empty sheet (only headers)', [
                            'sheet' => $this->sheetName,
                            'highest_row' => $highestRow
                        ]);
                        // Set mainCoverage to null to indicate empty sheet
                        $this->mainCoverage = null;
                        
                        // CRITICAL: Manually add an empty row 2 to prevent
                        // WithHeadingRow from trying to read beyond the available rows
                        // This prevents the "beyond highest row" exception
                        try {
                            // Force the sheet to have at least 2 rows (header + 1 empty data row)
                            // This prevents WithHeadingRow from throwing an exception
                            $sheet->setCellValue('A2', '');
                            $sheet->getRowDimension(2)->setVisible(false); // Hide the empty row
                        } catch (\Exception $e) {
                            \Log::warning('Could not modify sheet to prevent exception', [
                                'sheet' => $this->sheetName,
                                'error' => $e->getMessage()
                            ]);
                        }
                        
                        return; // Exit early, don't process this sheet
                    }
                } catch (\Exception $e) {
                    // If we can't read the sheet, log and skip
                    \Log::warning('Error checking sheet, skipping', [
                        'sheet' => $this->sheetName ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    // Set mainCoverage to null to indicate empty sheet
                    $this->mainCoverage = null;
                    
                    // Try to prevent exception by adding an empty row
                    try {
                        $sheet = $event->getSheet()->getDelegate();
                        $sheet->setCellValue('A2', '');
                        $sheet->getRowDimension(2)->setVisible(false);
                    } catch (\Exception $e2) {
                        // Ignore if we can't modify the sheet
                    }
                    
                    return;
                }

                // Try to find coverage by s_CoverageCode first, then by s_ScreenName
                $this->mainCoverage = CoverageMaster::where('s_CoverageCode', $this->sheetName)
                    ->orWhere('s_ScreenName', $this->sheetName)
                    ->first();

                if (!$this->mainCoverage) {
                    throw new \Exception("Coverage not found for sheet: {$this->sheetName}");
                }

                $this->specifiedCoverages = $this->mainCoverage
                    ->specifiedCoverages()
                    ->EffectiveItemOnly()
                    ->pluck('id', 'specified_name');

                $this->vehicles = $this->getVehicles();
                $this->device = $this->getDevices();
                $this->member = $this->getBeneficiaries();
            }
        ];
    }

    /** ---------------- HELPERS ---------------- */

    public function getVehicles()
    {
        return Vehicle::select('vehiclePlate', 'id')
            ->PolicyId($this->policy->id)
            ->TermId($this->termId)
            ->ActionId($this->actionId)
            ->get()
            ->pluck('vehiclePlate', 'id')
            ->toArray();
    }

    public function getDevices()
    {
        return PolicyCellPhone::Policy($this->policy->id)
            ->term($this->termId)
            ->action($this->actionId)
            ->get()
            ->pluck('device_type', 'id')
            ->toArray();
    }

    public function getBeneficiaries()
    {
        return PolicyBeneficiary::selectRaw(
            "id, concat(first_name,' ',middle_name,' ',last_name) as name"
        )
            ->Policy($this->policy->id)
            ->term($this->termId)
            ->action($this->actionId)
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get or create PolicyCoverage for the combination of:
     * policy_id, term_id, action_id, risk_address_id, coverage_id
     * If exists, reuse it; otherwise create new one
     */
    public function getPolicyCoverage(){
        // Create cache key for this combination
        $cacheKey = $this->selectedRiskAddress . '_' . $this->mainCoverage->id;
        
        // Check cache first
        if (isset($this->policyCoverageCache[$cacheKey])) {
            return $this->policyCoverageCache[$cacheKey];
        }
        
        // Find existing PolicyCoverage with exact combination
        $policyCoverage = PolicyCoverage::withTrashed()
            ->Policy($this->policy->id)
            ->Term($this->termId)
            ->Action($this->actionId)
            ->Coverage($this->mainCoverage->id)
            ->Risk($this->selectedRiskAddress)
            ->first();
        
        // If not found, create new one
        if (!$policyCoverage){
            $policyCoverage = new PolicyCoverage();
            $policyCoverage->risk_address_id = $this->selectedRiskAddress;
            $policyCoverage->term_id = $this->termId;
            $policyCoverage->action_id = $this->actionId;
            $policyCoverage->policy_id = $this->policy->id;
            $policyCoverage->coverage_id = $this->mainCoverage->id;
            $policyCoverage->save();
        }
        
        // Cache it for reuse
        $this->policyCoverageCache[$cacheKey] = $policyCoverage;
        
        return $policyCoverage;
    }
    
    /**
     * Get or create the nth sub-coverage (0-indexed) for a combination of:
     * s_ScreenName + s_ParentCoverageCode
     * If it doesn't exist, creates it first
     */
    public function getOrCreateNthSubCoverage($screenName, $index = 0){
        // Get parent coverage code (sheet name)
        $parentCoverageCode = $this->sheetName;
        
        // Find all sub-coverages with this combination, ordered by id
        $subCoverages = CoverageMaster::where('s_ScreenName', $screenName)
            ->where('s_ParentCoverageCode', $parentCoverageCode)
            ->orderBy('id', 'asc')
            ->get();
        
        // If the nth sub-coverage exists, return it
        if ($subCoverages->count() > $index) {
            return $subCoverages[$index];
        }
        
        // If it doesn't exist, we need to create it
        // First, try to find a template from existing sub-coverages with same screen name
        $templateCoverage = CoverageMaster::where('s_ScreenName', $screenName)
            ->orderBy('id', 'asc')
            ->first();
        
        // If no template found, check if it exists in the subCoverageCodeAndId mapping
        if (!$templateCoverage && isset($this->subCoverageCodeAndId[$screenName])) {
            $templateCoverage = CoverageMaster::find($this->subCoverageCodeAndId[$screenName]);
        }
        
        // If still no template, try to find any CoverageMaster with same screen name (as fallback)
        // This would be a sub-coverage (CHILD type) that might exist elsewhere
        if (!$templateCoverage) {
            $templateCoverage = CoverageMaster::where('s_ScreenName', $screenName)
                ->where('s_UsageType', 'CHILD')
                ->orderBy('id', 'asc')
                ->first();
        }
        
        // If we have a template, create new coverage based on it
        if ($templateCoverage) {
            $newSubCoverage = new CoverageMaster();
            
            // Copy all attributes from template, excluding system fields
            foreach ($templateCoverage->getAttributes() as $key => $value) {
                if (!in_array($key, ['id', 'created_at', 'updated_at', 'd_CreatedDate', 'd_UpdatedDate'])) {
                    $newSubCoverage->$key = $value;
                }
            }
            
            // Ensure parent coverage code is set correctly
            $newSubCoverage->s_ParentCoverageCode = $parentCoverageCode;
            $newSubCoverage->s_ParentCoverageID = $this->mainCoverage->id ?? null;
            
            // Keep s_ScreenName and s_CoverageName the same (no incremental suffix)
            // The coverage name should remain unchanged
            
            $newSubCoverage->save();
            return $newSubCoverage;
        }
        
        // If no template found at all, return null (will be logged as warning)
        return null;
    }
    
    /**
     * Validate that a value is numeric (with optional commas)
     */
    private function validateNumericField($value, $fieldName, $coverageName)
    {
        if ($value === null || $value === '') {
            return; // Empty values are OK
        }

        $cleaned = str_replace(',', '', (string)$value);
        $cleaned = preg_replace('/[^0-9.-]/', '', $cleaned);

        // Check if the cleaned value is a valid number
        if (!is_numeric($cleaned) || $cleaned === '') {
            $errorMessage = "Sheet: {$coverageName} - Numeric value should be entered in '{$fieldName}' field";
            \Log::error($errorMessage, [
                'sheet' => $coverageName,
                'field' => $fieldName,
                'value' => $value
            ]);
            throw new \Exception($errorMessage);
        }
    }

    /**
     * Remove commas from numeric values and convert to numeric type
     */
    private function removeCommasAndConvertToNumeric($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // Convert to string, remove commas, and convert to float/numeric
        $cleaned = str_replace(',', '', (string)$value);

        // Remove any other non-numeric characters except decimal point and minus sign
        $cleaned = preg_replace('/[^0-9.-]/', '', $cleaned);
        
        // Convert to numeric (float for decimals, int for whole numbers)
        if ($cleaned === '' || $cleaned === '-') {
            return 0;
        }
        
        return is_numeric($cleaned) ? (strpos($cleaned, '.') !== false ? (float)$cleaned : (int)$cleaned) : 0;
    }

    /**
     * Normalize and fetch ratefactor_AnnualWages from various header spellings.
     * Accepts any case/spacing/underscore variants of "ratefactor_AnnualWages".
     */
    private function getRatefactorAnnualWagesValue(array $rowData)
    {
        foreach ($rowData as $key => $value) {
            $normalized = strtolower(str_replace([' ', '-'], '_', $key));
            if ($normalized === 'ratefactor_annualwages') {
                if ($value !== null && $value !== '') {
                    return $this->removeCommasAndConvertToNumeric($value);
                }
            }
        }

        return 0;
    }
    
    /**
     * Save COMMERCIALMOTOR data to motor table
     * Uses dynamic column mapping from config and updateOrCreate to edit existing records
     * rowIndex: 0 = 1st record, 1 = 2nd record, etc.
     */
    private function saveMotorData($rowData, $rowIndex = 0)
    {
        // Get registration number from Excel (try multiple formats)
        $registrationNo = $rowData['registration_no'] ?? $rowData['Registration No'] ?? 
                         $rowData['registration no'] ?? $rowData['vehicle_plate'] ?? 
                         $rowData['Vehicle Plate'] ?? $rowData['vehiclePlate'] ?? '';
        
        if (empty($registrationNo)) {
            \Log::error('Registration number is required for COMMERCIALMOTOR import', ['row' => $rowData]);
            throw new \Exception('Registration number is required for COMMERCIALMOTOR import');
        }
        
        // Fetch vehicle data from Vehicle table using registration number
        $vehicle = Vehicle::PolicyId($this->policy->id)
            ->TermId($this->termId)
            ->ActionId($this->actionId)
            ->where('vehiclePlate', $registrationNo)
            ->whereNull('deleted_at')
            ->first();
        
        if (!$vehicle) {
            \Log::error('Vehicle not found for registration number', [
                'registration_no' => $registrationNo,
                'policy_id' => $this->policy->id,
                'term_id' => $this->termId,
                'action_id' => $this->actionId
            ]);
            throw new \Exception("Vehicle not found for registration number: {$registrationNo}");
        }
        
        // Validate that vehicle belongs to the selected risk address
        if ($vehicle->risk_id != $this->selectedRiskAddress) {
            // Get risk address name for error message
            $riskAddressName = '';
            if (is_array($this->riskAddressAndId)) {
                // Reverse lookup: find name by ID
                foreach ($this->riskAddressAndId as $name => $id) {
                    if ($id == $this->selectedRiskAddress) {
                        $riskAddressName = $name;
                        break;
                    }
                }
            }
            
            // If not found in array, try to get from RiskAddress model
            if (empty($riskAddressName)) {
                $riskAddress = \AlphaDirect\Models\RiskAddress::find($this->selectedRiskAddress);
                $riskAddressName = $riskAddress->address_name ?? 'Unknown';
            }
            
            $errorMessage = "This vehicle ({$registrationNo}) does not exist for risk address: {$riskAddressName}";
            \Log::error($errorMessage, [
                'registration_no' => $registrationNo,
                'vehicle_risk_id' => $vehicle->risk_id,
                'selected_risk_address_id' => $this->selectedRiskAddress,
                'risk_address_name' => $riskAddressName
            ]);
            throw new \Exception($errorMessage);
        }
        
        // Build data array - start with vehicle data from Vehicle table
        $data = [
            'policy_coverage_id' => $this->selectedPolicyCoverages->id,
            'registration_no' => $vehicle->vehiclePlate,
            'make' => $vehicle->make ?? null,
            'model' => $vehicle->model ?? null,
            'vehicle_name' => trim(($vehicle->make ?? '') . ' ' . ($vehicle->model ?? '')),
            'chassis_number' => $vehicle->chassisNo ?? null,
            'engine_number' => $vehicle->engineNo ?? null,
            'estimated_value' => $vehicle->estimated_value ?? null,
            'previousActionIdCov' => $this->actionId,
        ];
        
        // Get field mappings from config for other fields (coverage values, premiums, etc.)
        $coverageType = 'COMMERCIALMOTOR';
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}.field_mappings.motor";
        $fieldMappings = config($configPath, []);
        
        // Map Excel columns to database fields dynamically (for coverage values, premiums, etc.)
        // Skip vehicle-related fields that were already set from Vehicle table
        $vehicleFieldsFromTable = ['registration_no', 'make', 'model', 'vehicle_name', 'chassis_number', 'engine_number', 'estimated_value'];
        
        foreach ($fieldMappings as $headerName => $dbField) {
            // Skip risk address as it's not a motor table field
            if ($dbField === 'riskAddress.address_name') {
                continue;
            }
            
            // Skip vehicle fields that were already set from Vehicle table
            // Allow Excel to override if explicitly provided
            if (in_array($dbField, $vehicleFieldsFromTable)) {
                // Check if Excel has a value for this field (allow override)
                $hasExcelValue = false;
                $possibleKeys = [
                    strtolower(str_replace(' ', '_', $headerName)),
                    strtolower(str_replace('_', ' ', $headerName)),
                    $headerName,
                    strtolower($headerName),
                    ucfirst(strtolower(str_replace(' ', '_', $headerName))),
                    str_replace(' ', '_', strtolower($headerName)),
                ];
                foreach ($possibleKeys as $key) {
                    if (isset($rowData[$key]) && $rowData[$key] !== '') {
                        $hasExcelValue = true;
                        break;
                    }
                }
                if (!$hasExcelValue) {
                    continue; // Use vehicle table value, don't override
                }
            }
             
            // Try multiple formats to get the Excel column value
            $possibleKeys = [
                strtolower(str_replace(' ', '_', $headerName)), // vehicle_name
                strtolower(str_replace('_', ' ', $headerName)), // vehicle name
                $headerName, // Vehicle Name (exact match)
                strtolower($headerName), // vehicle_name
                ucfirst(strtolower(str_replace(' ', '_', $headerName))), // Vehicle_name
                str_replace(' ', '_', strtolower($headerName)), // vehicle_name
            ];
            
            $value = null; 
            foreach ($possibleKeys as $key) {
                if (isset($rowData[$key]) && $rowData[$key] !== '') {
                    $value = $rowData[$key];
                    break;
                }
            }
            
            // Map to database field (use dbField directly as it's the column name)
            if ($value !== null && $value !== '') {
                // Remove commas from numeric fields
                $numericFields = [
                    'coverage_value', 'calculated_value', 'estimated_value',
                    'coverage_value_main', 'calculated_value_main',
                    'premium_wreckage_removal', 'premium_window_glass', 'premium_locks_keys',
                    'premium_parts_accessories', 'premium_audio_accessories', 'premium_riot_strike',
                    'premium_car_hire_theft', 'premium_credit_shortfall', 'premium_insured_driver',
                    'premium_insured_family', 'premium_medical_expenses', 'premium_passenger_liability',
                    'premium_third_party_liability', 'premium_specified_accessories',
                    'premium_unorthorised_passanger_liability', 'premium_parking_facilities',
                    'premium_com_windscreen', 'own_damage_minimum_amount', 'windscreen_minimum_amount',
                    'loss_of_keys_minimum_amount'
                ];
                
                if (in_array(strtolower($dbField), $numericFields)) {
                    $value = $this->removeCommasAndConvertToNumeric($value);
                }
                
                // Special handling: Store "Coverage Value" in both coverage_value and coverage_value_main
                if ($dbField === 'coverage_value' && $headerName === 'Coverage Value') {
                    $data['coverage_value'] = $value;
                    $data['coverage_value_main'] = $value;
                }
                // Special handling: Store "Calculated Value" in both calculated_value and calculated_value_main
                elseif ($dbField === 'calculated_value' && $headerName === 'Calculated Value') {
                    $data['calculated_value'] = $value;
                    $data['calculated_value_main'] = $value;
                }
                else {
                    $data[$dbField] = $value;
                }
            }
        }
        
        // Get existing records for this policy_coverage_id, ordered by id
        // Note: Motor model doesn't use SoftDeletes trait, but has deleted_at column
        // Get all records (including soft-deleted) to match by position
        $existingRecords = Motor::where('policy_coverage_id', $this->selectedPolicyCoverages->id)
            ->orderBy('id', 'asc')
            ->get();
        
        // If the nth record exists, update it; otherwise create new one
        if ($existingRecords->count() > $rowIndex) {
            // Update existing record at rowIndex
            $existingRecord = $existingRecords[$rowIndex];
            $data['deleted_at'] = null; // Restore if soft-deleted
            $existingRecord->update($data);
        } else {
            // Guard: do not create a second live motor row for the same plate under a
            // DIFFERENT risk-address coverage on this policy+action. A vehicle belongs to
            // one risk address; it must be removed from the other before being added here.
            $dupMotor = Motor::join('policy_coverages as pc', 'pc.id', '=', 'motor.policy_coverage_id')
                ->where('motor.registration_no', $vehicle->vehiclePlate)
                ->where('pc.policy_id', $this->policy->id)
                ->where('pc.action_id', $this->actionId)
                ->where('motor.policy_coverage_id', '!=', $this->selectedPolicyCoverages->id)
                ->whereNull('motor.deleted_at')
                ->whereNull('pc.deleted_at')
                ->exists();

            if ($dupMotor) {
                throw new \Exception("Vehicle {$vehicle->vehiclePlate} is already covered under another risk address on this policy. Remove it from that risk address before importing it here.");
            }

            // Create new record
            Motor::create($data);
        }
    }
    
    /**
     * Save MOTORTRADERSEXTERNAL data to motor_traders table
     * Uses dynamic column mapping from config and updateOrCreate to edit existing records
     * rowIndex: 0 = 1st record, 1 = 2nd record, etc.
     */
    private function saveMotorTradersData($rowData, $rowIndex = 0)
    {
        // Get field mappings from config
        $coverageType = 'MOTORTRADERSEXTERNAL';
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}.field_mappings.motor_traders";
        $fieldMappings = config($configPath, []);
        
        // Build data array dynamically based on field mappings
        $data = [
            'policy_coverage_id' => $this->selectedPolicyCoverages->id,
        ];
        
        // Map Excel columns to database fields dynamically
        foreach ($fieldMappings as $headerName => $dbField) {
            // Skip risk address as it's not a motor_traders table field
            if ($dbField === 'riskAddress.address_name') {
                continue;
            } 
             
            // Try multiple formats to get the Excel column value
            $possibleKeys = [
                strtolower(str_replace(' ', '_', $headerName)), // type_of_cover
                strtolower(str_replace('_', ' ', $headerName)), // type of cover
                $headerName, // Type Of Cover (exact match)
                strtolower($headerName), // type of cover
                ucfirst(strtolower(str_replace(' ', '_', $headerName))), // Type_of_cover
                str_replace(' ', '_', strtolower($headerName)), // type_of_cover
            ];
            
            $value = null; 
            foreach ($possibleKeys as $key) {
                if (isset($rowData[$key]) && $rowData[$key] !== '') {
                    $value = $rowData[$key];
                    break;
                }
            }
            
            // Map to database field (use dbField directly as it's the column name)
            if ($value !== null && $value !== '') {
                // Map Type Of Cover display values to database values
                if ($dbField === 'type_of_cover') {
                    $typeOfCoverMapping = [
                        'Comprehensive' => 'Comprehensive',
                        'Third party only' => 'third_party_only',
                        'Third party fire and theft' => 'Third_fire_and_theft',
                    ];
                    if (isset($typeOfCoverMapping[$value])) {
                        $value = $typeOfCoverMapping[$value];
                    }
                }
                
                // Remove commas from numeric fields
                $numericFields = [
                    'loss_or_damage_coverage_value', 'loss_or_damage_calculated_value',
                    'third_party_liability_coverage_value', 'third_party_liability_calculated_value',
                    'medical_benefits_coverage_value', 'medical_benefits_calculated_value',
                    'vehicle_lent_hire_coverage_value', 'vehicle_lent_hire_calculated_value',
                    'social_domestic_pleasure_coverage_value', 'social_domestic_pleasure_calculated_value',
                    'unauthoried_use_coverage_value', 'unauthoried_use_calculated_value',
                    'windscreen_coverage_value', 'windscreen_calculated_value',
                    'contigent_liability_coverage_value', 'contigent_liability_calculated_value',
                    'wreckage_removal_coverage_value', 'wreckage_removal_calculated_value',
                    'loss_of_key_coverage_value', 'loss_of_key_calculated_value',
                    'Loss_of_use_of_customer_coverage_value', 'Loss_of_use_of_customer_calculated_value',
                    'motor_cycle_motor_tricycle_coverage_value', 'motor_cycle_motor_tricycle_calculated_value',
                    'passanger_liability_respect_of_motor_coverage_value', 'passanger_liability_respect_of_motor_calculated_value',
                    'special_type_vehicle_coverage_value', 'special_type_vehicle_calculated_value',
                    'own_damage_minimum_amount', 'windscreen_minimum_amount',
                ];
                
                if (in_array(strtolower($dbField), $numericFields)) {
                    $value = $this->removeCommasAndConvertToNumeric($value);
                }
                
                $data[$dbField] = $value;
            }
        }
        
        // Get existing records for this policy_coverage_id, ordered by id
        // MotorTraders uses SoftDeletes, so get all records (including soft-deleted) to match by position
        $existingRecords = MotorTraders::where('policy_coverage_id', $this->selectedPolicyCoverages->id)
            ->withTrashed()
            ->orderBy('id', 'asc')
            ->get();
        
        // If the nth record exists, update it; otherwise create new one
        if ($existingRecords->count() > $rowIndex) {
            // Update existing record at rowIndex
            $existingRecord = $existingRecords[$rowIndex];
            if ($existingRecord->trashed()) {
                $existingRecord->restore();
            }
            $existingRecord->update($data);
        } else {
            // Create new record
            MotorTraders::create($data);
        }
    }
    
    /**
     * Save MOTORTRADERSINTERNAL data to motor_traders_internal table
     * Uses dynamic column mapping from config and updateOrCreate to edit existing records
     * rowIndex: 0 = 1st record, 1 = 2nd record, etc.
     */
    private function saveMotorTradersInternalData($rowData, $rowIndex = 0)
    {
        // Get field mappings from config
        $coverageType = 'MOTORTRADERSINTERNAL';
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}.field_mappings.motor_traders_internal";
        $fieldMappings = config($configPath, []);
        
        // Build data array dynamically based on field mappings
        $data = [
            'policy_coverage_id' => $this->selectedPolicyCoverages->id,
        ];
        
        // Map Excel columns to database fields dynamically
        foreach ($fieldMappings as $headerName => $dbField) {
            // Skip risk address as it's not a motor_traders_internal table field
            if ($dbField === 'riskAddress.address_name') {
                continue;
            } 
             
            // Try multiple formats to get the Excel column value
            $possibleKeys = [
                strtolower(str_replace(' ', '_', $headerName)), // type_of_cover
                strtolower(str_replace('_', ' ', $headerName)), // type of cover
                $headerName, // Type Of Cover (exact match)
                strtolower($headerName), // type of cover
                ucfirst(strtolower(str_replace(' ', '_', $headerName))), // Type_of_cover
                str_replace(' ', '_', strtolower($headerName)), // type_of_cover
            ];
            
            $value = null; 
            foreach ($possibleKeys as $key) {
                if (isset($rowData[$key]) && $rowData[$key] !== '') {
                    $value = $rowData[$key];
                    break;
                }
            }
            
            // Map to database field (use dbField directly as it's the column name)
            if ($value !== null && $value !== '') {
                // Map Type Of Cover display values to database values
                if ($dbField === 'type_of_cover') {
                    $typeOfCoverMapping = [
                        'Comprehensive' => 'Comprehensive',
                        'Third party only' => 'third_party_only',
                        'Third party fire and theft' => 'Third_fire_and_theft',
                    ];
                    if (isset($typeOfCoverMapping[$value])) {
                        $value = $typeOfCoverMapping[$value];
                    }
                }
                
                // Remove commas from numeric fields
                $numericFields = [
                    'loss_or_damage_coverage_value', 'loss_or_damage_calculated_value',
                    'third_party_liability_coverage_value', 'third_party_liability_calculated_value',
                    'medical_benefits_coverage_value', 'medical_benefits_calculated_value',
                    'vehicle_lent_hire_coverage_value', 'vehicle_lent_hire_calculated_value',
                    'social_domestic_pleasure_coverage_value', 'social_domestic_pleasure_calculated_value',
                    'unauthoried_use_coverage_value', 'unauthoried_use_calculated_value',
                    'windscreen_coverage_value', 'windscreen_calculated_value',
                    'contigent_liability_coverage_value', 'contigent_liability_calculated_value',
                    'wreckage_removal_coverage_value', 'wreckage_removal_calculated_value',
                    'loss_of_key_coverage_value', 'loss_of_key_calculated_value',
                    'Loss_of_use_of_customer_coverage_value', 'Loss_of_use_of_customer_calculated_value',
                    'motor_cycle_motor_tricycle_coverage_value', 'motor_cycle_motor_tricycle_calculated_value',
                    'passanger_liability_respect_of_motor_coverage_value', 'passanger_liability_respect_of_motor_calculated_value',
                    'special_type_vehicle_coverage_value', 'special_type_vehicle_calculated_value',
                    'own_damage_minimum_amount', 'windscreen_minimum_amount',
                ];
                
                if (in_array(strtolower($dbField), $numericFields)) {
                    $value = $this->removeCommasAndConvertToNumeric($value);
                }
                
                $data[$dbField] = $value;
            }
        }
        
        // Get existing records for this policy_coverage_id, ordered by id
        // MotorTradersInternal uses SoftDeletes, so get all records (including soft-deleted) to match by position
        $existingRecords = MotorTradersInternal::where('policy_coverage_id', $this->selectedPolicyCoverages->id)
            ->withTrashed()
            ->orderBy('id', 'asc')
            ->get();
        
        // If the nth record exists, update it; otherwise create new one
        if ($existingRecords->count() > $rowIndex) {
            // Update existing record at rowIndex
            $existingRecord = $existingRecords[$rowIndex];
            if ($existingRecord->trashed()) {
                $existingRecord->restore();
            }
            $existingRecord->update($data);
        } else {
            // Create new record
            MotorTradersInternal::create($data);
        }
    }
    
    /**
     * Save FIDELITYGUARANTEE data to policy_coverages_data table
     * Uses dynamic column mapping from config and updateOrCreate to edit existing records
     * rowIndex: 0 = 1st record, 1 = 2nd record, etc.
     */
    private function saveFidelityGuaranteeData($rowData, $rowIndex = 0)
    {
        // Get field mappings from config
        $coverageType = strtoupper($this->sheetName);
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}.field_mappings.coverage_detail";
        $fieldMappings = config($configPath, []);
        
        // Build data array dynamically based on field mappings
        // Note: term_id and action_id are not stored in policy_coverages_data table
        // They can be accessed via PolicyCoverage relationship when needed
        $data = [
            'policyCoverageID' => $this->selectedPolicyCoverages->id,
            'policy_id' => $this->policy->id,
            'risk_address' => $this->selectedRiskAddress,
        ];
        
        // Map Excel columns to database fields dynamically
        foreach ($fieldMappings as $headerName => $dbField) {
            // Skip risk address as it's already set
            if ($dbField === 'riskAddress.address_name') {
                continue;
            } 
             
            // Try multiple formats to get the Excel column value
            $possibleKeys = [
                strtolower(str_replace(' ', '_', $headerName)), // cover_type
                strtolower(str_replace('_', ' ', $headerName)), // cover type
                $headerName, // cover_type (exact match)
                strtolower($headerName), // cover_type
                ucfirst(strtolower(str_replace(' ', '_', $headerName))), // Cover_type
            ];
            
            $value = null; 
            foreach ($possibleKeys as $key) {
                if (isset($rowData[$key]) && $rowData[$key] !== '') {
                    $value = $rowData[$key];
                    break;
                }
            }
            
            // Map to database field (use dbField directly as it's the column name)
            if ($value !== null && $value !== '') {
                // Remove commas from numeric fields
                if (in_array(strtolower($headerName), ['limit', 'premium', 'ratefactor_annualwages', 'sum insured', 'amount_to_be_guaranteed'])) {
                    $value = $this->removeCommasAndConvertToNumeric($value);
                }
                $data[$dbField] = $value;
            }
        }
        
        // Get existing records for this policyCoverageID, ordered by id
        $existingRecords = PolicyCoveragesData::where('policyCoverageID', $this->selectedPolicyCoverages->id)
            ->orderBy('id', 'asc')
            ->get();
        
        // If the nth record exists, update it; otherwise create new one
        if ($existingRecords->count() > $rowIndex) {
            // Update existing record at rowIndex
            $existingRecord = $existingRecords[$rowIndex];
            $existingRecord->update($data);
        } else {
            // Create new record
            PolicyCoveragesData::create($data);
        }
    }

 
}