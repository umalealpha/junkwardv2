<?php

namespace AlphaDirect\Http\Livewire\Coverage\SpecifiedCoverage;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Edit extends Component
{
    Public  $specidied_coverages;
    public  $effective_from;
    public  $effective_to;

    protected $rules = [
        'specidied_coverages.specified_code' => 'required',
        'specidied_coverages.specified_name' => 'required',
        'specidied_coverages.rate' => 'required',
        'effective_from' => 'required',
        'effective_to' => 'required',
        'specidied_coverages.coverage_id' => 'required',
        // 'specidied_coverages.added_by' => 'required'
    ];

    protected $validationAttributes = [
        'specidied_coverages.coverage_id' => 'coverage',
    ];

    public function getCoveragesMasterProperty(){
		return CoverageMaster::select('id','s_CoverageName')->mainCoverageOnly()
		->get()->keyBy('id')
        ->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->s_CoverageName
            ];
        });
	}

    public function mount($id){
        $this->specidied_coverages = SpecifiedCoveragesItems::find($id);
        $this->effective_from = (new Carbon($this->specidied_coverages->effective_from))->format(config('constants.date.format'));
        $this->effective_to = (new Carbon($this->specidied_coverages->effective_to))->format(config('constants.date.format'));
    }

    public function submit(){
        // dd($this);
        $this->validate();
        // dd($this->effective_from);
        $this->specidied_coverages->effective_from = Carbon::createFromFormat(config('constants.date.format'),$this->effective_from);
        $this->specidied_coverages->effective_to = Carbon::createFromFormat(config('constants.date.format'),$this->effective_to);
        if ($this->specidied_coverages->save()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Specified Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Specified Coverage Updated - '.$this->specidied_coverages->specified_name);
                
            session()->flash('success', "Specidied Coverage Updated Successfully");
            return Redirect::route('specifiedcoverage');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
        return view('v2.livewire.coverage.specified-coverage.edit')->layout('layouts.app-v2');
    }
}
