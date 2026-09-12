<?php namespace AlphaDirect\Repositories\PolicyCellPhone;

use AlphaDirect\PolicyCellPhone;

class PolicyCellPhoneRepository implements PolicyCellPhoneInterface
{
    // policyCellPhone property on class instances
    protected $policy_cell_phone;

    // Constructor to bind policyCellPhone to repo
    public function __construct( PolicyCellPhone $policy_cell_phone )
    {
        $this->policy_cell_phone = $policy_cell_phone;
    }


    public function add_new_policy_cell_phone($attributes)
    {

        $policy_cell_phone = new $this->policy_cell_phone;
        foreach($attributes as $key => $value) {
            $policy_cell_phone->$key = $value;
        }
        return $policy_cell_phone->save();
    }

    public function get_policy_cell_phone_by_id($id) {
        return $this->policy_cell_phone->where('id',$id)->first();
    }

    public function update_policy_cell_phone_by_id($id, $attributes) {
        $policy_cell_phone = $this->get_policy_cell_phone_by_id($id);
        foreach($attributes as $key => $value) {
            $policy_cell_phone->$key = $value;
        }
        return $policy_cell_phone->save();
    }

}