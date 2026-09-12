<?php

namespace Modules\Incentive\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \Modules\Incentive\Events\AddIncentive::class => [
            \Modules\Incentive\Listeners\AddIncentiveFire::class,
        ],
    ];
}