<?php
namespace AlphaDirect\Http\Traits;

use Config;

trait DashboardTrait {
    public $colors;

    public function initDashboardTrait(){
        $this->colors = Config::get('constants.colors');
    }
    

    public function getPercentage($count,$preCount){
        if((!empty($count)) && (!empty($preCount))){
            return((($count)-($preCount))/($preCount))*100;
        }else{
            if((($count==0) && ($preCount!=0))||(($count!=0) && ($preCount==0))){
                return (($count)-($preCount))*100;
            }else{
                return 0;
            }
        }
    }
}
