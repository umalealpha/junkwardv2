<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyStatusLogs;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;

class CancelHospitalCashbackAge65Plus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelhospitalcashbackage65plus:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel policies for hospital cashback (product_id=9) if policy holder age >= 72 years (COVER CEASE AGE)';

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
        $cron->name = "cancelhospitalcashbackage65plus:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started: cancelhospitalcashbackage65plus:cron');

        try {
            // Query policies where product_id = 9, status = 1 (active)
            // Check policy holder (Main Member) age >= 72 years (COVER CEASE AGE)
            $policies = Policy::leftJoin('customer_profile', 'customer_profile.customer_id', 'policies.customer_id')
                ->leftJoin('customer', 'customer.id', 'policies.customer_id')
                ->where('policies.product_id', 9)
                ->where('policies.status', 1)
                // ->whereDate('policies.created_at', '>=', '2025-12-18')
                ->where(function($query) {
                    // Policy holder (Main Member/customer) age >= 72 years
                    $query->where('customer_profile.dob', '!=', null)
                          ->where(DB::raw("(DATE_FORMAT(customer_profile.dob,'%Y-%m-%d'))"), '<=', Carbon::parse('today')->subYear(72)->format('Y-m-d'));
                })
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('cancel_policies')
                        ->whereColumn('cancel_policies.policyNumber', 'policies.policyNumber');
                })
                ->select(
                    'policies.id as policy_id',
                    'policies.policyNumber',
                    'policies.customer_id',
                    'policies.status',
                    'customer.firstName',
                    'customer.lastName',
                    'customer.cellphone',
                    'customer.email',
                    'customer_profile.dob'
                )
                // ->limit(5)
                ->get();
                
            Log::info('Found ' . $policies->count() . ' policies to check for cancellation');
            //   dd($policies);      
            if ($policies->count() > 0) {
                foreach ($policies as $policyData) {
                    try {
                        $policy = Policy::where('id', $policyData->policy_id)->first();

                        if (!$policy || $policy->status == 2) {
                            Log::info('Policy ' . $policyData->policyNumber . ' already cancelled or not found');
                            continue;
                        }

                        $customer = Customer::where('id', $policyData->customer_id)->first();

                        if (!$customer) {
                            Log::warning('Customer not found for policy ' . $policyData->policyNumber);
                            continue;
                        }

                        // Check premium payment status
                        $ledgerBalance = Ledger::where('policy_id', $policy->id)
                            ->orderBy('id', 'DESC')
                            ->first(['balance']);
                        
                        $outstandingBalance = 0;
                        if ($ledgerBalance && $ledgerBalance->balance != null) {
                            $outstandingBalance = $ledgerBalance->balance;
                        }
                        $premiumsPaid = ($outstandingBalance <= 0);
                        
                        // Check policy holder (Main Member) age limit
                        $ageExceeds = false;
                        $cancellationReason = '';
                        
                        // Check Policy Holder (Main Member/customer) age >= 72
                        if ($policyData->dob != null) {
                            $customerAge = Carbon::parse($policyData->dob)->age;
                            if ($customerAge >= 72) {
                                $ageExceeds = true;
                                $cancellationReason = 'Policy holder age >= 72 years (COVER CEASE AGE) - Automatic cancellation';
                            }
                        }
                        
                        // Apply cancellation logic:
                        // 1. Policy remains active if premiums are paid AND age limit does not exceed
                        // 2. Cancel if age limits exceed (regardless of payment status)
                        // 3. Cancel if premiums are not paid (regardless of age)
                        $shouldCancel = false;
                        
                        if ($ageExceeds) {
                            // Scenario 2: Age exceeds - cancel regardless of payment status
                            $shouldCancel = true;
                            if ($premiumsPaid) {
                                $cancellationReason .= ' (Premiums are paid but age limit exceeded)';
                            }
                        } elseif (!$premiumsPaid) {
                            // Scenario 3: Premiums not paid - cancel even if age is within limits
                            $shouldCancel = true;
                            $cancellationReason = 'Outstanding premium balance (P' . number_format($outstandingBalance, 2) . ') - Automatic cancellation';
                        }
                        // Scenario 1: Premiums paid AND age within limits - policy remains active (shouldCancel = false)
                        
                        if (!$shouldCancel) {
                            Log::info('Policy ' . $policyData->policyNumber . ' remains active - Premiums paid and age within limits');
                            continue;
                        }

                        // Handle payment gateway cancellation (VCS/RealPay/DPO)
                        sleep(1);
                        
                        // Check customer_banking table first for billing method
                        $banking = CustomerBanking::where('policy_id', $policy->id)
                            ->orderBy('id', 'desc')
                            ->first(['billing']);
                        
                        $paymentMethod = "VCS"; // Default to VCS
                        
                        if ($banking && isset($banking->billing) && !empty($banking->billing)) {
                            $paymentMethod = $banking->billing;
                        } else {
                            // Fallback to PaymentTransaction if customer_banking doesn't have billing
                            $trans = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                                ->orderBy('id','desc')
                                ->first(['paymentMethod']);
                            
                            if ($trans && isset($trans->paymentMethod) && !empty($trans->paymentMethod)) {
                                $paymentMethod = $trans->paymentMethod;
                            }
                        }
                        
                        switch ($paymentMethod) {
                            case 'RealPay':
                                $realpay = RealpayPaymentRequest::where('policy_id', $policy->id)
                                    ->orderBy('id', 'desc')
                                    ->first();

                                // if ($realpay && $realpay->status == 1) {
                                    $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                    $log->cancelRealpayContract($policy->id);
                                    $log->cancelRealpayContractsForInstProduct($policy->id);
                                    $addLog = $log->logEvent($policy->id, 2);

                                    if ($addLog) {
                                        $request = new RealpayCancelRequests();
                                        $request->policy_id = $policy->id;
                                        $request->leftout_premium_contract = null;
                                        $request->contract = $realpay->contract;
                                        $request->cancel_status = 0;
                                        $request->save();
                                    }
                                // }
                                break;
                            case 'VCS':
                                $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)
                                    ->orderBy('id', 'desc')
                                    ->first();
                                if (isset($transctionsRow->referenceNumber) && !empty($transctionsRow->referenceNumber)) {
                                    $referenceNumber = $transctionsRow->referenceNumber;
                                    $vcs = new PaymentController;
                                    $vcs->suspendTransactionOnVCS($referenceNumber);
                                }
                                break;
                            case 'DPO':
                                $dpoCon = new DpoPaymentController();
                                $cancelContract = $dpoCon->CancelContractForDpoPolicy($policy);
                                Log::info('DPO contract cancellation initiated for policy: ' . $policy->policyNumber);
                                break;
                            default:
                                break;
                        }

                        // Cancel the policy
                        $policy->status = 2;
                        $policy->save();

                        // Update policy dates if needed
                        if (!PolicyStatusLogs::where('policyNumber', $policy->policyNumber)
                            ->whereNotNull('cancelled_date')
                            ->exists()) {
                            $pc = new PolicyController();
                            $pc->updatePolicyDates($policy->policyNumber, 2);
                        }

                        // Create customer feedback record
                        $feedback = new CustomerFeedback();
                        $feedback->policy_id = $policy->id;
                        $feedback->customer_id = $policy->customer_id;
                        $feedback->product_id = $policy->product_id;
                        $feedback->reason = $cancellationReason;
                        $feedback->save();

                        // // Send SMS
                        // if ($customer->cellphone != null) {
                        //     $sms = new SmsMessaging();
                        //     $sms->SendSMSEmailPolicyCancelled($customer->cellphone, $customer->firstName, $policy->policyNumber);
                        //     Log::info('SMS sent for policy cancellation: ' . $policy->policyNumber);
                        // } else {
                        //     Log::warning('No cellphone number for customer ' . $customer->id . ' - policy ' . $policy->policyNumber);
                        // }

                        // // Send Email
                        // if ($customer->email != null) {
                        //     $data = new \stdClass();
                        //     $data->user_id = null;
                        //     $data->policy_id = $policy->id;
                        //     $data->customer_id = $customer->id;
                        //     $data->hook = 'cancel_policy';
                        //     $data->attachment = null;
                        //     $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                            
                        //     if ($emailTemplate) {
                        //         $markdown = new MailTemplate($data);
                        //         $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                        //         event(new \AlphaDirect\Events\SendMail($customer->email, $emailTemplate->subject, "", $html, null, ['policyNumber' => $policy->policyNumber, 'hook' => $data->hook]));
                        //         Log::info('Email sent for policy cancellation: ' . $policy->policyNumber);
                        //     } else {
                        //         Log::warning('Email template not found for hook: cancel_policy');
                        //     }
                        // } else {
                        //     Log::warning('No email address for customer ' . $customer->id . ' - policy ' . $policy->policyNumber);
                        // }

                        CancelPolicy::updateOrCreate(
                            ['policyNumber' => $policy->policyNumber],
                            [
                                'reason'   => $cancellationReason,
                                'status'   => 1,
                                'sms_sent' => 1, // SMS dispatched above
                            ]
                        );

                        Log::info('Policy cancelled successfully: ' . $policy->policyNumber);

                    } catch (\Exception $ex) {
                        Log::error('Error cancelling policy ' . $policyData->policyNumber . ': ' . $ex->getMessage() . ' - Line: ' . $ex->getLine());
                        continue;
                    }
                }
            } else {
                Log::info('No policies found to cancel');
            }

        } catch (\Exception $e) {
            Log::error('Error in cancelhospitalcashbackage65plus:cron: ' . $e->getMessage() . ' - Line: ' . $e->getLine());
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Completed: cancelhospitalcashbackage65plus:cron');
        
        return 1;
    }
}

