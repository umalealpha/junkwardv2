<?php
namespace AlphaDirect\Repositories\Customer;

use AlphaDirect\Customer;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Input\Input;

class CustomerRepository implements CustomerInterface
{
    // model property on class instances
    protected $customer;

    // Constructor to bind model to repo
    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    // Get all instances of model
    public function all()
    {
        return $this->customer->all();
    }

    // create a new record in the database


    public function add_new_customer($data)
    {
        $customer = new $this->customer;
        foreach($data as $key => $value) {
            $customer->$key = $value;
        }
        $customer->save();
        return $customer->id;

    }
    // update record in the database
    public function get_customer_by_id($id) {
        return $this->customer->where('id',$id)->first();
    }

    // update record in the database
    public function update_customer_by_id($id,$attributes)
    {
        $customer = $this->get_customer_by_id($id);
        foreach($attributes as $key => $value) {
            if($value != NULL || $value != ''){
                $customer->$key = $value;
            }
        }
        $customer->save();
        return $customer->id;
    }
}
