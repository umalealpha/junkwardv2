<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use Livewire\Component;
use AlphaDirect\Http\Traits\DashboardTrait;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Carbon\Carbon;
use DB;

class BlacklistedCustomerDetails extends Component
{
    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $totalPolicyCount = 0;

    protected $listeners = ['setBlackGraylistedDates' => 'setDates'];
    
    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
    }
    
    public function  mount() {
        $this->startDate = Carbon::now(); //->subDay(30); //->subMonth( 20 )
        $this->endDate = Carbon::now();
        $this->initDashboardTrait();
    }

    public function getBlackListedCustomerCount($startDate,$endDate,$customerCategory){
        $queryForBlackListedCustomerCount = Customer::queryForListedCustomerCountWithStatusBetweenDates($this->startDate,$this->endDate,$customerCategory);
        return $queryForBlackListedCustomerCount;
    }

    public function getGreyListedCustomerCount($startDate,$endDate,$customerCategory){
        $queryForGreyListedCustomerCount = Customer::queryForListedCustomerCountWithStatusBetweenDates($this->startDate,$this->endDate,$customerCategory);
        return $queryForGreyListedCustomerCount;
    }
    
    public function render()
    {
        return view('v2.livewire.dashboard.blacklisted-customer-details');
    }
}
