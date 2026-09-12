<?php

namespace AlphaDirect\Imports\Sheets;

use AlphaDirect\Models\Company;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageEntity;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeSheet;

class RiskAddressImport implements ToModel,WithHeadingRow,WithEvents
{
    public Policy $policy;

    public function __construct($policy,$termId,$actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    public function model(array $row)
    {
        $company = Company::Name($row['associated_company'])->SubCompanyOnly()->Activated()->first();
        if ($company){
            $riskAddress = RiskAddress::firstOrNew(['address_name' => $row['address'],'company_id'=>$company->id]);
            $riskAddress->physical_address = $row['physical_address'];
            $riskAddress->policy_id = $this->policy->id;
            $riskAddress->term_id = $this->termId;
            $riskAddress->action_id = $this->actionId;
            $riskAddress->customer_id = $this->policy->customer_id;

            $riskAddress->save();
        }
    }

    public function registerEvents(): array
    {
        return [
            // Removed deletion logic - now using updateOrCreate instead of delete/recreate
            // BeforeSheet::class => function (BeforeSheet $event) {
            //     PolicyCoverage::Policy($this->policy->id)->Term($this->termId)->Action($this->actionId)->get()->each(function($policyCoverage) {
            //         $policyCoverage->delete();
            //     });
            // }
        ];
    }
}
