<?php

namespace AlphaDirect\Http\Controllers\FrontendPay;

use AlphaDirect\Activation;
use AlphaDirect\AgentLogins;
use AlphaDirect\ArchivedPolicies;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\CustomerGeneratedActivationCode;
use AlphaDirect\CustomerProfile;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Lead;
use AlphaDirect\Mail\ForgotPassword;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\OTP;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyLead;
use AlphaDirect\PolicyMember;
use AlphaDirect\KycCompliance;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Region;
use AlphaDirect\Stores;
use AlphaDirect\User;
use AlphaDirect\Banks;
use AlphaDirect\Models\NgeniusTransection;
use AlphaDirect\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Customer;
use AlphaDirect\CustomerMati;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Events\CancelScheduleTransactionEvent;
use AlphaDirect\Events\CancelTokenEvent;
use AlphaDirect\MatiVerification;
use AlphaDirect\Transaction;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PolicyBundled;
use AlphaDirect\Models\PolicyUpgrade;

use AlphaDirect\Models\PolicyUpgradeMotorcomp;
use AlphaDirect\MotorGetPolicyDetail;
use AlphaDirect\Http\Controllers\PayM8Controller;


// use Hash;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Pnlinh\InfobipSms\Facades\InfobipSms;

// use Redirect;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\Payment\Flutterwave\FlutterwaveController;
use AlphaDirect\Repositories\ClaimCellphone\ClaimCellphoneInterface;
use AlphaDirect\Repositories\Customer\CustomerInterface;
use AlphaDirect\Repositories\CustomerBanking\CustomerBankingInterface;
use AlphaDirect\Repositories\CustomerKyc\CustomerKycInterface;
use AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface;
use AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneInterface;
use Illuminate\Support\Facades\Validator;
use AlphaDirect\Events\CommissionPolicyEvent;
use AlphaDirect\Events\CreatePolicyEvent;
use AlphaDirect\Events\DecrementCounterIfPolicySellsEvent as DecrementCounter;
use AlphaDirect\Mail\ForgotPasswordRepaircenter;
use AlphaDirect\RepairCenter;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\UserPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Jose\Util\Hash as UtilHash;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Modules\Inventory\Entities\StoresInventory;
use Illuminate\Mail\Markdown;
use AlphaDirect\OTPTemp;
use AlphaDirect\Config;
use AlphaDirect\CustomerConsent;
use PDF;
use AlphaDirect\Models\BundledRerate;
use AlphaDirect\Http\Controllers\NgeniusPaymentController;
use AlphaDirect\PolicyTerm;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use stdClass;
use AlphaDirect\Models\PaymentEmail;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
use Log;
class CustomerController extends Controller
{
    protected $claim_cellphone_interface;
    protected $policy_cell_phone_interface;
    protected $customer_interface;
    protected $customer_profile_interface;
    protected $customer_kyc_interface;
    protected $customer_banking_interface;

    public function __construct(ClaimCellphoneInterface $claim_cellphone_interface, PolicyCellPhoneInterface $policy_cell_phone_interface, CustomerInterface $customer_interface, CustomerProfileInterface $customer_profile_interface, CustomerKycInterface $customer_kyc_interface, CustomerBankingInterface $customer_banking_interface)
    {
        $this->claim_cellphone_interface = $claim_cellphone_interface;
        $this->policy_cell_phone_interface = $policy_cell_phone_interface;
        $this->customer_interface = $customer_interface;
        $this->customer_profile_interface = $customer_profile_interface;
        $this->customer_kyc_interface = $customer_kyc_interface;
        $this->customer_banking_interface = $customer_banking_interface;
    }

    public function isImageValid($image)
    {
        try {
            $data = Image::make($image)->exif();
            if ($data != null) {
                $exif = exif_read_data($image, 0, true);
                if ($exif) {
                    if (array_key_exists('EXIF', $exif)) {
                        if (array_key_exists('DateTimeOriginal', $exif['EXIF'])) {
                            $DateTime = Carbon::parse($exif['EXIF']['DateTimeOriginal'])->timestamp;
                        } elseif (array_key_exists('aTime' || 'DateTime', $exif['EXIF'])) {
                            $DateTime = Carbon::parse($exif['EXIF']['aTime' || 'DateTime'])->timestamp;
                        } else {
                            return false;
                        }
                        $createdDateTime = Carbon::createFromTimestamp($DateTime);
                        $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                        $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                        if ($diff_in_hours > 24)
                            return false;
                        elseif ($diff_in_hours < 24)
                            return true;
                        else
                            return false;
                    } elseif (array_key_exists('FILE', $exif)) {
                        $DateTime = $exif['FILE']['FileDateTime'];
                        $createdDateTime = Carbon::createFromTimestamp($DateTime);
                        $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                        $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                        if ($diff_in_hours > 24)
                            return false;
                        elseif ($diff_in_hours < 24)
                            return true;
                        else
                            return false;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getCustomer(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'policyNumber' => 'required'
        ]);

        try{
            if(Policy::where('policyNumber', $request->policyNumber)->exists())
            {
                $policy = Policy::with('customer')->where('policyNumber', $request->policyNumber)->first(array('id','customer_id','quoteNumber','policyNumber','premium','first_premium','first_premium_wvat','premium_freq','billing_day','isVirtualBox','BillingStart','billingStartDate'));

                $quotes = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('id','ratings_id','premiumMonthly','premiumAnnually','premium3Inst'));
                $pay_email = PaymentEmail::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first();
            if(isset($pay_email) && $pay_email != null && $pay_email->pay_email != null){
                  $email = $pay_email->pay_email;
            }else if(isset($policy->customer) && $policy->customer->email != null){
                  $email = $policy->customer->email;
            }else{
                  $email = null;
            }
                return response()->json(['status' => true, 'customer'=> $policy->customer, 'policy'=> $policy,'quotes'=> $quotes,'email'=>$email], 200);
            }else{
                return response()->json(['status' => false, 'message'=> 'policy number not found.'], 400);
            }
        }catch(Exception $e)
        {
            return response()->json(['status' => false, 'message'=> 'policy number not found.'], 400);
        }
    }

    public function checkVehicleExistWIthStatusActive($vehiclePlate)
    {
        try {
            $count = Vehicle::join('policies', 'policies.id', 'vehicle.policy_id')->where('vehicle.vehiclePlate', $vehiclePlate)
                    ->where('policies.status', '!=', 2)->count();
            if ($count > 0)
                return true;
            else
                return false;
        } catch (Exception $e) {

            return null;
        }
    }

    public function getAgent()
    {
        try {
            $agents = User::role('Agent')->where('active', 1)->get(array('id', 'firstName', 'lastName'))->toArray();
            $ids = array();
            foreach ($agents as $a) {
                if ($a['id'] != null) {
                    array_push($ids, $a['id']);
                }
            }
            $keys = array_rand($ids);

            return $ids[$keys];
        } catch (Exception $e) {
            return null;
        }
    }

    public function imageUpload(Request $request)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $result = $this->isImageValid($file);
            //if ($result) {
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/temp/' . md5(rand(10, 1000)) . time() . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            return response()->json(['success' => true, 'filePath' => $filePath], 200);
            // } else {
            //     return response()->json(['success' => false, 'Message' => 'The photo is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
            // }
        }
    }

