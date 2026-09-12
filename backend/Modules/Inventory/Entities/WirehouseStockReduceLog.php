<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
class WirehouseStockReduceLog extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
	protected $table ="warehouse_stock_reduce_log";
    protected $fillable = [
		'warehouses_id',
		'product_id','plan_id',
		'reason','availabe_stock','damaged_stock','transfer_warehouses_id','user_id'
	];
	
}
