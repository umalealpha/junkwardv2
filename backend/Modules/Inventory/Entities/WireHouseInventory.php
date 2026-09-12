<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
class WireHouseInventory extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

	use SoftDeletes;
    protected $fillable = [
		'warehouse_id',
		'plan_id',
		'product_id',
		'max_inventory',
		'min_inventory',
	];
    protected $table = "warehouses_inteventories";

    public function generateTags(): array
    {
        return [
            'Warehouse Inventory'.' '.$this->auditEvent,
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

    protected static function newFactory()
    {
        return \Modules\Inventory\Database\factories\WireHouseInventoryFactory::new();
    }

	public function product(){
		return $this->belongsTo(\AlphaDirect\Product::class);
	}
	
	public function plan(){
		return $this->belongsTo(\AlphaDirect\Productplan::class);
	}
	
	public function wirehouse(){
		return $this->belongsTo(\AlphaDirect\WareHouse::class,'warehouse_id');
	}
}
