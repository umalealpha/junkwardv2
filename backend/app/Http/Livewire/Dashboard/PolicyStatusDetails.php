<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use AlphaDirect\Http\Traits\DashboardTrait;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Livewire\Component;

class PolicyStatusDetails extends Component
{
    use DashboardTrait;
    public int $totalPolicyCount = 0;
    public array $policyStatus = ['Activated' => 0,'Deactivated' => 0,'Cancelled' => 0,'Expired' => 0];
    public Carbon $startDate;
    public Carbon $endDate;
    public $differenceBetweenDates = 1;
    protected $listeners = ['setDatesForPolicyStatus' => 'setDates'];

    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
        $this->differenceBetweenDates = $this->startDate->diffInDays($this->endDate) ? $this->startDate->diffInDays($this->endDate) : 1;
        $this->countPolicyStatus();
        $this->dispatchBrowserEvent('redraw_chart', ['policy_data' => $this->policyForChart()]);
    }

    public function mount(){
        $this->startDate = Carbon::now();
        $this->endDate = Carbon::now();
        $this->countPolicyStatus();
        $this->initDashboardTrait();
    }
    
    public function render()
    {
        return view('v2.livewire.dashboard.policy-status-details');
    }

    public function countPolicyStatus(){
        $dbPolicyStatus =  Policy::statusCount($this->startDate,$this->endDate)->pluck('count','status_name')->toArray();
        $dbPreviousPolicyStatus =  Policy::statusCount((clone $this->startDate)->subDay($this->differenceBetweenDates),(clone $this->endDate)->subDay($this->differenceBetweenDates))->pluck('count','status_name')->toArray();
        $this->totalPolicyCount = 0;
        foreach ($this->policyStatus as $status => $count){
            $this->policyStatus[$status] = [$dbPolicyStatus[$status] ?? 0, $dbPreviousPolicyStatus[$status] ?? 0];
            $this->totalPolicyCount = $this->totalPolicyCount+($dbPolicyStatus[$status] ?? 0);
        }
        return $this->policyStatus;
    }

    public function policyForChart(){
        $policy_for_chart[] = ['Policy Name', 'Number of policy '];
        foreach ($this->policyStatus as $status => $count){
            $policy_for_chart[] = [$status,$count[0] ?? 0];
        }
        return $policy_for_chart;
    }
}
