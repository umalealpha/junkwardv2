<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use Livewire\Component;
use AlphaDirect\Http\Traits\DashboardTrait;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\PaymentTransaction; 
use Carbon\Carbon;
use DB;

class SuccessfulPremiumDetails extends Component {
    use DashboardTrait;
    public $startDate;
    public $endDate;
    public $paymentTransDetails = [];

    protected $listeners = ['setSuccessfulPremiumDates' => 'setDates'];

    public function setDates($data){
        $this->startDate = Carbon::createFromFormat('d M Y',$data['startDate']);
        $this->endDate = Carbon::createFromFormat('d M Y',$data['endDate']);
        $this->dispatchBrowserEvent('kt_chart', ['product_count' => $this->SuccessPaymentTransForChart['product_count'],
        'product_date' => $this->SuccessPaymentTransForChart['date'],'product_max_count' => $this->SuccessPaymentTransForChart['max'],
        'product_min_count' => $this->SuccessPaymentTransForChart['min']]);
    }
    
    public function mount(){
        $this->startDate = Carbon::now(); //->subDay(60);
        $this->endDate = Carbon::now();
        $this->initDashboardTrait();
    }

    public function getSuccessPaymentTransProperty(){
        $this->paymentTransDates = PaymentTransaction::queryForpaymentTransDatesBetweenDates($this->startDate,$this->endDate);
        if(count($this->paymentTransDates)>0){
            $products = Product::ProductsName();
            foreach ($this->paymentTransDates as $key=> $data){
                $date = Carbon::createFromFormat('Y-m-d', $data->date)->format('m/d');
                foreach ($products as $key=>$name) {
                    $this->paymentTransDetails[ $name ][ $date ] = Policy::queryForPaymentTransCountwithDates($data->date,$key);
                }
            }
        }
        return $this->paymentTransDetails;
    }
    
    public function getSuccessPaymentTransForChartProperty(){
        $max = 0;
        $min = 1; 
        $product_chart['date'] = [];
        $product_chart['product_count'] = [];
        if(count($this->SuccessPaymentTrans)>0){
            foreach ($this->SuccessPaymentTrans as $key => $count){
                $temp["name"]=$key;
                $temp["data"]=array_values($count);
                $product_chart['product_count'][] =$temp;
                $product_chart['date'] = array_keys($count); 
                $arrayMax[] = max(array_values($count));
                $arrayMin[] = min(array_values($count));
            }
            $max = max($arrayMax);
            $min = min($arrayMin);
        }
        $product_chart['min'] = $min;
        $product_chart['max'] = $max;
        return  $product_chart;
    }
    
    public function render() {
        return view( 'v2.livewire.dashboard.successful-premium-details' );
    }
}
