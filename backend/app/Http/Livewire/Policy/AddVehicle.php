<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Models\MotorType;
use AlphaDirect\Vehicle;
use AlphaDirect\Policy;
use AlphaDirect\VehicleMake;
use Livewire\Component;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\User;
use AlphaDirect\Models\Motor;
use AlphaDirect\Models\PolicyAction;
use DB;
class AddVehicle extends Component
{
    public $policy;
    public $V_isUpdate = false;
    public $isPrevious = false;
    public $vehicleData;
    public $motorData;
    public $termId;
    public $actionId;
    public $editable = true;
    public $allMotorType;

    public $vechicleModels;
    public $newPlate, $plateChangeComment;
    public $showApprovalModal = false;
    public $approvalPlate = null;
    public $approvalComment = null;
    protected $rules = [
        'vehicleData.vehiclePlate' => '',//'required|max:7',
        'vehicleData.chassisNo' => '',
        'vehicleData.engineNo' => '',
        'vehicleData.seats' => '',//'required',
        'vehicleData.is_imported' => '',
        'vehicleData.year' => 'required',
        'vehicleData.make' => '',
        'vehicleData.model' => 'required',
        'vehicleData.estimated_value' => 'required',
        'vehicleData.claim_count' => 'required',
        'vehicleData.is_imported' => '',
        'vehicleData.make' => '',
        'vehicleData.model' => '',
        'vehicleData.risk_id' => 'required',
        'vehicleData.vehicle_type' => 'required',
        'vehicleData.make_id' => 'required|integer', // ✅ new
        'vehicleData.model_id' => 'required|integer',
    ];
    protected $listeners = ['refreshParent' => '$refresh', 'triggerVehicleDelete'];

    protected $messages = [
        'vehicleData.estimated_value.required_if' => 'estimated value of vehicle is required',
        'vehicleData.claim_count.required_if' => 'no of accidents field is required',
    ];
    public function mount()
    {
        $this->vehicleData = new Vehicle();

        $this->allMotorType = MotorType::where('product_id', $this->policy->product_id)->get()->keyBy('id')->map(function ($d) {
            return [
                'id' => $d->id,
                'name' => $d->motor_name
            ];
        });
    }
    public function render()
    {
        return view('v2.livewire.policy.add-vehicle');
    }

    public function getallVehicles()
    {
        return Vehicle::PolicyId($this->policy->id)->TermId($this->termId)->ActionId($this->actionId)->whereNull('deleted_at')->
        where(function ($q) {
        $q->where('approval_status', 'APPROVED')
          ->orWhereNull('approval_status');
        })->get();
    }

    public function getallRiskAddress()
    {
        return RiskAddress::Policy($this->policy->id)->action($this->actionId)->get()->keyBy('id')->map(function ($riskaddress) {
            return [
                'id' => $riskaddress->id,
                'name' => $riskaddress->address_name
            ];
        });
    }


