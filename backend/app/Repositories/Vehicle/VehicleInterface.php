<?php namespace AlphaDirect\Repositories\Vehicle;

interface VehicleInterface
{
    public function all();

    //public function create($data);
    public function add_new_vehicle($data);

    public function get_vehicle_by_id($id);

    public function update_vehicle_by_id($id,$attributes);

    public function delete($id);

    public function show($id);
}