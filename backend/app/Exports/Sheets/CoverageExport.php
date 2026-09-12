<?php
namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Http\Traits\Excel\ExportTrait;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\PolicyCoverageEntity;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Vehicle;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class CoverageExport implements WithTitle,FromCollection,WithStyles,WithColumnWidths,WithEvents
{
    public $policy;
    public $termId;
    public $actionId;

    public $policyCoverages;
    public $coverageName;
    public $maxLength = 100;

    public function __construct($policy,$termId,$actionId,$coverageName,$policyCoverages)
    {
             $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;

        $this->policyCoverages = $policyCoverages;
       //dd($this->policyCoverages,$coverageName);
//        $this->coverageName = $policyCoverage->coverage->s_CoverageCode."->".$policyCoverage->id;
        $this->coverageName = $coverageName;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->coverageName ?? 'NA';
    }
    public function collection()
    {
        // Get coverage type configuration dynamically
        $coverageType = $this->getCoverageType();
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
        
        $headers = config("{$configPath}.headers");
        $defaultValues = config("{$configPath}.default_value");
        $fieldMappings = config("{$configPath}.field_mappings", []);
        
        // Ensure headers and defaultValues are arrays
        if (!is_array($headers)) {
            $headers = [];
        }
        if (!is_array($defaultValues)) {
            $defaultValues = array_fill(0, count($headers), '');
        }
    
        // Start with only headers, don't pre-fill with default values
        $data = [$headers];
        $rowIndex = 0; // Current row index (0 = header, 1+ = data rows)

        foreach ($this->policyCoverages as $index => $policyCoverage){
            $isFireCoverage = ($policyCoverage->coverage_id == 1);
            
            if ($isFireCoverage) {
                \Log::info('FIRE Coverage Export - Processing PolicyCoverage', [
                    'coverage_name' => $this->coverageName,
                    'policy_coverage_id' => $policyCoverage->id,
                    'coverage_id' => $policyCoverage->coverage_id,
                    'has_coverageDetail' => isset($policyCoverage->coverageDetail),
                    'coverageDetail_type' => gettype($policyCoverage->coverageDetail),
                    'coverageDetail_count' => $policyCoverage->coverageDetail ? (is_countable($policyCoverage->coverageDetail) ? count($policyCoverage->coverageDetail) : 'N/A') : 0,
                ]);
            }
            
            $rowCoverageIndex = $rowIndex;
            $rowSpecifiedIndex = $rowIndex;
            $rowEntityIndex = $rowIndex;
            
            // Process coverage details
            if (!empty($fieldMappings['coverage_detail'])) {
                if ($isFireCoverage) {
                    \Log::info('FIRE Coverage Export - Processing coverage_detail', [
                        'policy_coverage_id' => $policyCoverage->id,
                        'fieldMappings_count' => count($fieldMappings['coverage_detail']),
                        'has_coverageDetail_relationship' => isset($policyCoverage->coverageDetail),
                    ]);
                }
                
                // For FIDELITYGUARANTEE, read from policy_coverages_data table
                if (strtoupper($this->coverageName) === 'FIDELITYGUARANTEE') {
                    $fidelityData = PolicyCoveragesData::where('policyCoverageID', $policyCoverage->id)->get();
                    foreach ($fidelityData as $key => $fidelityItem) {
                        $rowCoverageIndex++;
                        // Initialize row with default values
                        $data[$rowCoverageIndex] = $defaultValues;
                        $this->mapFidelityDataToRow($data, $rowCoverageIndex, $headers, $fieldMappings['coverage_detail'], $fidelityItem, $policyCoverage, $key === 0);
                        $rowIndex = max($rowIndex, $rowCoverageIndex);
                    }
                } else {
                    // For other coverages, read from PolicyCoverageDetail
                    if ($isFireCoverage) {
                        \Log::info('FIRE Coverage Export - Before iterating coverageDetail', [
                            'policy_coverage_id' => $policyCoverage->id,
                            'coverageDetail_exists' => isset($policyCoverage->coverageDetail),
                            'coverageDetail_is_collection' => $policyCoverage->coverageDetail instanceof \Illuminate\Support\Collection,
                            'coverageDetail_count' => $policyCoverage->coverageDetail ? (is_countable($policyCoverage->coverageDetail) ? count($policyCoverage->coverageDetail) : 'N/A') : 0,
                        ]);
                    }
                    
                    $coverageDetailCount = 0;
                    foreach ($policyCoverage->coverageDetail as $key => $coverageDetail) {
                        $coverageDetailCount++;
                        if ($isFireCoverage) {
                            \Log::info('FIRE Coverage Export - Processing coverageDetail item', [
                                'policy_coverage_id' => $policyCoverage->id,
                                'coverageDetail_id' => $coverageDetail->id ?? 'N/A',
                                'coverageDetail_coverage_id' => $coverageDetail->coverage_id ?? 'N/A',
                                'coverageDetail_coverage_value' => $coverageDetail->coverage_value ?? 'N/A',
                                'item_index' => $key,
                            ]);
                        }
                        $rowCoverageIndex++;
                        // Initialize row with default values
                        $data[$rowCoverageIndex] = $defaultValues;
                        $this->mapDataToRow($data, $rowCoverageIndex, $headers, $fieldMappings['coverage_detail'], $coverageDetail, $policyCoverage, $key === 0);
                        $rowIndex = max($rowIndex, $rowCoverageIndex);
                    }
                    
                    if ($isFireCoverage) {
                        \Log::info('FIRE Coverage Export - After iterating coverageDetail', [
                            'policy_coverage_id' => $policyCoverage->id,
                            'coverageDetail_items_processed' => $coverageDetailCount,
                            'rows_added' => $rowCoverageIndex - $rowIndex,
                        ]);
                    }
                }
            } else {
                if ($isFireCoverage) {
                    \Log::warning('FIRE Coverage Export - No coverage_detail field mappings', [
                        'policy_coverage_id' => $policyCoverage->id,
                        'coverage_name' => $this->coverageName,
                        'fieldMappings' => $fieldMappings,
                    ]);
                }
            }
            
            // Process motor data for COMMERCIALMOTOR
            if (strtoupper($this->coverageName) === 'COMMERCIALMOTOR' && !empty($fieldMappings['motor'])) {
                if ($policyCoverage->motor && $policyCoverage->motor->count() > 0) {
                    foreach ($policyCoverage->motor as $key => $motorItem) {
                        // Skip soft-deleted records
                        if ($motorItem && $motorItem->deleted_at) {
                            continue;
                        }
                        if (!$motorItem) {
                            continue;
                        }
                        $rowCoverageIndex++;
                        // Initialize row with default values (ensure it matches header count)
                        $rowData = is_array($defaultValues) ? $defaultValues : array_fill(0, count($headers), '');
                        // Ensure row has correct length
                        while (count($rowData) < count($headers)) {
                            $rowData[] = '';
                        }
                        $data[$rowCoverageIndex] = $rowData;
                        $this->mapMotorDataToRow($data, $rowCoverageIndex, $headers, $fieldMappings['motor'], $motorItem, $policyCoverage, $key === 0);
                        $rowIndex = max($rowIndex, $rowCoverageIndex);
                    }
                }
            }
             
            // Process motor traders external data for MOTORTRADERSEXTERNAL
            if (strtoupper($this->coverageName) === 'MOTORTRADERSEXTERNAL' && !empty($fieldMappings['motor_traders'])) {
                if ($policyCoverage->motorExteranal && $policyCoverage->motorExteranal->count() > 0) {
                    foreach ($policyCoverage->motorExteranal as $key => $motorTradersItem) {
                        // Skip soft-deleted records
                        if ($motorTradersItem && $motorTradersItem->deleted_at) {
                            continue;
                        }
                        if (!$motorTradersItem) {
                            continue;
                        }
                        $rowCoverageIndex++;
                        // Initialize row with default values (ensure it matches header count)
                        $rowData = is_array($defaultValues) ? $defaultValues : array_fill(0, count($headers), '');
                        // Ensure row has correct length
                        while (count($rowData) < count($headers)) {
                            $rowData[] = '';
                        }
                        $data[$rowCoverageIndex] = $rowData;
                        $this->mapMotorTradersDataToRow($data, $rowCoverageIndex, $headers, $fieldMappings['motor_traders'], $motorTradersItem, $policyCoverage, $key === 0);
                        $rowIndex = max($rowIndex, $rowCoverageIndex);
                    }
                }
            }
             
            // Process motor traders internal data for MOTORTRADERSINTERNAL
            if (strtoupper($this->coverageName) === 'MOTORTRADERSINTERNAL' && !empty($fieldMappings['motor_traders_internal'])) {
                if ($policyCoverage->motorInternal && $policyCoverage->motorInternal->count() > 0) {
                    foreach ($policyCoverage->motorInternal as $key => $motorTradersInternalItem) {
                        // Skip soft-deleted records
                        if ($motorTradersInternalItem && $motorTradersInternalItem->deleted_at) {
                            continue;
                        }
                        if (!$motorTradersInternalItem) {
                            continue;
                        }
                        $rowCoverageIndex++;
                        // Initialize row with default values (ensure it matches header count)
                        $rowData = is_array($defaultValues) ? $defaultValues : array_fill(0, count($headers), '');
                        // Ensure row has correct length
                        while (count($rowData) < count($headers)) {
                            $rowData[] = '';
                        }
                        $data[$rowCoverageIndex] = $rowData;
                        $this->mapMotorTradersInternalDataToRow($data, $rowCoverageIndex, $headers, $fieldMappings['motor_traders_internal'], $motorTradersInternalItem, $policyCoverage, $key === 0);
                        $rowIndex = max($rowIndex, $rowCoverageIndex);
                    }
                }
            }
              // Process specified items (these are separate rows, continue from where we left off)
            // if (!empty($fieldMappings['specified_items'])) {
            //     foreach ($policyCoverage->specifedItems as $key => $specifiedItem) {
            //         $rowSpecifiedIndex++;
            //         // Initialize row with default values
            //         $data[$rowSpecifiedIndex] = $defaultValues;
            //         $this->mapDataToRow($data, $rowSpecifiedIndex, $headers, $fieldMappings['specified_items'], $specifiedItem, $policyCoverage, $key === 0);
            //         $rowIndex = max($rowIndex, $rowSpecifiedIndex);
            //     }
            // }
            
            // // Process entities (all entities go to the same row, different columns)
            // if (!empty($fieldMappings['entities']) && $policyCoverage->entities->count() > 0) {
            //     $rowEntityIndex++;
            //     // Initialize row with default values
            //     $data[$rowEntityIndex] = $defaultValues;
            //     foreach ($policyCoverage->entities as $key => $entity) {
            //         $this->mapEntityDataToRow($data, $rowEntityIndex, $headers, $fieldMappings['entities'], $entity, $policyCoverage, $key === 0);
            //     }
            //     $rowIndex = max($rowIndex, $rowEntityIndex);
            // }
            // Skip specified items and entities - only process coverage details (coverage and subcoverages)
            // Specified items and entities are not included in coverage export
        }
        
        // Filter out completely empty rows (rows with only empty strings or default values)
        $finalData = [$headers]; // Always include header
        for ($i = 1; $i <= $rowIndex; $i++) {
            if (isset($data[$i])) {
                $rowData = $data[$i];
                // Check if row has any non-empty values
                $hasNonEmptyValue = false;
                foreach ($rowData as $value) {
                  
                    if ($value !== '' && $value !== null && trim($value) !== '') {
                        $hasNonEmptyValue = true;
                        break;
                    }
                }
                // Only add row if it has at least one non-empty value
                if ($hasNonEmptyValue) {
                  
                    $finalData[] = $rowData;
                }
            }
        }
        
        // If no data rows, return only header
        if (count($finalData) <= 1) {
            return new Collection([$headers]);
        }
       // dd($finalData);
        return new Collection($finalData);
    }
    
    /**
     * Get coverage type based on coverage name
     */
    private function getCoverageType()
    {
        // Check if specific coverage type exists in config, otherwise use OTHER
        $coverageType = strtoupper($this->coverageName);
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
        
        if (config("{$configPath}.headers")) {
            return $coverageType;
        }
        
        return 'OTHER';
    }
    
    /**
     * Map data to row based on field mappings
     */
    private function mapDataToRow(&$data, $rowIndex, $headers, $mappings, $item, $policyCoverage, $isFirstInGroup)
    {
      //  dd($policyCoverage['coverage_id']);
        
        foreach ($mappings as $headerName => $fieldPath) {
            $columnIndex = array_search($headerName, $headers);
            if ($columnIndex === false) {
                continue;
            }
            
            // Handle special cases
            if ($fieldPath === 'riskAddress.address_name') {
                if ($isFirstInGroup) {
                    $data[$rowIndex][$columnIndex] = $policyCoverage->riskAddress->address_name ?? '';
                }
            } else {
              
                $value = $this->getNestedValue($item, $fieldPath);
                $data[$rowIndex][$columnIndex] = $value ?? '';
    //               if($policyCoverage['coverage_id']==1)
    //         {
    //    dd( $rowIndex,$columnIndex,$value);
    //         }
            }
        }
    }
    
    /**
     * Map entity data to row
     */
    private function mapEntityDataToRow(&$data, $rowIndex, $headers, $mappings, $entity, $policyCoverage, $isFirstInGroup)
    {
      
        foreach ($mappings as $headerName => $fieldPath) {
            $columnIndex = array_search($headerName, $headers);
            if ($columnIndex === false) {
                continue;
            }
            
            // Handle special cases
            if ($fieldPath === 'riskAddress.address_name') {
                if ($isFirstInGroup) {
                    $data[$rowIndex][$columnIndex] = $policyCoverage->riskAddress->address_name ?? '';
                }
            } elseif (strpos($fieldPath, 'entity_type:') === 0) {
                // Handle entity type mapping (e.g., 'entity_type:Vehicle')
                $entityType = str_replace('entity_type:', '', $fieldPath);
                if ($entity->entity_type == $entityType) {
                    $entityValue = "";
                    if ($entityType == "Vehicle") {
                        $entityValue = $this->getVehicles()[$entity->entity_id] ?? '';
                    } elseif ($entityType == "Member") {
                        $entityValue = $this->getBeneficiaries()[$entity->entity_id] ?? '';
                    } elseif ($entityType == "Device") {
                        $entityValue = $this->getDevices()[$entity->entity_id] ?? '';
                    }
                    $data[$rowIndex][$columnIndex] = $entityValue;
                }
            } else {
                $value = $this->getNestedValue($entity, $fieldPath);
                $data[$rowIndex][$columnIndex] = $value ?? '';
            }
        }
    }
    
    /**
     * Map FIDELITYGUARANTEE data to row (from policy_coverages_data table)
     */
    private function mapFidelityDataToRow(&$data, $rowIndex, $headers, $mappings, $item, $policyCoverage, $isFirstInGroup)
    {
        foreach ($mappings as $headerName => $fieldPath) {
            $columnIndex = array_search($headerName, $headers);
            if ($columnIndex === false) {
                continue;
            }
            
            // Handle special cases
            if ($fieldPath === 'riskAddress.address_name') {
                if ($isFirstInGroup) {
                    $data[$rowIndex][$columnIndex] = $policyCoverage->riskAddress->address_name ?? '';
                }
            } else {
                // For FIDELITYGUARANTEE, fieldPath is the database column name directly
                // (e.g., 'cover_type', 'cover_area', 'amount_to_be_guaranteed', 'premium')
                $value = $item->$fieldPath ?? null;
                $data[$rowIndex][$columnIndex] = $value ?? '';
            }
        }
    }
    
    /**
     * Map COMMERCIALMOTOR data to row (from motor table)
     */
    private function mapMotorDataToRow(&$data, $rowIndex, $headers, $mappings, $item, $policyCoverage, $isFirstInGroup)
    {
        
        if (!$item || !$mappings || !is_array($headers)) {
            return;
        }
        
        foreach ($mappings as $headerName => $fieldPath) {
            $columnIndex = array_search($headerName, $headers);
            if ($columnIndex === false) {
                continue;
            }
            
            // Handle special cases
            if ($fieldPath === 'riskAddress.address_name') {
                // Include risk address for every motor record row
                $data[$rowIndex][$columnIndex] = $policyCoverage->riskAddress->address_name ?? '';
            } else {
                // For motor, fieldPath is the database column name directly
                $value = $item->$fieldPath ?? null;
                $data[$rowIndex][$columnIndex] = $value ?? '';
            }
        }
    }
    
    /**
     * Map MOTORTRADERSEXTERNAL data to row (from motor_traders table)
     */
    private function mapMotorTradersDataToRow(&$data, $rowIndex, $headers, $mappings, $item, $policyCoverage, $isFirstInGroup)
    {
        if (!$item || !$mappings || !is_array($headers)) {
            return;
        }
        
        foreach ($mappings as $headerName => $fieldPath) {
            $columnIndex = array_search($headerName, $headers);
            if ($columnIndex === false) {
                continue;
            }
            
            // Handle special cases
            if ($fieldPath === 'riskAddress.address_name') {
                // Include risk address for every motor traders record row
                $data[$rowIndex][$columnIndex] = $policyCoverage->riskAddress->address_name ?? '';
            } else {
                // For motor traders, fieldPath is the database column name directly
                $value = $item->$fieldPath ?? null;
                
                // Map Type Of Cover database values to display values for export
                if ($fieldPath === 'type_of_cover' && $value !== null) {
                    $typeOfCoverMapping = [
                        'Comprehensive' => 'Comprehensive',
                        'third_party_only' => 'Third party only',
                        'Third_fire_and_theft' => 'Third party fire and theft',
                    ];
                    if (isset($typeOfCoverMapping[$value])) {
                        $value = $typeOfCoverMapping[$value];
                    }
                }
                
                $data[$rowIndex][$columnIndex] = $value ?? '';
            }
        }
    }
    
    /**
     * Map MOTORTRADERSINTERNAL data to row (from motor_traders_internal table)
     */
    private function mapMotorTradersInternalDataToRow(&$data, $rowIndex, $headers, $mappings, $item, $policyCoverage, $isFirstInGroup)
    {
        if (!$item || !$mappings || !is_array($headers)) {
            return;
        }
        
        foreach ($mappings as $headerName => $fieldPath) {
            $columnIndex = array_search($headerName, $headers);
            if ($columnIndex === false) {
                continue;
            }
            
            // Handle special cases
            if ($fieldPath === 'riskAddress.address_name') {
                // Include risk address for every motor traders internal record row
                $data[$rowIndex][$columnIndex] = $policyCoverage->riskAddress->address_name ?? '';
            } else {
                // For motor traders internal, fieldPath is the database column name directly
                $value = $item->$fieldPath ?? null;
                
                // Map Type Of Cover database values to display values for export
                if ($fieldPath === 'type_of_cover' && $value !== null) {
                    $typeOfCoverMapping = [
                        'Comprehensive' => 'Comprehensive',
                        'third_party_only' => 'Third party only',
                        'Third_fire_and_theft' => 'Third party fire and theft',
                    ];
                    if (isset($typeOfCoverMapping[$value])) {
                        $value = $typeOfCoverMapping[$value];
                    }
                }
                
                $data[$rowIndex][$columnIndex] = $value ?? '';
            }
        }
    }
    
    /**
     * Get nested value from object using dot notation
     */
    private function getNestedValue($object, $path)
    {
        $parts = explode('.', $path);
        $value = $object;
        
        foreach ($parts as $part) {
            if (is_object($value) && isset($value->$part)) {
                $value = $value->$part;
            } elseif (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    public function styles(Worksheet $sheet)
    {
        $coverageType = $this->getCoverageType();
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
        return config("{$configPath}.style", config('constants.excel.edit_policy.coverage.OTHER.style'));
    }
    
    public function columnWidths(): array
    {
        $coverageType = $this->getCoverageType();
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
        return config("{$configPath}.column_widths", config('constants.excel.edit_policy.coverage.OTHER.column_widths'));
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                // Get the actual number of rows in the sheet (header + data rows)
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Check coverage type
                $isCommercialMotor = strtoupper($this->coverageName) === 'COMMERCIALMOTOR';
                $isMotorTradersExternal = strtoupper($this->coverageName) === 'MOTORTRADERSEXTERNAL';
                $isMotorTradersInternal = strtoupper($this->coverageName) === 'MOTORTRADERSINTERNAL';
                
                if ($isCommercialMotor) {
                    // For COMMERCIALMOTOR, add dropdowns for specific fields
                    $useOptions = ['Personal', 'Business'];
                    $typeOfCoverOptions = ['Comprehensive', 'Third party only', 'Third party fire and theft'];
                    
                    // Get headers to find column positions
                    $coverageType = $this->getCoverageType();
                    $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
                    $headers = config("{$configPath}.headers", []);
                    
                    // Find column indices for the fields
                    $useColumn = $this->getColumnLetter(array_search('Use', $headers));
                    $typeOfCoverColumn = $this->getColumnLetter(array_search('Type Of Cover', $headers));
                    $useMainColumn = $this->getColumnLetter(array_search('Use Main', $headers));
                    $typeOfCoverMainColumn = $this->getColumnLetter(array_search('Type Of Cover Main', $headers));
                    
                    // Add dropdowns for data rows (starting from row 2)
                    for ($i = 2; $i <= $highestRow; $i++) {
                        if ($useColumn) {
                            ExportTrait::generateDropDown($event, $useColumn . $i, $useOptions);
                        }
                        if ($typeOfCoverColumn) {
                            ExportTrait::generateDropDown($event, $typeOfCoverColumn . $i, $typeOfCoverOptions);
                        }
                        if ($useMainColumn) {
                            ExportTrait::generateDropDown($event, $useMainColumn . $i, $useOptions);
                        }
                        if ($typeOfCoverMainColumn) {
                            ExportTrait::generateDropDown($event, $typeOfCoverMainColumn . $i, $typeOfCoverOptions);
                        }
                    }
                } elseif ($isMotorTradersExternal) {
                    // For MOTORTRADERSEXTERNAL, add dropdown for Type Of Cover
                    $typeOfCoverOptions = ['Comprehensive', 'Third party only', 'Third party fire and theft'];
                    
                    // Get headers to find column positions
                    $coverageType = $this->getCoverageType();
                    $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
                    $headers = config("{$configPath}.headers", []);
                    
                    // Find column index for Type Of Cover
                    $typeOfCoverColumn = $this->getColumnLetter(array_search('Type Of Cover', $headers));
                    
                    // Add dropdowns for data rows (starting from row 2)
                    // Add dropdowns for entire column (starting from row 2, up to row 1000 to cover all potential rows)
                    $maxRows = max($highestRow, 1000); // At least 1000 rows, or more if data exists
                    for ($i = 2; $i <= $maxRows; $i++) {
                        if ($typeOfCoverColumn) {
                            ExportTrait::generateDropDown($event, $typeOfCoverColumn . $i, $typeOfCoverOptions);
                        }
                    }
                } elseif ($isMotorTradersInternal) {
                    // For MOTORTRADERSINTERNAL, add dropdown for Type Of Cover
                    $typeOfCoverOptions = ['Comprehensive', 'Third party only', 'Third party fire and theft'];
                    
                    // Get headers to find column positions
                    $coverageType = $this->getCoverageType();
                    $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
                    $headers = config("{$configPath}.headers", []);
                    
                    // Find column index for Type Of Cover
                    $typeOfCoverColumn = $this->getColumnLetter(array_search('Type Of Cover', $headers));
                    
                    // Add dropdowns for data rows (starting from row 2)
                    // Add dropdowns for entire column (starting from row 2, up to row 1000 to cover all potential rows)
                    $maxRows = max($highestRow, 1000); // At least 1000 rows, or more if data exists
                    for ($i = 2; $i <= $maxRows; $i++) {
                        if ($typeOfCoverColumn) {
                            ExportTrait::generateDropDown($event, $typeOfCoverColumn . $i, $typeOfCoverOptions);
                        }
                    }
                } else {
                    // For other coverages, use the original dropdown logic
                    // Generate dropdowns only for data rows (starting from row 2, skipping header row 1)
                    // But limit to actual data rows to avoid adding dropdowns to blank rows
                    $riskAddress = $this->getRiskAddress();
                    $vehicles = $this->getVehicles();
                    $members = $this->getBeneficiaries();
                    $devices = $this->getDevices();
                    
                    // Start from row 2 (skip header), go up to the highest row with data
                    for ($i = 2; $i <= $highestRow; $i++){
                        ExportTrait::generateDropDown($event, 'A' . $i, $riskAddress);
                        ExportTrait::generateDropDown($event, 'B' . $i, $vehicles);
                        ExportTrait::generateDropDown($event, 'C' . $i, $members);
                        ExportTrait::generateDropDown($event, 'D' . $i, $devices);
                    }
                }
            },
        ];
    }
    
    /**
     * Convert column index (0-based) to Excel column letter (A, B, C, etc.)
     */
    private function getColumnLetter($index)
    {
        if ($index === false || $index === null || $index < 0) {
            return null;
        }
        // Convert 0-based index to Excel column letter using Coordinate class
        // 0 = A, 1 = B, ..., 25 = Z, 26 = AA, etc.
        return Coordinate::stringFromColumnIndex($index + 1);
    }


    public function getRiskAddress(){
        return RiskAddress::select('id','address_name')->Policy($this->policy->id)->term($this->termId)->action($this->actionId)->get()
            ->pluck('address_name','id')->toArray();
    }

    public function getVehicles(){
        return Vehicle::select('vehiclePlate','id')->PolicyId($this->policy->id)->TermId($this->termId)->ActionId($this->actionId)
            ->get()->pluck('vehiclePlate','id')->toArray();
    }

    
    public function getDevices(){
        return PolicyCellPhone::Policy($this->policy->id)->term($this->termId)->action($this->actionId)->get()
            ->pluck('device_type','id')->toArray();
    }

    public function getBeneficiaries(){
        return PolicyBeneficiary::selectRaw("id,concat(first_name,' ',middle_name,' ',last_name) as name")->Policy($this->policy->id)->term($this->termId)->action($this->actionId)->get()
            ->pluck('name','id')->toArray();
    }
}
