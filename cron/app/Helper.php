<?php
/**
 * Sanket Shah
 * User: SHAHPC
 * Date: 27-09-2019
 * Time: 18:24
 */
namespace AlphaDirect;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use AlphaDirect\Http\Controllers\Payment\VCS\VcsController;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PDF;
use Carbon;

class Helper {
    /*
     * method to retrieve image source and return icon fileas per the extension
     * return: file
     */
    public static function getImageSrc($file){

        if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
            return asset('images/pdf.ico');
        else if(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
            return asset('images/word.ico');
        else if(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
            return asset('images/excel.png');
        else if(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
            return Storage::disk('s3')->url($file);
        else
            return asset('images/doc.png');
    }

    /*
     * stores ledger entries as per the defined rules for policies
     * param: user ID
     * param: entity type
     * param: entity id
     * param: product id
     * param: action
     * return: view => policy listing page
     */
    public static function ledgerStore($user_id, $entity_type, $entity_id, $product_id, $action_id){
        return true;
        // $balance = 0;
        // $amt = 0;
        // $rules = AccountingRules::where('product_id', $product_id)->where('action', $action_id)->get(array('account_id', 'trans_type_id', 'trans_subtype_id', 'amount_type', 'entry_type','action'));

        // if($rules != NULL)
        // {
        //     $check = $rules->where('action', 'NEWBUSINESS')->where('amount_type', 31)->toArray();
        //     $rules = $rules->toArray();
        // } else {
        //     $rules = array();
        // }

        // //For New Business generate invoice for gross Premium
        // if($action_id == 'NEWBUSINESS' && $check == null)
        // {
        //     $invoice = array();
        //     $invoice['account_id'] = 5;
        //     $invoice['trans_type_id'] = 1;
        //     $invoice['trans_subtype_id'] = 1;
        //     $invoice['amount_type'] = 31;
        //     $invoice['entry_type'] = 'credit';
        //     $invoice['action'] = 'NEWBUSINESS';

        //     array_push($rules,$invoice);
        // }

        // if($entity_type == 'POLICY')
        // {
        //     $policy = Policy::where('id', $entity_id)->first(array('id', 'policyNumber','premium', 'vat','created_at'));
        //     $balance = Ledger::where('policy_id', $entity_id)->orderBy('id', 'DESC')->first(array('balance'));
        // }
        // else
        // {
        //     $claim = Claim::where('id', $entity_id)->first(array('policy_id'));
        //     $policy = Policy::where('id', $claim->policy_id)->first(array('id', 'policyNumber','premium', 'vat','created_at'));
        //     $balance = Ledger::where('claim_id', $entity_id)->orderBy('id', 'DESC')->first(array('balance'));
        // }
        // $policy_id = $policy->id;
        // if($balance != null){
        //     $balance = $balance->balance;
        // }

        // $ledger_ids = array();
        // $credit = 0;

        // foreach($rules as $rule)
        // {
        //     $ledgerValues = new Ledger();
        //     $ledgerValues->customer_id = $user_id;
        //     $ledgerValues->policy_id = $policy->id;
        //     $banking_id = CustomerBanking::where('customer_id',$user_id)->first(array('id'));
        //     $ledgerValues->banking_id = $banking_id->id;
        //     $ledgerValues->premium = $policy->premium;
        //     $ledgerValues->account_id = $rule['account_id'];
        //     $accName = Accounts::where('id',$rule['account_id'])->first();
        //     if(!empty($accName)){
        //         $ledgerValues->account_name = $accName->account_name;
        //     }
        //     else{
        //         $ledgerValues->account_name = '-';
        //     }

        //     // Net Premium
        //     if($rule['amount_type'] == 30){
        //         $amt = $policy->premium - $policy->vat;
        //     }
        //     // Gross Premium
        //     elseif ($rule['amount_type'] == 31){
        //         $amt = $policy->premium;
        //     }
        //     // VAT
        //     elseif ($rule['amount_type'] == 32){
        //         $amt = $policy->vat;
        //     }

        //     if($rule['entry_type'] == 'credit') {
        //         $ledgerValues->credit = $amt;
        //         $ledgerValues->balance = $balance + $amt;
        //         $credit = 1;
        //     }
        //     else {

        //         $ledgerValues->debit = $amt;
        //         $ledgerValues->balance = $balance - $amt;

        //     }
        //     $balance = $ledgerValues->balance;

        //     $ledgerValues->trans_type = $rule['trans_type_id'];
        //     $ledgerValues->trans_sub_type = $rule['trans_subtype_id'];
        //     $ledgerValues->trans_ref = $policy->policyNumber;
        //     $ledgerValues->orig_trans = $rule['action'];
        //     $ledgerValues->amount_type = $rule['amount_type'];

        //     $curTime = new \DateTime('NOW');
        //     $ledgerValues->accounting_date =  $policy->created_at;
        //     $ledgerValues->system_date = $policy->created_at;
        //     $ledgerValues->eff_date = $curTime->format("Y-m-d");
        //     if($entity_type == 'CLAIM')
        //     {
        //         $ledgerValues->claim_id = $entity_id;
        //     }
        //     $ledgerValues->save();

        //     $ledger_ids[] = $ledgerValues->id;
        // }

        // if(count($ledger_ids) > 0 && $credit && $action_id=="NEWBUSINESS")
        //     Helper::generateInvoice($ledger_ids);

        // return;
    }

    /*
     * generates unique strings
     * function usage: mt_rand to generate random strings
     * return: generated string
     */
    public static function gen_ustring($v1,$v2){
        try{
            $generatedString =  mt_rand($v1, $v2);
            return $generatedString;

        }catch (\Exception $ex) {
            return Redirect::back()->with($ex->getMessage(), $ex->getLine());
        }

    }

    public static function getCloudFrontURL($url){
        try{
            $url = str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'), Storage::disk('s3')->url(str_replace(array('\/', ' '), array('/', '%20'), $url)));
            return str_replace(' ','+',$url);
        }catch (\Exception $ex) {
            return Redirect::back()->with($ex->getMessage(), $ex->getLine());
        }
    }

    /*
     * Stores entries for reserve values
     * param: user ID
     * param: entity type
     * param: entity id
     * param: product id
     * param: action
     * param: total sum of values
     * param: vat info
     * return: claim page
     */
    public static function ReserveLedgerStore($user_id, $entity_type, $entity_id, $product_id, $action_id,$sum,$vatInfo){
        return true;
        // $balance = 0;
        // $rules = AccountingRules::where('product_id', $product_id)->where('action', $action_id)->get(array('account_id', 'trans_type_id', 'trans_subtype_id', 'amount_type', 'entry_type','action'));
        // $claim = Claim::where('id', $entity_id)->first(array('policy_id'));
        // $policy = Policy::where('id', $claim->policy_id)->first(array('id', 'policyNumber','premium', 'vat','vat_percent'));
        // $balance = Ledger::where('claim_id', $entity_id)->orderBy('id', 'DESC')->first(array('balance'));
        // if($vatInfo == 0){
        //     $amt = $sum;
        // }
        // else{
        //     $amt = $sum - (($policy->vat_percent/100) * $sum);
        // }
        // $policy_id = $policy->id;
        // if($balance != null){
        //     $balance = $balance->balance;
        // }
        // foreach($rules as $rule)
        // {
        //     $ledgerValues = new Ledger();
        //     $ledgerValues->customer_id = $user_id;
        //     $ledgerValues->policy_id = $policy->id;
        //     $banking_id = CustomerBanking::where('customer_id',$user_id)->first(array('id'));
        //     $ledgerValues->banking_id = $banking_id->id;
        //     $ledgerValues->premium = $policy->premium;
        //     $ledgerValues->account_id = $rule['account_id'];
        //     $accName = Accounts::where('id',$rule['account_id'])->first();
        //     if(!empty($accName)){
        //         $ledgerValues->account_name = $accName->account_name;
        //     }
        //     else{
        //         $ledgerValues->account_name = '-';
        //     }
        //     if($rule['entry_type'] == 'credit') {
        //         $ledgerValues->credit = $amt;
        //         $ledgerValues->balance = $balance + $amt;
        //         $credit = 1;
        //     }
        //     else {
        //         $ledgerValues->debit = $amt;
        //         $ledgerValues->balance = $balance - $amt;
        //     }
        //     $balance = $ledgerValues->balance;

        //     $ledgerValues->trans_type = $rule['trans_type_id'];
        //     $ledgerValues->trans_sub_type = $rule['trans_subtype_id'];
        //     $ledgerValues->trans_ref = $policy->policyNumber;
        //     $ledgerValues->orig_trans = $rule['action'];
        //     $ledgerValues->amount_type = $rule['amount_type'];

        //     $curTime = new \DateTime('NOW');
        //     $ledgerValues->accounting_date =  $curTime->format("Y-m-d");
        //     $ledgerValues->system_date = $curTime->format("Y-m-d");
        //     $ledgerValues->eff_date = $curTime->format("Y-m-d");
        //     if($entity_type == 'CLAIM')
        //     {
        //         $ledgerValues->claim_id = $entity_id;
        //     }
        //     $ledgerValues->save();


        // }
        // return;
    }

    /*
     * generayes invoice for specific policy from ledger tab
     * param: ledger entries for specific policies
     */
    public static function generateInvoice($ledger_id)
    {
        $invoice = Ledger::where('id', $ledger_id)->first(array('trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id'));
        $vat = Ledger::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
        $policy = Policy::where('id', $invoice->policy_id)->first(array('policyNumber'));
        $data = [
            'invoice'         => $invoice,
            'customer'        => Customer::where('id', $invoice->customer_id)->first(array('firstName', 'lastName')),
            'customerProfile' => CustomerProfile::where('customer_id', $invoice->customer_id)->first(array('address')),
            'vat'             => $vat->debit,
            'policyNumber'    => $policy->policyNumber
        ];

        $pdf = PDF::loadView('admin.subLedger.invoice', $data);

        $filePath = 'MIS/' .$invoice->policy_id . '/'.'Customer/'.$invoice->customer_id.'/'.'Invoice/'.$invoice->invoice_no.'.pdf' ;
        Storage::disk('s3')->put($filePath , $pdf->output());

        $invoice->invoice_file = $filePath;
        $invoice->save();

        /*$data = new \stdClass();
        $data->attachment[] = $filePath;
        $data->hook = 'ledger_invoice';
        $email = Customer::where('id', $ledger->customer_id)->first(array('email'));

        Mail::to($email)
            ->queue(new MailTemplate($data)); */

        return $filePath;
    }

    /*
     * implements payment for policy specific invoice
     * param: billing option
     * param: policy number
     * param: policyID
     * param: premiumv value
     */
    public static function makePayment($billingOption, $policyNumber, $policyId, $premium)
    {
        switch ($billingOption) {
            case 'VCS':
                $vcs = new VcsController();
                return $vcs->graphiteVcsPayment($policyNumber, $premium, $policyId);
                break;
            case 'Orange':
                $orangeMoney = new OrangeMoneyController();
                return $orangeMoney->webPayIntiliazer($policyNumber, $premium);
                break;
            case 'RealPay':
                $realPay = new RealPayController();
                return $realPay->addClientRealPay($policyNumber, $premium);
                break;
            default:
                return Redirect::back()->with('error', 'Please select a payment vendor')
                    ->withInput($policyNumber);
        }

        return;
    }

    public static function validation()
    {
        $rules = array(
            'product' => array('required','integer'),
            'vehiclePlate' => array('required'),
            'purpose' => array('required'),
            'billing_day' => array('required','date_format:d-m-Y'),
            'frequency' => array('required','integer'),
            'Payment_method' => array('required'),
            'bankName' => array('required'),
            'branchCode' => array('required'),
            'bankAccountType' => array('required'),
            'accountNumber' => array('required'),
            'front' => array('required','image','mimes:jpg,jpeg,png,gif','max:2048'),
            'back' => array('required','image','mimes:jpg,jpeg,png,gif','max:2048'),
            'right' => array('required','image','mimes:jpg,jpeg,png,gif','max:2048'),
            'left' => array('required','image','mimes:jpg,jpeg,png,gif','max:2048'),
            'vehicleRegistration' => array('required','image','mimes:jpg,jpeg,png,gif','max:2048'),
            'phone' => array('required','integer'),
            'omang' => array('required','integer'),
            'passport' => array('required'),
            'firstname' => array('required'),
            'lastname' => array('required'),
            'email' => array('required'),
            'password' => array('required','min:6'),
            'gender' => array('required'),
            'address' => array('required'),
            'state' => array('required'),
            'passportIssuingCountry' => array('required'),
            'city' => array('required'),
            'maritalstatus' => array('required'),
            'dob' => array('required','date_format:d-m-Y'),
            'omangexpiry' => array('required'),
            'passportexpiry' => array('required'),
            'note' => array('required'),
            'plan' => array('required'),
            'agentCode' => array('required'),
            'store_id' => array('required','integer'),
            'sum_insured' => array('required'),
            'premium' => array('required'),
            'premium_label_vat' => array('required'),
            'leftout_premium' => array('required'),
            'leftout_premium_wvat' => array('required'),
            'activation_code' => array('required'),
            'generatedQuoteCode' => array('required'),
            'agent_id' => array('required','integer'),
            'stores' => array('required'),
            'beneficiaries' => array('required'),
            'vinnumber' => array('required'),
            'enginenumber' => array('required'),
            'financial_interest' => array('required'),
            'other_finance' => array('required'),
            'claim_count' => array('required'),
            'estimated_value' => array('required'),
        );

        $validator = Validator::make(Input::all(),$rules);
        if($validator->fails())
        {
            return json_encode($validator->messages());
        } else {
            return 0;
        }
    }

    public static function str_ordinal($value, $superscript = false)
    {
        $number = abs($value);

        $indicators = ['th','st','nd','rd','th','th','th','th','th','th'];

        $suffix = $superscript ? '<sup>' . $indicators[$number % 10] . '</sup>' : $indicators[$number % 10];
        if ($number % 100 >= 11 && $number % 100 <= 13) {
            $suffix = $superscript ? '<sup>th</sup>' : 'th';
        }

        return number_format($number) . $suffix;
    }

    public static function getFrequencyDetails($freq,$date){
        switch($freq){
            case 1:
                return 'every month';
                break;
            case 2:
                return 'for 3 consequtive months';
                break;
            case 3:
                $myDate = $date;
                $date = \Carbon\Carbon::createFromFormat('Y-m-d', $date);

                $monthName = $date->format('F');
                return 'of '.$monthName.' every year';
                break;
            default:
                return 'N/A';
                break;
        }
    }

    public static function getCityName($id){
        $data = City::where('id',$id)->first(['name']);
        if($data){
            return $data->name;
        }else{
            return '-';
        }
    }


}


