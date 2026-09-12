<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
class WirehouseStock extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = [
		'warehouses_inteventories_id',
		'stock',
		'stock_add_remove','user_id'
	];

    public function generateTags(): array
    {
        return [
            'Warehouse Stock'.' '.$this->auditEvent,
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
        return \Modules\Inventory\Database\factories\WirehouseStockFactory::new();
    }
}
