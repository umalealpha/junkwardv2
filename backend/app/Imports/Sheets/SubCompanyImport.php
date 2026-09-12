<?php

namespace AlphaDirect\Imports\Sheets;

use AlphaDirect\Models\Company;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SubCompanyImport implements ToModel,WithHeadingRow
{

    public Policy $policy;

    public function __construct($policy)
    {
        $this->policy = $policy;
    }
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $company = Company::firstOrNew(['name' => $row['company_name'],'parent_id'=>$this->policy->profile->company->id]);
        $company->VAT_registration_number = $row['vat_registration_number'];
        $company->company_registration_number = $row['registration_number'];
        $company->save();
    }
}
