<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerProfile;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\QuotesForPromotion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Log;
use AlphaDirect\ReratedPremiumQuote;
use AlphaDirect\Models\CronStatus;

class RerateWithExistingQuoteInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ratewithexistingquoteinfo:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rate with existing quote info';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "ratewithexistingquoteinfo:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $promoQuotes = QuotesForPromotion::where('is_rerated',0)->get();
        if(count($promoQuotes) != 0){
            Log::info('Cron Started to re_rate quotes');
            foreach($promoQuotes as $key=>$quoteData) {
                $quote = MotorComprehensiveQuotes::where('quoteNumber',$quoteData->quoteNumber)->first();
                if($quote != null && $quote->status == 1){
                    $profile = CustomerProfile::where('customer_id',$quote->customer_id)->first();
                    $check = new CustomerController();
                    $getCount = $check->checkCustomerQuotes($quote->customer_id);
                    if($getCount == 0){
                        if($profile != null){
                            $year = $quote->manufacturingYear;
                            $make = $quote->make;
                            $dob = Carbon::createFromFormat('Y-m-d', $profile->dob)->format('d/m/Y');
                            $sum_insured = $quote->estimatedValue;
                            $status = $quote->is_imported;
                            $marital_status = ($profile->maritalstatus == 1 || $profile->maritalstatus == 5) ? 'Never Married' : 'Married Before';
                            $claim_count = $quote->priorAccidents;
                            $gender = ($profile->gender == 1) ? 'Male' : 'Female';;

                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => env('RATINGS_URL').'calculation',
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_POSTFIELDS => '{
                            "make":"'.$make.'",
                            "manufacturing_year":"'.$year.'",
                            "dob":"'.$dob.'",
                            "sum_insured":"'.$sum_insured.'",
                            "status":"'.$status.'",
                            "marital_status":"'.$marital_status.'",
                            "claim_count":"'.$claim_count.'",
                            "gender":"'.$gender.'"
                            }',
                                CURLOPT_HTTPHEADER => array(
                                    'Content-Type: application/json',
                                    'Cookie: __cfduid=d52d1be0186f72c5ab914ad142d22ba941620644343'
                                ),
                            ));

                            $response = curl_exec($curl);

                            curl_close($curl);
                            $res = json_decode($response, true);
                            if($res != null && $res['success'] == 1){
                                $update = MotorComprehensiveQuotes::where('quoteNumber',$quoteData->quoteNumber)->first();
                                $update->ratings_id = $res['rate_id'];
                                $update->premiumMonthly = $res['monthly_premium_vat'];
                                $update->premium3Inst = $res['threemonthly_preminum_vat'];
                                $update->premiumAnnually = $res['result'];
                                $update->save();

                                $updatePromo = QuotesForPromotion::where('quoteNumber',$quoteData->quoteNumber)->first();
                                $updatePromo->is_rerated = 1 ;
                                $updatePromo->save();

                                $add = new ReratedPremiumQuote();
                                $add->quote_number = $quote->quoteNumber;
                                $add->rate_id = $res['rate_id'];
                                $add->old_value = $quote->premiumAnnually;
                                $add->new_value = $res['result'];
                                $add->reason = 'promotional Rerating';
                                $add->save();
                            }
                        }
                    }
                }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
