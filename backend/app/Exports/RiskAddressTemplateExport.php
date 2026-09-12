<?php

namespace AlphaDirect\Exports;

use AlphaDirect\City;
use AlphaDirect\Exports\Sheets\RiskAddressDataSheet;
use AlphaDirect\Exports\Sheets\RiskAddressReferenceOptionsSheetExport;
use AlphaDirect\Lookup;
use AlphaDirect\Policy;
use AlphaDirect\State;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * RiskAddressTemplateExport
 *
 * Exports ONLY the Risk Address sheet with all columns (for bulk import template).
 * This is simpler than EditPolicyExport which includes many other sheets.
 */
class RiskAddressTemplateExport implements WithMultipleSheets
{
    public Policy $policy;
    public $termId;
    public $actionId;

    public function __construct($policy, $termId = null, $actionId = null)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
    }

    /**
     * Return sheets array: the Risk Address data sheet plus a hidden lookup
     * sheet feeding its reference-column dropdowns (District, City,
     * Extension, Occupation, Structure Type, Construction Type, Usage,
     * Occupancy Type). Lists are sourced from the SAME data the app's
     * risk-address form uses (AlphaDirect\State / AlphaDirect\City,
     * scoped to country_id 28, and AlphaDirect\Lookup by key) so the
     * template matches what RiskAddressImport actually accepts.
     */
    public function sheets(): array
    {
        $referenceOptions = new RiskAddressReferenceOptionsSheetExport($this->buildReferenceOptions());

        return [
            new RiskAddressDataSheet($this->policy, $this->termId, $this->actionId, $referenceOptions),
            $referenceOptions,
        ];
    }

    /**
     * Build the option lists for each reference dropdown.
     *
     * District/City mirror RiskAddressImport's validation exactly:
     *  - District = AlphaDirect\State.name where country_id = 28
     *  - City = AlphaDirect\City.name for cities under those states
     *    (import validates by City.name only, but scoping to Botswana's
     *    states here keeps the template's list to the values that will
     *    actually resolve to a usable state on import).
     *
     * Construction Type / Structure Type / Occupation / Occupancy Type /
     * Extension mirror the lookup_data rows served by
     * RiskAddressController::getConstructionTypes/getStructureTypes/
     * getOccupationTypes/getOccupancyTypes/getExtensions.
     *
     * Usage mirrors RiskAddressController::getUsageTypes(), which is a
     * static list (not backed by lookup_data) — kept in sync manually.
     *
     * @return array<string, string[]>
     */
    protected function buildReferenceOptions(): array
    {
        return [
            'district' => State::where('country_id', 28)
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'city' => City::whereHas('state', fn ($q) => $q->where('country_id', 28))
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'construction_type' => $this->lookupValues('risk_construction_type'),
            'structure_type' => $this->lookupValues('risk_structure_type'),
            'occupation' => $this->lookupValues('risk_occupation'),
            'occupancy_type' => $this->lookupValues('risk_occupancy_type'),
            'extension' => $this->lookupValues('extensions'),
            // Static list — see RiskAddressController::getUsageTypes().
            'usage' => [
                'Administrative Office',
                'Distribution Center',
                'Manufacturing (Light)',
                'Manufacturing (Heavy)',
                'Retail (FMGG)',
                'Retail (High Value)',
                'Stock Yard',
            ],
        ];
    }

    /**
     * @return string[]
     */
    protected function lookupValues(string $key): array
    {
        return Lookup::where('key', $key)
            ->pluck('value')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
