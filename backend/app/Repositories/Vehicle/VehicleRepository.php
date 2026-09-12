<?php
namespace AlphaDirect\Repositories\Vehicle;

use AlphaDirect\Vehicle;

class VehicleRepository implements VehicleInterface
{
    // model property on class instances
    protected $vehicle;

    // Constructor to bind model to repo
    public function __construct(Vehicle $vehicle)
    {
        $this->vehicle = $vehicle;
    }

    // Get all instances of model
    public function all()
    {
        return $this->vehicle->all();
    }

    // create a new record in the database
    //public function create($data)
    public function add_new_vehicle($data)
    {
        $vehicle = new $this->vehicle;
        foreach($data as $key => $value) {
            $vehicle->$key = $value;
        }
        $vehicle->save();
        //dd($customer->customer_id);
        //return $customer->id;
        //return $this->claimCellphone->where(['first_name' => $first_name, 'last_name' => $last_name, 'mobile_number' => $mobile_number, 'country_code' => $country_code, 'pincode' => $pincode, 'address' => $address, 'landmark' => $landmark, 'city' => $city, 'state' => $state, 'country' => $country])->first();
    }

    public function get_vehicle_by_id($id) {
        return $this->vehicle->where('id',$id)->first();
    }

    // update record in the database
    public function update_vehicle_by_id($id,$attributes)
    {
        /*$record = $this->find($id);
        return $record->update($data);*/
        $vehicle = $this->get_customer_by_id($id);
        foreach($attributes as $key => $value) {
            $vehicle->$key = $value;
        }
        $vehicle->save();
        //return $customer;
    }

    // remove record from the database
    public function delete($id)
    {
        return $this->vehicle->destroy($id);
    }

    // show the record with the given id
    public function show($id)
    {
        return $this->vehicle-findOrFail($id);
    }

    // Get the associated model
    public function getModel()
    {
        return $this->vehicle;
    }

    // Set the associated model
    public function setModel($model)
    {
        $this->vehicle = $model;
        return $this;
    }

    // Eager load database relationships
    public function with($relations)
    {
        return $this->vehicle->with($relations);
    }
}