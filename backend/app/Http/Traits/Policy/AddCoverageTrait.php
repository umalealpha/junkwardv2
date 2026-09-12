<?php
namespace AlphaDirect\Http\Traits\Policy;

use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\PolicyMember;
use AlphaDirect\Product;
use Illuminate\Validation\Validator;
use AlphaDirect\Models\CoverageMaster;

trait AddCoverageTrait {

    public $hasRiskAddress = false;
    public $hasMember = false;
    public $hasVehicle = false;
    public $hasDevice = false;
    public $hasCoverage;
//    public $entity_type = [];

    public function getAvailableCoveragesMasterProperty(){
        return $this->policies->product ? $this->policies->product->coverageMaster()->get()->toArray() : [];
    }

    public function getPolicyMemberProperty(){
        return PolicyMember::where('policy_id',$this->policies->id)->get();
    }

    public function saveCoverages($formData){
        request()->merge($formData);
        $data = [];
        $this->resetErrorBag();
        $this->resetValidation();
        $selected = \AlphaDirect\Models\CoverageMaster::whereIn('s_ParentCoverageCode',array_filter(array_values($this->seletedCoverages)))->orderBy('s_ParentCoverageCode')->get();
        foreach($selected as $k){
            if(request()->get($k->id."_FIELD1")!=""){
                $data[$k->id]['policy_id']=$this->policies->id;
                $data[$k->id]['coverage_id']=$k->id;
                $data[$k->id]['main']=$k->s_ParentCoverageCode;
                $data[$k->id]['coverage_value']=request()->get($k->id."_FIELD1");
                $data[$k->id]['discount']=request()->get($k->id."_FIELD2");
                $data[$k->id]['type']=request()->get($k->id."_FIELD3");
                $data[$k->id]['value']=request()->get($k->id."_FIELD4");
            }
        }
        $this->withValidator(function (Validator $validator) use($selected) {
            $validator->after(function ($validator) use($selected){
                foreach($selected as $k){
                    #validation
                    if(request()->get($k->id."_FIELD1")!=""){
                        if(!is_numeric(request()->get($k->id."_FIELD1"))){
                            $validator->errors()->add($k->id."_FIELD1", $k->s_ScreenName." must be a numeric");
                        }
                    }
                }
            });
        })->validate();
        \DB::transaction(function () use($data) {
            // Delete-then-reinsert, scoped to the rows this method can actually
            // own. It used to be PolicyCoverage::where('policy_id', …)->delete()
            // — every coverage on the policy, across EVERY action and term,
            // issued ones included — while the rows it re-inserts carry no
            // action_id or term_id at all. This trait is used by both AddWizard
            // and EditWizard and, as a public Livewire method, is remotely
            // callable, so on an existing policy that unscoped delete was a
            // one-call wipe of the whole coverage history.
            //
            // Only the unstamped rows this path creates are cleared; anything
            // belonging to a real transaction is left alone.
            PolicyCoverage::where('policy_id', $this->policies->id)
                ->where(function ($q) {
                    $q->whereNull('action_id')->orWhere('action_id', 0);
                })
                ->delete();
            PolicyCoverage::insert($data);
        });
        $this->updateHasStatus();
    }

    public function updateHasStatus(){
        $this->hasRiskAddress = Product::find($this->policies->product_id)->coverageMaster()->HasRiskAddress()->exists();
        $this->hasMember = Product::find($this->policies->product_id)->coverageMaster()->HasMember()->exists();
        $this->hasVehicle = Product::find($this->policies->product_id)->coverageMaster()->HasVehicle()->exists();
        $this->hasDevice = Product::find($this->policies->product_id)->coverageMaster()->HasDevice()->exists();
        $this->hasCoverage = Product::find($this->policies->product_id)->coverageMaster()->exists();
        if ($this->hasRiskAddress and $this->step < 3){
            $this->step = 3;
        } else
        if ($this->hasMember and $this->step < 4){
            $this->step = 4;
        } else if ($this->hasVehicle and $this->step < 5){
            $this->step = 5;
        } else if ($this->hasDevice and $this->step < 6){
            $this->step = 6;
        }else{
            if (method_exists($this,'saveStep6')){
                $this->saveStep6();
            }
        }
        $this->render();
    }

    /* Back Step 1*/
    public function backToStep1(){
//        dd('hiii');
        $this->step=1;
//        dd($this->policies->plan_id);
        $this->allPlan= \AlphaDirect\Productplan::where('product_id', $this->policies->product_id)
            ->get()->keyBy('id')->map(function($d){
                return [
                    'id'=>$d->id,
                    'name'=>$d->name
                ];
            });
        $this->allCities= \AlphaDirect\City::where('state_id', $this->customer_profile->state)
            ->get()->keyBy('id')->map(function($d){
                return [
                    'id'=>$d->id,
                    'name'=>$d->name
                ];
            });
    }

    /* Back Step 2*/
    public function backToStep2(){
        if($this->hasRiskAddress){
            $this->step=2;
        }else{
            $this->step=1;
        }
    }

    /* Back Step 3*/
    public function backToStep3(){
        $this->step=3;
    }

    /* Back Step 4*/
    public function backToStep4(){
        if ($this->hasMember){
            $this->step=4;
        }else if($this->hasRiskAddress){
            $this->step=2;
        }else{
            $this->step=2;
        }
    }

    /* Back Step 5*/
    public function backToStep5(){
        if ($this->hasVehicle){
            $this->step=5;
        }else if($this->hasMember){
            $this->step=4;
        }else if($this->hasRiskAddress){
            $this->step=2;
        }else{
            $this->step=2;
        }
    }

//
//	public function saveStep2(){
//		$this->updateHasStatus();
//	}
//
//	public function saveStep4(){
//        $this->updateHasStatus();
//	}
//
//	public function saveStep5(){
//        $this->updateHasStatus();
//	}

}
