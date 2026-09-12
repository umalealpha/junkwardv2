<?php

namespace AlphaDirect\Providers;

use AlphaDirect\Events\CancelScheduleTransactionEvent;
use AlphaDirect\Events\CancelTokenEvent;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\RenewPolicySchedulesEvent;
use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\Events\TestOrangeScheduleTransactionEvent;
use AlphaDirect\Events\SendPaymentLinkEvent;
use AlphaDirect\Events\SubscriptionTokenEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Listeners\CancelScheduleTransactionListener;
use AlphaDirect\Listeners\CancelTokenListener;
use AlphaDirect\Listeners\ChargeTokenRecurrentListener;
use AlphaDirect\Listeners\CreateTokenListener;
use AlphaDirect\Listeners\PullAccountListener;
use AlphaDirect\Listeners\RenewPolicySchedulesListener;
use AlphaDirect\Listeners\ScheduleTransactionListener;
use AlphaDirect\Listeners\TestOrangeScheduleTransactionListener;
use AlphaDirect\Listeners\SendPaymentLinkListener;
use AlphaDirect\Listeners\SubscriptionTokenListener;
use AlphaDirect\Listeners\VerifyTokenListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use AlphaDirect\Events\ActivationCode;
use AlphaDirect\Events\CancelOrangeScheduleTransactionEvent;
use AlphaDirect\Events\UpdateRealpayInstallmentDataEvent;
use AlphaDirect\Jobs\ActivationCodeJob;
use AlphaDirect\Listeners\CancelOrangeScheduleTransactionListener;
use AlphaDirect\Listeners\UpdateRealpayInstallmentDataListener;
use AlphaDirect\Events\ExcelImportForPolicyActivate;
use AlphaDirect\Jobs\ExcelImportForPolicyActivateJob;
use AlphaDirect\Events\ExcelImportPolicyCancellation;
use AlphaDirect\Events\NewScheduleTransactionEvent;
use AlphaDirect\Jobs\ExcelImportPolicyCancellationJob;
use AlphaDirect\Listeners\NewScheduleTransactionListener;
use AlphaDirect\Events\DpoRefundExcelEvent;
use AlphaDirect\Jobs\DpoRefundExcelJob;
use AlphaDirect\Events\ExcelImportForNgeniusAddTrxn;
use AlphaDirect\Jobs\ExcelImportForNgeniusAddTrxnJob;
use AlphaDirect\Events\PolicyEvent;
use AlphaDirect\Events\ClaimEvent;
use AlphaDirect\Events\PaymentEvent;
use AlphaDirect\Listeners\PolicyEventListener;
use AlphaDirect\Listeners\ClaimEventListener;
use AlphaDirect\Listeners\PaymentEventListener;
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        VerifyTokenEvent::class => [
            VerifyTokenListener::class
        ],
        CancelTokenEvent::class => [
            CancelTokenListener::class
        ],
        SubscriptionTokenEvent::class => [
            SubscriptionTokenListener::class
        ],
        ChargeTokenRecurrentEvent::class => [
            ChargeTokenRecurrentListener::class
        ],
        PullAccountEvent::class => [
            PullAccountListener::class
        ],
        CreateTokenEvent::class => [
            CreateTokenListener::class
        ],
        ScheduleTransactionEvent::class => [
            ScheduleTransactionListener::class
        ],
        NewScheduleTransactionEvent::class => [
            NewScheduleTransactionListener::class
        ],
        CancelScheduleTransactionEvent::class => [
            CancelScheduleTransactionListener::class
        ],
        CancelOrangeScheduleTransactionEvent::class => [
            CancelOrangeScheduleTransactionListener::class
        ],
        UpdateRealpayInstallmentDataEvent::class => [
            UpdateRealpayInstallmentDataListener::class
        ],
        SendPaymentLinkEvent::class => [
            SendPaymentLinkListener::class
        ],
		\AlphaDirect\Events\SendMail::class => [
			\AlphaDirect\Listeners\SendMailFired::class,
		],
		\AlphaDirect\Events\SendSms::class => [
			\AlphaDirect\Listeners\SendSmsFired::class,
		],
        \AlphaDirect\Events\policyLifecycle::class=>[
            \AlphaDirect\Listeners\policyLifecycleListner::class,
        ],
        \AlphaDirect\Events\CustomerCashbackEvent::class=>[
            \AlphaDirect\Listeners\CustomerCashbackListner::class,
        ],
        RenewPolicySchedulesEvent::class => [
            RenewPolicySchedulesListener::class
        ],
        TestOrangeScheduleTransactionEvent::class => [
            TestOrangeScheduleTransactionListener::class
        ],
        ActivationCode::class => [
            ActivationCodeJob::class
        ],
        ExcelImportForPolicyActivate::class => [
            ExcelImportForPolicyActivateJob::class
        ],
        ExcelImportPolicyCancellation::class => [
            ExcelImportPolicyCancellationJob::class
        ],
        DpoRefundExcelEvent::class => [
            DpoRefundExcelJob::class
        ],
        ExcelImportForNgeniusAddTrxn::class => [
            ExcelImportForNgeniusAddTrxnJob::class
        ],
        // Domain events — cross-module integration
        PolicyEvent::class => [PolicyEventListener::class],
        ClaimEvent::class  => [ClaimEventListener::class, \AlphaDirect\Listeners\ClaimFormEmailListener::class, \AlphaDirect\Listeners\ClaimTrackingLinkListener::class, \AlphaDirect\Listeners\PremiumConfirmationListener::class],
        PaymentEvent::class => [PaymentEventListener::class],

        // Drop empty-diff audit rows (the "[] → []" noise in the Logs tab)
        // before they are written. See SkipEmptyAudit for the exact rule.
        \OwenIt\Auditing\Events\Auditing::class => [
            \AlphaDirect\Listeners\SkipEmptyAudit::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
