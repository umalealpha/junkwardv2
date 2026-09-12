<?php namespace AlphaDirect\Repositories\Customer;

interface CustomerInterface
{
    public function add_new_customer($data);

    public function get_customer_by_id($id);

    public function update_customer_by_id($id,$attributes);
}