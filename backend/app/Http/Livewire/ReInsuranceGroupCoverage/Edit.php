<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceGroupCoverage;

use Livewire\Component;
use AlphaDirect\Models\ReinsuranceGroup;
use AlphaDirect\Models\ReinsuranceGroupCoverage;
use AlphaDirect\Product;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Models\PolicyCoverage;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Redirect;
use DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

use AlphaDirect\Customer;
class Edit extends Component
{
    public ReinsuranceGroup $reinsurancegroup;
    public ReinsuranceGroupCoverage $reinsurancegroupcoverage;
    public $colSize = "col-md-3";
    public $seletedCoverages = [];

    protected $rules = [
        'reinsurancegroup.group_code' => 'required|unique:reinsurance_group,group_code',
        'reinsurancegroup.group_name' => 'required',
        'reinsurancegroup.product_id' => 'required',
        'reinsurancegroup.status' => '',
    ];

    public function mount($id){
        $this->reinsurancegroup = ReinsuranceGroup::find($id);
        $this->reinsurancegroup->status = ($this->reinsurancegroup->status==1)?true:false;
        $this->seletedCoverages = ProductCoverage::getProductCoverages($this->reinsurancegroup->product_id);
        \Log::info('Mount: Product ID = ' . $this->reinsurancegroup->product_id . ', Coverages = ' . count($this->seletedCoverages));
        \Log::info('Selected Coverages Data:', ['coverages' => $this->seletedCoverages, 'count' => count($this->seletedCoverages)]);
    }

