<?php

namespace AlphaDirect\View\Components\Policy;

use AlphaDirect\Banks;
use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\Product;
use AlphaDirect\ProductType;
use AlphaDirect\QuoteSettings;
use AlphaDirect\ReratedPremiumQuote;
use AlphaDirect\Stores;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Store;
use Illuminate\View\Component;

class ReratePremium extends Component
{
    public Policy $policy;
    public $premiumCalcDetails;
    public $dataMake;
    public $dataModel;
    public $reratedPremiumQuotes;
    public $years;
    public $is_renewal;
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($policy)
    {
        $this->policy = $policy;
        $this->premiumCalcDetails = null;
        // dd($policy->store?->name ?? ''); use for store name

        if ($policy->product->id == 3 || $policy->product->id == 2) {

            if ($policy->quoteNumber != null) {
                $this->premiumCalcDetails = MotorComprehensiveQuotes::QuoteNo($policy->quoteNumber)->first();
                $this->reratedPremiumQuotes = ReratedPremiumQuote::RateId($this->premiumCalcDetails->ratings_id)->orderBy('id', 'DESC')->first();
                $this->dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($this->premiumCalcDetails->is_imported);
                $this->dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($this->premiumCalcDetails->make, $this->premiumCalcDetails->is_imported, $this->premiumCalcDetails->year);
            } else {
                $this->premiumCalcDetails = null;
                $reratedPremiumQuotes = null;
                $this->premiumCalcDetails = new \stdClass();

                if ($policy->PolicyVehicle != null && isset($policy->PolicyVehicle->first()->is_imported)) {
                    $this->premiumCalcDetails->is_imported  = $policy->PolicyVehicle->first()->is_imported == 0 ? "No" : "Yes";
                } else {
                    $this->premiumCalcDetails->is_imported  = null;
                }

                if ($policy->PolicyVehicle != null && isset($policy->PolicyVehicle->first()->make)) {
                    $this->premiumCalcDetails->make = $policy->PolicyVehicle->first()->make;
                } else {
                    $this->premiumCalcDetails->make  = null;
                }

                if ($policy->PolicyVehicle != null && isset($policy->PolicyVehicle->first()->model)) {
                    $this->premiumCalcDetails->model = $policy->PolicyVehicle->first()->model;
                } else {
                    $this->premiumCalcDetails->model  = null;
                }

                if ($this->policy->PolicyVehicle != null && isset($this->policy->PolicyVehicle->first()->year)) {
                    $this->premiumCalcDetails->year = $policy->PolicyVehicle->first()->year;
                } else {
                    $this->premiumCalcDetails->year  = null;
                }

                $this->premiumCalcDetails->estimatedValue = $policy->sum_assured;

                if ($this->policy->PolicyVehicle != null && isset($policy->PolicyVehicle->first()->claim_count)) {
                    $this->premiumCalcDetails->priorAccidents = $policy->PolicyVehicle->first()->claim_count;
                } else {
                    $this->premiumCalcDetails->priorAccidents  = null;
                }

                $this->dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($this->premiumCalcDetails->is_imported);
                $this->dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($this->premiumCalcDetails->make, $this->premiumCalcDetails->is_imported, $this->premiumCalcDetails->year);
                $this->premiumCalcDetails->quoteNumber  = null;
                $this->premiumCalcDetails->ratings_id = null;
                $this->premiumCalcDetails->premiumMonthly = $policy->premium;
                $this->premiumCalcDetails->premium3Inst = $policy->premium;
                $this->premiumCalcDetails->premiumAnnually = $policy->premium;
                $this->premiumCalcDetails->discount_surcharge = null;
                $this->premiumCalcDetails->premium_rate = null;
                $this->premiumCalcDetails->ratio = null;
            }

            $this->premiumCalcDetails->priorAccidents = Claim::PolicyId($policy->id)->count();
            $countLog = PolicyPremiumReratingLog::PolicyNumber($policy->policyNumber)->count();
            $setting = QuoteSettings::orderBy('id','DESC')->first(array('policy_premium_edit_limit'));
            $this->premiumCalcDetails->can_edit = ($countLog < $setting->policy_premium_edit_limit) ? 1 : 0;
        }

        $this->years = range(1990,Carbon::now()->year);
        $this->is_renewal = PolicyRenewal::PolicyNumber($policy->policyNumber)->orderBy('id', 'desc')->first('is_renewed');
        $banks['names'] = Banks::all();
        // $paymentDetails = $merged_all->all();

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.policy.rerate-premium');
    }
}
