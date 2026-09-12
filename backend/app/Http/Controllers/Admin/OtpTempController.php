<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlphaDirect\OTPTemp;
use Respect\Validation\Rules\Regex;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Product;

class OtpTempController extends Controller
{
    public function index()
    {
        $otp= OTPTemp::first();
        return view('admin.otpTemp.create')->with('otp', $otp);
    }

    public function saveOTP(Request $request)
    {
        $otpx = random_int(0, 999999);
       
        $d = strtotime("+1 day");
        $dt = date("Y-m-d h:i:sa", $d);
        $otp= new OTPTemp();

        $count =  $otp->count();

        if($count > 0)
        {
           $status = $otp->first()->update([ 'otp' => $otpx , 'otp_exp' => $dt]);
       }else{
           $status = $otp->create([ 'otp' => $otpx , 'otp_exp' => $dt]);
       }

      return redirect()->back()->with(['status' => 'success', 'message' => 'OTP saved successfully.']);
    }
    public function getGlobalOtp(Request $request)
    {
      //  dd($request);
        $mastercodey = $request->mastercode;
       $mastercodex = 135799;
       if($mastercodey == $mastercodex){
        $otp= OTPTemp::first();
         return response()->json(['status' => 'success', 'otp' => $otp->otp, 'exp_date' => $otp->otp_exp]);
        }else{
            return response()->json(['status' => 'error', 'message'=>'invalid input code']);
        }

    }
    public function deactivepolicyactivate()
    {
        $otp= OTPTemp::first();
        return view('admin.otpTemp.deactivPolicyActivateifPaymentDone')->with('otp', $otp);
    }
    public function deactivepolicyactivatedata(Request $request)
    {
        if(!isset($request->policyNumber) || $request->policyNumber == null){
            return response()->json(['status' => 'error', 'message'=>'Please provide policy number.' ], 403);
        }
        if(!Policy::where('policyNumber', $request->policyNumber)->exists())
        {
            return response()->json(['status' => 'error', 'message'=>'Policy number not found.' ], 400);
        }else{
               if(Policy::where('policyNumber', $request->policyNumber)->where('status','!=',0)->exists()){
                return response()->json(['status' => 'error', 'message'=>'Policy number status not In-active.' ], 400);
               }

                $policy =   Policy::where('policyNumber', $request->policyNumber)->first();
                $customer = Customer::where('id',$policy->customer_id)->first();
                $product = Product::where('id',$policy->product_id)->first();
                $data = [];
                if($policy != null &&  $customer != null && $product != null){
                        $trxn =  PaymentTransaction::where('policyNumber',$policy->policyNumber)->where(function($query){
                                                                                $query->where('status', 'success')
                                                                                ->orWhere('status','Success' )->orWhere('status','SUCCESS');
                                                                            })->orderby('id','desc')->first();
                        if($trxn != null){
                          $data = ['policy'=>$policy,'customer'=>$customer,'product'=>$product,'trxn'=>$trxn];
                          return response()->json(['status' => 'success', 'data' => $data], 200); 
                        }else{
                            return response()->json(['status' => 'error', 'message'=>'Payment Transaction not Found'],400);
                        }  
                        
                }else{
                    return response()->json(['status' => 'error', 'message'=>'No Data Found'],400); 
                } 





        }
       
      

    }
    public function deactivepolicyupdate(Request $request)
    {
        if(!isset($request->policyNumber) || $request->policyNumber == null){
           
            return redirect()->back()->with([ 'error','Please provide policy number.']);
        }
        if(!Policy::where('policyNumber', $request->policyNumber)->exists())
        {
            return redirect()->back()->with([ 'error', 'Policy number not found']);
           
        }else{
               if(Policy::where('policyNumber', $request->policyNumber)->where('status','!=',0)->exists()){
                return redirect()->back()->with([ 'error',  'Policy number status not In-active.']);
               
               }

                $policy =   Policy::where('policyNumber', $request->policyNumber)->first();
                $customer = Customer::where('id',$policy->customer_id)->first();
                $product = Product::where('id',$policy->product_id)->first();
                $data = [];
                if($policy != null &&  $customer != null && $product != null){
                        $trxn =  PaymentTransaction::where('policyNumber',$policy->policyNumber)->where(function($query){
                                                                                $query->where('status', 'success')
                                                                                ->orWhere('status','Success' )->orWhere('status','SUCCESS');
                                                                            })->orderby('id','desc')->first();
                        if($trxn != null){
                            $policy =   Policy::where('policyNumber', $request->policyNumber)->update(['status'=>1]);
                         
                          return redirect()->back()->with(['success', 'Policy Activated Successfully']);
                        }else{
                            return redirect()->back()->with(['error', 'Payment Transaction not Found']);
                           
                        }  
                        
                }else{
                    return redirect()->back()->with(['error',  'No Data Found']);
                   
                } 





        }
       
      

    }

    
}
