<?php

namespace AlphaDirect\Imports;

use AlphaDirect\PolicyBeneficiary;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Validators\Failure;

class MemberImport implements ToModel,WithHeadingRow,WithValidation,SkipsOnFailure
{
    use Importable;
    public $policy;
    public $termId;
    public $actionId;

    public function  __construct($policy,$termId,$actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;

        HeadingRowFormatter::default('none');
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        if (!isset($this->policy->id)) {
            return null;
        }
        $policy_beneficiary = new PolicyBeneficiary();
        $policy_beneficiary->policy_id = $this->policy->id;
        $policy_beneficiary->term_id = $this->termId;
        $policy_beneficiary->action_id = $this->actionId;
        $policy_beneficiary->first_name = $row['First Name'];
        $policy_beneficiary->middle_name = $row['Middle Name'];
        $policy_beneficiary->last_name = $row['Last Name'];
        $policy_beneficiary->relation = $row['Relation'];
        $policy_beneficiary->gender = $row['Gender'];
        $policy_beneficiary->payment = $row['Payment'];
        $policy_beneficiary->omang = $row['Omang'];
        $policy_beneficiary->passport = $row['Passport'];
        $policy_beneficiary->dob = \Carbon::parse($row['Date Of Birth'])->format('Y-m-d');
        return $policy_beneficiary;
    }
    public function rules(): array
    {
        return [
            "First Name" => 'required',
            "Middle Name" => 'required',
            "Last Name"  => 'required',
//            "Relation" => 'required',
//            "Gender" => 'required',
//            "Payment" => 'required',
//            "Omang" => "required_if:Passport,==,''",
//            "Passport" => "required_if:Omang,==,''",
//            "Date Of Birth" => ['required','date_format:d-m-Y']
        ];
    }

    public function onFailure(Failure ...$failures)
    {
        // TODO: Implement onFailure() method.
    }
}
