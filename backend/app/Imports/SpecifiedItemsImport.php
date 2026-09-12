<?php

namespace AlphaDirect\Imports;

use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use DB;

class SpecifiedItemsImport implements WithEvents, WithHeadingRow, OnEachRow
{
    public $policy;
    public $termId;
    public $actionId;
    
    public $riskAddressAndId = [];
    public $coverageCodeAndId = [];
    public $specifiedItemsAndId = [];
    
    // Cache for PolicyCoverage by risk address and coverage combination
    public $policyCoverageCache = [];
    
    // Current sheet name (coverage code)
    public $currentSheetName = null;

    // Track imported items to detect duplicates within same file
    public $importedItems = [];

    public function __construct($policy, $termId, $actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
        
        // Keep original column names (matching export format)
        HeadingRowFormatter::default('none');

        // Load risk addresses (trim names to handle whitespace differences)
        $this->riskAddressAndId = RiskAddress::select('id', 'address_name')
            ->Policy($this->policy->id)
            ->Term($this->termId)
            ->Action($this->actionId)
            ->get()
            ->mapWithKeys(function ($address) {
                return [$this->cleanWhitespace($address->address_name) => $address->id];
            })
            ->toArray();

        // Load coverage codes (normalise whitespace to handle differences)
        $this->coverageCodeAndId = CoverageMaster::select('id', 's_CoverageCode', 's_ScreenName')
            ->get()
            ->mapWithKeys(function ($coverage) {
                return [
                    $this->cleanWhitespace($coverage->s_CoverageCode) => $coverage->id,
                    $this->cleanWhitespace($coverage->s_ScreenName) => $coverage->id,
                ];
            })
            ->toArray();

        // Load all specified items (will be filtered by coverage later)
        // Trim names to handle whitespace differences in database
        $allSpecifiedItems = SpecifiedCoveragesItems::EffectiveItemOnly()
            ->get()
            ->mapWithKeys(function ($item) {
                return [$this->cleanWhitespace($item->specified_name) => $item->id];
            })
            ->toArray();

        $this->specifiedItemsAndId = $allSpecifiedItems;
    }

    /**
     * Collected per-row validation errors (all-or-nothing report). The
     * controller reads these after the import runs; if any exist it rolls the
     * transaction back and returns them grouped by sheet/coverage instead of
     * persisting a partial import. Each entry:
     *   ['sheet' => coverageCode, 'row' => rowNumber, 'message' => text]
     *
     * @var array<int, array{sheet:?string,row:int,message:string}>
     */
    public $errors = [];

    /** @return array<int, array{sheet:?string,row:int,message:string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** Record a row error (and skip the row) instead of aborting the import. */
    private function addError(int $rowNumber, string $message): void
    {
        $this->errors[] = [
            'sheet'   => $this->currentSheetName,
            'row'     => $rowNumber,
            'message' => $message,
        ];
    }

