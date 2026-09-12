<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Stores;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use AlphaDirect\Productplan;
use AlphaDirect\PolicyTerm;

class ReportExport implements WithHeadings,WithMapping,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;
    private $headings = [
                            "Policy No.",
                            "Customer Name",
                            "Contact Number",
                            "Agent",
                            "Branch",
                            "Product",
                            "Product Plan",
                            "Frequency",
                            "Premium",
                            "Vehicle Plate",
                            "Premium Value",
                            "KYC Status",
                            "Payment Status",
                            "Payment Vendor",
                            "Reference No.",
                            "Policy Status",
                            "Created At",
                            "Billing Start Date",
                            "Start Date",
                            "Expiry Date"
    ];

    public function map($id): array
    {
        $policy = Policy::where('id',$id)->first();
        $policyNumber = $policy->policyNumber;
        $paymentVendor = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('paymentMethod'));

                   if($paymentVendor && $paymentVendor->paymentMethod != null){
                      $pv =  $paymentVendor->paymentMethod;
                    }else {
                        $pv = "Payment Vendor Not Found";
                    }
        if(isset($policy->customer)){
            $name = $policy->customer->firstName.' '.$policy->customer->lastName;
            $mobile = $policy->customer->cellphone;
        }else{
             $name = null;
             $mobile = null;
        }
        if ($policy->agent_id != null){
                    $user = User::where('id', $policy->agent_id)->first(array('firstName','lastName'));
                    if ($user != null){
                        $AgentName = $user->firstName . ' ' . $user->lastName;
                    }else{
                        $AgentName = null;
                    }
        }else{
            $AgentName = null;
        }
        if($policy->storeID !=null || $policy->storeID != ""){
                    $stores = Stores::where('id',$policy->storeID)->first();
                    if($stores){
                        $storeName = $stores->name;
                    }else{
                        $storeName = null;
                    }
                    
        }else{
                   $storeName = null;
        }
        if ($policy->product != null) {
                if($policy->product->name != null){
                    $productName =  $policy->product->name;
                }else{
                    $productName = null;
                }
        }else{
            $productName = null;
        }
        if ($policy->plan_id != null){
            $productPlan = Productplan::where('id', $policy->plan_id)->first('name');
            if($productPlan->name){
              $ProductPlan = $productPlan->name;
            }else{
              $ProductPlan = null;
            }
        }else{
                    $ProductPlan = '-';
        }
        if($policy->premium_freq == 1){
                   $Frequency = "Monthly Installment";
        }elseif($policy->premium_freq == 2){
                    $Frequency = "3 Installment"; 
        }elseif($policy->premium_freq == 3){
                    $Frequency = "Yearly Payment"; 
        }else{
                   $Frequency = "Monthly Installment";
        }
       
        $AnnualPremium =  "P ".($policy->premium);
       if ($policy->has_vehicle == 1){
           $vehicle = Vehicle::where('policy_id', $policy->id)->first('vehiclePlate');
            if($vehicle){
                if($vehicle->vehiclePlate){
                   $VehiclePlate = strtoupper($vehicle->vehiclePlate);
                }else{
                   $VehiclePlate = null;
                }
                            
            }else{
                $VehiclePlate = null;
            }
        }else{
            $VehiclePlate = null;
        }
        if ($policy->premium != null) {
            $premium = $policy->premium .' Kwacha';
                    
        }else{
           $premium = null;
        }
        $kycStatus = KYC::where('customer_id', $policy->customer_id)->first('compliance');
            if($kycStatus){
             if ($kycStatus->compliance == 1){
                        $kstatus = 'Completed';
              }else{
                        $kstatus = 'Incomplete';
              }

                   
        }else{
            $kstatus =  null;
        }
        $paymentTrans = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('status'));

                  if($paymentTrans && $paymentTrans->status){
                        $pstatus = $paymentTrans->status;
                        switch ($pstatus){
                            case 'A':
                                $pstatus = "Payment Pending";
                                break;
                            case 'P':
                                $pstatus = "Payment Processing";
                                break;
                            case 'S':
                                $pstatus = "Payment Successfull";
                                break;
                                break;
                            case 'SUCCESS':
                                $pstatus = "Payment Successful";
                                break;
                             case '1':
                                $pstatus = "Payment Successful";
                                break;
                            case 'Success':
                                $pstatus = "Payment Successful";
                                break;
                            case 'success':
                                $pstatus = "Payment Successful";
                                break;
                            case 'PROCESSING':
                                $pstatus = "Payment Processing";
                                break;
                            case 'F':
                                $pstatus = "Payment Failed";
                                break;
                            case 'Failed':
                                $pstatus = "Payment Failed";
                                break;
                            case '0':
                                $pstatus = "Payment Failed";
                                break;
                            default:
                                $pstatus =  $paymentTrans->status;
                                break;
                        }

                    }else {
                        $pstatus = "Payment Status Not Found";
                    }
             $trans = '';
                $trans = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first();
                if ($trans != NULL && $trans['referenceNumber'] != null)
                {
                    $ref = $trans['referenceNumber'];
                }
                else
                {
                    $ref = "Payment reference not generated";
                }  
       if ($policy->status == 1){
        $policyStatus = 'Activated';
        }elseif ($policy->status == 2)
        {
        $policyStatus = 'Cancel';
       
    }elseif ($policy->status == 3)
    {
    $policyStatus = 'Expired';
    }else{
        $policyStatus = 'Deactivated';
        }
         $policyTerms = PolicyTerm::where('policy_id',$policy->id)->orderBy('id','DESC')->first('term_end_date');
       if($policyTerms){
           $termend =    $policyTerms->term_end_date;
       }else{
           $termend = null;
       }
                       
       return [
            $policyNumber,
            $name,
            $mobile,
            $AgentName,
            $storeName ,
            $productName,
            $ProductPlan,
            $Frequency,
            $AnnualPremium,
            $VehiclePlate,
            $premium,
            $kstatus,
            $pstatus,
            $pv,
            $ref,
            $policyStatus,
            $policy->created_at,
            $policy->billingStartDate,
            $policy->created_at,
            $termend

        ];
    }

    public function collection()
    {
        $query =  Policy::whereBetween('created_at', ['2023-01-01 00:00:00'  , '2023-11-29 23:59:59' ])
                  
        
        ->orderBy('id','desc')->get();
             return $query->pluck('id');
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
