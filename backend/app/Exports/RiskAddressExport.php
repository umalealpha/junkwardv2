<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Exports\Sheets\RiskAddressDataSheet;
use AlphaDirect\Exports\Sheets\StatesCitiesReferenceSheet;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RiskAddressExport implements WithMultipleSheets
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
     * @return array
     */
    public function sheets(): array
    {
        return [
            new RiskAddressDataSheet($this->policy, $this->termId, $this->actionId),
            new StatesCitiesReferenceSheet(),
        ];
    }
}
