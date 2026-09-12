<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ClaimQuote extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'claim_quotes';

    public function supplier()
    {
        return $this->hasOne('AlphaDirect\Supplier', 'id', 'supplier_id')->select(array('id', 'supplierName'));
    }

    public function repair_center()
    {
        return $this->hasOne('AlphaDirect\RepairCenter', 'id', 'supplier_id')->select(array('id', 'name'));

    }
}
