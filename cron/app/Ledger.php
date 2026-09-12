<?php

namespace AlphaDirect;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
class Ledger extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_ledger';

    public function policy()
    {
        return $this->hasOne('AlphaDirect\Policy', 'id', 'policy_id');
    }

    public function customer()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customer_id', 'id');
    }

    public function scopefindledgerbypolicyid($query,$policy_id){
        return $query->where('policy_id',$policy_id)
            ->where('status','Pending')
            ->where('trans_type','Invoice')
            ->orderBy('id','desc');
    }

    public function scopeCalculateAmountByDate($query,$date_before,$from_date){

        return $query->whereBetween('accounting_date',[Carbon::parse($date_before)->format('Y-m-d'),Carbon::parse($from_date)->format('Y-m-d')])
            ->sum('invoice_amount');
    }

}
