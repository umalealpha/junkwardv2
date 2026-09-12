<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use Illuminate\Console\Command;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\PayMSchuduleTransection;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\CustomerBanking;
use AlphaDirect\PolicyTerm;
use Carbon\Carbon;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Helper;

class Paym8Transaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paymtransaction:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process PayM8 transactions and update payment status';

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
     * @return int
     */
    public function handle()
    {
       
        try {
            $cron = new CronStatus();
            $cron->name  = "paymtransaction:cron";
            $cron->start = \Carbon\Carbon::now();
            $cron->save();
            
            $startDate = now()->subDays(7)->format('Y-m-d H:i:s');
            $endDate = now()->format('Y-m-d H:i:s');
            $url = config('services.paym8.url').'PaymentsService/api/V1/transactions/Search/';
            $authorization = 'Basic ' . config('services.paym8.key');

            $postData = json_encode([
                "searchFilterArgs" => [
                    "startDate" => $startDate,
                    "endDate" => $endDate,
                    "merchantName" => null,
                    "branchName" => "Alpha Direct Insurance",
                    "merchantReference" => null,
                    "transactionOutcome" => null,
                    "responseCode" => null,
                    "systemReference" => null,
                    "operator" => "None",
                    "operatorToString" => "None",
                    "amount" => null,
                    "amountFrom" => null,
                    "amountTo" => null,
                    "recurringPaymentsFilters" => null,
                    "cardSearchFilters" => null,
                    "directBankFilters" => null,
                    "isAdvanced" => true,
                    "searchTerm" => null
                ],
                "pageListArgs" => [
                    "pageNumber" => 1,
                    "pageSize" => 500
                ]
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $authorization
                ],
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($error) {
                Log::error('PayM8 cURL Error: ' . $error);
                $this->error('cURL Error: ' . $error);
                return 1;
            }

            if ($httpCode !== 200) {
                Log::error('PayM8 API Error: HTTP Code ' . $httpCode . ' Response: ' . $response);
                $this->error('API Error: HTTP Code ' . $httpCode);
                return 1;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('PayM8 JSON Decode Error: ' . json_last_error_msg());
                $this->error('JSON Decode Error: ' . json_last_error_msg());
                return 1;
            }

            if (!isset($data['data']['data'])) {
                Log::info('PayM8: No transaction data found');
                $this->info('No transaction data found');
                return 0;
            }

            $processedCount = 0;
            foreach ($data['data']['data'] as $transaction) {
                try {
                    if (PaymentTransaction::where('referenceNumber', $transaction['installmentId'])->exists()) {
                        $paymentTransaction = PaymentTransaction::where('referenceNumber', $transaction['installmentId'])
                            ->whereIn('status', ['PROCESSING', 'FAILED'])
                            ->first();
                        
                        if ($paymentTransaction) {
                            $paymentTransaction->status = match ($transaction['status']) {
                                4 => 'SUCCESS',
                                7 => 'SUCCESS',
                                3 => 'PROCESSING',
                                default => 'FAILED',
                            };
                            
                            if ($transaction['status'] == 4 || $transaction['status'] == 7) {
                                // Convert .NET Date format to Carbon instance
                                $paymentTransaction->paymentDate = $this->parseDotNetDate($transaction['whenUpdated'] ?? null);
                            }
                            
                            $paymentTransaction->save();
                            if($transaction['status'] == 4 || $transaction['status'] == 7){
                                $policy = Policy::where('policyNumber', $transaction['policyNumber'])->first();
                                if($policy->status == 0){
                                    $this->updatePolicyStatus($policy);
                                }
                            }
                            $processedCount++;
                        }
                    } else {
                        $paymschtr = PayMSchuduleTransection::where('installmentid', $transaction['installmentId'])->first();
                        
                        if ($paymschtr) {
                            $policy = Policy::where('policyNumber', $paymschtr->policyNumber)->first();
                            
                            if (!$policy) {
                                Log::warning('Policy not found for policy number: ' . $paymschtr->policyNumber);
                                continue;
                            }
                            
                            $paymentTransaction = new PaymentTransaction();
                            $paymentTransaction->policyNumber = $paymschtr->policyNumber;
                            $paymentTransaction->policy_id = $policy->id;
                            $paymentTransaction->referenceNumber = $transaction['installmentId'] ?? null;
                            $paymentTransaction->amount = $paymschtr->premium;
                            $paymentTransaction->status = match ($transaction['status']) {
                                4 => 'SUCCESS',
                                7 => 'SUCCESS',
                                3 => 'PROCESSING',
                                default => 'FAILED',
                            };
                            
                            $paymentTransaction->paymentDate = $this->parseDotNetDate($transaction['whenUpdated'] ?? null);
                            $paymentTransaction->paymentMethod = 'PayM8';
                            $paymentTransaction->numberOfInstalmentsPaid = $paymschtr->installment;
                            $paymentTransaction->paymentFrequency = $policy->premium_freq;
                            $paymentTransaction->TransID = $transaction['id'];
                            $paymentTransaction->note = $transaction['outcomeCode'] ?? null;
                            $paymentTransaction->reason = $transaction['outcomeDescription'] ?? null;

                            $paymentTransaction->save();
                            if($transaction['status'] == 4 || $transaction['status'] == 7){
                                if($policy->status == 0){
                                    $this->updatePolicyStatus($policy);
                                }
                            }

                            $processedCount++;
                            $cron->processedCount = $processedCount;
                            $cron->save();
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing transaction ' . ($transaction['installmentId'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
            
            // Update cron status after processing all transactions
           
            $cron->end = \Carbon\Carbon::now();
           
            $cron->save();
            
            $this->info("Successfully processed {$processedCount} transactions");
            Log::info("PayM8 Transaction processing completed. Processed: {$processedCount} transactions");
            
            return 0;
            
        } catch (\Exception $e) {
            // Update cron status on error
           
            
            Log::error('PayM8 Transaction Command Error: ' . $e->getMessage());
            $this->error('Command Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Parse .NET Date format to Carbon instance
     * 
     * @param string|null $dotNetDate
     * @return \Carbon\Carbon|null
     */
    private function parseDotNetDate($dotNetDate)
    {
        if (!$dotNetDate) {
            return \Carbon\Carbon::now();
        }

        // Parse .NET Date format: "/Date(1753207201353+0200)/"
        if (preg_match('/\/Date\((\d+)([+-]\d{4})?\)\//', $dotNetDate, $matches)) {
            $timestamp = (int) $matches[1] / 1000; // Convert milliseconds to seconds
            $timezone = isset($matches[2]) ? $matches[2] : '+0000';
            
            // Create Carbon instance with the timestamp and timezone
            return \Carbon\Carbon::createFromTimestamp($timestamp, $timezone);
        }

        // Fallback to current time if parsing fails
        return \Carbon\Carbon::now();
    }
    private function updatePolicyStatus($policy)
    {
        $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
        $customerBanking =  CustomerBanking::updateOrCreate([
            'policy_id'        => $policy->id,
        ], [
            'billing'          => 'PayM8',
            'billingCell'      => $policy->customer->cellphone,
            'billingStartDate' => $policy->billingStartDate,
            'billing_day'      => $policy->billing_day
        ]);
        $action = $policyController->action($policy->id, 1, 'PayM8'); //set the Policy status to active
      
       
            $policy['billingStart'] = isset($billingStart) ? $billingStart : null;
    

            $policyUpdate = Policy::where('policyNumber', $policy->policyNumber)->first();
            $term = PolicyTerm::where('policy_id',$policyUpdate->id)->where('trans_type','NEW BUSINESS')->where('status','Deactive')->where('term_end_date','>',Carbon::now()->format('Y-m-d'))->orderBy('id','desc')->first();
            if (isset($term)) {
                $term->status = 'Active';
                $term->save();
            }
           Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');
       
    }
}