    public function validateFields($data)
    {
        //check the age
        $dateOfBirth = Carbon::createFromFormat('d/m/Y', $data['dob'])->format('Y-m-d');
        $years = Carbon::parse($dateOfBirth)->age;
        if ($years < 18) {
            return ['success' => false, 'Message' => 'Customer age is less than 18.'];
        }

         if (isset($data['passport']) && isset($data['omang']) && $data['passport'] == null && $data['omang'] == null) {
            return response()->json(['status' => 'Failed', 'message' => 'Either omang or passport is mandatory'], 401);
        } elseif (isset($data['passport']) && isset($data['omang']) && $data['passport'] != null && $data['omang'] == null) {
            unset($data['omang']);
            if (isset($data['omangexpiry'])) {
                unset($data['omangexpiry']);
            }
            if (isset($data['passportexpiry']) && $data['passportexpiry'] == "") {
                unset($data['passportexpiry']);
            }
            if (isset($data['passportIssuingCountry']) && $data['passportIssuingCountry'] == "") {
                unset($data['omangexpiry']);
            }
        } else {
            unset($data['passport']);
            if (isset($data['passportexpiry'])) {
                unset($data['passportexpiry']);
            }
            if (isset($data['passportIssuingCountry'])) {
                unset($data['passportIssuingCountry']);
            }
            if (isset($data['omangexpiry']) && $data['omangexpiry'] == "") {
                unset($data['omangexpiry']);
            }
        }
        //Commented as MATI is implemented
        /*
            //If Empty the Omang and Passport are already uploaded
            if (isset($data['omangKyc']) && $data['omangKyc'] == "")
                unset($data['omangKyc']);
            if (isset($data['omangbackKyc']) && $data['omangbackKyc'] == "")
                unset($data['omangbackKyc']);
            if (isset($data['passportKyc']) && $data['passportKyc'] == "")
                unset($data['passportKyc']);
        */
        //get the product details
        if ($data['product'] != null)
            $product = Product::where('id', $data['product'])->first(array('id', 'has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
        else
            return ['success' => false, 'message' => "Product ID is empty"];

        //remove unwanted data from request body as per the product
        unset($data['store_id'],
            $data['middleName'],
            $data['agent_id'],
            $data['premium_label_vat'],
            $data['leftout_premium'],
            $data['other_finance'],
            $data['password'],
            $data['cpassword'],
            $data['has_member'],
            $data['has_vehicle'],
            $data['store_id'],
            $data['email'],
            $data['agentCode'],
            $data['billing_day'],
            $data['leftout_premium_wvat'],
            $data['email'],
            $data['passportexpiry'],
            $data['wo_vat'],
            $data['is_broker'],
            $data['customerKYCToken'],
            $data['sum_insured']
        );
         unset($data['p1_m_a_d_i'],
            $data['t_p_c_i'],
            $data['motor_comprehensive'],
            $data['legal_insurance'],
            $data['c_d_insurance'],
            $data['frequency_mc'],
            $data['first_month_premium'],
            $data['BillingStart'],
            $data['paydate_label_month_premium'],
            $data['sum_insured']




        );


if (  isset($data->product_id_li) && $data->product_id_li != 4  ) {

         unset($data['e_name'],
            $data['emp_no'],
            $data['emp_phone'],
            $data['legal_insurance'],
            $data['salary_pay_date'],
            $data['legalFName'],
            $data['legalMName'],
            $data['legalLName'],
            $data['legalPhone'],
            $data['legalEmail'],
            $data['legalDOB'],
            $data['legalOmang'],
            $data['legalPassport'],
            $data['omangExpiry'],
            $data['passportExpiry'],

        );

 }elseif (   $data['product'] != 4  ) {

         unset($data['e_name'],
            $data['emp_no'],
            $data['emp_phone'],
            $data['legal_insurance'],
            $data['salary_pay_date'],
            $data['legalFName'],
            $data['legalMName'],
            $data['legalLName'],
            $data['legalPhone'],
            $data['legalEmail'],
            $data['legalDOB'],
            $data['legalOmang'],
            $data['legalPassport'],
            $data['omangExpiry'],
            $data['passportExpiry'],

        );


 }elseif(  $data['product'] == 4 && $data['maritalstatus'] != 2 ) {

    unset($data['legalFName'],
    $data['legalMName'],
    $data['legalLName'],
    $data['legalPhone'],
    $data['legalEmail'],
    $data['legalDOB'],
    $data['legalOmang'],
    $data['legalPassport'],
    $data['omangExpiry'],
    $data['passportExpiry'],

);
 }






 if ($data['Payment_method'] == "RealPay") {
            if ($data['bankName'] == null || $data['branchCode'] == null || $data['bankAccountType'] == null || $data['accountNumber'] == null) {
                return [
                    'error' => false, 'message' => "Sorry, Please provide complete account details to process policy with RealPay"
                ];
            }
        } else {
            unset($data['bankName'],
                $data['branchCode'],
                $data['bankAccountType'],
                $data['confirmAccountNumber'],
                $data['accountNumber']);
        }
        if ( $data['Payment_method'] != "DPO" ){
            unset($data['pay_email']
           );
        }else{
            unset($data['pay_email']);
        }
        if ($data['Payment_method'] == "dpo" && isset($data['email']) && ($data['email'] == null || $data['email'] == '')) {
            return [
                'error' => false, 'message' => "Sorry, Please provide email ID to process payment with DPO"
            ];
        }

        switch ($data['product']) {
            case 1:
                unset(
                    $data['purpose'],
                    $data['middleName'],
                    $data['generatedQuoteCode'],
                    $data['quoteNumber'],
                    $data['leftout_premium'],
                    $data['leftout_premium_wvat'],
                    $data['wo_vat'],
                    $data['frequency'],
                    $data['make'],
                    $data['year'],
                    $data['model'],
                    $data['claim_count'],
                    $data['mileage'],
                    $data['condition'],
                    $data['estimated_value'],
                    $data['vehiclePlate'],
                    $data['vinnumber'],
                    $data['enginenumber'],
                    $data['model_other_value']
                );
                break;
            case 2:
                unset(
                    $data['generatedQuoteCode'],
                    $data['middleName'],
                    $data['quoteNumber'],
                    $data['leftout_premium'],
                    $data['leftout_premium_wvat'],
                    $data['wo_vat'],
                    $data['frequency'],
                    $data['claim_count'],
                    $data['estimated_value'],
                    $data['beneficiaries'],
                    $data['password'],
                    $data['purpose'],
                    $data['cpassword'],
                    $data['vinnumber'],
                    $data['enginenumber'],
                    $data['model_other_value']
                );
                break;
            case 3:
                unset(
                    $data['quoteNumber'],
                    $data['middleName'],
                    $data['beneficiaries'],
                    $data['password'],
                    $data['cpassword'],
                    $data['is_activation'],
                    $data['passport'],
                    $data['passportIssuingCountry'],
                    $data['is_broker'],
                    $data['vinnumber'],
                    $data['enginenumber'],
                    $data['model_other_value'],
                    $data['billing_date']
                );
                break;
            case 4:
                unset(
                    $data['purpose'],
                    $data['middleName'],
                    $data['generatedQuoteCode'],
                    $data['quoteNumber'],
                    $data['leftout_premium'],
                    $data['leftout_premium_wvat'],
                    $data['wo_vat'],
                    $data['frequency'],
                    $data['make'],
                    $data['year'],
                    $data['model'],
                    $data['claim_count'],
                    $data['mileage'],
                    $data['condition'],
                    $data['estimated_value'],
                    $data['vehiclePlate'],
                    $data['vinnumber'],
                    $data['legalFName'],
                    $data['legalMName'],
                    $data['legalLName'],
                    $data['legalPhone'],
                    $data['legalEmail'],
                    $data['legalGender'],
                    $data['legalDOB'],
                    $data['legalOmang'],
                    $data['legalPassport'],
                    $data['omangExpiry'],
                    $data['passportExpiry'],
                    $data['enginenumber'],
                    $data['model_other_value']
                );
                break;
            case 5:
                unset(
                    $data['purpose'],
                    $data['middleName'],
                    $data['generatedQuoteCode'],
                    $data['leftout_premium'],
                    $data['leftout_premium_wvat'],
                    $data['wo_vat'],
                    $data['frequency'],
                    $data['make'],
                    $data['year'],
                    $data['model'],
                    $data['claim_count'],
                    $data['mileage'],
                    $data['condition'],
                    $data['estimated_value'],
                    $data['vehiclePlate'],
                    $data['frequency'],
                    $data['quoteNumber'],
                    $data['vinnumber'],
                    $data['enginenumber'],
                    $data['model_other_value']
                );
                break;
        }


        //Return with error message if any of the required value as per the product is empty
        foreach ($data as $key => $d) {
            if ($d == null || $d == '') {
                return ['success' => false, 'message' => $key . ' is required'];
            }
        }


        //Check vehicle plate and vehicle existence with the vehicle plate number
        if ($product->has_vehicle == 1) {

            //Check vehicle purpose i.e it should only be private
            if ($data['product'] == 3) {
                if ($data['purpose'] != '27' || $data['purpose'] != 27) {
                    return ['success' => false, 'message' => "Sorry, Currently we can only provide vehicles with personal use"];
                }
            }

            //check if the vehicle plate is valid and in format(B123XYZ) or not
            $regex = '^[Bb]{1}\d{3}[a-zA-Z]{3}$^';
            // Defensive: PHP 8+ throws "Undefined array key" → ErrorException
            // when the request omits this key. Fall back to '' so the regex
            // below short-circuits with a clean "format" message instead of
            // bubbling a 500 with a generic "Server Error" body.
            $vehiclePlate = $data['vehiclePlate'] ?? '';
            $result = preg_match($regex, $vehiclePlate);
            if ($result == 0) {
                return ['success' => false, 'message' => "Vehicle plate is not in correct format"];
            }

            //Check if the vehicle is already registered with another policy and if so, it should be only in cancelled state
            $exist = $this->checkVehicleExistWIthStatusActive($vehiclePlate);
            if ($exist == true) {
                return [
                    'success' => false, 'message' => "Vehicle already associated with existing policy"
                ];
            }

            //Estimated Value should be between P20K and P500K
            if ($product->id == 3) {
                if (isset($data['agentCode']) && $data['agentCode'] != null && $data['estimated_value'] <= 500000) {
                    $agentData = User::where('id', $data['agentCode'])->first(array('bypass_500k'));
                    if ($agentData->bypass_500k != 1) {
                        return [
                            'success' => false, 'message' => "Please provide valid estimated value of the vehicle"
                        ];
                    }
                }

                if (isset($data['estimated_value']) && $data['estimated_value'] != null && ($data['estimated_value'] >= 20000) == false) {
                    return [
                        'success' => false, 'message' => "Please provide valid estimated value of the vehicle"
                    ];
                }
            }

            //Check vehicle make
            if (isset($data['make']) && $data['make'] == null) {
                return [
                    'success' => false, 'message' => "Please provide valid make of the vehicle"
                ];
            }

            //Check vehicle model
            if (isset($data['model']) && $data['model'] == null) {
                return [
                    'success' => false, 'message' => "Please provide valid valid model of the vehicle"
                ];
            }

        }

        //P1 million validation
        if ($product->has_member == 1) {

            $omang    = isset($data['omang']) && $data['omang'] != null ? htmlspecialchars(strip_tags($data['omang'])) : null;
            $passport = isset($data['passport']) && $data['passport'] != null ? htmlspecialchars(strip_tags($data['passport'])) : null;
            $idType   = $omang != null ? "Omang" : "Passport";
            $idValue  = $omang != null ? $omang : $passport;

            $customerCheck = $this->checkIfMemberAlreadyRegistered($idType, $idValue);

            if ($customerCheck == true) {
                return response()->json(['title' => 'Customer already registered for this policy', 'error' => 'Customer is already owner of Accidental death insurace policy. Please check again.'], 400);
            }

            if (isset($data['beneficiaries'])) {
                $totalPercent = 0;
                foreach ($data['beneficiaries'] as $b) {
                    $totalPercent += (int)$b['beneficiaryPayment'];
                }
                if ($totalPercent > 100) {
                    return [
                        'success' => false, 'message' => "Beneficiary(s) total payment % should be less than 100%"
                    ];
                }
            }
        }

        //Cellphone Validations
        if ($product->type == 'Cellphone') {
            if ($data['devices'] != null) {
                $totalValue = 0;
                foreach ($data['devices'] as $key => $device) {
                    $totalValue += $device['phone_value'];
                    if (!isset($device['imei']) || $device['imei'] == null) {
                        return ['title' => 'Please add devices first', 'error' => 'Please add devices first.'];
                    }
                }
                if ($totalValue > 5000) {
                    return [
                        'success' => false, 'message' => "Total value(s) of the device should not exceed P5,000"
                    ];
                }
            } else {
                return ['title' => 'Please add devices first', 'error' => 'Please add devices first.'];
            }
        }

        if (isset($data['billing_day']) && $data['billing_day'] == '' && $data['frequency'] == 1) {
            return [
                'success' => false, 'message' => "Sorry, you need to select billing start day"
            ];
        }
        if (isset($data['frequency']) && $data['frequency'] == null && $data['frequency'] == 3) {
            return [
                'success' => false, 'message' => "Sorry, Please choose premium frequency"
            ];
        }
        if ($data['Payment_method'] == null) {
            return [
                'success' => false, 'message' => "Sorry, Please choose payment method"
            ];
        }


        return null;
    }



    /**
     * Build the driver's-licence fields for customer_profile from the request.
     * Mirrors the product-2 (Third Party Car) flow's column set:
     *   driving_license / license_class / license_valid_from / license_valid_to.
     *
     * Guarded with the live column list so an environment that hasn't run the
     * 2026_06_02 add-driving-licence migration won't break createPolicy. The FE
     * (graphite-v2) sends licenseValidFrom/To as d/m/Y (same as dob); fall back
     * to Carbon::parse for any other shape.
     */
    private function buildLicenseProfileData(Request $request)
    {
        $out  = [];
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('customer_profile');

        $number = $request->input('drivingLicenseNumber');
        $class  = $request->input('licenseClass');
        $from   = $request->input('licenseValidFrom');
        $to     = $request->input('licenseValidTo');

        if (in_array('driving_license', $cols) && $number !== null && $number !== '') {
            $out['driving_license'] = htmlspecialchars(strip_tags($number));
        }
        if (in_array('license_class', $cols) && $class !== null && $class !== '') {
            $out['license_class'] = htmlspecialchars(strip_tags($class));
        }
        if (in_array('license_valid_from', $cols) && $from) {
            $out['license_valid_from'] = $this->parseLicenseDate($from);
        }
        if (in_array('license_valid_to', $cols) && $to) {
            $out['license_valid_to'] = $this->parseLicenseDate($to);
        }

        return $out;
    }

    /** Parse a licence date (d/m/Y from the FE, else best-effort) to Y-m-d. */
    private function parseLicenseDate($value)
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    public function createPolicy(Request $request)
    {

        if ($request->Payment_method == 'DPO') {
            if (!isset($request->email)) {
                return response()->json(['title' => 'Email is mandatory', 'description' => 'PLease provide email if payment method is DPO.'], 412);
            }
        }

        if( isset($request->sum_insured) &&  ($request->product == 3 || $request->product_id == 3) && str_replace(',', '', $request->sum_insured)  > 500000){

                if($request->is_broker != 1 || $request->agent_id == null || $request->stores == null  ){
                    return response()->json(['success' => false, 'message' => 'We can not process policies worth more than 500000BWP, Please contact Alphadirect office.'], 401);
                }else{
                    $agent_500k = User::where('id',$request->agent_id)->first();
                    if($agent_500k == null){
                            return response()->json(['success' => false, 'message' => 'Agent ID not found Please enter a valid Agent id'],401);


                    }else{
                        if($agent_500k->bypass_500k != 1){
                            return response()->json(['success' => false, 'message' => 'This Agent ID has no permission for Estimated Value of Vehicle more than 500000BWP'],401);
                        }
                    }

            }
        }

        //check stock availability
        // $stock = \Modules\Inventory\Entities\StoresInventory::where('store_id','=',$request->get('stores'))->where('product_id','=',$request->get('product'))->where('plan_id','=',$request->get('plan_id'))->first(array('counter'));
        // if(($stock && $stock->counter == '0') || !$stock){
        //     return response()->json(['success' => false, 'message' => 'stock is not available at the moment.'], 401);
        // }
        try {
            DB::beginTransaction();

            if($request->product_id == 1 || $request->product_id == 4 || $request->product_id == 3){
                $dateOfBCheck = Carbon::createFromFormat('d/m/Y', $request->dob)->format('Y-m-d');
                $totalYears = Carbon::parse($dateOfBCheck)->age;
                // if ($totalYears < 18 || $totalYears > 65) {
                //     return ['success' => false, 'Message' => 'Customer age is less than 18 or more than 65.'];
                // }

                if ($totalYears < 18) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18.'];
                }
            }


            $res = $this->validateFields($request->except('cust_dob','stores','e_name','emp_no','email','emp_phone','salary_pay_date','mati-identityId', 'customer_id', 'passportIssuingCountry', 'passportexpiry', 'omangExpiry', 'only_realpay_1','only_realpay_2'));

            if ($res != null && isset($res['success']) && $res['success'] == false) {
                return response()->json($res, 401);
            }
            if ($request->hasFile('front')) {
                $file = $request->file('front');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'message' => 'The photo of the Front of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }
            if ($request->hasFile('back')) {
                $file = $request->file('back');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'message' => 'The photo of the rear (back) of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }
            if ($request->hasFile('right')) {
                $file = $request->file('right');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'message' => 'The photo of the right of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }
            if ($request->hasFile('left')) {
                $file = $request->file('left');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'message' => 'The photo of the left of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                }
            }
            if ($request->hasFile('vehicleRegistration')) {
                $file = $request->file('vehicleRegistration');
                $result = $this->isImageValid($file);
                if ($result == false) {
                    return response()->json(['success' => false, 'message' => 'The photo of the Vehicle registration book is older than 24 hours, and can not be accepted. Please take a new image of the Vehicle registration book and re-upload.'], 401);
                }
            }

            // $validator = Validator::make($request->all(), [
            //     'premium' => 'required',
            // ]);

            $validator = Validator::make($request->except('mati-identityId','customer_id', 'm_status'), [
                'premium' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->messages()->first()], 401);
            }

//            if ($product->type == 'Cellphone') {
//                if ($request->get('devices') != null) {
//                    foreach ($request->get('devices') as $key => $device) {
//
//                        if (!isset($device['imei']) || $device['imei'] == null) {
//                            return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
//                        }
//                    }
//                } else {
//                    return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
//                }
//            }

            //check for email and cellphone
            /*
            switch (true) {
                case $request->get('omang') != null || $request->get('phone') != null:
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first();
                    if($profile == null){
                        $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first(); // as we pointing to customer table
                        if ($profile != null) {
                            $profile->customer_id = $profile->id;
                        }
                    }
                    dd($profile);
                    break;

                case $request->get('passport') != null || $request->get('phone') != null:
                    $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first();
                    if($profile == null){
                        $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first(); // as we pointing to customer table
                        if ($profile != null) {
                            $profile->customer_id = $profile->id;
                        }
                    }
                    break;


                case $request->get('email') != null:
                    $profile = Customer::where('email', $request->get('phone'))->orderBy('id', 'asc')->first(); // as we pointing to customer table
                    if ($profile != null) {
                        $profile->customer_id = $profile->id;
                    }
                    break;

                default:
                    $profile = null;
                    break;
            } */

            //check for omang and passport
            $profile = null;
            $event_data = null;

            if ($request->get('phone') != null) {
                $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first(array('id','is_blocked'));
            }
            // is blocked

            if($profile && $profile->is_blocked != null){
                if($profile->is_blocked == 1){
                    return response()->json(['success' => false,'message'=>'customer is blocked'], 200);
                }
            }


            if ($profile != null) {
                $profile->customer_id = $profile->id;

                if ($request->product == 1) {
                    $row = Policy::where('customer_id',$profile->customer_id)->where('product_id', $request->product)->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
                //Legal Products
                if ($request->product == 4) {
                    $row = Policy::where('customer_id',$profile->customer_id)->where('product_id', $request->product)->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
            }

            if ($profile == null) {
                if ($request->get('omang') != null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
                }
            }

            $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
            $mati_enable = $mati_config->value;

            if ($profile == null) {
                $fname = htmlspecialchars(strip_tags($request->input('firstname', '')));
                $lname = htmlspecialchars(strip_tags($request->input('lastname', '')));
                $email = htmlspecialchars(strip_tags($request->input('email', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('phone', '')));
                // $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));

                if ($request->input('mati-identityId') && $request->input('mati-identityId') != '' && $request->input('mati-identityId') != NULL && $request->input('mati-identityId') != 'null') {
                    $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));
                }else {
                    if($mati_enable == 0){
                        $mati_identity  = 0;
                    }else{
                        $mati_identity  = NULL;
                    }
                }

                $f_login = 0; // flag for first time login user
                $data = [
                    'firstName' => $fname,
                    'lastName' => $lname,
                    'email' => $email,
                    'cellphone' => $cellphone,
                    'f_login' => $f_login,
                    'mati_identity' => $mati_identity,
                ];

                if ($request->get('password') != NULL) {
                    $data['password'] = Hash::make($request->get('password'));
                } else {
                    $data['password'] = Str::random(8);
                }

                $user_id = $this->customer_interface->add_new_customer($data);

                // for Password Reset
                $token                  = Str::random(8);
                $user_password          = new UserPassword();
                $user_password->user_id = $user_id;
                $user_password->token   = $token;
                $url                    = env('LIVEQUOTE_URL').'reset_password_first_time.php?token='.$token;
                $user_password->url     = $url;
                $user_password->save();


                //sms
                $sms = new SmsMessaging();
                $sms->sendSmsUserCreate(29, $fname, $lname, $cellphone, $url);

                //mail
                if ($email != null) {
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->customer_id = $user_id;
                    $data->new_user_password_url_id = $user_password->id;
                    $data->hook = 'user_create';
                    $data->attachment = null;

                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    //$html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                //event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                    // return response()->json(['success' => 1, 'mail' => $mail], 200);
                }

                $gender         = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address        = htmlspecialchars(strip_tags($request->input('address', '')));
                $omang          = htmlspecialchars(strip_tags($request->input('omang', '')));
                $state          = htmlspecialchars(strip_tags($request->input('state', '')));
                $passport       = htmlspecialchars(strip_tags($request->input('passport', '')));
                $countryId      = htmlspecialchars(strip_tags($request->input('passportIssuingCountry', '')));
                $city           = htmlspecialchars(strip_tags($request->input('city', '')));
                $maritalstatus  = htmlspecialchars(strip_tags($request->input('maritalstatus', '')));
                $dob            = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
                $sourceOfIncome = json_encode($request->get('sourceOfIncome'));

                $data = [
                    'gender'      => $gender,
                    'customer_id' => $user_id,
                    'address'     => $address,
                    'omang'       => $omang,
                    'state'       => $state,
                    'passport'    => $passport,
                    //'countryId'       => $countryId,
                    'city'           => $city,
                    'maritalstatus'  => $maritalstatus,
                    'dob'            => $dob,
                    'sourceOfIncome' => $sourceOfIncome
                ];
                $data = array_merge($data, $this->buildLicenseProfileData($request));
                $this->customer_profile_interface->add_new_customer_profile($data);

                $omangExpiry = htmlspecialchars(strip_tags($request->input('omangexpiry', '')));
                $passportExpiry = htmlspecialchars(strip_tags($request->input('passportexpiry', '')));
                $omangFront = htmlspecialchars(strip_tags($request->input('omangKyc', '')));
                $omangBack = htmlspecialchars(strip_tags($request->input('omangbackKyc', '')));
                $passport = htmlspecialchars(strip_tags($request->input('passportKyc', '')));
                $data = [
                    'customer_id' => $user_id,
                    'omangExpiry' => $omangExpiry,
                    'passportExpiry' => $passportExpiry,
                    'passportIssuingCountry' => $countryId,
                ];
                if ($omangFront != NULL) {
                    $fileName = explode('/', $omangFront);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage:: disk('s3')->move($omangFront, $filePath);
                    $data['omang'] = $filePath;
                }
                if ($omangBack != NULL) {
                    $fileName = explode('/', $omangBack);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang_back' . '/' . $name;
                    Storage:: disk('s3')->move($omangBack, $filePath);
                    $data['omangBack'] = $filePath;
                }
                if ($passport != NULL) {
                    $fileName = explode('/', $passport);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/passport' . '/' . $name;
                    Storage:: disk('s3')->move($passport, $filePath);
                    $data['passport'] = $filePath;
                }
                $customerKYCToken = htmlspecialchars(strip_tags($request->get('customerKYCToken', NULL)));
                if ($customerKYCToken != NULL) {
                    $data['customer_id'] = $user_id;
                    $this->customer_kyc_interface->update_customer_kyc_by_token($customerKYCToken, $data);
                } else {
                    $this->customer_kyc_interface->add_new_customer_kyc($data);
                }
            } else {
                $fname     = htmlspecialchars(strip_tags($request->input('firstname', '')));
                $lname     = htmlspecialchars(strip_tags($request->input('lastname', '')));
                $email     = htmlspecialchars(strip_tags($request->input('email', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('phone', '')));
                // $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));
                if ($request->input('mati-identityId') && $request->input('mati-identityId') != '' && $request->input('mati-identityId') != NULL && $request->input('mati-identityId') != 'null') {
                    $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));
                } else {
                    $customer = Customer::where('id',$profile->customer_id)->first();
                    if (isset($customer)) {
                        $mati_identity = $customer->mati_identity;
                    } else {
                        if($mati_enable == 0){
                            $mati_identity  = 0;
                        }else{
                            $mati_identity  = NULL;
                        }
                    }
                }
                $data = [
                    'firstName' => $fname,
                    'lastName' => $lname,
                    'email' => $email,
                    'cellphone' => $cellphone,
                    'mati_identity' => $mati_identity,
                ];
                if ($request->get('password') != NULL) {
                    $data['password'] = Hash::make($request->get('password'));
                }
                $user_id = $profile->customer_id;
                //dd($profile, $data);
                $this->customer_interface->update_customer_by_id($user_id, $data);
                $gender         = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address        = htmlspecialchars(strip_tags($request->input('address', '')));
                $omang          = htmlspecialchars(strip_tags($request->input('omang', '')));
                $passport       = htmlspecialchars(strip_tags($request->input('passport', '')));
                $maritalstatus  = htmlspecialchars(strip_tags($request->input('maritalstatus', '')));
                $city           = htmlspecialchars(strip_tags($request->input('city', '')));
                $state          = htmlspecialchars(strip_tags($request->input('state', '')));
                $dob            = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
                $countryId      = htmlspecialchars(strip_tags($request->input('passportIssuingCountry', '')));
                $sourceOfIncome = json_encode($request->get('sourceOfIncome'));

                $data = [
                    'customer_id'    => $user_id,
                    'gender'         => $gender,
                    'address'        => $address,
                    'omang'          => $omang,
                    'passport'       => $passport,
                    'maritalstatus'  => $maritalstatus,
                    'countryId'      => $countryId,
                    'city'           => $city,
                    'state'          => $state,
                    'dob'            => $dob,
                    'sourceOfIncome' => $sourceOfIncome
                ];
                $data = array_merge($data, $this->buildLicenseProfileData($request));
                $profile = $this->customer_profile_interface->update_customer_profile($user_id, $data);
                $omangExpiry = htmlspecialchars(strip_tags($request->input('omangexpiry', '')));
                $passportExpiry = htmlspecialchars(strip_tags($request->input('passportexpiry', '')));
                $omangFront = htmlspecialchars(strip_tags($request->input('omangKyc', '')));
                $omangBack = htmlspecialchars(strip_tags($request->input('omangbackKyc', '')));
                $passport = htmlspecialchars(strip_tags($request->input('passportKyc', '')));
                $data = [
                    'omangExpiry' => $omangExpiry,
                    'passportExpiry' => $passportExpiry,
                    'compliance' => 0,
                    'status' => "Unchecked",
                    'passportIssuingCountry' => $countryId,
                    'reason' => null,
                    'remark' => null,
                ];
                if ($omangFront != NULL) {
                    $fileName = explode('/', $omangFront);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage::disk('s3')->move($omangFront, $filePath);
                    $data['omang'] = $filePath;
                }
                if ($omangBack != NULL) {
                    $fileName = explode('/', $omangBack);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang_back' . '/' . $name;
                    Storage::disk('s3')->move($omangBack, $filePath);
                    $data['omangBack'] = $filePath;
                }
                if ($passport != NULL) {
                    $fileName = explode('/', $passport);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/passport' . '/' . $name;
                    Storage::disk('s3')->move($passport, $filePath);
                    $data['passport'] = $filePath;
                }

                $customer = Customer::where('id',$user_id)->first();

                if (!isset($customer) && !isset($customer->mati_identity)) {
                    $customerKYCToken = htmlspecialchars(strip_tags($request->get('customerKYCToken', NULL)));
                    if ($customerKYCToken != NULL) {
                        $data['customer_id'] = $user_id;
                        $updateKyc = $this->customer_kyc_interface->update_customer_kyc_by_token($customerKYCToken, $data);
                    } else {
                        $updateKyc = $this->customer_kyc_interface->update_customer_kyc_by_id($user_id, $data);
                    }
                }
            }
            //Latestid for Policy Number
            $latest = Policy::orderBy('id', 'DESC')->first(array('policyNumber', 'id'));
            if ($latest == null) {
                $latest = collect();
                $latest->policyNumber = 0;
            }

            $product = Product::where('id', $request->get('product'))->first(array('id', 'has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
            $policy = new Policy();
            $policy->customer_id = $user_id;
            $policy->note = $request->get('note');


            if(isset($request->is_bundled)){
                if ($request->is_bundled == 1) {
                   $is_bundled = 1;

            $policy->is_bundled = $is_bundled;
            if (isset($request->bundled_discount_precent)) {
            $policy->bundled_discount_precent = $request->bundled_discount_precent;
            }

            if (isset($request->bundled_discount)) {
            $policy->bundled_discount = $request->bundled_discount;
            }

           if((isset($request->product_id_mc) && $request->product_id_mc == 3) && $request->frequency_mc == 1){
                   $policy->first_premium =  str_replace(',', '', $request->first_month_premium);
                   $policy->first_premium_wvat =  str_replace(',', '', $request->first_month_premium);
                }
            }
        }
            $policy->leadSource = isset($request->leadSource) && $request->leadSource != null ? $request->leadSource : 'LiveQuote';

            if ($request->frequency != 1) {
                $request->billing_day = $policy->billing_day = date('d');
            }

            if(isset($request->billing_date) && $request->billing_date != null && $request->get('product') != 3){

                $request->billing_day = null;
            }

            if ($request->billing_day != null) {
                $policy->billing_day = htmlspecialchars(strip_tags($request->billing_day));
                $policy->billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
                $policy->ori_billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
            } else {
                if($request->get('product') == 3) {


                    if($request->billing_date !='')
                    {
                        $policy->BillingStart = $request->get('BillingStart') ? $request->get('BillingStart') : '';
                        $policy->billing_day = date("d", strtotime(str_replace('/', '-', $request->get('billing_date'))));
                        $policy->billingStartDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->get('billing_date'))));
                        $policy->ori_billingStartDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->get('billing_date'))));
                    }
                    else
                    {

                        $current_timestamp = Carbon::now()->timestamp;
                        $policy->billing_day = date("d", $current_timestamp);
                        $policy->billingStartDate = $this->setDate($policy->billing_day);
                        $policy->ori_billingStartDate = $this->setDate($policy->billing_day);
                    }


                }else{
                        $policy->BillingStart = $request->get('BillingStart') ? $request->get('BillingStart') : '';
                        $policy->billingStartDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->get('billing_date'))));
                        $policy->ori_billingStartDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->get('billing_date'))));

                }

            }

            if($request->get('product') != 3) {
                if(!empty($request->get('BillingStart'))){
                    if($request->get('BillingStart')=='Later'){
                        $policy->isVirtualBox = 1;
                    }else{
                        $policy->isVirtualBox = null;
                    }
                }
            }


            $policy->product_id = $request->get('product');
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
            if (($product->premium_type_id == 11) && ($request->product != 3)) {

                $product_plan = Productplan::where('id', $request->get('plan_id'))->first(array('sum_assured', 'premium'));
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->get('plan_id');

             if(isset($request->is_bundled) && $request->is_bundled == 1){

                $policy->premium = $request->final_premium;
                $policy->sum_assured = htmlspecialchars(strip_tags($request->get('sum_insured')));
                if((isset($request->product_id_mc) && $request->product_id_mc == 3) && $request->frequency_mc == 1){

                 $policy->billing_day = $request->paydate_label_month_premium;
                 $policy->billingStartDate =  $this->setDate($policy->billing_day);
                 $policy->ori_billingStartDate =  $this->setDate($policy->billing_day);
               }

             }else{
                $policy->premium = $premium;
                 $policy->sum_assured = $product_plan->sum_assured;
             }


                $policy->premium_freq = 1; //$request->frequency;
                $policy->vat = $product_plan->premium * ($regionVat / 100);
                $policy->vat_percent = $regionVat;
                $policy->agent_id = htmlspecialchars(strip_tags($request->agentCode));
                $policy->storeID = htmlspecialchars(strip_tags($request->store_id));
            } else {
                $f = htmlspecialchars(strip_tags($request->frequency));
                //                $premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
                //                $policy->premium = $request->get('premium');
                //                $policy->vat = $request->get('premium') * ($regionVat / 100);
                $policy->premium_freq = $f;
                $policy->vat_percent = $regionVat;
                $policy->sum_assured = htmlspecialchars(strip_tags($request->get('sum_insured')));

                $plan = Productplan::where('product_id', htmlspecialchars(strip_tags($request->get('product'))))->first(array('id'));
                $policy->plan_id = $plan->id;
            }

            //Check policy id in archived policies
            $a_policy = ArchivedPolicies::where('id', $latest->id + 1)->exists();
            if ($a_policy == true)
                $addCount = $latest->id + 2;
            else
                $addCount = $latest->id + 1;

            $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad(($addCount), 6, '0', STR_PAD_LEFT);
            $policy->status = 0;
            $policy->has_vehicle = $product->has_vehicle;
            $policy->has_member = $product->has_member;
            $policy->preinspection = $product->preinspection;
            $policy->is_motor_items = $product->is_motor_items;
            $policy->limit = $product->limit;
            $policy->kyc_customer = $product->kyc_customer;
            $policy->kyc_recipient = $product->kyc_recipient;

            if ($product->id == 3 && $request->is_bundled != 1) {
                if ($request->frequency != '1') {
                    $policy->first_premium = htmlspecialchars(strip_tags($request->premium));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags($request->premium_label_vat));
                } else {
                    $policy->first_premium = htmlspecialchars(strip_tags($request->leftout_premium));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags($request->leftout_premium_wvat));
                }
            }elseif($product->id == 3 && $request->is_bundled == 1){

                if ($request->frequency != '1') {
                    $policy->first_premium = htmlspecialchars(strip_tags(round($request->final_premium,2)));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags(round($request->premium_label_vat * $request->final_premium / $request->premium,2)));
                } else {
                    $policy->first_premium = htmlspecialchars(strip_tags(round($request->leftout_premium * $request->final_premium / $request->premium,2)));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags(round($request->leftout_premium_wvat * $request->final_premium / $request->premium,2)));
                }


            }
            //$policy->billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
            $addDays = 0;
            if ($product->has_activation_code) {
                $activationData = new \Illuminate\Http\Request();
                $activationData->setMethod('POST');
                $activationData->vendor = 5;
                $activationData->branch = 8;
                $activationData->rack_no = 0;
                $activationData->trial_periods = 30;
                $activationData->trial_coverage = 100000;
                $activationData->city = 'Gaborone';
                $activationData->state = 'Gaborone';
                $activationData->country = 'Botswana';
                $activationData->product = $request->product;
                $activationData->plan = $request->get('plan_id');
                $activationData->cellphone = $cellphone;
                $activationData->id_type = $request->get('omang') != "" ? "omang" : "passport";
                $activationData->id_number = $activationData->id_type == "omang" ? $request->get('omang') : $request->get('passport');
                $activationData->product_type_id = 11;
                $activationData->status = 0;
                $activationCode = $this->generateActivationCode($activationData);

                $activation = Activation::where('activation_code', $activationCode->getData()->activationCode)->first();
                $activation->status = 1;
                $activation->save();

                $policy->activation_code = htmlspecialchars(strip_tags($activationCode->getData()->activationCode));
                $policy->serial_code = $activation->serial_code;
                $policy->is_sys_act_generated = 1;
                $addDays = $activation->trial_periods;
            }

            $policy->trial_period = Carbon::now()->addDays($addDays)->format('Y-m-d');

            if ($request->agentCode != null || $request->agent_id != null) {
                $policy->agent_id = $request->agentCode == null ? $request->agent_id : $request->agentCode;
            }
            if ($request->agentCode != null || $request->agent_id != null) {
                if($request->agentCode != null){
                    $agent = \AlphaDirect\User::where('id',$request->agentCode)->where('agency_id','!=',null)->first(['agency_id']);

                }else if($request->agent_id != null){
                    $agent = \AlphaDirect\User::where('id',$request->agent_id)->where('agency_id','!=',null)->first(['agency_id']);

                }
                if($agent){
                     $policy->agency_id = $agent->agency_id;
                }
            }

            if ($request->store_id != null || $request->stores) {
                $policy->storeID = $request->store_id == null ? $request->stores : $request->store_id;
            }

            // Blocked Customer
            // if ($request->get('phone')!= null) {
            //     $blockedCustomer = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'desc')->first();
            //     if ($blockedCustomer->is_blocked == 1) {
            //         $policy->customer_blocked = 1;
            //     }
            // }

            $policySaved = $policy->save();

            event(new \AlphaDirect\Events\policyLifecycle($policy->id,"Create"));

            $agent_id = $request->agentCode == null ? $request->agent_id : $request->agentCode;
            $store_id = $request->store_id == null ? $request->stores : $request->store_id;

            if (($store_id != null || $store_id != '') && ($agent_id != null || $agent_id != '')) {
                $this->addAgentActivity($store_id, $agent_id);
            }
            if ($policySaved == true && $policy->agentCode != null) {
                $data = [
                    'id' => $policy->id,
                    'agent_id' => $policy->agentCode,
                    'status' => $policy->status,
                ];
                event(new CommissionPolicyEvent($data));
            }

            // if(env('APP_STATUS') == 'Development') {
                if ($product->has_activation_code != 0 && $store_id != null) {
                    $check = \Modules\Inventory\Entities\StoresInventory::where('store_id','=',$request->get('stores'))->exists();
                    if($check == true){
                        //If stock available it will further try to deduct
                        $stock = \Modules\Inventory\Entities\StoresInventory::where('store_id','=',$request->get('stores'))->where('product_id','=',$request->get('product'))->where('plan_id','=',$request->get('plan_id'))->first(array('counter','id'));
                        if ($policySaved == true && $stock->counter != '0') {
                            $quantity = 1;
                            $user = User::where('id',$request->get('agentCode'))->firstOr(function () {
                                return User::where('id',1)->first();
                            });
                            event(new \Modules\Inventory\Events\DeductStockStore($stock->id,$user,$quantity));
                        }
                    }
                }
            // }

            //calling event for decrement couneter for specific product, plan and store
            if ($policySaved == true) {
                $data = [
                    'store_id' => $request->store_id,
                    'product_id' => $request->product_id,
                    'plan_id' => $request->planId,
                ];
                event(new DecrementCounter($data));
            }

            $importStatus = '';

            if ($policySaved && $request->generatedQuoteCode != null && $request->get('product') == 3) {
                $updateQuote = $this->updateQuoteStatus($request->generatedQuoteCode);
                if ($updateQuote == true) {
                    $data = MotorComprehensiveQuotes::where('quoteNumber', htmlspecialchars(strip_tags($request->generatedQuoteCode)))->first();
                    $policy->premium_freq = $request->frequency;

                    $policy->vat = $policy->premium * ($regionVat / 100);
                    $policy->quoteNumber = $request->generatedQuoteCode;
                    $policy->sum_assured = $data->estimatedValue;

                    if ($data->is_imported == "Yes")
                        $importStatus = 1;
                    else
                        $importStatus = 0;

             if(isset($request->is_bundled) && $request->is_bundled == 1){

                $policy->premium = $request->final_premium;

                if((isset($request->product_id_mc) && $request->product_id_mc == 3) && $request->frequency_mc == 1){
                   $policy->first_premium = str_replace(',', '', $request->first_month_premium);

                   $policy->first_premium_wvat = str_replace(',', '', $request->first_month_premium);
                }

             }else{
                   if ($request->frequency == '1' ) {
                        $policy->premium = $data->premiumMonthly;
                    } elseif ($request->frequency == '2') {
                        $policy->premium = $data->premium3Inst;
                    } elseif ($request->frequency == '3') {
                        $policy->premium = $data->premiumAnnually;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Payment frequency not found'], 401);
                    }
                }

                    $saved = $policy->save();
                }
            }

            if ($product->has_member) {
                if ($request->get('beneficiaries') != null) {
                    foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                        if ($beneficiary['beneficiaryRelation'] != null) {
                            $b = new PolicyBeneficiary();
                            $b->policy_id = $policy->id;
                            $b->relation = isset($beneficiary['beneficiaryRelation']) && $beneficiary['beneficiaryRelation'] != null ? $beneficiary['beneficiaryRelation'] : "";
                            $b->omang = isset($beneficiary['beneficiaryOmang']) && $beneficiary['beneficiaryOmang'] != null ? $beneficiary['beneficiaryOmang'] : "";
                            $b->passport = isset($beneficiary['beneficiaryPassport']) && $beneficiary['beneficiaryPassport'] != null ? $beneficiary['beneficiaryPassport'] : "";
                            $b->first_name = isset($beneficiary['beneficiaryFName']) && $beneficiary['beneficiaryFName'] != null ? $beneficiary['beneficiaryFName'] : "";
                            $b->last_name = isset($beneficiary['beneficiaryLName']) && $beneficiary['beneficiaryLName'] != null ? $beneficiary['beneficiaryLName'] : "";
                            $b->dob = isset($beneficiary['beneficiaryDOB']) && ($beneficiary['beneficiaryDOB'] != "") ? date('Y-m-d', strtotime(str_replace('/', '-', $beneficiary['beneficiaryDOB']))) : "";
                            $b->gender = isset($beneficiary['beneficiaryGender']) && $beneficiary['beneficiaryGender'] != null ? $beneficiary['beneficiaryGender'] : "";
                            $b->payment = isset($beneficiary['beneficiaryPayment']) && $beneficiary['beneficiaryPayment'] != null ? $beneficiary['beneficiaryPayment'] : "";
                            $b->save();
                        }
                    }
                }
            }

            if ($request->get('product') == 4 ) {
                if($request->get('maritalstatus') == 2){
                    $b = new PolicyBeneficiary();
                    $b->policy_id = $policy->id;
                    $b->relation = 'Spouse';
                    $b->first_name = isset($request->legalFName) ? $request->legalFName : "";
                    $b->middle_name = isset($request->legalMName) ? $request->legalMName : "";
                    $b->last_name = isset($request->legalLName) ? $request->legalLName : "";
                    $b->cellphone = isset($request->legalPhone) ? $request->legalPhone : "";
                    $b->email = isset($request->legalEmail) ? $request->legalEmail : "";
                    $b->passport = isset($request->legalPassport) ? $request->legalPassport : "";
                    $b->omang = isset($request->legalOmang) ? $request->legalOmang : "";
                    $b->gender = isset($request->legalGender) ? $request->legalGender : "";
                    if(($request->legalDOB != null) || ($request->legalDOB != '') ){
                        $b->dob = Carbon::createFromFormat('d/m/Y', $request->legalDOB)->format('Y-m-d');
                    }
                    $b->legalOmangExpiry = isset($request->omangExpiry) ? $request->omangExpiry : "";
                    $b->legalPassportExpiry = isset($request->passportExpiry) ? $request->passportExpiry : "";
                    $b->save();
                }
            }

