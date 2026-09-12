<?php

namespace AlphaDirect\Imports;

use AlphaDirect\Imports\Sheets\ApplicantInformationImport;
use AlphaDirect\Imports\Sheets\CoverageImport;
use AlphaDirect\Imports\Sheets\SubCompanyImport;
use AlphaDirect\Imports\Sheets\RiskAddressImport;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\PolicyCoverage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class TempImport implements ToCollection
{
    public function collection(Collection $collection)
    {
        // No action needed, we just need this to count sheets
    }
}

class EditPolicyImport implements WithMappedCells, ToModel, WithMultipleSheets, WithEvents, SkipsUnknownSheets
{
    public $policy;
    public $termId;
    public $actionId;
    public $availableSheetNames = [];

    public function __construct($policy, $termId, $actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
    }
    
    public $filePath = null;
    public $sheetDataCache = []; // Cache to store which sheets have data
    private $spreadsheetInstance = null; // Cache spreadsheet instance to avoid loading multiple times
    
    /**
     * Load sheet names from Excel file
     * This should be called before sheets() method
     */
    public function loadSheetNamesFromFile($filePath)
    {
        $this->filePath = $filePath;
        $this->sheetDataCache = []; // Reset cache when loading new file
        $this->spreadsheetInstance = null; // Reset spreadsheet instance
        
        if ($filePath && file_exists($filePath)) {
            try {
                $this->spreadsheetInstance = IOFactory::load($filePath);
                $this->availableSheetNames = $this->spreadsheetInstance->getSheetNames();
            } catch (\Exception $e) {
                \Log::warning('Could not load sheet names from file', [
                    'file' => $filePath,
                    'error' => $e->getMessage()
                ]);
                $this->availableSheetNames = [];
            }
        }
    }
    
    /**
     * Check if a sheet has actual data (not just headers)
     * Returns true if sheet has at least one data row beyond header
     */
    private function sheetHasData($sheetName)
    {
        // Cache the result to avoid reading the file multiple times
        if (isset($this->sheetDataCache[$sheetName])) {
            return $this->sheetDataCache[$sheetName];
        }
        
        if (empty($this->filePath) || !file_exists($this->filePath)) {
            // If we can't check, assume it has NO data to prevent empty sheets from being added
            // This prevents "beyond highest row" errors
            \Log::warning('Cannot check sheet data - file path not available, assuming empty', [
                'sheet' => $sheetName,
                'file_path' => $this->filePath
            ]);
            $this->sheetDataCache[$sheetName] = false;
            return false;
        }
        
        try {
            // Use cached spreadsheet instance if available, otherwise load it
            if (!$this->spreadsheetInstance) {
                $this->spreadsheetInstance = IOFactory::load($this->filePath);
            }
            
            // Find the sheet by name (case-insensitive)
            $targetSheet = null;
            foreach ($this->spreadsheetInstance->getSheetNames() as $name) {
                if (strcasecmp(trim($name), trim($sheetName)) === 0) {
                    $targetSheet = $this->spreadsheetInstance->getSheetByName($name);
                    break;
                }
            }
            
            if (!$targetSheet) {
                // Sheet not found, assume it has no data
                $this->sheetDataCache[$sheetName] = false;
                return false;
            }
            
            // Get the highest row and column
            $highestRow = $targetSheet->getHighestRow();
            $highestColumn = $targetSheet->getHighestColumn();
            
            // If sheet has only 1 row (header), it's blank
            if ($highestRow <= 1) {
                \Log::info('Sheet has only header row (empty)', [
                    'sheet' => $sheetName,
                    'highest_row' => $highestRow
                ]);
                $this->sheetDataCache[$sheetName] = false;
                return false;
            }
            
            // Convert column letter to number for proper iteration
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            
            // Check rows starting from row 2 (skip header row 1)
            // A row is considered to have data if at least one cell is not empty
            for ($row = 2; $row <= $highestRow; $row++) {
                for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $cellValue = $targetSheet->getCell($colLetter . $row)->getValue();
                    if ($cellValue !== null && trim($cellValue) !== '') {
                        // Found at least one non-empty cell in a data row
                        $this->sheetDataCache[$sheetName] = true;
                        return true;
                    }
                }
            }
            
            // No data found in any row beyond header
            \Log::info('Sheet has no data in rows (empty)', [
                'sheet' => $sheetName,
                'highest_row' => $highestRow
            ]);
            $this->sheetDataCache[$sheetName] = false;
            return false;
            
        } catch (\Exception $e) {
            \Log::warning('Could not check if sheet has data - assuming empty to prevent errors', [
                'sheet' => $sheetName,
                'file' => $this->filePath,
                'error' => $e->getMessage()
            ]);
            // On error, assume it has NO data to prevent empty sheets from causing import failures
            // This is safer than assuming it has data and causing "beyond highest row" errors
            $this->sheetDataCache[$sheetName] = false;
            return false;
        }
    }
    
    /**
     * Register events (required by WithEvents interface)
     */
    public function registerEvents(): array
    {
        return [];
    }

    /**
     * Mapping specific cells from Excel
     */
    public function mapping(): array
    {
        return [
            'customer_name'   => 'C9',
            'customer_number' => 'C10',
            'customer_email'  => 'C11',
        ];
    }

    /**
     * Update customer info from mapped cells
     */
    public function model($row)
    {
        if (!empty($row['customer_name']) || !empty($row['customer_email'])) {
            $this->policy->customer->firstName = $row['customer_name'] ?? $this->policy->customer->firstName;
            $this->policy->customer->email     = $row['customer_email'] ?? $this->policy->customer->email;
            $this->policy->customer->cellphone = $row['customer_number'] ?? $this->policy->customer->cellphone;
            $this->policy->customer->save();
        }
    }

    /**
     * Dynamically define sheets to import
     */
