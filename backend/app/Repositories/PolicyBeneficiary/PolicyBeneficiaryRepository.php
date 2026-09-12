<?php namespace AlphaDirect\Repositories\PolicyBeneficiary;

use AlphaDirect\PolicyBeneficiary;

class PolicyBeneficiaryRepository implements PolicyBeneficiaryInterface
{
    // policy_beneficiaryCellPhone property on class instances
    protected $policy_beneficiary;

    // Constructor to bind policy_beneficiaryCellPhone to repo
    public function __construct( PolicyBeneficiary $policy_beneficiary )
    {
        $this->policy_beneficiary = $policy_beneficiary;
    }

    public function add_new_policy_beneficiary($attributes)
    {

        $policy_beneficiary = new $this->policy_beneficiary;
        foreach($attributes as $key => $value) {
            $policy_beneficiary->$key = $value;
        }
        $policy_beneficiary->save();
        return $policy_beneficiary;
    }

    public function get_policy_beneficiary_by_id($id) {
        return $this->policy_beneficiary->where('policy_beneficiary_id',$id)->first();
    }

    public function update_policy_beneficiary_by_id($id, $attributes) {
        $policy_beneficiary = $this->get_policy_beneficiary_by_id($id);
        foreach($attributes as $key => $value) {
            $policy_beneficiary->$key = $value;
        }
        $policy_beneficiary->save();
        return $policy_beneficiary;
    }

}