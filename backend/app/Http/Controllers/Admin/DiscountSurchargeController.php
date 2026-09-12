<?php

namespace AlphaDirect\Http\Controllers\admin;

use AlphaDirect\DiscountSurcharge;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\PolicyDiscountSurcharge;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Models\PolicyRenewal;
use Illuminate\Http\Request;
use DB;
use Redirect;
use Auth;
class DiscountSurchargeController extends Controller
{
    public function __construct(Request $request)
    {

    }

    public function policyDiscountSurcharge(Request $request)
    {
        if (auth::user()->hasPermissionTo('policy_discount_surcharge') ) {
            try {
                $policy = Policy::where('id', $request->policyId)->first(array('id','policyNumber','premium_freq','sum_assured'));
                $setting = QuoteSettings::first(array('edit_limit'));
                $edited = PolicyDiscountSurcharge::where('policy_id', $policy->id)
                    ->where('discount', '!=', 'null')
                    ->where('surcharge', '!=', 'null')
                    ->get()
                    ->count();

                if (((int)$setting->edit_limit >= $edited + 1) == true) {
                    $requestData = $request->all();
                    foreach ($requestData as $key => $req) {
                        if ($req == null || $req == '') {
                             return Redirect::back()->with('error', ucfirst($key) . ' is required');
                        }
                    }

                    /*Start*/
                    $dataDisSur = PolicyDiscountSurcharge::where('policy_id', $policy->id);
                    $totalDisc = abs($dataDisSur->where('discount', '!=', null)->sum('discount'));
                    $totalDisc = number_format((float)$totalDisc, 2, '.', '');
                    $totalSurc = PolicyDiscountSurcharge::where('policy_id', $policy->id)->where('surcharge', '!=', null)->sum('surcharge');
                    $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                    /*End*/

                    $Role = auth()->user()::with('roles')->first();
                    $userRole = $Role->roles[0]->id;
                    $data = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();

                    $old = PolicyDiscountSurcharge::where('policy_id', $policy->id)->orderBy('id','desc')->first(array('new_value'));

                    $annualPremium = $this->getAnnualPremium($request->policyId);

                    if($old) {
                        $oldValue = $old->new_value;
                        $annualPremium = $old->new_value;
                    }
                    else {
                        $oldValue = $annualPremium;
                    }

                    if ($data) {
                        $type = $request->type;
                        $value_type = $request->value_type;
                        $value = (float)$request->value;
                        $permittedFlatValue = ($data->$type) / 100 * $annualPremium;
                        $permittedPercentValue = $data->$type;

                        if ($value_type == 1) {
                            $v_perc = ($value /$annualPremium) * 100;
                            $v_flat = $value;
                        } elseif ($value_type == 2) {
                            $v_perc = $value;
                            $v_flat = ($value / 100) *$annualPremium;
                        } else {
                            DB::rollBack();
                            //return array('success'=>0,'message'=>'Value type not found');
                            return Redirect::back()->with('error', 'Value type not found');
                        }

                        if ($type == 'discount') {
                            if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                //return array('success'=>0,'message'=>'Can not exceed maximum discount value allowed');
                                return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');
                        }

                        if ($type == 'surcharge') {
                            if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                //return array('success'=>0,'message'=>'Can not exceed maximum surcharge value allowed');
                                return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');
                        }

                        if ($v_perc <= $permittedPercentValue) {
                            if ($type == 'discount') {
                                if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                    //return array('success'=>0,'message'=>'Can not exceed maximum discount value allowed');
                                    return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');

                                $annual = number_format((float)$annualPremium - $v_flat, 2, '.', '');
                            } elseif ($type == 'surcharge') {
                                if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                    //return array('success'=>0,'message'=>'Can not exceed maximum surcharge value allowed');
                                    return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');

                                $annual = number_format((float)$annualPremium + $v_flat, 2, '.', '');
                            } else {
                                //return array('success'=>0,'message'=>'Type not found');
                                return Redirect::back()->with('error', 'Type not found');
                            }

                            if ($type == 'discount') {
                                $dis_value = $v_perc;
                                $sur_value = 0;
                                $flatValue = -($v_flat);
                            } elseif ($type == 'surcharge') {
                                $sur_value = $v_perc;
                                $dis_value = 0;
                                $flatValue = $v_flat;
                            } else {
                                return Redirect::back()->with('error', 'Type not found');
                            }

                            $logData = [
                                'policy_id'=>$policy->id,
                                'discount'=>$dis_value,
                                'surcharge'=> $sur_value,
                                'old_value'=>$oldValue,
                                'new_value'=>$annual,
                                'total_dis_surc'=>$flatValue,
                                'ip_address'=>$request->ip(),
                                'user_id'=>auth::user()->id,
                            ];

                            $log = PolicyDiscountSurcharge::addLog($logData);

                            return Redirect::back()->with('success', $type.' added successfully');
                        } else {
                            //return array('success'=>0,'message'=>'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                            return Redirect::back()->with('error', 'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                        }
                    } else {
                        //return array('success'=>0,'message'=>'Values not found for role : ' . $Role->roles[0]->name);

                        return Redirect::back()->with('error', 'Values not found for role : ' . $Role->roles[0]->name);
                    }
                } else {
                    //return array('success'=>0,'message'=>'You have exceeded maximum number of updates allowed');

                    return Redirect::back()->with('error', 'You have exceeded maximum number of updates allowed');
                }
            } catch (\Exception $e) {
                return Redirect::back()->with('error', $e->getMessage().'-'.$e->getLine());
            }
         } else {
             return Redirect::back()->with('error','Sorry! You do not have permission to access this page!');
         }
    }


    public function policyRenewalDiscountSurcharge(Request $request)
    {
        if (auth::user()->hasPermissionTo('policy_discount_surcharge') ) {
        try {
            $policy = Policy::where('id', $request->policyId)->first(array('id','policyNumber','premium_freq','sum_assured'));
            $setting = QuoteSettings::first(array('edit_limit'));
            $edited = PolicyDiscountSurcharge::where('policy_id', $policy->id)
                ->where('discount', '!=', 'null')
                ->where('surcharge', '!=', 'null')
                ->get()
                ->count();

            if (((int)$setting->edit_limit >= $edited + 1) == true) {
                $requestData = $request->all();
                foreach ($requestData as $key => $req) {
                    if ($req == null || $req == '') {
                            return Redirect::back()->with('error', ucfirst($key) . ' is required');
                    }
                }

                /*Start*/
                $dataDisSur = PolicyDiscountSurcharge::where('policy_id', $policy->id);
                $totalDisc = abs($dataDisSur->where('discount', '!=', null)->sum('discount'));
                $totalDisc = number_format((float)$totalDisc, 2, '.', '');
                $totalSurc = PolicyDiscountSurcharge::where('policy_id', $policy->id)->where('surcharge', '!=', null)->sum('surcharge');
                $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                /*End*/

                $Role = auth()->user()::with('roles')->first();
                $userRole = $Role->roles[0]->id;
                $data = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();

                $old = PolicyDiscountSurcharge::where('policy_id', $policy->id)->orderBy('id','desc')->first(array('new_value'));

                $annualPremium = $this->getRenewalPremium($policy->policyNumber);

                if($old) {
                    $oldValue = $old->new_value;
                    $annualPremium = $old->new_value;
                }
                else {
                    $oldValue = $annualPremium;
                }

                if ($data) {
                    $type = $request->type;
                    $value_type = $request->value_type;
                    $value = (float)$request->value;
                    $permittedFlatValue = ($data->$type) / 100 * $annualPremium;
                    $permittedPercentValue = $data->$type;

                    if ($value_type == 1) {
                        $v_perc = ($value /$annualPremium) * 100;
                        $v_flat = $value;
                    } elseif ($value_type == 2) {
                        $v_perc = $value;
                        $v_flat = ($value / 100) *$annualPremium;
                    } else {
                        DB::rollBack();
                        //return array('success'=>0,'message'=>'Value type not found');
                        return Redirect::back()->with('error', 'Value type not found');
                    }

                    if ($type == 'discount') {
                        if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                            //return array('success'=>0,'message'=>'Can not exceed maximum discount value allowed');
                            return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');
                    }

                    if ($type == 'surcharge') {
                        if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                            //return array('success'=>0,'message'=>'Can not exceed maximum surcharge value allowed');
                            return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');
                    }

                    if ($v_perc <= $permittedPercentValue) {
                        if ($type == 'discount') {
                            if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                //return array('success'=>0,'message'=>'Can not exceed maximum discount value allowed');
                                return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');

                            $annual = number_format((float)$annualPremium - $v_flat, 2, '.', '');
                        } elseif ($type == 'surcharge') {
                            if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                //return array('success'=>0,'message'=>'Can not exceed maximum surcharge value allowed');
                                return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');

                            $annual = number_format((float)$annualPremium + $v_flat, 2, '.', '');
                        } else {
                            //return array('success'=>0,'message'=>'Type not found');
                            return Redirect::back()->with('error', 'Type not found');
                        }

                        if ($type == 'discount') {
                            $dis_value = $v_perc;
                            $sur_value = 0;
                            $flatValue = -($v_flat);
                        } elseif ($type == 'surcharge') {
                            $sur_value = $v_perc;
                            $dis_value = 0;
                            $flatValue = $v_flat;
                        } else {
                            return Redirect::back()->with('error', 'Type not found');
                        }

                        $logData = [
                            'policy_id'=>$policy->id,
                            'discount'=>$dis_value,
                            'surcharge'=> $sur_value,
                            'old_value'=>$oldValue,
                            'new_value'=>$annual,
                            'total_dis_surc'=>$flatValue,
                            'ip_address'=>$request->ip(),
                            'user_id'=>auth::user()->id,
                        ];

                        $log = PolicyDiscountSurcharge::addLog($logData);

                        return Redirect::back()->with('success', $type.' added successfully');
                    } else {
                        //return array('success'=>0,'message'=>'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                        return Redirect::back()->with('error', 'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                    }
                } else {
                    //return array('success'=>0,'message'=>'Values not found for role : ' . $Role->roles[0]->name);

                    return Redirect::back()->with('error', 'Values not found for role : ' . $Role->roles[0]->name);
                }
            } else {
                //return array('success'=>0,'message'=>'You have exceeded maximum number of updates allowed');

                return Redirect::back()->with('error', 'You have exceeded maximum number of updates allowed');
            }
        } catch (\Exception $e) {
            return Redirect::back()->with('error', $e->getMessage().'-'.$e->getLine());
        }
    } else {
        return Redirect::back()->with('error','Sorry! You do not have permission to access this page!');
    }
    }

    public function getAnnualPremium($policyId){
        $policy = Policy::where('id',$policyId)->first(array('premium_freq','premium'));
        $premium = $policy->premium;

        if($policy){
            switch($policy->premium_freq){
                case 1:
                    $premium = ($policy->premium * 12) / 1.08;
                    break;
                case 2:
                    $premium = ($policy->premium) * 3;
                    break;
                case 3:
                    $premium = ($policy->premium);
                    break;
                default:
                    $premium = ($policy->premium);
            }
        }

        return $premium;

    }

    public function getRenewalPremium($policyNumber)
    {
        $renewals = PolicyRenewal::where('policyNumber',$policyNumber)->orderBy('id', 'desc')->first();
        if (isset($renewals)) {
            return $renewals->new_premium;
        } else {
            return null;
        }
    }
}
