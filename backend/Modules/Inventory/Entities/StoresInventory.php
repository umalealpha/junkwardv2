<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class StoresInventory extends Model
{
    use HasFactory;
	use SoftDeletes;
    protected $fillable = [
		'store_id',
		'plan_name',
		'plan_id',
		'product_name',
		'product_id',
		'max_inventory',
		'min_inventory',
		'counter',
        'created_at',
		'updated_at'
	];
    protected $table = "stores_inventories";

    public function generateTags(): array
    {
        return [
            'Store Inventory'.' '.$this->auditEvent,
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
        return \Modules\Inventory\Database\factories\StoresInventoryFactory::new();
    }

	public function product(){
		return $this->belongsTo(\AlphaDirect\Product::class);
	}

	public function plan(){
		return $this->belongsTo(\AlphaDirect\Productplan::class);
	}

	public function store(){
		return $this->belongsTo(\Modules\Inventory\Entities\Stores::class);
	}
}
