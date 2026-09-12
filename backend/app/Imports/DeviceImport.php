<?php

namespace AlphaDirect\Imports;

use AlphaDirect\DeviceMakeModel;
use Illuminate\Validation\Rule;
use AlphaDirect\PolicyCellPhone;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Validators\Failure;

class DeviceImport implements ToModel,WithHeadingRow,WithValidation,SkipsOnFailure
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
        if (!isset($this->policy->id) or !isset($this->policy->customer_id)) {
            return null;
        }
        $policy_cell_phone = new PolicyCellPhone();

        $policy_cell_phone->policy_id = $this->policy->id;
        $policy_cell_phone->customer_id = $this->policy->customer_id;
        $policy_cell_phone->term_id = $this->termId;
        $policy_cell_phone->action_id = $this->actionId;
        $policy_cell_phone->device_type = $row['Device Type'];
        $policy_cell_phone->imei = $row['IMEI'];
        $policy_cell_phone->cell_phone_make = $row['Make'];
        $policy_cell_phone->cell_phone_model = $row['Model'];
        $policy_cell_phone->phone_value = $row['Phone Value'];

        return $policy_cell_phone;
    }

    public function rules(): array
    {
        return [
            'Device Type' => Rule::in(['Cellphone','Tablet','Laptop']),
//            'IMEI' => 'required',
//            'Make' => 'required',
//            'Model' => 'required',
//            'Phone Value' => 'required',
        ];
    }

    public function onFailure(Failure ...$failures)
    {
        // TODO: Implement onFailure() method.
    }
}
