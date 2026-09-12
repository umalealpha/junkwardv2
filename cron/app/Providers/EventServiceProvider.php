<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \AlphaDirect\Events\SendMail::Class => [
			\AlphaDirect\Listeners\SendMailFired::class,
		],
       \AlphaDirect\Events\SendSms::Class => [
			\AlphaDirect\Listeners\SendSmsFired::class,
		],
        \AlphaDirect\Events\CancelTokenEvent::class => [
            \AlphaDirect\Listeners\CancelTokenListener::class,
        ],
        \AlphaDirect\Events\CancelScheduleTransactionEvent::class => [
            \AlphaDirect\Listeners\CancelScheduleTransactionListener::class,
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
