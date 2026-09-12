<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quote extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = ['quoteCode', 'customerId', 'agentId', 'status', 'productId', 'planId', 'has_vehicle', 'has_member', 'preinspection', 'is_motor_items', 'quote_limit', 'kyc_customer', 'kyc_recipient'];
    protected $table = 'quotes';

    public function customerQuote()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customerId', 'id');
    }

    public function createdByAgent()
    {
        return $this->belongsTo('AlphaDirect\User', 'agentId', 'id');
    }

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy','quoteCode', 'quoteNumber');
    }
}
