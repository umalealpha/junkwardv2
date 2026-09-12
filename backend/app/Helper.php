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
use AlphaDirect\Models\Company;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use AlphaDirect\Region;
use Http\Client\Exception;
use PDF;
use SnappyPDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use AlphaDirect\PolicySonali;
use AlphaDirect\PaymentTransactionSonali;
use AlphaDirect\Models\PaymentTransactionArchiveSonali;
use AlphaDirect\LedgerSonali;
use AlphaDirect\SubLedgerSonali;
use AlphaDirect\CreditNoteSonali;
use AlphaDirect\Models\LedgerArchiveSonali;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\PolicyTerm;

class Helper {

    /**
     * Base URL of the customer-facing start SPA to send a browser back to
     * (DPO return, partner set-password link, etc.).
     *
     * Resolution order:
     *   1. $preferredOrigin — the Origin the browser sent when it started the
     *      flow (e.g. https://start-dev2.alphadirect.co.bw) IF it is one of
     *      the start hosts in config('cors.allowed_origins'). This lets one
     *      backend serve staging and prod start sites correctly.
     *   2. START_URL / START_SPA_URL env — only if it is a start host. On
     *      2026-09-08 staging's START_URL pointed at graphite-v2-fe (the
     *      admin SPA) and customers landed there after paying.
     *   3. https://start-v2.alphadirect.co.bw
     *
     * Never returns a non-start host, whatever the env says.
     */
    public static function startSpaBase(?string $preferredOrigin = null): string
    {
        $allowed = [];
        foreach ((array) config('cors.allowed_origins', []) as $o) {
            $h = parse_url((string) $o, PHP_URL_HOST);
            if ($h && str_starts_with($h, 'start')) {
                $allowed[strtolower($h)] = 'https://' . strtolower($h);
            }
        }
        $pick = function (?string $url) use ($allowed): ?string {
            if (!$url) return null;
            $p = parse_url(trim($url));
            $h = strtolower((string) ($p['host'] ?? ''));
            if ($h === '' || !isset($allowed[$h])) return null;
            if (($p['scheme'] ?? 'https') !== 'https') return null;
            return $allowed[$h];
        };
        if ($base = $pick($preferredOrigin)) {
            return $base;
        }
        $envUrl = env('START_URL') ?: env('START_SPA_URL');
        if ($base = $pick($envUrl)) {
            return $base;
        }
        if ($envUrl) {
            Log::warning('start_spa_base.env_not_start_host', ['START_URL' => $envUrl]);
        }
        return 'https://start-v2.alphadirect.co.bw';
    }
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
     * Add Invoice entries to Ledger
     */
    public static function addInvoiceToLedger($policy_id,$termId,$actionId,$date)
    {
        try {
            //Check if Invoice with same date already exists
            $check_invoice_exists = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->where('invoice_date', $date)->count();
            if($check_invoice_exists == 0)
            {
                $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                if($ledger == NULL)
                {
                    $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                    $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                    //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                }
                $policy = Policy::where('id', $policy_id)->first();

                // MIS/MIB (Instant Insurance) only: $policy->vat historically held the
                // VAT *rate* (e.g. 14) rather than the VAT *amount*, so a flat P14.00 was
                // booked to VAT instead of 14% of the premium. Recompute the true VAT
                // amount from the VAT-inclusive premium. DomCom policies are left untouched.
                $isInstant = in_array(substr((string) $policy->policyNumber, 0, 3), ['MIS', 'MIB'], true);
                if ($isInstant) {
                    $vatPct = (float) ($policy->vat_percent ?: 14);
                    $policy->vat = number_format(
                        (float) $policy->premium - ((float) $policy->premium / (1 + ($vatPct / 100))),
                        2, '.', ''
                    );
                }

                if($ledger != NULL)
                {
                    $invoice_no = $ledger->invoice_no;
                    $invoice_no++;
                    $banking_id = $ledger->banking_id;

                } else { 
                    $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);
                    $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                    if($banking_id != NULL)
                        $banking_id = $banking_id->id;
                    else
                        $banking_id = NULL;
                }
                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                if ($balance != null) {
                    $balance = $balance->balance;
                } else {
                    $balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                    if ($balance != null) {
                        $balance = $balance->balance;
                    } else {
                        $balance = 0;
                    }
                }

                $data = array();
                $record = array();
                $subData = array();
                $subRecord = array();

                //-------------------------------PREMIUM--------------------------------------
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = $termId;
                $record['action_id'] = $actionId;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice Premium';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = str_replace(',', '',number_format(((float)$policy->premium - (float)$policy->vat), 2));
                $record['debit'] = str_replace(',', '',$amt);
                $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = $termId;
                $subRecord['action_id'] = $actionId;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Insurance Sales A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                //-------------------------------PREMIUM--------------------------------------
                //-------------------------------VAT--------------------------------------

                $record = array();

                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = $termId;
                $record['action_id'] = $actionId;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice VAT';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = floatval($policy->vat);

                $record['debit'] = str_replace(',', '',$amt);
                $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2));
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();

                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = $termId;
                $subRecord['action_id'] = $actionId;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'VAT Control A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'VAT on Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                //-------------------------------VAT--------------------------------------
                //-------------------------------INVOICE--------------------------------------

                $record = array();
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = $termId;
                $record['action_id'] = $actionId;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = 1;
                $record['invoice_date'] = Carbon::parse($date);
                $record['invoice_no'] = $invoice_no;
                $record['invoice_amount'] = $policy->premium;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = $policy->premium;
                $record['pmts_adjust'] = NULL;
                // MIS/MIB invoices had no due date (always "—"). Set it to invoice_date + 1
                // month; DomCom keeps its existing NULL behaviour.
                $record['due_date'] = $isInstant ? Carbon::parse($date)->addMonthsNoOverflow(1)->format('Y-m-d') : NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = number_format(((float)str_replace(',', '',$policy->premium) - (float)str_replace(',', '',$policy->vat)), 2);

                $record['debit'] = $policy->premium;
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = $termId;
                $subRecord['action_id'] = $actionId;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Accounts Receivable A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Accounts Receivable';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = NULL;
                $subRecord['debit'] = $policy->premium;

                $subData[] = $subRecord;

                $subData[0]['trans_ref'] = $invoice_no;
                $subData[1]['trans_ref'] = $invoice_no;
                $subData[2]['trans_ref'] = $invoice_no;


                //-------------------------------INVOICE-------------------------------------

                Ledger::insert($data);
                SubLedger::insert($subData);

                return true;

            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }


