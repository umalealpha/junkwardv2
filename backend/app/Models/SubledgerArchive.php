<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class SubledgerArchive extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $connection = 'mysql3';

    protected $table ='policy_subledger';

    public function policy()
    {
        return $this->hasOne('AlphaDirect\Policy', 'id', 'policy_id')->select('id', 'customer_id');
    }
}
