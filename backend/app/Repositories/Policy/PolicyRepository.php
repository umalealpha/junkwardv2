<?php namespace AlphaDirect\Repositories\Policy;

use AlphaDirect\Policy;

class PolicyRepository implements PolicyInterface
{
    // policyCellPhone property on class instances
    protected $policy;

    // Constructor to bind policyCellPhone to repo
    public function __construct( Policy $policy )
    {
        $this->policy = $policy;
    }


    public function add_new_policy($attributes)
    {

        $policy = new $this->policy;
        foreach($attributes as $key => $value) {
            $policy->$key = $value;
        }
        $policy->save();
        return $policy;
    }

    public function get_policy_by_id($id) {
        return $this->policy->where('policy_id',$id)->first();
    }

    public function update_policy_by_id($id, $attributes) {
        $policy = $this->get_policy_by_id($id);
        foreach($attributes as $key => $value) {
            $policy->$key = $value;
        }
        $policy->save();
        return $policy;
    }

}