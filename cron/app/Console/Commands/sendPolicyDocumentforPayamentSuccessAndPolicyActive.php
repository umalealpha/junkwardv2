<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\sentPolicyDocuments;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;

class sendPolicyDocumentforPayamentSuccessAndPolicyActive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendpolicydocumentforpayamentsuccess:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sendPolicyDocumentforPayamentSuccess';

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
    /**
     * Core MIS retail product line this cron covers: Accidental Death (1),
     * Third Party Car (2), Legal (4), Mobile & Electronic Device (5),
     * Hospital Cashback (9). Previously this was a whereNotIn([3,7,8]) —
     * an exclusion list that pre-dated several product launches and would
     * silently sweep in any new product (e.g. it never excluded 16/17/18/20/22,
     * V2's COMG/DOMG-prefixed Engineering/Liabilities/Marine lines, which
     * have their own document pipeline and would risk a duplicate send).
     * Motor Comprehensive (3) is deliberately excluded — it already has its
     * own dedicated cron subsystem (ExpiredMotorCompPolicies,
     * autoRenewMotorCompExpiredPolicies, etc.) for document handling.
     */
    private const MIS_PRODUCT_IDS = [1, 2, 4, 5, 9];

    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "sendpolicydocumentforpayamentsuccess:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        try {
            $policyController = new PolicyController();
            $paymentT = PaymentTransaction::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->whereIn('status',['1','success','SUCCESS','Success'])->get();
            if(count($paymentT) > 0){
                foreach($paymentT as $payment){
                    $policy = Policy::where('policyNumber',$payment->policyNumber)
                    ->where('status', 1)->whereIn('product_id', self::MIS_PRODUCT_IDS)->first();

                    if($policy){

                      $totaltrxn =  PaymentTransaction::whereIn('status',['1','success','SUCCESS','Success'])->where('policyNumber',$policy->policyNumber)->get();
                        if(count($totaltrxn) == 1){

                                try{
                                    if (!sentPolicyDocuments::where('policyNumber', $policy->policyNumber)->where('doc','Policy Document')->exists()) {
                                        $d = new DocumentController();
                                        $isGenerated = $d->generatePolicyDocument($policy->id);
                                        $policyController->sendPolicyDocument($policy->id, "System");
                                    }
                                }catch(\Throwable $ex){
                                    \Log::error('sendpolicydocumentforpayamentsuccess: failed for ' . $policy->policyNumber . ': ' . $ex->getMessage());
                                }

                        }
                    }

                }

            }
        } catch (\Throwable $e) {
            // \Throwable (not \Exception) — see PolicyLedgerDaily's identical
            // fix. Without this, a fatal error here leaves cron_status.end
            // NULL forever, showing as a permanently "Running" cron in the
            // portal even though the process is long dead.
            \Log::error('sendpolicydocumentforpayamentsuccess: cron failed: ' . $e->getMessage());
        } finally {
            $cron->end = \Carbon\Carbon::now();
            $cron->save();
        }
    }
}
