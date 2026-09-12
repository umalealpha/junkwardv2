<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \Modules\Inventory\Events\DeductStockStore::class => [
            \Modules\Inventory\Listeners\DeductStockStoreFire::class,
        ],
		\Modules\Inventory\Events\AddIncentive::class => [
            \Modules\Inventory\Listeners\AddIncentiveFire::class,
        ],
    ];
}