//            if($request->Payment_method == "orangeMoney"){
//                $request->Payment_method = "Orange USSD";
//            }
            $banking = new CustomerBanking();
            $banking->customer_id = $user_id;
            $banking->policy_id = $policy->id;
            $banking->billing = $request->get('Payment_method');
            $banking->billingCell = $request->get('phone');
            if ($request->get('Payment_method') == 'RealPay') {
                $banking->bankName = $request->get('bankName');
                $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('bankAccountType');
            }
            if ($request->get('Payment_method') == 'PayM8') {

                $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('bankAccountType');
            }
            if ($request->frequency == 1) {
                if (htmlspecialchars(strip_tags($request->get('product'))) == 3) {
                    $banking->billingStartDate = $this->setDate($request->billing_day);
                }else{
//                    if(isset($request->billing_date) && $request->billing_date != null && $request->get('product') != 3){
//
//                    }else{
//                        $banking->billingStartDate = Carbon::now()->format('Y-m-d');
//                    }

                    $banking->billingStartDate = $policy->billingStartDate;
                }
            }elseif ($request->frequency == 2) {
                $banking->billingStartDate = Carbon::now()->addMonths(1)->format('Y-m-d');
            }else {
                $banking->billingStartDate = Carbon::now()->addYear()->format('Y-m-d');
            }
            $banking->billing_day = $request->billing_day;
            $saved = $banking->save();

            if ($product->has_vehicle) {
                $vehicle = new Vehicle();
                $vehicle->customer_id = $user_id;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = $request->vehiclePlate;
                if (htmlspecialchars(strip_tags($request->get('product'))) == 3) {
                    $vehicleData = MotorComprehensiveQuotes::where('quoteNumber', $request->generatedQuoteCode)->first(array('make', 'model', 'manufacturingYear'));
                    if ($vehicleData != null) {
                        $vehicle->make = $vehicleData->make;
                        $vehicle->model = $vehicleData->model;
                        $vehicle->year = $vehicleData->manufacturingYear;
                    }
                } else {
                    $vehicle->make = $request->make;
                    $vehicle->model = $request->model;
                    $vehicle->year = $request->year;
                }
                $vehicle->purpose = $request->purpose;
                $vehicle->is_private = $request->purpose;
                $vehicle->vinnumber = $request->vinnumber;
                $vehicle->engineNo = $request->enginenumber;
                $vehicle->financial_interest = $request->financial_interest;
                $vehicle->financial_interest_other = $request->other_finance;
                $vehicle->claim_count = $request->claim_count;
                $vehicle->is_imported = $importStatus;
                $vehicle->mileage = 'LO';
                $vehicle->condition = 'EX';
                $vehicle->estimated_value = $request->estimated_value;

                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->front = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Front image is invalid'], 401);
                    }
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->back = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Back image is invalid'], 401);
                    }
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->right = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Right image is invalid'], 401);
                    }
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');

                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->left = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Left image is invalid'], 401);
                    }
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    //$result = $this->isImageValid($file);
                    //                        if ($request->hasFile('vehicleRegistration')) {
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->vehicleRegistration = $filePath;
                    //                        } else {
                    //                            return response()->json(['success' => false, 'Message' => 'Vehicle registration image is invalid'], 401);
                    //                        }
                }
                $saved = $vehicle->save();
            }

            if ($product->type == 'Cellphone') {
                if ($request->get('devices') != null) {

                    $sumAssuredCellphone = 0;
                    foreach ($request->get('devices') as $key => $device) {
                        $data = array();
                        $policy_id = $policy->id;
                        $customer_id = $user_id;
                        $device_type = htmlspecialchars(strip_tags($device['device_type']));
                        $imei = htmlspecialchars(strip_tags($device['imei']));
                        $phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                        $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                        if ($cell_phone_make == 'Other')
                            $cell_phone_make = htmlspecialchars(strip_tags($device['make_other']));
                        else {
                            $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                            $cell_phone_make = $make->name;
                        }
                        $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                        if ($cell_phone_model == 'Other')
                            $cell_phone_model = htmlspecialchars(strip_tags($device['model_other']));
                        else {
                            $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                            $cell_phone_model = $model->name;
                        }
                        $data = [
                            'policy_id' => $policy_id,
                            'customer_id' => $customer_id,
                            'device_type' => $device_type,
                            'imei' => $imei,
                            'phone_value' => $phone_value,
                            'cell_phone_make' => $cell_phone_make,
                            'cell_phone_model' => $cell_phone_model,
                        ];

                        if ($device['cell_phone_front'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_front']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/front' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_front'], $filePath);
                            $data['cell_phone_front'] = $filePath;
                        }

                        if ($device['cell_phone_back'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_back']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/back' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_back'], $filePath);
                            $data['cell_phone_back'] = $filePath;
                        }

                        if ($device['cell_phone_left'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_left']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/left' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_left'], $filePath);
                            $data['cell_phone_left'] = $filePath;
                        }
                        if ($device['cell_phone_right'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_right']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/right' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_right'], $filePath);
                            $data['cell_phone_right'] = $filePath;
                        }
                        if ($device['cell_phone_top'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_top']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/top' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_top'], $filePath);
                            $data['cell_phone_top'] = $filePath;
                        }
                        if ($device['cell_phone_bottom'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_bottom']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/bottom' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_bottom'], $filePath);
                            $data['cell_phone_bottom'] = $filePath;
                        }

                        $policyCellPhone = $this->policy_cell_phone_interface->add_new_policy_cell_phone($data);
                        $sumAssuredCellphone = $sumAssuredCellphone + $phone_value;
                    }
                    $p = Policy::where('id', $policy->id)->first();
                    if ((isset($request->is_bundled) && $request->is_bundled == 1) && ((isset($request->product_id_mc) && $request->product_id_mc == 3) || $request->get('product') == 3 )) {

                          $p->sum_assured = htmlspecialchars(strip_tags($request->get('sum_insured')));
                    }else{
                        $p->sum_assured = $sumAssuredCellphone;
                    }
                    $p->save();
                }
            }

            // policy document is generated only for product id = 3 hence its is excluded here
            if ($request->get('product') != 3) {
                //sms
                $sms = new SmsMessaging();
                $sms->sendSmsPolicyCreate(28, $policy->policyNumber, $fname, $lname, $cellphone);
                //mail
                // if ($email != null) {
                //     $data = new \stdClass();
                //     $data->hook = 'create_policy';
                //     $data->customer_id = $user_id;
                //     $data->policy_id = $policy->id;
                //     $data->user_id = null;
                //     $data->attachment = null;

                //     $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                //     //Send Mail on Policy Create
                //     $markdown = new MailTemplate($data);
                //     $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	            //     event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html));
                // }
            }


            if ($request->get('product') == 4) {
                $employerdetails = CustomerProfile::where('customer_id', $user_id)->first();
                 if ($product->type == 'Legal') {
                    $employerdetails->e_name          = $request->e_name;
                    $employerdetails->emp_no         = $request->emp_no;
                    $employerdetails->emp_phone        = $request->emp_phone;
                    $employerdetails->salary_pay_date = $request->salary_pay_date;
                    $employerdetails->save();
                 }
            }




            if (isset($request->is_bundled)) {
            if ($request->is_bundled == 1) {
                if (isset($request->product_id_p) ) {
                    if ($request->product_id_p == 1 ) {
                        $policy_bundled = new PolicyBundled();
                        $policy_bundled->policy_id = $policy->id;
                        $policy_bundled->product_id = $request->product_id_p;
                        $policy_bundled->plan_name = $request->p1_m_a_d_i;
                        $policy_bundled->premium = $request->product_id_1_premium;
                        $policy_bundled->subtotal = $request->subtotal5;
                        $policy_bundled->final_premium = $request->final_premium;
                        $policy_bundled->save();

                        if ($request->get('beneficiaries') != null) {
                                foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                                    if ($beneficiary['beneficiaryRelation'] != null) {
                                        $b = new PolicyBeneficiary();
                                        $b->policy_id = $policy->id;
                                        $b->relation = isset($beneficiary['beneficiaryRelation']) && $beneficiary['beneficiaryRelation'] != null ? $beneficiary['beneficiaryRelation'] : "";
                                        $b->omang = isset($beneficiary['beneficiaryOmang']) && $beneficiary['beneficiaryOmang'] != null ? $beneficiary['beneficiaryOmang'] : "";
                                        $b->passport = isset($beneficiary['beneficiaryPassport']) && $beneficiary['beneficiaryPassport'] != null ? $beneficiary['beneficiaryPassport'] : "";
                                        $b->first_name = isset($beneficiary['beneficiaryFName']) && $beneficiary['beneficiaryFName'] != null ? $beneficiary['beneficiaryFName'] : "";
                                        $b->last_name = isset($beneficiary['beneficiaryLName']) && $beneficiary['beneficiaryLName'] != null ? $beneficiary['beneficiaryLName'] : "";
                                        $b->dob = isset($beneficiary['beneficiaryDOB']) && ($beneficiary['beneficiaryDOB'] != "") ? date('Y-m-d', strtotime(str_replace('/', '-', $beneficiary['beneficiaryDOB']))) : "";
                                        $b->gender = isset($beneficiary['beneficiaryGender']) && $beneficiary['beneficiaryGender'] != null ? $beneficiary['beneficiaryGender'] : "";
                                        $b->payment = isset($beneficiary['beneficiaryPayment']) && $beneficiary['beneficiaryPayment'] != null ? $beneficiary['beneficiaryPayment'] : "";
                                        $b->save();
                                    }
                                }
                        }
                    }
                }
               if (isset($request->product_id_tpci) ) {
                    if ($request->product_id_tpci == 2 ) {
                        $policy_bundled = new PolicyBundled();
                        $policy_bundled->policy_id = $policy->id;
                        $policy_bundled->product_id = $request->product_id_tpci;
                        $policy_bundled->plan_name = $request->t_p_c_i;
                        $policy_bundled->premium = $request->product_id_2_premium;
                        $policy_bundled->subtotal = $request->subtotal5;
                        $policy_bundled->final_premium = $request->final_premium;
                        $policy_bundled->save();
                    }
                }
            if (isset($request->product_id_mc)) {
                    if ($request->product_id_mc == 3 ) {
            $policy_bundled = new PolicyBundled();
            $policy_bundled->policy_id = $policy->id;
            $policy_bundled->product_id = $request->product_id_mc;
            $policy_bundled->plan_name = $request->motor_comprehensive;
            $policy_bundled->premium = $request->product_id_3_premium;
            $policy_bundled->frequency_mc = $request->frequency_mc;
             $policy_bundled->subtotal = $request->subtotal5;
            $policy_bundled->final_premium = $request->final_premium;
            $policy_bundled->save();
            $vehicle = new Vehicle();
                $vehicle->customer_id = $user_id;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = $request->vehiclePlate;

                    $vehicle->make = $request->make;
                    $vehicle->model = $request->model;
                    $vehicle->year = $request->year;

                $vehicle->purpose = $request->purpose;
                $vehicle->is_private = $request->purpose;
                $vehicle->vinnumber = $request->vinnumber;
                $vehicle->engineNo = $request->enginenumber;
                $vehicle->financial_interest = $request->financial_interest;
                $vehicle->financial_interest_other = $request->other_finance;
                $vehicle->claim_count = $request->claim_count;
                $vehicle->is_imported = $importStatus;
                $vehicle->mileage = 'LO';
                $vehicle->condition = 'EX';
                $vehicle->estimated_value = $request->estimated_value;

                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->front = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Front image is invalid'], 401);
                    }
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->back = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Back image is invalid'], 401);
                    }
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->right = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Right image is invalid'], 401);
                    }
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');

                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->left = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Left image is invalid'], 401);
                    }
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    //$result = $this->isImageValid($file);
                    //                        if ($request->hasFile('vehicleRegistration')) {
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->vehicleRegistration = $filePath;
                    //                        } else {
                    //                            return response()->json(['success' => false, 'Message' => 'Vehicle registration image is invalid'], 401);
                    //                        }
                }
                $saved = $vehicle->save();

            }
            }
             if (isset($request->product_id_li) ) {
                    if ($request->product_id_li == 4 ) {
            $policy_bundled = new PolicyBundled();
            $policy_bundled->policy_id = $policy->id;
            $policy_bundled->product_id = $request->product_id_li;
            $policy_bundled->plan_name = $request->legal_insurance;
            $policy_bundled->premium = $request->product_id_6_premium;
             $policy_bundled->subtotal = $request->subtotal5;
            $policy_bundled->final_premium = $request->final_premium;
            $policy_bundled->save();


             $employerdetails = CustomerProfile::where('customer_id', $user_id)->first();


                    $employerdetails->e_name          = $request->e_name;
                    $employerdetails->emp_no         = $request->emp_no;
                    $employerdetails->emp_phone        = $request->emp_phone;
                    $employerdetails->salary_pay_date = $request->salary_pay_date;
                    $employerdetails->save();


            if($request->get('maritalstatus') == 2){
                    $b = new PolicyBeneficiary();
                    $b->policy_id = $policy->id;
                    $b->relation = 'Spouse';
                    $b->first_name = isset($request->legalFName) ? $request->legalFName : "";
                    $b->middle_name = isset($request->legalMName) ? $request->legalMName : "";
                    $b->last_name = isset($request->legalLName) ? $request->legalLName : "";
                    $b->cellphone = isset($request->legalPhone) ? $request->legalPhone : "";
                    $b->email = isset($request->legalEmail) ? $request->legalEmail : "";
                    $b->passport = isset($request->legalPassport) ? $request->legalPassport : "";
                    $b->omang = isset($request->legalOmang) ? $request->legalOmang : "";
                    $b->gender = isset($request->legalGender) ? $request->legalGender : "";
                    if(($request->legalDOB != null) || ($request->legalDOB != '') ){
                        $b->dob = Carbon::createFromFormat('d/m/Y', $request->legalDOB)->format('Y-m-d');
                    }
                    $b->save();
                }

            }
            }

             if (isset($request->product_id_cdi) ) {
                    if ($request->product_id_cdi == 5 ) {

            $policy_bundled = new PolicyBundled();
            $policy_bundled->policy_id = $policy->id;
            $policy_bundled->product_id = $request->product_id_cdi;
            $policy_bundled->plan_name = $request->c_d_insurance;
            $policy_bundled->premium = $request->product_id_5_premium;
             $policy_bundled->subtotal = $request->subtotal5;
            $policy_bundled->final_premium = $request->final_premium;
            $policy_bundled->save();

             if ($request->get('devices') != null) {

                    $sumAssuredCellphone = 0;
                    foreach ($request->get('devices') as $key => $device) {

                        $data = array();
                        $policy_id = $policy->id;
                        $customer_id = $user_id;
                        $device_type = htmlspecialchars(strip_tags($device['device_type']));
                        $imei = htmlspecialchars(strip_tags($device['imei']));
                        $phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                        $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                        if ($cell_phone_make == 'Other')
                            $cell_phone_make = htmlspecialchars(strip_tags($device['make_other']));
                        else {
                            $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                            $cell_phone_make = $make->name;
                        }
                        $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                        if ($cell_phone_model == 'Other')
                            $cell_phone_model = htmlspecialchars(strip_tags($device['model_other']));
                        else {
                            $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                            $cell_phone_model = $model->name;
                        }
                        $data = [
                            'policy_id' => $policy_id,
                            'customer_id' => $customer_id,
                            'device_type' => $device_type,
                            'imei' => $imei,
                            'phone_value' => $phone_value,
                            'cell_phone_make' => $cell_phone_make,
                            'cell_phone_model' => $cell_phone_model,
                        ];

                        if ($device['cell_phone_front'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_front']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/front' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_front'], $filePath);
                            $data['cell_phone_front'] = $filePath;
                        }

                        if ($device['cell_phone_back'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_back']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/back' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_back'], $filePath);
                            $data['cell_phone_back'] = $filePath;
                        }

                        if ($device['cell_phone_left'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_left']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/left' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_left'], $filePath);
                            $data['cell_phone_left'] = $filePath;
                        }
                        if ($device['cell_phone_right'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_right']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/right' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_right'], $filePath);
                            $data['cell_phone_right'] = $filePath;
                        }
                        if ($device['cell_phone_top'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_top']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/top' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_top'], $filePath);
                            $data['cell_phone_top'] = $filePath;
                        }
                        if ($device['cell_phone_bottom'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_bottom']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/bottom' . '/' . $name;
                            //Storage::disk('s3')->put($filePath, $files);
                            Storage::disk('s3')->move($device['cell_phone_bottom'], $filePath);
                            $data['cell_phone_bottom'] = $filePath;
                        }

                        $policyCellPhone = $this->policy_cell_phone_interface->add_new_policy_cell_phone($data);

                    }


                }

            }
            }

            }
            }

            DB::commit();

            // documents
            // $d = new DocumentController();
            // $verificationDoc = $d->generateInformationDocument($policy->id);

            // if ($verificationDoc != null) {
            //     $policy->verification_doc = $verificationDoc;
            //     $saved = $policy->save();
            // }

            // if (htmlspecialchars(strip_tags($request->get('product'))) != 3 || $policy->is_bundled == 1) {
            //     $isGenerated = $d->generatePolicyDocument($policy->id);
            //     if ($isGenerated != null) {
            //         $sent = $d->sendPolicyDocument($policy->id,"Agent");
            //     }
            // }


            $customerConsent = new CustomerConsent();
            $customerConsent->policy_id = $policy->id;
            $customerConsent->customer_id = $policy->customer_id;
            $customerConsent->ip_address = $request->ip();
            $customerConsent->browser_name = $request->header('User-Agent');
            $customerConsent->is_consent_yes = $request->is_consent_yes;
            $customerConsent->is_consent_to_process_yes = $request->is_consent_to_process_yes;
            $customerConsent->save();

            if ($policy->save() && $policy->product_id == 3) {

                if(isset($policy->policyActivatedDate)) {
                    $expiry_date = Carbon::parse($policy->policyActivatedDate)->addYear()->subDays(1)->format('Y-m-d');
                } else {
                    $expiry_date = Carbon::parse($policy->created_at)->addYear()->subDays(1)->format('Y-m-d');
                }

                $status = 'Deactive';
                // if (isset($expiry_date)) {
                //     if ($expiry_date < Carbon::now()) {
                //         $status = 'Deactive';
                //     } else {
                //         $status = 'Active';
                //     }
                // }

                $policy_data = [
                    'premium'      => $policy->premium,
                    'premium_freq' => $policy->premium_freq
                ];

                $policyCon = new PolicyController();
                $premium = $policyCon->getMotorComprehensivePolicyPremium($policy_data);

                $data = [
                    'policy_id' => $policy->id,
                    'term_start_date' => ($policy->policyActivatedDate) ? $policy->policyActivatedDate : $policy->created_at,
                    'term_end_date' => $expiry_date,
                    // 'premium' => $policy->premium,
                    'premium' => $policy->premium,
                    'annual_premium' => isset($premium['annual']) ? round($premium['annual'],2) : null,
                    'vat' => $policy->vat,
                    'vat_percent' => $policy->vat_percent,
                    'renewed_by' => $request->agent_id,
                    'renewals_date' => $expiry_date,
                    'frequency' => $policy->premium_freq,
                    'first_premium' => $policy->first_premium,
                    'billing_start_date' => $policy->billingStartDate,
                    'policy_documents' => NULL,
                    'policyActivatedDate' => $policy->policyActivatedDate,
                    'payment_method' => $request->Payment_method,
                    'payment_reference' => $policy->id,
                    'trans_type' => 'NEW BUSINESS',
                    'status' => $status,
                    'created_at' => Carbon::now()->format('Y-m-d'),

                ];
                $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
            }
            if($policy->save() && env("APP_STATUS") == 'Production' ){
                $llmapi = new LlmApiCrontroller();
                $llmapi->RegisterCustomer($policy->customer_id,$policy->agent_id);
                $llmapi->SalePolicy($policy->policyNumber);
            }

            DB::commit();

            switch ($request->Payment_method) {
                case 'VCS':
                    $vcs = new PaymentController;
                    //For Motor Comprehensive
                    if ($request->get('product') == 3 ){
                        return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, $policy->leadSource);
                        break;
                    } else {
                        return $vcs->handlePaymentForActivationCodeGenerated($policy->policyNumber, 'LiveQuote');
                        break;
                    }
                case 'Flutterwave':
                    $rave = new FlutterwaveController;
                    return $rave->handlePayment($policy->policyNumber, $product_plan->slug, $product_plan->flutter_plan_id);
                    break;
                case 'DPO':
                    if(isset($request->pay_email) && $request->pay_email != null){
                        $payemail               = new PaymentEmail();
                        $payemail->policyNumber =  $policy->policyNumber;
                        $payemail->pay_email =  $request->pay_email;
                        $payemail->save();
                        }
                    // return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber, 'product_id' => $policy->product_id], 200);
                        $dpo = new DpoPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        if($policy->product_id == 3){
                          $dpoReturn = $dpo->findPolicyForOnlinePaymentMotorComp($request);
                        }else{
                          $dpoReturn = $dpo->findPolicyForOnlinePayment($request);
                        }

                        return $dpoReturn;
                    break;
                 case 'N-Genius':

                        $Ngenius = new NgeniusPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource = $policy->leadSource;
                        $NgeniusReturn = $Ngenius->NgeniusPayment($request);
                        return $NgeniusReturn;
                    break;
                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                    break;
                case 'orangeMoney':
                    $orange = new OrangeMoneyController();
                    $log = $orange->addSchedule($policy->policyNumber);
                    return response()->json(['status' => '200', 'message' => 'Policy Created Successfully! Please complete the payment through Orange Money USSD', 'PolicyNumber' => $policy->policyNumber], 200);
                    break;
                case 'RealPay':
                    if(isset($request->instant_activate_policy)) {
                        if(isset($request->pay_email) && $request->pay_email != null){
                            $payemail               = new PaymentEmail();
                            $payemail->policyNumber =  $policy->policyNumber;
                            $payemail->pay_email =  $request->pay_email;
                            $payemail->save();
                        }
                        $dpo = new DpoPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->requestType = 'instantActivatePolicy';
                        $pay = $dpo->findPolicyForOnlinePayment($request);
                        return $pay;
                        break;
                    } else {
                        $addEvent = $this->realpayPayment($policy);
                        // return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                        break;
                    }
                case 'PayM8':
                    $paym8 = new PayM8Controller();
                    $addEvent = $paym8->createAdHocPayment($policy->id);
                    return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                    break;
                case 'cash':
                    return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber], 200);
                    // case 'Cash':
                    //     return $this->storeCashPayment($policy);
                    break;
                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->all());
            }

            return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber], 200);
        } catch (\Throwable $ex) {
            // Without the leading backslash this catch resolves to
            // AlphaDirect\Http\Controllers\FrontendPay\Exception (no such
            // class) and never matches anything — so \ErrorException and
            // friends bubble to Laravel's framework, which with APP_DEBUG=false
            // returns the unhelpful {"message":"Server Error"} body. Use
            // \Throwable so any error surfaces with its real message.
            DB::rollback();
            return response()->json(['success' => 0, 'message' => $ex->getMessage()], 401);
        }
    }
    public function storepaymPayment(Request $request)
    {

        if(isset($request->policyNumber)){
            $policy = Policy::where('policyNumber', $request->policyNumber)
            ->first(array('id', 'customer_id', 'product_id','plan_id','quoteNumber','annual_premium','premium','first_premium',
            'first_premium_wvat','premium_freq','vat','vat_percent','policyNumber','leadSource','billing_day','billingStartDate'));

            if (isset($policy) && $policy->product_id == 3) {
                if($policy){
                    $policy->leadSource = $request->leadSource;
                    //$policy->billingStartDate = $this->setDate($request->billing_day);
                    $date = Carbon::createFromFormat('d/m/Y', $request->billing_date);
                    $formattedBillingDate = $date->format('Y-m-d');
                    $policy->billingStartDate =  $formattedBillingDate;
                    ///$policy->billing_day = $request->billing_day;
                    $policy->billing_day = date("d", strtotime(str_replace('/', '-', $request->get('billing_date'))));
                    $policy->save();
                    $customer = Customer::where('id', $policy->customer_id)->first(array('id', 'firstName', 'lastName','email','cellphone'));
                    if(isset($customer)){
                        if(isset($policy->premium_freq)){
                            $checkCustomerBanking = CustomerBanking::where('policy_id', $policy->id)->exists();
                            if($checkCustomerBanking){
                                $banking = CustomerBanking::where('policy_id', $policy->id)->first();
                            }else{
                                $banking = new CustomerBanking();
                            }
                            // Outgoing payment method — captured before overwrite for the audit log.
                            $oldPaymentMethod = ($checkCustomerBanking && $banking->billing != null) ? $banking->billing : null;
                            $banking->customer_id = $policy->customer_id;
                            $banking->policy_id = $policy->id;
                            $banking->billing = $request->Payment_method;
                            $banking->billingCell = $customer->cellphone;

                            $banking->branchCode = $request->branchCode;
                            $banking->accountNumber = $request->accountNumber;
                            $banking->accountType = $request->bankAccountType;
                            if ($policy->premium_freq == 1) {
                                if (htmlspecialchars(strip_tags($request->product)) == 3) {
                                    //$banking->billingStartDate = $this->setDate($request->billing_day);
                                    $banking->billingStartDate  = date("d", strtotime(str_replace('/', '-', $request->get('billing_date'))));
                                }else{
                                    $banking->billingStartDate = $policy->billingStartDate;
                                }
                            }elseif ($policy->premium_freq == 2) {
                                $banking->billingStartDate = Carbon::now()->addMonths(1)->format('Y-m-d');
                            }else {
                                $banking->billingStartDate = Carbon::now()->addYear()->format('Y-m-d');
                            }
                            $banking->billing_day = $request->billing_day;
                            $banking->save();

                            // Payment-conversion audit (parity with graphiteBWV8) — motor-comp
                            // PayM8 reprocess from start now sends a verified agent_id.
                            $updateCOntract = new \AlphaDirect\Models\UpdateContract();
                            $updateCOntract->policyNumber       = $policy->policyNumber;
                            $updateCOntract->new_payment_method = "PayM8_start_MotorComp";
                            $updateCOntract->old_payment_method = $oldPaymentMethod;
                            $updateCOntract->agent              = (isset($request->agent_id) && $request->agent_id != null) ? $request->agent_id : null;
                            $updateCOntract->save();

                                $policyController = new PolicyController();
                                $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
                                $paym8 = new PayM8Controller();
                                $addEvent = $paym8->createAdHocPayment($policy->id);
                                //return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);



                            return response()->json(['title' => 'Payment is successful.', 'description' => 'Payment is successful.', 'status' => "200"], 200);
                        } else {
                            return response()->json(['title' => 'Policy not allowed for payment.', 'description' => 'Policy not allowed for payment. Frequency for this policy does not exists.', 'status' => "399"], 200);
                        }
                    } else {
                        return response()->json(['title' => 'Customer for this policy does not exists.', 'description' => 'Customer for this policy does not exists.', 'status' => "399"], 200);
                    }
                } else {
                    return response()->json(['title' => 'Policy number does not exists.', 'description' => 'Policy number does not exists.', 'status' => "399"], 200);
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Only motor Comprehensive policies are allowed to process redopayment', 'type' => 'error'], 401);
            }

        } else {
            return response()->json(['title' => 'Policy number does not exists.', 'description' => 'Policy number does not exists.', 'status' => "399"], 200);
        }
    }
    public function storeRealPayPayment(Request $request)
    {

        if(isset($request->policyNumber)){
            $policy = Policy::where('policyNumber', $request->policyNumber)
            ->first(array('id', 'customer_id', 'product_id','plan_id','quoteNumber','annual_premium','premium','first_premium',
            'first_premium_wvat','premium_freq','vat','vat_percent','policyNumber','leadSource','billing_day','billingStartDate'));

            if (isset($policy) && $policy->product_id == 3) {
                if($policy){
                    $policy->leadSource = $request->leadSource;
                    //$policy->billingStartDate = $this->setDate($request->billing_day);
                    $date = Carbon::createFromFormat('d/m/Y', $request->billing_date);
                    $formattedBillingDate = $date->format('Y-m-d');
                    $policy->billingStartDate =  $formattedBillingDate;
                    ///$policy->billing_day = $request->billing_day;
                    $policy->billing_day = date("d", strtotime(str_replace('/', '-', $request->get('billing_date'))));
                    $policy->save();
                    $customer = Customer::where('id', $policy->customer_id)->first(array('id', 'firstName', 'lastName','email','cellphone'));
                    if(isset($customer)){
                        if(isset($policy->premium_freq)){
                            $checkCustomerBanking = CustomerBanking::where('policy_id', $policy->id)->exists();
                            if($checkCustomerBanking){
                                $banking = CustomerBanking::where('policy_id', $policy->id)->first();
                            }else{
                                $banking = new CustomerBanking();
                            }
                            // Outgoing payment method — captured before overwrite for the audit log.
                            $oldPaymentMethod = ($checkCustomerBanking && $banking->billing != null) ? $banking->billing : null;
                            $banking->customer_id = $policy->customer_id;
                            $banking->policy_id = $policy->id;
                            $banking->billing = $request->Payment_method;
                            $banking->billingCell = $customer->cellphone;
                            $banking->bankName = $request->bankName;
                            $banking->branchCode = $request->branchCode;
                            $banking->accountNumber = $request->accountNumber;
                            $banking->accountType = $request->bankAccountType;
                            if ($policy->premium_freq == 1) {
                                if (htmlspecialchars(strip_tags($request->product)) == 3) {
                                    //$banking->billingStartDate = $this->setDate($request->billing_day);
                                    $banking->billingStartDate  = date("d", strtotime(str_replace('/', '-', $request->get('billing_date'))));
                                }else{
                                    $banking->billingStartDate = $policy->billingStartDate;
                                }
                            }elseif ($policy->premium_freq == 2) {
                                $banking->billingStartDate = Carbon::now()->addMonths(1)->format('Y-m-d');
                            }else {
                                $banking->billingStartDate = Carbon::now()->addYear()->format('Y-m-d');
                            }
                            $banking->billing_day = $request->billing_day;
                            $banking->save();

                            // Payment-conversion audit (parity with graphiteBWV8) — motor-comp
                            // RealPay reprocess from start now sends a verified agent_id.
                            $updateCOntract = new \AlphaDirect\Models\UpdateContract();
                            $updateCOntract->policyNumber       = $policy->policyNumber;
                            $updateCOntract->new_payment_method = "RealPay_start_MotorComp";
                            $updateCOntract->old_payment_method = $oldPaymentMethod;
                            $updateCOntract->agent              = (isset($request->agent_id) && $request->agent_id != null) ? $request->agent_id : null;
                            $updateCOntract->save();

                            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            // if ($policy->product_id == 3) {
                            //     $clientNumber = $realpay->cancelRealpayContract($policy->id);

                            //     // if($clientNumber == null){
                            //         $clientNumber = $realpay->cancelRealpayContractsForInstProduct($policy->id);
                            //     // }

                            // } else {
                            //     $clientNumber = $realpay->cancelRealpayContractsForInstProduct($policy->id);
                            // }

                            $policyController = new PolicyController();
                            $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                            $getContractStatus = $realpay->contractIsActive($policy->id,$policy->policyNumber);
                            if ($getContractStatus == false) {
                                $this->realpayPayment($policy);
                            }
                            return response()->json(['title' => 'Payment is successful.', 'description' => 'Payment is successful.', 'status' => "200"], 200);
                        } else {
                            return response()->json(['title' => 'Policy not allowed for payment.', 'description' => 'Policy not allowed for payment. Frequency for this policy does not exists.', 'status' => "399"], 200);
                        }
                    } else {
                        return response()->json(['title' => 'Customer for this policy does not exists.', 'description' => 'Customer for this policy does not exists.', 'status' => "399"], 200);
                    }
                } else {
                    return response()->json(['title' => 'Policy number does not exists.', 'description' => 'Policy number does not exists.', 'status' => "399"], 200);
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Only motor Comprehensive policies are allowed to process redopayment', 'type' => 'error'], 401);
            }

        } else {
            return response()->json(['title' => 'Policy number does not exists.', 'description' => 'Policy number does not exists.', 'status' => "399"], 200);
        }
    }

    public function storeCashPayment($policy)
    {
        return response()->json(['success' => 1, 'PolicyNumber' => $policy->policyNumber], 200);
    }

    public function updateQuoteStatus($qNumber)
    {
        try {
            $data = MotorComprehensiveQuotes::where('quoteNumber', $qNumber)->first();
            if ($data) {
                $data->status = 2;
                $data->save();
            }

            return true;
        } catch (Exception $e) {

            ;
        }
    }

    public function addAgentActivity($storeId, $agentId)
    {
        try {
            $userExist = User::where('id', '=', $agentId)->exists();
            $storeExist = Stores::where('id', '=', $storeId)->exists();

            if ($userExist == true && $storeExist == true) {
                $data = AgentLogins::where('agent_id', $agentId)
                    ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)'), [Carbon::parse('today')
                        ->format('Y-m-d'), Carbon::parse('today')
                        ->format('Y-m-d')])
                    ->orderBy('id', 'desc')->first();

                if ($data == null)
                    $data = new AgentLogins();

                $data->agent_id = $agentId;
                $data->store_id = $storeId;
                $data->last_activity = Carbon::now()->toDateTimeString();
                $data->save();

                return 1;
            } else {
                return 0;
            }
        } catch (\Exception $ex) {
            return 0;
        }
    }

    public function addAgentActivityAPI(Request $request)
    {
        try {
            $this->addAgentActivity($request->store, $request->agent);
        } catch (\Exception $ex) {
            return response()->json(['status' => '401', 'message' => $ex->getMessage()], 401);
        }
    }

    protected function fetchPremium($request)
    {
        try {
            $client2 = new \GuzzleHttp\Client();
            $response2 = $client2->request(
                'POST',
                env('RATINGS_URL') . 'getPremium',
                [
                    'form_params' => [
                        'garbage_data_slug' => 'MTIyMA=='
                    ]
                ]
            );
            $response2 = $response2->getBody()->getContents();
            $data = json_decode($response2);
            if ($data->value != null) {
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function realpayPayment($policy)
    {

        $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $addLog = $log->logEvent($policy->id, 1);
        $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
        $stringArr = \Opis\Closure\serialize($responseArr);

        $transaction = new Transaction();
        $transaction->policyNumber = $policy->policyNumber;
        $transaction->amount = $policy->premium;
        $transaction->customer_id = $policy->customer_id;
        $transaction->realPayTransaction_id = $policy->id;
        $transaction->referenceNumber = $policy->policyNumber;
        $transaction->status = "PENDING";
        $transaction->save();

        if ($addLog == true) {
            $payRequest = new RealpayPaymentRequest();
            $payRequest->policy_id = $policy->id;
            $payRequest->clientNumber = $policy->policyNumber;
            // $payRequest->first_premium = $policy->leftout_premium;
            $payRequest->first_premium = $policy->first_premium;
            $payRequest->premium = $policy->premium;
            $payRequest->billing_day = $policy->billing_day;
            $payRequest->billing_date = $policy->billingStartDate;
            $payRequest->first_premium_contract = null;
            $payRequest->contract = null;
            $payRequest->status = 0;
            $payRequest->response = $stringArr;
            $payRequest->frequency = $policy->premium_freq;
            $payRequest->clientCreated = 0;
            $payRequest->contractCreated = 0;
            $payRequest->save();

            return response()->json(['status' => '200', 'message' => 'Payment successful', 'PolicyNumber' => $policy->policyNumber], 200);
        } else {
            return response()->json(['status' => '401', 'message' => 'Payment log unsuccessful'], 401);
        }

        //return response()->json(['success'=>1],200);

        /* $RealPayController = new RealPayController();
        // $date = $request->billing_day;
        if($request->billing_day != null){
            $date = $request->billing_day;
        }else{
            $current_timestamp = Carbon::now()->timestamp;
            $date = date("d", $current_timestamp);
        }
        if ($date != null)
            $success = $RealPayController->leftOutPremiumPayment($policy, $request->leftout_premium, $date);

        if ($success == true)
            return $RealPayController->addClientRealPayAlphaFePay($policy, $premium, $customerExist, $existingPolicy, $request->leftout_premium);*/
    }

    public function logRealpayManually(Request $req)
    {
        try {
            if ($req->policyId == null)
                return null;

            $policy = Policy::where('id', $req->policyId)->first();

            if ($policy == null)
                return null;

            $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $addLog = $log->logEvent($policy->id, 1);
            $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
            $stringArr = \Opis\Closure\serialize($responseArr);

            $transaction = new Transaction();
            $transaction->policyNumber = $policy->policyNumber;
            $transaction->amount = $policy->premium;
            $transaction->customer_id = $policy->customer_id;
            $transaction->realPayTransaction_id = $policy->id;
            $transaction->referenceNumber = $policy->policyNumber;
            $transaction->status = "PENDING";
            $transaction->save();

            if ($addLog == true) {
                $payRequest = new RealpayPaymentRequest();
                $payRequest->policy_id = $policy->id;
                $payRequest->first_premium = $policy->first_premium;
                $payRequest->premium = $policy->premium;
                $payRequest->billing_day = $policy->billing_day;
                $payRequest->billing_date = $policy->billingStartDate;
                $payRequest->first_premium_contract = null;
                $payRequest->contract = null;
                $payRequest->status = 0;
                $payRequest->response = $stringArr;
                $payRequest->frequency = $policy->premium_freq;
                $payRequest->clientCreated = 0;
                $payRequest->contractCreated = 0;
                $payRequest->save();
            }
        } catch (Exception $e) {
            return null;
        }
    }

    public function getVehicleNoByPolicyNo($policy_no)
    {
        $policy = Policy::where('policyNumber', $policy_no)->first(array('id'));
        if ($policy != NULL) {
            $vehicle = Vehicle::where('policy_id', $policy->id)->first(array('vehiclePlate'));
            if ($vehicle != NULL)
                return $vehicle->vehiclePlate;
            else
                return 0;
        } else {
            return 0;
        }
    }

    /** functon for creating 6 digit OTP code */
    public function generateOTP()
    {
        $string = str_random(6);

        // generate a otp based on 6 digits +
        $otp = Helper::gen_ustring(100000, 999999);

        // shuffle the result
        $string = str_shuffle($otp);
    }

    /**Api function for sending an OTP code,   */
    public function requestOTP(Request $request)
    {
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        $customer = new Customer();
        $otp = new OTP();

        try {

            if ($cellphone != null) {
                $otp_response = $otp->OTPStore($cellphone); //save otp
                $data = $otp_response->getData();
                $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
                //$sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);

                return response()->json(['status' => 'OTP code sent successfully to ' . $cellphone], 200);
            } else {
                return response()->json('User does not exist', 401);
            }
        } catch (Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 401);
        }
    }

    public function resendOTP(Request $request)
    {
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        $email = $request->email;
        $customer_id = $request->customer_id;
        if($cellphone != null){
           $numberofOTP = OTP::where('cellphone',$cellphone)
            ->where('created_at', '>',\Carbon\Carbon::now()->subSecond(120)->format('Y-m-d H:i:s'))->orderBy('id','desc')->exists();
            if($numberofOTP){
               return response()->json(['status' => 'Please try again after 120 seconds'], 300);
            }
        }
        if($customer_id != NULL && $customer_id != '')
        {
            $customer = Customer::where('id', $customer_id)->first(array('cellphone', 'email'));
            if($customer != NULL)
            {
                $cellphone = $customer->cellphone;
                $email = $customer->email;
            }
        }
        $otp = new OTP();
        try {

            if ($cellphone != null || $email != null) {
                //$customer = Customer::where('email',$email)->first();
                $otp_response = $otp->OTPStore($cellphone); //save otp
                $data = $otp_response->getData();
                $id = $data->id;
                $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
                $data1=[];
                $data1 =[
                    "type"=>"template",
                    "subType"=>"policy_create_otp",
                    "mobileNumber"=>'267'.$cellphone,
                    "policyOtp"=> $data->otp_code,
                    "policyNumber"=>null,
                    "customer_id"=>  null
                ];

                $WhatsAppController=  new WhatsAppController();
                $WhatsAppController->sendMessage($data1);

                //$sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);
                if ($email != null) {
                    $data = new \stdClass();
                    $data->user_id = $id;
                    $data->customer_id = null;
                    $data->hook = 'otp_mail';
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                    //Mail::to($email)->send(new MailTemplate($data));
                }


                return response()->json(['status' => 'OTP code sent successfully to ' . $cellphone], 200);
            } else {
                return response()->json('User does not exist', 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 401);
        }
    }



    public function sendOTPPolicy(Request $request)
    {
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        if($cellphone != null){
           $numberofOTP = OTP::where('cellphone',$cellphone)
            ->where('created_at', '>',\Carbon\Carbon::now()->subSecond(120)->format('Y-m-d H:i:s'))->orderBy('id','desc')->exists();
            if($numberofOTP){
               return response()->json(['status' => 'Please try again after 120 seconds'], 300);
            }
        }
        $email = $request->email;
        $accountNumber = $request->accountNumber;
        $billingMethod = $request->billingMethod;
        //$customer = new Customer();
        $otp = new OTP();

        try {

            if ($cellphone != null || $email != null) {

                //$customer = Customer::where('email',$email)->first();
                $otp_response = $otp->OTPStore($cellphone); //save otp
                $data = $otp_response->getData();
                $id = $data->id;
                if(isset($data->customer_id)){
                    $customer_id =   $data->customer_id;
                }else{
                    $customer_id =   null;
                }

                $data1 =[
                    "type"=>"template",
                    "subType"=>"policy_create_otp",
                    "mobileNumber"=>'267'.$cellphone,
                    "policyOtp"=> $data->otp_code,
                    "policyNumber"=>null,
                    "customer_id"=> null
                ];

                $WhatsAppController=  new WhatsAppController();
                $WhatsAppController->sendMessage($data1);
               // }
                if($billingMethod == 'RealPay'){
                    $b = Banks::where('bank_number',$request->bank)->first();

                    if($b && $b->bank_name){
                        $bank = $b->bank_name;
                    }else{
                        $bank = '-';
                    }

                    $smsMessaging = new SmsMessaging;
                    $smsMessaging->sendOTPPolicyCreate(36, $bank,$accountNumber,$data->otp_code,$cellphone);
                }else {
                    $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
                    //$sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);
                }


                if ($email != null) {
                    $data = new \stdClass();
                    $data->user_id = $id;
                    $data->customer_id = null;
                    $data->hook = 'otp_mail';
                    $data->attachment = null;

                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                   $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	               event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));

                   //Mail::to($email)->send(new MailTemplate($data));
                }


                return response()->json(['status' => 'OTP code sent successfully to ' . $cellphone], 200);
            } else {
                return response()->json('User does not exist', 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 401);
        }
    }

    public function testMail(Request $request)
    {
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        $email = $request->email;
        //$customer = new Customer();
        $otp = new OTP();

        try {

            if ($cellphone != null || $email != null) {
                //$customer = Customer::where('email',$email)->first();
                $otp_response = $otp->OTPStore($cellphone); //save otp
                $data = $otp_response->getData();
                $id = $data->id;
                $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
                //$sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);
                if ($email != null) {
                    $data = new \stdClass();
                    $data->user_id = $id;
                    $data->customer_id = null;
                    $data->hook = 'otp_mail';
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                  //  Mail::to($email)->send(new MailTemplate($data));
                }
                return response()->json(['status' => 'OTP code sent successfully to ' . $cellphone], 200);
            } else {
                return response()->json('User does not exist', 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 401);
        }
    }

    public function authenticateOTPCodeUsingOtpCodeAndCellphone($query, $otp_code, $cellphone)
    {
        return $query->where('otp', $otp_code)->where('cellphone', $cellphone)->exists();
    }

    public function authenticateOTP(Request $request)
    {
        //code to autheticate the Customers OTP and Number
        $cellphone = $request->phoneNumber;
        $otp_code = $request->otpCode;
        try {
            if ($cellphone != null && $otp_code != null) {
                $otp = new OTP(); //instance of OTP model
                $otpValid = $otp->authenticateOTPCodeUsingOtpCodeAndCellphone($otp_code, $cellphone);
                // REMOVED (security, CFO-approved 3-Sep-2026): the OTPTemp "master
                // OTP" fallback let ANY otp_code that existed anywhere pass for ANY
                // cellphone while a single admin-set code was in its 24h window — a
                // backdoor over the whole customer base. A wrong code now simply
                // fails below, like it should.


                if ($otpValid == true) { //if otp has corresponding cellphone
                    $otpData = $otp->getOTPDataUsingOTPCode($otp_code);
                    $user_id = Customer::where('cellphone', $cellphone)->first();
                    //$sms = InfobipSms::send('+267' . $otpData->cellphone, 'Alpha Direct, Your OTP Has been Verified');
                    $deleteOTP = $otp->deleteOTP($otpData->id);

                    if($user_id == null)
                        $id = null;
                    else
                        $id = $user_id->id;

                    return response()->json(['status' => 'success','customer_id'=>$id,'message' => 'OTP successfully verified'], 200);
                } else {
                    return response()->json(['status' => 'failed', 'message' => 'OTP verification failed'], 401);
                }
            } else {
                return response()->json('Phone number & OTP code is empty', 401);
            }
        } catch (Exception $th) {

            return response()->json(['error' => $th->getMessage()]);
        }
    }

    private function setDate($day)
    {
        $current_timestamp = Carbon::now()->timestamp;
        $newDate = date("d", $current_timestamp);
        $mnth = (int)date("m", $current_timestamp);
        $yr = (int)date("Y", $current_timestamp);

        // Days in the current month. Was cal_days_in_month(CAL_GREGORIAN, ...),
        // but ext/calendar isn't enabled on the prod PHP, so that fatals with
        // "Call to undefined function ...cal_days_in_month()". date('t') is a
        // core function and returns the same value.
        $v = (int) date('t', mktime(0, 0, 0, $mnth, 1, $yr));

        $days = $newDate - $day;
        if ($days < 0) {
            $date = Carbon::now()->addDays(abs($days))->format('Y-m-d');
            return $date;
        } elseif ($days > 0) {
            $date = Carbon::now()->addDays($v - abs($days))->format('Y-m-d');  /*env('AVERAGE_DAYS')*/
            return $date;
        } else {
            $date = Carbon::now()->format('Y-m-d');
            return $date;
        }
    }

    public function activationDetail(Request $request)
    {
        $profile = null;
        if ($request->get('cellphone') != null) {
            $profile = Customer::where('cellphone', $request->get('cellphone'))->orderBy('id', 'asc')->first();
        }
        if ($profile != null) {
            $profile->customer_id = $profile->id;
        }
        //check for omang and passport
        if ($profile == null) {
            if ($request->get('omang') != null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
            } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
            } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
            }
        }
        if ($profile != NULL) {
            if ($profile->password == null)
                $is_user = 0;
            else
                $is_user = 1;
        } else {
            $is_user = 0;
        }
        //Motor Comprehensive
        if ($request->get('activation_code') == 0) {
            return response()->json(['success' => 1, 'is_user' => $is_user], 200);
        }
        $activation = Activation::where('activation_code', $request->get('activation_code'))->first();
        $product = Product::where('id', $activation->product_id)->first();
        $regionVat = Region::where('id', $product->region_id)->first();
        $plan = Productplan::where('id', $activation->product_plan_id)->first();
        if ($product->name == null) {
            // Prepare the error message
            return response()->json('No product plans available', 401);
        } else {
            return response()->json(['success' => 1, 'product' => $product->name, 'product_id' => $product->id, 'plan' => $plan->name, 'plan_billing' => $plan->billing, 'plan_id' => $plan->id, 'premium' => $plan->premium, 'vat' => $regionVat->vat, 'vehicle' => $product->has_vehicle, 'is_user' => $is_user], 200);
        }
    }

    public function getproductDetails(Request $request)
    {
        $profile = null;
        if ($request->get('cellphone') != null) {
            $profile = Customer::where('cellphone', $request->get('cellphone'))->orderBy('id', 'asc')->first();
        }
        if ($profile != null) {
            $profile->customer_id = $profile->id;
        }
        //check for omang and passport
        if ($profile == null) {
            if ($request->get('omang') != null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
            } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
            } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
            }
        }
        // Enable Mati
        $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
        $mati_enable = $mati_config->value;

        // if($mati_enable == 1){
        //     $kyc = 0;
        // }else{
        //     $kyc = 1;
        // }
        $kyc = 0;
        $is_user_check = 0;
        if ($profile != NULL) {
            $checkPolicy = Policy::where('customer_id', $profile->customer_id)->get();
            if ($checkPolicy != NULL && isset($checkPolicy->policyNumber)) {
                $is_user_check = 1;
            } else {
                $is_user_check = 0;
            }
        } else {
            $is_user_check = 0;
        }

        if ($profile != NULL) {
            $is_user = 1;
            $customerkyc = KYC::where('customer_id', $profile->customer_id)->first(array('omang', 'passport'));
            if ($customerkyc != NULL) {
                if ($customerkyc->omang != NULL && $request->get('omang') != NULL)
                    $kyc = 1;
                if ($customerkyc->passport != NULL && $request->get('passport') != NULL)
                    $kyc = 1;
            }elseif($mati_enable == 1){
                $kyc = 0;
            }

            if($kyc == 0)
            {
                $customer = Customer::where('id', $profile->customer_id)->first(array('mati_identity'));
                if($customer->mati_identity != NULL)
                {
                    $mati = CustomerMati::where('identity_id', $customer->mati_identity)->orderBy('id', 'desc')->first(array('passport', 'omang'));
                    if ($mati != NULL) {
                        if ($mati->omang != NULL || $mati->passport != NULL)
                            $kyc = 1;
                    }
                }
            }

            if ($profile->password != null) {
                $is_user = 1;
            } else {
                $is_user = 0;
            }

            $customer = Customer::where('id', $profile->customer_id)->first(array('mati_identity'));
            if (isset($customer->mati_identity) && $customer->mati_identity != NULL  && $customer->mati_identity != '') {
                $kyc = 1;
            } elseif($mati_enable == 1) {
                $kyc = 0;
            }

        } else {
            $is_user = 0;
        }

        $product = Product::where('id', $request->product_id)->first();
        if ($request->product_id == 3)
            $request->plan_id = Productplan::where('product_id', $request->product_id)->first(array('id'))->id;

        $compliance = KycCompliance::where('id', $product->kyc_compliance)->first(array('flow_id'));
        if($compliance != NULL && $compliance->flow_id > 0 && $compliance->flow_id != NULL)
            $flow_id = $compliance->flow_id;
        else {
            if(env('APP_STATUS') == 'Production')
                $flow_id = '616ac99406694f001be574c7'; // AdvanceKYC LIVE
            else
                $flow_id = '612c87b1ebca36001b310ea1'; // LiveQuote Test
        }
        $regionVat = Region::where('id', $product->region_id)->first();
        $plan = Productplan::where('id', $request->plan_id)->first();

        if ($product == null) {
            // Prepare the error message
            return response()->json('No product id available', 401);
        } else {
            $product = Product::where('id', $request->product_id)->first();
            if ($request->product_id == 3)
                $request->plan_id = Productplan::where('product_id', $request->product_id)->first(array('id'))->id;


            $regionVat = Region::where('id', $product->region_id)->first();
            $plan = Productplan::where('id', $request->plan_id)->first();

            $bundled_show = 0;
            $MotorComprehensivedisplay = 0;
            $data1 = Config::where('key','bundled_products_settings')->first();
            if( $data1 == null ){
                $data = null;
                return view('Bundled_settings', compact('data'));

            }else{
                $data4 = json_decode($data1->value);
                foreach($data4 as $data3)
                $bundled_show = $data3->bundled_show;
                $MotorComprehensivedisplay = $data3->motor_comprehensive;
              }
            return response()->json(['success' => 1, 'product' => $product->name, 'MotorComprehensivedisplay' => $MotorComprehensivedisplay, 'bundled_show'=> $bundled_show, 'plan' => $plan->name, 'plan_billing' => $plan->billing, 'plan_id' => $plan->id, 'premium' => $plan->premium, 'vat' => $regionVat->vat, 'vehicle' => $product->has_vehicle, 'member' => $product->has_member, 'is_user' => $is_user, 'kyc' => $kyc,'is_user_check' => $is_user_check, 'mati_flow_id' => $flow_id], 200);
        }
    }


    public function editPolicy(Request $request)
    {
        $policy = Policy::where('id', $request->get('id'))->first();
        $user = Customer::with(['profile'])->where('id', $policy->customer_id)->first(array('id', 'firstName', 'lastName', 'email', 'cellphone'));
        $user->profile->dob = Carbon::parse($user->profile->dob)->format('Y-m-d');
        $product = Product::where('id', $policy->product_id)->first(array('name', 'product_type_id'));
        $plan = Productplan::where('product_id', $policy->product_id)->first(array('name'));
        $beneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();
        $banking = CustomerBanking::where('policy_id', $policy->id)->first();
        $kyc = KYC::where('customer_id', $policy->customer_id)->first();
        $policy_cellphone = PolicyCellPhone::where('policy_id', $policy->id)->get();
        if ($policy->has_vehicle)
            $vehicle = Vehicle::where('policy_id', $policy->id)->first();
        else
            $vehicle = NULL;
        return response()->json(
            array(
                'success' => 1,
                'policy' => $policy,
                'plan' => $plan,
                'user' => $user,
                'product' => $product,
                'beneficiary' => $beneficiaries,
                'banking' => $banking,
                'kyc' => $kyc,
                'vehicle' => $vehicle,
                'policy_cellphone' => $policy_cellphone
            ),
            200
        );
    }

    public function updatePolicy(Request $request)
    {
        try {
            $policy = Policy::where('id', $request->policyId)->first();
            $policy->sum_assured = $request->get('sum_insured');
            $saved = $policy->save();

            $user = Customer::where('id', $policy->customer_id)->first();
            $user->firstName = $request->get('firstname');
            $user->lastName = $request->get('lastname');
            $user->email = $request->get('email');
            $user->cellphone = $request->get('phone');
            $user->save();
            $profile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
            $profile->customer_id = $user->id;
            $profile->gender = $request->get('gender');
            $profile->address = $request->get('address');
            $profile->omang = $request->get('omang');
            $profile->passport = $request->get('passport');
            $profile->maritalstatus = $request->get('maritalstatus');
            $profile->city = $request->get('city');
            $profile->dob = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
            $profile->save();

            //For Already added members
            if (is_array($request->get('old_member'))) {
                for ($i = 0; $i < count($request->get('old_member')); $i++) {
                    $m = PolicyMember::where('id', $request->get('old_member')[$i])->first();
                    $m->relation = $request->get('old_relation')[$i];
                    $m->first_name = $request->get('old_memberFName')[$i];
                    $m->last_name = $request->get('old_memberLName')[$i];
                    $m->dob = Carbon::parse($request->get('old_memberDOB')[$i])->format('Y-m-d');
                    $m->gender = $request->get('old_memberGender')[$i];
                    $m->save();
                }
            }
            if ($request->get('members') != null) {
                foreach ($request->get('members') as $key => $member) {
                    if ($member['relation'] != null) {
                        $m = new PolicyMember();
                        $m->policy_id = $policy->id;
                        $m->relation = $member['relation'];
                        $m->first_name = $member['memberFName'];
                        $m->last_name = $member['memberLName'];
                        $m->dob = Carbon::parse($member['memberDOB'])->format('Y-m-d');
                        $m->gender = $member['memberGender'];
                        $m->save();
                    }
                }
            }
            if (is_array($request->get('old_beneficiary')) && $request->get('old_beneficiary') != null) {
                for ($i = 0; $i < count($request->get('old_beneficiary')); $i++) {
                    $b = PolicyBeneficiary::where('id', $request->get('old_beneficiary')[$i])->first();
                    $b->relation = $request->get('old_beneficiaryRelation')[$i];
                    $b->first_name = $request->get('old_beneficiaryFName')[$i];
                    $b->last_name = $request->get('old_beneficiaryLName')[$i];
                    $b->omang = $request->get('old_beneficiaryOmang')[$i];
                    $b->passport = $request->get('old_beneficiaryPassport')[$i];
                    $b->dob = Carbon::createFromFormat('d/m/Y', $request->get('old_beneficiaryDOB')[$i]);
                    $b->gender = $request->get('old_beneficiaryGender')[$i];
                    $b->payment = $request->get('old_beneficiaryPayment')[$i];
                    $b->save();
                }
            }
            if (is_array($request->get('beneficiaries')) && $request->get('beneficiaries') != null) {
                foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->relation = $beneficiary['beneficiaryRelation'];
                        $b->first_name = $beneficiary['beneficiaryFName'];
                        $b->last_name = $beneficiary['beneficiaryLName'];
                        $b->omang = $beneficiary['beneficiaryOmang'];
                        $b->passport = $beneficiary['beneficiaryPassport'];
                        $b->dob = Carbon::createFromFormat('d/m/Y', $beneficiary['beneficiaryDOB']);
                        $b->gender = $beneficiary['beneficiaryGender'];
                        $b->payment = $beneficiary['beneficiaryPayment'];
                        $b->save();
                    }
                }
            }

            $banking = CustomerBanking::where('customer_id', $policy->customer_id)->where('policy_id', $policy->id)->first();
            $banking->accountNumber = $request->get('accountNumber');
            $banking->billing = $request->get('billingMethod');
            $banking->billingCell = $request->get('contact');
            $banking->bankName = $request->get('bankName');
            $banking->branchCode = $request->get('branchCode');
            $banking->accountType = $request->get('bankAccountType');
            $saved = $banking->save();
            if ($policy->has_vehicle == '1') {
                $vehicle = Vehicle::where('customer_id', $policy->customer_id)->where('policy_id', $policy->id)->first();
                $vehicle->vehiclePlate = $request->vehiclePlate;
                $vehicle->make = $request->make;
                $vehicle->model = $request->model;
                $vehicle->year = $request->year;
                $vehicle->claim_count = $request->claim_count;
                $vehicle->is_imported = $request->is_imported;
                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->front = $filePath;
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->back = $filePath;
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->right = $filePath;
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->left = $filePath;
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicle' . '/registration' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->vehicleRegistration = $filePath;
                }
                $saved = $vehicle->save();
            }
            if ($policy->save()) {
                $customer = DB::select('call getCustomerCelllphone(?)', [$user->id]);
                $cellPhone = $customer[0]->cellphone;
                if ($cellPhone) {
                    $smsMessaging = new SmsMessaging;
                    $smsMessaging->sendPolicyUpdateSMS(6, $cellPhone, $policy->policyNumber);
                }
            }
            return response()->json(['success' => 1], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], $e->getCode());
        }
    }

    public function saveKyc(Request $request)
    {

        $kyc = new KYC();
        $kyc->customer_id = $request->customer_id;
        if ($request->hasFile('driving_license')) {
            $file = $request->file('driving_license');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $kyc->driving_license = $filePath;
        }
        if ($request->hasFile('omang')) {
            $file = $request->file('omang');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $kyc->omang = $filePath;
        }
        if ($request->hasFile('proof_residence')) {
            $file = $request->file('proof_residence');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $kyc->proof_residence = $filePath;
        }
        if ($request->hasFile('proof_income')) {
            $file = $request->file('proof_income');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $kyc->proof_income = $filePath;
        }
        if ($request->hasFile('passport')) {
            $file = $request->file('passport');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $kyc->passport = $filePath;
        }

        $kyc->compliance = 0;

        $kyc->save();
        return response()->json(['success' => 1], 200);
    }

    public function forgotPassword(Request $request)
    {
        $user = Customer::where('email', $request->get('email'))->first();
        if ($user != null && $user->email != null) {
            $email = $user->email;
            $id = $user->id;

            // Record an OUTSTANDING reset for this customer so the tokened
            // updatePassword guard (added 3-Sep-2026) will let THIS reset through
            // and nothing else. Without this row a genuine forgot-password would
            // be refused. status 0 = not yet used; updatePassword sets it to 1.
            UserPassword::create(['user_id' => $id, 'token' => Str::random(8), 'status' => 0]);

            $markdown = new ForgotPassword($id);
            $html = $markdown->render('Mail.ForgotPassword');
            event(new \AlphaDirect\Events\SendMail($email,'Alphadirect | Forgot Password',"",$html));
            //Mail::to($email)->send(new ForgotPassword($id));

            return response()->json(['success' => 1], 200);
        } else {
            return response()->json(['success' => 0], 401);
        }
    }

    public function resetPassword($id)
    {
        if ($user = Customer::where('id', base64_decode($id))->first()) {
            return view('auth.reset_password', compact('id'));
        }
        //        else{
        //            return view('auth.reset_password');
        //        }
    }

    public function updatePassword(Request $request)
    {
        if ($request->password != null) {
            $user = Customer::where('id', base64_decode($request->id))->first();
            if ($user != NULL) {
                // SECURITY (CFO-approved 3-Sep-2026): this endpoint used to change
                // any customer's password from just their (guessable) id, with no
                // reset token — account takeover by id. Require an OUTSTANDING
                // password-reset request for THIS customer (created by the
                // forgot-password / new-customer flow) and consume it, so a direct
                // POST with someone else's id no longer works.
                $reset = UserPassword::where('user_id', $user->id)
                    ->where(function ($q) { $q->whereNull('status')->orWhere('status', '!=', 1); })
                    ->orderBy('id', 'desc')->first();
                if (!$reset) {
                    // Reached by a browser form POST, so render the same error page
                    // the other branches use rather than raw JSON.
                    return view('error');
                }
                $reset->status = 1;
                $reset->save();

                $user->password = Hash::make($request->password);
                $user->save();
                //sms
                $sms = new SmsMessaging();
                $sms->sendSmsResetPassword(27, $user->cellphone, $user->firstName, $user->password);
                //mail
                if ($user->email != null) {
                    $data = new \stdClass();
                    $data->user_id = $user->id;
                    $data->customer_id = null;
                    $data->hook = 'password_reset';
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                    //Mail::to($user->email)->send(new MailTemplate($data));
                }
                return Redirect::route('thankyou');
            } else {
                return view('error');
            }
        } else {
            return view('error');
        }
    }


    public function forgotPassword_repaircenter(Request $request)
    {
        $user = RepairCenter::where('email', $request->get('email'))->first();
        if ($user != null && $user->email != null) {
            $email = $user->email;
            $id = $user->id;

            $markdown = new ForgotPasswordRepaircenter($id);
            $html = $markdown->render('Mail.ForgotPasswordRepaircenter');
            event(new \AlphaDirect\Events\SendMail($email,'Alphadirect | Forgot Password',"",$html));

          //  Mail::to($email)->send(new ForgotPasswordRepaircenter($id));

            return response()->json(['success' => 1], 200);
        } else {
            return response()->json(['success' => 0], 401);
        }
    }

    // for Repair Center
    public function resetPassword_repaircenter($id)
    {
        if ($user = RepairCenter::where('id', base64_decode($id))->first()) {
            return view('auth.reset_password_repaircenter', compact('id'));
        }
    }

    public function updatePassword_repaircenter(Request $request)
    {
        if ($request->password != null) {
            $user = RepairCenter::where('id', base64_decode($request->id))->first();
            if ($user != NULL) {
                $user->password = Hash::make($request->password);
                $user->save();
                return Redirect::route('thankyou');
            } else {
                return view('error');
            }
        } else {
            return view('error');
        }
    }

     //for First time User

     public function resetPasswordFirstTimeUser(Request $request)
     {
         try{
             if($request->filled('token')){
                 if(UserPassword::where('token', $request->token)->exists())
                 {
                     $user = UserPassword::where('token', $request->token)->first();
                     if($user->status == 0){
                         if (Customer::where('id', $user->user_id)->exists()) {
                             return redirect(env('LIVEQUOTE_URL').'reset_password.php?token='. $user->token);
                         }else{
                             // dd('customer not exists');
                             return redirect(env('LIVEQUOTE_URL').'reset_password_error.php');
                         }
                     }else{
                         return redirect(env('LIVEQUOTE_URL').'reset_password_error.php')->with('status','Status is invalid' );
                     }
                 }else{
                     // dd('user not exists ');
                     return redirect(env('LIVEQUOTE_URL').'reset_password_error.php');
                 }
             }else{
                 // dd('if(!isset($request->token) && $request->token != null)');
                 return redirect(env('LIVEQUOTE_URL').'reset_password_error.php');
             }
         }catch(Exception $ex)
         {
             return redirect(env('LIVEQUOTE_URL').'reset_password_error.php');
         }
     }

    public function updatePasswordFirstTimeUser(Request $request)
    {
        if ($request->password != null) {
            $user = Customer::where('id', base64_decode($request->id))->first();
            $user_status = UserPassword::where('user_id', base64_decode($request->id))->first();
            if ($user != NULL) {
                $user->password = Hash::make($request->password);
                $user->save();

                $user_status->status = 1;
                $user_status->save();
                //sms
                $sms = new SmsMessaging();
                $sms->sendSmsResetPassword(27, $user->cellphone, $user->firstName, $user->password);
                //mail
                if ($user->email != null) {
                    $data = new \stdClass();
                    $data->user_id = $user->id;
                    $data->customer_id = null;
                    $data->hook = 'password_reset';
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                 //   Mail::to($user->email)->send(new MailTemplate($data));
                }
                return Redirect::route('thankyou');
            } else {
                return view('error');
            }
        } else {
            return view('error');
        }
    }

    public function checkIfMemberAlreadyRegistered($idType, $idValue)
    {
        $omang = $idType == 'Omang' ? $idValue : null;
        $passport = $idType == 'Passport' ? $idValue : null;
        if ($omang != null || $passport != null) {
            $count = CustomerProfile::where(strtolower($idType), $idValue)->first();
            if ($count != null) {
                $policyCount = 0;
                $customers = CustomerProfile::where(strtolower($idType), $idValue)->get();
                foreach ($customers as $customer) {

                    $checkPolicy = Policy::where('customer_id', $customer->customer_id)->where("product_id", "1")->first();
                    if ($checkPolicy == null) {
                        return false;
                    } else {
                        $checkStatus = $checkPolicy->status;
                        if ($checkStatus != null && ($checkStatus != 2)) {
                            $policyCount = $policyCount + 1;
                        }
                    }
                }
                if ($policyCount > 0)
                    return true;
                else
                    return false;
            } else {
                return false;
            }
        }
    }

    public function generateActivationCode(Request $request)
    {
        $latest_code = Activation::orderBy('id', 'DESC')->first(array('serial_code', 'activation_code', 'group_id'));
        if ($latest_code == null) {
            $serial_code = 'AAAAAA';
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $group_id = 1;
        } else {
            $serial_code = $latest_code->serial_code;
            $group_id = $latest_code->group_id + 1;
            $activation_code = Helper::gen_ustring(10000000, 99999999);
        }
        $activation = new Activation();
        if ($latest_code != null) {
            $var = base_convert($serial_code, 36, 10);
            $var++;
            //To check if number in serial code than increament
            while (preg_match('~[0-9]+~', strtoupper(base_convert($var, 10, 36)))) {
                $var++;
            }
            $serial_code = strtoupper(base_convert($var, 10, 36));
        }
        $activation->group_id = $group_id;
        $activation->serial_code = $serial_code;
        $check = Activation::where('activation_code', $activation_code)->count();
        while ($check > 0) {
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $check = Activation::where('activation_code', $activation_code)->count();
        }
        $activation->activation_code = $activation_code;
        $activation->vendor = $request->vendor;
        $activation->branch = $request->branch;
        $activation->rack_no = $request->rack_no;
        $activation->trial_periods = $request->trial_periods;
        $activation->trial_coverage = $request->trial_coverage;
        $activation->country = $request->country;
        $activation->city = $request->city;
        $activation->state = $request->state;
        $activation->product_type_id = Product::where('id', $request->product)->first(array('product_type_id')); //product_type_id
        $activation->product_id = $request->product;
        $activation->premium_type_id = Product::where('id', $request->product)->first(array('premium_type_id'));
        $activation->product_plan_id = $request->plan;
        $activation->status = 0;
        $saved = $activation->save();

        $check = CustomerGeneratedActivationCode::where('cellphone', $request->get('cellphone'))
            ->where('id_number', $request->get('id_number'))
            ->Where('status', '0')
            ->Where('product_id', $request->get('product'))
            ->Where('plan_id', $request->get('plan'))
            ->first(array('activation_code'));
        if ($check == NULL) {
            $activationSave = new CustomerGeneratedActivationCode();
            $activationSave->activation_code = $activation_code;
            $activationSave->id_type = $request->id_type;
            $activationSave->id_number = $request->id_number;
            $activationSave->cellphone = $request->cellphone;
            $activationSave->status = $request->status;
            $activationSave->product_id = $request->product;
            $activationSave->plan_id = $request->plan;
            $activationSaved = $activationSave->save();
            $smsMessaging = new SmsMessaging;
            $smsMessaging->sendActivationCode($request->cellphone, $activation_code);

            if ($saved && $activationSaved) {
                return response()->json(['activationCode' => $activation_code], 200);
            } else {
                return response()->json('Can not process the activation.Please contact administration. ', 401);
            }
        } else {
            return response()->json(['activationCode' => $check->activation_code], 200);
        }
    }

    public function comprehensiveLead(Request $request)
    {
        try {
            $user = new Customer();
            $user->firstName = $request->firstName;
            $user->lastName = $request->lastName;
            $user->cellphone = $request->cellphone;
            $user->email = $request->email;
            $user->save();
            $id = $user->id;
            if ($user != null) {
                //Latestid for Lead Number
                $latest = PolicyLead::latest()->first(array('id'));

                $lead = new Lead();
                $lead->customerId = $id;
                $lead->product = 'Motor Comprehensive';
                $lead->note = 'Estimated value of a vehicle is above 500,000';
                $lead->hasPurchased = $request->has_purchased;
                $lead->save();
                return response()->json(['success' => 1], 200);
            }
        } catch (Exception $ex) {

            return response()->json(['success' => 0], 401);
        }
    }


    public function customerFeedback(Request $request)
    {
        try {
            DB::beginTransaction();
            $banking = CustomerBanking::where('policy_id', $request->policy_id)->first();
            $policyToCancel = Policy::where('id', $request->policy_id)->first();

            $policyToCancel->status = 2;
            $cancelled = $policyToCancel->save();

            if ($cancelled) {
                if ($request->customer_id != null) {
                    $customer = Customer::where('id', $request->customer_id)->first();
                    $data = new \stdClass();
                    $data->policy_id = $request->policy_id;
                    $data->customer_id = $customer->id;
                    $data->hook = 'cancel_policy';
                    $data->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,NULL,['policyNumber' => $policyToCancel->policyNumber,'hook' => $data->hook]));
                   // Mail::to($customer->email)->send(new MailTemplate($data));

                    //sms
                    $sms = new SmsMessaging();
                    $sms->SendSMSEmailPolicyCancelled($customer->cellphone,$customer->firstName,$policyToCancel->policyNumber);
                    // $sms->SendSMSEmailPolicyCancelled(24, $policyToCancel->policyNumber, $customer->firstName, $customer->cellphone);

                    $feedback = new CustomerFeedback();
                    $feedback->policy_id = $request->policy_id;
                    $feedback->customer_id = $request->customer_id;
                    $feedback->product_id = $request->product_id;

                    if ($request->reason != null) {
                        $feedback->reason = $request->reason;
                    }
                    if ($request->circumstances != null) {
                        $feedback->circumstances = $request->circumstances;
                    }
                    if ($request->other_company != null) {
                        $feedback->other_company = $request->other_company;
                    }
                    $feedback->save();

                  /*  $banking = CustomerBanking::where('policy_id', $policyToCancel->id)->first();
                    if ($banking->billing == 'VCS') {
                        $transctionsRow = Transaction::where('policyNumber', $policyToCancel->policyNumber)->orderBy('id', 'desc')->first();
                        if ($transctionsRow && $transctionsRow->referenceNumber) {
                            $referenceNumber = $transctionsRow->referenceNumber;
                            $vcs = new PaymentController;
                            $vcs->suspendTransactionOnVCS($referenceNumber);
                        }
                    }elseif (NgeniusTransection::where('policy_number',$policyToCancel->policyNumber)->orderby('id','desc')->where('status',1)->exists()) {
                        $policyNumber = $policyToCancel->policyNumber;
                        $ngenius = new NgeniusPaymentController();
                        $cancel =  $ngenius->NgeniusRecurringDeletedata2($policyNumber);

                       if($cancel == 2){
                        return 2;
                       }else{
                        if($cancel == 1){
                            $policyToCancel->status = 2;
                            $policyToCancel->isPaymentCancel = 1;
                            $policyToCancel->save();
                        }
                    }


                    }elseif($banking->billing == 'DPO') {
                        $data = [
                            "token"            => ScheduleTransaction::where('policy_number', $policyToCancel->policyNumber)->where('status', 1)->value('token'),
                            "policy_number"    => $policyToCancel->policyNumber,
                            "CompanyRef"       => env('COMPANY_REF'),
                            "customer_id"      => $policyToCancel->customer_id,
                        ];

                        if($data['token'] != null )
                        {
                            CancelTokenEvent::dispatch($data);
                        }

                        if(ScheduleTransaction::where('policy_number', $data['policy_number'])->exists())
                        {
                            CancelScheduleTransactionEvent::dispatch($data);
                        }

                    }
                     elseif ($banking->billing == 'RealPay') {
                        $check = RealpayClientContracts::where('policy_id',$request->policy_id)
                            ->orderBy('id','desc')
                            ->get();

                        if($check->isEmpty()){
                            $check = RealpayContractDetails::where('ClientNumber', $policyToCancel->policyNumber)->get();
                        }

                        if($check->isEmpty()){
                            $check = RealpayPaymentRequest::where('policy_id', $request->policy_id)->get();
                        }

                        if ($check != null) {
                            $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            $addLog = $log->logEvent($policyToCancel->id, 2);

                            if ($addLog) {
                                $request                           = new RealpayCancelRequests();
                                $request->policy_id                = $policyToCancel->id;
                                $request->leftout_premium_contract = null;
                                $request->contract                 = $banking->contract_number;
                                $request->cancel_status            = 0;
                                $request->save();
                            } else {
                                return response()->json(['success' => 0], 401);
                            }
                        } else {
                            // policy lifecycle


                            return response()->json(['success' => 0, 'Message' => 'Policy cancelled successfully'], 200);
                        }
                    } else {
                        return response()->json(['success' => 0, 'Message' => 'Banking details not found'], 401);
                    }
*/
                    $action_user = null;
                    $action_customer = $request->customer_id;
                    event(new \AlphaDirect\Events\policyLifecycle($request->policy_id,"Cancel",$action_user,$action_customer));

                    $pc = new PolicyController();
                    $update = $pc->updatePolicyDates($policyToCancel->policyNumber, 2);
                    $cancelstatus =  $pc->CancelPaymentsForPolicy($policyToCancel);
                    DB::commit();

                    $banking = CustomerBanking::where('policy_id', $policyToCancel->id)->first();
                    if (isset($banking)) {
                        return response()->json(['success' => 1, 'paymentMethod' => $banking->billing, 'message' => 'Cancellation successful. Please log into VCS system and MANUALLY cancel the transaction.Feedback submitted successfully'], 200);
                    } else {
                        return response()->json(['success' => 1, 'paymentMethod' => null,'message' => 'Sucess! Your policy cancelled successfully'], 200);
                    }

                    // return response()->json(['success' => 1], 200);
                } else {
                    return response()->json(['success' => 0, 'paymentMethod' => null, 'message' => 'Something went wrong'], 401);
                }
            } else {
                return response()->json(['success' => 0, 'paymentMethod' => null, 'message' => 'Something went wrong'], 401);
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['success' => 0, 'message' => $ex,'paymentMethod' => null, 'message' => 'Something went wrong'], 401);
        }
    }

    public function customerFeedbackFromStart(Request $request)
    {
        try{
            DB::beginTransaction();

            $policyToCancel = Policy::where('id', $request->policy_id)->first();
            if ($policyToCancel) {
                if(!isset($request->fromstart) && $request->fromstart != "yes"){
                  return response()->json(['success' => 0, 'paymentMethod' => null], 401);
                }
                if ($request->customer_id != null) {
                    $feedback              = new CustomerFeedback();
                    $feedback->policy_id   = $request->policy_id;
                    $feedback->customer_id = $request->customer_id;
                    $feedback->product_id  = $request->product_id;

                    if ($request->reason != null) {
                        $feedback->reason = $request->reason;
                    }
                    if ($request->circumstances != null) {
                        $feedback->circumstances = $request->circumstances;
                    }
                    if ($request->other_company != null) {
                        $feedback->other_company = $request->other_company;
                    }
                    $feedback->save();

                  /*  $banking = CustomerBanking::where('policy_id', $policyToCancel->id)->first(); //open this when the customer_banking table bug fixes
                    $transctionsSelect = null;
                    if (isset($banking) && $banking->billing != 'RealPay') {

                        $transctionsSelect = Transaction::where('policyNumber', $policyToCancel->policyNumber)->orderBy('id', 'desc')->first();
                        if (!isset($transctionsSelect)) {
                            $transctionsSelect = PaymentTransaction::where('policyNumber', $policyToCancel->policyNumber)->where('paymentMethod', 'DPO')->orderBy('id', 'desc')->first();
                            if(!isset($transctionsSelect))
                            {
                                return response()->json(['success' => 0, 'Message' => 'Banking details not found'], 401);
                            }
                        }
                    }

                    if(isset($transctionsSelect->paymentMethod) && $transctionsSelect->paymentMethod == 'DPO')
                    {
                        $banking->billing = 'DPO';
                    }
                    else{
                        if (isset($transctionsSelect->referenceNumber) && $transctionsSelect->referenceNumber != '') {
                            $banking->billing = 'VCS';
                        }

                        if (isset($transctionsSelect->realPayTransaction_id) && $transctionsSelect->realPayTransaction_id != '') {
                            $banking->billing = 'RealPay';
                        }

                        if ($banking->billing == 'VCS') {
                            $transctionsRow = Transaction::where('policyNumber', $policyToCancel->policyNumber)->orderBy('id', 'desc')->first();
                            if ($transctionsRow && $transctionsRow->referenceNumber) {
                                $referenceNumber = $transctionsRow->referenceNumber;
                                $vcs = new PaymentController;
                                $vcs->suspendTransactionOnVCS($referenceNumber);
                                $policyToCancel->status = 2;
                                $policyToCancel->save();
                            }
                        }elseif (NgeniusTransection::where('policy_number',$policyToCancel->policyNumber)->orderby('id','desc')->where('status',1)->exists()) {
                        $policyNumber = $policyToCancel->policyNumber;
                        $ngenius = new NgeniusPaymentController();
                        $cancel =  $ngenius->NgeniusRecurringDeletedata2($policyNumber);

                       if($cancel == 2){
                        return 2;
                       }else{
                        if($cancel == 1){
                            $policyToCancel->status = 2;
                            $policyToCancel->isPaymentCancel = 1;
                            $policyToCancel->save();
                        }
                    }


                    }elseif ($banking->billing == 'RealPay') {
                            $check = RealpayClientContracts::where('policy_id',$request->policy_id)
                            ->orderBy('id','desc')
                            ->get();

                            if($check->isEmpty()){
                                $check = RealpayContractDetails::where('ClientNumber', $policyToCancel->policyNumber)->get();
                            }

                            if($check->isEmpty()){
                                $check = RealpayPaymentRequest::where('policy_id', $request->policy_id)->get();
                            }

                            if ($check != null) {
                                $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                $addLog = $log->logEvent($policyToCancel->id, 2);

                                if ($addLog) {
                                    $request = new RealpayCancelRequests();
                                    $request->policy_id = $policyToCancel->id;
                                    $request->leftout_premium_contract = null;
                                    $request->contract = $banking->contract_number;
                                    $request->cancel_status = 0;
                                    $request->save();
                                    $policyToCancel->status = 2;
                                    $policyToCancel->isPaymentCancel = 1;
                                    $policyToCancel->save();
                                } else {
                                    return response()->json(['success' => 0], 401);
                                }
                            }
                        }
                    }

                    $data = [
                        "token"            => ScheduleTransaction::where('policy_number', $policyToCancel->policyNumber)->where('status', 1)->value('token'),
                        "policy_number"    => $policyToCancel->policyNumber,
                        "CompanyRef"       => env('COMPANY_REF'),
                        "customer_id"      => $policyToCancel->customer_id,
                    ];

                    if($data['token'] != null )
                    {
                        CancelTokenEvent::dispatch($data);
                    }

                    if(ScheduleTransaction::where('policy_number', $data['policy_number'])->exists())
                    {
                        CancelScheduleTransactionEvent::dispatch($data);
                    }
                    */

                    $action_user = null;
                    $action_customer = $request->customer_id;
                    event(new \AlphaDirect\Events\policyLifecycle($request->policy_id,"Cancel",$action_user,$action_customer));

                    $policyToCancel->status = 2;
                    $policyToCancel->save();
                    $customer = Customer::where('id', $request->customer_id)->first();
                    if($customer){
                    if($customer->email != null){
                    $data = new \stdClass();
                    $data->policy_id = $request->policy_id;
                    $data->customer_id = $customer->id;
                    $data->hook = 'cancel_policy';
                    $data->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,NULL,['policyNumber' => $policyToCancel->policyNumber,'hook' => $data->hook]));
                   // Mail::to($customer->email)->send(new MailTemplate($data));
                    }
                    //sms
                    $sms = new SmsMessaging();
                    $sms->SendSMSEmailPolicyCancelled($customer->cellphone,$customer->firstName,$policyToCancel->policyNumber);

                    }

                    $pc     = new PolicyController();
                    $update = $pc->updatePolicyDates($policyToCancel->policyNumber, 2);
                    $cancelstatus =  $pc->CancelPaymentsForPolicy($policyToCancel);
                    DB::commit();

                    $banking = CustomerBanking::where('policy_id', $policyToCancel->id)->first();
                    if (isset($banking)) {
                        return response()->json(['success' => 1, 'paymentMethod' => $banking->billing], 200);
                    } else {
                        return response()->json(['success' => 1, 'paymentMethod' => null], 200);
                    }

                    // return response()->json(['success' => 1], 200);

                } else {
                    return response()->json(['success' => 0, 'paymentMethod' => null], 401);
                }
            } else {
                return response()->json(['success' => 0, 'paymentMethod' => null], 401);
            }
        }catch(Exception $x)
        {
            DB::rollback();
        }
    }

    public function cancelPolicy($id)
    {
        try {
            $policyToCancel = Policy::where('id', $id)->first();
            $policyToCancel->status = 2;
            $policyToCancel->save();
            $banking = CustomerBanking::where('policy_id', $policyToCancel->id)->first();
            if ($banking->billing == 'VCS') {
                $transctionsRow = Transaction::where('policyNumber', $policyToCancel->policyNumber)->orderBy('id', 'desc')->first();
                if ($transctionsRow && $transctionsRow->referenceNumber) {
                    $referenceNumber = $transctionsRow->referenceNumber;
                    $vcs = new PaymentController;
                    $vcs->suspendTransactionOnVCS($referenceNumber);
                    return 1;
                }
            } elseif ($banking->billing == 'RealPay') {
                $check = RealpayPaymentRequest::where('policy_id', $id)->first();
                if ($check != null && $check->status == 1 && ($check->contract == $id)) {
                    $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    $addLog = $log->logEvent($policyToCancel->id, 2);

                    if ($addLog) {
                        $request = new RealpayCancelRequests();
                        $request->policy_id = $policyToCancel->id;
                        $request->leftout_premium_contract = null;
                        $request->contract = $banking->contract_number;
                        $request->cancel_status = 0;
                        $request->save();

                        return 1;
                    } else {
                        return 0;
                    }
                }
            } else {
                return 0;
            }

            $pc = new PolicyController();
            $update = $pc->updatePolicyDates($policyToCancel->policyNumber, 2);
            return 1;
        } catch (Exception $exception) {
            return $exception;
        }
    }


    /* public function customerFeedback(Request $request)
    {
        $banking = CustomerBanking::where('policy_id',$request->policy_id)->first();
        $is_merged = $banking->merge_ref;
        if($is_merged){
         $amountToDeduct = Policy::where('policy_id',$banking->policy_id)->first();
        }
        $RealPayController = new RealPayController();
        $installments = $RealPayController->getInstallments($banking);
        if($installments != null){
            if($is_merged == 1){
                foreach($installments as $i){
                    $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                      <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                         <ns1:editInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pRequestdata>
                                <ns1:installmentReferenceNumber>'. $i['refNum'] .'</ns1:installmentReferenceNumber>
                                <ns1:ctcAmount></ns1:ctcAmount>
                                <ns1:actionDate></ns1:actionDate>
                                <ns1:tracking></ns1:tracking>
                                <ns1:status></ns1:status>
                                <ns1:installmentAmount>'. $i['totalInstallmentAmount'] - $amountToDeduct .'</ns1:installmentAmount>
                            </ns1:pRequestdata>
                         </ns1:editInstallmentsElement>
                      </soap:Body>
                    </soap:Envelope>';
                    $options = [
                        'headers' => [
                            'Content-Type' => 'application/soap+xml',
                        ],
                        'body' => $xml,
                    ];
                    $client = new \GuzzleHttp\Client();
                    $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
                }
                $response = $apiRequest->getBody()->getContents();
            }else{
                foreach($installments as $i){
                    $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                      <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                         <ns1:editInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pRequestdata>
                                <ns1:installmentReferenceNumber>'. $i['refNum'] .'</ns1:installmentReferenceNumber>
                                <ns1:ctcAmount></ns1:ctcAmount>
                                <ns1:actionDate></ns1:actionDate>
                                <ns1:tracking></ns1:tracking>
                                <ns1:status>I</ns1:status>
                                <ns1:installmentAmount></ns1:installmentAmount>
                            </ns1:pRequestdata>
                         </ns1:editInstallmentsElement>
                      </soap:Body>
                    </soap:Envelope>';
                    $options = [
                        'headers' => [
                            'Content-Type' => 'application/soap+xml',
                        ],
                        'body' => $xml,
                    ];
                    $client = new \GuzzleHttp\Client();
                    $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
                }
                $response = $apiRequest->getBody()->getContents();
            }
        }
        $policyToCancel = Policy::where('id',$request->policy_id)->first();
        $policyToCancel->status = 2;
        $cancelled = $policyToCancel->save();
        if($cancelled){
            if ($request->customer_id != null) {
                $feedback = new CustomerFeedback();
                $feedback->policy_id = $request->policy_id;
                $feedback->customer_id = $request->customer_id;
                $feedback->product_id = $request->product_id;
                if ($request->reason != null) {
                    $feedback->reason = $request->reason;
                }
                if ($request->circumstances != null) {
                    $feedback->circumstances = $request->circumstances;
                }
                if ($request->other_company != null) {
                    $feedback->other_company = $request->other_company;
                }
                $feedback->save();
                return response()->json(['success' => 1], 200);
            }
            else{
                return response()->json(['success' => 0], 401);
            }
        }else{
            return response()->json(['success' => 0], 401);
        }
    }*/


    public function sendOtpByPolicy(Request $request)
    {
        $policy_id = $request->policy_id;
        $policy = Policy::with('customer', 'kyc')->find($policy_id);
        if ($policy) {
            if (!empty($policy->customer)) {
                $request->request->add(['cellphone' => $policy->customer->cellphone]);
                $sendOtp = $this->requestOTP($request);
                if ($sendOtp->getStatusCode() == 200) {
                    return response()->json(
                        [
                            'success' => 1,
                            'status' => $sendOtp->getData()->status,
                            'cellphone' => $policy->customer->cellphone,
                            'customer' => $policy->customer,
                            'customer_compliance' => $policy->kyc
                        ],
                        200
                    );
                } else {
                    return response()->json(
                        [
                            'success' => 0,
                            'status' => $sendOtp->getData()
                        ],
                        200
                    );
                }
            }
        } else {
            return response()->json(['success' => 0, 'status' => 'Policy Not Found'], 200);
        }
    }


    public function imageUploadApi(Request $request){

        if (isset($request->image_url) && $request->image_url != null) {
            // if ($request->hasFile('image_url')) {
                // $file = $request->file('image_url');
                // $result = $this->isImageValid($file);
                // $name = $file->getClientOriginalName();
                // dd($request->all());

                // $filePath = 'image/upload/' . md5(rand(10, 1000)) . time() . $request->image_url;
                // Storage::disk('s3')->put($filePath, 'public');

                // SSRF guard: only allow safe public http(s) image URLs.
                // Reject non-http(s) schemes, raw IPs, and hosts resolving to
                // private/reserved/loopback/link-local ranges (incl. AWS metadata).
                $parsed = parse_url($request->image_url);
                if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])
                    || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                    return response()->json(['error' => 'Invalid image URL.'], 422);
                }
                $host = $parsed['host'];
                // If the host is already an IP literal, validate it directly;
                // otherwise resolve it via DNS.
                $resolvedIp = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
                if (!filter_var(
                    $resolvedIp,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                )) {
                    return response()->json(['error' => 'Invalid image URL.'], 422);
                }

                $headers = get_headers($request->image_url);
                $fileExist = stripos($headers[0],"200 OK") ? true : false;
                if($fileExist){
                    $filePath = 'image/upload/'.basename($request->image_url);
                    Storage::disk('s3')->put($filePath, file_get_contents($request->image_url));

                    // $fileLink = env('S3_BASE_URL') != null ? env('S3_BASE_URL').'/'.$filePath : config('app.S3_BASE_URL').'/'.$filePath;
                    $fileLink = config('app.S3_BASE_URL').$filePath;

                    return response()->json(['success' => true, 'fileLink' => $fileLink, 'fileLinkWithBaseURL' => $filePath], 200);
                }
                return response()->json(['success' => true, 'fileLink' => 'FILE_NOT_EXIST', 'fileLinkWithBaseURL' => 'FILE_NOT_EXIST'], 200);
            // } else {
            //     return response()->json(['status' => false, 'message' => 'Image not found.'], 201);
            // }

        } else {
            return response()->json(['status' => false, 'message' => 'Data not found.'], 201);
        }
    }

    // public function renewPolicy(Request $request)
    // {
    //     if($request->get('policyNumber') != null){
    //         // $renewPolicy = Policy::where('policyNumber', $request->get('policyNumber'))->get('id','billing_day');
    //         $renewPolicy = PaymentTransaction::where('policyNumber', $request->get('policyNumber'))->first();
    //         $renewPolicy->policyNumber = htmlspecialchars(strip_tags($request->input('policyNumber', '')));
    //         $renewPolicy->paymentDate = htmlspecialchars(strip_tags($request->input('paymentDate', '')));
    //         $renewPolicy->paymentMethod = htmlspecialchars(strip_tags($request->input('paymentMethod', '')));
    //         $renewPolicy->save();
    //         return response()->json(['status' => true, 'message' => 'stored'], 200);
    //     } else {
    //         return response()->json(['status' => false, 'message' => 'Data not found.'], 201);
    //     }

    // }

    public function renewPolicy(Request $request)
    {
        if($request->get('policyNumber') != null){
            $policy = Policy::where('policyNumber', $request->get('policyNumber'))->first();
            $policyNumber = htmlspecialchars(strip_tags($request->input('policyNumber', '')));
            $paymentDate = htmlspecialchars(strip_tags($request->input('paymentDate', '')));
            $paymentMethod = htmlspecialchars(strip_tags($request->input('paymentMethod', '')));

            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->first();

            //dd($customerBanking);

            return response()->json(['status' => true, 'message' => 'stored'], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Data not found.'], 201);
        }

    }


     public function lifeInsurance(Request $request)
    {
         $p1_m_a_d_i_premium = '';
         $add_p1_m_a_d_i = null;
         $subtotal1 = null;
         $subtotal2 = null;
         $subtotal3 = null;
         $subtotal4 = null;
         $subtotal5 = null;
         $totalPremium = null;

         $discount_rate = null;
         $t_p_c_i_premium = '';
         $count1 = 0;
         $count2 = 0;
         $count3 = 0;
         $count4 = 0;
         $count5 = 0;
         $data = 0;
         $motor_comprehensive_premium = '';
         $legal_insurance_premium = '';
         $c_d_insurance_premium = '';
         $add_life_insurance = 0;
         $regionVat = 14;
         $basedis = 0;
         $xx = "";
         $discount_on = "";


              if ($request->product_id == 1 ){$xx = "P1 Million Accidental Death Insurance";}
                if ($request->product_id == 2 ) {$xx = "Third Party Car Insurance" ;}
                if ($request->product_id == 3 ) { $xx = "Motor Comprehensive" ;}
                if ($request->product_id == 4 ) {$xx = "Legal Insurance" ;}
                if ($request->product_id == 5 ) {$xx = "Cellphone and device insurance";}


         $regionVat = Region::where('name', 'Botswana')->first(array('vat'))->vat;
if(isset($request->p1_m_a_d_i)){
         $product_plan = Productplan::where('product_id','1')->first();
      if($request->p1_m_a_d_i == 1){
         $p1_m_a_d_i_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
          }
if(isset($request->add_p1_m_a_d_i)){
      if($request->add_p1_m_a_d_i == 1){

         $subtotal1 = $p1_m_a_d_i_premium;
          }
      if($request->add_p1_m_a_d_i == 0){

         $subtotal1 = 0;
            }
         $count1 = $request->add_p1_m_a_d_i;
           }
           }
if(isset($request->t_p_c_i)){
      if($request->t_p_c_i == 1){
         $product_plan = Productplan::where('product_id','2')->where('name','P29_P100000_Cover')->first();
         $t_p_c_i_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
            }
      if($request->t_p_c_i == 2){
         $product_plan = Productplan::where('product_id','2')->where('name','P39_P500000_Cover')->first();
         $t_p_c_i_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
            }
      if($request->t_p_c_i == 3){
         $product_plan = Productplan::where('product_id','2')->where('name','P49_P1000000_Cover')->first();
         $t_p_c_i_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
            }
if(isset($request->add_t_p_c_i)){
      if($request->add_t_p_c_i == 1){
             // $add_p1_m_a_d_i = "Third Party Car Insurance added successfully!";
         $subtotal2 =  $t_p_c_i_premium;
            }
      if($request->add_t_p_c_i == 0){
             //  $add_p1_m_a_d_i = "Third Party Car Insurance removed successfully!";
         $subtotal2 = 0;
            }
         $count2 = $request->add_t_p_c_i;
           }

           }
           /////////////////////////////////////moter/////////
if(isset($request->motor_comprehensive)){
         $product_plan = Productplan::where('product_id','3')->first();
      if($request->motor_comprehensive == 1){
         $motor_comprehensive_premium =  number_format((float)( $request->moter_comp_pre), 2, '.', '');
            }
if(isset($request->add_motor_comprehensive)){
      if($request->add_motor_comprehensive == 1){
           //  $add_p1_m_a_d_i = "Motor Comprehensive added successfully!";
         $subtotal3 =  $request->moter_comp_pre;
            }
      if($request->add_motor_comprehensive == 0){
           //    $add_p1_m_a_d_i = "Motor Comprehensive removed successfully!";
         $subtotal3 = 0;
            }
             $count3 = $request->add_motor_comprehensive;
           }

           }
           //////////////////////////////////////////legal/////////
if(isset($request->legal_insurance)){

      if($request->legal_insurance == 1){
         $product_plan = Productplan::where('product_id','4')->where('name', 'P49_Legal')->first();
         $legal_insurance_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
            }
      if($request->legal_insurance == 2){
         $product_plan = Productplan::where('product_id','4')->where('name', 'P79_Funeral_cover')->first();
         $legal_insurance_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
            }
      if($request->legal_insurance == 3){
         $product_plan = Productplan::where('product_id','4')->where('name', 'P99_Funeral_cover')->first();
         $legal_insurance_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
            }
if(isset($request->add_legal_insurance)){
      if($request->add_legal_insurance == 1){
             // $add_p1_m_a_d_i = "Legal Insurance added successfully!";
         $subtotal4 =  $legal_insurance_premium;
            }
      if($request->add_legal_insurance == 0){
            //   $add_p1_m_a_d_i = "Legal Insurance removed successfully!";
         $subtotal4 = 0;
            }
         $count4 = $request->add_legal_insurance;
           }

           }
           /////////////////////////////////////////////////////////////cd/////////////////
if(isset($request->c_d_insurance)){
         $product_plan = Productplan::where('product_id','5')->first();
      if($request->c_d_insurance == 1){
         $c_d_insurance_premium = number_format((float)($product_plan->premium + $product_plan->premium  * $regionVat/100), 2, '.', '');
            }
if(isset($request->add_c_d_insurance)){
      if($request->add_c_d_insurance == 1){
             // $add_p1_m_a_d_i = "Cellphone and device insurance added successfully!";
         $subtotal5 =  $c_d_insurance_premium;
            }
      if($request->add_c_d_insurance == 0){
              // $add_p1_m_a_d_i = "Cellphone and device insurance removed successfully!";
         $subtotal5 = 0;
            }
         $count5 = $request->add_c_d_insurance;
           }
       }
////////////////////////////////////////////////////////
         $counter = 1 + $count1 + $count2 +  $count3 + $count4 + $count5;
    if ( $counter >= 2) {
         $bundled_products_settings = Config::where('key','bundled_products_settings')->first();
     if( $bundled_products_settings == null ){
         $data = 0;
             }else{
         $data4 = json_decode($bundled_products_settings->value);
 foreach($data4 as $data3)
         $data5 = $data3;
         $basedis = $data5->basediscount;
      if($counter == 2){
         $data = $data5->two_products;
            }
      if($counter == 3){
         $data =  $data5->three_products;
            }
      if($counter == 4){
         $data =  $data5->four_products;
            }
      if($counter > 4){
         $data =  $data5->more_than_four;
            }
            }
            }
         $add_p1_m_a_d_i = $add_p1_m_a_d_i;
         $subtotal = number_format((float)($subtotal1 + $subtotal2 + $subtotal3 + $subtotal4 + $subtotal5), 2, '.', '');
if(isset($request->m_c_premium)){
         $add_life_insurance = $request->m_c_premium;
           }
           if($request->product_id ==3 || $request->add_motor_comprehensive == 1){
            if($basedis == 1){
                $total1 = $subtotal + $add_life_insurance ;
                $discount_rate =  $data;

                $discount =   number_format((float)($total1 * $discount_rate /100), 2, '.', '');
                $totalPremium =  number_format((float)($total1 - $discount), 2, '.', '');
                $discount_on = "(Sub Total Premium + " .$xx.")";

              }else{

                $total1 = $subtotal;
                $discount_rate =  $data;
                if($request->add_motor_comprehensive == 1){
                     $discount =   number_format((float)(($total1 + $add_life_insurance -  $subtotal3) * $discount_rate /100), 2, '.', '');
                     $totalPremium =  number_format((float)($total1 + $add_life_insurance  - $discount ), 2, '.', '');
                }else{
                     $discount =   number_format((float)(($total1 -  $subtotal3) * $discount_rate /100), 2, '.', '');
                     $totalPremium =  number_format((float)($total1 + $add_life_insurance - $discount ), 2, '.', '');
                }


                $discount_on = "( Without motor comprehensive )";
               }
           }else{
            $total1 = $subtotal + $add_life_insurance ;
            $discount_rate =  $data;
            $discount =   number_format((float)($total1 * $discount_rate /100), 2, '.', '');
            $totalPremium =  number_format((float)($total1 - $discount), 2, '.', '');
            $discount_on = "(Sub Total Premium + " .$xx.")";
           }



         if(isset($request->tostermsg)){
            $add_p1_m_a_d_i = $request->tostermsg;
        }else{
           $add_p1_m_a_d_i = '';
        }

         return response()->json(['status' => true,'t_p_c_i_premium' => $t_p_c_i_premium,'discount_on'=>$discount_on,'c_d_insurance_premium' => $c_d_insurance_premium,'legal_insurance_premium'=>$legal_insurance_premium,'motor_comprehensive_premium'=>$motor_comprehensive_premium,  'p1_m_a_d_i_premium' => $p1_m_a_d_i_premium,'add_p1_m_a_d_i' => $add_p1_m_a_d_i,'subtotal' => $subtotal,'totalPremium' => $totalPremium, 'discount' => $discount, 'discount_rate' => $discount_rate, 'add_life_insurance' => $add_life_insurance ], 200);
    }
    public function BundledRerate(Request $request)
    {
    /* if( !BundledRerate::where('policy_id',$request->bundled_policy_id)->exists()){


       if($request->product_id_1_premium != ""){
          $BundledRerate = new BundledRerate();
           $BundledRerate->policy_id = $request->bundled_policy_id;
           $BundledRerate->subtotal = $request->subtotal5;
           $BundledRerate->bundled_discount_precent = $request->bundled_discount_precent;
           $BundledRerate->bundled_discount = $request->bundled_discount;
           $BundledRerate->final_premium = $request->final_premium;
           $BundledRerate->product_id = 1;
           $BundledRerate->plan_name = 1;
           $BundledRerate->premium = $request->product_id_1_premium;
           $BundledRerate->save();
        }
        if($request->product_id_2_premium != ""){
            $BundledRerate = new BundledRerate();
             $BundledRerate->policy_id = $request->bundled_policy_id;
             $BundledRerate->subtotal = $request->subtotal5;
             $BundledRerate->bundled_discount_precent = $request->bundled_discount_precent;
             $BundledRerate->bundled_discount = $request->bundled_discount;
             $BundledRerate->final_premium = $request->final_premium;
             $BundledRerate->product_id = 2;
             $BundledRerate->plan_name = 3;
             $BundledRerate->premium = $request->product_id_1_premium;
             $BundledRerate->save();
          }
          if($request->product_id_3_premium != ""){
            $BundledRerate = new BundledRerate();
             $BundledRerate->policy_id = $request->bundled_policy_id;
             $BundledRerate->subtotal = $request->subtotal5;
             $BundledRerate->bundled_discount_precent = $request->bundled_discount_precent;
             $BundledRerate->bundled_discount = $request->bundled_discount;
             $BundledRerate->final_premium = $request->final_premium;
             $BundledRerate->product_id = 3;
             $BundledRerate->plan_name = 1;
             if($request->frequency_mc_bundled_rerate != 1){
                $BundledRerate->frequency_mc = $request->frequency_mc_bundled_rerate;
             }else{
                $BundledRerate->frequency_mc = 1;
             }

             $BundledRerate->premium = $request->product_id_1_premium;
             $BundledRerate->save();
          }
          if($request->product_id_4_premium != ""){
            $BundledRerate = new BundledRerate();
             $BundledRerate->policy_id = $request->bundled_policy_id;
             $BundledRerate->subtotal = $request->subtotal5;
             $BundledRerate->bundled_discount_precent = $request->bundled_discount_precent;
             $BundledRerate->bundled_discount = $request->bundled_discount;
             $BundledRerate->final_premium = $request->final_premium;
             $BundledRerate->product_id = 4;
             $BundledRerate->plan_name = 1;
             $BundledRerate->premium = $request->product_id_1_premium;
             $BundledRerate->save();
          }
          if($request->product_id_5_premium != ""){
            $BundledRerate = new BundledRerate();
             $BundledRerate->policy_id = $request->bundled_policy_id;
             $BundledRerate->subtotal = $request->subtotal5;
             $BundledRerate->bundled_discount_precent = $request->bundled_discount_precent;
             $BundledRerate->bundled_discount = $request->bundled_discount;
             $BundledRerate->final_premium = $request->final_premium;
             $BundledRerate->product_id = 5;
             $BundledRerate->plan_name = 1;
             $BundledRerate->premium = $request->product_id_1_premium;
             $BundledRerate->save();
          }

          return response()->json(['status' => "success",'message' => "updated"]);
        }
        return response()->json(['status' => "error",'message' => "Already updated"]);
        */

        if($request->product_id_1_premium != ""){
            if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',1)->exists()){
                $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',1)->first();
            }else{
                $BundledRerate = new PolicyBundled();
                $BundledRerate->product_id = 1;
                $BundledRerate->plan_name = 1;
            }
             $BundledRerate->policy_id = $request->bundled_policy_id;
             $BundledRerate->subtotal = $request->subtotal5;
             $policy = Policy::where('id',$request->bundled_policy_id)->first();
             $policy->bundled_discount_precent = $request->bundled_discount_precent;
             $policy->bundled_discount = $request->bundled_discount;
             $policy->premium = $request->final_premium;
             $policy->save();
             $BundledRerate->final_premium = $request->final_premium;
             $BundledRerate->premium = $request->product_id_1_premium;
             $BundledRerate->save();
          }else{
            if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',1)->exists()){
            $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',1)->first();
            $BundledRerate->delete();
            }
        }
          if($request->product_id_2_premium != ""){
            if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',2)->exists()){
                $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',2)->first();
            }else{
                $BundledRerate = new PolicyBundled();
                $BundledRerate->product_id = 2;
                $BundledRerate->plan_name = 3;
            }
               $BundledRerate->policy_id = $request->bundled_policy_id;
               $BundledRerate->subtotal = $request->subtotal5;
               $policy = Policy::where('id',$request->bundled_policy_id)->first();
                $policy->bundled_discount_precent = $request->bundled_discount_precent;
                $policy->bundled_discount = $request->bundled_discount;
                $policy->premium = $request->final_premium;
                $policy->save();
               $BundledRerate->final_premium = $request->final_premium;
               $BundledRerate->premium = $request->product_id_2_premium;
               $BundledRerate->save();
            }else{
                if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',2)->exists()){
                $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',2)->first();
                $BundledRerate->delete();
                }
            }
            if($request->product_id_3_premium != ""){
                if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',3)->exists()){
                    $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',3)->first();
                }else{
                    $BundledRerate = new PolicyBundled();
                    $BundledRerate->product_id = 3;
                    $BundledRerate->plan_name = 1;
                }
               $BundledRerate->policy_id = $request->bundled_policy_id;
               $BundledRerate->subtotal = $request->subtotal5;
               $policy = Policy::where('id',$request->bundled_policy_id)->first();
               $policy->bundled_discount_precent = $request->bundled_discount_precent;
               $policy->bundled_discount = $request->bundled_discount;
               $policy->premium = $request->final_premium;
               $policy->save();
               $BundledRerate->final_premium = $request->final_premium;

               if($request->frequency_mc_bundled_rerate != 1){
                  $BundledRerate->frequency_mc = $request->frequency_mc_bundled_rerate;
               }else{
                  $BundledRerate->frequency_mc = 1;
               }

               $BundledRerate->premium = $request->product_id_3_premium;
               $BundledRerate->save();
            }else{
                if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',3)->exists()){
                $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',3)->first();
                $BundledRerate->delete();
                }
            }
            if($request->product_id_4_premium != ""){
              if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',4)->exists()){
                    $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',4)->first();
                }else{
                    $BundledRerate = new PolicyBundled();
                    $BundledRerate->product_id = 4;
                    $BundledRerate->plan_name = 1;
                }
               $BundledRerate->policy_id = $request->bundled_policy_id;
               $BundledRerate->subtotal = $request->subtotal5;
               $policy = Policy::where('id',$request->bundled_policy_id)->first();
               $policy->bundled_discount_precent = $request->bundled_discount_precent;
               $policy->bundled_discount = $request->bundled_discount;
               $policy->premium = $request->final_premium;
               $policy->save();
               $BundledRerate->final_premium = $request->final_premium;
               $BundledRerate->premium = $request->product_id_4_premium;
               $BundledRerate->save();
            }else{
                if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',4)->exists()){
                $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',4)->first();
                $BundledRerate->delete();
                }
            }
            if($request->product_id_5_premium != ""){
              if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',5)->exists()){
                    $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',5)->first();
                }else{
                    $BundledRerate = new PolicyBundled();
                    $BundledRerate->product_id = 5;
                    $BundledRerate->plan_name = 1;
                }
               $BundledRerate->policy_id = $request->bundled_policy_id;
               $BundledRerate->subtotal = $request->subtotal5;
               $policy = Policy::where('id',$request->bundled_policy_id)->first();
               $policy->bundled_discount_precent = $request->bundled_discount_precent;
               $policy->bundled_discount = $request->bundled_discount;
               $policy->premium = $request->final_premium;
               $policy->save();
               $BundledRerate->final_premium = $request->final_premium;
               $BundledRerate->premium = $request->product_id_5_premium;
               $BundledRerate->save();
            }else{
                if(PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',5)->exists()){
                $BundledRerate = PolicyBundled::where('policy_id',$request->bundled_policy_id)->where('product_id',5)->first();
                $BundledRerate->delete();
                }
            }






       /* $request->validate([
            'premium'   => 'required',
            'policy_number' => 'required',
            'product_id'    => 'required|integer|min:1',
            'premium_freq'  => 'required|',
        ]);
        if(!ScheduleTransaction::whereIn('status', [0, 1, 2, 3])->where('policy_number', $request->policy_number)->count() > 0 )
        {
            return redirect()->back()->withError('No records found.');
        }

        DB::beginTransaction();
        try{
            if($request->product_id == 3 && $request->premium_freq == 1)
            {
                $scheduleTrans = ScheduleTransaction::whereIn('status', [0, 1, 2, 3])
                                                ->where('policy_number', $request->policy_number)
                                                ->update([
                                                            'premium' => $request->premium
                                                        ]);
            }
            DB::commit();
            return redirect()->back()->withSuccess('Primium for schedule transactions updated successfully.');
        }catch(Exception $ex)
        {
            DB::rollback();
            return redirect()->back()->withError($ex->getMessage());
        }  */
    }
    public function upgradeAdiToAgiGold(Request $request)
    {

        if($policy = Policy::where('policyNumber',$request->policyNumber)->where('product_id',1)->where('plan_id',13)->where('status',1)->exists()){
            return response()->json(['status' => "error",'message' => "Policy already Active with Adi Gold Plan"], 400);
        }
        $policy = Policy::where('policyNumber',$request->policyNumber)->where('product_id',1)->where('plan_id','!=',13)->where('status',1)->first();

          if($policy != null){
            $banking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id','desc')->first();
            if ($banking != null && $banking->billing != $request->get('payment_method')) {
                return response()->json([
                    'status' => "error",
                    'message' => "Customer payment method will be same as previous payment method"
                ], 400);
            }
           // if($banking != null && ($banking->billing == "DPO" || $banking->billing == "N-Genius" || $banking->billing == "RealPay")){
                    $policyUpgrade = new PolicyUpgrade();
                    $policyUpgrade->policy_id = $policy->id;
                    $policyUpgrade->product_id = $policy->product_id;
                    $policyUpgrade->old_plan_id = $policy->plan_id;
                    $policyUpgrade->save();
                    $planId=13;
                    $plans = Productplan::where('id', $planId)->first(array('id', 'product_id', 'name', 'slug', 'premium', 'sum_assured'));
                    $product = Product::where('id', $plans->product_id)->first(array('region_id', 'premium_type_id'));
                    $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                    if ($product->premium_type_id == 11) {
                        $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
                    }
                $product = Product::where('id',1)->first();
                $policy->plan_id = 13;
                $policy->sum_assured = $plans->sum_assured;
                $policy->premium = $premium;
                $policy->vat = round( $premium* ($regionVat / 100), 2);
                $policy->vat_percent = $regionVat;
                $policy->save();
                $document = new DocumentController();
                $generate = $document->generatePolicyDocument($policy->id);
                $document->sendPolicyDocument($policy->id);

                $policyUpgrade->new_plan_id = 13;
                $policyUpgrade->status = 0;
                $policyUpgrade->agent_id =$request->agentId ;
                $policyUpgrade->save();
                $policyUpgrade->id;
                event(new \AlphaDirect\Events\policyLifecycle($policy->id, "Create"));
                // $policyController = new PolicyController();
                // $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                $old_payment_method = '';

                $checkCustomerBanking = CustomerBanking::where('policy_id', $policy->id)->exists();

                if($checkCustomerBanking){

                    $banking = CustomerBanking::where('policy_id',$policy->id)->first();

                    $old_payment_method = $banking->billing;

                }else{

                    $banking = new CustomerBanking();

                }
                $customer = Customer::where('id',$policy->customer_id)->first();
                $banking->customer_id = $policy->customer_id;

                $banking->policy_id = $policy->id;

                $banking->billing = $request->get('payment_method');

                $banking->billingCell = $customer->cellphone;

                if ($request->get('payment_method') == 'RealPay') {

                    $banking->bankName = $request->get('bankName');

                    $banking->branchCode = $request->get('branchCode');

                    $banking->accountNumber = $request->get('accountNumber');

                    $banking->accountType = $request->get('accountType');

                }
                if ($request->get('Payment_method') == 'PayM8') {

                    $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('accountType');
                }
                $banking->save();
                $post = PolicyUpgrade::find($policyUpgrade->id);
                $post->status = "1";
                $post->old_payment_type = $old_payment_method;
                $post->new_payment_type = $request->payment_method;
                $post->save();


            if($request->payment_method!=''){
                switch ($request->payment_method) {

                    case 'DPO':
                        // $pl = new PolicyController();
                        // $request->searchValue = $policy->policyNumber;
                        // $request->email = $request->dpo_email;
                        // $request->billing_date=  date("d/m/Y");
                        // $request->leadSource = "start.alphadirect.co.bw";

                        // $dpourl=$pl->updateExpiredCardForDPO($request);
                        // return $dpourl;
                        $schudule = ScheduleTransaction::where('policy_number',$policy->policyNumber)->whereIn('status',[0,1])->update(['premium'=>$policy->premium]);
                        return response()->json(['status' => "success",'message' => "Adi 49 to Adi Gold 79 upgraded Successfully"],200);
                        break;

                   case 'N-Genius':
                        $ng = new NgeniusPaymentController();
                        $policyController = new PolicyController();
                        $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
                        if($cancelPayment == 1){
                          $policy->status = 5;
                          $policy->save();
                        }
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource = "start.alphadirect.co.bw";
                        return $ng->NgeniusPayment($request);

                        break;
                    case 'PayM8':

                        $policyController = new PolicyController();
                        $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
                        $paym8 = new PayM8Controller();
                        $addEvent = $paym8->createAdHocPayment($policy->id);
                        return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                        break;


                    case 'RealPay':

                        //$realpay = new PolicyController();
                        $request->billingDay =  date("d/m/Y");
                        $request->policyID=$policy->id;
                        $request->leadSource = "start.alphadirect.co.bw";
                        //$cancelPayment = $realpay->CancelPaymentsForPolicy($policy);

                        if($old_payment_method == 'RealPay')
                        {
                            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            $request->policyID = $policy->id;
                            $clientNumber = $realpay->cancelOldcreateNewContract($request);
                        }
                        else
                        {
                            $clientNumber = $this->realpayPayment($policy);

                        }
                       //return response()->json(['status' => "success",'message' => "Adi 49 to Adi Gold 79 upgraded Successfully"],200);
                       return $clientNumber;
                     break;
                    default:
                        $policy->status = 5;
                        $policy->save();
                        $url = env("START_URL").'redopayment';
                        return response()->json(['status' => "success",'message' => "updated Please make payment" ,'url'=>$url],200);
                        break;
                }


            }else{
                $url = env("START_URL").'redopayment';
             return response()->json(['status' => "success",'message' => "updated" ,'url'=>$url],200);
            }

                return response()->json(['status' => "error",'message' => "Error"],200);
          }else{

            return response()->json(['status' => "error",'message' => "Policy Not Found Or Not Active"],400);
          }

    }
 public function upgradeTpToTpGold(Request $request)
    {
            if($policy = Policy::where('policyNumber',$request->policyNumber)
                ->where('product_id',2)->where('plan_id',16)->where('status',1)->exists()){
             return response()->json(['status' => "error",'message' => "Policy already Active with Third Party Car Insurance Gold Plan"], 400);
            }
          $policy = Policy::where('policyNumber',$request->policyNumber)->where('product_id',2)->where('plan_id','!=',16)->where('status',1)->first();
          if($policy != null){
            $banking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id','desc')->first();
            if ($banking != null && $banking->billing != $request->get('payment_method')) {
                return response()->json([
                    'status' => "error",
                    'message' => "Customer payment method will be same as previous payment method"
                ], 400);
            }
            //if($banking != null && ($banking->billing == "DPO" || $banking->billing == "N-Genius" || $banking->billing == "RealPay")){
                    $policyUpgrade = new PolicyUpgrade();
                    $policyUpgrade->policy_id = $policy->id;
                    $policyUpgrade->product_id = $policy->product_id;
                    $policyUpgrade->old_plan_id = $policy->plan_id;
                    $policyUpgrade->save();
                    $planId=16;
                $plans = Productplan::where('id', $planId)->first(array('id', 'product_id', 'name', 'slug', 'premium', 'sum_assured'));
                $product = Product::where('id', $plans->product_id)->first(array('region_id', 'premium_type_id'));
                $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                if ($product->premium_type_id == 11) {
                    $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
                    }
                $product = Product::where('id',1)->first();
                $policy->plan_id = 16;
                $policy->sum_assured = $plans->sum_assured;
                $policy->premium = $premium;
                $policy->vat = round( $premium* ($regionVat / 100), 2);
                $policy->vat_percent = $regionVat;
                $policy->save();
                $document = new DocumentController();
                $generate = $document->generatePolicyDocument($policy->id);
                $document->sendPolicyDocument($policy->id);
                $policyUpgrade->new_plan_id = 16;
                $policyUpgrade->agent_id = $request->agentId;
                $policyUpgrade->status = 0;
                $policyUpgrade->save();
                $policyUpgrade->id;

                event(new \AlphaDirect\Events\policyLifecycle($policy->id, "Create"));
                // $policyController = new PolicyController();
                // $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
                $old_payment_method = '';

                $checkCustomerBanking = CustomerBanking::where('policy_id', $policy->id)->exists();

                if($checkCustomerBanking){

                    $banking = CustomerBanking::where('policy_id',$policy->id)->first();

                    $old_payment_method = $banking->billing;

                }else{

                    $banking = new CustomerBanking();

                }

                $customer = Customer::where('id',$policy->customer_id)->first();
                $banking->customer_id = $policy->customer_id;

                $banking->policy_id = $policy->id;

                $banking->billing = $request->get('payment_method');

                $banking->billingCell = $customer->cellphone;



                if ($request->get('payment_method') == 'RealPay') {

                    $banking->bankName = $request->get('bankName');

                    $banking->branchCode = $request->get('branchCode');

                    $banking->accountNumber = $request->get('accountNumber');

                    $banking->accountType = $request->get('accountType');

                }
                if ($request->get('Payment_method') == 'PayM8') {

                    $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('accountType');
                }
                $banking->save();
                $post = PolicyUpgrade::find($policyUpgrade->id);
                $post->status = "1";
                $post->old_payment_type = $old_payment_method;

                $post->new_payment_type = $request->payment_method;
                $post->save();
            // }else{
            //   return response()->json(['status' => "error",'message' => "Policy payment vendor DPO or N-Genius are allowed to upgrade Third Party Car Insurance Gold"],400);

            // }
            $request->billingDay =   date("d/m/Y");
            if($request->payment_method!=''){
                switch ($request->payment_method) {

                    case 'DPO':
                        // $pl = new PolicyController();
                        // $request->searchValue = $policy->policyNumber;
                        // $request->email = $request->dpo_email;
                        // $request->billing_date=  date("d/m/Y");
                        // $request->leadSource = "start.alphadirect.co.bw";

                        // $dpourl=$pl->updateExpiredCardForDPO($request);
                        // return $dpourl;
                        $schudule = ScheduleTransaction::where('policy_number',$policy->policyNumber)->whereIn('status',[0,1])->update(['premium'=>$policy->premium]);
                        return response()->json(['status' => "success",'message' => "Adi 49 to Adi Gold 79 upgraded Successfully"],200);
                        break;

                   case 'N-Genius':
                        $ng = new NgeniusPaymentController();
                        $cancelold =   $ng->NgeniusRecurringDeletedata2($policy->policyNumber);
                        if($cancelold == 1){
                          $policy->status = 5;
                          $policy->save();
                        }
                        $ng = new NgeniusPaymentController();
                        // $policyController = new PolicyController();
                        // $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource = "start.alphadirect.co.bw";
                        return $ng->NgeniusPayment($request);

                        break;
                        case 'PayM8':

                            $policyController = new PolicyController();
                            $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
                            $paym8 = new PayM8Controller();
                            $addEvent = $paym8->createAdHocPayment($policy->id);
                            return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                            break;
                    case 'RealPay':

                        $realpay = new PolicyController();
                        $request->policyID=$policy->id;
                        $request->leadSource = "start.alphadirect.co.bw";
                        if($old_payment_method == 'RealPay')
                        {
                            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            $request->policyID = $policy->id;
                            $clientNumber = $realpay->cancelOldcreateNewContract($request);
                        }
                        else
                        {
                            $clientNumber = $this->realpayPayment($policy);

                        }

                       return $clientNumber;
                     break;
                    default:
                        $policy->status = 5;
                        $policy->save();
                        $url = env("START_URL").'redopayment';
                        return response()->json(['status' => "success",'message' => "updated Please make payment" ,'url'=>$url],200);
                        break;
                }


            }else{
                $url = env("START_URL").'redopayment';
             return response()->json(['status' => "success",'message' => "updated" ,'url'=>$url],200);
            }
                return response()->json(['status' => "error",'message' => "Error"],400);
          }else{

            return response()->json(['status' => "error",'message' => "Policy Not Found Or Not Active"],400);
          }

    }

    public function tqcreatePolicy(Request $request)
    {

        if ($request->Payment_method == 'DPO') {
            if (!isset($request->email)) {
                return response()->json(['title' => 'Email is mandatory', 'description' => 'PLease provide email if payment method is DPO.'], 412);
            }
        }


        if( isset($request->sum_insured) &&  ($request->product == 3 || $request->product_id == 3) && str_replace(',', '', $request->sum_insured)  > 500000){

                if($request->is_broker != 1 || $request->agent_id == null || $request->stores == null  ){
                    return response()->json(['success' => false, 'message' => 'We can not process policies worth more than 500000BWP, Please contact Alphadirect office.'], 401);
                }else{
                    $agent_500k = User::where('id',$request->agent_id)->first();
                    if($agent_500k == null){
                            return response()->json(['success' => false, 'message' => 'Agent ID not found Please enter a valid Agent id'],401);


                    }else{
                        if($agent_500k->bypass_500k != 1){
                            return response()->json(['success' => false, 'message' => 'This Agent ID has no permission for Estimated Value of Vehicle more than 500000BWP'],401);
                        }
                    }

            }
        }

        //check stock availability
        // $stock = \Modules\Inventory\Entities\StoresInventory::where('store_id','=',$request->get('stores'))->where('product_id','=',$request->get('product'))->where('plan_id','=',$request->get('plan_id'))->first(array('counter'));
        // if(($stock && $stock->counter == '0') || !$stock){
        //     return response()->json(['success' => false, 'message' => 'stock is not available at the moment.'], 401);
        // }
        try {
            DB::beginTransaction();

            if($request->product_id == 1 || $request->product_id == 4 || $request->product_id == 3){
                $dateOfBCheck = Carbon::createFromFormat('d/m/Y', $request->dob)->format('Y-m-d');
                $totalYears = Carbon::parse($dateOfBCheck)->age;
                // if ($totalYears < 18 || $totalYears > 65) {
                //     return ['success' => false, 'Message' => 'Customer age is less than 18 or more than 65.'];
                // }
                if ($totalYears < 18) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18 .'];
                }
            }


            $res = $this->validateFields($request->except('cust_dob','stores','e_name','emp_no','email','emp_phone','salary_pay_date','mati-identityId', 'customer_id', 'passportIssuingCountry', 'passportexpiry', 'omangExpiry', 'only_realpay_1','only_realpay_2'));

            if ($res != null && isset($res['success']) && $res['success'] == true) {
                return response()->json($res, 200);
            }
           // dd($request->all());
            // if ($request->hasFile('front')) {
            //     $file = $request->file('front');
            //     $result = $this->isImageValid($file);
            //     if ($result == false) {
            //         return response()->json(['success' => false, 'message' => 'The photo of the Front of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
            //     }
            // }
            // if ($request->hasFile('back')) {
            //     $file = $request->file('back');
            //     $result = $this->isImageValid($file);
            //     if ($result == false) {
            //         return response()->json(['success' => false, 'message' => 'The photo of the rear (back) of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
            //     }
            // }
            // if ($request->hasFile('right')) {
            //     $file = $request->file('right');
            //     $result = $this->isImageValid($file);
            //     if ($result == false) {
            //         return response()->json(['success' => false, 'message' => 'The photo of the right of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
            //     }
            // }
            // if ($request->hasFile('left')) {
            //     $file = $request->file('left');
            //     $result = $this->isImageValid($file);
            //     if ($result == false) {
            //         return response()->json(['success' => false, 'message' => 'The photo of the left of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
            //     }
            // }
            // if ($request->hasFile('vehicleRegistration')) {
            //     $file = $request->file('vehicleRegistration');
            //     $result = $this->isImageValid($file);
            //     if ($result == false) {
            //         return response()->json(['success' => false, 'message' => 'The photo of the Vehicle registration book is older than 24 hours, and can not be accepted. Please take a new image of the Vehicle registration book and re-upload.'], 401);
            //     }
            // }

            // $validator = Validator::make($request->all(), [
            //     'premium' => 'required',
            // ]);

            $validator = Validator::make($request->except('mati-identityId','customer_id', 'm_status'), [
                'premium' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->messages()->first()], 401);
            }

