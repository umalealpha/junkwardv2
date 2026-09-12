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
class Add extends Component
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

    public function mount()
    {
        $this->validation_rule_group = new ValidationRuleGroupMaster();
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
        return ValidationRuleMaster::select('n_PrValidationRuleMaster_PK as id','s_RuleCode as name')->get();
    }

    public function submit()
    {
        // foreach($this->ricvGroups as $id => $name){
        //     $this->rules["seleced_rules.$id"] = "required" ;
        //     $this->validationAttributes["seleced_rules.$id"] = "rule" ;
        // }
        // dd($this->ricvGroups,$this->seleced_rules,$this);
        $this->validate();
        $this->validation_rule_group->save();
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
                ->log('Validation Rule Group Added - '.$this->validation_rule_group->s_RuleCode);
            session()->flash('success', "Validation Rule Group Created Successfully");
            return Redirect::route('validationrulegroup');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
        return view('v2.livewire.validation-rule-group.add')->layout('layouts.app-v2');
    }

}
