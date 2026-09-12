<?php

namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\Http\Traits\Excel\ExportTrait;
use AlphaDirect\Models\Motor;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class CommercialMotorExport implements WithTitle, FromCollection, WithStyles, WithColumnWidths, WithEvents
{
    public $policy;
    public $termId;
    public $actionId;
    public $maxLength = 100;

    public function __construct($policy, $termId, $actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return "Commercial Motor";
    }

    public function collection()
    {
        // Get coverage type configuration dynamically
        $coverageType = 'COMMERCIALMOTOR';
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
        
        $headers = config("{$configPath}.headers");
        $defaultValues = config("{$configPath}.default_value");
        $fieldMappings = config("{$configPath}.field_mappings", []);
    
        // Start with only headers, don't pre-fill with default values
        $data = [$headers];          
        $rowIndex = 0; // Current row index (0 = header, 1+ = data rows)

        // Get policy coverages with motor relationship and risk address
        $policyCoverages = PolicyCoverage::where('policy_id', $this->policy->id)
            ->where('term_id', $this->termId)
            ->where('action_id', $this->actionId)
            ->with(['motor', 'riskAddress'])
            ->get();

        foreach ($policyCoverages as $policyCoverage) {
            // Process motor records
            if (!empty($fieldMappings['motor']) && $policyCoverage->motor->count() > 0) {
                foreach ($policyCoverage->motor as $key => $motor) {
                    // Skip soft-deleted records
                    if ($motor->deleted_at) {
                        continue;
                    }
                                                                      
                    $rowIndex++;
                    // Initialize row with default values
                    $data[$rowIndex] = $defaultValues;
                    $this->mapMotorDataToRow($data, $rowIndex, $headers, $fieldMappings['motor'], $motor, $policyCoverage, $key === 0);
                }
            }
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
        
        return new Collection($finalData);
    }
    
    /**
     * Map motor data to row based on field mappings
     */
    private function mapMotorDataToRow(&$data, $rowIndex, $headers, $mappings, $motor, $policyCoverage, $isFirstInGroup)
    {
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
                $value = $motor->$fieldPath ?? null;
                $data[$rowIndex][$columnIndex] = $value ?? '';
            }
        }
    }

    public function styles(Worksheet $sheet)
    {
        $coverageType = 'COMMERCIALMOTOR';
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
        return config("{$configPath}.style", config('constants.excel.edit_policy.coverage.OTHER.style'));
    }
    
    public function columnWidths(): array
    {
        $coverageType = 'COMMERCIALMOTOR';
        $configPath = "constants.excel.edit_policy.coverage.{$coverageType}";
        return config("{$configPath}.column_widths", config('constants.excel.edit_policy.coverage.OTHER.column_widths'));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Get the actual number of rows in the sheet (header + data rows)
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Generate dropdowns only for data rows (starting from row 2, skipping header row 1)
                // But limit to actual data rows to avoid adding dropdowns to blank rows
                $riskAddress = $this->getRiskAddress();
                
                // Start from row 2 (skip header), go up to the highest row with data
                for ($i = 2; $i <= $highestRow; $i++) {
                    ExportTrait::generateDropDown($event, 'A' . $i, $riskAddress);
                }
            },
        ];
    }

    public function getRiskAddress()
    {
        return RiskAddress::select('id', 'address_name')
            ->Policy($this->policy->id)
            ->term($this->termId)
            ->action($this->actionId)
            ->get()
            ->pluck('address_name', 'id')
            ->toArray();
    }
}