    public static function addInvoice($policy_id,$amount,$date)
    {
        try {
            //Check if Invoice with same date already exists
            $check_invoice_exists = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->where('invoice_date', $date)->count();
            if($check_invoice_exists == 0)
            {
                $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                if($ledger == NULL)
                {
                    $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                    $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                    //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                }
                $policy = Policy::where('id', $policy_id)->first();
                $policy->premium = $amount;

                // MIS/MIB (Instant Insurance) only: recompute the true VAT amount (14% of
                // the VAT-inclusive premium) rather than booking the flat stored value.
                $isInstant = in_array(substr((string) $policy->policyNumber, 0, 3), ['MIS', 'MIB'], true);
                if ($isInstant) {
                    $vatPct = (float) ($policy->vat_percent ?: 14);
                    $policy->vat = number_format(
                        (float) $policy->premium - ((float) $policy->premium / (1 + ($vatPct / 100))),
                        2, '.', ''
                    );
                }

                if($ledger != NULL)
                {
                    $invoice_no = $ledger->invoice_no;
                    $invoice_no++;
                    $banking_id = $ledger->banking_id;

                } else {
                    $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);
                    $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                    if($banking_id != NULL)
                        $banking_id = $banking_id->id;
                    else
                        $banking_id = NULL;
                }
                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                if ($balance != null) {
                    $balance = $balance->balance;
                } else {
                    $balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                    if ($balance != null) {
                        $balance = $balance->balance;
                    } else {
                        $balance = 0;
                    }
                }
                $balance = $amount;
                $data = array();
                $record = array();
                $subData = array();
                $subRecord = array();

                //-------------------------------PREMIUM--------------------------------------
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = NULL;
                $record['action_id'] = NULL;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice Premium';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = str_replace(',', '',number_format(((float)$policy->premium - (float)$policy->vat), 2));
                $record['debit'] = str_replace(',', '',$amt);
                $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = NULL;
                $subRecord['action_id'] = NULL;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Insurance Sales A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                //-------------------------------PREMIUM--------------------------------------
                //-------------------------------VAT--------------------------------------

                $record = array();

                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = NULL;
                $record['action_id'] = NULL;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice VAT';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = floatval($policy->vat);

                $record['debit'] = str_replace(',', '',$amt);
                $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2));
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();

                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = NULL;
                $subRecord['action_id'] = NULL;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'VAT Control A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'VAT on Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                //-------------------------------VAT--------------------------------------
                //-------------------------------INVOICE--------------------------------------

                $record = array();
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = NULL;
                $record['action_id'] = NULL;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = 1;
                $record['invoice_date'] = Carbon::parse($date);
                $record['invoice_no'] = $invoice_no;
                $record['invoice_amount'] = $amount;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = $policy->premium;
                $record['pmts_adjust'] = NULL;
                // MIS/MIB invoices had no due date (always "—"). Set it to invoice_date + 1
                // month; DomCom keeps its existing NULL behaviour.
                $record['due_date'] = $isInstant ? Carbon::parse($date)->addMonthsNoOverflow(1)->format('Y-m-d') : NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = number_format(((float)str_replace(',', '',$policy->premium) - (float)str_replace(',', '',$policy->vat)), 2);

                $record['debit'] = $policy->premium;
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = NULL;
                $subRecord['action_id'] = NULL;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Accounts Receivable A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Accounts Receivable';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = NULL;
                $subRecord['debit'] = $policy->premium;

                $subData[] = $subRecord;

                $subData[0]['trans_ref'] = $invoice_no;
                $subData[1]['trans_ref'] = $invoice_no;
                $subData[2]['trans_ref'] = $invoice_no;


                //-------------------------------INVOICE-------------------------------------

                Ledger::insert($data);
                SubLedger::insert($subData);

                return true;

            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
    /*
     * Add Invoice entries to Ledger
     */

     public static function addInvoiceComDom($policy_id,$amount,$date,$description)
    {
        try {
            //Check if Invoice with same date already exists
            $check_invoice_exists = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->where('invoice_date', $date)->count();
            if($check_invoice_exists == 0)
            {
                
                $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                if($ledger == NULL)
                {
                    $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                    $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                    //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                }
                $policy = Policy::where('id', $policy_id)->first();
                
                $policy->premium = str_replace(',', '',$amount);
                if($ledger != NULL)
                {
                    $invoice_no = $ledger->invoice_no;
                    $invoice_no++;
                    $banking_id = $ledger->banking_id;

                } else {
                    $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);
                    $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                    if($banking_id != NULL)
                        $banking_id = $banking_id->id;
                    else
                        $banking_id = NULL;
                }
                
                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                if ($balance != null) {
                    $balance = $balance->balance;
                } else {
                    $balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                    if ($balance != null) {
                        $balance = $balance->balance;
                    } else {
                        $balance = 0;
                    }
                }
                
                $balance = $amount;
                $data = array();
                $record = array();
                $subData = array();
                $subRecord = array();

                //-------------------------------PREMIUM--------------------------------------
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = NULL;
                $record['action_id'] = NULL;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date)->format('Y-m-d');
                $record['trans_type'] = 'Invoice Premium';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] =  Carbon::parse($date)->format('Y-m-d');
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] =  Carbon::parse($date)->format('Y-m-d');
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;
                $record['description'] = $description;

                $amt = $policy->premium;//str_replace(',', '',number_format(((float)$policy->premium - (float)$policy->vat), 2));
                $record['debit'] = str_replace(',', '',$amt);
                $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = NULL;
                $subRecord['action_id'] = NULL;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Insurance Sales A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;
               
                //-------------------------------PREMIUM--------------------------------------
                //-------------------------------VAT--------------------------------------

                $record = array();

                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = NULL;
                $record['action_id'] = NULL;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice VAT';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;
                $record['description'] = $description;

                //$amt = floatval($policy->vat);

                $record['debit'] = str_replace(',', '',$amt);
                $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2));
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();

                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = NULL;
                $subRecord['action_id'] = NULL;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'VAT Control A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'VAT on Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                //-------------------------------VAT--------------------------------------
                //-------------------------------INVOICE--------------------------------------

                $record = array();
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = NULL;
                $record['action_id'] = NULL;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = 1;
                $record['invoice_date'] = Carbon::parse($date);
                $record['invoice_no'] = $invoice_no;
                $record['invoice_amount'] = $policy->premium;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = $policy->premium;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;
                $record['description'] = $description;

                //$amt = number_format(((float)str_replace(',', '',$policy->premium) - (float)str_replace(',', '',$policy->vat)), 2);

                $record['debit'] = $policy->premium;
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = NULL;
                $subRecord['action_id'] = NULL;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Accounts Receivable A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Accounts Receivable';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = NULL;
                $subRecord['debit'] = $policy->premium;

                $subData[] = $subRecord;

                $subData[0]['trans_ref'] = $invoice_no;
                $subData[1]['trans_ref'] = $invoice_no;
                $subData[2]['trans_ref'] = $invoice_no;


                //-------------------------------INVOICE-------------------------------------
                
                Ledger::insert($data);
                SubLedger::insert($subData);

                return true;

            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
    public static function addProRatedInvoiceToLedger($policy_id,$termId,$actionId,$date)
    {
        try {
            $total_pro_rate_premium = 0;
            //Main Coverage & Risk Address
            $policy_coverages = PolicyCoverage::Policy($policy_id)->Term($termId)->Action($actionId)->orderBy('risk_address_id','asc')->orderBy('coverage_id', 'asc')->get();
            foreach($policy_coverages as $coverages){
                $all_sub_coverages = CoverageMaster::where('s_ParentCoverageCode',$coverages->coverage->s_CoverageCode)->where('s_UsageType','CHILD')->where('isDocDisplay','Y')->get();
                $coverages->all_sub_coverages = $all_sub_coverages;
                if($coverages->coverageDetail){
                    $total_pro_rate_premium = $coverages->coverageDetail->sum('pro_rate_premium');
                }
            }

            if($total_pro_rate_premium > 0)
            {
                $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                if($ledger == NULL)
                {
                    $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                    $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                    //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');;
                }
                $policy = Policy::where('id', $policy_id)->first();
                $policy->premium = $total_pro_rate_premium;

                // MIS/MIB (Instant Insurance) only: recompute the true VAT amount (14% of
                // the VAT-inclusive premium) rather than booking the flat stored value.
                $isInstant = in_array(substr((string) $policy->policyNumber, 0, 3), ['MIS', 'MIB'], true);
                if ($isInstant) {
                    $vatPct = (float) ($policy->vat_percent ?: 14);
                    $policy->vat = number_format(
                        (float) $policy->premium - ((float) $policy->premium / (1 + ($vatPct / 100))),
                        2, '.', ''
                    );
                }

                if($ledger != NULL)
                {
                    $invoice_no = $ledger->invoice_no;
                    $invoice_no++;
                    $banking_id = $ledger->banking_id;

                } else {
                    $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);
                    $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                    if($banking_id != NULL)
                        $banking_id = $banking_id->id;
                    else
                        $banking_id = NULL;
                }
                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                if ($balance != null) {
                    $balance = $balance->balance;
                } else {
                    $balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                    if ($balance != null) {
                        $balance = $balance->balance;
                    } else {
                        $balance = 0;
                    }
                }

                $data = array();
                $record = array();
                $subData = array();
                $subRecord = array();

                //-------------------------------PREMIUM--------------------------------------
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = $termId;
                $record['action_id'] = $actionId;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice Premium';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = str_replace(',', '',number_format(((float)$policy->premium - (float)$policy->vat), 2));
                $record['debit'] = str_replace(',', '',$amt);
                $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = $termId;
                $subRecord['action_id'] = $actionId;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Insurance Sales A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                //-------------------------------PREMIUM--------------------------------------
                //-------------------------------VAT--------------------------------------

                $record = array();

                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = $termId;
                $record['action_id'] = $actionId;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice VAT';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = floatval($policy->vat);

                $record['debit'] = str_replace(',', '',$amt);
                $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2));
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();

                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = $termId;
                $subRecord['action_id'] = $actionId;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'VAT Control A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'VAT on Insurance Premium';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = $amt;
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                //-------------------------------VAT--------------------------------------
                //-------------------------------INVOICE--------------------------------------

                $record = array();
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['term_id'] = $termId;
                $record['action_id'] = $actionId;
                $record['claim_id'] = NULL;
                $record['banking_id'] = $banking_id;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($date);
                $record['trans_type'] = 'Invoice';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = NULL;
                $record['orig_trans'] = NULL;
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($date);
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($date);
                $record['invoice_file'] = 1;
                $record['invoice_date'] = Carbon::parse($date);
                $record['invoice_no'] = $invoice_no;
                $record['invoice_amount'] = $policy->premium;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = $policy->premium;
                $record['pmts_adjust'] = NULL;
                // MIS/MIB invoices had no due date (always "—"). Set it to invoice_date + 1
                // month; DomCom keeps its existing NULL behaviour.
                $record['due_date'] = $isInstant ? Carbon::parse($date)->addMonthsNoOverflow(1)->format('Y-m-d') : NULL;
                $record['status'] = 'Pending';
                $record['credit'] = NULL;

                $amt = number_format(((float)str_replace(',', '',$policy->premium) - (float)str_replace(',', '',$policy->vat)), 2);

                $record['debit'] = $policy->premium;
                $record['balance'] = str_replace(',', '',$balance);

                $data[] = $record;

                //----SUB-LEDGER

                $subRecord = array();
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['term_id'] = $termId;
                $subRecord['action_id'] = $actionId;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = $banking_id;
                $subRecord['account_name'] = 'Accounts Receivable A/C';
                $subRecord['accounting_date'] = Carbon::parse($date);
                $subRecord['trans_type'] = 'Accounts Receivable';
                $subRecord['trans_ref'] = NULL;
                $subRecord['system_date'] = Carbon::parse($date);
                $subRecord['credit'] = NULL;
                $subRecord['debit'] = $policy->premium;

                $subData[] = $subRecord;

                $subData[0]['trans_ref'] = $invoice_no;
                $subData[1]['trans_ref'] = $invoice_no;
                $subData[2]['trans_ref'] = $invoice_no;


                //-------------------------------INVOICE-------------------------------------

                Ledger::insert($data);
                SubLedger::insert($subData);

                return true;

            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }


    /*
     * generayes invoice for specific policy from ledger tab
     * param: ledger entries for specific policies
     */
    public static function generateInvoice($ledger_id)
    {
        $date = Carbon::now()->timestamp;

        $invoice = Ledger::where('id', $ledger_id)->first(array('action_id','term_id','trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id'));
        if($invoice == NULL)
        {
            $invoice = LedgerArchive::where('id', $ledger_id)->first(array('action_id','term_id','trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id'));
            $vat = LedgerArchive::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
        } else {
            $vat = Ledger::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
        }
        $policy = Policy::where('id', $invoice->policy_id)->first(array('id','product_id','term_id','policyNumber','premium', 'premium_freq'));
        // if($policy->premium_freq == 3)
        //     $endDate = Carbon::parse($invoice->invoice_date)->addYear()->subDay()->format('d/m/Y');
        // else
        //     $endDate = Carbon::parse($invoice->due_date)->subDay()->format('d/m/Y');

        $policyAction = PolicyAction::where('id',$invoice->action_id)->first();
        if($policyAction){
            $invoiceEndDate =  Carbon::parse($policyAction->effective_to)->format('d/m/Y');
            $invoiceStartDate =  Carbon::parse($policyAction->effective_from)->format('d/m/Y');
            $note = $policyAction->note;
        }else{
            $note = 'Gross Premium';
            if($policy->premium_freq == 3){
                $invoiceEndDate = Carbon::parse($invoice->invoice_date)->addYear()->subDay()->format('d/m/Y');
                $invoiceStartDate = Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            }else{
                $invoiceEndDate = Carbon::parse($invoice->due_date)->subDay()->format('d/m/Y');
                $invoiceStartDate = Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            }
        }
        // vat calculations
        $regionVat = Region::where('id', 7)->first('vat')?->vat;
        $premiumExcludingVAT = $invoice->invoice_amount / (1 + ($regionVat/100));
        if(($policy->premium_freq)==1){
            $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
        }
        $vat = $premiumExcludingVAT * ($regionVat/100);
        $ServiceCharge8 = $premiumExcludingVAT*(8/100);
        $vatServiceCharge = $ServiceCharge8*($regionVat/100);
        $customerProfile = CustomerProfile::where('customer_id', $invoice->customer_id)->first(array('address','entity_type','company_id'));
        $company = Company::where('id', $customerProfile->company_id)->first(['id','name']);

        $data = [
            'invoice'             => $invoice,
            'customer'            => Customer::where('id', $invoice->customer_id)->first(array('firstName', 'lastName')),
            'customerProfile'     => $customerProfile,
            'company'             => $company,
            'invoiceEndDate'      => $invoiceEndDate,
            'invoiceStartDate'    => $invoiceStartDate,
            'policy'              => $policy,
            'premiumExcludingVAT' => $premiumExcludingVAT,
            'vat'                 => $vat,
            'ServiceCharge8'      => $ServiceCharge8,
            'vatServiceCharge'    => $vatServiceCharge,
            'policyAction'        => $policyAction,
            'note'        => $note,
        ];

        $pdf = PDF::loadView('admin.subLedger.invoice', $data);

        $filePath = 'MIS/' .$invoice->policy_id . '/'.'Customer/'.$invoice->customer_id.'/'.'Invoice/'.$date.'_'.$invoice->invoice_no.'.pdf' ;
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

    public static function generateInvoiceDomCom($ledger_id)
    {
        
        $date = Carbon::now()->timestamp;

        $invoice = Ledger::where('id', $ledger_id)->first(array('action_id','term_id','trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id','eff_date','description'));
        
        if($invoice == NULL)
        {
            $invoice = LedgerArchive::where('id', $ledger_id)->first(array('action_id','term_id','trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id','eff_date','description'));
            $vat = LedgerArchive::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
        } else {
            $vat = Ledger::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
        }
        $policy = Policy::where('id', $invoice->policy_id)->first(array('id','product_id','term_id','policyNumber','premium', 'premium_freq'));
    $productId=$policy->product_id;
        $policySum = Ledger::where('policy_id', $policy->id)->sum('premium');
        if($policySum == NULL)
        {
            $policySum = LedgerArchive::where('policy_id', $policy->id)->sum('premium');
        }        
        $invoiceStartDate = date('d/m/Y',strtotime($invoice->invoice_date));//Carbon::parse($invoice->invoice_date)->format('d/m/Y');
        
        $invoiceCnt = Ledger::where('policy_id', $policy->id)->where('trans_type','Invoice')->count();

        //$invoiceEndDate = Carbon::parse($invoice->eff_date)->subDay()->format('d/m/Y');

        if($policy->premium_freq == 3)
            $invoiceEndDate = Carbon::parse($invoice->invoice_date)->addYear()->subDay()->format('d/m/Y');
        else
            $invoiceEndDate = Carbon::parse($invoice->invoice_date)->subMonth()->format('d/m/Y');        
        
       
        $policyAction = PolicyAction::where('id',$invoice->action_id)->first();
       
        $invoiceNo = explode("-",$invoice->invoice_no);

        $policy_term = PolicyTerm::where('policy_id',$invoice->policy_id)
       // ->where('id',$invoice->term_id)
        ->orderby('id','desc')
        ->first(['term_start_date','term_end_date']);
        if($invoiceNo[1] == '001')
        {
            $note = isset($policyAction->note) ? $policyAction->note : 'New Business'; 
            $term_start_date = Carbon::parse($policy_term->term_start_date)->format('d/m/Y');
            $term_end_date = Carbon::parse($policy_term->term_end_date)->format('d/m/Y');
        }elseif (isset($policyAction) && $policyAction->transaction_type == 'ANNIVERSARY-RENEW') {

            $note = 'Anniversary Renewal';
            $term_start_date = date('d/m/Y', strtotime("+".($invoiceNo[1]-1) ." months", strtotime($policy_term->term_start_date)));
            $term_end_date   = date('d/m/Y', strtotime("+".($invoiceNo[1]-1)." months", strtotime($policy_term->term_end_date)));
        }
        elseif(isset($policyAction) &&  $policyAction->note!=null){
            $note = $policyAction->note;
            $term_start_date = date('d/m/Y', strtotime("+".($invoiceNo[1]-1) ." months", strtotime($policy_term->term_start_date)));
            $term_end_date   = date('d/m/Y', strtotime("+".($invoiceNo[1]-1)." months", strtotime($policy_term->term_end_date)));
        }
        else
        {
            $inVNumber = (ltrim($invoiceNo[1], '0') - 1);
         
            $note = 'Batch renewal as expiry';
            $term_start_date = date('d/m/Y', strtotime("+".($inVNumber) ." months", strtotime($policy_term->term_start_date)));
            $term_end_date   = date('d/m/Y', strtotime("+".($inVNumber)." months", strtotime($policy_term->term_end_date)));
        }
        //dd($term_start_date,$term_end_date);
        // vat calculations
        $regionVat = Region::where('id', 7)->first('vat')?->vat;
        $premiumExcludingVAT = $invoice->invoice_amount / (1 + ($regionVat/100));
        if(($policy->premium_freq)==1){
            $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
        }
        $vat = $premiumExcludingVAT * ($regionVat/100);
        $ServiceCharge8 = $premiumExcludingVAT*(8/100);
        $vatServiceCharge = $ServiceCharge8*($regionVat/100);
        $customerProfile = CustomerProfile::where('customer_id', $invoice->customer_id)->first(array('address','entity_type','company_id'));
        $company = Company::where('id', $customerProfile->company_id)->first(['id','name','VAT_registration_number']);
        
        $risk_address = RiskAddress::policy($invoice->policy_id)->term($invoice->term_id)->action($invoice->action_id)->orderby('id','desc')->first(['address_name','risk_city']);
        $getCity = null;
        if (isset($risk_address)) {
            $getCity = \AlphaDirect\City::where('id', $risk_address->risk_city)->first('name');
        }
 
        
        
        $data = [
            'invoice'             => $invoice,
            'customer'            => Customer::where('id', $invoice->customer_id)->first(array('firstName', 'lastName')),
            'customerProfile'     => $customerProfile,
            'company'             => $company,            
            'invoiceStartDate'    => $invoiceStartDate,
            'invoiceEndDate'      => $invoiceEndDate,
            'policy'              => $policy,
            'premiumExcludingVAT' => $premiumExcludingVAT,
            'vat'                 => $vat,
            'ServiceCharge8'      => $ServiceCharge8,
            'vatServiceCharge'    => $vatServiceCharge,
            'policyAction'        => $policyAction,
            'productId'        => $productId,
            'note'        => $note,
            'policySum' =>$policySum,
            'risk_address' => $risk_address,
            'getCity' => $getCity,
            'policy_term' => $policy_term,
            'term_start_date' => $term_start_date,
            'term_end_date' => $term_end_date,
        ];
     
        //$pdf = PDF::loadView('v2.livewire.policy.invoice.invoice', $data);
      // return view('v2/livewire/policy/invoice/invoice', $data);
       $pdf = \SnappyPDF::loadView('v2/livewire/policy/invoice/invoice', $data);

        //$pdf = \PDF::loadView('v2/livewire/policy/invoice/invoice', $data);
        if($invoice->invoice_amount <= 0){
        $filePath = 'dom_com_invoice/'.$policy->policyNumber.'_'.$date.'_creditNote.pdf';
        } else{
        $filePath = 'dom_com_invoice/'.$policy->policyNumber.'_'.$date.'_invoice.pdf';
        }
        Storage::disk('public')->put($filePath , $pdf->output());
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
     * generayes invoice for specific policy from ledger tab
     * param: ledger entries for specific policies
     */
    public static function generateInvoiceSonali($ledger_id)
    {
        $date = Carbon::now()->timestamp;

        $invoice = LedgerSonali::where('id', $ledger_id)->first(array('action_id','term_id','trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id'));
        if($invoice == NULL)
        {
            $invoice = LedgerArchiveSonali::where('id', $ledger_id)->first(array('action_id','term_id','trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id'));
            $vat = LedgerArchiveSonali::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
        } else {
            $vat = LedgerSonali::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
        }
        $policy = Policy::where('id', $invoice->policy_id)->first(array('id','product_id','term_id','policyNumber','premium', 'premium_freq'));
        // if($policy->premium_freq == 3)
        //     $endDate = Carbon::parse($invoice->invoice_date)->addYear()->subDay()->format('d/m/Y');
        // else
        //     $endDate = Carbon::parse($invoice->due_date)->subDay()->format('d/m/Y');

        $policyAction = PolicyAction::where('id',$invoice->action_id)->first();
        if($policyAction){
            $invoiceEndDate =  Carbon::parse($policyAction->effective_to)->format('d/m/Y');
            $invoiceStartDate =  Carbon::parse($policyAction->effective_from)->format('d/m/Y');
            $note = $policyAction->note;
        }else{
            $note = 'Gross Premium';
            if($policy->premium_freq == 3){
                $invoiceEndDate = Carbon::parse($invoice->invoice_date)->addYear()->subDay()->format('d/m/Y');
                $invoiceStartDate = Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            }else{
                $invoiceEndDate = Carbon::parse($invoice->due_date)->subDay()->format('d/m/Y');
                $invoiceStartDate = Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            }
        }
        // vat calculations
        $regionVat = Region::where('id', 7)->first('vat')?->vat;
        $premiumExcludingVAT = $invoice->invoice_amount / (1 + ($regionVat/100));
        if(($policy->premium_freq)==1){
            $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
        }
        $vat = $premiumExcludingVAT * ($regionVat/100);
        $ServiceCharge8 = $premiumExcludingVAT*(8/100);
        $vatServiceCharge = $ServiceCharge8*($regionVat/100);
        $customerProfile = CustomerProfile::where('customer_id', $invoice->customer_id)->first(array('address','entity_type','company_id'));
        $company = Company::where('id', $customerProfile->company_id)->first(['id','name']);

        $data = [
            'invoice'             => $invoice,
            'customer'            => Customer::where('id', $invoice->customer_id)->first(array('firstName', 'lastName')),
            'customerProfile'     => $customerProfile,
            'company'             => $company,
            'invoiceEndDate'      => $invoiceEndDate,
            'invoiceStartDate'    => $invoiceStartDate,
            'policy'              => $policy,
            'premiumExcludingVAT' => $premiumExcludingVAT,
            'vat'                 => $vat,
            'ServiceCharge8'      => $ServiceCharge8,
            'vatServiceCharge'    => $vatServiceCharge,
            'policyAction'        => $policyAction,
            'note'        => $note,
        ];

        $pdf = PDF::loadView('admin.subLedgerSonali.invoice', $data);

        $filePath = 'MIS/' .$invoice->policy_id . '/'.'Customer/'.$invoice->customer_id.'/'.'Invoice/'.$date.'_'.$invoice->invoice_no.'.pdf' ;
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

      public static function generateInvoiceDomComIssued($policyid,$actionId,$invoicedate)
            {
            
                try {
                    
                    $today = Carbon::now()->format('d');//Carbon::today();            
                    $date = Carbon::now()->format('Y-m-d');

                    // 19 (SpecialistDOM) added: DomCom + Specialist are now billed
                    // action-wise only — the calendar-driven ledger crons no longer
                    // invoice them — so every product in that family must have an
                    // issue-time invoice path here or it would raise none at all.
                    $policiesbyBilled = Policy::
                    whereIn('product_id',[7,8,16,17,18,19,20,22,23,24])
                    ->whereIn('status', [1,2])
                    ->where('id',$policyid)
                    ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 
                    'created_at', 'updated_at', 'first_premium', 'annual_premium' ,
                    'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate',
                     'is_sys_act_generated', 'billingStartDate', 'status'))
                    ->get();
                    $policies = $policiesbyBilled->chunk(1000);
                    
                    if($policies->isEmpty())
                    {
                        Log::info('Invoices data Not found');
                    }else{
                    foreach($policies as $records) {
                        foreach($records as $policy)
                        {
                            $policy_id = $policy->id;  
            
                            $policyAction = PolicyAction::where('policy_id', $policy_id)->where('id',$actionId)->where('status', 'ISSUED')->get();  
                            foreach($policyAction as $policyActions){
                            $termId = isset($policyActions->term_id ) ? $policyActions->term_id : 0;
                            $actionId = isset($policyActions->id ) ? $policyActions->id : 0;
                            // ->where('action_id', $policyActions->id)
                            $check_invoice_exists = Ledger::where('policy_id', $policy_id)
                            ->where('trans_type', 'Invoice')
                            ->whereNull('deleted_at')
                            ->where('action_id', $actionId)
                            ->count();
                            
                            if($check_invoice_exists ==0 && ($policy->premium_freq == 1 || $policy->premium_freq == 2 || $policy->premium_freq == 3 || $policy->premium_freq == 5 || $policy->premium_freq == 6)){
                                $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                                $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                                // if($ledger == NULL)
                                // {
                                //     $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                                //     $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                                //     //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');.
                                // }
                                $policy = Policy::where('id', $policy_id)->first();
                                if($ledger != NULL)
                                {
                                    $invoice_no = $ledger->invoice_no;
                                    $invoice_no++;
                                    $banking_id = $ledger->banking_id;
            
                                } else {
                                    $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);
                                    $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                                    if($banking_id != NULL)
                                        $banking_id = $banking_id->id;
                                    else
                                        $banking_id = NULL;
                                }
                                $balance = 0;
                                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                                if ($balance != null) {
                                    $balance = $balance->balance;
                                } else {
                                    //$balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                                    if ($balance != null) {
                                        $balance = $balance->balance;
                                    } else {
                                        $balance = 0;
                                    }
                                }
                                $data = array();
                                $record = array();
                                $subData = array();
                                $subRecord = array();
            
                                //-------------------------------PREMIUM--------------------------------------
                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice Premium';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = NULL;
                                $record['invoice_date'] = NULL;
                                $record['invoice_no'] = NULL;
                                $record['invoice_amount'] = NULL;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = NULL;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;
            
                                $amt = str_replace(',', '',number_format(((float)$policyActions->premium - (float)$policy->vat), 2));
                                $record['debit'] = str_replace(',', '',$amt);
                                $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                                $record['balance'] = str_replace(',', '',$balance);
            
                                $data[] = $record;
            
            
                                //----SUB-LEDGER
                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'Insurance Sales A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'Insurance Premium';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = $amt;
                                $subRecord['debit'] = NULL;
            
                                $subData[] = $subRecord;
            
                                //-------------------------------PREMIUM--------------------------------------
                                //-------------------------------VAT--------------------------------------
            
                                $record = array();
            
                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice VAT';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = NULL;
                                $record['invoice_date'] = NULL;
                                $record['invoice_no'] = NULL;
                                $record['invoice_amount'] = NULL;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = NULL;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;
            
                                $amt = floatval($policy->vat);
            
                                $record['debit'] = str_replace(',', '',$amt);
                                $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2));
                                $record['balance'] = str_replace(',', '',$balance);
            
                                $data[] = $record;
            
                                //----SUB-LEDGER
            
                                $subRecord = array();
            
                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'VAT Control A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'VAT on Insurance Premium';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = $amt;
                                $subRecord['debit'] = NULL;
            
                                $subData[] = $subRecord;
            
                                //-------------------------------VAT--------------------------------------
                                //-------------------------------INVOICE--------------------------------------
            
                                $record = array();
                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = 1;
                                $record['invoice_date'] = Carbon::parse($policyActions->effective_from);
                                $record['invoice_no'] = $invoice_no;
                                $record['invoice_amount'] = $policyActions->premium;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = $policyActions->premium;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;
            
                                $amt = number_format(((float)str_replace(',', '',$policyActions->premium) - (float)str_replace(',', '',$policy->vat)), 2);
            
                                $record['debit'] = $policyActions->premium;
                                $record['balance'] = str_replace(',', '',$balance);
            
                                $data[] = $record;
            
                                //----SUB-LEDGER
            
                                $subRecord = array();
                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'Accounts Receivable A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'Accounts Receivable';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = NULL;
                                $subRecord['debit'] = $policyActions->premium;
            
                                $subData[] = $subRecord;
            
                                $subData[0]['trans_ref'] = $invoice_no;
                                $subData[1]['trans_ref'] = $invoice_no;
                                $subData[2]['trans_ref'] = $invoice_no;
                                //-------------------------------INVOICE-------------------------------------
                                Ledger::insert($data);
                                SubLedger::insert($subData);
                                Log::info('Invoices data',$data);
                            }
                    
            
            
                        }
                        }
                    }
                
                }
            
                } catch (\Exception $e) {
                    Log::error('Error in generateInvoice method: ' . $e->getMessage(), ['exception' => $e]);
                    return false;
                }
            }
}


