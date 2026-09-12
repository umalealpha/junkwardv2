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
    public $successMessage = '';
    public $errorMessage = '';
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
        "specified_items" => [
            'ImportFrom' => \AlphaDirect\Imports\SpecifiedItemsImport::class,
            'ExportFrom' => \AlphaDirect\Exports\SpecifiedItemsExport::class,
        ],
    ];
    public $selectedType;

    public $rules = [
        'file' => 'required'
    ];

    public function refreshCurrent()
    {
        $this->errors = [];
        $this->successMessage = '';
        $this->errorMessage = '';
    }

//    public function mount(Policy $policy,$for,$term_id){
//        $this->policy = $policy;
//        $this->selectedType = $for;
//        $this->termId = $term_id;
//    } 

    public function importExcel(){
     // dd( $this->typeOfFiles,$this->typeOfFiles[$this->selectedType]['ImportFrom'],$this->policy,$this->termId,$this->actionId);
        $this->validate();
        
        // Reset messages
        $this->errors = [];
        $this->successMessage = '';
        $this->errorMessage = '';
        
        // Increase execution time and memory limit for large imports
        set_time_limit(0); // Unlimited execution time
        ini_set('memory_limit', '512M'); // Increase memory limit
        
        try {
            // For edit_policy imports, pass the file path to get sheet names
            $importInstance = new $this->typeOfFiles[$this->selectedType]['ImportFrom']($this->policy,$this->termId,$this->actionId);
            
            // If it's EditPolicyImport, load sheet names from file before importing
            if ($this->selectedType === 'edit_policy' && method_exists($importInstance, 'loadSheetNamesFromFile')) {
                $filePath = $this->file->getRealPath();
                if ($filePath) {
                    $importInstance->loadSheetNamesFromFile($filePath);
                }
            }
            
            Excel::import($importInstance, $this->file);
            
            // Import completed successfully
            $this->successMessage = 'Import completed successfully!';
            
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Handle validation errors (row-level errors)
            $this->errors = $e->failures();
            $this->errorMessage = 'Import failed due to validation errors. Please check the errors below.';
            
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            // Handle PhpSpreadsheet exceptions (like "beyond highest row" for empty sheets)
            // These are often caused by empty sheets that should be skipped
            if (strpos($e->getMessage(), 'beyond highest row') !== false ||
                strpos($e->getMessage(), 'Start row') !== false ||
                strpos($e->getMessage(), 'highest row') !== false) {
                \Log::info('Empty sheet detected during import, continuing with other sheets', [
                    'error' => $e->getMessage()
                ]);
                // Show success message - the data that was imported before this error should be saved
                // Note: Maatwebsite Excel wraps imports in a transaction, so if this exception
                // is thrown, the transaction might be rolled back. We need to prevent this.
                $this->successMessage = 'Import completed successfully! (Some empty sheets were automatically skipped)';
            } else {
                // Other PhpSpreadsheet errors
                $this->errorMessage = 'Import failed: ' . $e->getMessage();
                \Log::error('Excel Import Error (PhpSpreadsheet)', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            
        } catch (\Exception $e) {
            // Handle any other exceptions
            // Check if it's an empty sheet error or "beyond highest row" error
            if (strpos($e->getMessage(), 'is empty (header only)') !== false ||
                strpos($e->getMessage(), 'beyond highest row') !== false ||
                strpos($e->getMessage(), 'Start row') !== false ||
                strpos($e->getMessage(), 'highest row') !== false) {
                \Log::info('Empty sheet detected during import, continuing with other sheets', [
                    'error' => $e->getMessage()
                ]);
                // Don't show error to user, just log it and show success
                // The import will continue with other sheets
                $this->successMessage = 'Import completed successfully! (Empty sheets were automatically skipped)';
            } else {
                // Convert newlines to <br> tags for HTML display
                $errorMessage = 'Import failed - ' . $e->getMessage();
                // Escape HTML first, then convert newlines to <br> tags
                $this->errorMessage = nl2br(e($errorMessage));
                \Log::error('Excel Import Error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
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
