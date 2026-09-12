<?php

namespace AlphaDirect\Repositories\CustomerBanking;

interface CustomerBankingInterface
{
    public function add_new_customer_banking($attributes);

    public function get_customer_banking_by_id($id);

    public function update_customer_banking_by_id($id, $attributes);




}