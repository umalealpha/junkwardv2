<?php

namespace AlphaDirect;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Yajra\DataTables\DataTables;

class PaymentUrls extends Model
{
    //

    //uniq_code this column filled at the time of sms sending to balance customer.
    //for ref check PolicyController. Method name sendPolicyPaymentSms
    protected $fillable = ['uniq_code','cellphone', 'policy_id', 'amount', 'request_from', 'status', 'url'];

    protected $table = 'payment_urls';

    public function createUrl($policy_number, $leadSource = 'graphite')
    {
        $urlValue = \Config::get('values.graphite_url');
        $policy = new Policy();
        $policyData = $policy->GetPolicyDataByPolicyNumberWithCustomerData($policy_number);
        $paymentUrl = new PaymentUrls();
        $paymentUrl->cellphone = $policyData->customer->cellphone;
        $paymentUrl->policy_id = $policyData->id;
        $paymentUrl->created_by = auth()->user()->id;
        $paymentUrl->amount = $policyData->premium;
        $paymentUrl->request_from = $leadSource;
        $paymentUrl->status = 0;

        if ($paymentUrl->save()) {
            try {
                //update the table
                $addPyamentUrl = PaymentUrls::findorFail($paymentUrl->id);
                $addPyamentUrl->url = $urlValue . 'graphitePaymentForm/' . base64_encode($paymentUrl->id);
                $addPyamentUrl->save();

                return response()->json(['message' => 'success', 'url' => $addPyamentUrl->url, 'status' => 200], 200);
            } catch (Exception $ex) {
                return response()->json(['error' => $ex->getMessage()], 500);
            }
        } else {
            return response()->json(['message' => 'Error in saving payment data'], 419);
        }
    }

    public function paymenturlPolicy()
    {

        return $this->belongsTo('AlphaDirect\Policy', 'policy_id', 'id')->select('policyNumber');
    }

    public function scopegetPaymentURLDataUsingPolicyId($query, $policy_id)
    {

        return DataTables::of($query->orderBy('created_at', 'desc')->where('policy_id', $policy_id))
            ->editColumn('status', function ($paymentUrls) {
                if ($paymentUrls->status == 0) {
                    $status = '<span class="kt-font-bold kt-font-success">Not Used</span>';
                    return $status;
                } else {
                    $status = '<span class="kt-font-bold kt-font-danger">Used</span>';
                    return $status;
                }
            })
            ->editColumn('created_at', function ($paymentUrls) {
                return $paymentUrls->created_at->diffForHumans();
            })
            ->addColumn('created_by', function ($paymentUrls) {
                $user = User::where('id',$paymentUrls->created_by)->first();
                if($user){
                    return $user->firstName.' '.$user->lastName;
                }else{
                    return '-';
                }
            })
            ->addColumn('link_type', function ($paymentUrls) {
                if($paymentUrls->link_type)
                    return ucfirst($paymentUrls->link_type);
                else
                    return '-';
            })
            ->addColumn('actions', function ($paymentUrls) {
                if ($paymentUrls->status == 0) {
                    $actions = '<a href="" value="' . $paymentUrls->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md editId" id="editId"  data-toggle="modal" data-target="#resend_sms" title="Edit">
                            <i class="fas fa-reply"></i>
                            <a href= "' . route('paymentUrl.delete', $paymentUrls->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete"><i class="la la-trash"></i></a>';
                    return $actions;
                } else {
                    return null;
                }
            })
            ->rawColumns(['status','actions','added_by','link_type'])
            ->make(true);
    }
}
