<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Accounts;
use AlphaDirect\City;
use AlphaDirect\Config;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\State;
use AlphaDirect\TermsConditions;
use AlphaDirect\User;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\Vehicle;
use AlphaDirect\VehicleMake;
use Http\Client\Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Exports\DeviceMakeModelExport;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;

class ConfigController extends Controller
{
    public function getWordings()
    {

        $config = Config::where('key', 'tc_agent_app')->first(array('key', 'value'));
        $refinedText = strip_tags($config['value']);

        return response()->json($refinedText, 200);
    }

    public function getEnvVariables(Request $request)
    {

        $graphiteUrl = env('GRAPHITE_URL');
        $urlValue = env('LIVEQUOTE_URL');
        $urlValue1 = env('START_URL');
        $client_auth_key = env('client_auth_key');

        if (isset($request->requirement)) {
            $requirements = ($request->requirement)->toArray();
            $requirementOutput = array();
            foreach ($requirements as $r) {

            }
        }

        // $infobibUsername = env('INFOBIP_USERNAME');
        //$infobibPassword = env('INFOBIP_PASSWORD');

        return response(
            [
                'Graphite Url' => $graphiteUrl,
                'LIVEQUOTE_URL' => $urlValue,
                'Start' => $urlValue1,
                'client_auth_key' => $client_auth_key

            ]
        );

    }

    public function getTermsConditions()
    {
        try {
            return view('admin.TermsConditions.index');
        } catch (Exception $e) {

        }
    }

    public function clearCache()
    {
        try{
         Artisan::call('optimize:clear');
         return response()->json(['message'=> 'Great! Cache cleared.']);
        }catch(Exception $ex)
        {
         return response()->json(['message'=>  $ex->getMessage()]);
        }
    }

