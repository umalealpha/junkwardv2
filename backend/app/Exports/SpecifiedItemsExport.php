<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Exports\Sheets\SpecifiedItemsSheetExport;
use AlphaDirect\Exports\Sheets\RiskAddressOptionsSheetExport;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\RiskAddress;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SpecifiedItemsExport implements WithMultipleSheets
{
    public $policy;
    public $termId;
    public $actionId;

    public function __construct($policy, $termId, $actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        $policyCoverages = $this->getPolicyCoverages();

        // Existing risk addresses for this policy/action — used to populate the
        // "Risk Address" dropdown on each coverage sheet so operators pick a
        // valid address instead of typing it (prevents name mismatches on
        // import). The hidden lookup sheet that holds them is appended LAST
        // (see end of this method) so a visible coverage sheet stays the active
        // tab — Excel errors if the active sheet is hidden.
        $riskAddresses = $this->getRiskAddressNames();
        
        // Group policy coverages by coverage code
        $coverageWisePolicyCoverages = [];
        $coverageCodeMapping = []; // Map uppercase codes to actual case codes
        
        foreach ($policyCoverages as $policyCoverage) {
            $coverageCode = $policyCoverage->coverage->s_CoverageCode;
            $coverageCodeUpper = strtoupper($coverageCode);
            
            // Store with original case, but also map uppercase for case-insensitive lookup
            if (!isset($coverageWisePolicyCoverages[$coverageCodeUpper])) {
                $coverageWisePolicyCoverages[$coverageCodeUpper] = [];
                $coverageCodeMapping[$coverageCodeUpper] = $coverageCode;
            }
            $coverageWisePolicyCoverages[$coverageCodeUpper][] = $policyCoverage;
        }
        
        // List of coverages that should always be included in export (even if no data)
        $requiredCoverages = [
            'FIRE',
            'MONEY',
            'THEFT',
            'WORKERSCOMPENSATION',
            'BUSINESSINTERUPTION',
            'BUSINESSALLRISKS',
            'GOODSINTRANSIT'
        ];
        
        // First, add existing coverage sheets (that are not in required list)
        foreach ($coverageWisePolicyCoverages as $coverageCodeUpper => $policyCoverages) {
            // Only add if not in required list (required ones will be added separately at the end)
            if (!in_array($coverageCodeUpper, $requiredCoverages)) {
                $actualCoverageCode = isset($coverageCodeMapping[$coverageCodeUpper]) 
                    ? $coverageCodeMapping[$coverageCodeUpper] 
                    : $coverageCodeUpper;
                
                // Check if any of these policy coverages have specified items
                $policyCoverageIds = collect($policyCoverages)->pluck('id')->toArray();
                $hasSpecifiedItems = \AlphaDirect\Models\PolicySpecifiedItem::whereIn('policy_coverage_id', $policyCoverageIds)->exists();
                
                // Only create sheet if there are specified items for this coverage
                if ($hasSpecifiedItems) {
                    $sheets[] = new SpecifiedItemsSheetExport(
                        $this->policy,
                        $this->termId,
                        $this->actionId,
                        $actualCoverageCode,
                        $policyCoverages,
                        $riskAddresses
                    );
                }
            }
        }
        
        // Then, add required coverages in specified order (with data if exists, otherwise empty)
        foreach ($requiredCoverages as $coverageCode) {
            $coverageCodeUpper = strtoupper($coverageCode);
            $policyCoverages = isset($coverageWisePolicyCoverages[$coverageCodeUpper]) 
                ? $coverageWisePolicyCoverages[$coverageCodeUpper] 
                : [];
            
            // Always create sheet for required coverages, even if empty
            $sheets[] = new SpecifiedItemsSheetExport(
                $this->policy,
                $this->termId,
                $this->actionId,
                $coverageCode,
                $policyCoverages,
                $riskAddresses
            );
        }

        // Hidden lookup sheet LAST — holds the risk-address options the
        // dropdowns reference. Added after the coverage sheets so the active
        // tab is a visible coverage sheet (Excel errors on a hidden active
        // sheet). The dropdown formula is resolved by Excel at open time, so
        // sheet order does not affect it.
        if (!empty($riskAddresses)) {
            $sheets[] = new RiskAddressOptionsSheetExport($riskAddresses);
        }

        return $sheets;
    }

    /**
     * Distinct, non-deleted risk-address names for this policy/action — the
     * options shown in the "Risk Address" dropdown on each coverage sheet.
     *
     * @return string[]
     */
    private function getRiskAddressNames(): array
    {
        return RiskAddress::where('policy_id', $this->policy->id)
            ->when($this->actionId, fn ($q) => $q->where('action_id', $this->actionId))
            ->whereNull('deleted_at')
            ->pluck('address_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get policy coverages for this policy, term, and action
     */
    private function getPolicyCoverages()
    {
        return PolicyCoverage::where('policy_id', $this->policy->id)
            ->where('term_id', $this->termId)
            ->where('action_id', $this->actionId)
            ->with([
                'coverage:id,s_CoverageCode',
                'riskAddress:id,address_name'
            ])
            ->get();
    }
}

