<?php
namespace AlphaDirect\Repositories\ClaimCellphone;

use AlphaDirect\ClaimCellphone;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Input\Input;

class ClaimCellphoneRepository implements ClaimCellphoneInterface
{
    // model property on class instances
    protected $claimCellphone;

    // Constructor to bind model to repo
    public function __construct(ClaimCellphone $claimCellphone)
    {
        $this->claimCellphone = $claimCellphone;
    }

    // Get all instances of model
    public function all()
    {
        return $this->claimCellphone->all();
    }

    // create a new record in the database


    public function add_new_claim_cellphone($data)
    {
        $claimCellphone = new $this->claimCellphone;
        foreach($data as $key => $value) {
            $claimCellphone->$key = $value;
        }
        $claimCellphone->save();
        return response()->json([$claimCellphone,'test'],401);
        return $claimCellphone->claim_id;
        //return $this->claimCellphone->where(['first_name' => $first_name, 'last_name' => $last_name, 'mobile_number' => $mobile_number, 'country_code' => $country_code, 'pincode' => $pincode, 'address' => $address, 'landmark' => $landmark, 'city' => $city, 'state' => $state, 'country' => $country])->first();
    }
    // update record in the database
    public function update(array $data, $id)
    {
        $record = $this->find($id);
        return $record->update($data);
    }

    // remove record from the database
    public function delete($id)
    {
        return $this->claimCellphone->destroy($id);
    }

    // show the record with the given id
    public function show($id)
    {
        return $this->claimCellphone-findOrFail($id);
    }

    // Get the associated model
    public function getModel()
    {
        return $this->claimCellphone;
    }

    // Set the associated model
    public function setModel($model)
    {
        $this->claimCellphone = $model;
        return $this;
    }

    // Eager load database relationships
    public function with($relations)
    {
        return $this->claimCellphone->with($relations);
    }
}