<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use Livewire\Component;
use AlphaDirect\Http\Traits\DashboardTrait;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\Claim;
use Carbon\Carbon;
use DB;

class PercentageOfHighRiskClaimsDetails extends Component
{   
    
    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $totalPolicyCount = 0;

    protected $listeners = ['setPercentageHighRiskClaimsDates' => 'setDates'];
    
    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
    }
    
    public function  mount() {
        $this->startDate = Carbon::now(); //->subDay(30); //->subMonth( 20 )
        $this->endDate = Carbon::now();
        $this->initDashboardTrait();
    }

    public function getHighRiskClaimWithStatusCount($startDate,$endDate,$status){
        $queryForClaimCount = Claim::queryForHighRiskClaimCountWithStatusBetweenDates($this->startDate,$this->endDate,$status);
        return $queryForClaimCount;
    }

    public function render()
    {
        return view('v2.livewire.dashboard.percentage-of-high-risk-claims-details');
    }
}
