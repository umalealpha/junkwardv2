<?php

namespace AlphaDirect\Exports;

use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use AlphaDirect\Product;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RealpayTransactions implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'ID',
        'Policy Number',
        'Client Number',
        'Contract Number',
        'Product',
        'Plan',
        'First Premium',
        'Premium',
        'Frequency',
        'Billing Day',
        'Billing Date',
        'Policy Status',
        'Payment Status',
        'Created at',
    ];

    public function map($id): array
    {
        $data = RealpayPaymentRequest::where('id',$id)
            ->orderBy('id','desc')
            ->first();
        if($data && $data->policy_id){
            $policy = Policy::where('id',$data->policy_id)->first(array('product_id'));
            if($policy && $policy->product_id){
                $product = Product::where('id',$policy->product_id)->first(array('name'));
                if($product && $product->name)
                    $productName = $product->name;
                else
                    $productName = 'NA';
            }else{
                $productName = 'NA';
            }
        }

        if($data && $data->policy_id){
            $policy = Policy::where('id',$data->policy_id)->first(array('status'));
            if($policy){
                if($policy->status == 0)
                    $policyStatus = 'Deactivated';
                elseif($policy->status == 1)
                    $policyStatus = 'Deactivated';
                elseif($policy->status == 2)
                    $policyStatus = 'Cancelled';
                else
                    $policyStatus = 'N/A';
            }else{
                $productName = 'NA';
            }
        }
        $count = RealpayContractInstallments::where('contractNumber',$data->policy_id)->count();
        $active = RealpayContractInstallments::where('contractNumber',$data->policy_id)->where('InstalmentStatus','A')->get(array('id'));
        $success = RealpayContractInstallments::where('contractNumber',$data->policy_id)->where('InstalmentStatus','S')->get(array('id'));
        $failed = RealpayContractInstallments::where('contractNumber',$data->policy_id)->where('InstalmentStatus','F')->get(array('id'));

        if($count == 0){
            $payment = 'Failed';
        }else{
            $payment = 'Active: '.count($active) .', Success: '.count($success).', Failed: '.count($failed);
        }

        if($data && $data->policy_id){
            $policy = Policy::where('id',$data->policy_id)->first(array('plan_id'));
            if($policy && $policy->plan_id){
                $productplan = Productplan::where('id',$policy->plan_id)->first(array('name'));
                if($product && $productplan->name)
                    $plan = $product->name;
                else
                    $plan = 'NA';
            }else{
                $plan = 'NA';
            }
        }

        if($data && $data->policy_id){
            $policy = Policy::where('id',$data->policy_id)->first(array('premium_freq'));
            if($policy && $policy->premium_freq){
                if($policy->premium_freq == 1){
                    $freq = 'Monthly';
                }elseif($policy->premium_freq == 2){
                    $freq = 'Three Instalments';
                }elseif($policy->premium_freq == 3){
                    $freq = 'Annually';
                }else{
                    $freq = '-';
                }
            }else{
                $freq = 'NA';
            }
        }

        $fp = '-';
        if($data && $data->policy_id){
            $policy = Policy::where('id',$data->policy_id)->first(array('first_premium_wvat','premium_freq'));
            if($policy && $policy->first_premium_wvat && $policy->premium_freq == 1){
                $fp = $policy->first_premium_wvat;
            }else{
                $fp = '-';
            }
        }


        $dataStatus = null;


        return [
            isset($data['id'])?$data['id']:'NA',
            isset($data['clientNumber'])?$data['clientNumber']:'NA',
            isset($data['clientNumber'])?$data['clientNumber']:'NA',
            isset($data['contract'])?$data['contract']:'NA',
            $productName,
            $plan,
            $fp,
            isset($data['premium'])?$data['premium']:'-',
            $freq,
            isset($data['billing_day'])?$data['billing_day']:'NA',
            isset($data['billing_date'])?$data['billing_date']:'NA',
            $policyStatus,
            $payment,
            isset($data['created_at'])?$data['created_at']:'NA',
        ];
    }


    public function collection()
    {

        $query = RealpayPaymentRequest::get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
