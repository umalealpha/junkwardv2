<?php

namespace AlphaDirect\Imports;

use AlphaDirect\City;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\State;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Validators\Failure;

class RiskAddressImport implements ToModel,WithHeadingRow,WithValidation,SkipsOnFailure,WithMultipleSheets
{
    use Importable;
    // Vendor trait: ModelImporter calls rememberRowNumber($index) before each
    // model() invocation (detected via method_exists), giving us the real
    // Excel row number (header = row 1) via getRowNumber() below — used so
    // data-lookup problems (unknown city/district) are reported against the
    // correct row instead of only being knowable inside model()'s $row array.
    use RemembersRowNumber;

    public $policy;
    public $termId;
    public $actionId;

    /**
     * Collected row errors (all-or-nothing report), mirroring
     * SpecifiedItemsImport's $errors/getErrors()/addError() pattern. Covers
     * both rules()-validation failures (via onFailure()) and data-lookup
     * failures (unknown Risk City / Risk District) detected in model().
     * The controller reads these after the import runs; if any exist it
     * rolls the transaction back and returns them to the operator instead of
     * persisting a partial import.
     *
     * @var array<int, array{row:int,message:string}>
     */
    protected $errors = [];

    /** @return array<int, array{row:int,message:string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** Record a row error (row is skipped by the caller) instead of aborting the import. */
    private function addError(int $rowNumber, string $message): void
    {
        $this->errors[] = [
            'row'     => $rowNumber,
            'message' => $message,
        ];
    }

    /**
     * Bind this importer to the FIRST sheet only.
     *
     * Without WithMultipleSheets, the vendor Reader assigns the same import
     * instance to EVERY sheet in the workbook (Reader::loadSpreadsheet(),
     * "no multiple sheets" branch: array_fill(0, sheetCount, $import)). The
     * template built by RiskAddressTemplateExport now always puts the
     * "Risk Address" data sheet at index 0 and a hidden reference-options
     * sheet (RiskAddressReferenceOptionsSheetExport, title
     * "RiskAddressRefOptions") at index 1 for the dropdown lists. That
     * reference sheet has no "Address Name" column, so — without this —
     * every one of its rows fails the required-field rule below and rolls
     * back the whole import, even though the data sheet was valid.
     *
     * Index-based binding (rather than matching on
     * RiskAddressDataSheet::title()) is used deliberately: it is robust to
     * ANY extra/stray sheet an operator's workbook might contain, and it
     * still works if the operator renames the "Risk Address" tab (name
     * matching would break on a rename, whereas position 0 is a template
     * contract we control). It also matches older, single-sheet templates
     * already in operators' hands — there is no sheet 1, so only sheet 0
     * is read either way.
     */
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    /**
     * Canonical headings as produced by RiskAddressDataSheet::headings(), keyed by a
     * normalized (trimmed / whitespace-collapsed / lower-cased) form for lookup.
     */
    private const CANONICAL_HEADINGS = [
        'address name' => 'Address Name',
        'lat' => 'lat',
        'lng' => 'lng',
        'physical address' => 'Physical Address',
        'risk district' => 'Risk District',
        'risk city' => 'Risk City',
        'extension' => 'Extension',
        'occupation' => 'Occupation',
        'town class' => 'Town Class',
        'risk class' => 'Risk Class',
        'iso rcv' => 'ISO RCV',
        'year built' => 'Year Built',
        'area' => 'Area',
        'structure type' => 'Structure Type',
        'construction type' => 'Construction Type',
        'distance to water' => 'Distance To Water',
        'distance to fire' => 'Distance To Fire',
        'distance to hydrant' => 'Distance To Hydrant',
        'usage' => 'Usage',
        'occupancy type' => 'Occupancy Type',
        'central fire alarm' => 'Central Fire Alarm',
        'central burglar alarm' => 'Central Burglar Alarm',
        'gated community' => 'Gated Community',
        'automatic sprinklers' => 'Automatic Sprinklers',
    ];

    public function  __construct($policy,$termId,$actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;

        // Header text often drifts from the template (extra/leading spaces, different
        // case, non-breaking spaces, collapsed double-spaces) when operators copy/paste
        // or re-type column headers. Normalize + map back to the canonical template
        // heading (see RiskAddressDataSheet::headings()) so model()/rules() below keep
        // reading the exact keys they already expect.
        HeadingRowFormatter::extend('risk_address', function ($value, $key) {
            if (empty($value)) {
                return $key;
            }

            return self::normalizeHeading((string) $value);
        });
        HeadingRowFormatter::default('risk_address');
    }

