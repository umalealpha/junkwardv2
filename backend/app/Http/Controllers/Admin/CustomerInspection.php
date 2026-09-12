<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\AgentPreInsepection;
use AlphaDirect\CellphoneDeviceStatus;
use AlphaDirect\Customer;
use AlphaDirect\Mail\PreInspectionMail;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\VehicleDelete;
use AlphaDirect\Policy;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Yajra\DataTables\DataTables;
use Redirect;
use DB;
use OwenIt\Auditing\Models\Audit;
use AlphaDirect\Models\CellphoneDelete;


use function GuzzleHttp\json_encode;

class CustomerInspection extends Controller
{

    /**shows listing of all agent inspection data
     * @return View inspection listing page
     */
    public function agentInspectionAppUpload()
    {
        $kycDocuments = AgentPreInsepection::all();
        return view('admin.agentAppUploads.inspection', compact('kycDocuments'));
    }
    public function customerVehicleDeleteRecordes($id)
    {
      $data = VehicleDelete::where('vehicle_id',$id)->orderBy('id','desc')->first();
      if($data){
        return view('admin.agentAppUploads.vehicle_delete_record', compact('data'));
      }else{
        return Redirect::back()->with('error', 'No Data Found');
      } 
    }
    public function CellphoneDeleteRecordes($id)
    {
      $data = CellphoneDelete::where('policy_cellphone_id',$id)->orderBy('id','desc')->first();
      if($data){
        return view('admin.agentAppUploads.cellphone_delete_record', compact('data'));
      }else{
        return Redirect::back()->with('error', 'No Data Found');
      } 
    }

    public function deviceData(){
        return view('admin.agentAppUploads.devices');
    }

    /**
     * /*
     * Pass data through ajax call
     * @return mixed
     */

    /*->orWhere('policies.policyNumber', 'like', '%' .$searchValue . '%')
                    ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'),'like','%'.$searchValue.'%')
                    ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.lastName)'),'like','%'.$searchValue.'%');*/

