<?php

namespace AlphaDirect\Providers;
use AlphaDirect\Models\HelpDeskTicket;
use AlphaDirect\Models\LocalPersonalAccessToken;
use AlphaDirect\Observers\PolicyObserver;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Repositories\PolicyRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PolicyRepository::class);

        // SHARED WITHIN A PROCESS, REBUILT WITH THE CONTAINER. The FAC period
        // lock runs on every FacPlacement save, so it memoises how far the
        // register is closed rather than re-querying per row — a bulk import
        // saves thousands in one process. A singleton keeps that memo for the
        // request and lets the test suite start each test with a clean one.
        $this->app->singleton(\AlphaDirect\Services\Reinsurance\FacPeriodLock::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Global helpers loaded via composer autoload (app/helpers.php)
        // Trust AWS ALB reverse-proxy headers so generated URLs use HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
            \Illuminate\Http\Request::setTrustedProxies(
                ['*'],
                \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            );
        }

        // Route Sanctum token writes to local SQLite so no writes reach the read-only RDS
        Sanctum::usePersonalAccessTokenModel(LocalPersonalAccessToken::class);

        // Register model observer for cache invalidation
        Policy::observe(PolicyObserver::class);

        // Help Desk SLA engine — reacts to ticket create + status changes.
        // No-ops while config('help_desk.sla.enabled') is false (the default),
        // so this is inert until the SLA feature is switched on.
        HelpDeskTicket::observe(\AlphaDirect\Observers\HelpDeskTicketObserver::class);

        // Audit every change to customer.email so we can trace DPO wrong-
        // customer debit incidents back to a data-entry event. Failures in
        // the observer must not block the save — it catches everything.
        \AlphaDirect\Customer::observe(\AlphaDirect\Observers\CustomerEmailAuditObserver::class);

        // Custom Blade directive to disable autocomplete
        Blade::directive('noautocomplete', function () {
            return 'autocomplete="off"';
        });
        
        PaymentTransaction::created(function ($payment) {
            if($payment->policyNumber != null){
                if($payment->status == "SUCCESS" || $payment->status =="Success")
                    $status = 1;
                else
                    $status = 0;

                if($payment->amount == 1)
                    $type = 7 ; //Commission on Instant Premium for 1 Pula
                else {
                    $paymentCount = PaymentTransaction::where('policyNumber',$payment->policyNumber)->count();
                    if($paymentCount <= 1)
                        $type = 6; //Commission on Registration of Payment method
                    else
                        $type = 8; //Commission on Subsequent Collection
                }

                $policy = Policy::where('policyNumber',$payment->policyNumber)->first();
                if($policy != NULL)
                {
                    if($policy->agent_id != null){
                        $amount = $payment->amount;
                        event(new \Modules\Incentive\Events\AddIncentive($policy,$type,$status,$amount));
                    }
                    event(new \Modules\Cashback\Events\CustomerCashbackEvent($policy,$type,$status));
                }

                if (isset($policy)) {
                    $actionDate = \Carbon\Carbon::parse($policy->created_at)->format('Y-m-d');
                    if ($actionDate < '2022-08-01' && $policy->vat_percent == 14) {
                        if($policy->premium_freq == 1 && $policy->product_id == 3){
                            $policyPremium = $policy->premium;
                            if($policy->premium_freq == 1)
                                $policyPremium = $policyPremium/1.08; //Remove only in case of monthly frequency
                            $policyPremium = $policyPremium/1.14;

                            $premiumWithoutVAT = $policyPremium;

                            $policyPremium = $premiumWithoutVAT*1.12;

                            if($policy->premium_freq == 1)
                                $policyPremium = $policyPremium*1.08; // Add service Tax 1.08
                            $newPremium = $policyPremium;
                            $new_vat = number_format($policy->premium - $newPremium, 2);
                            $paymentTrans = PaymentTransaction::where('policyNumber',$payment->policyNumber)->where('referenceNumber',$payment->referenceNumber)->first();
                            if (isset($paymentTrans)) {
                                // foreach ($paymentTrans as $key => $payment) {
                                    $paymentTrans->extra_vat = $new_vat;
                                    $paymentTrans->save();
                                // }
                            }
                        }
                    }
                }

            }
        });

        //For VCS
        PaymentTransaction::updated(function ($payment) {
            if($payment->policyNumber != null && $payment->paymentMethod == 'VCS'){
                if($payment->status == "SUCCESS" || $payment->status =="Success")
                    $status = 1;
                else
                    $status = 0;

                if($payment->amount == 1)
                    $type = 7 ; //Commission on Instant Premium for 1 Pula
                else {
                    $paymentCount = PaymentTransaction::where('policyNumber',$payment->policyNumber)->count();
                    if($paymentCount <= 1)
                        $type = 6; //Commission on Registration of Payment method
                    else
                        $type = 8; //Commission on Subsequent Collection
                }

                $policy = Policy::where('policyNumber',$payment->policyNumber)->first();
                if($policy->agent_id != null){
                    $amount = $payment->amount;
                    event(new \Modules\Incentive\Events\AddIncentive($policy,$type,$status,$amount));
                }
                event(new \Modules\Cashback\Events\CustomerCashbackEvent($policy,$type,$status));
            }

            if ($payment->policyNumber != null ) {

                $policy = Policy::where('policyNumber',$payment->policyNumber)->first();

                if (isset($policy)) {
                    $actionDate = \Carbon\Carbon::parse($policy->created_at)->format('Y-m-d');
                    if ($actionDate < '2022-08-01' && $policy->vat_percent == 14) {
                        if($policy->premium_freq == 1 && $policy->product_id == 3){
                            $policyPremium = $policy->premium;
                            if($policy->premium_freq == 1)
                                $policyPremium = $policyPremium/1.08; //Remove only in case of monthly frequency
                            $policyPremium = $policyPremium/1.14;

                            $premiumWithoutVAT = $policyPremium;

                            $policyPremium = $premiumWithoutVAT*1.12;
                            if($policy->premium_freq == 1)
                                $policyPremium = $policyPremium*1.08; // Add service Tax 1.08
                            $newPremium = $policyPremium;
                            $new_vat = number_format($policy->premium - $newPremium, 2);

                            $paymentTrans = PaymentTransaction::where('policyNumber',$payment->policyNumber)->where('referenceNumber',$payment->referenceNumber)->first();
                            if (isset($paymentTrans)) {
                                // foreach ($paymentTrans as $key => $payment) {
                                    $paymentTrans->extra_vat = $new_vat;
                                    $paymentTrans->save();
                                // }
                            }
                        }
                    }
                }
            }
        });
    }
}
