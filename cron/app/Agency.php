<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Agency extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'agencies';
    protected $fillable = ['name'];

    public static function store($data){
        try{
            if(isset($data['status']) && $data['status'] == 1)
                $data['status'] = 1;
            else
                $data['status'] = 0;

            $exist = Agency::where('name',$data['name'])->exists();

            if($exist == false)
                $agency = new Agency();
            else
                return ['Response' => 'error', 'Message' => 'Agency already exists'];

            $agency->name = $data['name'];
            $agency->status = $data['status'];
            $agency->save();

            return ['Response'=>'success','Message'=>'Agency added successfully'];
        }catch(\Exception $e){
            return ['Response'=>'error','Message'=>$e->getMessage()];
        }
    }

    public static function getAgencies($column,$value){
        try{
            $agency = Agency::where($column,$value)->get(array('id','name'));
            return ['Response'=>'success','Agencies'=>$agency];
        }catch(\Exception $e){
            return ['Response'=>'error','Message'=>$e->getMessage()];
        }
    }
}
