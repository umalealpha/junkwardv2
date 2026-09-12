<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Yajra\DataTables\DataTables;
use DB;
use Redirect;
use function GuzzleHttp\Psr7\str;

class Transaction extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = ['customer_id', 'orangeTransaction_id', 'vcsTransaction_id', 'realpayTransaction_id', 'transactionType', 'amount', 'policyNumber', 'status', 'paymentDescription'];

    protected $table = 'transactions';

    public function orangeTransactions()
    {
        return $this->hasMany('AlphaDirect\OrangeTransactions', 'orangeTransaction_id');
    }

    public function vcsTransactions()
    {
        return $this->hasMany('AlphaDirect\VcsTransaction', 'vcsTransaction_id');
    }

    public function realpayTransactions()
    {
        return $this->hasMany('AlphaDirect\RealPay', 'realpayTransaction_id');
    }

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy')->orderBy('id', 'DESC')->first();
    }

    public function customer()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customer_id');
    }

    public function scopetransactionData($query)
    {
//        $transactions = Transaction::where('customer_id' , '!=' , '')->get();
        $transactions = Transaction::where('customer_id' , '!=' , '')->orderBy('id', 'ASC')->get();
        return DataTables::of($transactions)
            ->editColumn('created_at', function ($transactions) {
                return $transactions->created_at;
            })
            ->addColumn('customer', function ($transactions) {
                if ($transactions->customer_id != null || $transactions->customer_id != '') {
                    $customer = Customer::where('id', $transactions->customer_id)->first(array('firstName', 'lastName'));
                    if($customer != NULL){
                        if($customer->firstName != '' && $customer->lastName !=''){
                            return $customer->firstName . ' ' . $customer->lastName;
                        }
                        else
                        return '-';
                    }
                    else
                    return '-';
                } else {
                    return '-';
                }

            })
            ->rawColumns(['created_at','customer'])
            ->make(true);

    }


    public static function insertData($data){
        try{
            $cellphone = (strlen($data['orangeTransaction_id']) == 11) ? substr($data['orangeTransaction_id'],3,11) : $data['orangeTransaction_id'];
            $data['orangeTransaction_id'] = $cellphone;

            $checkRef = Transaction::where('referenceNumber',$data['referenceNumber'])->pluck('policyNumber');
            $checkPayment = PaymentTransaction::where('referenceNumber',$data['referenceNumber'])->first();

            if(count($checkRef) == 0){
                $checkPolicy = Policy::where('policyNumber',$data['policyNumber'])->first(array('policyNumber','status'));
                if($checkPolicy != null){
                    $data['policyNumber'] = $checkPolicy->policyNumber;
                    DB::table('transactions')->insert($data);

                    $payment = new PaymentTransaction();
                    $payment->policyNumber = $checkPolicy->policyNumber;
                    $payment->referenceNumber = $data['referenceNumber'];
                    $payment->amount = $data['amount'];
                    $payment->status = 'Success';
                    $payment->paymentDate = $data['created_at'];
                    $payment->paymentMethod = 'orangeMoney';
                    $payment->is_ledger = 0;
                    $payment->save();

                    return array('status'=>'Success','policyNumber'=>$checkPolicy->policyNumber,'message'=>'updated');
                }else{
                    $customer = Customer::where('cellphone',$cellphone)->first(array('id'));
                   if($customer != null){
                       $policy = Policy::where('customer_id',$customer->id)->get();
                       if(count($policy) == 1){
                           $data['policyNumber'] = $policy[0]->policyNumber;
                           DB::table('transactions')->insert($data);

                           $payment = new PaymentTransaction();
                           $payment->policyNumber = $policy[0]->policyNumber;
                           $payment->referenceNumber = $data['referenceNumber'];
                           $payment->amount = $data['amount'];
                           $payment->status = 'Success';
                           $payment->paymentDate = $data['created_at'];
                           $payment->paymentMethod = 'orangeMoney';
                           $payment->is_ledger = 0;
                           $payment->save();

                           return array('status'=>'Success','policyNumber'=>$policy[0]->policyNumber,'message'=>'Updated');
                       }elseif(count($policy) == 0){
                           return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Policy not found, customer found with cellphone , holds 0 policy');
                       }else{
                           return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Policy not found and customer found with multiple policies');
                       }
                   }else{
                       return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Policy & Customer not found');
                   }
                }
            }else{
                $logged = '';
                foreach($checkRef as $policyNum){
                    $logged .= ' '.$policyNum;
                }
                if($checkPayment == null){
                    $payment = new PaymentTransaction();
                    $payment->policyNumber = $checkRef[0];
                    $payment->referenceNumber = $data['referenceNumber'];
                    $payment->amount = $data['amount'];
                    $payment->status = 'Success';
                    $payment->paymentDate = $data['created_at'];
                    $payment->paymentMethod = 'orangeMoney';
                    $payment->is_ledger = 0;
                    $payment->save();
                }
                return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Transaction already logged for'.$logged);
            }
        }catch(\Exception $ex){
            return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>$ex->getMessage().' '.$ex->getLine());
        }
    }

    public static function insertData1($data)
    {
        dd($data);
        $cell = (strlen($data['orangeTransaction_id']) == 11) ? substr($data['orangeTransaction_id'],3,11) : $data['orangeTransaction_id'];
        try{
            $value=DB::table('transactions')->where('referenceNumber', $data['referenceNumber'])->get();
           if(count($value) == 0){
               $policy = Policy::where('policyNumber',$data['policyNumber'])->first();
               if($policy == null){
                   $cell = (strlen($data['orangeTransaction_id']) == 11) ? substr($data['orangeTransaction_id'],3,11) : $data['orangeTransaction_id'];
                   $customer = Customer::where('cellphone',$cell)->first(array('id'));
                   if($customer != null){
                       $policyCus = Policy::where('customer_id',$customer->id)->get(array('policyNumber'));
                       if(count($policyCus) == 1){
                           if(count($value) == 0){
                               DB::table('transactions')->insert($data);
                               return array('status'=>'Success','policyNumber'=>$data['policyNumber'],'message'=>'Updated');
                           }else{
                               return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Transaction already logged');
                           }
                       }elseif(count($policyCus) == 0){
                           return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Customer found with cellphone but customer holds no policies');
                       }else{
                           return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Policy found with cellphone but customer holds multiple policies');
                       }
                   }else{
                       return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Customer and policy not found');
                   }
               }else{
                   if(count($value) == 0){
                       DB::table('transactions')->insert($data);
                       return array('status'=>'Success','policyNumber'=>$data['policyNumber'],'message'=>'Updated');
                   }else{
                       return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Transaction already logged');
                   }
               }
           }else{
               return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Transaction already logged');
           }
        }catch(\Exception $ex){
            return array('status'=>'Failed','policyNumber'=>$data['policyNumber'],'message'=>'Something went wrong');
        }
    }

}
