<?php

namespace AlphaDirect\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('AlphaDirect\Repositories\Customer\CustomerInterface', 'AlphaDirect\Repositories\Customer\CustomerRepository');
        $this->app->bind('AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface', 'AlphaDirect\Repositories\CustomerProfile\CustomerProfileRepository');
        $this->app->bind('AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneInterface', 'AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneRepository');
        $this->app->bind('AlphaDirect\Repositories\CustomerKyc\CustomerKycInterface', 'AlphaDirect\Repositories\CustomerKyc\CustomerKycRepository');
        $this->app->bind('AlphaDirect\Repositories\CustomerBanking\CustomerBankingInterface', 'AlphaDirect\Repositories\CustomerBanking\CustomerBankingRepository');
        $this->app->bind('AlphaDirect\Repositories\ClaimCellphone\ClaimCellphoneInterface', 'AlphaDirect\Repositories\ClaimCellphone\ClaimCellphoneRepository');
        $this->app->bind('AlphaDirect\Repositories\Vehicle\VehicleInterface', 'AlphaDirect\Repositories\Vehicle\VehicleRepository');
        $this->app->bind('AlphaDirect\Repositories\Policy\PolicyInterface', 'AlphaDirect\Repositories\Policy\PolicyRepository');
        $this->app->bind('AlphaDirect\Repositories\PolicyBeneficiary\PolicyBeneficiaryInterface', 'AlphaDirect\Repositories\PolicyBeneficiary\PolicyBeneficiaryRepository');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
