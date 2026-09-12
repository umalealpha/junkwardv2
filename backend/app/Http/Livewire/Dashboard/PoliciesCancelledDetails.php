<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use AlphaDirect\Http\Traits\DashboardTrait;
use Livewire\Component;
use AlphaDirect\Policy;
use Carbon\Carbon;
use DB;

class PoliciesCancelledDetails extends Component
{

    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $totalPolicyCount = 0;

    protected $listeners = ['setActivated&CancelledDates' => 'setDates'];

    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
    }

    public function  mount() {
        $this->startDate = Carbon::now(); //->subDay(30)
        $this->endDate = Carbon::now();
        $this->initDashboardTrait();
    }

    public function getPolicyStatusCount($startDate,$endDate,$status){
        return Policy::Status($status)->betweenCreatedDates($startDate,$endDate)->orderBy('id','desc')->count();
    }

    public function render()
    {
        return view('v2.livewire.dashboard.policies-cancelled-details');
    }

  

}
