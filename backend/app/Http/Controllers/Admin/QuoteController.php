<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Activation;
use AlphaDirect\Banks;
use AlphaDirect\Models\PolicyRenewal;
use Log;
use AlphaDirect\BlackListIp;
use AlphaDirect\Config;
use AlphaDirect\Customer;
use AlphaDirect\KycCompliance;
use AlphaDirect\CustomerProfile;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\DiscountSurcharge;
use AlphaDirect\Helper;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\MonthRateSetting;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\PaymentTransactionArchive;
use AlphaDirect\PolicyAppliedDiscount;
use AlphaDirect\Region;
use AlphaDirect\Stores;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\CustomerBanking;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\RealpayCancelRequests;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Mail;
use Hash;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Mockery\Exception;
use Redirect;
use PDF;
use File;
use AlphaDirect\ReratedPremiumQuote;
use AlphaDirect\Exports\MotorCompExport;
use AlphaDirect\Http\Controllers\FrontendPay\CustomerController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\UserPassword;
use AlphaDirect\UserProfile;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;
use AlphaDirect\VehicleMake;
use AlphaDirect\Http\Controllers\WhatsAppController;


class QuoteController extends Controller
{

    public function store(Request $request)
    {
       
        try {
           
            $data = $request->all();
          
            unset($data['premiumFrequency'], $data['email'], $data['premium'], $data['middleName'], $data['omang'], $data['passport'], $data['omangexpiry'], $data['passportexpiry'], $data['passportIssuingCountry'], $data['model_other']);

            /*   if(isset($data['passport']) && isset($data['omang']) && $data['passport'] == null && $data['omang'] == null){
               return response()->json(['status' => 'Failed', 'message' => 'Either omang or passport is mandatory'], 401);
           }elseif(isset($data['passport']) && isset($data['omang']) && $data['passport'] != null && $data['omang'] == null){
               unset($data['omang']);
               if(isset($data['omangexpiry'])){
                   unset($data['omangexpiry']);
               }
               if(isset( $data['passportexpiry']) && $data['passportexpiry'] == ""){
                unset($data['passportexpiry']);
               }
               if(isset( $data['passportIssuingCountry']) && $data['passportIssuingCountry'] == ""){
                unset($data['omangexpiry']);
               }
           }else{
               unset($data['passport']);
               if(isset($data['passportexpiry'])){
                   unset($data['passportexpiry']);
               }
               if(isset($data['passportIssuingCountry'])){
                   unset($data['passportIssuingCountry']);
               }
               if(isset($data['omangexpiry']) && $data['omangexpiry'] == ""){
                unset($data['omangexpiry']);
               }
           } */

            if (isset($data['country'])) {
                unset($data['country']);
            }
            if (isset($data['state'])) {
                unset($data['state']);
            }
            if (isset($data['city'])) {
                unset($data['city']);
            }
            if (isset($data['variant'])) {
                unset($data['variant']);
            }
          

            if ($data['agentID'] == null &&  $data['stores'] == null) {
                unset($data['agentID'], $data['stores']);
            }

            if (isset($data['agentID']) && $data['agentID'] == null && isset($data['stores']) && $data['stores'] != null) {
                return response()->json(['status' => 'Failed', 'message' => 'Agent id is required when store is selected'], 401);
            }

            if (isset($data['agentID']) && $data['agentID'] != null && isset($data['stores']) && $data['stores'] == null) {
                return response()->json(['status' => 'Failed', 'message' => 'Store is required when agent is selected'], 401);
            }

            foreach ($data as $key => $d) {
                if ($d == '' || $d == null) {
                    return response()->json(['status' => 'Failed', 'message' => $key . ' is mandatory'], 401);
                }
            }

            $dateOfBirth =  Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');

            $years = Carbon::parse($dateOfBirth)->age;

            if ($years < 18) {
                return response()->json(['status' => 'Failed', 'message' => 'Please select valid date'], 401);
            }

            $ip = BlackListIp::where('ip', $request->ip())->first();

            if ($ip == null || ($ip && $ip->status == 0) || $request->agentID != null) {
                if ($request->omang || $request->passport) {
                    if ($request->omang != null)
                        $id = htmlspecialchars(strip_tags($request->omang));
                    else
                        $id = htmlspecialchars(strip_tags($request->passport));

                    $phone = htmlspecialchars(strip_tags($request->cellphone));

                    $customerId = $this->checkCustomer($phone, $id);

                    $customer = Customer::where('id', $customerId)->first();

                    if ($customerId == null) {
                        $create = $this->createCustomer($request);
                        $customerId = $create;
                    } else {
                        if ($customer->customer_category == 2 && isset($data['agentID'])){
                            $user = User::where('id',$data['agentID'])->where('create_high_risk_quote',1)->first();
                            if (isset($user)) {
                                $customer->customer_category = 0;
                            }
                        }

                        if ($customer->customer_category == '2' || $customer->is_blocked == "1") {
                            return response()->json(['status' => 'Failed', 'message' => 'Please note this customer is blocked, we cant process this application'], 401);
                        }
                        $update = $this->updateCustomer($request, $customerId);
                        if ($update == false) {
                            return response()->json(['status' => 'Failed', 'message' => 'Quote can not be processed. Please contact administrator'], 401);
                        }
                        $customerId = $update;
                    }

                    if ($request->agentID == null) {
                        $check = $this->checkCustomerQuotes($customerId);
                        if ($check == true) {
                            $ip = BlackListIp::where('ip', $request->ip())->first();

                            if ($ip == null)
                                $blackList = new BlackListIp();
                            else
                                $blackList = BlackListIp::where('ip', $request->ip())->first();

                            $blackList->ip = $request->ip();
                            $blackList->status = 1;
                            $blackList->save();

                            return response()->json(['status' => 'Failed', 'message' => 'Customer quote limits exceed'], 406);
                        }
                    } else {
                        $check = true;
                        //Release IP , if agent is involved and ip is blocked
                        $ip = BlackListIp::where('ip', $request->ip())->first();
                        if ($ip != null) {
                            $ip->status = 0;
                            $ip->save();
                        }
                    }
                    if ($request->productId == 3) {
                        $store = $this->storeMotorComprehensiveQuote($request, $customerId);
                        if (isset($customer)) {
                            if(isset($customer->mati_identity) && $customer->mati_identity != NULL  && $customer->mati_identity != '')
                            {
                                //$customerDetails->mati_identity = $customerDetails->mati_identity;
                                //$customerDetails->mati_id = $customerDetails->mati_identity;
                            } else {
                                $customer->mati_identity = NULL;
                                //$customerDetails->mati_id = NULL;
                            }
                        } else {
                            $customer = null;
                        }

                        if ($store != false)
                            return response()->json(['status' => 'success', 'QuoteNumber' => $store, 'customer' => $customer], 200);
                        else
                            return response()->json(['status' => 'Something went wrong'], 401);
                    }
                } else {
                    return response()->json(['status' => 'Failed', 'message' => 'Please provide Omang ID number to save quote'], 401);
                }
            } else {
                return response()->json(['status' => 'Failed', 'message' => 'Customer quote limits exceed'], 406);
            }
        } catch (\Mockery\Exception $ex) {
            return response()->json(['status' => $ex], 401);
        }
    }

    public function updateCustomer($request, $id)
    {
        try {
            $customer = Customer::where('id', $id)->first();
            $customer->firstName = ($request->firstName == null) ? ucfirst($customer->firstName) : $request->firstName;
            $customer->lastName = ($request->lastName == null) ? ucfirst($customer->lastName) : $request->lastName;
            $customer->middleName = ($request->middleName == null) ? ucfirst($customer->middleName) : $request->middleName;
            $customer->email = ($request->email == null) ? ucfirst($customer->email) : $request->email;
            $customer->cellphone = ($request->cellphone == null) ? ucfirst($customer->cellphone) : $request->cellphone;
            /* if ($request->omang != "") {
                $customer->password = Hash::make($request->omang);
            } elseif ($request->passport != "") {
                $customer->password = Hash::make($request->passport);
            } else {
                $customer->password = Hash::make(111111);
            } */
            $savedCustomer = $customer->save();

            $ifExists = CustomerProfile::where('customer_id',$customer->id)->exists();
            if(!empty($ifExists))
            {
                $profile = CustomerProfile::where('customer_id',$customer->id)->first();
            }else{
                /*Add Records to Customers Profile table*/
                $profile = new CustomerProfile();
            }

            $profile->customer_id = $customer->id;
            $profile->gender =  $request->gender == "Male" ? 1 : 0;
            $profile->dob = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
            $profile->maritalstatus = $request->maritalstatus;
            $profile->driving_license_number = ($request->driving_license_number == null) ? $profile->driving_license_number : $request->driving_license_number;
            $profile->license_valid_till     = ($request->license_valid_till == null) ? $profile->license_valid_till : $request->license_valid_till;
            $profile->omang = ($request->omang == null) ? $profile->omang : $request->omang;
            $profile->passport = ($request->passport == null) ? $profile->passport : $request->passport;
            $profile->countryId = ($request->countryId == null) ? $profile->countryId : $request->countryId;
            $profile->address = ($request->address == null) ? $profile->address : $request->address;
            $profile->city = ($request->city == null) ? $profile->city : $request->city;
            $profile->state = ($request->state == null) ? $profile->state : $request->state;
            $savedCustomerProfile = $profile->save();

            if ($savedCustomerProfile == true) {
                return $customer->id;
            } else {
                return null;
            }
        } catch (\Exception $ex) {
            return null;
        }
    }

    public function getSetting()
    {
        try {
            $data = QuoteSettings::first();
            return view('quote_settings', compact('data'));
        } catch (Exception $ex) {
        }
    }
    public function BundledgetSetting()
    {

        try {
            $data1 = Config::where('key','bundled_products_settings')->first();
            if( $data1 == null ){


                $data = null;
                return view('Bundled_settings', compact('data'));

            }else{
                $data4 = json_decode($data1->value);
                foreach($data4 as $data3)
                $data = $data3;
                return view('Bundled_settings', compact('data'));
            }


        } catch (Exception $ex) {
        }
    }
    public function agentPinSetting()
    {

        try {
            $data1 = Config::where('key','agentpinstatus')->first();
            if( $data1 == null ){


                $data = null;
                return view('agentpinsetting', compact('data'));

            }else{
                $data = $data1->value;

                return view('agentpinsetting', compact('data'));
            }


        } catch (Exception $ex) {
        }
    }
    public function agentPinstatus()
    {

        try {
            $data1 = Config::where('key','agentpinstatus')->first();
            if( $data1 == null ){
                   $data = null;
                    return response()->json(['status' =>0]);
            }else{
                $data = $data1->value;
                if( $data == 1 ){
                    return response()->json(['status' => 1]);
                }else{
                    return response()->json(['status' =>0]);
                }

            }


        } catch (Exception $ex) {

        }
    }

    public function checkCustomer($phone, $id)
    {
        try {
            $profile = null;
            if ($phone != null) {
                $profile = Customer::where('cellphone', $phone)->orderBy('id', 'desc')->first(array('id'));
            }
            if ($profile != null) {
                return $profile->id;
            }
            $customer = CustomerProfile::where('omang', $id)
                ->orWhere('passport', $id)
                ->first(array('customer_id'));
            if ($customer && $customer->customer_id) {
                return $customer->customer_id;
            } else {
                return null;
            }
        } catch (\PHPUnit\Exception $ex) {
            return null;
        }
    }