    public function inspectionData(Request $request)
    {
        //dd($request->all());
        $records = Vehicle::orderBy('id', 'DESC')
            ->leftJoin('customer', 'customer.id', 'vehicle.customer_id')
            ->leftJoin('policies', 'policies.id', 'vehicle.policy_id')
            ->where('policies.product_id',3)
            ->where('policies.status', '!=' , 2);
            // ->whereIn('policies.product_id', [ 2 , 3] );

        if ($request->customer_name != null) {
            $records->where(DB::raw('CONCAT_WS(" ", customer.firstName, customer.lastName)'),'like','%'.$request->customer_name.'%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName)'),'like','%'.$request->customer_name.'%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.lastName)'),'like','%'.$request->customer_name.'%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'), 'like', '%' .$request->customer_name. '%');
        }
        if ($request->policy_number != null) {
            $records->where('policies.policyNumber', $request->policy_number);
        }
        if ($request->vehicle_plate != null) {
            $records->where('vehicle.vehiclePlate', $request->vehicle_plate);
        }

        $vehicleData = $records->get(['vehicle.id', 'vehicle.vehiclePlate', 'vehicle.front', 'vehicle.back', 'vehicle.left', 'vehicle.right','vehicle.status','vehicle.make','vehicle.model','vehicle.year','vehicle.policy_id','vehicle.vehicleRegistration','vehicle.created_at','vehicle.vehicle_valuation']);

        return DataTables::of($vehicleData)
            ->editColumn('created_at', function ($vehicleData)
            {
                return $vehicleData->created_at->diffForHumans();
            })
            ->addColumn('front', function ($vehicleData)
            {
                if ($vehicleData->front != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($vehicleData->front) . '" target= "_blank">' . $vehicleData->vehiclePlate . ' Front Picture </a>';
                }else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('back', function ($vehicleData)
            {
                if ($vehicleData->back != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($vehicleData->back) . '" target= "_blank">' . $vehicleData->vehiclePlate . ' Back Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('right', function ($vehicleData)
            {
                if ($vehicleData->right != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($vehicleData->right) . ' " target= "_blank">' . $vehicleData->vehiclePlate . ' Right Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('left', function ($vehicleData)
            {
                if ($vehicleData->left != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($vehicleData->left) . ' " target= "_blank">' . $vehicleData->vehiclePlate . ' Left Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('vehicleRegistration', function ($vehicleData)
            {
                if ($vehicleData->vehicleRegistration != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($vehicleData->vehicleRegistration) . ' " target= "_blank">' . $vehicleData->vehiclePlate . ' Registration Book </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('vehicle_valuation', function ($vehicleData)
            {
                if ($vehicleData->vehicle_valuation != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($vehicleData->vehicle_valuation) . ' " target= "_blank">' . $vehicleData->vehiclePlate . ' Vehicle Invoice </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('status', function ($vehicleData)
            {
                if($vehicleData->status == 1)
                    $status = '<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Approved</span>';
                elseif($vehicleData->status == 2)
                    $status = '<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Unapproved</span>';
                elseif($vehicleData->status == 3)
                    $status = '<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Recheck</span>';
                else
                    $status = '<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Pending</span>';


                return $status;
            })
            ->addColumn('actions', function ($vehicleData)
            {
                $actions = '';
                $actions .= '<a href="' . route('admin.customerInspection.edit', $vehicleData->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="la la-edit"></i>
                                </a>';

                $actions .= '<a href="' . route('admin.customerInspection.view', $vehicleData->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-eye"></i>
                            </a>';

                return $actions;
            })
            ->addColumn('policy_id', function ($vehicleData)
            {
                if($vehicleData->policy_id){
                    $policy = Policy::where('id',$vehicleData->policy_id)->first(array('policyNumber'));
                    if($policy)
                        return $policy->policyNumber;
                    else
                        return '--';
                }else{
                    return '--';
                }
            })
            ->addColumn('year', function ($vehicleData)
            {
                    if($vehicleData->year)
                        return $vehicleData->year;
                    else
                        return '--';
            })
            ->addColumn('model', function ($vehicleData)
            {
                if($vehicleData->model)
                    return $vehicleData->model;
                else
                    return '--';
            })
            ->addColumn('make', function ($vehicleData)
            {
                if($vehicleData->make)
                    return $vehicleData->make;
                else
                    return '--';
            })
            ->rawColumns(['actions','vehicle_valuation', 'status', 'front', 'back', 'right', 'left','vehicleRegistration','make','model','year','policy_id'])
            ->make(true);
    }

    public function verify($id){
        try{
            $data = Vehicle::where('id',$id)->orderBy('id','DESC')->first();
            $policydetails = Policy::where('id',$data->policy_id)->first();
            if($policydetails){
             $activity = Audit::orderBy('created_at', 'desc')
             ->where('event','updated')
             ->where('auditable_type','AlphaDirect\Vehicle')
             //->where('policy_id',$policydetails->id)
             ->where('user_id','!=',null)
             ->where('policy_number',$policydetails->policyNumber)
             ->first(array('id','agent_id','user_id'));

             if($activity){
                 $performedBy = User::where('id',$activity->user_id)->first(array('firstName','lastName'));
             }else{
                 $performedBy = User::where('id',$data->added_by)->first(array('firstName','lastName'));
             }
            }else{
                 $performedBy = User::where('id',$data->added_by)->first(array('firstName','lastName'));
            }

            $policy = Policy::where('id',$data->policy_id)->first(array('policyNumber'));
            $data['policy_id'] = $policy->policyNumber;
            return view('admin.agentAppUploads.viewInspection', compact('data','performedBy'));
        }catch(\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }
    public function verifyedit($id){
        try{
            $data = Vehicle::where('id',$id)->orderBy('id','DESC')->first();
            $policydetails = Policy::where('id',$data->policy_id)->first();
            if($policydetails){
             $activity = Audit::orderBy('created_at', 'desc')
             ->where('event','updated')
             ->where('auditable_type','AlphaDirect\Vehicle')
             //->where('policy_id',$policydetails->id)
             ->where('user_id','!=',null)
             ->where('policy_number',$policydetails->policyNumber)
             ->first(array('id','agent_id','user_id'));

             if($activity){
                 $performedBy = User::where('id',$activity->user_id)->first(array('firstName','lastName'));
             }else{
                 $performedBy = User::where('id',$data->added_by)->first(array('firstName','lastName'));
             }
            }else{
                 $performedBy = User::where('id',$data->added_by)->first(array('firstName','lastName'));
            }

            $policy = Policy::where('id',$data->policy_id)->first(array('policyNumber'));
            $data['policy_id'] = $policy->policyNumber;
            return view('admin.agentAppUploads.editInspection', compact('data','performedBy'));
        }catch(\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function deviceInspectionData(){
        $cellphone = \AlphaDirect\PolicyCellPhone::join('policies','policies.id','policy_cellphone.policy_id')
        ->where('policies.status', '!=' , 2)
        ->whereNotNull('policy_cellphone.customer_id')
        ->get(['policy_cellphone.id','policy_cellphone.policy_id','policy_cellphone.customer_id','policy_cellphone.cell_phone_front',
    'policy_cellphone.cell_phone_back','policy_cellphone.cell_phone_right','policy_cellphone.cell_phone_left','policy_cellphone.cell_phone_top',
    'policy_cellphone.cell_phone_bottom','policy_cellphone.status','policy_cellphone.device_type','policy_cellphone.imei',
    'policy_cellphone.cell_phone_make','policy_cellphone.cell_phone_model'
    
    ]);
        

        return DataTables::of($cellphone)
            ->addColumn('policy_id', function ($cellphone)
            {
                $policy = \AlphaDirect\Policy::where('id',$cellphone->policy_id)->first(array('policyNumber'));

                if($policy)
                    return $policy->policyNumber;
                else
                    return 'N/A';
            })
            ->addColumn('customer_id', function ($cellphone)
            {
                $customer = \AlphaDirect\Customer::where('id',$cellphone->customer_id)->first(array('firstName','middleName','lastName'));
                if($customer)
                    return ucwords($customer->firstName).' '.ucwords($customer->middleName).' '.ucwords($customer->lastName);
                else
                    return 'N/A';

            })
            ->addColumn('cell_phone_front', function ($cellphone)
            {
                if ($cellphone->cell_phone_front != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($cellphone->cell_phone_front) . ' " target= "_blank">Front Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('cell_phone_back', function ($cellphone)
            {
                if ($cellphone->cell_phone_back != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($cellphone->cell_phone_back) . ' " target= "_blank">Back Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('cell_phone_right', function ($cellphone)
            {
                if ($cellphone->cell_phone_right != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($cellphone->cell_phone_right) . ' " target= "_blank">Right Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('cell_phone_left', function ($cellphone)
            {
                if ($cellphone->cell_phone_left != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($cellphone->cell_phone_left) . ' " target= "_blank">Left Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('cell_phone_top', function ($cellphone)
            {
                if ($cellphone->cell_phone_top != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($cellphone->cell_phone_top) . ' " target= "_blank">Top Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('cell_phone_bottom', function ($cellphone)
            {
                if ($cellphone->cell_phone_bottom != null)
                {
                    return '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($cellphone->cell_phone_bottom) . ' " target= "_blank">Bottom Picture </a>';
                }
                else
                {
                    return 'Not uploaded';
                }
            })
            ->addColumn('action', function ($cellphone)
            {
                $actions = '';
                $actions .= '<a href="' . route('admin.deviceInspection.edit', $cellphone->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="la la-edit"></i>
                                </a>';

                $actions .= '<a href="' . route('admin.deviceInspection.view', $cellphone->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-eye"></i>
                            </a>';

                return $actions;
            })
            ->addColumn('status', function ($cellphone)
            {
                if($cellphone->status == 1)
                    return 'Approved';
                else
                    return 'Unapproved';
            })
            ->rawColumns(['status', 'action', 'cell_phone_bottom', 'cell_phone_top', 'cell_phone_left', 'cell_phone_right','cell_phone_back','cell_phone_front','customer_id','policy_id'])
            ->make(true);
    }

    public function editDeviceData($id){
        try{
            $data = PolicyCellPhone::leftJoin('policies','policies.id','policy_cellphone.policy_id')
                ->leftJoin('customer','customer.id','policy_cellphone.customer_id')
                ->leftJoin('policy_cellphone_device_status','policy_cellphone_device_status.policy_cellphone_id','policy_cellphone.id')
                ->where('policy_cellphone.id',$id)
                ->first(array(
                        'customer.firstName',
                        'customer.middleName',
                        'customer.lastName',
                        'policy_cellphone.device_type',
                        'policy_cellphone.id',
                        'policy_cellphone.imei',
                        'policy_cellphone.phone_value',
                        'policy_cellphone.cell_phone_make',
                        'policy_cellphone.cell_phone_model',
                        'policy_cellphone.cell_phone_front',
                        'policy_cellphone.cell_phone_back',
                        'policy_cellphone.cell_phone_left',
                        'policy_cellphone.cell_phone_right',
                        'policy_cellphone.cell_phone_top',
                        'policy_cellphone.cell_phone_bottom',
                        'policy_cellphone.created_at',
                        'policy_cellphone.updated_at',
                        'policies.policyNumber',
                        'policy_cellphone_device_status.status',
                        'policy_cellphone_device_status.cell_phone_top_status',
                        'policy_cellphone_device_status.cell_phone_top_remark',
                        'policy_cellphone_device_status.cell_phone_bottom_status',
                        'policy_cellphone_device_status.cell_phone_bottom_remark',
                        'policy_cellphone_device_status.cell_phone_left_status',
                        'policy_cellphone_device_status.cell_phone_left_image_remark',
                        'policy_cellphone_device_status.cell_phone_right_status',
                        'policy_cellphone_device_status.cell_phone_right_image_remark',
                        'policy_cellphone_device_status.cell_phone_back_status',
                        'policy_cellphone_device_status.cell_phone_back_image_remark',
                        'policy_cellphone_device_status.cell_phone_front_status',
                        'policy_cellphone_device_status.cell_phone_front_image_remark',
                        'policy_cellphone_device_status.remark',
                    )
                );

            return view('admin.agentAppUploads.editDeviceData', compact('data'));
        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function viewDeviceData($id){
        try{
            $data = PolicyCellPhone::leftJoin('policies','policies.id','policy_cellphone.policy_id')
                ->leftJoin('customer','customer.id','policy_cellphone.customer_id')
                ->leftJoin('policy_cellphone_device_status','policy_cellphone_device_status.policy_cellphone_id','policy_cellphone.id')
                ->where('policy_cellphone.id',$id)
                ->first(array(
                        'policies.policyNumber',
                        'customer.firstName',
                        'customer.middleName',
                        'customer.lastName',
                        'policy_cellphone.device_type',
                        'policy_cellphone.id',
                        'policy_cellphone.imei',
                        'policy_cellphone.phone_value',
                        'policy_cellphone.cell_phone_make',
                        'policy_cellphone.cell_phone_model',
                        'policy_cellphone.cell_phone_front',
                        'policy_cellphone.cell_phone_back',
                        'policy_cellphone.cell_phone_left',
                        'policy_cellphone.cell_phone_right',
                        'policy_cellphone.cell_phone_top',
                        'policy_cellphone.cell_phone_bottom',
                        'policy_cellphone_device_status.status',
                        'policy_cellphone.created_at',
                        'policy_cellphone.updated_at',
                        'policy_cellphone_device_status.cell_phone_top_status',
                        'policy_cellphone_device_status.cell_phone_top_remark',
                        'policy_cellphone_device_status.cell_phone_bottom_status',
                        'policy_cellphone_device_status.cell_phone_bottom_remark',
                        'policy_cellphone_device_status.cell_phone_left_status',
                        'policy_cellphone_device_status.cell_phone_left_image_remark',
                        'policy_cellphone_device_status.cell_phone_right_status',
                        'policy_cellphone_device_status.cell_phone_right_image_remark',
                        'policy_cellphone_device_status.cell_phone_back_status',
                        'policy_cellphone_device_status.cell_phone_back_image_remark',
                        'policy_cellphone_device_status.cell_phone_front_status',
                        'policy_cellphone_device_status.cell_phone_front_image_remark',
                        'policy_cellphone_device_status.remark',
                    )
                );
            return view('admin.agentAppUploads.viewDeviceData', compact('data'));

        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function updateDeviceStatus(Request $request){
        try{
            $data = CellphoneDeviceStatus::where('policy_cellphone_id',$request->data_id)->first();
            $device = PolicyCellPhone::where('id',$request->data_id)->first();

            if($data == null)
                $data = new CellphoneDeviceStatus();

            $data->policy_cellphone_id = $request->data_id;
            $data->cell_phone_top_status = $request->cell_phone_top_status;
            $data->cell_phone_top_remark = $request->cell_phone_top_remark;

            $data->cell_phone_bottom_status = $request->cell_phone_bottom_status;
            $data->cell_phone_bottom_remark = $request->cell_phone_bottom_remark;

            $data->cell_phone_left_status = $request->cell_phone_left_status;
            $data->cell_phone_left_image_remark = $request->cell_phone_left_image_remark;

            $data->cell_phone_right_status = $request->cell_phone_right_status;
            $data->cell_phone_right_image_remark = $request->cell_phone_right_image_remark;

            $data->cell_phone_back_status = $request->cell_phone_back_status;
            $data->cell_phone_back_image_remark = $request->cell_phone_back_image_remark;

            $data->cell_phone_front_status = $request->cell_phone_front_status;
            $data->cell_phone_front_image_remark = $request->cell_phone_front_image_remark;

            if((isset($request->cell_phone_top_status) && $request->cell_phone_top_status == 1)
            && (isset($request->cell_phone_bottom_status) && $request->cell_phone_bottom_status == 1)
            && (isset($request->cell_phone_left_status) && $request->cell_phone_left_status == 1)
            && (isset($request->cell_phone_right_status) && $request->cell_phone_right_status == 1)
            && (isset($request->cell_phone_back_status) && $request->cell_phone_back_status == 1)
            && (isset($request->cell_phone_front_status) && $request->cell_phone_front_status == 1)){
                $status = 1;
            }else{
                $status = 0;
            }

            $data->status = $status;
            $data->remark = $request->remark;

            $reason = '';
            if($status == 1) {
                $chk=PolicyController::checkMotorpolicyStatus(5,$device->policy_id,1);  
                if($chk==1){
                Policy::where('id', $device->policy_id)
                ->update([
                    'status' => 1
                    ]);
                }
                $reason .= 'Approved';
            }else {

                if (isset($request->cell_phone_top_status)) {
                    if($request->cell_phone_top_status == 0) {
                        if (!empty($request->cell_phone_top_remark)) {
                            $reason .= "Device Top : " . $request->cell_phone_top_remark . "<br>";
                        } elseif ($data && $device->cell_phone_top == null && $request->cell_phone_top_status == 0) {
                            $reason .= "Device Top : Not Uploaded<br>";
                        } else {
                            $reason .= 'Device Top : Reason  Not Mentioned' . "<br>";
                        }
                    }
                }else{
                    $reason .= "Device Top : Not Uploaded<br>";
                }
                if (isset($request->cell_phone_bottom_status)) {
                    if($request->cell_phone_bottom_status == 0) {
                        if (!empty($request->cell_phone_bottom_remark)) {
                            $reason .= "Device Bottom: " . $request->cell_phone_bottom_remark . "<br>";
                        } elseif ($data && $device->cell_phone_bottom == null && $request->cell_phone_bottom_status == 0) {
                            $reason .= "Device Bottom: Not Uploaded<br>";
                        } else {
                            $reason .= 'Device Bottom: Reason  Not Mentioned' . "<br>";
                        }
                    }
                }else{
                    $reason .= "Device Bottom : Not Uploaded<br>";
                }
                if (isset($request->cell_phone_left_status)) {
                    if($request->cell_phone_left_status == 0) {
                        if (!empty($request->cell_phone_left_image_remark)) {
                            $reason .= "Device Left: " . $request->cell_phone_left_image_remark . "<br>";
                        } elseif ($data && $device->cell_phone_left == null && $request->cell_phone_left_status == 0) {
                            $reason .= "Device Left: Not Uploaded<br>";
                        } else {
                            $reason .= 'Device Left: Reason  Not Mentioned' . "<br>";
                        }
                    }
                }else{
                    $reason .= "Device Left : Not Uploaded<br>";
                }
                if (isset($request->cell_phone_right_status)) {
                    if($request->cell_phone_right_status == 0) {
                        if (!empty($request->cell_phone_right_image_remark)) {
                            $reason .= "Device Right: " . $request->cell_phone_right_image_remark . "<br>";
                        } elseif ($data && $device->cell_phone_right == null) {
                            $reason .= "Device Right: Not Uploaded<br>";
                        } else {
                            $reason .= 'Device Right: Reason  Not Mentioned' . "<br>";
                        }
                    }
                }else{
                    $reason .= "Device Right : Not Uploaded<br>";
                }
                if (isset($request->cell_phone_back_status)) {
                    if($request->cell_phone_back_status == 0) {
                        if (!empty($request->cell_phone_back_image_remark)) {
                            $reason .= "Device Back: " . $request->cell_phone_back_image_remark . "<br>";
                        } elseif ($data && $device->cell_phone_back == null) {
                            $reason .= "Device Back: Not Uploaded<br>";
                        } else {
                            $reason .= 'Device Back: Reason  Not Mentioned' . "<br>";
                        }
                    }
                }else{
                    $reason .= "Device Back : Not Uploaded<br>";
                }

                if (isset($request->cell_phone_front_status)) {
                    if($request->cell_phone_front_status == 0) {
                        if (!empty($request->cell_phone_front_image_remark)) {
                            $reason .= "Device Front: " . $request->cell_phone_front_image_remark . "<br>";
                        } elseif ($data && $device->cell_phone_front == null) {
                            $reason .= "Device Front: Not Uploaded<br>";
                        } else {
                            $reason .= 'Device Front: Reason  Not Mentioned' . "<br>";
                        }
                    }
                }else{
                    $reason .= "Device Front : Not Uploaded<br>";
                }
            }

            $data->reason = $reason;
            $data->save();

            $device->status = $status;
            $device->reason = $reason;
            $device->save();

            $customer = Customer::where('id',$device->customer_id)->first(array('firstName','lastName','email','cellphone'));
            $mail = $customer->email;
            $cname = $customer->firstName.' '.$customer->lastName;
            $cnumber = $customer->cellphone;

            $policy = Policy::where('id',$device->policy_id)->first();

            if($policy->agent_id != null){
                $incentivetype = 5;
                $amount = $policy->premium;
                $status = $status;
                event(new \Modules\Incentive\Events\AddIncentive($policy,$incentivetype,$status,$amount));
            }
            $type = 5;
            event(new \Modules\Cashback\Events\CustomerCashbackEvent($policy,$type,$status));

            if($policy && $policy->agent_id) {
                $user = User::leftJoin('user_profile','user_profile.user_id','users.id')
                    ->where('users.id', $policy->agent_id)
                    ->first(array('users.email','users.firstName','users.lastName','user_profile.cellphone'));
                if($user && $user->email) {
                    $agnetEmail = $user->email;
                    $aname = $user->firstName.' '.$user->lastName;
                    $anumber = $user->cellphone;
                }else{
                    $agnetEmail = null;
                    $anumber = null;
                }
            }else{
                $agnetEmail = null;
                $anumber = null;
            }

            /**Email Functionality*/
            if($mail != null && $status == 0){
                $email = new \stdClass();
                $email->user_id = $device->id;
                $email->hook = 'device_preinspection_customer';
                $email->customer_id = $device->customer_id;
                $email->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($mail,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $email->hook]));
               // $sent = \Illuminate\Support\Facades\Mail::to($mail)->send(new MailTemplate($email));
            }

            if($agnetEmail != null && $status == 0){
                $email = new \stdClass();
                $email->user_id = $device->id;
                $email->hook = 'device_preinspection_agent';
                $email->customer_id = $device->customer_id;
                $email->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($agnetEmail,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $email->hook]));
               // $sent = \Illuminate\Support\Facades\Mail::to($agnetEmail)->send(new MailTemplate($email));
            }

            /**SMS Functionality*/
            $sms = new SmsMessaging();

            if($anumber != null && $status == 0){
                $sendCustomer = $sms->smsDevicePreinspectionAgent(15,$cname,$aname,$policy->policyNumber,$device->cell_phone_make,$device->cell_phone_model,$device->device_type,$anumber);
            }

            if($cnumber != null && $status == 0){
                $sendCustomer = $sms->smsDevicePreinspectionCustomer(16,$cname,$policy->policyNumber,$device->cell_phone_make,$device->cell_phone_model,$device->device_type,$cnumber);
            
                 $data1 =[
                    "type"=>"template",
                    "subType"=>"preinspection_reject",
                    "mobileNumber"=>'267'.$cnumber,
                    "policyNumber"=>$policy->policyNumber,
                    "customer_name"=>$cname,
                    "customer_id"=>  $device->customer_id,
                    "cell_phone_make"=>$device->cell_phone_make,
                    "phone_model"=>$device->cell_phone_model,
                    "device_type"=>$device->device_type

                ];
                
                $WhatsAppController=  new WhatsAppController();
                $WhatsAppController->sendMessage($data1);
                
            }

            return Redirect::back()->with('success', 'Device data status updated');

        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function view($id){
        try{
            $data = Vehicle::where('id',$id)->orderBy('id','DESC')->first();


            if($data->policy_id)
                $policy = Policy::where('id',$data->policy_id)->first(array('policyNumber'))->policyNumber;
            else
                $policy = 'N/A';

            $data['policy_id'] = $policy;
            return view('admin.agentAppUploads.verifyInspection', compact('data'));
        }catch(\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request){
        try{
            $data = Vehicle::where('id',$request->data_id)->first();
            if($data->vehiclePlate){
                $info = Vehicle::where('vehiclePlate',$data->vehiclePlate)->orderBy('id','DESC')->first(array('policy_id'));
                if($info != null){
                   $policy = Policy::where('id',$info->policy_id)->first();
                   if($policy){
                       $customer = Customer::where('id',$policy->customer_id)->first(array('id','firstName','cellphone','email'));
                       $agent = User::join('user_profile','user_profile.user_id','users.id')
                           ->where('users.id',$policy->agent_id)
                           ->first(array('users.id','users.firstName','users.firstName','user_profile.cellphone','users.email'));
                       if($customer){
                            if($customer->email)
                                $mail = $customer->email;
                            else
                                $mail = null;

                           if($customer->cellphone)
                               $cellphone = $customer->cellphone;
                           else
                               $cellphone = null;
                       }
                   }
                }
            }

            if(isset($request->data_id)){
                $data->front_status = isset($request->front_status) ? $request->front_status : 0;
                $data->front_image_remark = isset($request->front_image_remark) ? $request->front_image_remark : '';
                $data->back_status= isset($request->back_status) ? $request->back_status : 0;
                $data->back_image_remark = isset($request->back_image_remark) ? $request->back_image_remark : '';
                $data->right_status = isset($request->right_status) ? $request->right_status : 0;
                $data->right_image_remark = isset($request->right_image_remark) ? $request->right_image_remark : '';
                $data->left_status = isset($request->left_status) ? $request->left_status : 0;
                $data->left_image_remark = isset($request->left_image_remark) ? $request->left_image_remark : '';
                $data->vehicle_registration_status = isset($request->vehicle_registration_status) ? $request->vehicle_registration_status : '';
                $data->registration_image_remark = isset($request->registration_image_remark) ? $request->registration_image_remark : 0;

                $data->vehicle_invoice_status = isset($request->vehicle_invoice_status) ? $request->vehicle_invoice_status : 0;
                $data->vehicle_invoice_remark = isset($request->vehicle_invoice_remark) ? $request->vehicle_invoice_remark : 0;

                $data->compliance = $this->getVehicleComplianceStatus($request);
                $data->remark = $request->remark;
                $reason = '';
                if($data->compliance == 1) {
                    $reason .= 'Approved';
                    $chk=PolicyController::checkMotorpolicyStatus(3,$info->policy_id,1);  
                    if($chk==1){
                    Policy::where('id', $info->policy_id)
                    ->update([
                        'status' => 1
                        ]);
                    }
                    $data->status = 1;

                }else{
                    if(isset($request->front_status) && $request->front_status == 0){
                        if(!empty($request->front_image_remark)) {
                            $reason .= "Vehicle Front: " . $request->front_image_remark . "<br>";
                        }elseif($data && $data->front == null){
                            $reason .= "Vehicle Front: Not Uploaded<br>";
                        }else{
                            $reason .= 'Vehicle Front: Reason  Not Mentioned'. "<br>";
                        }
                    }
                    if(isset($request->back_status) && $request->back_status == 0){
                        if(!empty($request->back_image_remark)) {
                            $reason .= "Vehicle Back: " . $request->back_image_remark . "<br>";
                        }elseif($data && $data->back == null){
                            $reason .= "Vehicle Back: Not Uploaded<br>";
                        }else{
                            $reason .= 'Vehicle Back: Reason  Not Mentioned'. "<br>";
                        }
                    }
                    if(isset($request->right_status) && $request->right_status == 0){
                        if(!empty($request->right_image_remark)) {
                            $reason .= "Vehicle Right: " . $request->right_image_remark . "<br>";
                        }elseif($data && $data->right == null){
                            $reason .= "Vehicle Right: Not Uploaded<br>";
                        }else{
                            $reason .= 'Vehicle Right: Reason  Not Mentioned'. "<br>";
                        }
                    }
                    if(isset($request->left_status) && $request->left_status == 0){
                        if(!empty($request->left_image_remark)) {
                            $reason .= "Vehicle Left: " . $request->left_image_remark . "<br>";
                        }elseif($data && $data->left == null){
                            $reason .= "Vehicle Left: Not Uploaded<br>";
                        }else{
                            $reason .= 'Vehicle Left: Reason  Not Mentioned'. "<br>";
                        }
                    }
                    if(isset($request->vehicle_registration_status) && $request->vehicle_registration_status == 0){
                        if(!empty($request->registration_image_remark)) {
                            $reason .= "Vehicle Registration Book: " . $request->registration_image_remark . "<br>";
                        }elseif($data && $data->vehicleRegistration == null){
                            $reason .= "Vehicle Registration Book: Not Uploaded<br>";
                        }else{
                            $reason .= 'Vehicle Registration Book: Reason  Not Mentioned'. "<br>";
                        }
                    }
                    $data->status = 2;
                }

                $data->reason = $reason;
                $data->save();

                if($policy->agent_id != null){
                    $incentivetype = 4;
                    $amount = $policy->premium;
                    $status = $data->compliance;
                    event(new \Modules\Incentive\Events\AddIncentive($policy,$incentivetype,$status,$amount));
                }
                $status = $data->compliance;
                $type = 4;
                event(new \Modules\Cashback\Events\CustomerCashbackEvent($policy,$type,$status));

                /**Email Functionality*/
                if($mail != null && $data->compliance == 2){
                    $email = new \stdClass();
                    $email->user_id = $data->id;
                    $email->hook = 'vehicle_preinspection';
                    $email->customer_id = $customer->id;
                    $email->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $email->hook)->first(array('subject'));
                    $markdown = new MailTemplate($email);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$email]);
                    event(new \AlphaDirect\Events\SendMail($mail,$emailTemplate->subject,"",$html,NULL,['policyNumber' => $policy->policyNumber,'hook' => $email->hook]));
                   // $sent = Mail::to($mail)->send(new MailTemplate($email));
                }

                if($policy->agent_id != null && $agent->email != null && $data->compliance == 2){
                    $email = new \stdClass();
                    $email->user_id = $data->id;
                    $email->hook = 'agent_vehicle_preinspection';
                    $email->customer_id = $customer->id;
                    $email->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $email->hook)->first(array('subject'));
                    $markdown = new MailTemplate($email);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$email]);
                    event(new \AlphaDirect\Events\SendMail($mail,$emailTemplate->subject,"",$html,NULL,['policyNumber' => $policy->policyNumber,'hook' => $email->hook]));

                }
                $name = $customer->firstName.' '.$customer->lastName;
                /**SMS Functionality*/
                $sms = new SmsMessaging();
                if($cellphone != null && $data->compliance == 2){
                    $sendCustomer = $sms->smsCustomerPreinspection(12,$name,$policy->policyNumber,$cellphone);
               
                    $data1 =[
                        "type"=>"template",
                        "subType"=>"vehicle_preinspection_reject",
                        "mobileNumber"=>'267'.$cellphone,
                        "policyNumber"=>$policy->policyNumber,
                        "customer_name"=>$name,
                        "customer_id"=>  $policy->customer_id
    
                    ];
                    
                    $WhatsAppController=  new WhatsAppController();
                    $WhatsAppController->sendMessage($data1);
                    
                }
                if($agent && $agent->cellphone){
                    $sendAgent = $sms->smsAgentPreinspection(13,$agent->firstName,$name,$policy->policyNumber,$agent->cellphone);
                              }
                if($data->save())
                    return Redirect::back()->with('success', 'Information updated successfully');
                else
                    return Redirect::back()->with('error', 'Something went wrong');
            }
            else{
                return Redirect::back()->with('error', 'Missing data');
            }
        }catch(\Exception $exception){
            return Redirect::back()->with('error', $exception->getMessage());
        }
    }
    public function getVehicleComplianceStatus(Request $request){
        try{
            if(isset($request->front_status) && $request->front_status == 1
                && isset($request->front_status) && $request->front_status == 1
                && isset($request->back_status) && $request->back_status == 1
                && isset($request->right_status) && $request->right_status == 1
                && isset($request->left_status) && $request->left_status == 1
                && isset($request->vehicle_registration_status) && $request->vehicle_registration_status == 1
                && isset($request->vehicle_invoice_status) && $request->vehicle_invoice_status == 1){
                return 1;
            }else{
                return 2;
            }
        }catch(\Exception $e){
            return 0;
        }
    }
    public function sendemailurllink(Request $request)
    {

    $vehicleData = Vehicle::where('id', $request->id )->first();
    if($vehicleData != null){
       $customer = Customer::where('id', $vehicleData->customer_id)->first();
    if($customer != null){
    if($customer->email != null){
       $policy = Policy::where('id',$vehicleData->policy_id )->first();
    if($policy != null){
       $str = $vehicleData->id;
       $xy = base64_encode($str);
       $url = env('START_URL').'vehicle_update1.php?id='.$xy;
       $mail = $customer->email;
       $email = new \stdClass();
       $email->user_id = null;
       $email->hook = 'agent_vehicle_preinspection';
       $email->customer_id = $vehicleData->customer_id;
       $email->firstName = $customer->firstName;
       $email->middleName = $customer->middleName;
       $email->lastName = $customer->lastName;
       $email->attachment = null;
       $email->url = $url;
       $markdown = new PreInspectionMail($email);
       $html = $markdown->render('Mail.PreInspectionMail',['data'=>$email]);
       event(new \AlphaDirect\Events\SendMail($mail,"Alphadirect | Upload Photos of vehicle","",$html,null,[]));
    if($customer->cellphone != null){
       $customer_firstName = $customer->firstName;
       $customer_lastName =  $customer->lastName;
       $customer_id = $customer->id;
       $phoneNumber = $customer->cellphone;
       $sms = new SmsMessaging();
       $sms->SendSMSVehicleUploadPhotosLink($phoneNumber,$url,$customer_id,$customer_firstName,$customer_lastName);

       return redirect()->back()->with('success', 'Vehicle Photos upload link send on Customer email and cellphone');
      }return redirect()->back()->with('error', 'Cellphone no. or Email is not present');
         }
        }
       }
      }
     }
    }
