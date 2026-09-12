<?php

namespace AlphaDirect\Http\Livewire\ValidationRule;

use AlphaDirect\Coverage;
use AlphaDirect\Models\Alpharicvggroup;
use AlphaDirect\Models\ValidationRuleDetail;
use AlphaDirect\Models\ValidationRuleMaster;
use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Add extends Component
{
    public $rule_code;
    public $rule_description;
    public $product_id;
    public $product_type;
    public $screen_error_msg;
    public $rule_apply_on;
    public $can_rate_policy;
    public $can_print_quote;
    public $can_print_application;
    public $can_bind_application;
    public $can_submit_un_bound_application;
    public $can_issue_policy;
    public $rule_start_date;
    public $rule_end_date;
    public $rules_status = true;

    public $inputs = [];
    public $i = 1;
    public $rule_for;
    public $formula_expression;
    public $value;
    public $value_to;
    public $showValueToInput = [];

    protected $rules = [
        'rule_code' => 'required',
        'rule_description' => 'required',
        'product_id' => 'required',
        'screen_error_msg' => 'required',
        'rule_apply_on' => 'required',
        'can_rate_policy' => 'required',
        'can_print_quote' => 'required',
        'can_print_application' => 'required',
        'can_bind_application' => 'required',
        'can_submit_un_bound_application' => 'required',
        'can_issue_policy' => 'required',
        'rule_start_date' => 'required',
        'rule_end_date' => 'required',
        'rules_status' => 'required'
    ];
    protected $messages = [
        'rule_for.*.required' => 'The rule for field is required.',
        'formula_expression.*.required' => 'The formula expression field is required.',
        'value.*.required' => 'The value field is required.',
        'value_to.*.required' => 'The value to field is required.',
    ];

    public function addRow($i)
    {
        $i = $i + 1;
        $this->i = $i;
        array_push($this->inputs, $i);
    }

    public function remove($i)
    {
        unset($this->inputs[$i]);
    }


    public function submit(){
        foreach ($this->inputs as $index => $value){
            $this->rules["rule_for.$index"] = 'required';
            $this->rules["formula_expression.$index"] = 'required';
            $this->rules["value.$index"] = 'required';
            $this->rules["value_to.$index"] = 'required'; //$this->showValueToInput[$index] ? 'required' : '';

        }

        $validatedData = $this->validate();
        $validation_rule = new ValidationRuleMaster();

        // on the column description there is a comment called `dont know what this for`
        // $validation_rule->s_Screen = Null;
        // $validation_rule->s_Combination = Null;


        $validation_rule->s_RuleCode = $this->rule_code;
        $validation_rule->n_Product_FK = $this->product_id;
        $validation_rule->s_ScreenErrorMsg = $this->screen_error_msg;
        $validation_rule->s_Description = $this->rule_description;
        $validation_rule->s_RuleApplyOn = $this->rule_apply_on;


        $validation_rule->s_CanRate = $this->can_rate_policy;
        $validation_rule->s_CanPrintQuote = $this->can_print_quote;
        $validation_rule->s_CanPrintApp = $this->can_print_application;
        $validation_rule->s_CanBindApp = $this->can_bind_application;
        $validation_rule->s_CanUnBoundApp = $this->can_submit_un_bound_application;
        $validation_rule->s_CanIssue = $this->can_issue_policy;
        // $validation_rule->d_EffectiveDateFrom = $this->rule_start_date;
        // $validation_rule->d_EffectiveDateTo = $this->rule_end_date;
        $validation_rule->d_EffectiveDateFrom = Carbon::createFromFormat(config('constants.date.format'),$this->rule_start_date);
        $validation_rule->d_EffectiveDateTo = Carbon::createFromFormat(config('constants.date.format'),$this->rule_end_date);
        $validation_rule->s_RuleStatus = $this->rules_status ? 'ACTIVE' : 'INACTIVE';


        if ($validation_rule->save()){
            foreach ($this->inputs as $index => $value){
                $rule_detail = new ValidationRuleDetail();
                $rule_detail->n_PrValidationRuleMaster_FK = $validation_rule->n_PrValidationRuleMaster_PK;

                // n_PrValidationCodeMasters_FK -> alpharicvggroup.n_Id_PK
                // (i.e. the reinsurance coverage group this rule applies to,
                // selected via the 'Rule For' dropdown which is populated
                // from getAlpharicvggroups()).
                $rule_detail->n_PrValidationCodeMasters_FK = $this->rule_for[$index];

                $rule_detail->s_FormulaExpression = $this->formula_expression[$index];
                $rule_detail->s_CompareValue = $this->value[$index];
                $rule_detail->s_CompareValueBetween = $this->value_to[$index] ?? null;
                $rule_detail->save();
            }
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Validation Rule')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Validation Rule Added - '.$this->rule_code);
            session()->flash('success', "Validation Rule Created Successfully");
            return Redirect::route('validationrule');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    /*
        Get All Product
    */
    public function getProduct(){
        return Product::select('id','name')->activated()->get()->pluck('name','id')->toArray();
    }

    /*
        Get All Rules Applid On
    */
    public function getRulesApplidOn(){
        return ['TERMSTARTDATE'=>'Term Start Date','TRANSACTIONSTARTDATE'=>'Transaction Start Date','BOOKINGDATE'=>'Booking Date'];
    }

    /*
        Get All Coverages
    */
    public function getCoverages(){
        return Coverage::select('id','name')->activated()->get()->pluck('name','id')->toArray();
    }

    /*
        Get All Alpharicvggroups
    */
    public function getAlpharicvggroups(){
        return Alpharicvggroup::select('n_Id_PK','s_GroupName')->activated()->pluck('s_GroupName','n_Id_PK');
    }

    /*
        Get All Formula Expression
    */
    public function getFormulaExpression(){
        return [
            '61' => '=',
            '60' => '<',
            '8804' => '<=',
            '62' => '>',
            '8805' => '>=',
            '8800' => '!=',
            '8801' => 'Between',
            '8802' => 'Not Between'
        ];
    }

    public function mount()
    {

    }


    // public function formulaExpressionChanged($index)
    // {
    //     $expression = $this->formula_expression[$index];
    //     if ($expression === '8801' || $expression === '8802') {
    //         $this->showValueToInput[$index] = true;
    //     } else {
    //         $this->showValueToInput[$index] = false;
    //         $this->value_to[$index] = null;
    //     }
    // }



    public function getYesNoArray(){
        return ['Y' => 'Yes' , 'N' => 'No' ];
    }

    public function render()
    {
        return view('v2.livewire.validation-rule.add')->layout('layouts.app-v2');
    }
}
