<?php

namespace AlphaDirect\Repositories\PolicyCellPhone;

interface PolicyCellPhoneInterface
{

    public function add_new_policy_cell_phone($attributes);

    public function get_policy_cell_phone_by_id($id);

    public function update_policy_cell_phone_by_id($id, $attributes);

}