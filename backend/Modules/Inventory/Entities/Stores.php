<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class Stores extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    public function generateTags(): array
    {
         return [
             'Store'.' '.$this->auditEvent,
         ];
    }

    public function transformAudit(array $data): array
    {
        if (Arr::has($data, 'new_values.agent_id')) {
            $data['user_id'] = $data['new_values']['agent_id'];
        }
        if (Arr::has($data, 'new_values.user_id')) {
            $data['user_id'] = $data['new_values']['user_id'];
        }
        return $data;
    }

	use SoftDeletes;
    protected $fillable = [
		'partner_id','warehouses_id','city','name','status','state_id','contact_person',
		'email_id','mobile','min_inventory','max_inventory','appearance_order','address','created_at'
	];

    protected $table ="stores";

    protected static function newFactory()
    {
        return \Modules\Inventory\Database\factories\StoresFactory::new();
    }

	public function partner(){
		return $this->belongsTo(\Modules\Inventory\Entities\Partners::class);
	}

	public function cities(){
		return $this->belongsTo(\AlphaDirect\City::class, 'city');
	}

	public function state(){
		return $this->belongsTo(\AlphaDirect\State::class, 'state_id');
	}


	public function inventory()
    {
        return $this->hasMany(\Modules\Inventory\Entities\StoresInventory::class,'store_id','id');
    }

	public function wireHouse(){
		return $this->belongsTo(\Modules\Inventory\Entities\WareHouse::class, 'warehouses_id');

	}

}
