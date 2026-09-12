<?php

namespace AlphaDirect;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ScheduleTransaction extends Model /*implements Auditable */
{
   // use \OwenIt\Auditing\Auditable;
    //use HasFactory;
    protected $auditTimestamps = true;
    protected $gaurded = ['id'];
    protected $fillable = ['policy_id','policy_number','customer_id','installment','retry_count','email','premium','billing_date','status'];
    protected $table = 'scheduled_transactions';

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'id', 'policy_id');
    }

    public function scopeGetInactiveScheduledTransactions($query, $billing_date = null)
    {

        $billing_date = isset($billing_date) ? $billing_date : Carbon::today();

        return ScheduleTransaction::whereIn('status', [0, 1, 3])   //2=success
        ->whereDate('billing_date', '=', $billing_date)
        ->where('email','!=', '')
        ->whereNotNull('email')
        // ->whereBetween('billing_date', [$from, $to])
        ->where('retry_count', '<', 6)
        ->get();
    }

    public function scopeGetLastToken($query, $policy_number)
    {
        return ScheduleTransaction::where('policy_number', $policy_number)->where('status', 1)->first('token');
    }

    public function scopeGetPaymentScheduledTransactions($query)
    {
        $from = Carbon::now()->subDays(3);
        $to = Carbon::now();
        return ScheduleTransaction::where('status', 1)
       # ->whereDate('billing_date','=', Carbon::today())
       ->whereBetween('billing_date', [$from, $to])
        // ->whereNotNull('subscription_token')
        // ->whereNotNull('token')
        ->where('retry_count', '<', 5)
        ->get();
    }
}
