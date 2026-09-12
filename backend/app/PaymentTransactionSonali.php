<?php

namespace AlphaDirect;

use AlphaDirect\Http\Controllers\SmsMessaging;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use DB;
use Log;

class PaymentTransactionSonali  extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $table = 'payment_transactions_sonali';
    protected $fillable = ['policy_id','new_payment_date','policyNumber','referenceNumber','amount','status','paymentDate','paymentMethod','is_ledger','is_refund','numberOfInstalmentsPaid','paymentFrequency','paymentLoggedBy','cashRecipient','note','payment_proof_link','reason','refunded_by','TransID','CCDapproval','PnrID','TransactionToken','CompanyRef','created_at','updated_at'];
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

    public function policyProduct()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'policyNumber', 'policyNumber');
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

            if (isset($transaction->send_sms_email) && $transaction->send_sms_email == 0) {
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
                $policy = Policy::where('policyNumber',$transaction->policyNumber)->first(['id','customer_id','policyNumber','premium']);
                $transaction->policy_id = $policy->id;
                $transaction->new_payment_date = $date;
                $transaction->save();
                //mail
                if ($policy->customer->email != null) {
                    $data              = new \stdClass();
                    $data->user_id     = null;
                    $data->policy_id   = $policy->id;
                    $data->policyNumber   = $policy->policyNumber;
                    $data->customer_id = $policy->customer_id;
                    if( (strtoupper($transaction->status) == "SUCCESS") || ($transaction->status == 1)){

                            $data->hook        = 'pay_after_success_payment';

                    }else{
                        $sms              = new SmsMessaging();

                            $data->hook        = 'pay_after_failed_payment';
                            $smsTemplateSlug = "Send SMS to Customers on policy payment failed";

                        $sms->sendPaymentFailedSMSForAllPaymentMethod($smsTemplateSlug, $policy->customer->firstName, $policy->policyNumber, $policy->customer->cellphone, $policy->premium);
                    }
                    $data->attachment  = null;
                    $emailTemplate     = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown          = new MailTemplate($data);
                    $html              = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($policy->customer->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                    // Mail::to($policy->customer->email)->send(new MailTemplate($data));
                }
            }
        });
    }

    public function scopeStatus($query)
    {
        return $query->whereIn('status', [ 'FAILED', 'Failed', 'failed']);
    }

    public function scopeNote($query,$note)
    {
        return $query->where('note',$note);
    }

    public function scopeBetweenCreatedDates($query, $startDate, $endDate){
        $query->whereBetween('new_payment_date', [$startDate,$endDate]);
    }

    public static function queryForCurrentNoteCountBetweenDates(Carbon $startDate,Carbon $endDate,$columnNameForCount){
        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');
        return PaymentTransaction::selectRaw("note, count(note) as count_of_notes")
                                    ->BetweenCreatedDates($startDate,$endDate)
                                    ->whereNotNull('note')
                                    ->groupBy('note')
                                    ->orderBy('count_of_notes','desc')
                                    ->Status()
                                    ->limit(5)
                                    ->get();
    }


    public static function queryForpaymentTransDatesBetweenDates(Carbon $startDate,Carbon $endDate){
        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');
        return PaymentTransaction::select( 'id', DB::raw( 'DATE(new_payment_date) as date' ), 'status' )
                                    ->whereBetween( 'new_payment_date', [ $startDate, $endDate ] )
                                    ->whereIn( 'status', [ 'SUCCESS', 'Success', 'success' ] )
                                    ->orderBy('id','desc')
                                    ->groupBy( 'date' )
                                    ->get();
    }

    public static function queryForPaymentTransCountBetweenDates($date,$productId,$policyNumbers){
        return PaymentTransaction::select('id', 'policyNumber', 'new_payment_date', 'status')
                                    ->whereDate('new_payment_date', $date)
                                    ->whereIn('policyNumber', $policyNumbers)
                                    ->whereIn('status', [ 'SUCCESS', 'Success', 'success' ])
                                    ->count();
    }
    public function getAmountAttribute($value)
    {
        return str_replace(',', '.', $value);
    }

}
