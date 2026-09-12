<?php

namespace AlphaDirect;

use AlphaDirect\Http\Controllers\SmsMessaging;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentTransaction  extends Model /* implements Auditable */
{
   // use \OwenIt\Auditing\Auditable;
   // use HasFactory;
    protected $auditTimestamps = true;
    protected $table = 'payment_transactions';
    protected $fillable = ['policyNumber','referenceNumber','amount','status','paymentDate','paymentMethod','is_ledger','is_refund','numberOfInstalmentsPaid','paymentFrequency','paymentLoggedBy','cashRecipient','note','payment_proof_link','reason','refunded_by','TransID','CCDapproval','PnrID','TransactionToken','CompanyRef','created_at','updated_at'];
    protected $dates = [];
    protected $guarded = ['id'];

    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {

        if(isset($this->policyNumber)){
           $policy = Policy::where('policyNumber', $this->policyNumber)->first();
            if($policy != null){
            $data['policy_id'] = $policy->id;
           $data['policy_number'] = $policy->policyNumber;
         }
        }
        }
        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }

        return $data;

    }
    public function generateTags(): array
    {
        return [
            'Payment Transactions'.' '.$this->auditEvent,
        ];
    }

    public function policy(){
        return $this->hasMany('AlphaDirect\Policy','policyNumber','policyNumber');
    }

    public function sendSMSOnFailedPayments($policyNumber,$amount){
        $d = Policy::join('customer','customer.id','=','policies.customer_id')
            ->where('policies.policyNumber',$policyNumber)
            ->first(array(
                'policies.policyNumber',
                'customer.firstName',
                'customer.lastName',
                'customer.email',
                'customer.cellphone'
            ));

        if($d && $d->cellphone != null) {
            $sms = new SmsMessaging();
            $res = $sms->sendPaymentFailedSMS(21, $d->firstName . ' ' . $d->lastName, $d->policyNumber, $d->cellphone, $amount);
        }
    }

    protected static function booted()
    {
        static::created(function ($transaction) {
            $pos2 = strpos($transaction->paymentDate, 'T');
            $pos = strpos($transaction->paymentDate, ':');
            if ($pos2 !== false) {
                $date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('Y-m-d');
            } elseif ($pos !== false) {
                $pos3 = strpos($transaction->paymentDate, '/');
                if ($pos3 !== false) {
                    if(substr_count($transaction->paymentDate, ":") > 1)
                    {
                        $date = Carbon::createFromFormat('d/m/Y h:i:sa', $transaction->paymentDate)->format('Y-m-d');
                    } else {
                        $date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('Y-m-d');
                    }

                } else {
                    if(substr_count($transaction->paymentDate, ":") > 1)
                    {
                        $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('Y-m-d');
                    } else {
                        $date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('Y-m-d');
                    }
                }
            } else {
                $date = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
            }
            $policy = Policy::where('policyNumber',$transaction->policyNumber)->first(['id']);
            $transaction->policy_id = $policy->id;
            $transaction->new_payment_date = $date;
            $transaction->save();
        });
    }
}