    public function addVehicle()
    {
        // Clear old errors
        $this->resetErrorBag();
        $this->resetValidation();

        // Set common fields
        $this->vehicleData->policy_id = $this->policy->id;
        $this->vehicleData->customer_id = $this->policy->customer_id;

        $plate = $this->vehicleData->vehiclePlate;
        $makeName = VehicleMake::find($this->vehicleData['make_id'])->s_Make ?? null;
        $this->vehicleData->make = $makeName;
        $modelName = VehicleMake::find($this->vehicleData['model_id'])->s_Variant ?? null;
        $this->vehicleData->model = $modelName;
        //dd($this->vehicleData);
        /*
        |--------------------------------------------------------------------------
        | CASE 1: ADD NEW VEHICLE  (NO ID)
        --------------------------------------------------------------------------
        */
        if (!$this->V_isUpdate) {

            // 0. Block the SAME plate being added twice under the SAME policy + action
            //    (covers re-adding under a different risk address). To move a vehicle to
            //    another risk address it must first be removed from the current one.
            $sameActionVehicle = Vehicle::duplicateOnPolicyAction(
                $plate,
                $this->policy->id,
                $this->actionId
            );

            if ($sameActionVehicle) {
                $existingRiskName = optional(RiskAddress::find($sameActionVehicle->risk_id))->address_name
                    ?? 'another risk address';
                $this->dispatchBrowserEvent('alert', [
                    'type' => 'error',
                    'message' => 'Vehicle ' . $plate . ' is already added under risk address "' . $existingRiskName
                        . '" on this policy. A vehicle cannot be on two risk addresses. To move it, remove it from "'
                        . $existingRiskName . '" first, then add it under the new risk address.'
                ]);
                return;
            }

            // 1. Validate Plate Format ONLY when adding
            // if (!preg_match('/^[Bb]{1}\d{3}[A-Za-z]{3}$/', $plate)) {
            //     $this->addError('vehicleData.vehiclePlate', 'Invalid vehicle plate format. Example: B123ABC');
            //     return;
            // }
                $bwRegex = '/^[Bb]{1}\d{3}[A-Za-z]{3}$/';

            if (!preg_match($bwRegex, $plate)) {

                // 🚫 This is NON-BW vehicle
                // Check if manager already approved
                $vehicle = Vehicle::where('vehiclePlate', $plate)->whereNull('deleted_at')->first();

                if ($vehicle) {

                    // 1️⃣ Already approved → already used
                    // if ($vehicle->approval_status === 'APPROVED') {
                    //     $this->dispatchBrowserEvent('alert', [
                    //         'type' => 'error',
                    //         'message' => 'This vehicle is already used under another policy Id.'.$vehicle->policy_id.' Please verify and try again.'
                    //     ]);
                    //     return;
                    // }

                    // 2️⃣ Exists but not approved → pending
                    if ($vehicle->approval_status === 'PENDING') {
                        $this->dispatchBrowserEvent('alert', [
                            'type' => 'info',
                            'message' => 'Approval request is already pending for this vehicle and Policy Id is '.$vehicle->policy_id.'. Please verify and try again.'
                        ]);
                        return;
                    }
                }

                // 3️⃣ Vehicle not exists → open approval popup
                $this->approvalPlate = $plate;

                $this->dispatchBrowserEvent('open-modal', [
                    'id' => 'VehicleModal'
                ]);

            }

            $currentAction = PolicyAction::where('policy_id', $this->vehicleData->policy_id)
                ->where('id', $this->actionId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();
            $plate = $this->vehicleData->vehiclePlate;                     // vehicle no
            $currentPolicyId = $this->vehicleData->policy_id;                   // current policy id
            $newEffFrom = $currentAction->effective_from;               // new coverage start date
            $newEffTo = $currentAction->effective_to;                 // new coverage end date

            $result = $this->validateVehicleForNewPolicy(
                $plate,
                $currentPolicyId,
                $newEffFrom,
                $newEffTo
            );
            //dd($result);
            // if function returns message -> error block
            if ($result !== true) {
                return $result; // 🔴 return alertError message
            }
            // 3. ADD validation rules (strict)
            $rules = [
                'vehicleData.vehiclePlate' => 'required',
                'vehicleData.make' => 'required',
                'vehicleData.model' => 'required',
                'vehicleData.seats' => 'required|numeric|min:1',
                'vehicleData.engineNo' => 'nullable',
            ];

            $this->validate($rules);

            if (!preg_match('/^[Bb]{1}\d{3}[A-Za-z]{3}$/', $plate)) {

                    $approved = Vehicle::where('vehiclePlate', $plate)
                        ->where('approval_status', 'APPROVED')
                        ->exists();

                    if (!$approved) {
                        return;
                    }
                }
        }


        /*
        |--------------------------------------------------------------------------
        | CASE 2: UPDATE VEHICLE  (V_isUpdate = true)
        |--------------------------------------------------------------------------
        */ else {

            // ⛔ NO validation for plate format
            // ⛔ NO checking duplicate vehicle across policies
            // ⛔ Only basic fields must be present

            $rules = [
                'vehicleData.make' => 'required',
                'vehicleData.model' => 'required',
                'vehicleData.seats' => 'required|numeric|min:1',
                'vehicleData.engineNo' => 'nullable',
            ];

            $this->validate($rules);
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE VEHICLE
        |--------------------------------------------------------------------------
        */
        unset($this->vehicleData['make_id'], $this->vehicleData['model_id']);
        $this->vehicleData->is_imported = $this->vehicleData->is_imported ? 1 : 0;
        $this->vehicleData->term_id = $this->termId;
        $this->vehicleData->action_id = $this->actionId;
        $this->vehicleData->estimated_value = (float) str_replace(',', '', $this->vehicleData->estimated_value ?? 0);

        $this->vehicleData->save();


        /*
        |--------------------------------------------------------------------------
        | UPDATE MOTOR WHEN EDITING
        |--------------------------------------------------------------------------
        */
        if ($this->vehicleData->id) {

            // update motor only when editing
            if ($this->MotorDataId ?? false) {

                Motor::where('id', $this->MotorDataId)
                    ->update([
                        'make' => $this->vehicleData->make,
                        'model' => $this->vehicleData->model,
                        'engine_number' => $this->vehicleData->engineNo,
                        'chassis_number' => $this->vehicleData->chassisNo,
                        'registration_no' => $this->vehicleData->vehiclePlate,
                        'vehicle_name' => $this->vehicleData->make . " " . $this->vehicleData->model,
                        'estimated_value' => $this->vehicleData->estimated_value,
                    ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LOG + RESPONSE
        |--------------------------------------------------------------------------
        */
        if ($this->V_isUpdate) {

            activity('Vehicle')
                ->performedOn($this->policy)
                ->causedBy(auth()->user())
                ->log('Vehicle Details Updated');

            $this->dispatchBrowserEvent('alert', [
                'type' => 'success',
                'message' => 'Vehicle Details Updated Successfully!'
            ]);

        } else {

            activity('Vehicle')
                ->performedOn($this->policy)
                ->causedBy(auth()->user())
                ->log('Vehicle Details Added');

            $this->dispatchBrowserEvent('alert', [
                'type' => 'success',
                'message' => 'Vehicle Details Added Successfully!'
            ]);
        }

        // reset
        $this->vehicleData = new Vehicle();
        $this->V_isUpdate = false;
        $this->render();
    }
  /**
     * Get the current policy action transaction type for this component's actionId.
     */
    public function getActionTransactionTypeProperty()
    {
        if (!$this->actionId) {
            return null;
        }

        return PolicyAction::find($this->actionId)?->transaction_type;
    }
    public function requestVehicleApproval()
    {
        $this->validate([
            'approvalPlate' => 'required',
            'approvalComment' => 'required|min:5',
        ]);
       // dd($this->approvalPlate, $this->approvalComment, $this->termId, $this->actionId);
        Vehicle::updateOrCreate(
            ['vehiclePlate' => $this->approvalPlate],
            [
                'approval_requested_by' => auth()->id(),
                'approval_request_comment' => $this->approvalComment,
                'approval_status' => 'PENDING',
                'term_id'   => $this->termId,
                'action_id' => $this->actionId,
                'policy_id' => $this->policy->id,
                'customer_id' => $this->policy->customer_id,
            ]
        );

        $this->dispatchBrowserEvent('close-modal', ['id' => 'VehicleModal']);

        $this->dispatchBrowserEvent('alert', [
            'type' => 'info',
            'message' => 'Approval request sent to manager.'
        ]);

        // reset
        $this->approvalPlate = null;
        $this->approvalComment = null;
        $this->dispatchBrowserEvent('reload-page');
    }
    public function getPendingVehiclesProperty()
    {
       return Vehicle::whereIn('vehicle.approval_status', ['PENDING', 'APPROVED'])
                ->join('users as req', 'req.id', '=', 'vehicle.approval_requested_by') // requested by
                ->leftJoin('users as appr', 'appr.id', '=', 'vehicle.approved_by')     // approved by
                ->where('vehicle.policy_id', $this->policy->id)
                ->where('vehicle.action_id', $this->actionId)
                ->whereNull('vehicle.deleted_at')
                ->select(
                    'vehicle.*',
                    'req.firstName as requested_first_name',
                    'req.lastName  as requested_last_name',
                    'appr.firstName as approved_first_name',
                    'appr.lastName  as approved_last_name'
                )
                ->get();
    }
    public function approveVehicle($vehicleId)
{
    // 🔐 Only manager can approve
    // if (!auth()->user()->hasRole('manager')) {
    //     abort(403, 'Unauthorized action.');
    // }

    $vehicle = Vehicle::where('id', $vehicleId)->first();
    // ✅ Already approved check
    if ($vehicle->approval_status === 'APPROVED') {
        
        $this->dispatchBrowserEvent('alert', [
            'type' => 'info',
            'message' => 'Vehicle already approved.'
        ]);
        return;
    }

    // ✅ Approve vehicle
        Vehicle::
            where('id', $vehicle->id)
            ->update([
                'approval_status' => 'APPROVED',
                'approved_at'       => now(),
                'approved_by'       => auth()->id(),
            ]);
    activity('Vehicle')
                ->performedOn($this->policy)
                ->causedBy(auth()->user())
                ->log('Vehicle Details Approved');


    // 🔔 Success alert
    $this->dispatchBrowserEvent('alert', [
        'type' => 'success',
        'message' => 'Vehicle approved successfully.'
    ]);
}


    public function openPlateChangeModal()
    {
        $this->newPlate = $this->vehicleData->vehiclePlate;
        $this->plateChangeComment = '';

        $this->dispatchBrowserEvent('open-modal', ['id' => 'plateChangeModal']);
    }

    public function submitPlateChange()
    {
        //dd('here'.$this->vehicleData->id);
        $this->validate([
            'newPlate' => 'required|regex:/^[Bb]{1}\d{3}[A-Za-z]{3}$/',
            'plateChangeComment' => 'required|min:5',
        ]);

        // $exists = Vehicle::join('policies as p', 'p.id', '=', 'vehicle.policy_id')
        //     ->where('vehicle.vehiclePlate', $this->newPlate)
        //     ->where('vehicle.policy_id', '!=', $this->vehicleData->policy_id)
        //     ->whereNull('vehicle.deleted_at')
        //     ->where('p.status', '!=', 2)   // ❗ Policy must NOT be status 2
        //     ->exists();

        // if ($exists) {
        //     $this->dispatchBrowserEvent('alert', [
        //         'type' => 'error',
        //         'message' => 'This vehicle plate is already linked to another active policy. Please verify and try again.'
        //     ]);
        //     return;
        // }
        $currentAction = PolicyAction::where('policy_id', $this->vehicleData->policy_id)
            ->where('id', $this->actionId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();
        $plate = $this->newPlate;                     // vehicle no
        $currentPolicyId = $this->vehicleData->policy_id;                   // current policy id
        $newEffFrom = $currentAction->effective_from;               // new coverage start date
        $newEffTo = $currentAction->effective_to;                 // new coverage end date


        $result = $this->validateVehicleForNewPolicy(
            $plate,
            $currentPolicyId,
            $newEffFrom,
            $newEffTo
        );

        // if function returns message -> error block
        if ($result !== true) {
            return $result; // 🔴 return alertError message
        }

        $oldVehicle = Vehicle::find($this->vehicleData->id);

        // 1️⃣ Soft Delete Old Record
        $oldVehicle->deleted_at = now();
        $oldVehicle->deleted_by = auth()->id();
        $oldVehicle->plateChangeComment = $this->plateChangeComment;
        $oldVehicle->save();

        // 2️⃣ Clone record with new plate
        $newVehicle = $oldVehicle->replicate();
        $newVehicle->vehiclePlate = $this->newPlate;
        $newVehicle->deleted_at = null;
        $newVehicle->deleted_by = null;
        $newVehicle->plateChangeComment = null;
        $newVehicle->save();

        // 3️⃣ Update Motor Data
        $motorData = Motor::join('policy_coverages as pc', 'pc.id', '=', 'motor.policy_coverage_id')
            ->where('motor.registration_no', $this->vehicleData->vehiclePlate)
            ->where('pc.policy_id', $this->policy->id)
            ->where('pc.action_id', $this->actionId)
            ->select('motor.*') // you can select specific columns if needed
            ->first();
        if ($motorData) {
            $motorData->update([
                'registration_no' => $this->newPlate
            ]);
        }



        // 5️⃣ Refresh Form
        $this->vehicleData = $newVehicle;

        $this->dispatchBrowserEvent('close-modal', ['id' => 'plateChangeModal']);
        activity('Vehicle')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Vehicle Plate Updated');

        $this->dispatchBrowserEvent('alert', [
            'type' => 'success',
            'message' => 'Vehicle plate updated successfully!'
        ]);
    }

    public function validateVehicleForNewPolicy($plate, $currentPolicyId, $newEffFrom, $newEffTo)
    {
        // ------------------- STEP 1: VEHICLE TABLE CHECK ------------------- //
            $latestAction = DB::table('policy_actions')
            ->join('policies as p', 'p.id', '=', 'policy_actions.policy_id')
            ->where('p.status', '!=', 2)
            ->orderBy('policy_actions.effective_from', 'DESC')
            ->select(
                'policy_actions.id',
                'policy_actions.policy_id',
                'policy_actions.effective_from'
            )
            ->first();
            $vehicle = Vehicle::where('vehiclePlate', $plate)
                        ->where('action_id', $latestAction?->id)
                        ->first();

        if ($vehicle && $vehicle->action_id == $this->actionId && $vehicle->deleted_at == null) {
            $this->dispatchBrowserEvent('alert', [
                'type' => 'error',
                'message' => 'This vehicle plate is already linked to same policy ' . $vehicle->policy_id . ' Please verify and try again.'
            ]);
            return false;
        }
        if (!$vehicle) {
            return true; // Safe → No conflict vehicle found
        } else if ($vehicle && $vehicle->deleted_at != null) {
            return true; // Safe → No conflict vehicle found
        } else {

            // ------------------- STEP 2: MOTOR TABLE LAST RECORD CHECK ------------------- //
            $lastMotor = Motor::join('policy_coverages as pc', 'pc.id', '=', 'motor.policy_coverage_id')
                                ->join('policy_actions as pa', function ($join) {
                                    $join->on('pa.policy_id', '=', 'pc.policy_id')
                                        ->whereRaw('pa.effective_from = (
                                            SELECT MAX(pa2.effective_from)
                                            FROM policy_actions pa2
                                            WHERE pa2.policy_id = pc.policy_id
                                        )');
                                })
                                ->where('motor.registration_no', $plate)
                                ->where('pc.policy_id', '!=', $currentPolicyId)
                                ->whereNull('motor.deleted_at')
                                ->whereNull('pa.deleted_at')
                                ->select(
                                    'motor.*',
                                    'pc.policy_id',
                                    'pa.effective_from',
                                    'pa.effective_to',
                                    'pa.status as action_status',
                                    'pa.id as action_id',
                                    'pc.status as pc_status'
                                )
                                ->first();

               // dd($lastMotor);
            // No motor found → safe
            if (!$lastMotor)
                return true;
              // 1️⃣ Policy lapsed → allow (no error)
            if ($lastMotor->action_status === 'LAPSED') {
                return true;
            }

            // If deleted/cancelled → allow new policy
            if ($lastMotor->deleted_at == null && is_null($lastMotor->deleted_at)) {

                $this->dispatchBrowserEvent('alert', [
                    'type' => 'error',
                    'message' => 'This vehicle plate is already linked to another active policy ' . $vehicle->policy_id . ' Please verify and try again.'
                ]);

                return false;
            }
            if ($lastMotor->pc_status == '0') {

                $this->dispatchBrowserEvent('alert', [
                    'type' => 'error',
                    'message' => 'This vehicle plate is already linked to another active policy ' . $vehicle->policy_id . ' Please verify and try again.'
                ]);

                return false;
            }
            if (!isset($lastMotor) && isset($vehicle)) {
                $this->dispatchBrowserEvent('alert', [
                    'type' => 'error',
                    'message' => 'This vehicle plate is already linked to another active policyin vehicle details :policyId ' . $vehicle->policy_id . ' Please verify and delete.'
                ]);

                return false;
            }
            //dd($lastMotor);
            // ------------------- STEP 3: DATE RANGE VALIDATION ------------------- //
            $old_from = strtotime($lastMotor->effective_from);
            $old_to = strtotime($lastMotor->effective_to);
            $new_from = strtotime($newEffFrom);
            $new_to = strtotime($newEffTo);

            // Overlapping period logic (if any day clashes → block)
            $overlap = ($new_from <= $old_to && $new_to >= $old_from);

            if ($overlap) {
                $this->dispatchBrowserEvent('alert', [
                    'type' => 'error',
                    'message' => 'This vehicle plate is already linked to another active policy ' . $lastMotor->policy_id . ' Please verify dates and try again.'
                ]);
                return false;
            }

            // If no overlap → Allowed
            return true;
        }
    }


    public function vehicleDetailsedit($id)
    {

        $this->vehicleData = Vehicle::find(\Crypt::decrypt($id));
        // Boolean fix
        $this->vehicleData->is_imported = $this->vehicleData->is_imported == 1;

        /* ===================== LOAD MAKES ===================== */
        $this->vechicleMakes = $this->getVechicleMake();

        // Stored make (name or id depending on schema)
        $storedMake = $this->vehicleData->make;

        // Find matching make_id from options
        $makeId = collect($this->vechicleMakes)
            ->firstWhere('name', $storedMake)['id'] ?? null;

        $this->vehicleData->make_id = $makeId;

        /* ===================== LOAD MODELS ===================== */
        if ($makeId) {
            // trigger model loading
            $this->updatedVehicleData($makeId, 'make_id');

            $storedModel = $this->vehicleData->model;

            $modelId = collect($this->vechicleModels)
                ->firstWhere('name', $storedModel)['id'] ?? null;

            $this->vehicleData->model_id = $modelId;
        }

        //added by snehal 
        $motorData = Motor::join('policy_coverages as pc', 'pc.id', '=', 'motor.policy_coverage_id')
            ->where('motor.registration_no', $this->vehicleData->vehiclePlate)
            ->where('pc.policy_id', $this->policy->id)
            ->where('pc.action_id', $this->actionId)
            ->select('motor.*') // you can select specific columns if needed
            ->first();
        if ($motorData) { // or: if (!is_null($motorData))
            $this->MotorDataId = $motorData->id;
        }

        $this->V_isUpdate = true;
    }
    public function policyCoverage()
    {
        return $this->belongsTo(PolicyCoverage::class, 'policy_coverage_id');
    }
    public function triggerVehicleDelete($id)
    {
        $registarionNumber = Vehicle::find($id)->vehiclePlate;
        $risk_id = Vehicle::find($id)->risk_id;
        $policy_id = $this->policy->id;
        $action_id = $this->actionId;

        $motorVehicleId = Motor::where('registration_no', $registarionNumber)->get('id');
        // added by snehal 16-7-25
        $motor_coverage_ids = Motor::where('registration_no', $registarionNumber)
            ->whereHas('policyCoverage', function ($q) use ($policy_id, $action_id, $risk_id) {
                $q->whereIn('coverage_id', [22, 27])
                    ->where('policy_id', $policy_id)
                    ->where('action_id', $action_id)
                    ->where('risk_address_id', $risk_id);
            })->pluck('motor.id');


        Motor::whereIn('id', $motor_coverage_ids)->delete();
        $isVehicle = Vehicle::where('id', $id)->update(['deleted_at' => now(), 'deleted_by' => auth()->id()]);
        if ($isVehicle) {
            $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Vehicle Details Deleted Successfully!']);
        } else {
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Something Went Wrong']);
        }

        activity('Vehicle')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Vehicle Details Deleted');

        $this->vehicleDetailseditCancel();
        $this->render();
    }

    public function vehicleDetailseditCancel()
    {
        $this->vehicleData = new Vehicle();
        $this->V_isUpdate = false;
    }

    public function updatedVehicleData($value, $key)
    {
        /* ===================== IS IMPORTED CHANGE ===================== */
        if ($key === 'is_imported') {

            // Reset dependent fields
            $this->vehicleData['make_id'] = null;
            $this->vehicleData['model_id'] = null;
            $this->vechicleModels = [];

            // Reload makes
            $this->vechicleMakes = $this->getVechicleMake();

            $this->dispatchBrowserEvent('dropdown-changed', [
                'key' => 'is_imported',
                'data' => $this->vechicleMakes
            ]);
        }

        /* ===================== MAKE CHANGE ===================== */
        if ($key === 'make_id') {

            // Reset model when make changes
            $this->vehicleData['model_id'] = null;
            $this->vechicleModels = [];

            $makeName = $this->vechicleMakes[$value]['name'] ?? null;
            if (!$makeName) {
                return;
            }

            /* ---------- NON-IMPORTED (API) ---------- */
            if ($this->vehicleData['is_imported'] == 0) {

                if (empty($this->vehicleData['year'])) {
                    return;
                }

                $response = Http::withToken(
                    base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B:10415')
                )->get('http://api.realintel.co.za/json/trutrade/GetModels', [
                            'make' => $makeName,
                            'year' => $this->vehicleData['year']
                        ]);

                if ($response->successful()) {
                    $this->vechicleModels = collect($response['Variants'] ?? [])
                        ->filter()
                        ->unique()                       // remove duplicates
                        ->values()
                        ->map(function ($model, $index) {
                            return [
                                'id' => $index + 1,   // safe numeric id
                                'name' => trim($model),
                            ];
                        })
                        ->keyBy('id')
                        ->all();
                }
            }

            /* ---------- IMPORTED (DB) ---------- */
            if ($this->vehicleData['is_imported'] == 1) {

                $this->vechicleModels = VehicleMake::selectRaw('MIN(id) as id, s_Variant')
                    ->where('s_Make', $makeName)          // ✅ correct column
                    ->whereNotNull('s_Variant')
                    ->groupBy('s_Variant')
                    ->orderBy('s_Variant')
                    ->get()
                    ->mapWithKeys(function ($d) {
                        return [
                            $d->id => [
                                'id' => $d->id,
                                'name' => trim($d->s_Variant),
                            ]
                        ];
                    });
            }

            $this->dispatchBrowserEvent('dropdown-changed', [
                'key' => 'vehicleDataMake',
                'data' => $this->vechicleModels
            ]);
        }

        /* ===================== PRODUCT CHANGE ===================== */
        if ($key === 'product_id') {

            $motordata = MotorType::where('product_id', $value)
                ->orderBy('motor_name')
                ->get()
                ->mapWithKeys(function ($d) {
                    return [
                        $d->id => [
                            'id' => $d->id,
                            'name' => $d->motor_name
                        ]
                    ];
                });

            $this->dispatchBrowserEvent('dropdown-changed', [
                'key' => 'vehicle_type',
                'data' => $motordata,
                'selected_id' => $this->vehicleData['vehicle_type'] ?? null
            ]);
        }
    }


    // public function allMotorType(){
    //     $motordata= MotorType::where('product_id',$this->policy->product_id)->get()->keyBy('id')->map(function($d){
    //         return [
    //             'id'=>$d->id,
    //             'name'=>$d->motor_name
    //         ];
    //     });
    //     $this->dispatchBrowserEvent('dropdown-changed',[
    //         'key'=>'vehicle_type',
    //         'data'=>$motordata
    //     ]);
    // }

    public function getVechicleMake()
    {
        // ---------- NON-IMPORTED (API) ----------
        if ($this->vehicleData->is_imported == 0) {

            $response = \Http::withToken(
                base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B:10415')
            )->get('http://api.realintel.co.za/json/trutrade/GetMakes');

            if ($response->successful()) {
                return collect($response['Makes'])
                    ->pluck('Make')              // get only make name
                    ->filter()                   // remove null/empty
                    ->unique()                   // 🔑 remove duplicates
                    ->values()
                    ->map(function ($make, $index) {
                        return [
                            'id' => $index + 1,        // safe numeric id
                            'name' => trim($make),
                        ];
                    })
                    ->keyBy('id')
                    ->all();
            }
        }

        // ---------- IMPORTED (DB) ----------
        if ($this->vehicleData->is_imported == 1) {

            return VehicleMake::selectRaw('MIN(id) as id, s_Make')
                ->whereNotNull('s_Make')
                ->groupBy('s_Make')               // 🔑 one record per make
                ->orderBy('s_Make')
                ->get()
                ->mapWithKeys(function ($d) {
                    return [
                        $d->id => [
                            'id' => $d->id,          // safe numeric id
                            'name' => trim($d->s_Make),
                        ]
                    ];
                });
        }

        return [];
    }


    public function getVechicleModel()
    {
        // ---------- NON-IMPORTED (API) ----------
        if ($this->vehicleData->is_imported == 0) {

            if (empty($this->vehicleData->make_id) || empty($this->vehicleData->year)) {
                return [];
            }

            $response = \Http::withToken(
                base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B:10415')
            )->get('http://api.realintel.co.za/json/trutrade/GetModels', [
                        'make' => $this->getMakeNameById($this->vehicleData->make_id),
                        'year' => $this->vehicleData->year
                    ]);

            if ($response->successful()) {
                return collect($response['Variants'] ?? [])
                    ->filter()
                    ->unique()                     // 🔑 remove duplicates
                    ->values()
                    ->map(function ($model, $index) {
                        return [
                            'id' => $index + 1, // safe numeric id
                            'name' => trim($model),
                        ];
                    })
                    ->keyBy('id')
                    ->all();
            }
        }

        // ---------- IMPORTED (DB) ----------
        if ($this->vehicleData->is_imported == 1) {

            if (empty($this->vehicleData->make_id)) {
                return [];
            }

            return VehicleMake::selectRaw('MIN(id) as id, s_Variant')
                ->where('make_id', $this->vehicleData->make_id)
                ->whereNotNull('s_Variant')
                ->groupBy('s_Variant')             // 🔑 one record per model
                ->orderBy('s_Variant')
                ->get()
                ->mapWithKeys(function ($d) {
                    return [
                        $d->id => [
                            'id' => $d->id,
                            'name' => trim($d->s_Variant),
                        ]
                    ];
                });
        }

        return [];
    }
    private function getMakeNameById($makeId)
    {
        return $this->vechicleMakes[$makeId]['name'] ?? null;
    }

    public function getYear()
    {
        $year = [];
        for ($i = date("Y"); $i >= date('1970'); $i--) {
            $year[$i] = $i;
        }
        return $year;
    }


    public function saveStep5()
    {
        $this->emitUp('updateHasStatus');
    }

    public function backToStep4()
    {
        $this->emitUp('backToStep4');
    }
}
