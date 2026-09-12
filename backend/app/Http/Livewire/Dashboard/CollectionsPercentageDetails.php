<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use AlphaDirect\Http\Traits\DashboardTrait;
use Livewire\Component;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use DB;


class CollectionsPercentageDetails extends Component
{
    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $totalPolicyCount = 0;
    public $differenceBetweenDates = 1;

    protected $listeners = ['setCollectionsPercentageDates' => 'setDates'];

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

    public function render()
    {
        return view('v2.livewire.dashboard.collections-percentage-details');
    }


    public function getProductsProperty(){
        $startDate = (clone $this->startDate); //->subDay(10000)
        $endDate = (clone $this->endDate);
        $this->differenceBetweenDates = $startDate->diffInDays($endDate) ? $startDate->diffInDays($endDate) : 1;

        $queryForCurrent = Product::queryForTransactionBetweenDates($this->startDate,$this->endDate,'amount');
        $queryForCurrentWithSucess = Product::queryForTransactionBetweenDates($this->startDate,$this->endDate,'amount',"'SUCCESS', 'Success', 'success'");
        $queryForPrevious = Product::queryForTransactionBetweenDates($startDate->subDay($this->differenceBetweenDates),( $endDate)->subDay($this->differenceBetweenDates),'amount');
        $queryForPreviousWithSucess = Product::queryForTransactionBetweenDates($startDate->subDay($this->differenceBetweenDates),$endDate->subDay($this->differenceBetweenDates),'amount',"'SUCCESS', 'Success', 'success'");
        $products =  Product::selectRaw("name,($queryForCurrent) as current_sum_of_amount,($queryForCurrentWithSucess) as current_sum_of_amount_with_success,($queryForPrevious) as previous_sum_of_amount, ($queryForPreviousWithSucess) as previous_sum_of_amount_with_success")->Activated()->get();
        // dd($products);
        $data = [];
        foreach ($products as $index => $product){
            $data[$product->name] = [
                $this->getAmountPercentage($product->current_sum_of_amount,$product->current_sum_of_amount_with_success),
                $this->getAmountPercentage($product->previous_sum_of_amount,$product->previous_sum_of_amount_with_success)
            ];
        }
        return $data;
    }

    public function getAmountPercentage($sum_of_amount=0,$sum_of_amount_with_success=0){
        if (empty($sum_of_amount) or empty($sum_of_amount_with_success)){
            return 0;
        }
        return ((float)$sum_of_amount_with_success/(float)$sum_of_amount)*100;
    }


}
