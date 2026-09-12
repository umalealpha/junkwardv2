<?php

namespace AlphaDirect;

use Carbon\Traits\Creator;
use Illuminate\Database\Eloquent\Model;
use DateTime;
use Carbon\Carbon;

class PaymentSchedule extends Model
{
    protected $table = 'policy_payment_schedules';

    public static function checkPolicy($policyNumber){
          $check = Policy::where('policyNumber',$policyNumber)->exists();
          return $check;
    }

    public static function checkSchedule($policyNumber){
        $check = PaymentSchedule::where('policy_number',$policyNumber)->exists();
        return $check;
    }

    public static function addSchedule($policyNumber){
        try{
            $policy = Policy::where('policyNumber',$policyNumber)->first(array('customer_id','premium','policyNumber','billingStartDate'));
            $endDate = '2022-06-30';
            $d1=new DateTime();
            $d2=new DateTime($endDate);
            $Months = $d2->diff($d1);
            $limit = (($Months->y) * 12) + ($Months->m) + 1;

            $tempDate = new Carbon($policy->billingStartDate);

            $x = 1;

            do {
                if($x == 1)
                    $inc = $tempDate;
                else
                    $tempDate->addMonths(1);

                $add = new PaymentSchedule();
                $add->customer_id = $policy->customer_id;
                $add->policy_number = $policy->policyNumber ;
                $add->payment_method = 'orangeMoney';
                $add->term = 99;
                $add->payment_date = $inc;
                $add->amount = $policy->premium;
                $add->instalment_sequence = $x;
                $add->status = 0;
                $add->save();

                $x++;
            } while ($x <= $limit);

            return true;
        }catch(\Exception $ex){
            dd($ex->getMessage().' '.$ex->getLine());
            return false;
        }
    }
}
