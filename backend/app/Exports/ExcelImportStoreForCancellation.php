<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\Activation;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\ProductType;
use AlphaDirect\Vendor;
use AlphaDirect\Branch;
use Excel;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use AlphaDirect\ExcelImportForPolicy;
use AlphaDirect\Policy;
use AlphaDirect\User;
use Carbon\Carbon;

class ExcelImportStoreForCancellation implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    

    private $headings = [
        'PolicyNumber',
        'Policy Status',
        'Perform',
        'Status',
        'Activity By',
        'Payment Id',
        'Payment Date',
        'Customer Age',
        'Issue Credit Note',
        'Email Sent',
        'Sms Sent',
        'Created At',
       
    ];


    function __construct() {

       
    }

    public function map($id): array
    {
        $codes  = ExcelImportForPolicy::orderBy('created_at', 'DESC')->where('action','cancellation')->get();
        if(count($codes) > 0){
        foreach ($codes as $item2) {

            $PolicyNumber = $item2->policyNumber;
            $policy = Policy::where('policyNumber',$item2->policyNumber)->first(['status']);
            if($policy != null){
                if($policy->status == 1){
                    $PolicyStatus = 'Activated';
                }elseif($policy->status == 2){
                    $PolicyStatus = 'Cancel';
                }elseif($policy->status == 3){
                    $PolicyStatus = 'Expired';
                }else{
                    $PolicyStatus = 'Deactivated'; 
                }
               
            }else{
                $PolicyStatus = 'Deactivated'; 
            }
           
            $Perform = $item2->action;
            if ($item2->status == 1)
            {
                 $Status = 'Success';
            }
            elseif ($item2->status == 0)
            {
                 $Status = 'Failed';
            }
            else
            {
                 $Status = 'Pending';
            }
            if($item2->added_by){
                $user = User::where('id',$item2->added_by)->first(['firstName','lastName']);
                if($user){
                    $name = $user->firstName.' '.$user->lastName;
                }else{
                    $name = '-';
                }
                
                $ActivityBy =   $name;
               }else{
                $ActivityBy =   '-';
               }
           
            $PaymentId = $item2->last_success_transection_id;
            $PaymentDate = $item2->last_success_transection_date;
            $customer_age = $item2->customer_age;

            if($item2->issue_credit_note == 1){
                $issue_credit_note =  'Yes';
              }else{
                $issue_credit_note = 'No';
              }
              if($item2->email_sent == 1){
                $email_sent =  'Yes';
              }else{
                $email_sent = 'No';
              }
              if($item2->sms_sent == 1){
                $sms_sent =  'Yes';
              }else{
                $sms_sent = 'No';
              }
            
            $CreatedAt = Carbon::parse($policy->created_at)->format('Y-m-d');

        $x[] =  [
            $PolicyNumber,
            $PolicyStatus,
            $Perform,
            $Status,
            $ActivityBy,
            $PaymentId,
            $PaymentDate,
            $customer_age,
            $issue_credit_note,
            $email_sent,
            $sms_sent,
            $CreatedAt,
        ];
         }
        }
        return $x;

    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $codes = ExcelImportForPolicy::orderBy('created_at', 'DESC')->where('action','cancellation')->take(1)->get();
      
        $codes = $codes->sortBy('id');

        return $codes;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
