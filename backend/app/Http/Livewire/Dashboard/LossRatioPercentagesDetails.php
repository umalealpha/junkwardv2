<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use AlphaDirect\Http\Traits\DashboardTrait;
use Livewire\Component;
use AlphaDirect\Product;
use Carbon\Carbon;

class LossRatioPercentagesDetails extends Component
{
    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $differenceBetweenDates = 1;

    protected $listeners = ['setDatesForLossRation' => 'setDates'];

    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
        $this->differenceBetweenDates = $this->startDate->diffInDays($this->endDate) ? $this->startDate->diffInDays($this->endDate) : 1;
    }


    public function mount(){
        $this->startDate = Carbon::now(); //->subDay(10000);
        $this->endDate = Carbon::now();
        $this->initDashboardTrait();
    }

    public function render()
    {
        return view('v2.livewire.dashboard.loss-ratio-percentages-details');
    }
    public function getProductsProperty(){
        $startDate = (clone $this->startDate);  //->subDay(10000)
        $endDate = (clone $this->endDate);
        $this->differenceBetweenDates = $startDate->diffInDays($endDate) ? $startDate->diffInDays($endDate) : 1;

        $queryForSumOfReserveAmtCurrent = Product::queryForClaimReservesCoverageSumDates($this->startDate,$this->endDate,'claim_reserves_coverages.reserve_amt');
        $queryForSumOfPaymentAmtCurrent = Product::queryForClaimReservesCoverageSumDates($this->startDate,$this->endDate,'claim_reserves_coverages.payment_amt');
        $queryForSumOfReserveAmtPrevious = Product::queryForClaimReservesCoverageSumDates($startDate->subDay($this->differenceBetweenDates),( $endDate)->subDay($this->differenceBetweenDates),'claim_reserves_coverages.reserve_amt');
        $queryForSumOfPaymentAmtPrevious = Product::queryForClaimReservesCoverageSumDates($startDate->subDay($this->differenceBetweenDates),$endDate->subDay($this->differenceBetweenDates),'claim_reserves_coverages.payment_amt');
        $products = Product::selectRaw("name,($queryForSumOfReserveAmtCurrent) as sum_of_reserve_amt,($queryForSumOfPaymentAmtCurrent) as sum_of_payment_amt,($queryForSumOfReserveAmtPrevious) as previous_sum_of_reserve_amt, ($queryForSumOfPaymentAmtPrevious) as previous_sum_of_payment_amt")->Activated()->get();
        // dd($products);
        $data = [];
        foreach ($products as $index => $product){
            $data[$product->name] = [
                $this->getClaimPercentage($product->sum_of_reserve_amt,$product->sum_of_payment_amt),
                $this->getClaimPercentage($product->previous_sum_of_reserve_amt,$product->previous_sum_of_payment_amt)
            ];
        }
        return $data;
    }

    public function getClaimPercentage($payment_amt=0,$reserve_amt=0){
        if (empty($payment_amt) or empty($reserve_amt)){
            return 0;
        }
        return ((float)$reserve_amt/(float)$payment_amt)*100;
    }
}