//            if ($product->type == 'Cellphone') {
//                if ($request->get('devices') != null) {
//                    foreach ($request->get('devices') as $key => $device) {
//
//                        if (!isset($device['imei']) || $device['imei'] == null) {
//                            return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
//                        }
//                    }
//                } else {
//                    return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
//                }
//            }

            //check for email and cellphone
            /*
            switch (true) {
                case $request->get('omang') != null || $request->get('phone') != null:
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first();
                    if($profile == null){
                        $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first(); // as we pointing to customer table
                        if ($profile != null) {
                            $profile->customer_id = $profile->id;
                        }
                    }
                    dd($profile);
                    break;

                case $request->get('passport') != null || $request->get('phone') != null:
                    $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first();
                    if($profile == null){
                        $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first(); // as we pointing to customer table
                        if ($profile != null) {
                            $profile->customer_id = $profile->id;
                        }
                    }
                    break;


                case $request->get('email') != null:
                    $profile = Customer::where('email', $request->get('phone'))->orderBy('id', 'asc')->first(); // as we pointing to customer table
                    if ($profile != null) {
                        $profile->customer_id = $profile->id;
                    }
                    break;

                default:
                    $profile = null;
                    break;
            } */

            //check for omang and passport
            $profile = null;
            $event_data = null;

            if ($request->get('phone') != null) {
                $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first(array('id','is_blocked','id as customer_id'));
            }
            // is blocked

            if($profile && $profile->is_blocked != null){
                if($profile->is_blocked == 1){
                    return response()->json(['success' => false,'message'=>'customer is blocked'], 200);
                }
            }


            if ($profile != null) {
                $profile->customer_id = $profile->id;

                if ($request->product == 1) {
                    $row = Policy::where('customer_id',$profile->customer_id)->where('product_id', $request->product)->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
                //Legal Products
                if ($request->product == 4) {
                    $row = Policy::where('customer_id',$profile->customer_id)->where('product_id', $request->product)->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
            }

            if ($profile == null) {
                if ($request->get('omang') != null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
                }
            }

            // $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
            // $mati_enable = $mati_config->value;
            $policy_id =$request->input('policy_id');
            if ($profile == null) {

                $fname = htmlspecialchars(strip_tags($request->input('firstname', '')));
                $lname = htmlspecialchars(strip_tags($request->input('lastname', '')));
                $email = htmlspecialchars(strip_tags($request->input('email', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('phone', '')));
                // $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));

                // if ($request->input('mati-identityId') && $request->input('mati-identityId') != '' && $request->input('mati-identityId') != NULL && $request->input('mati-identityId') != 'null') {
                //     $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));
                // }else {
                //     if($mati_enable == 0){
                //         $mati_identity  = 0;
                //     }else{
                //         $mati_identity  = NULL;
                //     }
                // }

                $f_login = 0; // flag for first time login user
                $data = [
                    'firstName' => $fname,
                    'lastName' => $lname,
                    'email' => $email,
                    'cellphone' => $cellphone,
                    'f_login' => $f_login,
                    'mati_identity' => $mati_identity,
                ];

                if ($request->get('password') != NULL) {
                    $data['password'] = Hash::make($request->get('password'));
                } else {
                    $data['password'] = Str::random(8);
                }

                $user_id = $this->customer_interface->add_new_customer($data);

                // for Password Reset
                $token                  = Str::random(8);
                $user_password          = new UserPassword();
                $user_password->user_id = $user_id;
                $user_password->token   = $token;
                $url                    = env('LIVEQUOTE_URL').'reset_password_first_time.php?token='.$token;
                $user_password->url     = $url;
                $user_password->save();


                //sms
                $sms = new SmsMessaging();
                $sms->sendSmsUserCreate(29, $fname, $lname, $cellphone, $url);

                //mail
                if ($email != null) {
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->customer_id = $user_id;
                    $data->new_user_password_url_id = $user_password->id;
                    $data->hook = 'user_create';
                    $data->attachment = null;

                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                    // return response()->json(['success' => 1, 'mail' => $mail], 200);
                }

                $gender         = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address        = htmlspecialchars(strip_tags($request->input('address', '')));
                $omang          = htmlspecialchars(strip_tags($request->input('omang', '')));
                $state          = htmlspecialchars(strip_tags($request->input('state', '')));
                $passport       = htmlspecialchars(strip_tags($request->input('passport', '')));
                $countryId      = htmlspecialchars(strip_tags($request->input('passportIssuingCountry', '')));
                $city           = htmlspecialchars(strip_tags($request->input('city', '')));
                $maritalstatus  = htmlspecialchars(strip_tags($request->input('maritalstatus', '')));
                $dob            = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
                $sourceOfIncome = json_encode($request->get('sourceOfIncome'));

                $data = [
                    'gender'      => $gender,
                    'customer_id' => $user_id,
                    'address'     => $address,
                    'omang'       => $omang,
                    'state'       => $state,
                    'passport'    => $passport,
                    //'countryId'       => $countryId,
                    'city'           => $city,
                    'maritalstatus'  => $maritalstatus,
                    'dob'            => $dob,
                    'sourceOfIncome' => $sourceOfIncome
                ];
                $data = array_merge($data, $this->buildLicenseProfileData($request));
                $this->customer_profile_interface->add_new_customer_profile($data);

                $omangExpiry = htmlspecialchars(strip_tags($request->input('omangexpiry', '')));
                $passportExpiry = htmlspecialchars(strip_tags($request->input('passportexpiry', '')));
                $omangFront = htmlspecialchars(strip_tags($request->input('omangKyc', '')));
                $omangBack = htmlspecialchars(strip_tags($request->input('omangbackKyc', '')));
                $passport = htmlspecialchars(strip_tags($request->input('passportKyc', '')));
                $data = [
                    'customer_id' => $user_id,
                    'omangExpiry' => $omangExpiry,
                    'passportExpiry' => $passportExpiry,
                    'passportIssuingCountry' => $countryId,
                ];
                if ($omangFront != NULL) {
                    $fileName = explode('/', $omangFront);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage:: disk('s3')->move($omangFront, $filePath);
                    $data['omang'] = $filePath;
                }
                if ($omangBack != NULL) {
                    $fileName = explode('/', $omangBack);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang_back' . '/' . $name;
                    Storage:: disk('s3')->move($omangBack, $filePath);
                    $data['omangBack'] = $filePath;
                }
                if ($passport != NULL) {
                    $fileName = explode('/', $passport);
                    $name = array_pop($fileName);
                    $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/passport' . '/' . $name;
                    Storage:: disk('s3')->move($passport, $filePath);
                    $data['passport'] = $filePath;
                }
                $customerKYCToken = htmlspecialchars(strip_tags($request->get('customerKYCToken', NULL)));
                if ($customerKYCToken != NULL) {
                    $data['customer_id'] = $user_id;
                    $this->customer_kyc_interface->update_customer_kyc_by_token($customerKYCToken, $data);
                } else {
                    $this->customer_kyc_interface->add_new_customer_kyc($data);
                }
            } else {


                $fname     = htmlspecialchars(strip_tags($request->input('firstname', '')));
                $lname     = htmlspecialchars(strip_tags($request->input('lastname', '')));
                $email     = htmlspecialchars(strip_tags($request->input('email', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('phone', '')));
                $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));
                if ($request->input('mati-identityId') && $request->input('mati-identityId') != '' && $request->input('mati-identityId') != NULL && $request->input('mati-identityId') != 'null') {
                    $mati_identity = htmlspecialchars(strip_tags($request->input('mati-identityId')));
                } else {
                    $customer = Customer::where('id',$profile->customer_id)->first();
                    if (isset($customer)) {
                        $mati_identity = $customer->mati_identity;
                    } else {
                        if($mati_enable == 0){
                            $mati_identity  = 0;
                        }else{
                            $mati_identity  = NULL;
                        }
                    }
                }
                $data = [
                    'firstName' => $fname,
                    'lastName' => $lname,
                    'email' => $email,
                    'cellphone' => $cellphone,
                    'mati_identity' => $mati_identity,
                ];
                if ($request->get('password') != NULL) {
                    $data['password'] = Hash::make($request->get('password'));
                }
                $user_id = $profile->customer_id;

                $this->customer_interface->update_customer_by_id($user_id, $data);
                $gender         = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address        = htmlspecialchars(strip_tags($request->input('address', '')));
                $omang          = htmlspecialchars(strip_tags($request->input('omang', '')));
                $passport       = htmlspecialchars(strip_tags($request->input('passport', '')));
                $maritalstatus  = htmlspecialchars(strip_tags($request->input('maritalstatus', '')));
                $city           = htmlspecialchars(strip_tags($request->input('city', '')));
                $state          = htmlspecialchars(strip_tags($request->input('state', '')));
                $dob            = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
                $countryId      = htmlspecialchars(strip_tags($request->input('passportIssuingCountry', '')));
                $sourceOfIncome = json_encode($request->get('sourceOfIncome'));

                $data = [
                    'customer_id'    => $user_id,
                    'gender'         => $gender,
                    'address'        => $address,
                    'omang'          => $omang,
                    'passport'       => $passport,
                    'maritalstatus'  => $maritalstatus,
                    'countryId'      => $countryId,
                    'city'           => $city,
                    'state'          => $state,
                    'dob'            => $dob,
                    'sourceOfIncome' => $sourceOfIncome
                ];
                $data = array_merge($data, $this->buildLicenseProfileData($request));
                $profile = $this->customer_profile_interface->update_customer_profile($user_id, $data);
                $omangExpiry = htmlspecialchars(strip_tags($request->input('omangexpiry', '')));
                $passportExpiry = htmlspecialchars(strip_tags($request->input('passportexpiry', '')));
                $omangFront = htmlspecialchars(strip_tags($request->input('omangKyc', '')));
                $omangBack = htmlspecialchars(strip_tags($request->input('omangbackKyc', '')));
                $passport = htmlspecialchars(strip_tags($request->input('passportKyc', '')));
                $data = [
                    'omangExpiry' => $omangExpiry,
                    'passportExpiry' => $passportExpiry,
                    'compliance' => 0,
                    'status' => "Unchecked",
                    'passportIssuingCountry' => $countryId,
                    'reason' => null,
                    'remark' => null,
                ];
                // if ($omangFront != NULL) {
                //     $fileName = explode('/', $omangFront);
                //     $name = array_pop($fileName);
                //     $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang' . '/' . $name;
                //     Storage::disk('s3')->move($omangFront, $filePath);
                //     $data['omang'] = $filePath;
                // }
                // if ($omangBack != NULL) {
                //     $fileName = explode('/', $omangBack);
                //     $name = array_pop($fileName);
                //     $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/omang_back' . '/' . $name;
                //     Storage::disk('s3')->move($omangBack, $filePath);
                //     $data['omangBack'] = $filePath;
                // }
                // if ($passport != NULL) {
                //     $fileName = explode('/', $passport);
                //     $name = array_pop($fileName);
                //     $filePath = 'MIS/' . $user_id . '/' . 'Customer' . '/passport' . '/' . $name;
                //     Storage::disk('s3')->move($passport, $filePath);
                //     $data['passport'] = $filePath;
                // }

                $customer = Customer::where('id',$user_id)->first();

                if (!isset($customer) && !isset($customer->mati_identity)) {
                    $customerKYCToken = htmlspecialchars(strip_tags($request->get('customerKYCToken', NULL)));
                    if ($customerKYCToken != NULL) {
                        $data['customer_id'] = $user_id;
                        $updateKyc = $this->customer_kyc_interface->update_customer_kyc_by_token($customerKYCToken, $data);
                    } else {
                        $updateKyc = $this->customer_kyc_interface->update_customer_kyc_by_id($user_id, $data);
                    }
                }
            }
            //Latestid for Policy Number
            $latest = Policy::orderBy('id', 'DESC')->first(array('policyNumber', 'id'));
            if ($latest == null) {
                $latest = collect();
                $latest->policyNumber = 0;
            }

            $product = Product::where('id', $request->get('product'))->first(array('id', 'has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));

            $policy = Policy::find($policy_id);

            if ($policy == null) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Original policy not found for this upgrade. Please verify the policy_id and try again.'], 404);
            }

            $policy->customer_id = $user_id;
            $policy->note = $request->get('note');
            $policy->quoteNumber = $request->quoteNumber;

            if(isset($request->is_bundled)){
                if ($request->is_bundled == 1) {
                   $is_bundled = 1;

            $policy->is_bundled = $is_bundled;
            if (isset($request->bundled_discount_precent)) {
            $policy->bundled_discount_precent = $request->bundled_discount_precent;
            }

            if (isset($request->bundled_discount)) {
            $policy->bundled_discount = $request->bundled_discount;
            }

           if((isset($request->product_id_mc) && $request->product_id_mc == 3) && $request->frequency_mc == 1){
                   $policy->first_premium =  str_replace(',', '', $request->first_month_premium);
                   $policy->first_premium_wvat =  str_replace(',', '', $request->first_month_premium);
                }
            }
        }
            $policy->leadSource = isset($request->leadSource) && $request->leadSource != null ? $request->leadSource : 'LiveQuote';

            if ($request->frequency != 1) {
                $request->billing_day = $policy->billing_day = date('d');
            }

            if(isset($request->billing_date) && $request->billing_date != null && $request->get('product') != 3){

                $request->billing_day = null;
            }

            if ($request->billing_day != null) {
                $policy->billing_day = htmlspecialchars(strip_tags($request->billing_day));
                $policy->billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
                $policy->ori_billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
            } else {
                if($request->get('product') == 3) {
                    $current_timestamp = Carbon::now()->timestamp;
                    $policy->billing_day = date("d", $current_timestamp);
                    $policy->billingStartDate = $this->setDate($policy->billing_day);
                    $policy->ori_billingStartDate = $this->setDate($policy->billing_day);
                }else{
                        $policy->BillingStart = $request->get('BillingStart') ? $request->get('BillingStart') : '';
                        $policy->billingStartDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->get('billing_date'))));
                        $policy->ori_billingStartDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->get('billing_date'))));

                }
            }

            if($request->get('product') != 3) {
                if(!empty($request->get('BillingStart'))){
                    if($request->get('BillingStart')=='Later'){
                        $policy->isVirtualBox = 1;
                    }else{
                        $policy->isVirtualBox = null;
                    }
                }
            }


            $policy->product_id = $request->get('product');
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
            if (($product->premium_type_id == 11) && ($request->product != 3)) {

                $product_plan = Productplan::where('id', $request->get('plan_id'))->first(array('sum_assured', 'premium'));
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->get('plan_id');

             if(isset($request->is_bundled) && $request->is_bundled == 1){

                $policy->premium = "1".$request->final_premium;
                $policy->sum_assured = htmlspecialchars(strip_tags($request->get('sum_insured')));
                if((isset($request->product_id_mc) && $request->product_id_mc == 3) && $request->frequency_mc == 1){

                 $policy->billing_day = $request->paydate_label_month_premium;
                 $policy->billingStartDate =  $this->setDate($policy->billing_day);
                 $policy->ori_billingStartDate =  $this->setDate($policy->billing_day);
               }

             }else{
                $policy->premium = "2".$premium;
                 $policy->sum_assured = $product_plan->sum_assured;
             }


                $policy->premium_freq = 1; //$request->frequency;
                $policy->vat = $product_plan->premium * ($regionVat / 100);
                $policy->vat_percent = $regionVat;
                $policy->agent_id = htmlspecialchars(strip_tags($request->agentCode));
                $policy->storeID = htmlspecialchars(strip_tags($request->store_id));
            } else {
                $f = htmlspecialchars(strip_tags($request->frequency));
                //                $premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
                //                $policy->premium = $request->get('premium');
                //                $policy->vat = $request->get('premium') * ($regionVat / 100);
                $policy->premium = $request->get('premium');
                $policy->premium_freq = $f;
                $policy->vat_percent = $regionVat;
                $policy->sum_assured = htmlspecialchars(strip_tags($request->get('sum_insured')));

                $plan = Productplan::where('product_id', htmlspecialchars(strip_tags($request->get('product'))))->first(array('id'));
                $policy->plan_id = $plan->id;
            }

            //Check policy id in archived policies
            $a_policy = ArchivedPolicies::where('id', $latest->id + 1)->exists();
            if ($a_policy == true)
                $addCount = $latest->id + 2;
            else
                $addCount = $latest->id + 1;

            $policy->policyNumber = $policy->policyNumber;
            $policy->status = 0;
            $policy->has_vehicle = $product->has_vehicle;
            $policy->has_member = $product->has_member;
            $policy->preinspection = $product->preinspection;
            $policy->is_motor_items = $product->is_motor_items;
            $policy->limit = $product->limit;
            $policy->kyc_customer = $product->kyc_customer;
            $policy->kyc_recipient = $product->kyc_recipient;

            if ($product->id == 3 && $request->is_bundled != 1) {
                if ($request->frequency != '1') {
                    $policy->first_premium = htmlspecialchars(strip_tags($request->premium));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags($request->premium_label_vat));
                } else {
                    $policy->first_premium = htmlspecialchars(strip_tags($request->leftout_premium));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags($request->leftout_premium_wvat));
                }
            }elseif($product->id == 3 && $request->is_bundled == 1){

                if ($request->frequency != '1') {
                    $policy->first_premium = htmlspecialchars(strip_tags(round($request->final_premium,2)));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags(round($request->premium_label_vat * $request->final_premium / $request->premium,2)));
                } else {
                    $policy->first_premium = htmlspecialchars(strip_tags(round($request->leftout_premium * $request->final_premium / $request->premium,2)));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags(round($request->leftout_premium_wvat * $request->final_premium / $request->premium,2)));
                }


            }
            //$policy->billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
            $addDays = 0;
            if ($product->has_activation_code) {
                $activationData = new \Illuminate\Http\Request();
                $activationData->setMethod('POST');
                $activationData->vendor = 5;
                $activationData->branch = 8;
                $activationData->rack_no = 0;
                $activationData->trial_periods = 30;
                $activationData->trial_coverage = 100000;
                $activationData->city = 'Gaborone';
                $activationData->state = 'Gaborone';
                $activationData->country = 'Botswana';
                $activationData->product = $request->product;
                $activationData->plan = $request->get('plan_id');
                $activationData->cellphone = $cellphone;
                $activationData->id_type = $request->get('omang') != "" ? "omang" : "passport";
                $activationData->id_number = $activationData->id_type == "omang" ? $request->get('omang') : $request->get('passport');
                $activationData->product_type_id = 11;
                $activationData->status = 0;
                $activationCode = $this->generateActivationCode($activationData);

                $activation = Activation::where('activation_code', $activationCode->getData()->activationCode)->first();
                $activation->status = 1;
                $activation->save();

                $policy->activation_code = htmlspecialchars(strip_tags($activationCode->getData()->activationCode));
                $policy->serial_code = $activation->serial_code;
                $policy->is_sys_act_generated = 1;
                $addDays = $activation->trial_periods;
            }

            $policy->trial_period = Carbon::now()->addDays($addDays)->format('Y-m-d');

            if ($request->agentCode != null || $request->agent_id != null) {
                $policy->agent_id = $request->agentCode == null ? $request->agent_id : $request->agentCode;
            }
            if ($request->agentCode != null || $request->agent_id != null) {
                if($request->agentCode != null){
                    $agent = \AlphaDirect\User::where('id',$request->agentCode)->where('agency_id','!=',null)->first(['agency_id']);

                }else if($request->agent_id != null){
                    $agent = \AlphaDirect\User::where('id',$request->agent_id)->where('agency_id','!=',null)->first(['agency_id']);

                }
                if($agent){
                     $policy->agency_id = $agent->agency_id;
                }
            }

            if ($request->store_id != null || $request->stores) {
                $policy->storeID = $request->store_id == null ? $request->stores : $request->store_id;
            }

            // Blocked Customer
            // if ($request->get('phone')!= null) {
            //     $blockedCustomer = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'desc')->first();
            //     if ($blockedCustomer->is_blocked == 1) {
            //         $policy->customer_blocked = 1;
            //     }
            // }

            $policySaved = $policy->save();

            event(new \AlphaDirect\Events\policyLifecycle($policy->id,"Create"));

            $agent_id = $request->agentCode == null ? $request->agent_id : $request->agentCode;
            $store_id = $request->store_id == null ? $request->stores : $request->store_id;

            if (($store_id != null || $store_id != '') && ($agent_id != null || $agent_id != '')) {
                $this->addAgentActivity($store_id, $agent_id);
            }
            if ($policySaved == true && $policy->agentCode != null) {
                $data = [
                    'id' => $policy->id,
                    'agent_id' => $policy->agentCode,
                    'status' => $policy->status,
                ];
                event(new CommissionPolicyEvent($data));
            }

            // if(env('APP_STATUS') == 'Development') {
                if ($product->has_activation_code != 0 && $store_id != null) {
                    $check = \Modules\Inventory\Entities\StoresInventory::where('store_id','=',$request->get('stores'))->exists();
                    if($check == true){
                        //If stock available it will further try to deduct
                        $stock = \Modules\Inventory\Entities\StoresInventory::where('store_id','=',$request->get('stores'))->where('product_id','=',$request->get('product'))->where('plan_id','=',$request->get('plan_id'))->first(array('counter','id'));
                        if ($policySaved == true && $stock->counter != '0') {
                            $quantity = 1;
                            $user = User::where('id',$request->get('agentCode'))->firstOr(function () {
                                return User::where('id',1)->first();
                            });
                            event(new \Modules\Inventory\Events\DeductStockStore($stock->id,$user,$quantity));
                        }
                    }
                }
            // }

            //calling event for decrement couneter for specific product, plan and store
            if ($policySaved == true) {
                $data = [
                    'store_id' => $request->store_id,
                    'product_id' => $request->product_id,
                    'plan_id' => $request->planId,
                ];
                event(new DecrementCounter($data));
            }

            $importStatus = '';

            if ($policySaved && $policy->quoteNumber != null && $request->get('product') == 3) {
                $updateQuote = $this->updateQuoteStatus($policy->quoteNumber);
                if ($updateQuote == true) {
                    $data = MotorComprehensiveQuotes::where('quoteNumber', htmlspecialchars(strip_tags($policy->quoteNumber)))->first();
                    $policy->premium_freq = $request->frequency;

                    $policy->vat = $policy->premium * ($regionVat / 100);
                    $policy->quoteNumber = $policy->quoteNumber;
                    $policy->sum_assured = $data->estimatedValue;

                    if ($data->is_imported == "Yes")
                        $importStatus = 1;
                    else
                        $importStatus = 0;

             if(isset($request->is_bundled) && $request->is_bundled == 1){

                $policy->premium = $request->final_premium;

                if((isset($request->product_id_mc) && $request->product_id_mc == 3) && $request->frequency_mc == 1){
                   $policy->first_premium = str_replace(',', '', $request->first_month_premium);

                   $policy->first_premium_wvat = str_replace(',', '', $request->first_month_premium);
                }

             }else{
                   if ($request->frequency == '1' ) {
                        $policy->premium = $data->premiumMonthly;
                    } elseif ($request->frequency == '2') {
                        $policy->premium = $data->premium3Inst;
                    } elseif ($request->frequency == '3') {
                        $policy->premium = $data->premiumAnnually;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Payment frequency not found'], 401);
                    }
                }

                    $saved = $policy->save();
                }
            }

            if ($product->has_member) {
                if ($request->get('beneficiaries') != null) {
                    foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                        if ($beneficiary['beneficiaryRelation'] != null) {
                            $b = PolicyBeneficiary::where('policy_id',$policy_id);
                            $b->policy_id = $policy->id;
                            $b->relation = isset($beneficiary['beneficiaryRelation']) && $beneficiary['beneficiaryRelation'] != null ? $beneficiary['beneficiaryRelation'] : "";
                            $b->omang = isset($beneficiary['beneficiaryOmang']) && $beneficiary['beneficiaryOmang'] != null ? $beneficiary['beneficiaryOmang'] : "";
                            $b->passport = isset($beneficiary['beneficiaryPassport']) && $beneficiary['beneficiaryPassport'] != null ? $beneficiary['beneficiaryPassport'] : "";
                            $b->first_name = isset($beneficiary['beneficiaryFName']) && $beneficiary['beneficiaryFName'] != null ? $beneficiary['beneficiaryFName'] : "";
                            $b->last_name = isset($beneficiary['beneficiaryLName']) && $beneficiary['beneficiaryLName'] != null ? $beneficiary['beneficiaryLName'] : "";
                            $b->dob = isset($beneficiary['beneficiaryDOB']) && ($beneficiary['beneficiaryDOB'] != "") ? date('Y-m-d', strtotime(str_replace('/', '-', $beneficiary['beneficiaryDOB']))) : "";
                            $b->gender = isset($beneficiary['beneficiaryGender']) && $beneficiary['beneficiaryGender'] != null ? $beneficiary['beneficiaryGender'] : "";
                            $b->payment = isset($beneficiary['beneficiaryPayment']) && $beneficiary['beneficiaryPayment'] != null ? $beneficiary['beneficiaryPayment'] : "";
                            $b->save();
                        }
                    }
                }
            }

            if ($request->get('product') == 4 ) {
                if($request->get('maritalstatus') == 2){
                    $b = PolicyBeneficiary::where('policy_id',$policy_id);
                    $b->policy_id = $policy->id;
                    $b->relation = 'Spouse';
                    $b->first_name = isset($request->legalFName) ? $request->legalFName : "";
                    $b->middle_name = isset($request->legalMName) ? $request->legalMName : "";
                    $b->last_name = isset($request->legalLName) ? $request->legalLName : "";
                    $b->cellphone = isset($request->legalPhone) ? $request->legalPhone : "";
                    $b->email = isset($request->legalEmail) ? $request->legalEmail : "";
                    $b->passport = isset($request->legalPassport) ? $request->legalPassport : "";
                    $b->omang = isset($request->legalOmang) ? $request->legalOmang : "";
                    $b->gender = isset($request->legalGender) ? $request->legalGender : "";
                    if(($request->legalDOB != null) || ($request->legalDOB != '') ){
                        $b->dob = Carbon::createFromFormat('d/m/Y', $request->legalDOB)->format('Y-m-d');
                    }
                    $b->legalOmangExpiry = isset($request->omangExpiry) ? $request->omangExpiry : "";
                    $b->legalPassportExpiry = isset($request->passportExpiry) ? $request->passportExpiry : "";
                    $b->save();
                }
            }

