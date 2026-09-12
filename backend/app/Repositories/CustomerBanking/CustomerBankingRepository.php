<?php namespace AlphaDirect\Repositories\CustomerBanking;

use AlphaDirect\CustomerBanking;

class CustomerBankingRepository implements CustomerBankingInterface
{
    // policyCellPhone property on class instances
    protected $customer_banking;

    // Constructor to bind policyCellPhone to repo
    public function __construct( CustomerBanking $customer_banking)
    {
        $this->customer_banking = $customer_banking;
    }


    public function add_new_customer_banking($attributes)
    {
        $customer_banking = new $this->customer_banking;
        foreach($attributes as $key => $value) {
            $customer_banking->$key = $value;
        }
        $saved = $customer_banking->save();
        return $saved;
    }

    public function get_customer_banking_by_id($id) {
        return $this->customer_banking->where('customer_id',$id)->first();
    }

    public function update_customer_banking_by_id($id, $attributes) {
        $customer_banking = $this->get_customer_banking_by_id($id);
        foreach($attributes as $key => $value) {
            $customer_banking->$key = $value;
        }
        $saved = $customer_banking->save();
        return $saved;
    }

}