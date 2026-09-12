<?php

namespace AlphaDirect\Exports;
use AlphaDirect\DeviceMakeModel;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DeviceMakeModelExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
                                'ID',
                                'Device Make',
                                'Device Model',
                                'Device Type',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $deviceMakeModel = DeviceMakeModel::where('id',$id)->first();
        if($deviceMakeModel->id !=null){
            $id = $deviceMakeModel->id;
        }
        else{
            $id = 'N\A';
        }
        if($deviceMakeModel->id !=null){
            $divice_name = DeviceMakeModel::where('id',$deviceMakeModel->make_id)->first(array('name'));
            if($divice_name){
                $make_id = $divice_name->name;
            }
            else{
                $make_id = 'N\A';
            }
        }
        else{
            $make_id = 'N\A';
        }
        if($deviceMakeModel->id !=null){
            $name = $deviceMakeModel->name;
        }
        else{
            $name = 'N\A';
        }
        if($deviceMakeModel->id !=null){
            $deviceType = $deviceMakeModel->device_type;
        }
        else{
            $deviceType = 'N\A';
        }
        return [
            $id,
            $make_id,
            $name,
            $deviceType,
        ];

    }

    public function collection()
    {
        $query = DeviceMakeModel::orderBy('created_at', 'DESC');
        if ($this->filter['deviceTypeFilter'] != '-1' )
        {
            $query->where('device_type' , 'like', '%' . $this->filter['deviceTypeFilter'] . '%' );
        }
        $query = $query->get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
