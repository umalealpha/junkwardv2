<?php
namespace AlphaDirect\Repositories\CustomerProfile;

use AlphaDirect\CustomerProfile;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Input\Input;

class CustomerProfileRepository implements CustomerProfileInterface
{
    // model property on class instances
    protected $customerProfile;

    // Constructor to bind model to repo
    public function __construct(CustomerProfile $customerProfile)
    {
        $this->customerProfile = $customerProfile;
    }

    // Get all instances of model
    public function all()
    {
        return $this->customerProfile->all();
    }

    // create a new record in the database

    public function add_new_customer_profile($data)
    {
        $customerProfile = new $this->customerProfile;
        foreach($data as $key => $value) {
            $customerProfile->$key = $value;
        }

        $customerProfile->save();
        return $customerProfile->customer_id;
        //return $customer->cust_id;
        //return $this->claimCellphone->where(['first_name' => $first_name, 'last_name' => $last_name, 'mobile_number' => $mobile_number, 'country_code' => $country_code, 'pincode' => $pincode, 'address' => $address, 'landmark' => $landmark, 'city' => $city, 'state' => $state, 'country' => $country])->first();
    }
    // get record in the database
    public function get_customer_profile_by_id($id) {
        return $this->customerProfile->where('customer_id',$id)->first();
    }

    // update record in the database
    public function update_customer_profile($id,$attributes)
    {
        $customerProfile = $this->get_customer_profile_by_id($id);
        if($customerProfile== null){
            $this->add_new_customer_profile($attributes);
        }else{
        foreach($attributes as $key => $value) {
            if($value != NULL || $value != '') {
                $customerProfile->$key = $value;
            }
        }
            $customerProfile->save();
        }
        //return $customer;
    }
}

