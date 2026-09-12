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

class Add extends Component
{
    public  $specidied_coverages;
    public  $effective_from;
    public  $effective_to;

    protected $rules = [
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

    public function mount(){
        $this->specidied_coverages = new SpecifiedCoveragesItems();
    }

    public function submit(){
        $this->validate();
        $this->specidied_coverages->effective_from = Carbon::createFromFormat(config('constants.date.format'),$this->effective_from);
        $this->specidied_coverages->effective_to = Carbon::createFromFormat(config('constants.date.format'),$this->effective_to);
        $this->specidied_coverages->rate = (float)(str_replace(',', '', ($this->specidied_coverages->rate??''))) ?? 0;
        if ($this->specidied_coverages->save()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Specified Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Specified Coverage Added - '.$this->specidied_coverages->specified_name);
            session()->flash('success', "Specidied Coverage Created Successfully");
            return Redirect::route('specifiedcoverage');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
        return view('v2.livewire.coverage.specified-coverage.add')->layout('layouts.app-v2');
    }
}
