<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class RenewPolicyExport implements WithHeadings,WithMapping,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;

    private $headings = [
        'Policy Number',
        'Customer Name',
        'Cellphone',
        'Email',
        'Claim Count',
        'Expiry Date',
        'Old Premium',
        'New premium',
        'Premium changed (in percentage)',
        'Rerated',
        'Renewed',
        // 'Request',
        // 'Response'
        //  'Created_at',
        //  'Updated_at'
    ];

    public function map($id): array
    {

        $renew= PolicyRenewal::where('id',$id)->first();
        $policy= Policy::where('policyNumber',$renew->policyNumber)->first();
        $customer=Customer::find($policy->customer_id);

        if($renew && $renew->policyNumber)

            $Policy_number=$renew->policyNumber;
        else
            $Policy_number='N/A';


        if($customer && $customer->firstName && $customer->lastName)

           $customer_name= $customer->firstName.' '.$customer->lastName;
        else
            $customer_name='N/A';


        if($customer && $customer->cellphone)

             $cellphone=$customer->cellphone;
        else
             $cellphone='N/A';


        if($customer && $customer->email)

             $email=$customer->email;
        else
             $email='N/A';


        if($renew && $renew->expiry_date)
         {
            $expiry_date=Carbon::createFromFormat('Y-m-d', $renew->expiry_date)->format('d-m-Y');

         }
        else{
            $expiry_date='N/A';
        }


        if($renew && $renew->claim_count)

            $claim_count=$renew->claim_count;
        else
            $claim_count='N/A';


        if($renew && $renew->old_premium)
            $old_premium=$renew->old_premium;
        else
            $old_premium='N/A';


        if($renew && $renew->new_premium)
            $new_premium=$renew->new_premium;
        else
            $new_premium='N/A';

        if ($renew->old_premium && $renew->new_premium) {
            $premium_value = $renew->new_premium - $renew->old_premium;
            $premium_value = ($premium_value / $renew->old_premium) * 100;
            $premium_changed_in_per = round($premium_value);
        } else {
            $premium_changed_in_per = 'N/A';
        }


        if($renew && $renew->is_rated)
          {
             $rerated='Yes';
          }
        else
          {
            $rerated='No';
          }
          if($renew && $renew->is_renewed)
          {
             $is_renewed='Yes';
          }
        else
          {
            $is_renewed='No';
          }

        //  if($renew && $renew->request_data)
        //  {
        //     $request_data=unserialize($renew->request_data);

        //  }else{
        //       $request_data='N/A';
        //  }

        //  if($renew && $renew->response_data)
        //  {
        //     $response=unserialize($renew->response_data);

        //  }else{
        //       $response='N/A';
        //  }

        return $array= [
                $Policy_number,
                $customer_name,
                $cellphone,
                $email,
                $claim_count,
                $expiry_date,
                $old_premium,
                $new_premium,
                $premium_changed_in_per,
                $rerated,
                $is_renewed,
                // $request_data,
                // $response

                //$created_at,
                //$updated_at
        ];

    }
    public function collection()
    {
        //
        $query = PolicyRenewal::orderBy('id','DESC')->get();
        return $query->pluck('id');
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
