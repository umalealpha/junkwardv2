<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use AlphaDirect\Http\Traits\DashboardTrait;
use Livewire\Component;
use AlphaDirect\Product;
use Carbon\Carbon;

class TotalPoliciesDetails extends Component
{
    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $totalPolicyCount = 0;
    public $differenceBetweenDates = 1;

    protected $listeners = ['setDates' => 'setDates'];

    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
        $this->differenceBetweenDates = $this->startDate->diffInDays($this->endDate) ? $this->startDate->diffInDays($this->endDate) : 1;

        $this->dispatchBrowserEvent('redraw_chart', ['product_data' => $this->ProductForChart]);
    }


    public function mount(){
        $this->startDate = Carbon::now(); //->subDay(50);
        $this->endDate = Carbon::now();
        $this->initDashboardTrait();
    }

    public function render()
    {
        return view('v2.livewire.dashboard.total-policies-details');
    }
    public function getProductsProperty(){
        $startDate = (clone $this->startDate);
        $endDate = (clone $this->endDate);

        $this->differenceBetweenDates = $startDate->diffInDays($endDate) ? $startDate->diffInDays($endDate) : 1;
        return Product::select('id','name')
            ->WithPolicyCountBetweenDates($startDate,$endDate,'current_count')
            ->WithPolicyCountBetweenDates($startDate->subDay($this->differenceBetweenDates),$endDate->subDay($this->differenceBetweenDates),'previous_count')
            ->Activated()->get();
    }

    public function getProductForChartProperty(){
        $product_for_chart[] = ['Product Name', 'Number of policy under product'];
        foreach ($this->products as $key => $product){
            $product_for_chart[] = [$product->name,$product->current_count];
        }
        return $product_for_chart;
    }
}