    /**
     * Runs for each Excel row
     */
    public function onRow(Row $row)
    {
        $rowData = $row->toArray();

        // Debug logging
        \Log::info('Specified Items Import Row', [
            'row_data' => $rowData,
            'row_number' => $row->getIndex(),
            'sheet_name' => $this->currentSheetName,
            'keys' => array_keys($rowData)
        ]);

        // Skip empty rows
        if (empty(array_filter($rowData))) {
            \Log::info('Skipping empty row', ['row_number' => $row->getIndex()]);
            return;
        }

        // Skip if currentSheetName is not set (empty sheet was detected)
        if (!$this->currentSheetName) {
            \Log::warning('Skipping row - currentSheetName not set', [
                'row_number' => $row->getIndex(),
                'row_data' => $rowData
            ]);
            return;
        }

        // Match header columns case-insensitively and ignoring surrounding
        // spaces, so a header like "SUM INSURED" or "Sum Insured " still maps
        // correctly (headers are kept verbatim by HeadingRowFormatter 'none').
        // Build a normalised (trim + lowercase) key => value map of the row,
        // then look each column up by any of its accepted aliases (aliases are
        // normalised the same way, so case/spacing on either side is ignored).
        $normalizedRow = [];
        foreach ($rowData as $key => $value) {
            $normalizedRow[strtolower($this->cleanWhitespace($key))] = $value;
        }
        $pick = function (array $aliases) use ($normalizedRow) {
            foreach ($aliases as $alias) {
                $n = strtolower($this->cleanWhitespace($alias));
                if (array_key_exists($n, $normalizedRow)
                    && $normalizedRow[$n] !== null
                    && $this->cleanWhitespace($normalizedRow[$n]) !== '') {
                    return $normalizedRow[$n];
                }
            }
            return null;
        };

        // Normalise whitespace (incl. NBSP) so Excel values like " Main Office "
        // or text pasted from Word still match saved risk addresses.
        $riskAddressValue = $this->cleanWhitespace($pick(['Risk Address', 'risk_address', 'RiskAddress']) ?? '');
        // Get coverage code from sheet name (current sheet)
        $coverageCodeValue = $this->cleanWhitespace($this->currentSheetName);
        // Header is now "Item Description"; the old "Description Of Items"
        // variants are kept as fallbacks so previously-downloaded templates
        // still import. Normalise so extra/NBSP spaces do not affect matching.
        $descriptionOfItems = $this->cleanWhitespace($pick([
            'Item Description', 'item_description', 'ItemDescription',
            'Description Of Items', 'description_of_items', 'DescriptionOfItems',
            'item',
        ]) ?? '');
        $sumInsured = $pick(['Sum Insured', 'sum_insured', 'SumInsured', 'sum_insured_amount']) ?? 0;
        // Status column (added by the export). Blank or "Active" keeps the item
        // active; "Deactive" soft-deletes it. Old templates without the column
        // default to blank => active.
        $statusValue = $this->cleanWhitespace($pick(['Status', 'status']) ?? '');

        \Log::info('Parsed row values', [
            'risk_address' => $riskAddressValue,
            'coverage_code' => $coverageCodeValue,
            'description_of_items' => $descriptionOfItems,
            'sum_insured' => $sumInsured,
            'status' => $statusValue,
            'sheet' => $this->currentSheetName
        ]);

        // Validate required fields
        if (empty($riskAddressValue)) {
            $errorMessage = "Risk address is blank (required). Sheet: {$this->currentSheetName}, Row: " . ($row->getIndex());
            \Log::error($errorMessage, ['row' => $rowData, 'sheet' => $this->currentSheetName, 'row_number' => $row->getIndex()]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }

        if (empty($descriptionOfItems)) {
            $errorMessage = "Item Description is blank for risk address '{$riskAddressValue}'. Sheet: {$this->currentSheetName}, Row: " . ($row->getIndex());
            \Log::error($errorMessage, [
                'risk_address' => $riskAddressValue,
                'row' => $rowData, 
                'sheet' => $this->currentSheetName,
                'row_number' => $row->getIndex()
            ]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }

        // Validate sum insured is not blank or zero
        if (empty($sumInsured) || $sumInsured == 0 || trim($sumInsured) === '') {
            $errorMessage = "Sum Insured is blank for risk address '{$riskAddressValue}'. Sheet: {$this->currentSheetName}, Row: " . ($row->getIndex());
            \Log::error($errorMessage, [
                'risk_address' => $riskAddressValue,
                'sum_insured' => $sumInsured,
                'row' => $rowData, 
                'sheet' => $this->currentSheetName,
                'row_number' => $row->getIndex()
            ]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }

        // Validate sum insured is a positive numeric value (no negatives, no non-numeric text)
        $normalizedSumInsured = $this->normalizeNumericString($sumInsured);
        if ($normalizedSumInsured === '' || (float) $normalizedSumInsured <= 0) {
            $errorMessage = "Sum Insured must be a positive number (got '{$sumInsured}') for risk address '{$riskAddressValue}' under coverage '{$coverageCodeValue}'. Sheet: {$this->currentSheetName}, Row: " . ($row->getIndex());
            \Log::error($errorMessage, [
                'risk_address' => $riskAddressValue,
                'sum_insured' => $sumInsured,
                'row' => $rowData,
                'sheet' => $this->currentSheetName,
                'row_number' => $row->getIndex()
            ]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }

        // Validate risk address exists
        if (!isset($this->riskAddressAndId[$riskAddressValue])) {
            $errorMessage = "Risk address '{$riskAddressValue}' does not exist for this policy. Sheet: {$this->currentSheetName}, Row: " . ($row->getIndex());
            \Log::error($errorMessage, [
                'risk_address' => $riskAddressValue, 
                'sheet' => $this->currentSheetName,
                'row_number' => $row->getIndex(),
                'available_risk_addresses' => array_keys($this->riskAddressAndId)
            ]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }

        $riskAddressId = $this->riskAddressAndId[$riskAddressValue];

        // Validate coverage code exists in CoverageMaster
        if (!isset($this->coverageCodeAndId[$coverageCodeValue])) {
            $errorMessage = "Coverage code '{$coverageCodeValue}' does not exist. Sheet: {$this->currentSheetName}, Row: " . ($row->getIndex());
            \Log::error($errorMessage, ['coverage_code' => $coverageCodeValue, 'sheet' => $this->currentSheetName]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }

        $coverageId = $this->coverageCodeAndId[$coverageCodeValue];

        // Get or create PolicyCoverage (this will create it if it doesn't exist)
        $policyCoverage = $this->getPolicyCoverage($riskAddressId, $coverageId);

        // Validate specified item exists for this coverage
        $coverage = CoverageMaster::find($coverageId);
        if (!$coverage) {
            $this->addError($row->getIndex(), "Coverage '{$coverageCodeValue}' not found.");
            return;
        }

        $specifiedItemsForCoverage = $coverage->specifiedCoverages()
            ->EffectiveItemOnly()
            ->get()
            ->mapWithKeys(function ($item) {
                return [$this->cleanWhitespace($item->specified_name) => $item->id];
            })
            ->toArray();

        if (!isset($specifiedItemsForCoverage[$descriptionOfItems])) {
            $errorMessage = "Specified item '{$descriptionOfItems}' does not exist for coverage '{$coverageCodeValue}'";
            \Log::error($errorMessage, [
                'description_of_items' => $descriptionOfItems,
                'coverage_code' => $coverageCodeValue
            ]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }

        $specifiedCoverageId = $specifiedItemsForCoverage[$descriptionOfItems];

        // CRITICAL: Check for duplicate item within the same import file (prevents duplicate rows in same Excel file)
        $dupeKey = "{$riskAddressId}_{$specifiedCoverageId}_{$coverageId}";
        if (isset($this->importedItems[$dupeKey])) {
            $errorMessage = "Duplicate in import file: '{$descriptionOfItems}' already appears for risk address '{$riskAddressValue}' under coverage '{$coverageCodeValue}'. Row: " . ($row->getIndex());
            \Log::error($errorMessage, [
                'risk_address' => $riskAddressValue,
                'description' => $descriptionOfItems,
                'coverage_code' => $coverageCodeValue,
                'row_number' => $row->getIndex()
            ]);
            $this->addError($row->getIndex(), $errorMessage);
            return;
        }
        $this->importedItems[$dupeKey] = true;

        // Note: Existing items in database will be updated via updateOrCreate (not an error condition)
        // This allows re-importing the same items to update their values (sum_insured, rate, etc.)

        // Convert sum_insured to numeric
        $sumInsuredNumeric = $this->removeCommasAndConvertToNumeric($sumInsured);

        // Always fetch rate from specified item (not from Excel)
        $specifiedItem = SpecifiedCoveragesItems::find($specifiedCoverageId);
        $rate = $specifiedItem->rate ?? 0;

        // Calculate calculated_value automatically: (sum_insured * rate) / 100
        $calculatedValue = ($sumInsuredNumeric * $rate) / 100;

        // Desired active/soft-deleted state from the Status column: "Deactive"
        // soft-deletes the item; blank or "Active" keeps/reinstates it active.
        $deactivate = $this->isDeactiveStatus($statusValue);

        // Create or update PolicySpecifiedItem, then apply the requested status.
        // NOTE: delete()/restore() only manage the deleted_at flag (and update
        // stored values). Premium/endorsement recalculation is left to the
        // subsequent Rate step, exactly as for every other imported row — this
        // import sets stored values, it does not compute premium deltas.
        try {
            $specifiedItem = PolicySpecifiedItem::withTrashed()->updateOrCreate(
                [
                    'policy_coverage_id' => $policyCoverage->id,
                    'specified_coverage_id' => $specifiedCoverageId,
                ],
                [
                    'sum_insured' => $sumInsuredNumeric,
                    'rate' => $rate,
                    'calculated_value' => $calculatedValue,
                ]
            );

            if ($deactivate && !$specifiedItem->trashed()) {
                $specifiedItem->delete();   // soft-delete (stamps deleted_at)
            } elseif (!$deactivate && $specifiedItem->trashed()) {
                $specifiedItem->restore();  // reinstate (clears deleted_at)
            }

            \Log::info('Specified item saved successfully', [
                'policy_specified_item_id' => $specifiedItem->id,
                'policy_coverage_id' => $policyCoverage->id,
                'specified_coverage_id' => $specifiedCoverageId,
                'sum_insured' => $sumInsuredNumeric,
                'rate' => $rate,
                'calculated_value' => $calculatedValue,
                'status' => $deactivate ? 'Deactive' : 'Active',
                'deleted_at' => $specifiedItem->deleted_at,
                'sheet' => $this->currentSheetName
            ]);
        } catch (\Exception $e) {
            \Log::error('Error saving specified item', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'policy_coverage_id' => $policyCoverage->id,
                'specified_coverage_id' => $specifiedCoverageId,
                'row_data' => $rowData
            ]);
            // Collect instead of aborting — reported with all other row errors.
            $this->addError($row->getIndex(), "Could not save '{$descriptionOfItems}': " . $e->getMessage());
            return;
        }
    }

    /**
     * Get or create PolicyCoverage for the given risk address and coverage
     */
    private function getPolicyCoverage($riskAddressId, $coverageId)
    {
        $cacheKey = "{$riskAddressId}_{$coverageId}";
        
        if (isset($this->policyCoverageCache[$cacheKey])) {
            return $this->policyCoverageCache[$cacheKey];
        }

        $policyCoverage = PolicyCoverage::withTrashed()
            ->where('policy_id', $this->policy->id)
            ->where('term_id', $this->termId)
            ->where('action_id', $this->actionId)
            ->where('risk_address_id', $riskAddressId)
            ->where('coverage_id', $coverageId)
            ->first();

        if (!$policyCoverage) {
            // Create new PolicyCoverage using individual property assignment (not mass assignment)
            $policyCoverage = new PolicyCoverage();
            $policyCoverage->policy_id = $this->policy->id;
            $policyCoverage->term_id = $this->termId;
            $policyCoverage->action_id = $this->actionId;
            $policyCoverage->risk_address_id = $riskAddressId;
            $policyCoverage->coverage_id = $coverageId;
            $policyCoverage->save();
        } elseif ($policyCoverage->trashed()) {
            // Restore if soft-deleted
            $policyCoverage->restore();
        }

        $this->policyCoverageCache[$cacheKey] = $policyCoverage;
        return $policyCoverage;
    }

    /**
     * Convert a (possibly grouped) amount to a float. Returns 0 when the value
     * is blank or not a number — callers validate positivity separately.
     */
    private function removeCommasAndConvertToNumeric($value)
    {
        $normalized = $this->normalizeNumericString($value);
        return $normalized === '' ? 0.0 : (float) $normalized;
    }

    /**
     * Normalise a human-entered amount into a machine-parseable numeric string,
     * or '' if it is not a number.
     *
     * Handles both English (1,234,567.89) and European (1.234.567,89) grouping,
     * plus NBSP/space grouping. When both '.' and ',' are present the right-most
     * separator is treated as the decimal point and the other as the thousands
     * separator. A comma-only value keeps the legacy English/Botswana behaviour
     * (comma = thousands, so "1,000" => 1000), except a single comma followed by
     * one or two digits is read as a decimal ("1234,56" => 1234.56).
     */
    private function normalizeNumericString($value): string
    {
        $s = $this->cleanWhitespace($value);
        $s = str_replace(' ', '', $s); // drop any space grouping (e.g. "1 234")
        if ($s === '') {
            return '';
        }

        $hasComma = strpos($s, ',') !== false;
        $hasDot   = strpos($s, '.') !== false;

        if ($hasComma && $hasDot) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                // European: '.' thousands, ',' decimal
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                // English: ',' thousands, '.' decimal
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasComma) {
            if (preg_match('/^-?\d+,\d{1,2}$/', $s)) {
                $s = str_replace(',', '.', $s); // e.g. "1234,56" => decimal
            } else {
                $s = str_replace(',', '', $s);  // e.g. "1,234,567" => thousands
            }
        } elseif ($hasDot && substr_count($s, '.') > 1) {
            // Multiple dots, no comma => European thousands grouping (no decimals)
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? $s : '';
    }

    /**
     * Normalise text for matching: convert non-breaking (U+00A0) and other
     * unicode spaces to a regular space, collapse whitespace runs, and trim.
     * Excel values pasted from Word/web often contain NBSP, which plain trim()
     * leaves in place — causing spurious "does not exist" lookup misses when one
     * side of the comparison has it and the other does not. Applied to both the
     * DB-built lookup keys and the file values so both sides normalise alike.
     */
    private function cleanWhitespace($value): string
    {
        $s = (string) $value;
        $s = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /**
     * Whether a Status cell means "soft-delete this item". Only an explicit
     * deactive/inactive marker deactivates; blank or "Active" keeps the item
     * active (per the export's Active/Deactive dropdown).
     */
    private function isDeactiveStatus($status): bool
    {
        $s = strtolower($this->cleanWhitespace($status));
        return in_array($s, ['deactive', 'deactivate', 'deactivated', 'inactive', 'disabled'], true);
    }

    /**
     * Mirror of AlphaDirect\Exports\Sheets\SpecifiedItemsSheetExport::excelSheetTitle.
     * Excel caps tab titles at 31 chars and forbids : \ / ? * [ ]. The exporter
     * writes coverage codes as tab titles through this transform, so a code
     * longer than 31 chars is truncated. We re-apply the identical transform
     * here to match a truncated sheet title back to its full coverage code.
     * Keep in sync with the exporter.
     */
    private function excelSheetTitle(?string $code): string
    {
        $t = (string) ($code ?? '');
        $t = preg_replace('/[:\\\\\/?*\[\]]/u', ' ', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
        if ($t === '') {
            $t = 'NA';
        }
        return mb_substr($t, 0, 31);
    }

    /**
     * Register events to handle sheet names
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                try {
                    // Reset cache for each new sheet
                    $this->policyCoverageCache = [];
                    
                    // Get the sheet name (coverage code)
                    $sheetTitle = $event->getSheet()->getDelegate()->getTitle();
                    $this->currentSheetName = $sheetTitle;
                    
                    \Log::info('BeforeSheet event fired', [
                        'sheet_title' => $sheetTitle,
                        'policy_id' => $this->policy->id
                    ]);
                    
                    // Check if sheet has data rows (not just headers)
                    $sheet = $event->getSheet()->getDelegate();
                    $highestRow = $sheet->getHighestRow();
                    
                    \Log::info('Sheet row count check', [
                        'sheet' => $sheetTitle,
                        'highest_row' => $highestRow
                    ]);
                    
                    // If sheet only has header row (row 1) or is empty, skip processing
                    if ($highestRow <= 1) {
                        \Log::info('Skipping empty specified items sheet (only headers)', [
                            'sheet' => $sheetTitle,
                            'highest_row' => $highestRow
                        ]);
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
                                'sheet' => $sheetTitle,
                                'error' => $e->getMessage()
                            ]);
                        }
                        $this->currentSheetName = null;
                        return;
                    }
                    
                    // Validate coverage exists in CoverageMaster
                    if (!isset($this->coverageCodeAndId[$sheetTitle])) {
                        // Try case-insensitive lookup
                        $coverageCodeUpper = strtoupper($sheetTitle);
                        $found = false;
                        foreach ($this->coverageCodeAndId as $code => $id) {
                            if (strtoupper($code) === $coverageCodeUpper) {
                                $this->currentSheetName = $code; // Use the actual case from database
                                $found = true;
                                \Log::info('Found coverage with case-insensitive match', [
                                    'original_sheet' => $sheetTitle,
                                    'matched_code' => $code,
                                    'coverage_id' => $id
                                ]);
                                break;
                            }
                        }
                        if (!$found) {
                            // Excel caps tab names at 31 chars and forbids
                            // : \ / ? * [ ], so a coverage code that is too long
                            // or has an illegal char was exported as a sanitised/
                            // truncated title. Match the title against the SAME
                            // transform of each known code so those codes still
                            // round-trip back to their full DB value.
                            $titleTrunc = $this->excelSheetTitle($sheetTitle);
                            foreach ($this->coverageCodeAndId as $code => $id) {
                                if ($this->excelSheetTitle($code) === $titleTrunc) {
                                    $this->currentSheetName = $code; // full code from DB
                                    $found = true;
                                    \Log::info('Matched sanitised/truncated coverage sheet title', [
                                        'sheet' => $sheetTitle,
                                        'matched_code' => $code,
                                    ]);
                                    break;
                                }
                            }
                        }
                        if (!$found) {
                            \Log::warning('Coverage code not found for sheet, skipping', [
                                'sheet' => $sheetTitle,
                                'policy_id' => $this->policy->id,
                                'available_codes_sample' => array_slice(array_keys($this->coverageCodeAndId), 0, 10)
                            ]);
                            $this->currentSheetName = null;
                            return;
                        }
                    } else {
                        \Log::info('Coverage code found for sheet', [
                            'sheet' => $sheetTitle,
                            'coverage_id' => $this->coverageCodeAndId[$sheetTitle]
                        ]);
                    }
                    
                    \Log::info('Processing specified items sheet - ready for rows', [
                        'sheet_name' => $this->currentSheetName,
                        'policy_id' => $this->policy->id,
                        'highest_row' => $highestRow
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error in BeforeSheet event', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->currentSheetName = null;
                }
            }
        ];
    }
}

