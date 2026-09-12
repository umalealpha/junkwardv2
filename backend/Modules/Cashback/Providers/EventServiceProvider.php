<?php

namespace Modules\Cashback\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \Modules\Cashback\Events\CustomerCashbackEvent::class => [
            \Modules\Cashback\Listeners\CustomerCashbackListener::class,
        ],
    ];
}