    public function checkCustomerQuotes($customerId)
    {
        try {
            $settings = QuoteSettings::first();

            if ($settings != null)
                $limit = $settings->limit;
            else
                $limit = 5; //Default limit

            $quotes = MotorComprehensiveQuotes::where('customer_id', $customerId)
                ->where('status', 1)
                ->get(array('id'));

            if (count($quotes) == (int) $limit) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function createCustomer($request)
    {
        try {
            $customer = new Customer();
            $customer->firstName = ucfirst($request->firstName);
            $customer->lastName = ucfirst($request->lastName);
            $customer->middleName = ucfirst($request->middleName);
            $customer->email = $request->email;
            $customer->cellphone = $request->cellphone;
            $savedCustomer = $customer->save();

            $ifExists = CustomerProfile::where('customer_id',$customer->id)->exists();
            if(!empty($ifExists))
            {
                $profile = CustomerProfile::where('customer_id',$customer->id)->first();
            }else{
                /*Add Records to Customers Profile table*/
                $profile = new CustomerProfile();
            }

            $profile->customer_id = $customer->id;
            $profile->gender =  $request->gender == "Male" ? 1 : 0;
            $profile->dob = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
            $profile->maritalstatus = $request->maritalstatus;
            $profile->driving_license_number = $request->driving_license_number;
            $profile->license_valid_till     = $request->license_valid_till;
            $profile->omang = $request->omang;
            $profile->passport = $request->passport;
            $profile->countryId = $request->countryId;
            $profile->address = $request->address;
            $profile->city = $request->city;
            $profile->state = $request->state;
            $savedCustomerProfile = $profile->save();
            if ($profile->omang != null) {
                $customer->password = Hash::make($profile->omang);
                $customer->save();
            } elseif ($profile->passport) {
                $customer->password = Hash::make($profile->passport);
                $customer->save();
            } else {
                $customer->password = Hash::make(111111);
                $customer->save();
            }


            $token                  = Str::random(8);
            $user_password          = new UserPassword();
            $user_password->user_id = $customer->id;
            $user_password->token   = $token;
            $url                    = env('LIVEQUOTE_URL') . 'reset_password_first_time.php?token=' . $token;
            $user_password->url     = $url;
            $user_password->save();

            //sms
            $sms = new SmsMessaging();
            $sms->sendSmsUserCreate(29, $request->firstName, $request->lastName, $request->cellphone, $url);

            //mail
            if ($request->email != null) {
                $data = new \stdClass();
                $data->user_id = null;
                $data->customer_id = $customer->id;
                $data->new_user_password_url_id = $user_password->id;
                $data->hook = 'user_create';
                $data->attachment = null;
                // Mail::to($request->email)->send(new MailTemplate($data));
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                event(new \AlphaDirect\Events\SendMail($request->email, $emailTemplate->subject, "", $html, null, ['hook' => $data->hook]));
                //  $sent = \Illuminate\Support\Facades\Mail::to($request->email)->send(new MailTemplate($data));
            }

            if ($savedCustomer && $savedCustomerProfile)
                return $customer->id;
            else
                return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function storeMotorComprehensiveQuote($request, $customerId)
    {
        try {
          
            $data = new MotorComprehensiveQuotes();
            $data->customer_id = $customerId;
            $data->quoteNumber = $this->generateQuoteNumber();
            $data->is_imported = $request->is_imported;
            $data->type = 'New';
            if(isset($request->variant)){
                $data->variant = $request->variant;
            }

            $make = $request->make;
            if ($make == 'other') {
                $request['type']        = 'vehicle';
                $request['category']    = 'make';
                $request['make']        = $request->otherMake;
                $request['is_imported'] = $request->is_imported;
                $request['callback']    = 'create_policy';
                $make = app('AlphaDirect\Http\Controllers\MobileApp\MobileAppController')->addOption($request);
            }

            $model = $request->model;
            if ($model == 'other') {
                $request['type']        = 'vehicle';
                $request['category']    = 'model';
                $request['make']        = $make;
                $request['model']       = strtoupper($request->model_other);
                $request['is_imported'] = $request->is_imported;
                $request['callback']    = 'create_policy';
                if($request->is_imported=="Yes"){
                    $modelExists = VehicleMake::where('s_Make',$make)->where('s_Variant',$request->model)->where('is_imported',$request->is_imported)->first();
                    if(empty($modelExists)){
                        $model = app('AlphaDirect\Http\Controllers\MobileApp\MobileAppController')->addOption($request);
                    }else{
                        $model = $request->model;
                    }
                }else{
                    $model = strtoupper($request->model_other);;
                }
                $data->other_model = 1;
            }else{
                $data->other_model = 0;
            }
            $data->make = $make;
            $data->model = $model;
            $data->purpose = $request->purpose;
            $data->manufacturingYear = $request->manufacturingYear;
            $data->estimatedValue = $request->estimatedValue;
            $data->ratings_id = $request->rate_id;
            if(isset($request->claim_count) && $request->claim_count != null){
                $data->priorAccidents = $request->claim_count;
            }else{
                $data->priorAccidents = $request->priorAccidents = 0;
            }
            $data->premium = 0;
            $quotesetting = QuoteSettings::orderBy('id', 'DESC')->first(array('DaysToExpireQuote'));
            $data->expiry_date = Carbon::now()->addDays($quotesetting->DaysToExpireQuote)->format('Y-m-d');
            $data->premiumFrequency = $request->premiumFrequency;

            if ($request->estimatedValue != 0) {
                $rate = number_format((float)($request->premiumAnnually / $request->estimatedValue) * 100, 2, '.', '');
                if ($rate < 2.24) { //minimum premium rate is 2.24
                    Log::info('Rate is smaller than 2.24');
                    return false;
                } else {
                    $data->premium_rate = $rate;
                }
            } else {
                Log::info('Estimated value found null or 0');
                return false;
            }

            $data->premiumMonthly = $request->premiumMonthly;
            $data->premiumAnnually = $request->premiumAnnually;
            $data->premium3Inst = $request->premium3Inst;
            $data->agentID = $request->agentID;

            if ($request->agentID != null)
                $data->storeID = $request->stores;

            $data->status = 1;
            $data->save();

            // Capture the quote number immediately after the row is committed.
            // Everything below (PDF render, S3 upload, QR, WhatsApp, email) is a
            // non-critical side-effect — if any of it throws (e.g. wkhtmltopdf
            // missing, no S3 creds in dev), we must still return the saved quote
            // number rather than reporting failure for an already-stored quote.
            $generatedQuoteNumber = $data->quoteNumber;

            $product = Product::where('id', $request->productId)->first();
            if ($product != null) {
                $quote = new Quote();
                $quote->quoteCode = $data->quoteNumber;
                $quote->customerId = $customerId;
                $quote->agentId = $request->agentID;
                $quote->userIPAddress = $request->ip();
                $quote->productId = $product->id;
                $quote->planId = null;
                $quote->has_vehicle = $product->has_vehicle;
                $quote->has_member = $product->has_member;
                $quote->is_motor_items = $product->is_motor_items;
                $quote->preinspection = $product->preinspection;
                $quote->quote_limit = $product->limit;
                $quote->kyc_customer = $product->kyc_customer;
                $quote->kyc_recipient = $product->kyc_recipient;
                $quote->save();
                $id=$quote->id;
            }
            Log::info('Quote created successfully'.$id);
            $sendSmsEmail = 0;
            $customer = Customer::where('id', $customerId)->first();
            if (isset($customer)) {
                if ($customer->customer_category == 2 && isset($request->agentID)){
                    $user = User::where('id',$request->agentID)->where('create_high_risk_quote',1)->first();
                    if (isset($user)) {
                        $sendSmsEmail = 1;
                    }
                }
            }

            // ── Non-critical post-save side-effects ──────────────────────────
            // Quote document PDF, S3 upload, QR code, WhatsApp + email. Any
            // failure here is logged but must NOT fail the quote — the row is
            // already saved and $generatedQuoteNumber is returned below.
            try {
            $data = Quote::leftJoin('motor_comp_quotes', 'motor_comp_quotes.quoteNumber', 'quotes.quoteCode')
            ->leftJoin('customer', 'customer.id', 'quotes.customerId')
            ->leftJoin('customer_profile', 'customer_profile.customer_id', 'quotes.customerId')
            ->leftJoin('products', 'products.id', 'quotes.productId')
            ->where('quotes.id', $id)
            ->orderBy('quotes.id', 'desc')
            ->first();

            $quote = Quote::leftJoin('motor_comp_quotes', 'motor_comp_quotes.quoteNumber', 'quotes.quoteCode')
            ->where('quotes.id', $id)
            ->first(array('motor_comp_quotes.id', 'motor_comp_quotes.status', 'motor_comp_quotes.created_at', 'motor_comp_quotes.customer_id', 'motor_comp_quotes.agentID'));

            $dataStatus = MotorComprehensiveQuotes::where('quoteNumber', $data->quoteCode)->first(array('status', 'agentID'));

            $plan = Productplan::where('id', 8)->first();

            $date = $quote->created_at->format('Y-m-d');

            if ($data->premium_rate != null) {
                $ratio = $data->premium_rate;
            } else {
                if ($data->estimatedValue != 0)
                    $ratio = ($data->premiumAnnually / $data->estimatedValue) * 100;
                else
                    $ratio = '-';
            }

            $data['created_at'] = $date;
            $data['plan_name'] = $plan->name;
            $data['ratio'] = $ratio;
            $data['quote_status'] = $dataStatus->status;

            $agent = User::where('id', $dataStatus->agentID)->first(array('firstName', 'lastName'));
            $setting = QuoteSettings::first();
            $days = (int)(($setting->DaysToExpireQuote ?? 0) ?: 30);
            $start = new \Carbon\Carbon($data->created_at);
            $expiryDate = $start->addDays($days)->format('d-m-Y');

            $policyNumber = Policy::where('quoteNumber', $data->quoteCode)->first(array('policyNumber'));

            $store = Stores::where('id', $data->storeID)->first();
            if ($store && $store->name) {
                $storeName = $store->name;
            } else {
                $storeName = null;
            }
            $qr = $this->generateQRCode($data->quoteCode);
            $data1 = [
                'policyNumber' => $policyNumber,
                'data' => $data,
                'agent' => $agent,
                'expiryDate' => $expiryDate,
                'storeName' => $storeName,
                'QRCode' => $qr,
            ];


            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'Quotes/' . $data->quoteCode . '/Quote_'.$policyNumber.'_'.$date.'.pdf';

            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin/policy/quotes/document', $data1);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
        // Storage::disk('local')->put('public/document.pdf', $pdf->output());
            if (\File::exists(public_path($qr))) {
                \File::delete(public_path($qr));
            }
            $attachments = array();
            array_push($attachments, $path);

            $agentCellphone = UserProfile::where('user_id',$quote->agentID)->first(array('id', 'cellphone'));
            if ($agentCellphone && $agentCellphone->cellphone != null) {
                $dataCreatePolicy =[
                    "type"=>"template",
                    "subType"=>"send_quote_msg_agent",
                    "file"=>Helper::getCloudFrontURL($path),
                    "fileName"=>substr($path,23),
                    "mobileNumber"=>'267'.$agentCellphone->cellphone ,
                    "quoteCode"=>$data->quoteNumber
                ];

                $WhatsAppController= new WhatsAppController();
                $WhatsAppController->sendMessage($dataCreatePolicy);

            }
            if ($sendSmsEmail == 0) {
              //  $attachments = array();
                $data2 = new \stdClass();
                if($request->email != null){
                    $data2->user_id = null;
                    $data2->hook = 'send_quote';
                    $data2->customer_id = $customerId;
                    $data2->quoteNumber = $data->quoteNumber;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($request->email,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
                  //  $sent = Mail::to($customer->email)->send(new MailTemplate($data2));
                    // $q->quoteSent = 1;
                    // $q->save();
                    Log::info($html);

                }
            }
            //$message = $this->downloadQuote($data->quoteNumber,'002');exit;
            } catch (\Throwable $sideEffectError) {
                // Log and swallow — the quote is saved; PDF/email/etc are best-effort.
                Log::warning('Quote post-save side-effect failed for '.$generatedQuoteNumber.': '.$sideEffectError->getMessage());
            }

           return $generatedQuoteNumber;
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return false;
        }
    }

    public function getPremium($request)
    {
        try {
            $product = Product::where('id', $request->productId)->first();
            $vat = Region::where('id', $product->region_id)->first(array('vat'));
            if ($vat && $vat->vat) {
                switch ($request->premiumFrequency) {
                    case 1:
                        $premium = $request->premium / 12 * 1.08;
                        $grossPremium = $premium + ($vat->vat / 100 * $premium);
                        break;
                    case 2:
                        $premium = $request->premium / 3;
                        $grossPremium = $premium + ($vat->vat / 100 * $premium);
                        break;
                    case 3:
                        $premium = $request->premium;
                        $grossPremium = $premium + ($vat->vat / 100 * $premium);
                        break;
                    default:
                        $premium = ($request->premium / 12) * 1.08;
                        $grossPremium = $premium + ($vat->vat / 100 * $premium);
                        break;
                }
                return $grossPremium;
            } else {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }
    }

    public function generateQuoteNumber()
    {
        try {
            $latest = MotorComprehensiveQuotes::latest('quoteNumber')->first(array('quoteNumber'));
            if ($latest == null) {
                $latest = collect();
                $latest->quoteNumber = 0;
            }
            $length = 6;
            $pool = '023456789ABCDEFGHiJKLMNOPQRSTUVWXYZ';
            $quoteNumber = 'QN' . \Carbon\Carbon::now()->year . substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
            $check = Quote::where('quoteCode', $quoteNumber)->count();
            while ($check > 0) {
                $quoteNumber = 'QN' . \Carbon\Carbon::now()->year . substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
                $check = Quote::where('quoteCode', $quoteNumber)->count();
            }

            return $quoteNumber;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getQuoteDetails(Request $request)
    {
        try {
            $quoteNumber = $request->quoteNumber;
            if ($quoteNumber != null) {
                $quote = MotorComprehensiveQuotes::where('quoteNumber', $quoteNumber)->first();
                if ($quote && $quote->customer_id) {
                    // Accept status 0 AND 1 within the 30-day window — same
                    // tolerance as check() above. Historical quotes whose
                    // status was flipped to 0 outside Eloquent (no audit
                    // entry) must still hydrate so the customer can resume.
                    $daysOld = $quote->created_at != null
                        ? \Carbon\Carbon::parse($quote->created_at)->startOfDay()->diffInDays(\Carbon\Carbon::today(), false)
                        : PHP_INT_MAX;
                    if (in_array($quote->status, [0, 1]) && $daysOld <= 30) {
                        $customer = Customer::where('id', $quote->customer_id)->first();
                        $profile = CustomerProfile::where('customer_id', $customer->id)->first();
                        $v = Quote::where('quoteCode', $quoteNumber)->first();
                        $product = Product::where('id', $v->productId)->first();
                        $quote['product_id'] = $product->id;
                        $quote['plan_id'] = 7;
                        $quote['has_vehicle'] = 1;
                        $quote['plan_member'] = 0;
                        $quote['product_name'] = $product->name;
                        if($quote->priorAccidents == null){
                            $quote['priorAccidents']  = 0;
                        }
                        $region = Region::where('id', $product->region_id)->first();

                        if ($region->vat != null)
                            $vat = $region->vat;
                        else
                            return response()->json(['status' => 'Failed', 'Message' => 'VAT value not found for this product in the specified region'], 401);

                        $c_vat = ($vat / 100) * $quote->premiumAnnually;

                        $quote['premiumWithoutVat'] = abs($quote->premiumAnnually - $c_vat);

                        // for KYC
                        // $kyc = 0;
                        // $customerkyc = KYC::where('customer_id', $customer->id)->first(array('omang', 'passport'));
                        // if ($customerkyc != NULL) {
                        //     if ($customerkyc->omang != null && $profile->omang != NULL)
                        //         $kyc = 1;
                        //     if ($customerkyc->passport != NULL && $profile->passport != NULL)
                        //         $kyc = 1;
                        // }

                        $kyc = 0;
                        // if ($profile != NULL) {
                            // $customer = Customer::where('id', $customer->id)->first('mati_identity');
                            if(isset($customer->mati_identity) && $customer->mati_identity != NULL  && $customer->mati_identity != '')
                            {
                                $kyc = 1;   // for web app mati box
                            } else{
                                $customer->mati_identity = NULL;   // for mobile app mati box
                            }
                        // }

                        if ($quoteNumber != NULL) {
                            $is_user_check = 0;
                            $checkForPolicy = Policy::where('quoteNumber', $quoteNumber)->get();
                            if ($checkForPolicy != NULL && isset($checkForPolicy->policyNumber)) {
                                $is_user_check = 1;
                            } else {
                                $is_user_check = 0;
                            }
                        }

                        $compliance = KycCompliance::where('id', $product->kyc_compliance)->first(array('flow_id'));
                        if ($compliance != NULL && $compliance->flow_id != NULL)
                            $flow_id = $compliance->flow_id;
                        else {
                            if (env('APP_STATUS') == 'Production')
                                $flow_id = '616ac99406694f001be574c7'; // AdvanceKYC LIVE
                            else
                                $flow_id = '612c87b1ebca36001b310ea1'; // LiveQuote Test
                        }

                        // Global MATI toggle so the FE can decide whether to run
                        // the identity-verification gate at all.
                        $matiCfg = Config::where('key', 'enable_mati')->first(array('value'));
                        $enable_mati = ($matiCfg && (int) $matiCfg->value === 1) ? 1 : 0;

                        return response()->json(
                            [
                                'status' => 'Success',
                                'Customer' => $customer,
                                'Profile' => $profile,
                                'Quote' => $quote,
                                'kyc' => $kyc,
                                'is_user_check' => $is_user_check,
                                'mati_flow_id' => $flow_id,
                                'enable_mati' => $enable_mati
                            ],
                            200
                        );
                    } else {
                        return response()->json(['status' => 'Failed', 'Message' => 'Quote is either used or expired.'], 401);
                    }
                } else {
                    return response()->json(['status' => 'Success', 'Message' => 'Quote data not found'], 401);
                }
            } else {
                return response()->json(['status' => 'Failed', 'message' => 'Quote Number can not be null'], 401);
            }
        } catch (Exception $ex) {
            return response()->json(['status' => 'Failed', 'message' => $ex->getMessage()], 401);
        }
    }

    public function verify($id)
    {
        try {
            if ($id != null) {
                $eid = base64_encode($id);
                $url = env('QUOTE_URL') . $eid;
                $data = MotorComprehensiveQuotes::where('quoteNumber', $id)
                    ->where('status', 1)
                    ->first(array('quoteNumber'));
                if ($data && $data->quoteNumber != null) {
                    return redirect($url);
                } else {
                    $url = env('QUOTE_ERROR');
                    return redirect($url);
                }
            } else {
            }
        } catch (Exception $ex) {
        }
    }

    public function rejectQuote($qNumber)
    {
        try {
            $data = MotorComprehensiveQuotes::where('quoteNumber', $qNumber)->first();
            if ($data) {
                $data->status = 4;
                $data->save();
                return redirect()->back()->with('success', 'Quote: ' . $qNumber . ' has been rejected');
            } else {
                return redirect()->back()->with('error', 'Quote: ' . $qNumber . ' reject failed');
            }
        } catch (\Http\Client\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function check(Request $request)
    {
        try {
            if ($request->quoteNumber != null) {
                $data = MotorComprehensiveQuotes::where('quoteNumber', $request->quoteNumber)->first();
                if ($data && $data->quoteNumber) {
                    // Authoritative expiry = created_at + 30 days. The stored
                    // expiry_date column is unreliable for historical rows:
                    // when quote_settings.DaysToExpireQuote was null/0 at
                    // creation, expiry_date got set to today and the row
                    // expired on day one. Compute fresh from created_at so
                    // legitimate in-window quotes resume correctly.
                    //
                    // Applies to status 0 AND 1: some quotes are created with
                    // status=1 but later get their status flipped to 0 via a
                    // raw DB write (no audit entry) — they should still be
                    // resumable while within the 30-day window. The switch
                    // below treats status 0 the same as status 1.
                    if (in_array($data->status, [0, 1]) && $data->created_at != null) {
                        $daysOld = \Carbon\Carbon::parse($data->created_at)->startOfDay()
                                       ->diffInDays(\Carbon\Carbon::today(), false);
                        if ($daysOld > 30) {
                            return response()->json(['status' => 'Failed', 'message' => "Quote is expired."], 401);
                        }
                    }
                    switch ($data->status) {
                        case 0:
                            // Fall through — see comment above. Historical
                            // quotes whose status got flipped to 0 outside
                            // Eloquent are still resumable within 30 days.
                        case 1:
                            $eid = base64_encode($data->quoteNumber);
                            $url = env('QUOTE_URL') . $eid;
                            return response()->json(
                                ['status' => 'Success', 'url' => $url, 'eid' => $eid],
                                200
                            );
                            //return response()->json(['status' => 'Success', 'QuoteNumber' => $data->quoteNumber],200);
                            break;
                        case 2:
                            return response()->json(['status' => 'Failed', 'message' => "Quote has been already used"], 401);
                            break;
                        default:
                            return response()->json(['status' => 'Failed', 'message' => "No data found"], 401);
                            break;
                    }
                } else {
                    return response()->json(['status' => 'Failed', 'message' => "Quote not found"], 401);
                }
            } else {
                return response()->json(['status' => 'Failed', 'message' => "Please provide quote number"], 401);
            }
        } catch (Exception $ex) {
            return response()->json(['status' => 'Failed', 'message' => $ex->getMessage()], 401);
        }
    }

    public function storeSetting(Request $request)
    {
        try {
            $check = QuoteSettings::get();

            if (count($check) == 0)
                $store = new QuoteSettings();
            else
                $store = QuoteSettings::first();

            $store->hoursTosendMail = $request->hours;
            $store->limit = $request->limit;
            $store->edit_limit = $request->edit_limit;
            $store->policy_premium_edit_limit = $request->policy_premium_edit_limit;
            $store->DaysToExpireQuote = $request->days;
            $store->sum_assured_limit = $request->sum_assured_limit;
            $store->whatsappReturnMessage = $request->whatsappMessage;
            $store->save();

            return Redirect::back()->with('success', 'Settings updated successfully!');
        } catch (Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function bundledproductsstoreSetting(Request $request)
    {
           if(isset($request->bundled_show)){
            $bundled_show = 1;
           }else{
            $bundled_show = 0;
           }
           if(isset($request->motor_comprehensive)){
            $motor_comprehensive = 1;
           }else{
            $motor_comprehensive = 0;
           }
           if(isset($request->basediscount)){
            $basediscount = 1;
           }else{
            $basediscount = 0;
           }
        $value = [];
        $item = [];
        $item['two_products'] = $request->two_products;
        $item['three_products'] = $request->three_products;
        $item['four_products'] = $request->four_products;
        $item['more_than_four'] = $request->more_than_four_products;
        $item['motor_comprehensive'] = $motor_comprehensive;
        $item['bundled_show'] = $bundled_show;
        $item['basediscount'] = $basediscount;
        array_push($value, $item);


        try {
            $store = Config::where('key','bundled_products_settings')->first();

            if($store == null){
            $store1 = new Config();
            $store1->key = 'bundled_products_settings';
            $store1->value = json_encode($value);
            $store1->save();
            }else{
                $store->value = json_encode($value);
                $store->save();
            }




            return Redirect::back()->with('success', 'Settings updated successfully!');
        } catch (Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }
    public function agentpinstoreSetting(Request $request)
    {
           if(isset($request->agentpinstatus)){
            $agentpinstatus = 1;
           }else{
            $agentpinstatus = 0;
           }





        try {
            $store = Config::where('key','agentpinstatus')->first();

            if($store == null){
            $store1 = new Config();
            $store1->key = 'agentpinstatus';
            $store1->value = $agentpinstatus;
            $store1->save();
            }else{
                $store->value =$agentpinstatus;
                $store->save();
            }




            return Redirect::back()->with('success', 'Settings updated successfully!');
        } catch (Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    /**
     * Get month rate settings view
     */
    public function getMonthRateSetting()
    {
        try {
            $data = MonthRateSetting::orderBy('min_months')->get();
            return view('month_rate_settings', compact('data'));
        } catch (Exception $ex) {
            return redirect()->back()->with('error', $ex->getMessage());
        }
    }

    /**
     * Store month rate settings
     */
    public function storeMonthRateSetting(Request $request)
    {
        try {
            $request->validate([
                'rates' => 'required|array',
                'rates.*.min_months' => 'required|integer|min:0',
                'rates.*.max_months' => 'nullable|integer|min:1',
                'rates.*.rate_percentage' => 'required|numeric|min:0|max:100',
                'rates.*.description' => 'nullable|string|max:255',
                'rates.*.is_active' => 'boolean'
            ]);

            // Clear existing rates
            MonthRateSetting::truncate();

            // Insert new rates
            foreach ($request->rates as $rate) {
                MonthRateSetting::create([
                    'min_months' => $rate['min_months'],
                    'max_months' => isset($rate['max_months']) && $rate['max_months'] !== '' ? $rate['max_months'] : null,
                    'rate_percentage' => $rate['rate_percentage'],
                    'description' => $rate['description'] ?? null,
                    'is_active' => isset($rate['is_active']) ? (bool)$rate['is_active'] : true
                ]);
            }

            return redirect()->back()->with('success', 'Month rate settings updated successfully!');
        } catch (Exception $ex) {
            return redirect()->back()->with('error', $ex->getMessage());
        }
    }

    /**
     * Initialize default month rate settings
     */
    public function initializeDefaultMonthRates()
    {
        try {
            // Check if settings already exist
            if (MonthRateSetting::count() > 0) {
                return redirect()->back()->with('info', 'Month rate settings already exist.');
            }

            // Create default rates as specified by user
            $defaultRates = [
                ['min_months' => 24, 'max_months' => 36, 'rate_percentage' => 2.00, 'description' => '24-36 months rate'],
                ['min_months' => 36, 'max_months' => 48, 'rate_percentage' => 5.00, 'description' => '36-48 months rate'],
                ['min_months' => 48, 'max_months' => 60, 'rate_percentage' => 8.00, 'description' => '48-60 months rate'],
                ['min_months' => 60, 'max_months' => null, 'rate_percentage' => 10.00, 'description' => 'More than 60 months rate']
            ];

            foreach ($defaultRates as $rate) {
                MonthRateSetting::create($rate);
            }

            return redirect()->back()->with('success', 'Default month rate settings initialized successfully!');
        } catch (Exception $ex) {
            return redirect()->back()->with('error', $ex->getMessage());
        }
    }

    /**
     * Get policies eligible for month rate discounts
     */
    public function getPolicyDiscountEligibility()
    {
        try {
            return view('policy_discount_eligibility');
        } catch (Exception $ex) {
            return redirect()->back()->with('error', $ex->getMessage());
        }
    }

    /**
     * DataTable data for policy discount eligibility
     */
    public function getPolicyDiscountEligibilityData(Request $request)
    {
        try {
            // Get all policies (not cancelled) - limited to 10 for performance
            $policies = \DB::table('policies')
                ->leftJoin('products', 'policies.product_id', '=', 'products.id')
                ->leftJoin('customer', 'policies.customer_id', '=', 'customer.id')
                ->select([
                    'policies.id',
                    'policies.policyNumber', 
                    'policies.product_id',
                    'policies.premium_freq',
                    'policies.premium',
                    'policies.policyActivatedDate',
                    'products.name as product_name',
                    'customer.firstName',
                    'customer.lastName'
                ])
                ->where('policies.status', '==', 1) // Not cancelled
                //->limit(10) // Limit to 10 records for performance
                ->get();

                        // Process each policy to calculate transactions from both tables
            $result = [];
            $maxResults = 30; // Maximum number of eligible policies to return
            
            foreach ($policies as $policy) {
                // Check if we've reached the maximum number of results
                if (count($result) >= $maxResults) {
                    break; // Stop processing more policies
                }
                
                // Get successful transactions from live table
                $liveTransactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                    ->whereIn( 'status', [ 'SUCCESS', 'Success', 'success' ] )
                    ->count();
                
                // Get successful transactions from archived table  
                $archivedTransactions = PaymentTransactionArchive::where('policyNumber', $policy->policyNumber)
                    ->whereIn( 'status', [ 'SUCCESS', 'Success', 'success' ] )
                    ->count();
                
                // Total successful transactions
                $totalSuccessfulTransactions = $liveTransactions + $archivedTransactions;
                
                // Calculate months based on product ID and premium frequency
                $calculatedMonths = 0;
                if (in_array($policy->product_id, [1, 2, 4, 5, 9, 10])) {
                    // Each transaction counts as 1 month for these products
                    $calculatedMonths = $totalSuccessfulTransactions;
                } elseif (in_array($policy->product_id, [3, 7, 8])) {
                    if ($policy->premium_freq == 1 || $policy->premium_freq == null) {
                        // Monthly: Each transaction counts as 1 month
                        $calculatedMonths = $totalSuccessfulTransactions;
                    } elseif ($policy->premium_freq == 2) {
                        // Quarterly: Each transaction counts as 4 months
                        $calculatedMonths = $totalSuccessfulTransactions * 4;
                    } elseif ($policy->premium_freq == 3) {
                        // Yearly: Each transaction counts as 12 months
                        $calculatedMonths = $totalSuccessfulTransactions * 12;
                    }
                }
                
                // Skip policies with less than 24 calculated months
                if ($calculatedMonths < 24) {
                    continue;
                }
                
                // Get discount rate for calculated months
                $rate = MonthRateSetting::getRateForMonths($calculatedMonths);
                $discountRate = $rate ? $rate->rate_percentage : 0;
                $discountAmount = $rate ? ($policy->premium * $rate->rate_percentage / 100) : 0;
                
                // Check if policy already has discount applied and whether new discount qualifies
                if (!PolicyAppliedDiscount::qualifiesForNewDiscount($policy->policyNumber, $discountRate)) {
                    continue; // Skip if policy already has same or higher discount
                }

                // Format premium frequency. Codes: 1=Monthly, 2=Three Instalments,
                // 3=Annual, 4=Semiannual, 5=Quarterly, 6=Manual Input.
                $premiumFreqBadge = '';
                if ($policy->premium_freq == 1 || $policy->premium_freq == null) {
                    $premiumFreqBadge = '<span class="badge badge-primary">Monthly</span>';
                } elseif ($policy->premium_freq == 2) {
                    $premiumFreqBadge = '<span class="badge badge-info">Three Instalments</span>';
                } elseif ($policy->premium_freq == 3) {
                    $premiumFreqBadge = '<span class="badge badge-warning">Annual</span>';
                } elseif ($policy->premium_freq == 4) {
                    $premiumFreqBadge = '<span class="badge badge-info">Semiannual</span>';
                } elseif ($policy->premium_freq == 5) {
                    $premiumFreqBadge = '<span class="badge badge-info">Quarterly</span>';
                } elseif ($policy->premium_freq == 6) {
                    $premiumFreqBadge = '<span class="badge badge-secondary">Manual Input</span>';
                } else {
                    $premiumFreqBadge = '<span class="badge badge-secondary">Other (' . $policy->premium_freq . ')</span>';
                }

                // Action button
                $actionButton = '';
                if ($discountRate > 0) {
                    $actionButton = '<button type="button" 
                                            class="btn btn-success btn-sm apply-discount-btn" 
                                            data-policy-id="' . $policy->id . '"
                                            data-policy-number="' . $policy->policyNumber . '"
                                            data-discount-rate="' . $discountRate . '"
                                            data-discount-amount="' . number_format($discountAmount, 2) . '"
                                            data-calculated-months="' . $calculatedMonths . '">
                                        <i class="fa fa-percentage"></i> Apply Discount
                                    </button>';
                } else {
                    $actionButton = '<span class="text-muted">No Rate Available</span>';
                }

                $result[] = [
                    'policy_number' => '<strong>' . $policy->policyNumber . '</strong><br><small class="text-muted">ID: ' . $policy->id . '</small>',
                    'customer' => $policy->firstName . ' ' . $policy->lastName,
                    'product' => '<span class="badge badge-secondary">ID: ' . $policy->product_id . '</span><br>' . $policy->product_name,
                    'premium_freq' => $premiumFreqBadge,
                    'premium' => '<strong>P' . number_format($policy->premium, 2) . '</strong>',
                    'successful_transactions' => '<span class="badge badge-success">' . $totalSuccessfulTransactions . '</span>',
                    'calculated_months' => '<strong class="text-primary">' . $calculatedMonths . ' months</strong>',
                    'discount_rate' => $discountRate > 0 ? '<span class="badge badge-danger">' . $discountRate . '%</span>' : '<span class="text-muted">N/A</span>',
                    'discount_amount' => $discountAmount > 0 ? '<strong class="text-success">P' . number_format($discountAmount, 2) . '</strong>' : '<span class="text-muted">P0.00</span>',
                    'activated_date' => date('d/m/Y', strtotime($policy->policyActivatedDate)),
                    'action' => $actionButton
                ];
            }

            // Return DataTables format
            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => count($result),
                'recordsFiltered' => count($result),
                'data' => $result
            ]);

        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    /**
     * Apply discount to a policy
     */
    public function applyPolicyDiscount(Request $request)
    {
        try {
            $request->validate([
                'policy_id' => 'required|integer',
                'policy_number' => 'required|string',
                'discount_rate' => 'required|numeric|min:0|max:100',
                'discount_amount' => 'required|numeric|min:0',
                'original_premium' => 'required|numeric|min:0',
                'calculated_months' => 'required|integer|min:24',
                'notes' => 'nullable|string|max:1000'
            ]);

            // Check if policy already has a discount that is same or higher
            if (!PolicyAppliedDiscount::qualifiesForNewDiscount($request->policy_number, $request->discount_rate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy already has a discount with the same or higher rate applied.'
                ], 422);
            }

            \DB::beginTransaction();

            // Get policy and customer banking information
            $policy = Policy::find($request->policy_id);
            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found.'
                ], 404);
            }

            $customerBanking = CustomerBanking::where('policy_id', $request->policy_id)
                ->orderByDesc('id')
                ->first();

            // Calculate new premium after discount
            $newPremium = $request->original_premium - $request->discount_amount;

            // If policy has a previous discount with lower rate, supersede it
            $existingDiscount = PolicyAppliedDiscount::getActiveDiscount($request->policy_number);
            if ($existingDiscount && $request->discount_rate > $existingDiscount->discount_rate) {
                PolicyAppliedDiscount::supersedePreviousDiscount(
                    $request->policy_number, 
                    "Superseded by higher discount rate: {$request->discount_rate}%"
                );
            }

            // Handle different billing methods
            if ($customerBanking) {
                $billingMethod = strtolower($customerBanking->billing);
                
                switch ($billingMethod) {
                    case 'dpo':
                        // Update ScheduleTransaction amounts for pending transactions
                        $updatedTransactions = ScheduleTransaction::where('policy_number', $request->policy_number)
                            ->where('status', 0) // Only pending transactions
                            ->update(['premium' => $newPremium]);
                        
                        if ($updatedTransactions > 0) {
                            \Log::info("Updated {$updatedTransactions} DPO scheduled transactions for policy {$request->policy_number} with new premium: {$newPremium}");
                        }
                        break;

                    case 'realpay':
                        // Cancel old contract and create new one with new discount amount
                        try {
                            $realpayController = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            
                            // Cancel existing RealPay contract
                            $clientNumber = $realpayController->cancelRealpayContract($request->policy_id);
                            if ($clientNumber == null) {
                                $clientNumber = $realpayController->cancelRealpayContractsForInstProduct($request->policy_id);
                            }

                            if ($clientNumber != null) {
                                // Update RealPay cancel request status
                                $cancelRequest = RealpayCancelRequests::where('policy_id', $request->policy_id)->first();
                                if ($cancelRequest) {
                                    $cancelRequest->cancel_status = 1;
                                    $cancelRequest->save();
                                }

                                // Cancel existing transaction
                            

                                // Create new contract with new premium
                                $contractRequest = new \Illuminate\Http\Request();
                                $contractRequest->merge([
                                    'policyID' => $request->policy_id,
                                    'premium' => $newPremium,
                                    'leadSource' => 'discount_application'
                                ]);

                                $newContract = $realpayController->logRealpayPayment($contractRequest);
                                
                                if ($newContract->getData()->status != 200) {
                                    throw new \Exception('Failed to create new RealPay contract: ' . ($newContract->getData()->message ?? 'Unknown error'));
                                }
                            } else {
                                throw new \Exception('Failed to cancel existing RealPay contract');
                            }
                        } catch (\Exception $ex) {
                            \Log::error("RealPay contract cancellation/creation failed for policy {$request->policy_number}: " . $ex->getMessage());
                            throw new \Exception('RealPay contract update failed: ' . $ex->getMessage());
                        }
                        break;

                    default:
                        \Log::warning("Unsupported billing method '{$billingMethod}' for policy {$request->policy_number}");
                        break;
                }
            }

            // Update policy premium
            $policy->premium = $newPremium;
            $policy->save();

            // Apply new discount record
            $appliedDiscount = PolicyAppliedDiscount::create([
                'policyNumber' => $request->policy_number,
                'discount_rate' => $request->discount_rate,
                'discount_amount' => $request->discount_amount,
                'original_premium' => $request->original_premium,
                'calculated_months' => $request->calculated_months,
                'status' => 'applied',
                'action_by' => auth()->id(),
                'notes' => $request->notes ?: "Discount applied based on {$request->calculated_months} months of successful transactions",
                'applied_at' => now()
            ]);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Discount of {$request->discount_rate}% successfully applied to policy {$request->policy_number}. New premium: {$newPremium}",
                'applied_discount' => $appliedDiscount,
                'new_premium' => $newPremium,
                'billing_method' => $customerBanking ? $customerBanking->billing : 'Unknown'
            ]);

        } catch (\Exception $ex) {
            \DB::rollback();
            \Log::error("Policy discount application failed for policy {$request->policy_number}: " . $ex->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply discount: ' . $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Show applied discounts list page
     */
    public function getAppliedDiscountsList()
    {
        try {
            return view('applied_discounts_list');
        } catch (Exception $ex) {
            return redirect()->back()->with('error', 'Error loading applied discounts list: ' . $ex->getMessage());
        }
    }

    /**
     * DataTable data for applied discounts list
     */
    public function getAppliedDiscountsData(Request $request)
    {
        try {
            $appliedDiscounts = \DB::table('policy_applied_discounts')
                ->leftJoin('policies', 'policy_applied_discounts.policyNumber', '=', 'policies.policyNumber')
                ->leftJoin('products', 'policies.product_id', '=', 'products.id')
                ->leftJoin('customer', 'policies.customer_id', '=', 'customer.id')
                ->leftJoin('users', 'policy_applied_discounts.action_by', '=', 'users.id')
                ->select([
                    'policy_applied_discounts.*',
                    'policies.product_id',
                    'policies.premium_freq',
                    'products.name as product_name',
                    'customer.firstName',
                    'customer.lastName',
                    'users.firstName as applied_by_firstName',
                    'users.lastName as applied_by_lastName'
                ])
                ->whereIn('policy_applied_discounts.status', ['applied', 'superseded'])
                ->orderBy('policy_applied_discounts.applied_at', 'desc')
                ->get();

            $result = [];
            foreach ($appliedDiscounts as $discount) {
                // Format status badge
                $statusBadge = '';
                if ($discount->status == 'applied') {
                    $statusBadge = '<span class="badge badge-success">Applied</span>';
                } elseif ($discount->status == 'superseded') {
                    $statusBadge = '<span class="badge badge-warning">Superseded</span>';
                } elseif ($discount->status == 'cancelled') {
                    $statusBadge = '<span class="badge badge-danger">Cancelled</span>';
                }

                // Format premium frequency. Codes: 1=Monthly, 2=Three Instalments,
                // 3=Annual, 4=Semiannual, 5=Quarterly, 6=Manual Input.
                $premiumFreqBadge = '';
                if ($discount->premium_freq == 1 || $discount->premium_freq == null) {
                    $premiumFreqBadge = '<span class="badge badge-primary">Monthly</span>';
                } elseif ($discount->premium_freq == 2) {
                    $premiumFreqBadge = '<span class="badge badge-info">Three Instalments</span>';
                } elseif ($discount->premium_freq == 3) {
                    $premiumFreqBadge = '<span class="badge badge-warning">Annual</span>';
                } elseif ($discount->premium_freq == 4) {
                    $premiumFreqBadge = '<span class="badge badge-info">Semiannual</span>';
                } elseif ($discount->premium_freq == 5) {
                    $premiumFreqBadge = '<span class="badge badge-info">Quarterly</span>';
                } elseif ($discount->premium_freq == 6) {
                    $premiumFreqBadge = '<span class="badge badge-secondary">Manual Input</span>';
                } else {
                    $premiumFreqBadge = '<span class="badge badge-secondary">Other (' . $discount->premium_freq . ')</span>';
                }

                // Action buttons based on status
                $actionButtons = '';
                if ($discount->status == 'applied') {
                    $actionButtons = '<button type="button" class="btn btn-danger btn-sm cancel-discount-btn" 
                                              data-discount-id="' . $discount->id . '" 
                                              data-policy-number="' . $discount->policyNumber . '">
                                          <i class="fa fa-times"></i> Cancel
                                      </button>';
                } else {
                    $actionButtons = '<span class="text-muted">No actions available</span>';
                }

                $result[] = [
                    'policy_number' => '<strong>' . $discount->policyNumber . '</strong>',
                    'customer' => ($discount->firstName && $discount->lastName) ? $discount->firstName . ' ' . $discount->lastName : 'N/A',
                    'product' => '<span class="badge badge-secondary">ID: ' . $discount->product_id . '</span><br>' . ($discount->product_name ?: 'N/A'),
                    'premium_freq' => $premiumFreqBadge,
                    'original_premium' => '<strong>P' . number_format($discount->original_premium, 2) . '</strong>',
                    'calculated_months' => '<strong class="text-primary">' . $discount->calculated_months . ' months</strong>',
                    'discount_rate' => '<span class="badge badge-danger">' . $discount->discount_rate . '%</span>',
                    'discount_amount' => '<strong class="text-success">P' . number_format($discount->discount_amount, 2) . '</strong>',
                    'status' => $statusBadge,
                    'applied_by' => ($discount->applied_by_firstName && $discount->applied_by_lastName) ? $discount->applied_by_firstName . ' ' . $discount->applied_by_lastName : 'Unknown',
                    'applied_date' => date('d/m/Y H:i', strtotime($discount->applied_at)),
                    'action' => $actionButtons
                ];
            }

            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => count($result),
                'recordsFiltered' => count($result),
                'data' => $result
            ]);

        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function getWhatsappMessage()
    {
        try {
            $settings = QuoteSettings::first(array('whatsappReturnMessage'));
            if ($settings && $settings->whatsappReturnMessage != null) {
                return $settings->whatsappReturnMessage;
            } else {
                return 'Message not found on config setting';
            }
        } catch (Excwption $e) {
            return $e->getMessage();
        }
    }

    public function verifyGeneratedQuote(Request $request)
    {
        try {
            if ($request->quoteNumber != null) {
                $quote = MotorComprehensiveQuotes::where('quoteNumber', $request->quoteNumber)
                    ->first();

                if ($quote != null) {
                    if ($quote->status == 1) {
                        return response()->json(['status' => 'Success', 'Message' => "Quote is active"], 200);
                    } else {
                        return response()->json(['status' => 'Failed', 'Message' => "Quote data not found"], 401);
                    }
                } else {
                    return response()->json(['status' => 'Failed', 'Message' => "Quote data not found"], 401);
                }
            } else {
                return response()->json(['status' => 'Failed', 'Message' => "Quote number is empty"], 401);
            }
        } catch (Exception $e) {
            return response()->json(['status' => 'Failed', 'Message' => $e->getMessage()], 401);
        }
    }

    public function motorcompexport(Request $request)
    {
        return Excel::download(new MotorCompExport($request->all()), 'MotorComprehensiveExport.xlsx');
    }

    public function downloadQuote($id, $download)
    {
        try {
            
                $data = Quote::leftJoin('motor_comp_quotes', 'motor_comp_quotes.quoteNumber', 'quotes.quoteCode')
                ->leftJoin('customer', 'customer.id', 'quotes.customerId')
                ->leftJoin('customer_profile', 'customer_profile.customer_id', 'quotes.customerId')
                ->leftJoin('products', 'products.id', 'quotes.productId')
                ->where('quotes.quoteCode', $id)
                ->orderBy('quotes.id', 'desc')
                ->first();

                $quote = Quote::leftJoin('motor_comp_quotes', 'motor_comp_quotes.quoteNumber', 'quotes.quoteCode')
                ->where('quotes.quoteCode', $id)
                ->first(array('motor_comp_quotes.id', 'motor_comp_quotes.status', 'motor_comp_quotes.created_at', 'motor_comp_quotes.customer_id', 'motor_comp_quotes.agentID'));

            $dataStatus = MotorComprehensiveQuotes::where('quoteNumber', $data->quoteCode)->first(array('status', 'agentID'));

            $plan = Productplan::where('id', 8)->first();

            $date = $quote->created_at->format('Y-m-d');

            if ($data->premium_rate != null) {
                $ratio = $data->premium_rate;
            } else {
                if ($data->estimatedValue != 0)
                    $ratio = ($data->premiumAnnually / $data->estimatedValue) * 100;
                else
                    $ratio = '-';
            }

            $data['created_at'] = $date;
            $data['plan_name'] = $plan->name;
            $data['ratio'] = $ratio;
            $data['quote_status'] = $dataStatus->status;

            $agent = User::where('id', $dataStatus->agentID)->first(array('firstName', 'lastName'));
            $setting = QuoteSettings::first();
            $days = (int)(($setting->DaysToExpireQuote ?? 0) ?: 30);
            $start = new \Carbon\Carbon($data->created_at);
            $expiryDate = $start->addDays($days)->format('d-m-Y');

            $policyNumber = Policy::where('quoteNumber', $data->quoteCode)->first(array('policyNumber'));

            $store = Stores::where('id', $data->storeID)->first();
            if ($store && $store->name) {
                $storeName = $store->name;
            } else {
                $storeName = null;
            }
            $qr = $this->generateQRCode($data->quoteCode);
            $data1 = [
                'policyNumber' => $policyNumber,
                'data' => $data,
                'agent' => $agent,
                'expiryDate' => $expiryDate,
                'storeName' => $storeName,
                'QRCode' => $qr,
            ];


            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'Quotes/' . $data->quoteCode . '/Quote_'.$policyNumber.'_'.$date.'.pdf';

            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin/policy/quotes/document', $data1);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
           // Storage::disk('local')->put('public/document.pdf', $pdf->output());
            if (\File::exists(public_path($qr))) {
                \File::delete(public_path($qr));
            }
            $attachments = array();
         array_push($attachments, $path);

            $customer = Customer::where('id', $quote->customer_id)->first(array('id', 'email'));
            $agent = User::where('id', $quote->agentID)->first(array('id', 'email'));
            switch ($download) {
                case '100':
                    return Storage::disk('s3')->download($path);
                    break;
                case '010':
                    if ($customer && $customer->email != null) {
                        $data = new \stdClass();
                        $data->user_id = $quote->id;
                        $data->hook = 'send_quote_email';
                        $data->customer_id = $customer->id;
                        $data->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                        event(new \AlphaDirect\Events\SendMail($customer->email, $emailTemplate->subject, "", $html, $attachments, ['policyNumber' => $policyNumber, 'hook' => $data->hook]));
                        //  $sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($data));
                    }
                    return Redirect::back()->with('success', 'Email with quote sent to customer successfully');
                    break;
                case '001':
                    if ($agent && $agent->email != null) {
                        $data = new \stdClass();
                        $data->user_id = $quote->id;
                        $data->hook = 'send_quote_email_agent';
                        $data->customer_id = $customer->id;
                        $data->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                        event(new \AlphaDirect\Events\SendMail($agent->email, $emailTemplate->subject, "", $html, $attachments, ['policyNumber' => $policyNumber, 'hook' => $data->hook]));
                        //   $sent = \Illuminate\Support\Facades\Mail::to($agent->email)->send(new MailTemplate($data));
                       
                    }
                    return Redirect::back()->with('success', 'Email with quote sent to agent successfully');
                    break;
                  
                default:
                    return Redirect::back()->with('error', 'Operation not found');
                    break;
            }
        } catch (\Exception $e) {
            return Redirect::back()->with('error', $e->getMessage() . ' ' . $e->getLine());
        }
    }

    public function generateQRCode($quoteID)
    {
        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data(env('SELF_URL') . '/api/Back-To-Quote/' . $quoteID) //base64 encoding
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(205)
                ->margin(5)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();

            header('Content-Type: ' . $result->getMimeType());

            // echo $result->getString();
            $path = 'images/qrcode' . $quoteID . '.png';

            // Save it to a file
            $result->saveToFile($path);

            // Generate a data URI to include image data inline (i.e. inside an <img> tag)
            $dataUri = $result->getDataUri();
            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updatePremium(Request $request, $id)
    {
        try {

            $setting = QuoteSettings::first(array('edit_limit'));
            $edited = ReratedPremiumQuote::where('quote_number', $id)->get()->count();

            if (((int)$setting->edit_limit >= $edited + 1) == true) {
                $requestData = $request->all();
                foreach ($requestData as $key => $req) {
                    if ($req == null || $req == '') {
                        return Redirect::back()->with('error', ucfirst($key) . ' is required');
                    }
                }
                DB::beginTransaction();
                $quotes = MotorComprehensiveQuotes::where('quoteNumber', $id)->orderBy('id', 'DESC')->first();
                /*Start*/
                $data = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id);
                $totalDisc = abs($data->where('discount_surcharge', '<', 0)->sum('discount_surcharge'));
                $totalDisc = number_format((float)$totalDisc, 2, '.', '');
                $totalSurc = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id)->where('discount_surcharge', '>', 0)->sum('discount_surcharge');
                $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                /*End*/
                $old_value = $quotes->premiumAnnually;
                $Role = Auth()->user()::with('roles')->first();
                $userRole = $Role->roles[0]->id;
                $data = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();
                if ($data) {
                    $type = $request->type;
                    $value_type = $request->value_type;
                    $value = (float)$request->value;
                    $permittedFlatValue = ($data->$type) / 100 * $quotes->premiumAnnually;
                    $permittedPercentValue = $data->$type;

                    if ($value_type == 1) {
                        $v_perc = ($value / $quotes->premiumAnnually) * 100;
                        $v_flat = $value;
                    } elseif ($value_type == 2) {
                        $v_perc = $value;
                        $v_flat = ($value / 100) * $quotes->premiumAnnually;
                    } else {
                        DB::rollBack();
                        return Redirect::back()->with('error', 'Value type not found');
                    }

                    if ($type == 'discount') {
                        if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                            return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');
                    }

                    if ($type == 'surcharge') {
                        if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                            return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');
                    }

                    if ($v_perc <= $permittedPercentValue) {
                        if ($type == 'discount') {
                            if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');

                            $quotes->percent_discount_surcharge = '-' . number_format((float)$v_perc, 2, '.', '');
                            $annual = number_format((float)$quotes->premiumAnnually - $v_flat, 2, '.', '');
                        } elseif ($type == 'surcharge') {
                            if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');

                            $quotes->percent_discount_surcharge = '+' . number_format((float)$v_perc, 2, '.', '');
                            $annual = number_format((float)$quotes->premiumAnnually + $v_flat, 2, '.', '');
                        } else {
                            return Redirect::back()->with('error', 'Type not found');
                        }

                        $quotes->premiumAnnually = number_format((float)$annual, 2, '.', '');
                        $quotes->premium3Inst = number_format((float)$annual / 3, 2, '.', '');
                        $quotes->premiumMonthly = number_format((float)$annual / 12 * 1.08, 2, '.', '');
                        $quotes->discount_surcharge = number_format((float)$v_flat, 2, '.', '');
                        $quotes->premium_rate = number_format((float)($annual / $quotes->estimatedValue) * 100, 2, '.', '');
                        $quotes->save();

                        $d = ReratedPremiumQuote::where('quote_number', $id)->orderBy('id', 'desc')->get(array('discount_surcharge'));
                        $overall = $d->sum('discount_surcharge');
                        $total = $d->count();

                        $add = new ReratedPremiumQuote();
                        $add->quote_number = $id;
                        $add->rate_id = $quotes->ratings_id;
                        $add->discount_surcharge = $quotes->percent_discount_surcharge;
                        $add->old_value = $old_value;
                        $add->new_value = $annual;
                        $add->current_status = $overall;
                        $add->added_by = Auth()->user()->id;
                        $add->ip = $request->ip();
                        $add->reason = $request->reason;
                        $add->save();

                        if ($quotes->premium_rate < 2.24) { //updated with 2.24
                            DB::rollBack();
                            return Redirect::back()->with('error', 'Premium rate can not go below 2.24%');
                        } else {
                            DB::commit();
                            return Redirect::back()->with('success', 'Added ' . $type);
                        }
                    } else {
                        DB::rollBack();
                        return Redirect::back()->with('error', 'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                    }
                } else {
                    return Redirect::back()->with('error', 'Values not found for role : ' . $Role->roles[0]->name);
                }
            } else {
                return Redirect::back()->with('error', 'You have exceeded maximum number of updates allowed');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->with('error', $e->getMessage());
        }
    }



    public function addDiscountSurcharge(Request $request, $id)
    {
        try {

            $policy = Policy::where('policyNumber', $id)->first();

            $setting = QuoteSettings::first(array('edit_limit'));
            $edited = ReratedPremiumQuote::where('quote_number', $id)->get()->count();

            if (((int)$setting->edit_limit >= $edited + 1) == true) {
                $requestData = $request->all();
                foreach ($requestData as $key => $req) {
                    if ($req == null || $req == '') {
                        return Redirect::back()->with('error', ucfirst($key) . ' is required');
                    }
                }
                DB::beginTransaction();
                $quotes = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->orderBy('id', 'DESC')->first();
                // dd($quotes);
                /*Start*/
                $data = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id);

                $totalDisc = abs($data->where('discount_surcharge', '<', 0)->sum('discount_surcharge'));
                $totalDisc = number_format((float)$totalDisc, 2, '.', '');
                $totalSurc = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id)->where('discount_surcharge', '>', 0)->sum('discount_surcharge');
                $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                /*End*/
                $old_value = $quotes->premiumAnnually;
                $Role = Auth()->user()::with('roles')->first();
                $userRole = $Role->roles[0]->id;
                $data = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();
                if ($data) {
                    $type = $request->type;
                    $value_type = $request->value_type;
                    $value = (float)$request->value;
                    $permittedFlatValue = ($data->$type) / 100 * $quotes->premiumAnnually;
                    $permittedPercentValue = $data->$type;

                    if ($value_type == 1) {
                        $v_perc = ($value / $quotes->premiumAnnually) * 100;
                        $v_flat = $value;
                    } elseif ($value_type == 2) {
                        $v_perc = $value;
                        $v_flat = ($value / 100) * $quotes->premiumAnnually;
                    } else {
                        DB::rollBack();
                        return Redirect::back()->with('error', 'Value type not found');
                    }

                    if ($type == 'discount') {
                        if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                            return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');
                    }

                    if ($type == 'surcharge') {
                        if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                            return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');
                    }

                    if ($v_perc <= $permittedPercentValue) {
                        if ($type == 'discount') {
                            if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');

                            $quotes->percent_discount_surcharge = '-' . number_format((float)$v_perc, 2, '.', '');
                            $annual = number_format((float)$quotes->premiumAnnually - $v_flat, 2, '.', '');
                        } elseif ($type == 'surcharge') {
                            if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');

                            $quotes->percent_discount_surcharge = '+' . number_format((float)$v_perc, 2, '.', '');
                            $annual = number_format((float)$quotes->premiumAnnually + $v_flat, 2, '.', '');
                        } else {
                            return Redirect::back()->with('error', 'Type not found');
                        }

                        $quotes->premiumAnnually = number_format((float)$annual, 2, '.', '');
                        $quotes->premium3Inst = number_format((float)$annual / 3, 2, '.', '');
                        $quotes->premiumMonthly = number_format((float)$annual / 12 * 1.08, 2, '.', '');
                        $quotes->discount_surcharge = number_format((float)$v_flat, 2, '.', '');
                        $quotes->premium_rate = number_format((float)($annual / $quotes->estimatedValue) * 100, 2, '.', '');
                        $quotes->save();

                        $d = ReratedPremiumQuote::where('quote_number', $id)->orderBy('id', 'desc')->get(array('discount_surcharge'));
                        $overall = $d->sum('discount_surcharge');
                        $total = $d->count();

                        $add = new ReratedPremiumQuote();
                        $add->quote_number = $id;
                        $add->rate_id = $quotes->ratings_id;
                        $add->discount_surcharge = $quotes->percent_discount_surcharge;
                        $add->old_value = $old_value;
                        $add->new_value = $annual;
                        $add->current_status = $overall;
                        $add->added_by = Auth()->user()->id;
                        $add->ip = $request->ip();
                        $add->reason = $request->reason;
                        $add->save();

                        if ($quotes->premium_rate < 2.24) { //updated with 2.24
                            DB::rollBack();
                            return Redirect::back()->with('error', 'Premium rate can not go below 2%');
                        } else {
                            DB::commit();

                            $transactions = Transaction::join('payment_transactions', 'payment_transactions.policyNumber', 'transactions.policyNumber')
                                ->where('transactions.policyNumber', $policy->policyNumber)
                                ->orderBy('transactions.id', 'DESC')
                                ->first();


                            return redirect()->route('admin.policy.rerate_billing', ['id' => $policy->id]);
                        }
                    } else {
                        DB::rollBack();
                        return Redirect::back()->with('error', 'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                    }
                } else {
                    return Redirect::back()->with('error', 'Values not found for role : ' . $Role->roles[0]->name);
                }
            } else {
                return Redirect::back()->with('error', 'You have exceeded maximum number of updates allowed');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function updatePremiumAjax(Request $request, $id = null)
    {
        try {
            $setting = QuoteSettings::first(array('edit_limit'));
            $edited = ReratedPremiumQuote::where('quote_number', $request->quoteNumber)->get()->count();

            if (((int)$setting->edit_limit >= $edited + 1) == true) {
                $requestData = $request->all();
                foreach ($requestData as $key => $req) {
                    if ($req == null || $req == '') {

                        return response()->json(array(
                            'status' => 401,
                            'message' => ucfirst($key) . ' is required'

                        ), 401);
                    }
                }
                DB::beginTransaction();
                $quotes = MotorComprehensiveQuotes::where('quoteNumber', $request->quoteNumber)->orderBy('id', 'DESC')->first();
                /*Start*/
                $data = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id);
                $totalDisc = abs($data->where('discount_surcharge', '<', 0)->sum('discount_surcharge'));
                $totalDisc = number_format((float)$totalDisc, 2, '.', '');
                $totalSurc = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id)->where('discount_surcharge', '>', 0)->sum('discount_surcharge');
                $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                /*End*/
                $old_value = $quotes->premiumAnnually;

                $Role = Auth()->user()::with('roles')->first();
                $userRole = $Role->roles[0]->id;
                $data = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();
                if ($data) {
                    $type = $request->type;
                    $value_type = $request->value_type;
                    $value = (float)$request->value;
                    $permittedFlatValue = ($data->$type) / 100 * $quotes->premiumAnnually;
                    $permittedPercentValue = $data->$type;

                    if ($value_type == 1) {
                        $v_perc = ($value / $quotes->premiumAnnually) * 100;
                        $v_flat = $value;
                    }elseif ($value_type == 2) {
                        $v_perc = $value;
                        $v_flat = ($value / 100) * $quotes->premiumAnnually;
                    } else {
                        DB::rollBack();
                        return response()->json(array(
                            'status' => 401,
                            'message' => 'Value type not found'

                        ), 401);
                    }

                    if ($type == 'discount') {
                        if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                            return response()->json(array(
                                'status' => 401,
                                'message' => 'Can not exceed maximum discount value allowed'

                            ), 401);
                    }

                    if ($type == 'surcharge') {
                        if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                            return response()->json(array(
                                'status' => 401,
                                'message' => 'Can not exceed maximum surcharge value allowed'

                            ), 401);
                    }

                    if ($v_perc <= $permittedPercentValue) {
                        if ($type == 'discount') {
                            if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                return response()->json(array(
                                    'status' => 401,
                                    'message' => 'Can not exceed maximum discount value allowed'

                                ), 401);

                            $quotes->percent_discount_surcharge = '-' . number_format((float)$v_perc, 2, '.', '');
                            $annual = number_format((float)$quotes->premiumAnnually - $v_flat, 2, '.', '');
                        } elseif ($type == 'surcharge') {
                            if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                return response()->json(array(
                                    'status' => 401,
                                    'message' => 'Can not exceed maximum surcharge value allowed'

                                ), 401);

                            $quotes->percent_discount_surcharge = '+' . number_format((float)$v_perc, 2, '.', '');
                            $annual = number_format((float)$quotes->premiumAnnually + $v_flat, 2, '.', '');
                        } else {
                            return response()->json(array(
                                'status' => 401,
                                'message' => 'Type not found'

                            ), 401);
                        }

                        $quotes->premiumAnnually = number_format((float)$annual, 2, '.', '');
                        $quotes->premium3Inst = number_format((float)$annual / 3, 2, '.', '');
                        $quotes->premiumMonthly = number_format((float)$annual / 12 * 1.08, 2, '.', '');
                        $quotes->discount_surcharge = number_format((float)$v_flat, 2, '.', '');
                        $quotes->premium_rate = number_format((float)($annual / $quotes->estimatedValue) * 100, 2, '.', '');
                        $quotes->discSurAdded = 1;
                        $quotes->save();

                        $d = ReratedPremiumQuote::where('quote_number', $request->quoteNumber)->orderBy('id', 'desc')->get(array('discount_surcharge'));
                        $overall = $d->sum('discount_surcharge');
                        $total = $d->count();

                        $add = new ReratedPremiumQuote();
                        $add->quote_number = $request->quoteNumber;
                        $add->rate_id = $quotes->ratings_id;
                        $add->discount_surcharge = $quotes->percent_discount_surcharge;
                        $add->old_value = $old_value;
                        $add->new_value = $annual;
                        $add->current_status = $overall;
                        $add->added_by = auth()->user()->id;
                        $add->ip = $request->ip();
                        $add->reason = $request->reason;
                        $add->save();

                        if ($quotes->premium_rate < 2.24) { //updated with 2.24
                            DB::rollBack();
                            return response()->json(array(
                                'status' => 401,
                                'message' => 'Premium rate can not go below 2%'

                            ), 401);
                        } else {
                            DB::commit();
                            return response()->json(array(
                                'status' => 200,
                                'message' => 'Added ' . $type

                            ), 200);
                        }
                    } else {
                        DB::rollBack();
                        return response()->json(array(
                            'status' => 401,
                            'message' => 'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote')

                        ), 401);
                    }
                } else {
                    return response()->json(array(
                        'status' => 401,
                        'message' => 'Values not found for role : ' . $Role->roles[0]->name

                    ), 401);
                }
            } else {
                return response()->json(array(
                    'status' => 401,
                    'message' => 'You have exceeded maximum number of updates allowed'

                ), 401);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(array(
                'status' => 401,
                'message' => $e->getMessage()

            ), 401);
        }
    }

    public function updatePremiumRate(Request $request, $id = null)
    {
        try {
            $setting = QuoteSettings::first(array('edit_limit'));
            $edited = ReratedPremiumQuote::where('quote_number', $request->quoteNumber)->get()->count();

            if (((int)$setting->edit_limit >= $edited + 1) == true || (Auth::user()->hasRole('Super Admin') || Auth::user()->hasRole('Admin'))) {
                $requestData = $request->all();
                foreach ($requestData as $key => $req) {
                    if ($req == null || $req == '') {

                        return response()->json(array(
                            'status' => 401,
                            'message' => ucfirst($key) . ' is required'

                        ), 401);
                    }
                }
                DB::beginTransaction();
                $quotes = MotorComprehensiveQuotes::where('quoteNumber', $request->quoteNumber)->orderBy('id', 'DESC')->first();
                /*Start*/
                $data = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id);
                $totalDisc = abs($data->where('discount_surcharge', '<', 0)->sum('discount_surcharge'));
                $totalDisc = number_format((float)$totalDisc, 2, '.', '');
                $totalSurc = ReratedPremiumQuote::where('rate_id', $quotes->ratings_id)->where('discount_surcharge', '>', 0)->sum('discount_surcharge');
                $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                /*End*/
                $old_value = $quotes->premiumAnnually;

                $Role = Auth()->user()::with('roles')->first();
                $userRole = $Role->roles[0]->id;
                $data = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();
                if ($data) {
                    $value = (float)$request->value;
                    $value_type = $request->value_type;

                    if ($value_type == 2) {
                        $v_perc = $value;
                        $new_premium = ($value / 100) * $quotes->estimatedValue;
                    } elseif ($value_type == 1) {
                        $new_premium = $value;
                        $v_perc = $value;
                    }
                    else {
                        DB::rollBack();
                        return response()->json(array(
                            'status' => 401,
                            'message' => 'Value type not found'

                        ), 401);
                    }

                    // if($new_premium > $quotes->premiumAnnually){
                    //     // surcharge
                    //     $type = 'surcharge';
                    //     $permittedFlatValue = ($data->$type) / 100 * $new_premium;
                    //     $permittedPercentValue = $data->$type;
                    // }else{
                    //     // discount
                    //     $type = 'discount';
                    //     $permittedFlatValue = ($data->$type) / 100 * $new_premium;
                    //     $permittedPercentValue = $data->$type;
                    // }

                    // if ($type == 'surcharge') {
                    //     if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                    //         return response()->json(array(
                    //             'status' => 401,
                    //             'message' => 'Can not exceed maximum surcharge value allowed'
                    //         ), 401);
                    // }

                    // if ($type == 'discount') {
                    //   if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                    //     return response()->json(array(
                    //         'status' => 401,
                    //         'message' => 'Can not exceed maximum discount value allowed'
                    //     ), 401);
                    // }

                    // if ($v_perc <= $permittedPercentValue) {
                    //     if ($type == 'discount') {
                    //         if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                    //             return response()->json(array(
                    //                 'status' => 401,
                    //                 'message' => 'Can not exceed maximum discount value allowed'

                    //             ), 401);

                    //         $quotes->percent_discount_surcharge = '-' . number_format((float)$v_perc, 2, '.', '');
                    //         $annual = number_format((float)$quotes->premiumAnnually - $new_premium, 2, '.', '');
                    //     } elseif ($type == 'surcharge') {
                    //         if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                    //             return response()->json(array(
                    //                 'status' => 401,
                    //                 'message' => 'Can not exceed maximum surcharge value allowed'

                    //             ), 401);

                    //         $quotes->percent_discount_surcharge = '+' . number_format((float)$v_perc, 2, '.', '');
                    //         $annual = number_format((float)$new_premium -$quotes->premiumAnnually, 2, '.', '');
                    //     } else {
                    //         return response()->json(array(
                    //             'status' => 401,
                    //             'message' => 'Type not found'

                    //         ), 401);
                    //     }

                        if ($value_type == 2) {
                            $per_value = $value;
                            $flat_value = 0;
                        } elseif ($value_type == 1) {
                            $flat_value = $value;
                            $per_value = 0;
                        }

                        $quotes->premiumAnnually = number_format((float)$new_premium, 2, '.', '');
                        $quotes->premium3Inst = number_format((float)$new_premium / 3, 2, '.', '');
                        $quotes->premiumMonthly = number_format((float)$new_premium / 12 * 1.08, 2, '.', '');
                        // $quotes->discount_surcharge = number_format((float)$annual, 2, '.', '');
                        $quotes->premium_rate = number_format((float)($new_premium / $quotes->estimatedValue) * 100, 2, '.', '');
                        // $quotes->discSurAdded = 1;
                        $quotes->custom_rate_per = $per_value;
                        $quotes->custom_rate_flat = $flat_value;
                        $quotes->save();

                        $d = ReratedPremiumQuote::where('quote_number', $request->quoteNumber)->orderBy('id', 'desc')->get(array('discount_surcharge'));
                        $overall = $d->sum('discount_surcharge');
                        $total = $d->count();

                        $add = new ReratedPremiumQuote();
                        $add->quote_number = $request->quoteNumber;
                        $add->rate_id = $quotes->ratings_id;
                        $add->discount_surcharge = $quotes->percent_discount_surcharge;
                        $add->old_value = $old_value;
                        $add->new_value = $new_premium;
                        $add->current_status = $overall;
                        $add->added_by = auth()->user()->id;
                        $add->ip = $request->ip();
                        $add->reason = $request->reason;
                        $add->save();

                        if ($quotes->premium_rate < 2.24) { //updated with 2.24
                            DB::rollBack();
                            return response()->json(array(
                                'status' => 401,
                                'message' => 'Premium rate can not go below 2%'

                            ), 401);
                        } else {
                            DB::commit();
                            return response()->json(array(
                                'status' => 200,
                                'message' => 'Custom Rate Added'

                            ), 200);
                        }
                    // } else {
                    //     DB::rollBack();
                    //     return response()->json(array(
                    //         'status' => 401,
                    //         'message' => 'You are only permitted to ' . $type . ' upto ' . ($permittedPercentValue . '%' . ' for this quote')

                    //     ), 401);
                    // }

                } else {
                    return response()->json(array(
                        'status' => 401,
                        'message' => 'Values not found for role : ' . $Role->roles[0]->name
                    ), 401);
                }
            } else {
                return response()->json(array(
                    'status' => 401,
                    'message' => 'You have exceeded maximum number of updates allowed'
                ), 401);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(array(
                'status' => 401,
                'message' => $e->getMessage()

            ), 401);
        }
    }

    public function viewUpdateHistory($id)
    {
        try {
            return view('admin.policy.quotes.update_history', compact('id'));
        } catch (\Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }
    public function historyData($id)
    {
        $data = ReratedPremiumQuote::where('quote_number', $id)->get();

        return DataTables::of($data)
            ->addColumn('added_by', function ($data) {
                $user = USer::where('id', $data->added_by)->first();
                if ($user) {
                    return $user->firstName . ' ' . $user->lastName;
                } else {
                    return 'N/A';
                }
            })

            ->addColumn('actions', function ($data) {
                $action = null;
                $checkRecord = MotorComprehensiveQuotes::where('ratings_id',$data->rate_id)->first();
                // $policy = Policy::where('quoteNumber',$checkRecord->quoteNumber)->first();
                if(isset($checkRecord) && isset($data->discount_surcharge))
                {
                    $action = '<a href="' . route('quote.historyDataUnissueRate', $checkRecord->ratings_id) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Unissue">
                                <span class="kt-opacity-11" id="unissueBtn">Unissue</span>
                            </a>';
                }

                return  $action;
            })
            ->rawColumns(['added_by','actions'])
            ->make(true);
    }


    public function historyDataUnissueRate($ratings_id)
    {
        $motorCompQuotes = MotorComprehensiveQuotes::where('ratings_id',$ratings_id)->first();
        if ($motorCompQuotes->quoteNumber) {
            $reratedPremiumQuotes = ReratedPremiumQuote::where('rate_id','<',$ratings_id)->orderBy('id', 'desc')->first();//->skip(1)->take(1)
            if (isset($reratedPremiumQuotes)) {
                $fetchPremium = $this->fetchPremium($reratedPremiumQuotes->rate_id);
                $motorCompQuotes->ratings_id = $reratedPremiumQuotes->rate_id;
                $motorCompQuotes->premiumAnnually = $fetchPremium->result;
                $motorCompQuotes->premiumMonthly = $fetchPremium->monthly_premium_vat;
                $motorCompQuotes->premium3Inst = $fetchPremium->threemonthly_preminum_vat;
                $motorCompQuotes->save();
                return Redirect::back()->with('success','Successfully unissued');
            } else {
                return Redirect::back()->with('error','Failed to unissue');
            }

        } else {
            return Redirect::back()->with('error','Quote not found');
        }
    }


    protected function fetchPremium($rate_id)
    {
        try {

            $rateId = base64_encode($rate_id);

            $client2 = new \GuzzleHttp\Client();
            $response2 = $client2->request(
                'POST',
                env('RATINGS_URL') . 'getPremium',
                [
                    'form_params' => [
                        'garbage_data_slug' => $rateId
                    ]
                ]
            );
            $response2 = $response2->getBody()->getContents();
            $data = json_decode($response2);
            // dd($data);
            if ($data->success == true) {
                return $data;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    //    public function getProRataPremium(Request $request){
    //        try{
    //            $client = new \GuzzleHttp\Client();
    //            $API_URL = env('RATINGS_URL');
    //            $response = $client->request(
    //                'POST',
    //                $API_URL . 'getfirstPremium',
    //                [
    //                    'form_params' => [
    //                        'rate_id' => $request->get('rate_id'),
    //                        'day' => $request->get('day')
    //                    ]
    //                ]
    //            );
    //
    //            $status = $response->getStatusCode();
    //            $response = $response->getBody()->getContents();
    //            $data = json_decode($response, true);
    //            $rateId = $data['rate_id'];
    //            $proRataPremium = $data['ProRataPremium'];
    //
    //            if($request->quote_number){
    //                $re = ReratedPremiumQuote::where('rate_id',$data['rate_id'])->get(array('discount_surcharge'));
    //                $total = $re->sum('discount_surcharge');
    //                if($total != 0){
    //                    $totalValue = $proRataPremium + (($total/100)*$proRataPremium);
    //                    $fp = number_format((float) $totalValue*1.08, 2, '.', '');
    //                }else{
    //                    $fp = $proRataPremium*1.08;
    //                }
    //
    //                return response()->json([
    //                'success'=>true,
    //                'rate_id'=>$rateId,
    //                'ProRataPremium'=>$fp
    //            ],200);
    //        }else{
    //                return response()->json([
    //                    'success'=>false,
    //                    'message'=>'Quote number not is empty',
    //                ],401);
    //        }
    //        }catch(\Exception $e){
    //            return response()->json([
    //                'success'=>false,
    //                'message'=>$e->getMessage(),
    //            ],401);
    //        }
    //    }

    public function getProRataPremium(Request $request)
    {
        try {
            $day = $request->day;
            $today = date("d");
            $month = (int)date("m");
            $year = (int)date("Y");

            if ($request->quote_number) {
                $premium = MotorComprehensiveQuotes::where('ratings_id', $request->rate_id)->first(array('premiumAnnually'));
                if ($premium && $premium->premiumAnnually) {
                    $premium = round(($premium->premiumAnnually) / 12 * 1.08, 2);
                    $diff = $day - $today;
                    $number = (int) date('t', mktime(0, 0, 0, $month, 1, $year)); // 31 in January

                    if ($diff < 0)
                        $left = $number + $diff;
                    else
                        $left = $diff;

                    $proRataPremium = round(($premium / 30.4375) * $left, 2); //30.4375 is an average days in month for a year

                    return response()->json([
                        'success' => true,
                        'rate_id' => $request->rate_id,
                        'ProRataPremium' => $proRataPremium
                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Annual premium not found',
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote number is empty',
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function calculatePerDayPremium(Request $request)
    {
        try {
            $premium = MotorComprehensiveQuotes::where('quoteNumber', $request->quoteNumber)->first(array('premiumAnnually'));
            if ($premium && $premium->premiumAnnually) {
                $premium = round(($premium->premiumAnnually) / 12 * 1.08, 2);

                $perDayPremium = round($premium / 30.4375, 2); //30.4375 is an average days in month for a year
                $tilldate = round($request->day * $perDayPremium, 2);
                $date = Carbon::parse(today()->subDays(- ($request->day)))->format('d-m-Y');

                return response()->json([
                    'success' => true,
                    'quoteNumber' => $request->quoteNumber,
                    'perDayPremium' => $perDayPremium,
                    'tilldate' => $tilldate,
                    'premiumDate' => $date,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Premium values not found on quote'
                ], 401);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage() . ' ' . $ex->getLine()
            ], 401);
        }
    }

    public function perDayPremium($quoteNumber)
    {
        try {
            return view('admin.policy.quotes.per_day_premium', compact('quoteNumber'));
        } catch (\Exception $ex) {
        }
    }

    public function renewPolicyQuote(Request $request)
    {


        try {

            $make = $request->get('make');
            $year = $request->get('year');
            $model = $request->get('model');
            $dob = date("d/m/Y", strtotime($request->get('dob')));
            $sum_insured = $request->get('estimatedValue');
            $status = $request->get('is_imported');
            $marital_status = $request->get('marital');
            $claim_count = $request->get('prior_accidents');
            $gender = $request->get('gender');

            if ($marital_status == 1) {
                $updated_marital_status = "Never Married";
            } elseif ($marital_status == 2) {
                $updated_marital_status = "Married Before";
            } elseif ($marital_status == 3) {
                $updated_marital_status = "Married Before";
            } elseif ($marital_status == 4) {
                $updated_marital_status = "Married Before";
            } elseif ($marital_status == 5) {
                $updated_marital_status = "Never Married";
            } elseif ($marital_status == 6) {
                $updated_marital_status = "Married Before";
            } else {
                return response()->json(['success' => 'false', 'data' => null, 'message' => 'Please provide marital status'], 401);
            }

            if ($gender == 1) {
                $updated_gender = "Male";
            } elseif ($gender == 0) {
                $updated_gender = "Female";
            } else {
                return response()->json(['success' => 'false', 'data' => null, 'message' => 'Please provide gender'], 401);
            }


            if (isset($request->name) && $request->name == 'Reinstate') {
                $surcharge = \AlphaDirect\Lookup::where('key', 'resinstate_surcharge')->first('value');
                $adition = $surcharge->value / 100 * $sum_insured;
                $sum_insured = $sum_insured + $adition;
            }



            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $_ENV['RATINGS_URL'] . 'calculation',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{
                "make":"' . $make . '",
                "model":"' . $model . '",
                "manufacturing_year":"' . $year . '",
                "dob":"' . $dob . '",
                "sum_insured":"' . $sum_insured . '",
                "status":"' . $status . '",
                "marital_status":"' . $updated_marital_status . '",
                "claim_count":"' . $claim_count . '",
                "gender":"' . $updated_gender . '"
                }',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Cookie: __cfduid=d52d1be0186f72c5ab914ad142d22ba941620644343'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $data = json_decode($response, true);

            $logData = [
                "ratings_id" => $data['rate_id'],
                "policy_number" => $request->policyNumber,
                "month_ins" => $data['monthly_premium_vat'],
                "three_ins" => $data['threemonthly_preminum_vat'],
                "annual_ins" => $data['result'],

                "customer_marital_status" => $marital_status,
                "customer_dob" => $dob,
                "customer_gender" => $gender,
                "japnese_import" => $status,
                "make" => $make,
                "model" => $model,
                "sum_assured" => $sum_insured,
                "claim_count" => $claim_count,
                "manufacturing_year" => $year,
                "rerated_by" => $request->get('user_id'),
            ];

            $addRateLog = PolicyPremiumReratingLog::addReratingLog($logData);

            $store = $this->storeMotorComprehensiveQuoteRenew($request->all(), $logData);

            return response()->json(['success' => 'true', 'data' => $data, 'message' => 'Request is successful'], 200);
        } catch (\Exception $ex) {
            return response()->json(['success' => 'false', 'data' => null, 'message' => $ex->getMessage() . ' ' . $ex->getCode()], 401);
        }
    }

    public function storeMotorComprehensiveQuoteRenew($data, $logData)
    {
        try {
            $dataAdd = new MotorComprehensiveQuotes();
            $dataAdd->customer_id = $data['customer_id'];
            $dataAdd->quoteNumber = $this->generateQuoteNumber();
            $dataAdd->is_imported = $data['is_imported'];
            $dataAdd->type = 'Renew';

            //            $make = $data[''];
            //            $model = $data[''];

            $dataAdd->make = $data['make'];
            $dataAdd->model = $data['model'];
            $dataAdd->purpose = 27;
            $dataAdd->manufacturingYear = $logData['manufacturing_year'];
            $dataAdd->estimatedValue = $data['estimatedValue'];
            $dataAdd->ratings_id = $logData['ratings_id'];
            $dataAdd->priorAccidents = $logData['claim_count'];
            $dataAdd->premium = 0;
            $quotesetting = QuoteSettings::orderBy('id', 'DESC')->first(array('DaysToExpireQuote'));
            $dataAdd->expiry_date = Carbon::now()->addDays($quotesetting->DaysToExpireQuote)->format('Y-m-d');

            if ($data['estimatedValue'] != 0) {
                $rate = number_format((float)($logData['annual_ins'] / $data['estimatedValue']) * 100, 2, '.', '');
                if ($rate < 2.24) { //minimum premium rate is 2.24
                    Log::info('Rate is smaller than 2.24');
                    return false;
                } else {
                    $dataAdd->premium_rate = $rate;
                }
            } else {
                Log::info('Estimated value found null or 0');
                return false;
            }

            $dataAdd->premiumMonthly = $logData['month_ins'];
            $dataAdd->premiumAnnually = $logData['three_ins'];
            $dataAdd->premium3Inst = $logData['annual_ins'];
            //$dataAdd->agentID = $request->agentID;

            //            if($request->agentID != null)
            //                $dataAdd->storeID = $request->stores;

            $dataAdd->status = 1;
            $dataAdd->save();

            $product = Product::where('id', 3)->first();

            if ($product != null) {
                $quote = new Quote();
                $quote->quoteCode = $dataAdd->quoteNumber;
                $quote->customerId = $data['customer_id'];
                //$quote->agentId = ;
                //$quote->userIPAddress = $request->ip();
                $quote->productId = $product->id;
                $quote->planId = null;
                $quote->has_vehicle = $product->has_vehicle;
                $quote->has_member = $product->has_member;
                $quote->is_motor_items = $product->is_motor_items;
                $quote->preinspection = $product->preinspection;
                $quote->quote_limit = $product->limit;
                $quote->kyc_customer = $product->kyc_customer;
                $quote->kyc_recipient = $product->kyc_recipient;
                $quote->save();
            }

            return $dataAdd->quoteNumber;
        } catch (\Exception $ex) {
            return null;
        }
    }

    public function processRerate($data)
    {
        try {
            $make = $data['make'];
            $year = $data['year'];
            $model = $data['model'];
            $dob = date("d/m/Y", strtotime($data['dob']));
            $sum_insured = $data['estimatedValue'];
            $status = ($data['is_imported'] == 1) ? "Yes" : "No";
            $marital_status = $data['marital'];
            $claim_count = $data['prior_accidents'];
            $gender = $data['gender'];

            if ($marital_status == 1) {
                $updated_marital_status = "Never Married";
            } elseif ($marital_status == 2) {
                $updated_marital_status = "Married Before";
            } elseif ($marital_status == 3) {
                $updated_marital_status = "Married Before";
            } elseif ($marital_status == 4) {
                $updated_marital_status = "Married Before";
            } elseif ($marital_status == 5) {
                $updated_marital_status = "Never Married";
            } elseif ($marital_status == 6) {
                $updated_marital_status = "Married Before";
            } else {
                return ['status' => 'failed', 'message' => 'Maritial status not found'];
                //return response()->json(, 401);
                //return Redirect::back()->with('error','Maritial status not found');
            }

            if ($gender == 1) {
                $updated_gender = "Male";
            } elseif ($gender == 0) {
                $updated_gender = "Female";
            } else {
                return ['status' => 'failed', 'message' => 'Gender not found'];
                //return response()->json(, 401);
            }

            /*Hardcoded URL because ENV variables (env('RATINGS_URL')) is not working*/

            if (env('APP_STATUS') == 'Production')
                $rating_url = 'https://rate.alphadirect.co.bw/api/';
            else
                $rating_url = 'https://devratings.alphadirect.co.bw/api/';

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $rating_url . 'calculation',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{
                "make":"' . $make . '",
                "manufacturing_year":"' . $year . '",
                "dob":"' . $dob . '",
                "sum_insured":"' . $sum_insured . '",
                "status":"' . $status . '",
                "marital_status":"' . $updated_marital_status . '",
                "claim_count":"' . $claim_count . '",
                "gender":"' . $updated_gender . '"
                }',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Cookie: __cfduid=d52d1be0186f72c5ab914ad142d22ba941620644343'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $res = json_decode($response, true);

            //dd($res,[$make,$year,$dob,$sum_insured,$status,$updated_gender,$claim_count,$updated_marital_status]);

            if ($data['generate_quote'] == 1) {

                $logData = [
                    "ratings_id" => $res['rate_id'],
                    "policy_number" => $data['policyNumber'],
                    "month_ins" => $res['monthly_premium_vat'],
                    "three_ins" => $res['threemonthly_preminum_vat'],
                    "annual_ins" => $res['result'],

                    "customer_marital_status" => $marital_status,
                    "customer_dob" => $dob,
                    "customer_gender" => $gender,
                    "japnese_import" => $status,
                    "make" => $make,
                    "model" => $model,
                    "sum_assured" => $sum_insured,
                    "claim_count" => $claim_count,
                    "manufacturing_year" => $year,
                    "rerated_by" => -1,
                ];

                $addRateLog = PolicyPremiumReratingLog::addReratingLog($logData);

                //$quoteNumber = $this->storeMotorComprehensiveQuoteRenew($data);
                $quoteNumber = null;

                if ($res != null && $res["success"] == 1) {
                    $renewal = PolicyRenewal::where('id', $data['data_id'])->first();
                    $renewal->new_premium = $res["result"];
                    //                    $renewal->new_quote_number = $quoteNumber;
                    $renewal->is_rated = 1;
                    $renewal->sum_assured = $sum_insured;
                    $renewal->save();
                }

                return ['status' => 'success', 'quoteNumber' => $quoteNumber, 'is_rerated' => $data['generate_quote']];
            }

            return ['status' => 'success', 'quoteNumber' => null, 'is_rerated' => $data['generate_quote']];
        } catch (\Exception $ex) {

            return ['status' => 'failed', 'quoteNumber' => null, 'is_rerated' => null, 'message' => $ex->getMessage()];
            //return response()->json(, 401);
        }
    }


    public function calculateProrataPremium(Request $request)
    {
        try {

            $data = [
                "policyNumber" => $request->policyNumber,
                "frequency" => $request->frequency,
                "billingDate" => $request->billingDate
            ];

            $prorataValue = $this->calculateProrataPremiumFunc($data);

            return $prorataValue;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() . ' ' . $e->getLine(),
            ], 401);
        }
    }


    public function calculateProrataPremiumFunc($data)
    {
        try {

            if ($data['policyNumber']) {

                $premium = PolicyRenewal::where('policyNumber', $data['policyNumber'])->orderBy('id', 'desc')->first(array('new_premium', 'expiry_date'));

                if ($premium && $premium->new_premium) {

                    switch ($data['frequency']) {
                        case 1:
                            $annual = ($premium->new_premium * 12) / 1.08;
                            break;
                        case 2:
                            $annual = ($premium->new_premium) * 3;
                            break;
                        case 3:
                            $annual = ($premium->new_premium);
                            break;
                        default:
                            $annual = ($premium->new_premium);
                    }

                    if (isset($data['billingDate'])) {
                        $billingDays = strtotime($data['billingDate']);
                        $expiryDays = strtotime($premium->expiry_date);

                        // $billingDays = str_replace('/', '-', $data['billingDate']);
                        // dd($billingDays);
                        // $billingDays = date("Y-m-d",strtotime($billingDays));
                        // $billingDays = strtotime($billingDays);
                        // dd($billingDays,$expiryDays);
                    }


                    // $datetime1 = $data['billingDate'];
                    // $datetime2 = new Carbon($premium->expiry_date);
                    // $interval = $datetime1->diff($datetime2);
                    // $days = (int)$interval->format("%r%a");
                    // dd($days);

                    // $date1=date_create($data['billingDate']);
                    // $date2=date_create($premium->expiry_date);
                    // $diff=date_diff($date1,$date2);
                    // dd($diff);

                    // $diff = date_diff($billingDays, $premium->expiry_date);
                    // dd($diff);
                    $diff = ($expiryDays - $billingDays) / 60 / 60 / 24;
                    // dd($diff);
                    $proRataPremium = round(($annual / 30.4375) * $diff); //30.4375 is an average days in month for a year

                    return response()->json([
                        'success' => true,
                        'policyNumber' => $data['policyNumber'],
                        'ProRataPremium' => $proRataPremium
                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Annual premium not found',
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy number is empty',
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() . ' ' . $e->getLine(),
            ], 401);
        }
    }
}