//            if($request->Payment_method == "orangeMoney"){
//                $request->Payment_method = "Orange USSD";
//            }
            $old_payment_method = '';
            $checkCustomerBanking = CustomerBanking::where('policy_id', $policy_id)->exists();
            $policyController = new PolicyController();
            $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
            if($checkCustomerBanking){
                $banking = CustomerBanking::where('policy_id', $policy_id)->first();

                $old_payment_method = $banking->billing;
            }else{
                $banking = new CustomerBanking();
            }
            $banking->customer_id = $user_id;
            $banking->policy_id = $policy->id;
            $banking->billing = $request->get('Payment_method');
            $banking->billingCell = $request->get('phone');
            if ($request->get('Payment_method') == 'RealPay') {
                $banking->bankName = $request->get('bankName');
                $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('bankAccountType');
            }
            if ($request->get('Payment_method') == 'PayM8') {

                $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('bankAccountType');
            }
            if ($request->frequency == 1) {
                if (htmlspecialchars(strip_tags($request->get('product'))) == 3 && $request->billing_day = !null) {
                    $banking->billingStartDate = $this->setDate($request->billing_day);
                }else{
//                    if(isset($request->billing_date) && $request->billing_date != null && $request->get('product') != 3){
//
//                    }else{
//                        $banking->billingStartDate = Carbon::now()->format('Y-m-d');
//                    }

                    $banking->billingStartDate = $policy->billingStartDate;
                }
            }elseif ($request->frequency == 2) {
                $banking->billingStartDate = Carbon::now()->addMonths(1)->format('Y-m-d');
            }else {
                $banking->billingStartDate = Carbon::now()->addYear()->format('Y-m-d');
            }
            $banking->billing_day = $request->billing_day;
            $saved = $banking->save();

            if ($product->has_vehicle) {


                $checkCustomerVehicle = Vehicle::where('policy_id',$policy_id)->exists();
                if($checkCustomerVehicle){
                    $vehicle = Vehicle::where('policy_id',$policy_id)->first();
                }else{
                    $vehicle = new Vehicle();
                }
                $vehicle->customer_id = $user_id;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = $request->vehiclePlate;
                if (htmlspecialchars(strip_tags($request->get('product'))) == 3) {
                    $vehicleData = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('make', 'model', 'manufacturingYear'));
                    if ($vehicleData != null) {
                        $vehicle->make = $vehicleData->make;
                        $vehicle->model = $vehicleData->model;
                        $vehicle->year = $vehicleData->manufacturingYear;
                    }
                    else
                    {
                        $vehicle->make = $request->make;
                        $vehicle->model = $request->model;
                        $vehicle->year = $request->year;
                    }
                } else {
                    $vehicle->make = $request->make;
                    $vehicle->model = $request->model;
                    $vehicle->year = $request->year;
                }
                $vehicle->purpose = $request->purpose;
                $vehicle->is_private = $request->purpose;
                $vehicle->vinnumber = $request->vinnumber;
                $vehicle->engineNo = $request->enginenumber;
                $vehicle->financial_interest = $request->financial_interest;
                $vehicle->financial_interest_other = $request->other_finance;
                $vehicle->claim_count = $request->claim_count;
                $vehicle->is_imported = $importStatus;
                $vehicle->mileage = 'LO';
                $vehicle->condition = 'EX';
                $vehicle->estimated_value = $request->estimated_value;

                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->front = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Front image is invalid'], 401);
                    }
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->back = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Back image is invalid'], 401);
                    }
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->right = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Right image is invalid'], 401);
                    }
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');

                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->left = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Left image is invalid'], 401);
                    }
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    //$result = $this->isImageValid($file);
                    //                        if ($request->hasFile('vehicleRegistration')) {
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->vehicleRegistration = $filePath;
                    //                        } else {
                    //                            return response()->json(['success' => false, 'Message' => 'Vehicle registration image is invalid'], 401);
                    //                        }
                }

                $saved = $vehicle->save();
            }
            DB::commit();
            // documents
            $d = new DocumentController();
            $verificationDoc = $d->generateInformationDocument($policy->id);

            if ($verificationDoc != null) {
                $policy->verification_doc = $verificationDoc;
                $saved = $policy->save();
            }

            if (htmlspecialchars(strip_tags($request->get('product'))) != 3 || $policy->is_bundled == 1) {
                $isGenerated = $d->generatePolicyDocument($policy->id);
                if ($isGenerated != null) {
                    $sent = $d->sendPolicyDocument($policy->id,"Agent");
                }
            }

            $policy->BillingStart = $request->BillingStart;


            $customerConsent = new CustomerConsent();
            $customerConsent->policy_id = $policy->id;
            $customerConsent->customer_id = $policy->customer_id;
            $customerConsent->ip_address = $request->ip();
            $customerConsent->browser_name = $request->header('User-Agent');
            $customerConsent->is_consent_yes = $request->is_consent_yes;
            $customerConsent->is_consent_to_process_yes = $request->is_consent_to_process_yes;
            $customerConsent->save();

            if ($policy->save() && $policy->product_id == 3) {

                // if(isset($policy->policyActivatedDate)) {
                //     $expiry_date = Carbon::parse($policy->policyActivatedDate)->addYear()->subDays(1)->format('Y-m-d');
                // } else {
                //     $expiry_date = Carbon::parse($policy->created_at)->addYear()->subDays(1)->format('Y-m-d');
                // }
                $today = Carbon::today();
                $expiry_date = Carbon::parse($today)->addYear()->subDays(1)->format('Y-m-d');

                $status = 'Deactive';
                // if (isset($expiry_date)) {
                //     if ($expiry_date < Carbon::now()) {
                //         $status = 'Deactive';
                //     } else {
                //         $status = 'Active';
                //     }
                // }

                $policy_data = [
                    'premium'      => $policy->premium,
                    'premium_freq' => $policy->premium_freq
                ];

                $policyCon = new PolicyController();
                $premium = $policyCon->getMotorComprehensivePolicyPremium($policy_data);

                $data = [
                    'policy_id' => $policy->id,
                    'term_start_date' => $today,
                    'term_end_date' => $expiry_date,
                    // 'premium' => $policy->premium,
                    'premium' => $policy->premium,
                    'annual_premium' => isset($premium['annual']) ? round($premium['annual'],2) : null,
                    'vat' => $policy->vat,
                    'vat_percent' => $policy->vat_percent,
                    'renewed_by' => $request->agent_id,
                    'renewals_date' => $expiry_date,
                    'frequency' => $policy->premium_freq,
                    'first_premium' => $policy->first_premium,
                    'billing_start_date' => $policy->billingStartDate,
                    'policy_documents' => NULL,
                    'policyActivatedDate' => $policy->policyActivatedDate,
                    'payment_method' => $request->Payment_method,

                    'payment_reference' => $policy->id,
                    'trans_type' => 'NEW BUSINESS',
                    'status' => $status,
                    'created_at' => Carbon::now()->format('Y-m-d'),

                ];



                // $checkNewBusiness_term_id = PolicyTerm::where('policy_id',$policy_id)->exists();
                // if($checkCustomerVehicle){
                //     $checkNewBusiness_term_id = PolicyTerm::where('policy_id',$policy_id)->exists();
                // }else{
                //     $checkNewBusiness_term_id = PolicyTerm::addPolicyTerm($data);
                // }

                $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
            }
            if($policy->save() && env("APP_STATUS") == 'Production' ){
                $llmapi = new LlmApiCrontroller();
                $llmapi->RegisterCustomer($policy->customer_id,$policy->agent_id);
                $llmapi->SalePolicy($policy->policyNumber);
            }




            $checkPolicyUpgradeMotorcomp = PolicyUpgradeMotorcomp::where('policy_id',$policy_id)->exists();
            if($checkPolicyUpgradeMotorcomp){
                return response()->json(['success' => 0, 'message' => 'Policy Already coverted into motor comporasive'], 401);
            }else{
                $policyUpgradeMotorcomp = new PolicyUpgradeMotorcomp();
                $policyUpgradeMotorcomp->old_product_id = $request->old_product;
                $policyUpgradeMotorcomp->old_product_plan = $request->old_plan_id;
                $policyUpgradeMotorcomp->new_porduct_plan = $request->plan_id;
                $policyUpgradeMotorcomp->new_product_id = $request->product;
                $policyUpgradeMotorcomp->updated_date = date('Y-m-d');
                $policyUpgradeMotorcomp->old_premium = $request->old_premium;
                $policyUpgradeMotorcomp->updated_by = $request->agent_id;
                $policyUpgradeMotorcomp->policy_id = $request->policy_id;
                $policyUpgradeMotorcomp->status = '0';
                $policyUpgradeMotorcomp->old_payment_type = $old_payment_method;
                $policyUpgradeMotorcomp->new_payment_type = $request->Payment_method;
                $policyUpgradeMotorcomp->new_premium =  $request->premium;
                $policyUpgradeMotorcomp->save();
             }


             if ($policy->customer_id && $policy->product_id == 3) {
                $kyc = KYC::where('customer_id', $policy->customer_id)->first();

                $hasOtherMotorPolicies = Policy::where('customer_id', $policy->customer_id)
                                               ->where('product_id', 3)
                                               ->where('id', '!=', $policy->id)
                                               ->exists();

                if (!$hasOtherMotorPolicies && $kyc) {
                    $kyc->compliance = 0;
                    $kyc->save();
                }
            }

            DB::commit();

            $request['amount'] = $request->premium;



            switch ($request->Payment_method) {
                // case 'VCS':
                //     $vcs = new PaymentController;
                //     //For Motor Comprehensive
                //     if ($request->get('product') == 3 ){
                //         $t =  $vcs->handlePaymentForQuoteVariable($policy->policyNumber, $policy->leadSource);
                //         break;
                //     } else {
                //         return $vcs->handlePaymentForActivationCodeGenerated($policy->policyNumber, 'LiveQuote');
                //         break;
                //     }
                case 'Flutterwave':
                    $rave = new FlutterwaveController;
                    return $rave->handlePayment($policy->policyNumber, $product_plan->slug, $product_plan->flutter_plan_id);
                    PolicyUpgradeMotorcomp::where('policy_id', $policy->id)->update(['status' => '1']);
                    break;
                case 'PayM8':
                    $paym8 = new PayM8Controller();
                    $addEvent = $paym8->createAdHocPayment($policy->id);
                    return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                    break;
                case 'DPO':
                    if(isset($request->pay_email) && $request->pay_email != null){
                        $payemail               = new PaymentEmail();
                        $payemail->policyNumber =  $policy->policyNumber;
                        $payemail->pay_email =  $request->pay_email;
                        $payemail->save();
                        }
                    // return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber, 'product_id' => $policy->product_id], 200);
                        $dpo = new DpoPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;

                        if($request->plan_id == 8 && $request->product == 3){
                          $dpoReturn = $dpo->findPolicyForOnlinePaymentMotorComp($request);
                        }else{
                          $dpoReturn = $dpo->findPolicyForOnlinePayment($request);
                        }
                        PolicyUpgradeMotorcomp::where('policy_id', $policy->id)->update(['status' => '1']);
                        return $dpoReturn;
                    break;
                 case 'N-Genius':

                       $ngenius = new NgeniusPaymentController();
                       $cancel =  $ngenius->NgeniusRecurringDeletedata2($policy->policyNumber);
                        $Ngenius = new NgeniusPaymentController;

                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource = $policy->leadSource;
                        $NgeniusReturn = $Ngenius->NgeniusPayment($request);
                        PolicyUpgradeMotorcomp::where('policy_id', $policy->id)->update(['status' => '1']);
                        return $NgeniusReturn;
                    break;
                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                    PolicyUpgradeMotorcomp::where('policy_id', $policy->id)->update(['status' => '1']);
                    break;
                case 'orangeMoney':
                    $orange = new OrangeMoneyController();
                    $log = $orange->addSchedule($policy->policyNumber);
                    PolicyUpgradeMotorcomp::where('policy_id', $policy->id)->update(['status' => '1']);
                    return response()->json(['status' => '200', 'message' => 'Policy Created Successfully! Please complete the payment through Orange Money USSD', 'PolicyNumber' => $policy->policyNumber], 200);
                    break;
                case 'RealPay':

                        if($old_payment_method == 'RealPay')
                        {
                            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            $request->policyID = $policy->id;
                            $clientNumber = $realpay->cancelOldcreateNewContract($request);
                        }
                        else
                        {
                            $addEvent = $this->realpayPayment($policy);

                        }
                        PolicyUpgradeMotorcomp::where('policy_id', $policy->id)->update(['status' => '1']);



                case 'cash':
                    return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber], 200);
                    // case 'Cash':
                    //     return $this->storeCashPayment($policy);
                    break;
                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->all());
            }

            return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber], 200);
        } catch (Exception $ex) {
            DB::rollback();
            return response()->json(['success' => 0, 'message' => $ex->getMessage()], 401);
        }
    }

    public function getpolicyData(Request $request)
    {
        $policyNumber = base64_decode($request->policyNumber);
        $policy = MotorGetPolicyDetail::join('customer', 'policies.customer_id', '=', 'customer.id')
        ->join('customer_profile', 'customer.id', '=', 'customer_profile.customer_id')
        ->select('firstName', 'lastName','cellphone','email','gender','maritalstatus','dob',
        'omang','passport','customer.id as customer_id','policies.id as policy_id','plan_id',
        'premium','agent_id','product_id')
        ->where('policyNumber',$policyNumber)
        ->whereIn('policies.status',['0','1'])
        ->where('policies.product_id',2)
        ->first();

        if($policy != '')
        {
            return response()->json(['success' => 1, 'policyData' => $policy], 200);
        }
        else
        {
            return response()->json(['success' => 1, 'policyData' => ''], 200);

        }

    }

    public function getcustVechilInfo(Request $request)
    {
        $customerId = base64_decode($request->customer_id);
        $policy = CustomerProfile::join('vehicle', 'vehicle.customer_id', '=', 'customer_profile.customer_id')
        ->select('*')
        ->where('vehicle.customer_id',$customerId)
        ->first();
        if($policy != '')
        {
            return response()->json(['success' => 1, 'policyData' => $policy], 200);
        }
        else
        {
            return response()->json(['status' => "error",'message' => "These policy is not available. Please enter valid policy number."],400);
        }

    }
    public function cellphoneUpgrade(Request $request)
    {

        if($policy = Policy::where('policyNumber',$request->policyNumber)->where('product_id',5)->where('plan_id',17)->where('status',1)->exists()){
            return response()->json(['status' => "error",'message' => "Policy already Active with cellphone Plan P99"], 400);
        }
        $policy = Policy::where('policyNumber',$request->policyNumber)->where('product_id',5)->where('plan_id','!=',17)->where('status',1)->first();

          if($policy != null){
            $banking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id','desc')->first();
            $banking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id','desc')->first();
            if ($banking != null && $banking->billing != $request->get('payment_method')) {
                return response()->json([
                    'status' => "error",
                    'message' => "Customer payment method will be same as previous payment method"
                ], 400);
            }
           // if($banking != null && ($banking->billing == "DPO" || $banking->billing == "N-Genius" || $banking->billing == "RealPay")){
                    $policyUpgrade = new PolicyUpgrade();
                    $policyUpgrade->policy_id = $policy->id;
                    $policyUpgrade->product_id = $policy->product_id;
                    $policyUpgrade->old_plan_id = $policy->plan_id;
                    $policyUpgrade->save();
                    $planId=17;
                    $plans = Productplan::where('id', $planId)->first(array('id', 'product_id', 'name', 'slug', 'premium', 'sum_assured'));
                    $product = Product::where('id', $plans->product_id)->first(array('region_id', 'premium_type_id'));
                    $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                    if ($product->premium_type_id == 11) {
                        $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
                    }
                $product = Product::where('id',5)->first();
                $policy->plan_id = 17;
                $policy->sum_assured = $plans->sum_assured;
                $policy->premium = $premium;
                $policy->vat = round( $premium* ($regionVat / 100), 2);
                $policy->vat_percent = $regionVat;
                $policy->save();
                $document = new DocumentController();
                $generate = $document->generatePolicyDocument($policy->id);
                $document->sendPolicyDocument($policy->id);

                $policyUpgrade->new_plan_id = 17;
                $policyUpgrade->status = 0;
                $policyUpgrade->agent_id =$request->agentId ;
                $policyUpgrade->save();
                $policyUpgrade->id;
                event(new \AlphaDirect\Events\policyLifecycle($policy->id, "Create"));

                // $policyController = new PolicyController();
                // $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                $old_payment_method = '';

                $checkCustomerBanking = CustomerBanking::where('policy_id', $policy->id)->exists();

                if($checkCustomerBanking){

                    $banking = CustomerBanking::where('policy_id',$policy->id)->first();

                    $old_payment_method = $banking->billing;

                }else{

                    $banking = new CustomerBanking();

                }
                $customer = Customer::where('id',$policy->customer_id)->first();
                $banking->customer_id = $policy->customer_id;

                $banking->policy_id = $policy->id;

                $banking->billing = $request->get('payment_method');

                $banking->billingCell = $customer->cellphone;



                if ($request->get('payment_method') == 'RealPay') {

                    $banking->bankName = $request->get('bankName');

                    $banking->branchCode = $request->get('branchCode');

                    $banking->accountNumber = $request->get('accountNumber');

                    $banking->accountType = $request->get('accountType');

                }
                if ($request->get('Payment_method') == 'PayM8') {

                    $banking->branchCode = $request->get('branchCode');
                    $banking->accountNumber = $request->get('accountNumber');
                    $banking->accountType = $request->get('accountType');
                }
                $banking->save();
                $post = PolicyUpgrade::find($policyUpgrade->id);
                $post->status = "1";
                $post->old_payment_type = $old_payment_method;
                $post->new_payment_type = $request->payment_method;
                $post->save();

            if($request->payment_method!=''){
                switch ($request->payment_method) {

                    case 'DPO':
                        // $pl = new PolicyController();
                        // $request->searchValue = $policy->policyNumber;
                        // $request->email = $request->dpo_email;
                        // $request->billing_date=  date("d/m/Y");
                        // $request->leadSource = "start.alphadirect.co.bw";

                        // $dpourl=$pl->updateExpiredCardForDPO($request);
                        // return $dpourl;
                        $schudule = ScheduleTransaction::where('policy_number',$policy->policyNumber)->whereIn('status',[0,1])->update(['premium'=>$policy->premium]);
                        return response()->json(['status' => "success",'message' => "cellphone P49 to cellPhone 99 upgraded Successfully"],200);
                        break;
                    case 'PayM8':

                            $policyController = new PolicyController();
                            $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
                            $paym8 = new PayM8Controller();
                            $addEvent = $paym8->createAdHocPayment($policy->id);
                            return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                            break;

                   case 'N-Genius':
                        $ng = new NgeniusPaymentController();
                        $policyController = new PolicyController();
                        $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
                        if($cancelPayment == 1){
                          $policy->status = 5;
                          $policy->save();
                        }
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource = "start.alphadirect.co.bw";
                        return $ng->NgeniusPayment($request);

                        break;
                    case 'RealPay':

                        //$realpay = new PolicyController();
                        $request->billingDay =  date("d/m/Y");
                        $request->policyID=$policy->id;
                        $request->leadSource = "start.alphadirect.co.bw";
                        //$cancelPayment = $realpay->CancelPaymentsForPolicy($policy);



                        if($old_payment_method == 'RealPay')
                        {
                            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            $request->policyID = $policy->id;
                            $clientNumber = $realpay->cancelOldcreateNewContract($request);
                        }
                        else
                        {
                            $clientNumber = $this->realpayPayment($policy);

                        }

                       return $clientNumber;
                     break;
                    default:
                        $policy->status = 5;
                        $policy->save();
                        $url = env("START_URL").'redopayment';
                        return response()->json(['status' => "success",'message' => "updated Please make payment" ,'url'=>$url],200);
                        break;
                }


            }else{
                $url = env("START_URL").'redopayment';
             return response()->json(['status' => "success",'message' => "updated" ,'url'=>$url],200);
            }

                return response()->json(['status' => "error",'message' => "Error"],200);
          }else{

            return response()->json(['status' => "error",'message' => "Policy Not Found Or Not Active"],400);
          }

    }
    public function test()
    {
        $urls = [
            'https://devquote.alphadirect.co.bw/index.php',
            'https://devquote.alphadirect.co.bw/index.php',
            'https://devquote.alphadirect.co.bw/index.php'
        ];
        foreach ($urls as $url) {

            $ch = curl_init();


            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);


            $response = curl_exec($ch);


            if (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
                echo "Timeout occurred for URL: $url\n";
                curl_close($ch);
                continue;
            } elseif (curl_errno($ch)) {
                echo "cURL error: " . curl_error($ch) . " for URL: $url\n";
                curl_close($ch);
                continue;
            }


            echo "Response for URL: $url\n";
            echo $response;


            curl_close($ch);
        }
    }




}
