<?php

namespace AlphaDirect\Imports\Sheets;

use AlphaDirect\Models\Company;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMappedCells;

class ApplicantInformationImport implements ToModel,WithMappedCells
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
        $this->policy->profile->entity_type = $row['entity_type'];
        if ($row['entity_type'] == "Organisation"){
            $company = $this->policy->profile->company ?? new Company();
            $company->name = $row['company_name'] ?? '';
            $company->postal_address = $row['postal_address'];
            $company->company_registration_number = $row['company_registration_number'] ?? '';
            $company->VAT_registration_number = $row['VAT_registration_number'] ?? '';
            $company->contact_person = $row['contact_person'] ?? '';
            $company->contact_person_number = $row['contact_person_number'] ?? '';
            $company->primary_email = $row['primary_email'] ?? '';
            $company->secondary_email = $row['secondary_email'] ?? '';
            $company->save();
            $this->policy->profile->company_id = $company->id;
        }
        $this->policy->profile->save();
    }

    public function mapping(): array
    {
        return [
            'entity_type'  => 'C3',
            'company_name' => 'C4',
            'postal_address' => 'C5',
            'company_registration_number' => 'C6',
            'VAT_registration_number' => 'C7',
            'contact_person' => 'C8',
            'contact_person_number' => 'C9',
            'primary_email' => 'C10',
            'secondary_email' => 'C11',
        ];
    }
}
