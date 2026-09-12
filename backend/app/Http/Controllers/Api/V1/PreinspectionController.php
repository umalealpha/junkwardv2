<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\CellphoneDeviceStatus;
use AlphaDirect\Customer;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Helper;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Policy;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;

/**
 * PreinspectionController
 *
 * Admin vehicle & device pre-inspection: list, view, and approve/reject.
 * Ported from the legacy CustomerInspection admin controller
 * (/admin/customerVehicleInspection + /admin/deviceData) to the v2 API.
 *
 * Vehicle approval works on the `vehicle` table (Motor Comp product_id 3
 * and Third Party Car product_id 2).
 * Device approval works on `policy_cellphone` + `policy_cellphone_device_status`
 * (product_id 5).
 */
class PreinspectionController extends Controller
{
    // Vehicle status codes (vehicle.status)
    private const VEHICLE_STATUS = [
        1 => 'Approved',
        2 => 'Unapproved',
        3 => 'Recheck',
    ];

    // ──────────────────────────────────────────────────────────────
    // Vehicle pre-inspection
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/preinspection/vehicles
     * Motor vehicle inspections (product_id 3 Motor Comp, 2 Third Party Car), newest first.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'         => 'nullable|string|max:100',
            'policy_number'  => 'nullable|string|max:60',
            'vehicle_plate'  => 'nullable|string|max:60',
            'status'         => 'nullable|integer',
            'per_page'       => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('vehicle')
            ->leftJoin('customer', 'customer.id', '=', 'vehicle.customer_id')
            ->leftJoin('policies', 'policies.id', '=', 'vehicle.policy_id')
            ->whereIn('policies.product_id', [2, 3])
            ->where('policies.status', '!=', 2)
            ->when($validated['policy_number'] ?? null, fn ($q, $v) => $q->where('policies.policyNumber', $v))
            ->when($validated['vehicle_plate'] ?? null, fn ($q, $v) => $q->where('vehicle.vehiclePlate', $v))
            ->when(isset($validated['status']), fn ($q) => $q->where('vehicle.status', $validated['status']))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('vehicle.vehiclePlate', 'like', $like)
                      ->orWhere('policies.policyNumber', 'like', $like)
                      ->orWhere('customer.firstName', 'like', $like)
                      ->orWhere('customer.lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(customer.firstName), TRIM(customer.lastName)) LIKE ?", [$like]);
                    if (count($words) >= 2) {
                        $q->orWhere(fn($i) =>
                            $i->where('customer.firstName', 'like', "%{$words[0]}%")
                              ->where('customer.lastName', 'like', "%{$words[1]}%")
                        )->orWhere(fn($i) =>
                            $i->where('customer.firstName', 'like', "%{$words[1]}%")
                              ->where('customer.lastName', 'like', "%{$words[0]}%")
                        );
                    }
                });
            })
            ->orderBy('vehicle.id', 'desc')
            ->select([
                'vehicle.id', 'vehicle.vehiclePlate', 'vehicle.front', 'vehicle.back',
                'vehicle.left', 'vehicle.right', 'vehicle.vehicleRegistration', 'vehicle.vehicle_valuation',
                'vehicle.status', 'vehicle.make', 'vehicle.model', 'vehicle.year',
                'vehicle.policy_id', 'vehicle.created_at',
                'policies.policyNumber',
                DB::raw('CONCAT_WS(" ", customer.firstName, customer.lastName) as customerName'),
            ]);

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn ($r) => [
                'id'           => $r->id,
                'policyId'     => $r->policy_id,
                'policyNumber' => $r->policyNumber,
                'customerName' => trim((string) $r->customerName) ?: null,
                'vehiclePlate' => $r->vehiclePlate,
                'make'         => $r->make,
                'model'        => $r->model,
                'year'         => $r->year,
                'frontImage'   => $this->imageUrl($r->front),
                'backImage'    => $this->imageUrl($r->back),
                'leftImage'    => $this->imageUrl($r->left),
                'rightImage'   => $this->imageUrl($r->right),
                'status'       => (int) $r->status,
                'statusLabel'  => $this->vehicleStatusLabel($r->status),
                'createdAt'    => $r->created_at,
            ]),
            'meta' => $this->meta($results),
        ]);
    }

    /**
     * GET /api/v1/preinspection/vehicles/{id}
     * Single vehicle inspection detail for the view/edit screens.
     */
    public function vehicleShow(int $id): JsonResponse
    {
        $v = Vehicle::find($id);
        if (! $v) {
            return response()->json(['message' => 'Vehicle inspection not found'], 404);
        }

        $policy = $v->policy_id ? Policy::find($v->policy_id) : null;
        $customer = $v->customer_id ? Customer::find($v->customer_id) : null;
        $performedBy = $this->vehiclePerformedBy($v, $policy);

        return response()->json([
            'data' => [
                'id'           => $v->id,
                'policyId'     => $v->policy_id,
                'policyNumber' => $policy->policyNumber ?? null,
                'customerName' => $customer ? trim($customer->firstName . ' ' . $customer->lastName) : null,
                'vehiclePlate' => $v->vehiclePlate,
                'make'         => $v->make,
                'model'        => $v->model,
                'year'         => $v->year,
                'status'       => (int) $v->status,
                'statusLabel'  => $this->vehicleStatusLabel($v->status),
                'compliance'   => $v->compliance,
                'remark'       => $v->remark,
                'reason'       => $v->reason,
                'performedBy'  => $performedBy,
                'createdAt'    => $v->created_at,
                'updatedAt'    => $v->updated_at,
                'images'       => [
                    'front'        => ['url' => $this->imageUrl($v->front), 'status' => $this->intOrNull($v->front_status), 'remark' => $v->front_image_remark],
                    'back'         => ['url' => $this->imageUrl($v->back), 'status' => $this->intOrNull($v->back_status), 'remark' => $v->back_image_remark],
                    'left'         => ['url' => $this->imageUrl($v->left), 'status' => $this->intOrNull($v->left_status), 'remark' => $v->left_image_remark],
                    'right'        => ['url' => $this->imageUrl($v->right), 'status' => $this->intOrNull($v->right_status), 'remark' => $v->right_image_remark],
                    'registration' => ['url' => $this->imageUrl($v->vehicleRegistration), 'status' => $this->intOrNull($v->vehicle_registration_status), 'remark' => $v->registration_image_remark],
                    'invoice'      => ['url' => $this->imageUrl($v->vehicle_valuation), 'status' => $this->intOrNull($v->vehicle_invoice_status), 'remark' => $v->vehicle_invoice_remark],
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/preinspection/vehicles/{id}/approve
     * Approve / reject a vehicle inspection. On full approval the policy is
     * activated (when a successful payment exists) and incentive/cashback
     * events fire; on rejection the customer & agent are notified.
     * Ported from CustomerInspection::update().
     */
    public function vehicleUpdate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'front_status'                => 'nullable|integer|in:0,1',
            'back_status'                 => 'nullable|integer|in:0,1',
            'left_status'                 => 'nullable|integer|in:0,1',
            'right_status'                => 'nullable|integer|in:0,1',
            'vehicle_registration_status' => 'nullable|integer|in:0,1',
            'vehicle_invoice_status'      => 'nullable|integer|in:0,1',
            'front_image_remark'          => 'nullable|string',
            'back_image_remark'           => 'nullable|string',
            'left_image_remark'           => 'nullable|string',
            'right_image_remark'          => 'nullable|string',
            'registration_image_remark'   => 'nullable|string',
            'vehicle_invoice_remark'      => 'nullable|string',
            'remark'                      => 'nullable|string',
        ]);

        try {
            $data = Vehicle::find($id);
            if (! $data) {
                return response()->json(['message' => 'Vehicle inspection not found'], 404);
            }

            $policy   = $data->policy_id ? Policy::find($data->policy_id) : null;
            $customer = null;
            $agent    = null;
            $mail = null; $cellphone = null;
            if ($policy) {
                $customer = Customer::where('id', $policy->customer_id)->first(['id', 'firstName', 'lastName', 'cellphone', 'email']);
                if ($policy->agent_id) {
                    $agent = User::join('user_profile', 'user_profile.user_id', 'users.id')
                        ->where('users.id', $policy->agent_id)
                        ->first(['users.id', 'users.firstName', 'users.lastName', 'user_profile.cellphone', 'users.email']);
                }
                if ($customer) {
                    $mail      = $customer->email ?: null;
                    $cellphone = $customer->cellphone ?: null;
                }
            }

            $data->front_status               = $request->input('front_status', 0);
            $data->front_image_remark         = $request->input('front_image_remark', '');
            $data->back_status                = $request->input('back_status', 0);
            $data->back_image_remark          = $request->input('back_image_remark', '');
            $data->right_status               = $request->input('right_status', 0);
            $data->right_image_remark         = $request->input('right_image_remark', '');
            $data->left_status                = $request->input('left_status', 0);
            $data->left_image_remark          = $request->input('left_image_remark', '');
            $data->vehicle_registration_status = $request->input('vehicle_registration_status', 0);
            $data->registration_image_remark  = $request->input('registration_image_remark', '');
            $data->vehicle_invoice_status     = $request->input('vehicle_invoice_status', 0);
            $data->vehicle_invoice_remark     = $request->input('vehicle_invoice_remark', '');

            $compliance = $this->vehicleCompliance($request);
            $data->compliance = $compliance;
            $data->remark = $request->input('remark');

            $reason = '';
            if ($compliance == 1) {
                $reason = 'Approved';
                if ($policy) {
                    $chk = PolicyController::checkMotorpolicyStatus(3, $policy->id, 1);
                    if ($chk == 1) {
                        Policy::where('id', $policy->id)->update(['status' => 1]);
                    }
                }
                $data->status = 1;
            } else {
                $reason .= $this->rejectionLine($request, 'front_status', 'front_image_remark', $data->front, 'Vehicle Front');
                $reason .= $this->rejectionLine($request, 'back_status', 'back_image_remark', $data->back, 'Vehicle Back');
                $reason .= $this->rejectionLine($request, 'right_status', 'right_image_remark', $data->right, 'Vehicle Right');
                $reason .= $this->rejectionLine($request, 'left_status', 'left_image_remark', $data->left, 'Vehicle Left');
                $reason .= $this->rejectionLine($request, 'vehicle_registration_status', 'registration_image_remark', $data->vehicleRegistration, 'Vehicle Registration Book');
                $data->status = 2;
            }

            $data->reason = $reason;
            $data->save();

            // Incentive + cashback (type 4 = vehicle pre-inspection)
            if ($policy && $policy->agent_id != null) {
                event(new \Modules\Incentive\Events\AddIncentive($policy, 4, $compliance, $policy->premium));
            }
            if ($policy) {
                event(new \Modules\Cashback\Events\CustomerCashbackEvent($policy, 4, $compliance));
            }

            // Notifications only on rejection (compliance == 2)
            if ($policy && $compliance == 2) {
                $name = $customer ? trim($customer->firstName . ' ' . $customer->lastName) : '';

                if ($mail) {
                    $this->sendHookMail($mail, 'vehicle_preinspection', $customer->id ?? null, $policy->policyNumber);
                }
                if ($agent && $agent->email) {
                    $this->sendHookMail($mail ?: $agent->email, 'agent_vehicle_preinspection', $customer->id ?? null, $policy->policyNumber);
                }

                $sms = new SmsMessaging();
                if ($cellphone) {
                    $sms->smsCustomerPreinspection(12, $name, $policy->policyNumber, $cellphone);
                    try {
                        (new WhatsAppController())->sendMessage([
                            'type'          => 'template',
                            'subType'       => 'vehicle_preinspection_reject',
                            'mobileNumber'  => '267' . $cellphone,
                            'policyNumber'  => $policy->policyNumber,
                            'customer_name' => $name,
                            'customer_id'   => $policy->customer_id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Vehicle preinspection WhatsApp failed: ' . $e->getMessage());
                    }
                }
                if ($agent && $agent->cellphone) {
                    $sms->smsAgentPreinspection(13, $agent->firstName, $name, $policy->policyNumber, $agent->cellphone);
                }
            }

            return response()->json([
                'message'     => $compliance == 1 ? 'Vehicle inspection approved' : 'Vehicle inspection updated',
                'status'      => (int) $data->status,
                'statusLabel' => $this->vehicleStatusLabel($data->status),
            ]);
        } catch (\Exception $e) {
            Log::error('vehicleUpdate failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Device pre-inspection
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/preinspection/devices
     * Device (cellphone) inspections (product_id 5), newest first.
     */
    public function devices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'status'   => 'nullable|integer',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('policy_cellphone as pc')
            ->leftJoin('policies as p', 'p.id', '=', 'pc.policy_id')
            ->leftJoin('customer as c', 'c.id', '=', 'pc.customer_id')
            ->where('p.status', '!=', 2)
            ->whereNotNull('pc.customer_id')
            ->when(isset($validated['status']), fn ($q) => $q->where('pc.status', $validated['status']))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('pc.imei', 'like', $like)
                      ->orWhere('p.policyNumber', 'like', $like)
                      ->orWhere('pc.cell_phone_make', 'like', $like)
                      ->orWhere('c.firstName', 'like', $like)
                      ->orWhere('c.lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like]);
                    if (count($words) >= 2) {
                        $q->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[0]}%")
                              ->where('c.lastName', 'like', "%{$words[1]}%")
                        )->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[1]}%")
                              ->where('c.lastName', 'like', "%{$words[0]}%")
                        );
                    }
                });
            })
            ->orderBy('pc.id', 'desc')
            ->select([
                'pc.id', 'pc.policy_id', 'pc.imei', 'pc.cell_phone_make', 'pc.cell_phone_model',
                'pc.phone_value', 'pc.device_type', 'pc.status as device_status', 'pc.created_at',
                'p.policyNumber',
                DB::raw('CONCAT_WS(" ", c.firstName, c.lastName) as customerName'),
            ]);

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn ($r) => [
                'id'                => $r->id,
                'policyId'          => $r->policy_id,
                'policyNumber'      => $r->policyNumber,
                'customerName'      => trim((string) $r->customerName) ?: null,
                'imei'              => $r->imei,
                'make'              => $r->cell_phone_make,
                'model'             => $r->cell_phone_model,
                'deviceType'        => $r->device_type,
                'deviceValue'       => $r->phone_value,
                'deviceStatus'      => $this->intOrNull($r->device_status),
                'deviceStatusLabel' => $this->deviceStatusLabel($r->device_status),
                'createdAt'         => $r->created_at,
            ]),
            'meta' => $this->meta($results),
        ]);
    }

    /**
     * GET /api/v1/preinspection/devices/{id}
     * Single device inspection detail for the view/edit screens.
     * {id} is policy_cellphone.id.
     */
    public function deviceShow(int $id): JsonResponse
    {
        $pc = PolicyCellPhone::find($id);
        if (! $pc) {
            return response()->json(['message' => 'Device inspection not found'], 404);
        }

        $policy   = $pc->policy_id ? Policy::find($pc->policy_id) : null;
        $customer = $pc->customer_id ? Customer::find($pc->customer_id) : null;
        $ds = CellphoneDeviceStatus::where('policy_cellphone_id', $id)->first();

        $side = fn ($img, $statusCol, $remarkCol) => [
            'url'    => $this->imageUrl($img),
            'status' => $ds ? $this->intOrNull($ds->{$statusCol}) : null,
            'remark' => $ds ? $ds->{$remarkCol} : null,
        ];

        return response()->json([
            'data' => [
                'id'           => $pc->id,
                'policyId'     => $pc->policy_id,
                'policyNumber' => $policy->policyNumber ?? null,
                'customerName' => $customer ? trim($customer->firstName . ' ' . $customer->lastName) : null,
                'imei'         => $pc->imei,
                'make'         => $pc->cell_phone_make,
                'model'        => $pc->cell_phone_model,
                'deviceType'   => $pc->device_type,
                'deviceValue'  => $pc->phone_value,
                'status'       => $this->intOrNull($ds->status ?? $pc->status),
                'statusLabel'  => $this->deviceStatusLabel($ds->status ?? $pc->status),
                'remark'       => $ds->remark ?? null,
                'reason'       => $pc->reason,
                'createdAt'    => $pc->created_at,
                'updatedAt'    => $pc->updated_at,
                'images'       => [
                    'front'  => $side($pc->cell_phone_front, 'cell_phone_front_status', 'cell_phone_front_image_remark'),
                    'back'   => $side($pc->cell_phone_back, 'cell_phone_back_status', 'cell_phone_back_image_remark'),
                    'left'   => $side($pc->cell_phone_left, 'cell_phone_left_status', 'cell_phone_left_image_remark'),
                    'right'  => $side($pc->cell_phone_right, 'cell_phone_right_status', 'cell_phone_right_image_remark'),
                    'top'    => $side($pc->cell_phone_top, 'cell_phone_top_status', 'cell_phone_top_remark'),
                    'bottom' => $side($pc->cell_phone_bottom, 'cell_phone_bottom_status', 'cell_phone_bottom_remark'),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/preinspection/devices/{id}/approve
     * Approve / reject a device inspection. {id} is policy_cellphone.id.
     * Ported from CustomerInspection::updateDeviceStatus().
     */
    public function deviceUpdate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'cell_phone_top_status'         => 'nullable|integer|in:0,1',
            'cell_phone_bottom_status'      => 'nullable|integer|in:0,1',
            'cell_phone_left_status'        => 'nullable|integer|in:0,1',
            'cell_phone_right_status'       => 'nullable|integer|in:0,1',
            'cell_phone_back_status'        => 'nullable|integer|in:0,1',
            'cell_phone_front_status'       => 'nullable|integer|in:0,1',
            'cell_phone_top_remark'         => 'nullable|string',
            'cell_phone_bottom_remark'      => 'nullable|string',
            'cell_phone_left_image_remark'  => 'nullable|string',
            'cell_phone_right_image_remark' => 'nullable|string',
            'cell_phone_back_image_remark'  => 'nullable|string',
            'cell_phone_front_image_remark' => 'nullable|string',
            'remark'                        => 'nullable|string',
        ]);

        try {
            $device = PolicyCellPhone::find($id);
            if (! $device) {
                return response()->json(['message' => 'Device inspection not found'], 404);
            }

            $data = CellphoneDeviceStatus::where('policy_cellphone_id', $id)->first() ?: new CellphoneDeviceStatus();
            $data->policy_cellphone_id = $id;

            $data->cell_phone_top_status          = $request->input('cell_phone_top_status');
            $data->cell_phone_top_remark          = $request->input('cell_phone_top_remark');
            $data->cell_phone_bottom_status       = $request->input('cell_phone_bottom_status');
            $data->cell_phone_bottom_remark       = $request->input('cell_phone_bottom_remark');
            $data->cell_phone_left_status         = $request->input('cell_phone_left_status');
            $data->cell_phone_left_image_remark   = $request->input('cell_phone_left_image_remark');
            $data->cell_phone_right_status        = $request->input('cell_phone_right_status');
            $data->cell_phone_right_image_remark  = $request->input('cell_phone_right_image_remark');
            $data->cell_phone_back_status         = $request->input('cell_phone_back_status');
            $data->cell_phone_back_image_remark   = $request->input('cell_phone_back_image_remark');
            $data->cell_phone_front_status        = $request->input('cell_phone_front_status');
            $data->cell_phone_front_image_remark  = $request->input('cell_phone_front_image_remark');

            $allApproved = $request->input('cell_phone_top_status') == 1
                && $request->input('cell_phone_bottom_status') == 1
                && $request->input('cell_phone_left_status') == 1
                && $request->input('cell_phone_right_status') == 1
                && $request->input('cell_phone_back_status') == 1
                && $request->input('cell_phone_front_status') == 1;
            $status = $allApproved ? 1 : 0;

            $reason = '';
            if ($status == 1) {
                if ($device->policy_id) {
                    $chk = PolicyController::checkMotorpolicyStatus(5, $device->policy_id, 1);
                    if ($chk == 1) {
                        Policy::where('id', $device->policy_id)->update(['status' => 1]);
                    }
                }
                $reason = 'Approved';
            } else {
                $reason .= $this->deviceRejectionLine($request, 'cell_phone_top_status', 'cell_phone_top_remark', $device->cell_phone_top, 'Device Top');
                $reason .= $this->deviceRejectionLine($request, 'cell_phone_bottom_status', 'cell_phone_bottom_remark', $device->cell_phone_bottom, 'Device Bottom');
                $reason .= $this->deviceRejectionLine($request, 'cell_phone_left_status', 'cell_phone_left_image_remark', $device->cell_phone_left, 'Device Left');
                $reason .= $this->deviceRejectionLine($request, 'cell_phone_right_status', 'cell_phone_right_image_remark', $device->cell_phone_right, 'Device Right');
                $reason .= $this->deviceRejectionLine($request, 'cell_phone_back_status', 'cell_phone_back_image_remark', $device->cell_phone_back, 'Device Back');
                $reason .= $this->deviceRejectionLine($request, 'cell_phone_front_status', 'cell_phone_front_image_remark', $device->cell_phone_front, 'Device Front');
            }

            $data->status = $status;
            $data->remark = $request->input('remark');
            $data->reason = $reason;
            $data->save();

            $device->status = $status;
            $device->reason = $reason;
            $device->save();

            $policy   = $device->policy_id ? Policy::find($device->policy_id) : null;
            $customer = $device->customer_id ? Customer::where('id', $device->customer_id)->first(['firstName', 'lastName', 'email', 'cellphone']) : null;

            // Incentive + cashback (type 5 = device pre-inspection)
            if ($policy && $policy->agent_id != null) {
                event(new \Modules\Incentive\Events\AddIncentive($policy, 5, $status, $policy->premium));
            }
            if ($policy) {
                event(new \Modules\Cashback\Events\CustomerCashbackEvent($policy, 5, $status));
            }

            // Notifications only on rejection (status == 0)
            if ($policy && $status == 0) {
                $cname   = $customer ? trim($customer->firstName . ' ' . $customer->lastName) : '';
                $cnumber = $customer->cellphone ?? null;
                $cmail   = $customer->email ?? null;

                $agent = null;
                if ($policy->agent_id) {
                    $agent = User::leftJoin('user_profile', 'user_profile.user_id', 'users.id')
                        ->where('users.id', $policy->agent_id)
                        ->first(['users.email', 'users.firstName', 'users.lastName', 'user_profile.cellphone']);
                }

                if ($cmail) {
                    $this->sendHookMail($cmail, 'device_preinspection_customer', $device->customer_id, $policy->policyNumber);
                }
                if ($agent && $agent->email) {
                    $this->sendHookMail($agent->email, 'device_preinspection_agent', $device->customer_id, $policy->policyNumber);
                }

                $sms = new SmsMessaging();
                if ($agent && $agent->cellphone) {
                    $sms->smsDevicePreinspectionAgent(15, $cname, trim($agent->firstName . ' ' . $agent->lastName), $policy->policyNumber, $device->cell_phone_make, $device->cell_phone_model, $device->device_type, $agent->cellphone);
                }
                if ($cnumber) {
                    $sms->smsDevicePreinspectionCustomer(16, $cname, $policy->policyNumber, $device->cell_phone_make, $device->cell_phone_model, $device->device_type, $cnumber);
                    try {
                        (new WhatsAppController())->sendMessage([
                            'type'            => 'template',
                            'subType'         => 'preinspection_reject',
                            'mobileNumber'    => '267' . $cnumber,
                            'policyNumber'    => $policy->policyNumber,
                            'customer_name'   => $cname,
                            'customer_id'     => $device->customer_id,
                            'cell_phone_make' => $device->cell_phone_make,
                            'phone_model'     => $device->cell_phone_model,
                            'device_type'     => $device->device_type,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Device preinspection WhatsApp failed: ' . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'message'     => $status == 1 ? 'Device inspection approved' : 'Device inspection updated',
                'status'      => $status,
                'statusLabel' => $this->deviceStatusLabel($status),
            ]);
        } catch (\Exception $e) {
            Log::error('deviceUpdate failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function vehicleCompliance(Request $request): int
    {
        return ($request->input('front_status') == 1
            && $request->input('back_status') == 1
            && $request->input('right_status') == 1
            && $request->input('left_status') == 1
            && $request->input('vehicle_registration_status') == 1
            && $request->input('vehicle_invoice_status') == 1) ? 1 : 2;
    }

    /** Build a rejection reason line for one vehicle image (mirrors legacy logic). */
    private function rejectionLine(Request $request, string $statusKey, string $remarkKey, $image, string $label): string
    {
        if ($request->input($statusKey) === null || $request->input($statusKey) != 0) {
            return '';
        }
        $remark = $request->input($remarkKey);
        if (! empty($remark)) {
            return "{$label}: {$remark}<br>";
        }
        if ($image == null) {
            return "{$label}: Not Uploaded<br>";
        }
        return "{$label}: Reason  Not Mentioned<br>";
    }

    /** Build a rejection reason line for one device side (mirrors legacy logic). */
    private function deviceRejectionLine(Request $request, string $statusKey, string $remarkKey, $image, string $label): string
    {
        if ($request->input($statusKey) === null) {
            return "{$label} : Not Uploaded<br>";
        }
        if ($request->input($statusKey) != 0) {
            return '';
        }
        $remark = $request->input($remarkKey);
        if (! empty($remark)) {
            return "{$label}: {$remark}<br>";
        }
        if ($image == null) {
            return "{$label}: Not Uploaded<br>";
        }
        return "{$label}: Reason  Not Mentioned<br>";
    }

    /** Resolve the user who last touched this vehicle (audit trail → added_by). */
    private function vehiclePerformedBy(Vehicle $v, ?Policy $policy): ?string
    {
        $user = null;
        if ($policy) {
            $activity = Audit::where('event', 'updated')
                ->where('auditable_type', 'AlphaDirect\\Vehicle')
                ->whereNotNull('user_id')
                ->where('policy_number', $policy->policyNumber)
                ->orderBy('created_at', 'desc')
                ->first(['user_id']);
            if ($activity) {
                $user = User::find($activity->user_id, ['firstName', 'lastName']);
            }
        }
        if (! $user && $v->added_by) {
            $user = User::find($v->added_by, ['firstName', 'lastName']);
        }
        return $user ? trim($user->firstName . ' ' . $user->lastName) : null;
    }

    private function sendHookMail($to, string $hook, $customerId, ?string $policyNumber): void
    {
        try {
            $email = new \stdClass();
            $email->user_id = null;
            $email->hook = $hook;
            $email->customer_id = $customerId;
            $email->attachment = null;
            $template = EmailBroadcasting::where('hook_slug', $hook)->first(['subject']);
            if (! $template) {
                return;
            }
            $markdown = new MailTemplate($email);
            $html = $markdown->render('Mail.mailTemplate', ['data' => $email]);
            event(new \AlphaDirect\Events\SendMail($to, $template->subject, '', $html, null, ['policyNumber' => $policyNumber, 'hook' => $hook]));
        } catch (\Exception $e) {
            Log::error("Preinspection mail ({$hook}) failed: " . $e->getMessage());
        }
    }

    private function imageUrl($path): ?string
    {
        if (empty($path)) {
            return null;
        }
        // Already an absolute URL — return as-is.
        if (is_string($path) && preg_match('#^https?://#', $path)) {
            return $path;
        }
        try {
            return Helper::getCloudFrontURL($path);
        } catch (\Exception $e) {
            return $path;
        }
    }

    private function vehicleStatusLabel($status): string
    {
        return self::VEHICLE_STATUS[(int) $status] ?? 'Pending';
    }

    private function deviceStatusLabel($status): string
    {
        if ($status === null) {
            return 'Pending';
        }
        return ((int) $status === 1) ? 'Approved' : 'Unapproved';
    }

    private function intOrNull($value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function meta($results): array
    {
        return [
            'total'        => $results->total(),
            'per_page'     => $results->perPage(),
            'current_page' => $results->currentPage(),
            'last_page'    => $results->lastPage(),
            'from'         => $results->firstItem(),
            'to'           => $results->lastItem(),
        ];
    }
}