// public function sheets(): array // old code
//     {
//         $sheets[] = new ApplicantInformationImport($this->policy);
//         $sheets[] = new SubCompanyImport($this->policy);
//         $sheets[] = new RiskAddressImport($this->policy,$this->termId,$this->actionId);


//         $policyCoverages = $this->getPolicyCoverages();
//         $coverageWisePolicyCoverages = [];
//         foreach ($policyCoverages as $index =>  $policyCoverage){
//             $coverageWisePolicyCoverages[$policyCoverage->coverage->s_CoverageCode][] = $policyCoverage;
//         }
//         foreach ($coverageWisePolicyCoverages as $coverageCode =>  $policyCoverages){
//             $sheets[] = new CoverageImport($this->policy,$this->termId,$this->actionId,$coverageCode,$policyCoverages);
//         }
//         return $sheets;
//     }

public function sheets(): array
{
    // Fixed sheets first (always include these if they exist in Excel)
    $sheets = [];
    
    // Helper function to check if sheet exists (case-insensitive)
    $sheetExists = function($sheetName) {
        if (empty($this->availableSheetNames)) {
            return true; // If we couldn't load sheet names, include all (backward compatibility)
        }
        foreach ($this->availableSheetNames as $availableName) {
            if (strcasecmp(trim($availableName), trim($sheetName)) === 0) {
                return true;
            }
        }
        return false;
    };
    
    // Check if sheets exist in Excel file before adding them
    // Try multiple possible names for Applicant Information sheet
    $applicantSheetNames = ['Applicant Information', 'ApplicantInformation', 'Applicant'];
    $hasApplicantSheet = false;
    foreach ($applicantSheetNames as $name) {
        if ($sheetExists($name)) {
            $hasApplicantSheet = true;
            break;
        }
    }
    if ($hasApplicantSheet || empty($this->availableSheetNames)) {
        $sheets[] = new ApplicantInformationImport($this->policy);  // sheet 0
    }
    
    // Try multiple possible names for Sub Company sheet
    $subCompanySheetNames = ['Subsidiary Companies', 'Sub Company', 'SubCompany', 'Subsidiary'];
    $hasSubCompanySheet = false;
    foreach ($subCompanySheetNames as $name) {
        if ($sheetExists($name)) {
            $hasSubCompanySheet = true;
            break;
        }
    }
    if ($hasSubCompanySheet || empty($this->availableSheetNames)) {
        $sheets[] = new SubCompanyImport($this->policy);           // sheet 1
    }
    
    // Check Risk Address sheet
    if ($sheetExists('Risk Address') || empty($this->availableSheetNames)) {
        $sheets[] = new RiskAddressImport($this->policy, $this->termId, $this->actionId); // sheet 2
    }

    // Group policy coverages by coverage code
    $coverageWisePolicyCoverages = [];
    $existingCoverageCodes = [];
    foreach ($this->getPolicyCoverages() as $policyCoverage) {
        $coverageCode = $policyCoverage->coverage->s_CoverageCode;
        $coverageWisePolicyCoverages[$coverageCode][] = $policyCoverage;
        $existingCoverageCodes[] = $coverageCode;
    }
    
    // Identify fixed sheet names (non-coverage sheets)
    $fixedSheetNames = ['Applicant Information', 'ApplicantInformation', 'Applicant', 
                       'Subsidiary Companies', 'Sub Company', 'SubCompany', 'Subsidiary',
                       'Risk Address'];
    
    // Find new coverage sheets in Excel that don't exist in current policy coverages
    // These will be imported and PolicyCoverage will be created on-the-fly during import
    $newCoverageCodes = [];
    if (!empty($this->availableSheetNames)) {
        foreach ($this->availableSheetNames as $sheetName) {
            $sheetNameTrimmed = trim($sheetName);
            
            // Skip if it's a fixed sheet
            $isFixedSheet = false;
            foreach ($fixedSheetNames as $fixedName) {
                if (strcasecmp($sheetNameTrimmed, $fixedName) === 0) {
                    $isFixedSheet = true;
                    break;
                }
            }
            
            if ($isFixedSheet) {
                continue;
            }
            
            // Check if this sheet name matches a coverage code in CoverageMaster
            $coverageMaster = CoverageMaster::where('s_CoverageCode', $sheetNameTrimmed)
                ->orWhere('s_ScreenName', $sheetNameTrimmed)
                ->first();
            
            if ($coverageMaster) {
                $coverageCode = $coverageMaster->s_CoverageCode;
                // If this coverage doesn't exist in current policy coverages, it's a new one
                // But only add it if the sheet has actual data (not just headers)
                if (!in_array($coverageCode, $existingCoverageCodes)) {
                    // Check if this new coverage sheet has data before adding it
                    if ($this->sheetHasData($sheetNameTrimmed)) {
                        $newCoverageCodes[] = $coverageCode;
                        // Initialize empty array for this coverage - CoverageImport will create PolicyCoverage on-the-fly
                        $coverageWisePolicyCoverages[$coverageCode] = [];
                        $existingCoverageCodes[] = $coverageCode; // Add to prevent re-checking
                    } else {
                        \Log::info('Skipping blank new coverage sheet', [
                            'coverage_code' => $coverageCode,
                            'sheet_name' => $sheetNameTrimmed
                        ]);
                    }
                }
            }
        }
    }
    
    // Add coverage sheets dynamically - only if the sheet exists in Excel file AND has data
    foreach ($coverageWisePolicyCoverages as $coverageCode => $policyCoverages) {
        // Check if this coverage sheet exists in the Excel file (case-insensitive)
        if ($sheetExists($coverageCode)) {
            // Also check if the sheet has actual data (not just headers)
            // Find the actual sheet name (might have different case)
            $actualSheetName = null;
            
            if (!empty($this->availableSheetNames)) {
                foreach ($this->availableSheetNames as $availableName) {
                    if (strcasecmp(trim($availableName), trim($coverageCode)) === 0) {
                        $actualSheetName = $availableName;
                        break;
                    }
                }
            } else {
                // If availableSheetNames is empty, use coverageCode as sheet name
                // This happens when we can't load sheet names (backward compatibility)
                $actualSheetName = $coverageCode;
            }
            
            // Always check if sheet has data before adding it
            // This prevents empty sheets (header only) from being added
            if ($actualSheetName && !empty($this->filePath)) {
                $hasData = $this->sheetHasData($actualSheetName);
                
                if ($hasData) {
                    // Even if policyCoverages is empty (new coverage), still create the import
                    // CoverageImport will create PolicyCoverage on-the-fly when processing rows
                    $sheets[] = new CoverageImport(
                        $this->policy,
                        $this->termId,
                        $this->actionId,
                        $coverageCode,
                        $policyCoverages
                    );
                } else {
                    // Sheet exists but is blank (header only) - skip it and continue to next sheet
                    \Log::info('Skipping blank coverage sheet (header only)', [
                        'coverage_code' => $coverageCode,
                        'sheet_name' => $actualSheetName
                    ]);
                    // Continue to next iteration - don't add this sheet to import list
                    continue;
                }
            } else {
                // Cannot check sheet data (no file path or sheet name) - skip it to be safe
                \Log::info('Skipping coverage sheet - cannot verify data', [
                    'coverage_code' => $coverageCode,
                    'actual_sheet_name' => $actualSheetName,
                    'file_path_set' => !empty($this->filePath)
                ]);
                continue;
            }
        } else {
            // Sheet doesn't exist in Excel - skip it
            \Log::info('Coverage sheet does not exist in Excel file', [
                'coverage_code' => $coverageCode
            ]);
            continue;
        }
    }

    return $sheets;
}

    /**
     * Get all policy coverages
     */
    public function getPolicyCoverages()
    {
        return $this->policy->PolicyCoverage()
           // ->withTrashed() // commented by snehal on 6-01-26
            ->with([
                'coverage:id,s_CoverageCode',
                'riskAddress:id,address_name',
                'coverageDetail'
            ])
            ->get();
    }

    /**
     * Handle unknown sheets (sheets that exist in Excel but are not in our sheets() array)
     * This prevents Maatwebsite Excel from trying to process them and throwing exceptions
     */
    public function onUnknownSheet($sheetName)
    {
        \Log::info('Skipping unknown sheet (not in import list)', [
            'sheet' => $sheetName,
            'available_sheets' => $this->availableSheetNames
        ]);
        // Return null to skip this sheet - SkipsUnknownSheets will handle it
    }
}
