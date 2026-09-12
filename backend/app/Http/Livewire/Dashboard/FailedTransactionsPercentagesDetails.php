<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use AlphaDirect\Http\Traits\DashboardTrait;
use Livewire\Component;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use DB;

class FailedTransactionsPercentagesDetails extends Component
{
    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $totalPolicyCount = 0;
    public $differenceBetweenDates = 1;

    protected $listeners = ['setFailedTransPercentagesDates' => 'setDates'];

    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
        $this->differenceBetweenDates = $this->startDate->diffInDays($this->endDate) ? $this->startDate->diffInDays($this->endDate) : 1;
    }

    public function  mount() {
        $this->startDate = Carbon::now();
        $this->endDate = Carbon::now();
        $this->initDashboardTrait();
    }

    public function getPaymentTransactionProperty(){
        $queryForCurrentNoteCount = PaymentTransaction::queryForCurrentNoteCountBetweenDates($this->startDate,$this->endDate,'note');
        return $queryForCurrentNoteCount;
    }
    
    public function getPreviousNotesCount($startDate,$endDate,$note){
        return PaymentTransaction::Note($note)->betweenCreatedDates($startDate,$endDate)->Status()->orderBy('id','desc')->count();
    }
    
    public function render()
    {
        return view('v2.livewire.dashboard.failed-transactions-percentages-details');
    }
}
