<?php

namespace AlphaDirect\Repositories\CustomerKyc;

interface CustomerKycInterface
{
    public function add_new_customer_kyc($attributes);

    public function get_customer_kyc_by_id($id);

    public function update_customer_kyc_by_id($id, $attributes);

    public function get_customer_kyc_by_token($id);

    public function update_customer_kyc_by_token($id, $attributes);
}