<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
class StoresStockReduceLog extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
	protected $table ="stores_stock_reduce_log";
    protected $fillable = [
		'warehouses_id',
		'store_id',
		'reason','availabe_stock','damaged_stock','transfer_warehouses_id','user_id','transfer_store_id'
	];
	
}