    public function terms_conditions_data()
    {
        try {
            $tc = TermsConditions::get();
            return DataTables::of($tc)
            ->editColumn('created_at', function ($tc) {
                if ($tc->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $tc->created_at)->format('Y-m-d H:i') ;
                }
            })
                ->editColumn('paymentFrequency', function ($tc) {
                    if ($tc->paymentFrequency != null) {
                        switch ($tc->paymentFrequency) {
                            case 1:
                                return 'Monthly Instalments';
                                break;

                            case 2:
                                return 'Three Instalments in a year';
                                break;

                            case 3:
                                return 'Annual Instalments';
                                break;
                            case -1:
                                return 'All Frequencies';
                                break;

                            default:
                                return 'Payment frequency not found';
                                break;

                        }
                    } else {
                        return 'Payment frequency not found';
                    }
                })
                ->editColumn('long_text', function ($tc) {
                    if ($tc->long_text) {
                        return '<a href ="' . Storage::disk('s3')->url($tc->long_text) . ' " target= "_blank"> View Document </a>';
                    } else {
                        return '-';
                    }

                })
                ->editColumn('product_id', function ($tc) {
                    if ($tc->product_id) {
                        if ($tc->product_id != -1) {
                            $product = Product::where('id', $tc->product_id)->first(array('name'));
                            if ($product) {
                                if ($product->name) {
                                    return $product->name;
                                } else {
                                    return 'Product name not found';
                                }
                            } else {
                                return 'Product not found with ID: ' . $tc->product_id;
                            }
                        } else {
                            return 'All Products';
                        }
                    } else {
                        return '-';
                    }

                })
                ->rawColumns(['paymentFrequency', 'long_text', 'product_id'])
                ->make(true);
        } catch (Exception $e) {

        }
    }

    public function storeEmails(Request $request)
    {
        try {

            $invalid = array();
            $invCount = 0;
            $valid = 0;

            if (count($request->emails)) {
                foreach ($request->emails as $e) {
                    if ($e['email'] != null) {

                        $find1 = strpos($e['email'], '@');
                        $find2 = strpos($e['email'], '.');
                        $r = ($find1 !== false && $find2 !== false && $find2 > $find1);

                        if ($r) {
                            $valid += 1;
                            $data = new RealpayFailedTransEmails();
                            $data->email = $e['email'];
                            $data->added_by = auth::user()->id;
                            $data->save();
                        } else {
                            $invCount += 1;
                            array_push($invalid, $e['email']);
                        }

                    }
                }
                $string = '';
                if ($invCount > 0 && $valid > 0) {
                    foreach ($invalid as $in) {
                        $string .= $in . ',';
                    }
                    return Redirect::route('emailList')->with('success', 'EMails added successfully but failed ' . $string . ' as these are not valid');
                } elseif ($invCount == 0 && $valid > 0) {
                    return Redirect::route('emailList')->with('success', 'EMails added successfully');
                } elseif ($invCount > 0 && $valid == 0) {
                    return Redirect::route('emailList')->with('error', 'Invalid emails ' . $string);
                } else {
                    return Redirect::route('emailList')->with('success', 'EMails added successfully');
                }


            } else {
                return Redirect::back()->with('error', 'No email to add in the list');
            }
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function emailList()
    {
        return view('admin.accounts.emailList');
    }

    public function emailData()
    {
        $d = RealpayFailedTransEmails::get();
        return DataTables::of($d)
            ->editColumn('added_by', function ($d) {
                if ($d->added_by != null) {
                    $user = User::where('id', $d->added_by)->first(array('firstName', 'lastName'));
                    return $user->firstName . ' ' . $user->lastName;
                } else {
                    return '-';
                }

            })
            ->editColumn('actions', function ($d) {
                if ($d->added_by != null) {
                    $actions = '<a href="" value="' . $d->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                    return $actions;
                } else {
                    return '-';
                }

            })
            ->editColumn('created_at', function ($d) {
                if ($d->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $d->created_at)->format('Y-m-d H:i') ;
                }
            })
            ->rawColumns(['added_by', 'actions'])
            ->make(true);
    }



    public function addTermsConditions(Request $request){
        try{
            $products = Product::where('status',1)->get(array('id','name'));
            return view('admin.TermsConditions.create',compact('products'));
        }catch(Exception $e){

        }
    }

    public function confirmRemoveEmail(Request $request){
        try{
            $body = 'Are you sure you want to remove this email ?';
            return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
        }catch(Exception $e){

        }
    }

    public function destroy($id){
        try{


            $d = RealpayFailedTransEmails::where('id',$id)->delete();

            return Redirect::route('emailList')->with('success', 'EMail Removed Successfully');

        }catch(Exception $e){
            return Redirect::route('emailList')->with('error', 'Something Went Wrong');
        }

    }

    public function confirmCancel(Request $request){
        $body = 'Are you sure you want to cancel the instalment ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
    }

    public function storeTermsConditions(Request $request){
        try{
            $data = new TermsConditions();
            $data->product_id = $request->productId;
            $data->short_text = $request->shortText;
            if($request->hasFile('fullTc')) {
                $file = $request->file('fullTc');
                $ext = $request->fullTc->getClientOriginalExtension();
                $path = 'PolicyTerms&Conditions/' . $request->productId . '/Terms_And_Conditions.'.$ext;
                Storage::disk('s3')->put($path, file_get_contents($file), 'public');
                $data->long_text = $path;
            }

            $data->paymentFrequency = $request->paymentFrequency;
            $data->save();


            return Redirect::back()->with('success', 'Terms and conditions added successfully');

        }catch(Exception $e){
            return Redirect::back()->with('error', 'failed to add terms and conditions');
        }
    }

    public function fetchTermsConditions(Request $request){
        try{
            if($request->frequency && $request->product_id){
                $tc = TermsConditions::where('paymentFrequency',$request->frequency)
                    ->where('product_id',$request->product_id)
                    ->orWhere('product_id',-1)
                    ->get(
                        array(
                            'short_text'
                        )
                    );

                if($tc != null){
                    return response()->json(['Status' => 'Success','TermsConditions'=>$tc], 200);
                }else{
                    return response()->json(['Status' => 'Failed','Description'=>'No Terms And Conditions found'], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed','Description'=>'Please provide all the required data'], 401);
            }
        }catch (Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function addEmailForRealPay(){
        try{
            return view('admin.accounts.addEmails');
        }catch(Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }
    public function deviceMakeModel()
    {
        $deviceMakeModelType = DeviceMakeModel::groupBy('device_type')
            ->where('device_type','!=','')
            ->whereNotNull('device_type')
            ->get(array('device_type'));
        return view('admin.deviceMakeAndModel.index',compact('deviceMakeModelType'));
    }

    public function deviceMakeModelData(Request $request)
    {
        $deviceMakeModel = DeviceMakeModel::whereNotNull('make_id')
            ->where('make_id','<>','')
            ->whereNotNull('device_type')
            ->orderBy('id', 'DESC');
        if (request('deviceTypeFilter') != '-1' )
        {
            $deviceMakeModel->where('device_type' , 'like', '%' . $request->deviceTypeFilter . '%' );
        }

        return DataTables::eloquent($deviceMakeModel)
            ->editColumn('id', function ($deviceMakeModel) {
                $id = 'NA';
                if($deviceMakeModel->id !=null){
                    $id = $deviceMakeModel->id;
                }
                return $id;
            })
            ->editColumn('make_id', function ($deviceMakeModel) {
                $make_id = 'NA';
                if($deviceMakeModel->make_id == null && $deviceMakeModel->make_id != 0){

                    if($deviceMakeModel->name){
                        $make_id = $deviceMakeModel->name;
                    }else{
                        $make_id = 'NA';
                    }

                }
                else{
                    $device = DeviceMakeModel::where('id',$deviceMakeModel->make_id)->first(array('name'));
                    if($device){
                        $make_id = $device->name;
                    }else{
                        $make_id = 'NA';
                    }
                }
                return $make_id;
            })
            ->editColumn('name', function ($deviceMakeModel) {
                $name = 'NA';
                if($deviceMakeModel->id !=null && $deviceMakeModel->make_id != null){
                    $name = $deviceMakeModel->name;
                }else{
                    $name = 'NA';
                }
                return $name;
            })
            ->editColumn('device_type', function ($deviceMakeModel) {
                $deviceType = 'NA';
                if($deviceMakeModel->id !=null){
                    $deviceType = $deviceMakeModel->device_type;
                }
                return $deviceType;
            })->addColumn('actions', function ($deviceMakeModel)
            {
                $actions = '';
                if($deviceMakeModel->id !=null)
                {
                    $actions .= '<a href="' . url('device-make-model/edit', $deviceMakeModel->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if($deviceMakeModel->id !=null)
                {
                    $actions .= '<a href="" value="'.$deviceMakeModel->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['id','actions'])
            ->make(true);
    }
    public function deviceMakeModelCreate()
    {
        $deviceMake = DeviceMakeModel::whereNull('make_id')
            ->orWhere('make_id','')
            ->get(array('id','name'));
        $deviceModel = DeviceMakeModel::whereNotNull('make_id')
            ->orWhere('make_id','!=','')
            ->get(array('id','name'));
        $deviceMakeModelType = DeviceMakeModel::groupBy('device_type')
            ->where('device_type','!=','')
            ->whereNotNull('device_type')
            ->get(array('device_type'));

        return view('admin.deviceMakeAndModel.create',compact('deviceMake','deviceModel','deviceMakeModelType'));
    }

    public function addNewDeviceMake()
    {
        return view('admin.deviceMakeAndModel.add_new_device_make');
    }

    public function deviceMakeModelAddNewDeviceStore(Request $request)
    {
        try{
            $deviceMakeModel = DeviceMakeModel::get(array('name'));
            if(isset($request->name) && $request->name != NULL)
            {
                foreach($deviceMakeModel as $device)
                {
                    if(ucwords(strtolower($request->name)) == ucwords(strtolower($device->name)))
                    {
                        if(ucwords(strtolower($request->device_type)) == ucwords(strtolower($device->device_type)))
                        {
                           return Redirect::back()->with('error', $request->name.' Device make already exist for device type '.$device->device_type);
                       }
                    }
                }
                    $data = new DeviceMakeModel();
                    $data->name = htmlspecialchars(strip_tags($request->name));
                    $data->device_type = htmlspecialchars(strip_tags($request->device_type));
                    $data->make_id = htmlspecialchars(strip_tags($request->make_id));
                    $data->save();

                    return Redirect('/device-make-model')->with('success', 'Device make added successfully');
            }
        }catch(Exception $e){
            return Redirect::back()->with('error', 'something went wrong');
        }
    }
    public function deviceMakeModelStore(Request $request)
    {
        try{
            $deviceMakeModel = DeviceMakeModel::get(array('name','device_type'));
                foreach($deviceMakeModel as $device)
                {
                    if(ucwords(strtolower($request->name)) == ucwords(strtolower($device->name)))
                    {
                            return Redirect::back()->with('error', $request->name.' device make already exist');
                    }
                }

            $data = new DeviceMakeModel();
            $data->name = htmlspecialchars(strip_tags($request->name));
            $data->device_type = htmlspecialchars(strip_tags($request->device_type));
            $data->save();

            return Redirect::back()->with('success','Device make added successfully');

        }catch(Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }
    public function deviceMakeModelEdit(Request $request, $id)
    {
        $deviceMakeModelEdit = DeviceMakeModel::where('id', $id)->first();
        $deviceMakeModel = DeviceMakeModel::get();
        return view('admin.deviceMakeAndModel.edit',compact('deviceMakeModelEdit','deviceMakeModel'));
    }

    public function deviceMakeModelUpdate(Request $request ,$id)
    {
        unset($request->_token,$request->_method);
        foreach ($request->all() as $key=>$info){
            if($info == null)
                return Redirect::back()->with('error', $key.' is required');
        }
        $check = DeviceMakeModel::where('make_id',$request->make_id)->where('device_type',$request->device_type)->where('name',$request->name)->exists();

        if($check == true)
            return Redirect::back()->with('error', $request->name .' model already exist for selected make and type '.$request->device_type);

        $data = DeviceMakeModel::where('id', $id)->first();
        $data->make_id =  htmlspecialchars(strip_tags($request->make_id ? $request->make_id:""));
        $data->name = htmlspecialchars(strip_tags($request->name));
        $data->device_type = htmlspecialchars(strip_tags($request->device_type));
        $data->save();
        return Redirect('/device-make-model');
    }

    public function getDeviceMakeModelDelete(Request $request)
    {
            $body = 'Are you sure you want to delete the Device Make Model ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }

    public function destroyMakeModel($id)
    {
        try
        {
            $deviceMakeModel = DeviceMakeModel::where('id', $id)->delete();
            return Redirect('/device-make-model')
                ->with('success', 'Device Make Model Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect('/device-make-model')->with('error', 'Something Went Wrong');
        }
    }

    public function deviceMakeModelExport(Request $request){
        try{
            return Excel::download(new DeviceMakeModelExport($request->all()), 'DeviceMakeModelExport.xlsx');
        }catch(Exception $e){

        }
    }
    //Adding controller methods for Vehicle Add/Edit
    //Dont mind namespaces, its duped from Device Make Model Class
    public function vehicleMakeModel()
    {
        $vehicleMakeModelType = VehicleMake::groupBy('s_VehicleType')
            ->where('s_VehicleType','!=','')
            ->whereNotNull('s_VehicleType')
            ->get(array('s_VehicleType'));
        return view('admin.vehicleMakeAndModel.index',compact('vehicleMakeModelType'));
    }

    public function vehicleMakeModelData(Request $request)
    {
        $deviceMakeModel = VehicleMake::whereNotNull('id')
            ->where('s_MMCode','<>','')
            ->whereNotNull('s_Make')
            ->whereNotNull('s_VehicleType')
            ->orderBy('id', 'DESC');
        if (request('vehicleTypeFilter') != '-1' )
        {
            $deviceMakeModel->where('s_VehicleType' , 'like', '%' . $request->vehicleTypeFilter . '%' );
        }

        return DataTables::eloquent($deviceMakeModel)
            ->editColumn('id', function ($deviceMakeModel) {
                $id = 'NA';
                if($deviceMakeModel->id !=null){
                    $id = $deviceMakeModel->id;
                }
                return $id;
            })
            ->editColumn('s_MMCode', function ($deviceMakeModel) {
                $make_id = 'NA';
                if($deviceMakeModel->s_MMCode == null && $deviceMakeModel->s_MMCode != 0){

                    if($deviceMakeModel->s_Make){
                        $s_MMCode = $deviceMakeModel->s_Make;
                    }else{
                        $s_MMCode = 'NA';
                    }

                }
                else{
                    $device = VehicleMake::where('id',$deviceMakeModel->s_MMCode)->first(array('s_Make'));
                    if($device){
                        $s_MMCode = $device->s_Make;
                    }else{
                        $s_MMCode = 'NA';
                    }
                }
                return $s_MMCode;
            })
            ->editColumn('s_Make', function ($deviceMakeModel) {
                $s_Make = 'NA';
                if($deviceMakeModel->id !=null && $deviceMakeModel->s_MMCode != null){
                    $s_Make = $deviceMakeModel->s_Make;
                }else{
                    $s_Make = 'NA';
                }
                return $s_Make;
            })
            ->editColumn('s_VehicleType', function ($deviceMakeModel) {
                $deviceType = 'NA';
                if($deviceMakeModel->id !=null){
                    $s_VehicleType = $deviceMakeModel->s_VehicleType;
                }
                return $s_VehicleType;

            })->addColumn('actions', function ($deviceMakeModel)
            {
                $actions = '';
                if($deviceMakeModel->id !=null)
                {
                    $actions .= '<a href="' . url('vehicle-make-model/edit', $deviceMakeModel->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if($deviceMakeModel->id !=null)
                {
                    $actions .= '<a href="" value="'.$deviceMakeModel->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['id','actions'])
            ->make(true);
    }
    public function vehicleMakeModelCreate()
    {
        $deviceMake = VehicleMake::groupBy('s_Make')
            // ->whereNotNull('s_MMCode')
            // ->orWhere('s_MMCode','')
            ->get(array('id','s_Make'));
        $deviceModel = VehicleMake::whereNotNull('s_MMCode')
            ->orWhere('s_MMCode','!=','')
            ->get(array('id','s_Make'));
        $deviceMakeModelType = VehicleMake::groupBy('s_VehicleType')
            ->where('s_VehicleType','!=','')
            ->whereNotNull('s_VehicleType')
            ->get(array('s_VehicleType'));

        return view('admin.vehicleMakeAndModel.create',compact('deviceMake','deviceModel','deviceMakeModelType'));
    }

    public function addNewVehicleMake()
    {
        return view('admin.vehicleMakeAndModel.add_new_device_make');
    }

    public function vehicleMakeModelAddNewVehicleStore(Request $request)
    {
        try{
            $deviceMakeModel = VehicleMake::get(array('s_Make'));
            if(isset($request->s_Make) && $request->s_Make != NULL)
            {
                foreach($deviceMakeModel as $device)
                {
                    if(ucwords(strtolower($request->s_Make)) == ucwords(strtolower($device->s_Make)))
                    {
                        if(ucwords(strtolower($request->s_VehicleType)) == ucwords(strtolower($device->s_VehicleType)))
                        {
                            return Redirect::back()->with('error', $request->name.' Vehicle make already exist for vehicle type '.$device->s_VehicleType);
                        }
                    }
                }
                $data = new VehicleMake();
                $data->s_Make = htmlspecialchars(strip_tags($request->s_Make));
                $data->s_VehicleType = htmlspecialchars(strip_tags($request->s_VehicleType));
                $data->s_MMCode = htmlspecialchars(strip_tags($request->s_Make));
                $data->s_Variant = htmlspecialchars(strip_tags($request->s_Variant));
                $data->timestamps = false;
                $data->save();

                return Redirect('/vehicle-make-model')->with('success', 'Vehicle make added successfully');
            }
        }catch(Exception $e){
            return Redirect::back()->with('error', 'something went wrong');
        }
    }
    public function vehicleMakeModelStore(Request $request)
    {
        try{
            $deviceMakeModel = VehicleMake::get(array('s_Make'));
            foreach($deviceMakeModel as $device)
            {
                if(ucwords(strtolower($request->s_Make)) == ucwords(strtolower($device->s_Make)))
                {
                    return Redirect::back()->with('error', $request->s_Make.' vehicle make already exist');
                }
            }

            $data = new VehicleMake();
            $data->s_Make = htmlspecialchars(strip_tags($request->s_Make));
            $data->s_VehicleType = htmlspecialchars(strip_tags($request->s_VehicleType));
            $data->timestamps = false;
            $data->save();

            return Redirect::back()->with('success','Vehicle make added successfully');

        }catch(Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }
    public function vehicleMakeModelEdit(Request $request, $id)
    {
        $vehicleMakeModelEdit = VehicleMake::where('id', $id)->first();
        $vehicleMakeModel = VehicleMake::groupBy('s_Make')->get(['id','s_Make']);
        return view('admin.vehicleMakeAndModel.edit',compact('vehicleMakeModelEdit','vehicleMakeModel'));
    }

    public function vehicleMakeModelUpdate(Request $request ,$id)
    {
        unset($request->_token,$request->_method);
        foreach ($request->all() as $key=>$info){
            if($info == null)
                return Redirect::back()->with('error', $key.' is required');
        }
        $check = VehicleMake::where('s_MMCode',$request->s_MMCode)->where('s_VehicleType',$request->s_VehicleType)->where('s_Make',$request->s_Make)->exists();

        if($check == true)
            return Redirect::back()->with('error', $request->s_Make .' model already exist for selected make and type '.$request->s_VehicleType);

        $data = VehicleMake::where('id', $id)->first();
        $data->s_MMCode =  htmlspecialchars(strip_tags($request->s_MMCode ? $request->s_MMCode:""));
        $data->s_Make = htmlspecialchars(strip_tags($request->s_Make));
        $data->s_VehicleType = htmlspecialchars(strip_tags($request->s_VehicleType));
        $data->save();
        return Redirect('/vehicle-make-model');
    }

    public function getVehicleMakeModelDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the vehicle Make Model ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }

    public function destroyVehicleMakeModel($id)
    {
        try
        {
            $deviceMakeModel = VehicleMake::where('id', $id)->delete();
            return Redirect('/vehicle-make-model')
                ->with('success', 'Vehicle Make Model Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect('/vehicle-make-model')->with('error', 'Something Went Wrong');
        }
    }

    public function vehicleMakeModelExport(Request $request){
        try{
            return Excel::download(new VehicleMakeModelExport($request->all()), 'VehicleMakeModelExport.xlsx');
        }catch(Exception $e){

        }
    }


    public function getPaymentInfo($policyId){
        try{
            $data =  Policy::join('transactions', 'transactions.policyNumber', 'policies.policyNumber')
                ->join('payment_transactions', 'payment_transactions.policyNumber', 'policies.policyNumber')
                ->orderBy('policies.id','DESC')
                ->first(array());
        }catch(\Exception $e){
            return $e->getMessage();
        }
    }

    public function getStates(){
        $states = State::where('country_id',28)->get(array('id','name'));
        return $states;
    }

    public function getCities(Request $request){
        $cities = City::where('state_id',$request->state_id)->get(array('id','name'));
        return response()->json(['status' => 'success', 'Cities' => $cities]);
    }

    public function setApkVersion(Request $request){
        $config = Config::where('key','apk_version')->orderBy('id','DESC')->first(array('value'));
        return view('set_apk_version');
    }

    public function updateApkVersion(Request $request){
        try{
            $config = Config::where('key','applicationVersion')->first(array('id','key','value'));
            if($config == null) {
                $config = new Config();
                $config->key = 'applicationVersion';
            }
            $config->value = $request->version;
            $config->save();

            return Redirect::back()->with('success','Version number updated successfully');

        }catch(\Exception $e){
            return Redirect::back()->with('error',$e->getMessage().' '.$e->getLine());
        }
    }

    public function getAPkVersionNumber(){
        $config = Config::where('key','applicationVersion')->orderBy('id','DESC')->first(array('key','value'));
        if($config == null)
            return response()->json(['status' => 'failed', 'version' => null]);
        else
            return response()->json(['status' => 'success',  $config->key=> $config->value]);
    }

    public function addBenefitType(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'name' => 'required|string',
        ]);

        $config = Config::where('key', 'benefit_types')->first();
        $types = $config ? json_decode($config->value, true) : [];

        // Prevent duplicate code
        foreach ($types as $type) {
            if ($type['code'] === $request->code) {
                return response()->json(['success' => false, 'message' => 'Type code already exists.']);
            }
        }

        $types[] = [
            'code' => $request->code,
            'name' => $request->name,
        ];

        if (!$config) {
            $config = new Config();
            $config->key = 'benefit_types';
        }
        $config->value = json_encode($types);
        $config->save();

        return response()->json(['success' => true]);
    }

}
