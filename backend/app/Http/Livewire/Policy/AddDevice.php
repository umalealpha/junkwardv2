<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\DeviceMakeModel;
use AlphaDirect\PolicyCellPhone;
use Livewire\Component;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\User;

class AddDevice extends Component
{
    public $policy;
    public $D_isUpdate = false;
    public $deviceData;
    public $isPrevious = false;
    public $termId;
    public $actionId;
    public $editable = true;

    public $phoneMakes = [];
    public $phoneModels = [];


    protected $rules = [
        'deviceData.device_type' => 'required',
        'deviceData.imei' => 'required',
        'deviceData.cell_phone_make' => 'required',
        'deviceData.cell_phone_model' => 'required',
        'deviceData.phone_value' => 'required',
        'deviceData.risk_id' => 'required',
    ];

    protected $listeners = [ 'refreshParent'  => '$refresh','triggerDeviceDelete'];

    public function mount(){
        $this->deviceData = new PolicyCellPhone();
    }

    public function render()
    {
        return view('v2.livewire.policy.add-device');
    }


    public function getallDevicesProperty(){
        return PolicyCellPhone::policy($this->policy->id)->term($this->termId)->action($this->actionId)->get();
    }

    public function getallRiskAddress(){
		return RiskAddress::Policy($this->policy->id)->action($this->actionId)->get()->keyBy('id')->map(function($riskaddress){
			return [
				'id'=>$riskaddress->id,
				'name'=>$riskaddress->address_name
			];
		});
	}

    public function addDevice(){
        $this->validate();
        $this->deviceData->policy_id= $this->policy->id;
        $this->deviceData->customer_id= $this->policy->customer_id;
        $this->deviceData->term_id = $this->termId;
        $this->deviceData->action_id = $this->actionId;
        $this->deviceData->phone_value = (float)(str_replace(',', '', ($this->deviceData->phone_value ??''))) ?? 0;
        $this->deviceData->save();
        if($this->D_isUpdate){

            activity('Device')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Device Details Updated');

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Device Details Updated Successfully!']);
        }else{

            activity('Device')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Device Details Added');

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Device Details Added Successfully!']);
        }
        $this->deviceData= new PolicyCellPhone();
        $this->D_isUpdate=false;
        $this->render();
    }

    public function deviceDetailsedit($id){
        $this->deviceData= $dd= PolicyCellPhone::find(\Crypt::decrypt($id));
        $this->updatedDeviceData($this->deviceData->device_type,'device_type');
        $this->updatedDeviceData($this->deviceData->cell_phone_make,'cell_phone_make');
        $this->D_isUpdate=true;
    }

    public function triggerDeviceDelete($id){
        if (PolicyCellPhone::find($id)->delete()){
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Device Details Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }

        activity('Device')
        ->performedOn($this->policy)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Device Details Deleted');

        $this->deviceDetailsEditCancel();
        $this->render();
    }

    public function deviceDetailsEditCancel(){
        $this->deviceData= new PolicyCellPhone();
        $this->D_isUpdate=false;
    }

    public function updatedDeviceData($value, $key){
        if($key=="device_type"){
            $data=[];
            $data= DeviceMakeModel::where('make_id',null)
                ->where('device_type','=',$value)
                ->get()
                ->keyBy('name')->map(function($d){
                    return [
                        'id'=>$d->name,
                        'name'=>$d->name
                    ];
                });
            $this->phoneMakes = $data;
            $this->dispatchBrowserEvent('dropdown-changed',[
                'key'=>'cell_phone_make',
                'data'=>$data,
                'selected_id' => $this->deviceData->cell_phone_make
            ]);

        }

        if($key=="cell_phone_make"){
            $data=[];
            $make_id =  DeviceMakeModel::where('name','=',$value)
                ->where('device_type',$this->deviceData->device_type)
                ->first('id');

            $data= DeviceMakeModel::where('make_id','=',$make_id->id)
                ->get()
                ->keyBy('name')->map(function($d){
                    return [
                        'id'=>$d->name,
                        'name'=>$d->name
                    ];
                });
            $this->phoneModels = $data;
            $this->dispatchBrowserEvent('dropdown-changed',[
                'key'=>'cell_phone_model',
                'data'=>$data,
                'selected_id' => $this->deviceData->cell_phone_model
            ]);
        }
    }


    public function saveStep6(){
        // $this->addDevice();
        $this->emitUp('updateHasStatus');
    }

    public function backToStep5(){
        $this->emitUp('backToStep5');
    }
}
