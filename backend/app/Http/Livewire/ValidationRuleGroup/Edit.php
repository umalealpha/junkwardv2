<?php

namespace AlphaDirect\Http\Livewire\ValidationRuleGroup;

use AlphaDirect\Models\Alpharicvggroup;
use AlphaDirect\Models\ValidationRuleGroupMaster;
use AlphaDirect\Models\ValidationRuleGroupMasterDetail;
use AlphaDirect\Models\ValidationRuleMaster;
use AlphaDirect\Product;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Edit extends Component
{

    public ValidationRuleGroupMaster $validation_rule_group;
    public $seleced_rules = [];

    protected $rules = [
        'validation_rule_group.s_RuleCode' => 'required',
        'validation_rule_group.s_RuleDesc' => 'required',
        'validation_rule_group.n_Product_FK' => 'required'
    ];

    protected $validationAttributes = [
        'validation_rule_group.s_RuleCode' => 'rule code',
        'validation_rule_group.s_RuleDesc' => 'rule description',
        'validation_rule_group.n_Product_FK' => 'product',
        'ricv_groups.*' => 'ricv_groups',
    ];

    public function mount($id)
    {
        $this->validation_rule_group = ValidationRuleGroupMaster::find($id);
        $this->seleced_rules = ValidationRuleGroupMasterDetail::select('ricvggroup_id','n_PrValidationRuleMasters_FK')
            ->where('n_PrValidationRuleGroupMasters_FK',$this->validation_rule_group->n_PrValidationRuleGroupMasters_PK)
            ->where('n_PrValidationRuleMasters_FK','!=',0)
            ->pluck('n_PrValidationRuleMasters_FK','ricvggroup_id')->toArray();
            // dd($this->ValidationRules);
    }
    /*
       Get All Product
   */
    public function getProduct(){
        return Product::select('id','name')->activated()->get()->pluck('name','id')->toArray();
    }

    /*
        Get All RicvGroups
    */
    public function getRicvGroupsProperty(){
        return Alpharicvggroup::select('s_GroupName','n_Id_PK')->Activated()->pluck('s_GroupName','n_Id_PK')->toArray();
    }

    /*
        Get All Rules
    */
    public function getValidationRulesProperty(){
        return ValidationRuleMaster::select('n_PrValidationRuleMaster_PK as id','s_RuleCode as name')->get()->pluck('name','id')->toArray();
    }

    public function submit()
    {
        // foreach($this->ricvGroups as $id => $name){
        //     $this->rules["seleced_rules.$id"] = "required" ;
        //     $this->validationAttributes["seleced_rules.$id"] = "rule" ;
        // }
        $this->validate();
        $this->validation_rule_group->save();
        ValidationRuleGroupMasterDetail::where('n_PrValidationRuleGroupMasters_FK',$this->validation_rule_group->n_PrValidationRuleGroupMasters_PK)->delete();
        $validation_detail = [];
        foreach($this->ricvGroups as $id => $value){
            $validation_detail[] =
                [
                    'n_PrValidationRuleGroupMasters_FK' => $this->validation_rule_group->n_PrValidationRuleGroupMasters_PK,
                    'ricvggroup_id' => $id,
                    'n_PrValidationRuleMasters_FK' => $this->seleced_rules[$id] ?? NULL,
                    'n_CreatedUser' => \Auth::user()->id,
                    'd_CreatedDate' => now(),
                ];
        }
        if (ValidationRuleGroupMasterDetail::insert($validation_detail)){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Validation Rule')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Validation Rule Group Updated - '.$this->validation_rule_group->s_RuleCode);
            session()->flash('success', "Validation Rule Group Updated Successfully");
            return Redirect::route('validationrulegroup');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
        return view('v2.livewire.validation-rule-group.edit')->layout('layouts.app-v2');
    }
}