    /**
     * Normalize a header cell (trim, collapse internal whitespace, convert
     * non-breaking spaces to regular spaces) and map it back to the exact
     * canonical heading text when it matches one of the template's known
     * columns (case-insensitively). Unknown headers are returned normalized
     * (but not lower-cased) so the 'ID'/'Id'/'id' style variants read
     * elsewhere in model() keep working.
     */
    private static function normalizeHeading(string $value): string
    {
        // Convert non-breaking space (U+00A0) and narrow no-break space (U+202F)
        // to a regular space before collapsing whitespace.
        $normalized = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        $normalized = trim($normalized);

        return self::CANONICAL_HEADINGS[mb_strtolower($normalized)] ?? $normalized;
    }

    /**
     * Helper method to convert Yes/No text to 1/0
     */
    private function convertYesNoToBoolean($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $lowerValue = strtolower(trim($value));
        if (in_array($lowerValue, ['yes', 'y', '1', 'true'])) {
            return 1;
        } elseif (in_array($lowerValue, ['no', 'n', '0', 'false'])) {
            return 0;
        }

        // If value is already numeric, keep it as is
        return is_numeric($value) ? (int)$value : null;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        if (!isset($this->policy->id) or !isset($this->policy->customer_id)) {
            return null;
        }
        
        // Normalize address name first
        $addressName = trim($row['Address Name'] ?? '');
        
        if (empty($addressName)) {
            return null;
        }
        
        // Get state and city IDs
        $stateId = null;
        $cityId = null;
        
        // Priority: City-based state mapping
        // If city is provided, always map state from city's relationship in states and cities table
        // Ignore district value from Excel when city is provided
        if (!empty($row['Risk City'])) {
            // Search city by name and automatically get its state (ignore district from Excel)
            $city = City::with('state')->where('name', $row['Risk City'])->first();
            
            if (!$city) {
                // Collect the error (row is skipped) instead of throwing. A
                // throw here would previously only surface in AfterImport —
                // AFTER the vendor Reader's internal transaction had already
                // committed earlier valid rows (Reader.php:108-137) — making
                // "nothing was imported" false. Collecting instead lets the
                // controller inspect getErrors() once the whole file has
                // been read and roll back atomically if anything is wrong,
                // exactly like SpecifiedItemsImport.
                $cityName = $row['Risk City'];
                $errorMessage = "City '{$cityName}' does not exist (Risk Address: {$addressName})";

                \Log::error($errorMessage, [
                    'city' => $cityName,
                    'address_name' => $addressName,
                    'row' => $row
                ]);

                $this->addError($this->getRowNumber() ?? 0, $errorMessage);

                // Return null to skip this row
                return null;
            }
            
            // Always set city ID and map state from city's relationship (source of truth)
            // This replaces/overrides any district value from Excel
            $cityId = $city->id;
            if ($city->state) {
                $stateId = $city->state->id;
            }
        } else {
            // If no city provided, check if district is provided and validate it
            $riskDistrict = $row['Risk District'] ?? $row['Risk State'] ?? null;
            if (!empty($riskDistrict)) {
                $state = State::select('id')->where('country_id', 28)->where('name', $riskDistrict)->first();
                if (!$state) {
                    // Collected (not thrown) so this behaves identically to the
                    // unknown-city case above — previously this branch threw
                    // inline, which aborted the whole row loop immediately
                    // while the city branch kept going and only surfaced its
                    // error post-commit in AfterImport. Both lookup failures
                    // now take the same collect-and-skip path.
                    $errorMessage = "District '{$riskDistrict}' does not exist for address '{$addressName}'";
                    \Log::error($errorMessage, [
                        'district' => $riskDistrict,
                        'address_name' => $addressName,
                        'row' => $row
                    ]);
                    $this->addError($this->getRowNumber() ?? 0, $errorMessage);
                    return null;
                }
                $stateId = $state->id;
            }
        }
        
        // Track by ID - if ID is provided and exists, update that record
        $id = $row['ID'] ?? $row['id'] ?? $row['Id'] ?? null;
        $risk_address = null;
        
        // First, try to find by ID if provided
        if (!empty($id) && is_numeric($id)) {
            // Find existing record by ID, scoped to this policy/term/action (each
            // action owns its own risk_address rows — see PolicyAction::replicateRecords —
            // so an ID must still belong to the CURRENT action, not just the policy).
            $risk_address = RiskAddress::where('id', $id)
                ->Policy($this->policy->id)
                ->Term($this->termId)
                ->Action($this->actionId)
                ->first();
        }

        // If not found by ID, try to match by address_name, scoped to this policy/term/action
        // (to update the existing row for the CURRENT action instead of creating a duplicate,
        // and without grabbing another action's row that happens to share the same address_name).
        if (!$risk_address && !empty($addressName)) {
            $risk_address = RiskAddress::Policy($this->policy->id)
                ->Term($this->termId)
                ->Action($this->actionId)
                ->AddressName($addressName)
                ->first();
        }
        
        // If still not found, create new record
        if (!$risk_address) {
            $risk_address = new RiskAddress();
            $risk_address->policy_id = $this->policy->id;
            $risk_address->customer_id = $this->policy->customer_id;
        }
        
        // Update all fields (for both new and existing records)
        $risk_address->term_id = $this->termId;
        $risk_address->action_id = $this->actionId;
        $risk_address->address_name = $addressName;
        $risk_address->lat = $row['lat'] ?? null;
        $risk_address->lng = $row['lng'] ?? null;
        $risk_address->physical_address = $row['Physical Address'] ?? null;
        $risk_address->risk_state = $stateId; // State mapped from city if city is provided
        $risk_address->risk_city = $cityId; // City ID
        $risk_address->extension = $row['Extension'] ?? null;
        $risk_address->occupation = $row['Occupation'] ?? null;
        $risk_address->town_class = $row['Town Class'] ?? null;
        $risk_address->risk_class = $row['Risk Class'] ?? null;
        $risk_address->iso_rcv = $row['ISO RCV'] ?? null;
        $risk_address->year_built = $row['Year Built'] ?? null;
        $risk_address->area = $row['Area'] ?? null;
        $risk_address->structure_type = $row['Structure Type'] ?? null;
        $risk_address->const_type = $row['Construction Type'] ?? null;
        $risk_address->distance_to_water = $row['Distance To Water'] ?? null;
        $risk_address->distance_to_fire = $row['Distance To Fire'] ?? null;
        $risk_address->distance_to_hydrant = $row['Distance To Hydrant'] ?? null;
        $risk_address->usage = $row['Usage'] ?? null;
        $risk_address->occupancy_type = $row['Occupancy Type'] ?? null;
        // Convert Yes/No text to 1/0 for boolean fields
        $risk_address->central_fire = $this->convertYesNoToBoolean($row['Central Fire Alarm'] ?? null);
        $risk_address->central_burglar = $this->convertYesNoToBoolean($row['Central Burglar Alarm'] ?? null);
        $risk_address->gated_community = $this->convertYesNoToBoolean($row['Gated Community'] ?? null);
        $risk_address->automatic = $this->convertYesNoToBoolean($row['Automatic Sprinklers'] ?? null);
        
        return $risk_address;
    }


