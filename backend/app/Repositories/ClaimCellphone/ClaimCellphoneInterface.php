<?php namespace AlphaDirect\Repositories\ClaimCellphone;

interface ClaimCellphoneInterface
{
    public function all();

    public function add_new_claim_cellphone($data);

    public function update(array $data, $id);

    public function delete($id);

    public function show($id);
}