<?php

namespace AlphaDirect\Repositories\PolicyBeneficiary;

interface PolicyBeneficiaryInterface
{

    public function add_new_policy_beneficiary($attributes);

    public function get_policy_beneficiary_by_id($id);

    public function update_policy_beneficiary_by_id($id, $attributes);

}