    public function submit($formData){
        $this->rules['reinsurancegroup.group_code']= ['required', Rule::unique('reinsurance_group','group_code')->ignore($this->reinsurancegroup->id)];
        //dd($formData);
        $MOTORTRADERSEXTERNAL = [];
        $MOTORTRADERSEXTERNAL['coverage_name'][] = $formData['comprehensive_coverage_name_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['si_premium'][] = $formData['comprehensive_si_premium_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['ri_limit'][] = $formData['comprehensive_ri_limit_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['limit_value'][] = $formData['comprehensive_limit_value_motro_traders_ext'] ?? "";

        $MOTORTRADERSEXTERNAL['coverage_name'][] = $formData['third_party_only_coverage_name_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['si_premium'][] = $formData['third_party_only_si_premium_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['ri_limit'][] = $formData['third_party_only_ri_limit_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['limit_value'][] = $formData['third_party_only_limit_value_motro_traders_ext'] ?? "";

        $MOTORTRADERSEXTERNAL['coverage_name'][] = $formData['Third_fire_and_theft_coverage_name_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['si_premium'][] = $formData['Third_fire_and_theft_si_premium_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['ri_limit'][] = $formData['Third_fire_and_theft_ri_limit_motro_traders_ext'] ?? "";
        $MOTORTRADERSEXTERNAL['limit_value'][] = $formData['Third_fire_and_theft_limit_value_motro_traders_ext'] ?? "";


        $MOTORTRADERSINTERNAL = [];
        $MOTORTRADERSINTERNAL['coverage_name'][] = $formData['comprehensive_coverage_name_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['si_premium'][] = $formData['comprehensive_si_premium_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['ri_limit'][] = $formData['comprehensive_ri_limit_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['limit_value'][] = $formData['comprehensive_limit_value_motor_traders_int'] ?? "";

        $MOTORTRADERSINTERNAL['coverage_name'][] = $formData['third_party_only_coverage_name_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['si_premium'][] = $formData['third_party_only_si_premium_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['ri_limit'][] = $formData['third_party_only_ri_limit_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['limit_value'][] = $formData['third_party_only_limit_value_motor_traders_int'] ?? "";

        $MOTORTRADERSINTERNAL['coverage_name'][] = $formData['Third_fire_and_theft_coverage_name_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['si_premium'][] = $formData['Third_fire_and_theft_si_premium_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['ri_limit'][] = $formData['Third_fire_and_theft_ri_limit_motor_traders_int'] ?? "";
        $MOTORTRADERSINTERNAL['limit_value'][] = $formData['Third_fire_and_theft_limit_value_motor_traders_int'] ?? "";
        
        $COMMERCIALMOTOR = [];
        $COMMERCIALMOTOR['coverage_name'][] = $formData['comprehensive_coverage_name_motor_comm'] ?? "";
        $COMMERCIALMOTOR['si_premium'][] = $formData['comprehensive_si_premium_motor_comm'] ?? "";
        $COMMERCIALMOTOR['ri_limit'][] = $formData['comprehensive_ri_limit_motor_comm'] ?? "";
        $COMMERCIALMOTOR['limit_value'][] = $formData['comprehensive_limit_value_motor_comm'] ?? "";

        $COMMERCIALMOTOR['coverage_name'][] = $formData['third_party_only_coverage_name_motor_comm'] ?? "";
        $COMMERCIALMOTOR['si_premium'][] = $formData['third_party_only_si_premium_motor_comm'] ?? "";
        $COMMERCIALMOTOR['ri_limit'][] = $formData['third_party_only_ri_limit_motor_comm'] ?? "";
        $COMMERCIALMOTOR['limit_value'][] = $formData['third_party_only_limit_value_motor_comm'] ?? "";

        $COMMERCIALMOTOR['coverage_name'][] = $formData['Third_fire_and_theft_coverage_name_motor_comm'] ?? "";
        $COMMERCIALMOTOR['si_premium'][] = $formData['Third_fire_and_theft_si_premium_motor_comm'] ?? "";
        $COMMERCIALMOTOR['ri_limit'][] = $formData['Third_fire_and_theft_ri_limit_motor_comm'] ?? "";
        $COMMERCIALMOTOR['limit_value'][] = $formData['Third_fire_and_theft_limit_value_motor_comm'] ?? "";


        $FIDELITYGUARANTEE = [];
        $FIDELITYGUARANTEE['coverage_name'][] = $formData['blanket_coverage_name_fidelityguarantee'] ?? "";
        $FIDELITYGUARANTEE['si_premium'][] = $formData['blanket_si_premium_fidelityguarantee'] ?? "";
        $FIDELITYGUARANTEE['ri_limit'][] = $formData['blanket_ri_limit_fidelityguarantee'] ?? "";
        $FIDELITYGUARANTEE['limit_value'][] = $formData['blanket_limit_value_fidelityguarantee'] ?? "";

        $FIDELITYGUARANTEE['coverage_name'][] = $formData['name_and_position_coverage_name_fidelityguarantee'] ?? "";
        $FIDELITYGUARANTEE['si_premium'][] = $formData['name_and_position_si_premium_fidelityguarantee'] ?? "";
        $FIDELITYGUARANTEE['ri_limit'][] = $formData['name_and_position_ri_limit_fidelityguarantee'] ?? "";
        $FIDELITYGUARANTEE['limit_value'][] = $formData['name_and_position_limit_value_fidelityguarantee'] ?? "";


        request()->merge($formData);
        $selectedSubCoverage = CoverageMaster::select('id','s_ScreenName','s_CoverageCode')->whereIn('s_ParentCoverageCode',array_filter(array_values($this->seletedCoverages)))->where('s_UsageType', 'CHILD')->get();
        $data = [];

        $this->withValidator(function (Validator $validator) use($selectedSubCoverage) {
            $validator->after(function ($validator) use($selectedSubCoverage){
                foreach($selectedSubCoverage as $k){
                    #validation
                    if(request()->get($k->id."_FIELD4")!=""){
                        if(!is_numeric(request()->get($k->id."_FIELD4"))){
                            $validator->errors()->add($k->id."_FIELD4", $k->id." must be a numeric");
                        }
                    }
                }
            });
        })->validate();

        $this->reinsurancegroup->group_name = $this->reinsurancegroup->group_name;
        $this->reinsurancegroup->group_code = $this->reinsurancegroup->group_code;
        $this->reinsurancegroup->product_id = $this->reinsurancegroup->product_id;
        $this->reinsurancegroup->status = ($this->reinsurancegroup->status==true)?1:null;
        if ($this->reinsurancegroup->save()){
            if (!empty($selectedSubCoverage)){
                foreach($selectedSubCoverage as $k){
                    if(request()->get($k->id."_FIELD1")!=""){
                        $data[$k->id]['group_id']=$this->reinsurancegroup->id;
                        $data[$k->id]['coverage_id']=$k->id;
                        $data[$k->id]['coverage_name']=$k->s_CoverageCode ?? "";
                        $data[$k->id]['si_premium']=request()->get($k->id."_FIELD1");
                        $data[$k->id]['ri_limit']=request()->get($k->id."_FIELD2");
                        $data[$k->id]['limit_value']=request()->get($k->id."_FIELD3");
                    }
                }
            }
            if (!empty($data)){
                DB::table('reinsurance_group_coverage')->where('group_id', $this->reinsurancegroup->id)->delete();
                ReinsuranceGroupCoverage::insert($data);
            }
            //dd(count($COMMERCIALMOTOR));
            if(count($MOTORTRADERSEXTERNAL) > 0)
            {   
                
                    for($i=0; $i <= 2; $i++)
                    {
                        if($MOTORTRADERSEXTERNAL['si_premium'][$i] ?? "" != '')
                        {
                            $data[$i]['group_id']=$this->reinsurancegroup->id;
                            $data[$i]['coverage_id']= 15 ;
                            $data[$i]['coverage_name']=$MOTORTRADERSEXTERNAL['coverage_name'][$i] ?? "";
                            $data[$i]['si_premium']= $MOTORTRADERSEXTERNAL['si_premium'][$i] ?? "";
                            $data[$i]['ri_limit']=$MOTORTRADERSEXTERNAL['ri_limit'][$i] ?? "";
                            $data[$i]['limit_value']=$MOTORTRADERSEXTERNAL['limit_value'][$i] ?? "";
                        }
                        
                    }                
                    ReinsuranceGroupCoverage::insert($data);
            }
            if(count($MOTORTRADERSINTERNAL) > 0)
            {   
                
                    for($i=0; $i <= 2; $i++)
                    {
                        if($MOTORTRADERSINTERNAL['si_premium'][$i] ?? "" != '')
                        {
                            $data[$i]['group_id']=$this->reinsurancegroup->id;
                            $data[$i]['coverage_id']=16;
                            $data[$i]['coverage_name']=$MOTORTRADERSINTERNAL['coverage_name'][$i] ?? "";
                            $data[$i]['si_premium']=$MOTORTRADERSINTERNAL['si_premium'][$i] ?? "";
                            $data[$i]['ri_limit']=$MOTORTRADERSINTERNAL['ri_limit'][$i] ?? "";
                            $data[$i]['limit_value']=$MOTORTRADERSINTERNAL['limit_value'][$i] ?? "";
                        }
                    }                
                    ReinsuranceGroupCoverage::insert($data);
            }
             if(count($COMMERCIALMOTOR) > 0)
            {   
                
                    for($i=0; $i <= 2; $i++)
                    {
                        if($COMMERCIALMOTOR['si_premium'][$i] ?? "" != '')
                        {
                            $data[$i]['group_id']=$this->reinsurancegroup->id;
                            $data[$i]['coverage_id']= 22;
                            $data[$i]['coverage_name']=$COMMERCIALMOTOR['coverage_name'][$i] ?? "";
                            $data[$i]['si_premium']=$COMMERCIALMOTOR['si_premium'][$i] ?? "";
                            $data[$i]['ri_limit']=$COMMERCIALMOTOR['ri_limit'][$i] ?? "";
                            $data[$i]['limit_value']=$COMMERCIALMOTOR['limit_value'][$i] ?? "";
                        }
                    }                
                    ReinsuranceGroupCoverage::insert($data);
            }

            if(count($FIDELITYGUARANTEE) > 0)
            {   
                
                    for($i=0; $i <= 2; $i++)
                    {
                        if($FIDELITYGUARANTEE['si_premium'][$i] ?? "" != '')
                        {
                            $data[$i]['group_id']=$this->reinsurancegroup->id;
                            $data[$i]['coverage_id']= 9 ;
                            $data[$i]['coverage_name']=$FIDELITYGUARANTEE['coverage_name'][$i] ?? "";
                            $data[$i]['si_premium']= $FIDELITYGUARANTEE['si_premium'][$i] ?? "";
                            $data[$i]['ri_limit']=$FIDELITYGUARANTEE['ri_limit'][$i] ?? "";
                            $data[$i]['limit_value']=$FIDELITYGUARANTEE['limit_value'][$i] ?? "";
                        }
                        
                    }                
                    ReinsuranceGroupCoverage::insert($data);
            }
            DB::commit();
                $customer = Customer::where('id', auth()->user()->id)->first();
                activity('Re-Insurance Group Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Group Coverage Updated - '.$this->reinsurancegroup->id);
            session()->flash('success', "Re-Insurance group coverage type has been successfully updated.");
            return Redirect::route('reinsurance-group-coverage');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }


    public function getProductsProperty(){
        return Product::ProductsName();
    }

    public function updatedReinsurancegroupProductId($value)
    {
        $productId = $this->reinsurancegroup['product_id'];
        $this->emit('Products', $productId);
        $this->seletedCoverages = ProductCoverage::getProductCoverages($productId);
        \Log::info('Product updated: Product ID = ' . $productId . ', Coverages = ' . count($this->seletedCoverages));
    }

    public function getProductSubCoverages($coverageCode){
        // The CoverageMaster has heavily duplicated CHILD rows (e.g. FIRE
        // "Buildings" exists 54x, all with policy_id NULL). Collapse to one
        // row per screen name (keeping the lowest id) so the edit grid shows
        // each sub-coverage once instead of dozens of empty duplicates.
        return CoverageMaster::select('id','s_ScreenName','s_CoverageCode')
        ->where('s_ParentCoverageCode',$coverageCode)
        ->where('s_UsageType', 'CHILD')
        ->whereNull('policy_id')
        ->orderBy('id')
        ->get()
        ->unique('s_ScreenName')
        ->values();
    }

    public function getSubCoverageData($group_id,$subCoverge_id,$coverageCode = null)
    {
        // Match the saved row by coverage_id, but fall back to coverage_name
        // (s_CoverageCode) so values saved against a different duplicate master
        // id still re-select on edit. Most recent row wins if duplicated.
        return ReinsuranceGroupCoverage::where('group_id',$group_id)
            ->where(function($q) use ($subCoverge_id,$coverageCode){
                $q->where('coverage_id',$subCoverge_id);
                if(!empty($coverageCode)){ $q->orWhere('coverage_name',$coverageCode); }
            })
            ->orderByDesc('id')
            ->first();
    }
    public function getSubCoverageDataMotor($group_id,$motorId)
    {
        return ReinsuranceGroupCoverage::where('group_id',$group_id)->where('coverage_id',$motorId)->get()->toArray();
    }

    public function render()
    {
        return view('v2.livewire.re-insurance-group-coverage.edit')->layout('layouts.app-v2');
    }

}
