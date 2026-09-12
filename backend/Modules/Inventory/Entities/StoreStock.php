<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
class StoreStock extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    public function generateTags(): array
    {
         return [
             'Store Stock'.' '.$this->auditEvent,
         ];
    }

    public function transformAudit(array $data): array
    {
        if (Arr::has($data, 'new_values.store_inteventories_id')) {
            $left_stock=StoresInventory::where('id',$data['new_values']['store_inteventories_id'])->first('counter');
            $data['new_values']['stock_left'] = $left_stock->counter;
            $data['stock_left'] = $data['new_values']['stock_left'];
        }
        if (Arr::has($data, 'new_values.agent_id')) {
             $data['user_id'] = $data['new_values']['agent_id'];
        }
        if (Arr::has($data, 'new_values.user_id')) {
            $data['user_id'] = $data['new_values']['user_id'];
        }
         return $data;
    }

    protected $fillable = [
		'store_id','store_inteventories_id','stock','stock_add_remove','user_id','stock_left'
	];

    protected $table ="store_stock";
    protected static function newFactory()
    {
        return \Modules\Inventory\Database\factories\StoreStockFactory::new();
    }
}
