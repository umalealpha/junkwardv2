<?php

namespace AlphaDirect\Repositories\Policy;

interface PolicyInterface
{

    public function add_new_policy($attributes);

    public function get_policy_by_id($id);

    public function update_policy_by_id($id, $attributes);

}