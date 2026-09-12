<?php
namespace AlphaDirect\Repositories\CustomerProfile;

interface CustomerProfileInterface
{
    public function all();

    public function add_new_customer_profile($data);

    public function get_customer_profile_by_id($id);

    public function update_customer_profile($id,$attributes);
}
