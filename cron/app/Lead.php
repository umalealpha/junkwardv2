<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lead extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = ['leadCode', 'customerId', 'agentId', 'status'];
    protected $table = 'leads';

    public function customerLead()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customerId', 'id');
    }
    protected function createdByAgent()
    {
        return $this->belongsTo('AlphaDirect\User', 'agentId', 'id');
    }


}
