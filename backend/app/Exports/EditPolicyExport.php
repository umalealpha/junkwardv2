<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Exports\Sheets\ApplicantInformation;
use AlphaDirect\Exports\Sheets\CoverageExport;
use AlphaDirect\Exports\Sheets\SubCompanyExport;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use AlphaDirect\Exports\Sheets\RiskAddressExport;

class EditPolicyExport implements WithMultipleSheets
{
    public Policy $policy;
    public $termId;
    public $actionId;

    public function  __construct($policy,$termId,$actionId)
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
        $sheets[] = new ApplicantInformation($this->policy);
        $sheets[] = new SubCompanyExport($this->policy);
        $sheets[] = new RiskAddressExport($this->policy, $this->termId, $this->actionId);
        $policyCoverages = $this->getPolicyCoverages();
       
        $coverageWisePolicyCoverages = [];
        $coverageCodeMapping = []; // Map uppercase codes to actual case codes
        
        foreach ($policyCoverages as $index =>  $policyCoverage){
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
            'GOODSINTRANSIT',
            'FIDELITYGUARANTEE',
            'COMMERCIALMOTOR',
            'MOTORTRADERSEXTERNAL',
            'MOTORTRADERSINTERNAL'
        ];
        
        // First, add existing coverage sheets (that are not in required list)
        // Exclude MOTOR as it is handled separately (COMMERCIALMOTOR is now handled by CoverageExport)
        $excludedCoverages = ['MOTOR'];
        foreach ($coverageWisePolicyCoverages as $coverageCodeUpper =>  $policyCoverages){
            // Only add if not in required list and not excluded (excluded ones are handled separately)
            if (!in_array($coverageCodeUpper, $requiredCoverages) && !in_array($coverageCodeUpper, $excludedCoverages)) {
                $actualCoverageCode = isset($coverageCodeMapping[$coverageCodeUpper]) 
                    ? $coverageCodeMapping[$coverageCodeUpper] 
                    : $coverageCodeUpper;
                $sheets[] = new CoverageExport($this->policy,$this->termId,$this->actionId,$actualCoverageCode,$policyCoverages);
            }
        }
        
        // Then, add required coverages in specified order (with data if exists, otherwise empty)
        foreach ($requiredCoverages as $coverageCode) {
            $coverageCodeUpper = strtoupper($coverageCode);
            $policyCoverages = isset($coverageWisePolicyCoverages[$coverageCodeUpper]) 
                ? $coverageWisePolicyCoverages[$coverageCodeUpper] 
                : [];
            $sheets[] = new CoverageExport($this->policy,$this->termId,$this->actionId,$coverageCode,$policyCoverages);
        }
        return $sheets;
    }
    public function getPolicyCoverages(){
        $policyCoverages = $this->policy->PolicyCoverage()
            ->Policy($this->policy->id)
            ->Term($this->termId)
            ->Action($this->actionId)
            ->with([
                'coverage:id,s_CoverageCode',
                'riskAddress:id,address_name',
                'coverageDetail.coverage:id,s_ScreenName',
                'specifedItems',
                'entities',
                'motor',
                'motorExteranal',
                'motorInternal'
            ])
            ->get();
        
        // Log FIRE coverage details
        foreach ($policyCoverages as $policyCoverage) {
            if ($policyCoverage->coverage_id == 1) {
                \Log::info('FIRE Coverage Export - PolicyCoverage Found', [
                    'policy_id' => $this->policy->id,
                    'term_id' => $this->termId,
                    'action_id' => $this->actionId,
                    'policy_coverage_id' => $policyCoverage->id,
                    'coverage_id' => $policyCoverage->coverage_id,
                    'coverage_code' => $policyCoverage->coverage->s_CoverageCode ?? 'N/A',
                    'risk_address_id' => $policyCoverage->risk_address_id,
                    'risk_address_name' => $policyCoverage->riskAddress->address_name ?? 'N/A',
                    'coverageDetail_count' => $policyCoverage->coverageDetail ? $policyCoverage->coverageDetail->count() : 0,
                    'coverageDetail_ids' => $policyCoverage->coverageDetail ? $policyCoverage->coverageDetail->pluck('id')->toArray() : [],
                ]);
            }
        }
        
        return $policyCoverages;
    }
}
