<?php

namespace AlphaDirect;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;

class ScheduleTransaction extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $gaurded = ['id'];
    protected $fillable = ['policy_id','policy_number','customer_id','installment','retry_count','email','premium','billing_date','status'];
    protected $table = 'scheduled_transactions';
    public function transformAudit(array $data): array
    {
      //dd($this);
        if (Arr::has($data, 'new_values')) {

            if(isset($this->policy_number)){
                $policy = Policy::where('policyNumber', $this->policy_number)->first();
            }else if(isset($this->policyNumber)){
                $policy = Policy::where('policyNumber', $this->policyNumber)->first();
            }else if(isset($this->policy_id)){
                $policy = Policy::where('id', $this->policy_id)->first();
            }else if($this->id){
               $sch =  ScheduleTransaction::where('id',$this->id)->first('policy_number');
               if($sch != null && $sch->policy_number != null ){
                $policy = Policy::where('policyNumber', $sch->policy_number)->first();
               }else{
                $policy = null;
               }
            }else{
                $policy = null;
            }
            
            
                if($policy != null){
                    $data['policy_id'] = $policy->id;
                    $data['policy_number'] = $policy->policyNumber;
                }else{
                    $data['policy_id'] = null;
                    $data['policy_number'] = null; 
                }
            
        }

       
        return $data;
    }
    //adds in activity tag to the data while saving audits
    public function generateTags(): array
    {

       return [
                'scheduled_transactions'.' '.$this->auditEvent,
            ];
       
    }

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'id', 'policy_id');
    }

    public function scopeGetInactiveScheduledTransactions($query, $billing_date = null)
    {

        $billing_date = isset($billing_date) ? $billing_date : Carbon::today();
        //  $schs = ScheduleTransaction::whereIn('status', [0, 1, 3])   //2=success
        //                             ->whereDate('billing_date', '=', $billing_date)
        //                             ->where('email','!=', '')
        //                             ->whereNotNull('email')
        //                             ->where('retry_count', '<', 5)
        //                             ->orderBy('id','desc')
        //                             ->get();
        //  $policies2 = [];
        //  if(count($schs) > 0){
        //   foreach($schs as $polic){
        //       if(Policy::where('policyNumber',$polic->policy_number)->where('status',1)->exists()){

        //       if(ScheduleTransaction::where('policy_number',$polic->policy_number)->where('status',2)->whereIn('installment',[0,1])
        //                    ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(billing_date)') , [Carbon::parse('today')->subDay(7)
        //                    ->format('Y-m-d')  , Carbon::parse('today')->addDay(7)
        //                    ->format('Y-m-d') ])->exists()
        //       ){
        //         $policies2[] = $polic->id;

        //        }elseif(ScheduleTransaction::where('policy_number',$polic->policy_number)
        //                    ->where('status',2)
        //                    ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(billing_date)') , [Carbon::parse('today')->subDay(7)
        //                    ->format('Y-m-d')  , Carbon::parse('today')->addDay(7)
        //                    ->format('Y-m-d') ])->exists()
        //          ){
        //                  ///////
        //          }else{
        //               $policies2[] = $polic->id;
        //          }

        //       }
        //    }
        //  }
        //  if($policies2 != [] ){
        //     $policies = ScheduleTransaction::whereIn('id', $policies2)->orderBy('id','desc')->groupBy('policy_number')->get();
        //  }else{
        //     $policies = [];
        //  }


        // return $policies;



        return ScheduleTransaction::join('policies','policies.id','scheduled_transactions.policy_id')
        ->whereIn('scheduled_transactions.status', [0, 1, 3])   //2=success
        ->whereDate('scheduled_transactions.billing_date', '=', $billing_date)
        ->where('scheduled_transactions.email','!=', '')
        ->whereNotNull('scheduled_transactions.email')
        // ->whereBetween('billing_date', [$from, $to])
        ->where('scheduled_transactions.retry_count', '<', 5)
        ->where('policies.status', 1)
        // ->where('scheduled_transactions.policy_number','MIS2021028972')
        ->orderBy('scheduled_transactions.id','desc')
        //->take(700)
        //->limit(1000) // remove this limit condition to debit all records
        ->get(array(
            'scheduled_transactions.id',
            'scheduled_transactions.policy_id',
            'scheduled_transactions.policy_number',
            'scheduled_transactions.customer_id',
            'scheduled_transactions.installment',
            'scheduled_transactions.retry_count',
            'scheduled_transactions.premium',
            'scheduled_transactions.email',
            'scheduled_transactions.city',
            'scheduled_transactions.token',
            'scheduled_transactions.subscription_token',
            'scheduled_transactions.customer_token',
            'scheduled_transactions.billing_date',
            'scheduled_transactions.payment_method',
            'scheduled_transactions.reason',
            'scheduled_transactions.status',
            // 'scheduled_transactions.is_custom',
            'scheduled_transactions.processed_by',
            'scheduled_transactions.added_by',
        ));
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