    public function rules(): array
    {
        return [
            'Address Name' => 'required',
//            'lat' => 'required',
//            'lng' => 'required',
//            'Extension' => ['required','in:Block 5,Newstance,Ntshe'],
//            'Occupation' => ['required',"in:Abbatoirs,Commercial Office Building"],
//            'Town Class' => ['required',"in:High,Medium,Low"],
//            'Risk Class' => ['required',"in:High,Medium,Low"],
//            'ISO RCV' => 'required',
//            'Year Built' => 'required',
//            'Area' => 'required',
//            'Structure Type' => ['required',"in:Apartment,Commercial Office Building"],
//            'Construction Type' => ['required',"in:Aluminium Siding,Frame,Frame/Hardiplank"],
//            'Distance To Water' => 'required',
//            'Distance To Fire' => 'required',
//            'Distance To Hydrant' => 'required',
//            'Usage' => ['required',"in:Administrative Office,Distribution Center,Manufacturing (Light),Manufacturing (Heavy),Retail (FMGG),Retail (High Value),Stock Yard"],
//            'Occupancy Type' => ['required',"in:Owner,Tenant,Unocc,Vacant"],
//            'Central Fire Alarm' => ['required',"boolean"],
//            'Central Burglar Alarm' => ['required',"boolean"],
//            'Gated Community' => ['required',"boolean"],
//            'Automatic Sprinklers' => ['required',"in:Partial or None,Full incl Bath/Attic etc"],
        ];
    }

    /**
     * Collect rules()-validation failures (e.g. blank "Address Name") instead
     * of silently dropping them. Mirrors SpecifiedItemsImport's addError()
     * usage: the row is still skipped (vendor behaviour, since this class
     * implements SkipsOnFailure), but the operator now gets a message naming
     * the exact Excel row and field instead of the row vanishing with zero
     * feedback. $failure->row() is the real Excel row number (header = row 1
     * — see Row::getIndex() in vendor ModelImporter), so no off-by-one here.
     */
    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $errorMessage = "{$failure->attribute()}: " . implode(' ', $failure->errors());

            \Log::warning('Risk Address Import - row validation failure', [
                'row'       => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors'    => $failure->errors(),
            ]);

            $this->addError($failure->row(), $errorMessage);
        }
    }
}
