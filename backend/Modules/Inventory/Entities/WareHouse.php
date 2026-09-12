<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;

class WareHouse extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

	use SoftDeletes;
    protected $fillable = [
		"name",
		"status",
		"contact_person",
		"email_id",
		"mobile",
		"address"
	];

    protected $table ="warehouses";

    public function generateTags(): array
    {
         return [
             'Warehouse '.' '.$this->auditEvent,
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
        return \Modules\Inventory\Database\factories\WareHouseFactory::new();
    }

	public function inventory()
    {
        return $this->hasMany(\Modules\Inventory\Entities\WireHouseInventory::class,'warehouse_id','id');
    }
}
