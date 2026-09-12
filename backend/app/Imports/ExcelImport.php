<?php

namespace AlphaDirect\Http\Livewire\Common;

use AlphaDirect\Policy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ExcelImport extends Component
{
    use WithFileUploads;

    public $file;
    public $policy;
    public $termId;
    public $actionId;
    public $errors = [];
    public $importObject;
    public $exportObject;
    public $exportLinkName;

    public $typeOfFiles = [
        "vehicle" => [
            'ImportFrom' => \AlphaDirect\Imports\VehicleImport::class,
            'ExportFrom' => \AlphaDirect\Exports\VehicleExport::class,
        ],
        "member" => [
            'ImportFrom' => \AlphaDirect\Imports\MemberImport::class,
            'ExportFrom' => \AlphaDirect\Exports\MemberExport::class,
        ],
        "device" => [
            'ImportFrom' => \AlphaDirect\Imports\DeviceImport::class,
            'ExportFrom' => \AlphaDirect\Exports\DeviceExport::class,
        ],
        "risk_address" => [
            'ImportFrom' => \AlphaDirect\Imports\RiskAddressImport::class,
            'ExportFrom' => \AlphaDirect\Exports\RiskAddressExport::class,
        ],
        "edit_policy" => [
            'ImportFrom' => \AlphaDirect\Imports\EditPolicyImport::class,
            'ExportFrom' => \AlphaDirect\Exports\EditPolicyExport::class,
        ],
    ];
    public $selectedType;

    public $rules = [
        'file' => 'required'
    ];

    public function refreshCurrent()
    {
        $this->errors = [];
    }

//    public function mount(Policy $policy,$for,$term_id){
//        $this->policy = $policy;
//        $this->selectedType = $for;
//        $this->termId = $term_id;
//    } 

    public function importExcel(){
     // dd( $this->typeOfFiles,$this->typeOfFiles[$this->selectedType]['ImportFrom'],$this->policy,$this->termId,$this->actionId);
        $this->validate();
        
        // Increase execution time and memory limit for large imports
        set_time_limit(0); // Unlimited execution time
        ini_set('memory_limit', '512M'); // Increase memory limit
        
        try {
            Excel::import(new $this->typeOfFiles[$this->selectedType]['ImportFrom']($this->policy,$this->termId,$this->actionId), $this->file);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $this->errors = $e->failures();
        }

        $this->emitUp('refreshParent',$this->actionId);
    }

    public function downloadExcel(){
        
        return Excel::download(new $this->typeOfFiles[$this->selectedType]['ExportFrom']($this->policy,$this->termId,$this->actionId), $this->selectedType.'.xlsx');
    }

    public function render()
    {
        return view('v2.livewire.common.excel-import');
    }
}
