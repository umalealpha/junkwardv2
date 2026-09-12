<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Repositories\Customer\CustomerInterface;
use AlphaDirect\Repositories\CustomerBanking\CustomerBankingInterface;
use AlphaDirect\Repositories\CustomerKyc\CustomerKycInterface;
use AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface;
use Illuminate\Http\Request;
use Intervention\Image\ImageManagerStatic as Image;
use AlphaDirect\KYC;
use AlphaDirect\Vehicle;
use AlphaDirect\GlassClaim;
use AlphaDirect\IncidentPhoto;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\AgentKyc;
use AlphaDirect\AgentPreInsepection;
use AlphaDirect\CustomerProfile;
use Illuminate\Support\Facades\Storage;
use Aws\CloudFront;
use App;
use DB;
use Auth;
use Session;
use File;
use Carbon\Carbon;
use Validator;

class UploadController extends Controller
{
    protected $customer_kyc_interface;

    public function __construct(CustomerKycInterface $customer_kyc_interface) {
        $this->customer_kyc_interface = $customer_kyc_interface;
    }

    public function testcloudfront()
    {
        $cloudFront = App::make('aws')->createClient('CloudFront');
        $expires = time() + 300;
        $resourceKey = 'https://d20dgglp0tqnyi.cloudfront.net/Policy/Realpay/Failed/111/realpay_failed_trans.pdf';
        $options = array (
            'url'         => $resourceKey,
            //'url' => 'https://d20dgglp0tqnyi.cloudfront.net/E3PJYXBRPUSR65/Document/Policy_Document//General_Exceptions_Conditions_%26_Provisions.pdf',
            'expires'=>$expires,
            'key_pair_id'=> 'E3PJYXBRPUSR65',
            'private_key'=> public_path('aws/private_key_CF.pem')
        );
        $signedUrl = $cloudFront->getSignedUrl($options);
        //dd($signedUrl);
    }


    public function uploadOmang(Request $request)
    {

        try {

            $userDetails  = User::where('id', $request->id)->first();

            if ($request->hasFile('omang')) {

                $file = $request->file('omang');

                $name = $file->getClientOriginalName();

                $filePath = 'MIS/' . $userDetails->id . '/' . 'KYC' . '/' . $userDetails->firstName . '/Omang' . '/' . $name;

                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');


                $omangKYC =  KYC::where('user_id', $request->id)->first();
                $omangKYC->omang = $filePath;
                $omangKYC->save();

                $userVehicle =  Vehicle::where('vehiclePlate', $request->vehiclePlate)->first();


                if ($omangKYC->omang != 'Not Submitted' && $omangKYC->driving_license != 'Not Submitted'  && $omangKYC->proofResidence != 'Not Submitted' && $omangKYC->proofIncome  && $userVehicle->vehicleRegistration != null) {
                    $omangKYC->save();
                    return response()->json(['status' => 400]);
                }
            }

            Session::flash('omang', 'KYC-DOC: Omang has been uploaded successfully');

            return back();
        } catch (Exception $ex) {

            return response()->json(['error' => $ex->getMessage()]);
        }
    }

    public function uploadProofOfResidence(Request $request)
    {

        $userDetails  = User::where('id', $request->id)->first();


        if ($request->hasFile('residence')) {

            $file = $request->file('residence');

            $name = $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'KYC' . '/' . $userDetails->firstName . '/P.O.R' . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');


            $userKYC =  KYC::where('user_id', $request->id)->first();
            $userKYC->proofResidence = $filePath;
            $userKYC->save();
        }

        Session::flash('residence', 'KYC-DOC: Proof of residence has been uploaded successfully');

        return back();
    }

    public function uploadProofOfIncome(Request $request)
    {

        $userDetails  = User::where('id', $request->id)->first();



        if ($request->hasFile('income')) {

            $file = $request->file('income');

            $name = $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'KYC' . '/' . $userDetails->firstName . '/P.O.I' . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');


            $userKYC =  KYC::where('user_id', $request->id)->first();
            $userKYC->proofIncome = $filePath;
            $userKYC->save();
        }

        Session::flash('income', 'KYC-DOC: Proof of income has been uploaded successfully');

        return back();
    }

    public function uploadDriversLicense(Request $request)
    {

        $userDetails  = User::where('id', $request->id)->first();


        if ($request->hasFile('driversLicense')) {

            $file = $request->file('driversLicense');

            $name = $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'KYC' . '/' . $userDetails->firstName . '/DriversLicense' . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');


            $userKYC =  KYC::where('user_id', $request->id)->first();
            $userKYC->driving_license = $filePath;
            $userKYC->save();
        }

        Session::flash('driversLicense', 'KYC-DOC: Drivers License has been uploaded successfully');

        return back();
    }

    public function uploadBluebook(Request $request)
    {
        $userDetails  = User::where('id', $request->id)->first();

        if ($request->hasFile('bluebook')) {

            $file = $request->file('bluebook');

            $name = $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'KYC' . '/' . $userDetails->firstName . '/bluebook' . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');


            $userVehicle =  Vehicle::where('vehiclePlate', $request->vehiclePlate)->first();
            $userVehicle->vehicleRegistration = $filePath;
            $userVehicle->save();

            $userKYC =  KYC::where('user_id', $request->id)->first();

            Session::flash('bluebook', 'KYC-DOC: Vehicle Registration has been uploaded successfully');
        }
        return back();
    }

    public function uploadFrontImage(Request $request)
    {


        $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();


        $userDetails  = User::where('id', $vehicleDetails->user_id)->first();


        if ($request->hasFile('front')) {

            $file = $request->file('front');

            $name = $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();
            $vehicleDetails->front = $filePath;
            $vehicleDetails->save();
        }

        Session::flash('frontUploaded', 'Front image of vehicle uploaded');

        return back();
        //return back();
    }

    public function uploadBackImage(Request $request)
    {

        $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();

        $userDetails  = User::where('id', $vehicleDetails->user_id)->first();


        if ($request->hasFile('back')) {

            $file = $request->file('back');

            $name = $file->getClientOriginalName();


            $filePath = 'MIS/' . $userDetails->id . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $name;


            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();
            $vehicleDetails->back = $filePath;
            $vehicleDetails->save();
        }

        Session::flash('backUploaded', 'Back image of vehicle uploaded');

        return back();
    }

    public function uploadLeftImage(Request $request)
    {


        $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();

        $userDetails  = User::where('id', $vehicleDetails->user_id)->first();


        if ($request->hasFile('left')) {

            $file = $request->file('left');

            $name = $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();
            $vehicleDetails->left = $filePath;
            $vehicleDetails->save();
        }

        Session::flash('leftUploaded', 'Left image of vehicle uploaded');

        return back();
    }

    public function uploadRightImage(Request $request)
    {

        $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();

        $userDetails  = User::where('id', $vehicleDetails->user_id)->first();

        if ($request->hasFile('right')) {

            $file = $request->file('right');

            $name = $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $vehicleDetails =  Vehicle::where('policy_id', $request->id)->first();
            $vehicleDetails->right = $filePath;
            $vehicleDetails->save();
        }

        Session::flash('rightUploaded', 'Right image of vehicle uploaded');

        return back();
    }

    public function appUploadLeftImage(Request $request)
    {


        DB::table('vehicle')
            ->where('policy_id', $request->policyId)
            ->update(['left' => $request->left]);
    }


    public function uploadCarImages(Request $request)
    {

        //Images from app side,(one by one uploading)
        $frontImage = $request->front;
        $backImage = $request->back;
        $rightImage = $request->right;
        $leftImage = $request->left;


        if ($frontImage != null) {


            $vehicleDetails =  Vehicle::where('policy_id', $request->policyId)->first();
            $policyDetails = Policy::where('id', $request->policyId)->first();

            $userDetails  = User::where('id', $vehicleDetails->user_id)->first();


            $file = 'data:image/jpeg;base64,' . $request->front;

            $name = $vehicleDetails->vehiclePlate . 'Front';

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));
            $filePath = 'MIS/' . $userDetails->id . './' . $policyDetails->policyNumber . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $imageName;


            Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');

            DB::table('vehicle')
                ->where('policy_id', $request->policyId)
                ->update(['front' => $filePath]);

            if (File::exists($imageName)) {
                File::delete($imageName);
            }

            return response()->json($filePath);
        }

        if ($backImage != null) {

            $vehicleDetails =  Vehicle::where('policy_id', $request->policyId)->first();

            $userDetails  = User::where('id', $vehicleDetails->user_id)->first();


            $file = 'data:image/jpeg;base64,' . $request->back;

            $name = $vehicleDetails->vehiclePlate . 'Back';

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $imageName;


            Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');

            DB::table('vehicle')
                ->where('policy_id', $request->policyId)
                ->update(['back' => $filePath]);


            if (File::exists($imageName)) {
                File::delete($imageName);
            }
            return response()->json($filePath);
        }

        if ($rightImage != null) {

            $vehicleDetails =  Vehicle::where('policy_id', $request->policyId)->first();

            $userDetails  = User::where('id', $vehicleDetails->user_id)->first();


            $file = 'data:image/jpeg;base64,' . $request->right;

            $name = $vehicleDetails->vehiclePlate . 'Right';

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));
            $filePath = 'MIS/' . $userDetails->id . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $imageName;


            Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');

            DB::table('vehicle')
                ->where('policy_id', $request->policyId)
                ->update(['right' => $filePath]);

            if (File::exists($imageName)) {
                File::delete($imageName);
            }

            return response()->json($filePath);
        }


        if ($leftImage != null) {

            $vehicleDetails =  Vehicle::where('policy_id', $request->policyId)->first();

            $userDetails  = User::where('id', $vehicleDetails->user_id)->first();


            $file = 'data:image/jpeg;base64,' . $request->left;

            $name = $vehicleDetails->vehiclePlate . 'Left';

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));
            $filePath = 'MIS/' . $userDetails->id . '/' . 'Vehicle' . '/' . $vehicleDetails->vehiclePlate . '/' . $imageName;


            Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');

            DB::table('vehicle')
                ->where('policy_id', $request->policyId)
                ->update(['left' => $filePath]);

            if (File::exists($imageName)) {
                File::delete($imageName);
            }

            return response()->json($filePath);
        }
    }

    public function generateFileName($name, $value)
    {
        $imgExt = 'jpeg';
        $image = str_replace(' ', '+', $value);
        $imageName = $name . "." . $imgExt;

        file_put_contents($imageName, base64_decode($image));

        return $imageName;
    }

    public function agentAppUploadKyc(Request $request)
    {
        $name = $request->omangId != null ?  $request->omangId : $request->passportId;

        $passportNumber = $request->passportId;
        $omangNumber = $request->omangId;

        $AgentKyc = new AgentKyc();
        $AgentKyc->omangNumber = $request->omangId;
        $AgentKyc->agentId = $request->agentId;
        $AgentKyc->passportNumber = $request->passportId;
        $AgentKyc->passportIssuingCountry = isset($request->passportIssuingCountry) && $request->passportIssuingCountry != "" ? $request->passportIssuingCountry : "";
        $AgentKyc->proof_residence_doc_type = isset($request->proof_residence_doc_type) && $request->proof_residence_doc_type != "" ? $request->proof_residence_doc_type : "";
        $AgentKyc->proof_income_doc_type = isset($request->proof_income_doc_type) && $request->proof_income_doc_type != "" ? $request->proof_income_doc_type : "";
        $AgentKyc->omangExpiry = isset($request->omangExpiry) && $request->omangExpiry != "" ? $request->omangExpiry : "";
        $AgentKyc->passportExpiry = isset($request->passportExpiry) && $request->passportExpiry != "" ? $request->passportExpiry : "";
        $AgentKyc->licenseExpiry = isset($request->licenseExpiry) && $request->licenseExpiry != "" ? $request->licenseExpiry : "";



        $customer = CustomerProfile::where('omang', $name)->orWhere('passport', $name)->first();

        $kyc = KYC::where('customer_id', $customer->customer_id)->first();

        $attributes = [
            'omangNumber'   => $AgentKyc->omangNumber,
            'passportNumber'   => $AgentKyc->passportNumber,
            'passportIssuingCountry'   => $AgentKyc->passportIssuingCountry,
            'proof_residence_doc_type'   => $AgentKyc->proof_residence_doc_type,
            'proof_income_doc_type'   => $AgentKyc->proof_income_doc_type,
            'omangExpiry'   => $AgentKyc->omangExpiry,
            'passportExpiry'   => $AgentKyc->passportExpiry,
            'licenseExpiry'   => $AgentKyc->licenseExpiry,
        ];


        foreach ($request->kyc as $key => $value) {
            switch ($key) {
                case 'omang':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                    $AgentKyc->omang = $path;
                    $attributes['omang'] = $AgentKyc->omang;
                    break;

                case 'omangBack':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                    $AgentKyc->omangBack = $path;
                    $attributes['omangBack'] = $AgentKyc->omangBack;
                    break;

                case 'proofResidence':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                    $AgentKyc->proofResidence = $path;
                    $attributes['proof_residence'] = $AgentKyc->proofResidence;
                    break;


                case 'driversLicense':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                    $AgentKyc->driversLicense = $path;
                    $attributes['driving_license'] = $AgentKyc->driversLicense;
                    break;


                case 'proofIncome':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                    $AgentKyc->proofIncome = $path;
                    $attributes['proof_income'] =  $AgentKyc->proofIncome;
                    break;


                case 'passport':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->saveKycPath($key, $imageName, $omangNumber, $passportNumber);
                    $AgentKyc->passport = $path;
                    $attributes['passport'] = $AgentKyc->passport;
                    break;


                case 'default':
                    # code...
                    break;
            }
        }

            $AgentKyc->compliance = 0;
            $attributes['compliance']= 0;

        $saveStatus = $AgentKyc->save();

        if ($customer != null) {

            if ($kyc != NULL){
                $this->customer_kyc_interface->update_customer_kyc_by_id($customer->customer_id, $attributes);
            }
            else{
                $this->customer_kyc_interface->add_new_customer_kyc($attributes);
            }
        }
        if ($saveStatus == true)
            return response()->json(['title' => 'Successful', 'description' => 'Customer KYC photos have been successfully uploaded'], 200);
        else
            return response()->json(['title' => 'Failed', 'description' => 'Customer KYC photos have not been uploaded'], 401);
    }

    public function uploadCustomerKycDoc(Request $request)
    {
        $customer = Customer::where('id', 1)->first();
        $customerKyc = new KYC();
        $customerKyc->omang_id = $request->omangId;
        $customerKyc->passport_id = $request->passportId;
        $customerKyc->customer_id = $request->customer_id;
        $customerKyc->passportIssuingCountry = $request->passportIssuingCountry;
        $customerKyc->proof_residence_doc_type = $request->proof_residence_doc_type;
        $customerKyc->proof_income_doc_type = $request->proof_income_doc_type;
        $customerKyc->omangExpiry = $request->omangExpiry;
        $customerKyc->passportExpiry = $request->passportExpiry;
        $customerKyc->licenseExpiry = $request->licenseExpiry;

        if ($request->hasFile('driving')) {
            $file = $request->file('driving');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/driving_license' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $customerKyc->driving_license = $filePath;
        }
        if ($request->hasFile('omangBack')) {
            $file = $request->file('omangBack');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/omangBack' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $customerKyc->omangBack = $filePath;
        }
        if ($request->hasFile('omang')) {
            $file = $request->file('omang');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/Omang' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $customerKyc->omang = $filePath;
        }
        if ($request->hasFile('proofResidence')) {
            $file = $request->file('proofResidence');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_residence' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $customerKyc->proof_residence = $filePath;
        }
        if ($request->hasFile('proofIncome')) {
            $file = $request->file('proofIncome');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_income' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $customerKyc->proof_income = $filePath;
        }
        if ($request->hasFile('passport')) {
            $file = $request->file('passport');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/passport' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $customerKyc->passport = $filePath;
        }

        $customerKyc->save();
        $saveStatus = $customerKyc->save();
        if ($saveStatus == true)
            return response()->json(['title' => 'Successful', 'description' => 'Customer KYC photos have been successfully uploaded'], 200);
        else
            return response()->json(['title' => 'Failed', 'description' => 'Customer KYC photos have not been uploaded--1'], 401);
    }

    public function customerUploadKyc(Request $request)
    {
        $customer = Customer::where('id', 1)->first();
        $customerKyc = new KYC();
        $customerKyc->omang_id = $request->omangId;
        $customerKyc->passport_id = $request->passportId;
        $customerKyc->customer_id = $request->customer_id;
        $customerKyc->passportIssuingCountry = $request->passportIssuingCountry;
        $customerKyc->proof_residence_doc_type = $request->proof_residence_doc_type;
        $customerKyc->proof_income_doc_type = $request->proof_income_doc_type;
        $customerKyc->omangExpiry = $request->omangExpiry;
        $customerKyc->passportExpiry = $request->passportExpiry;
        $customerKyc->licenseExpiry = $request->licenseExpiry;
        foreach ($request->kyc as $key => $value) {

            switch ($key) {
                case 'omang':

                    if ($request->hasFile('omang')) {
                        $file = $request->file('omang');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/omang' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $customerKyc->omang = $filePath;
                    }
                    break;

                case 'omangBack':
                    $file = $request->file('omangBack');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/omangBack' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKyc->omangBack = $filePath;
                    break;

                case 'proof_residence':
                    $file = $request->file('proof_residence');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_residence' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKyc->proof_residence = $filePath;

                    break;


                case 'driversLicense':
                    $file = $request->file('driversLicense');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/driving_license' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKyc->driving_license = $filePath;

                    break;


                case 'proof_income':
                    $file = $request->file('proof_income');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_income' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKyc->proof_income = $filePath;

                    break;


                case 'passport':
                    $file = $request->file('passport');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/passport' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKyc->passport = $filePath;

                    break;


                case 'default':
                    # code...
                    break;
            }
        }

        $saveStatus = $customerKyc->save();
        if ($saveStatus == true)
            return response()->json(['title' => 'Successful', 'description' => 'Customer KYC photos have been successfully uploaded'], 200);
        else
            return response()->json(['title' => 'Failed', 'description' => 'Customer KYC photos have not been uploaded--1'], 401);
        /*$name = $request->omangId != null ?  $request->omangId : $request->passportId ;
        $customer = CustomerProfile::where('omang',$name)->orWhere('passport',$name)->first();
        $passportNumber = $request->passportId;
        $omangNumber = $request->omangId;*/

        /*if ($customer != null){
            $check = KYC::where('customer_id', $customer->customer_id)->first();
            if($check != null){
                $customerKyc = KYC::where('customer_id', $check->customer_id)->first();
            } else{
                $customerKyc = new KYC();
            }
            $customerKyc->omangNumber = $request->omangId;
            $customerKyc->passportNumber = $request->passportId;
            $customerKyc->customer_id = $request->customer_id;
            $customerKyc->passportIssuingCountry = $request->passportIssuingCountry;
            $customerKyc->proof_residence_doc_type = $request->proof_residence_doc_type;
            $customerKyc->proof_income_doc_type = $request->proof_income_doc_type;
            $customerKyc->omangExpiry = $request->omangExpiry;
            $customerKyc->passportExpiry = $request->passportExpiry;
            $customerKyc->licenseExpiry = $request->licenseExpiry;
            foreach($request->kyc as $key=>$value){
                switch($key){
                    case 'omang':
                        if ($request->hasFile('omang'))
                        {
                            $file = $request->file('omang');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/omang' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $customerKyc->omang = $filePath;
                        }
                        break;
                    case 'omangBack':
                        $file = $request->file('omangBack');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/omangBack' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $customerKyc->omangBack = $filePath;
                        break;
                    case 'proof_residence':
                        $file = $request->file('proof_residence');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_residence' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $customerKyc->proof_residence = $filePath;
                        break;
                    case 'driversLicense':
                        $file = $request->file('driversLicense');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/driving_license' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $customerKyc->driving_license = $filePath;
                        break;
                    case 'proof_income':
                        $file = $request->file('proof_income');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_income' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $customerKyc->proof_income = $filePath;
                        break;
                    case 'passport':
                        $file = $request->file('passport');
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/passport' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $customerKyc->passport = $filePath;
                        break;
                    case 'default':
                        # code...
                        break;
                }
            }
            $saveStatus = $customerKyc->save();
            if($saveStatus == true )
                return response()->json(['title'=>'Successful', 'description'=>'Customer KYC photos have been successfully uploaded'],200);
            else
                return response()->json(['title'=>'Failed', 'description'=>'Customer KYC photos have not been uploaded--1'],401);
        }else{
            return response()->json(['title'=>'Failed', 'description'=>'Customer KYC photos have not been uploaded--2'],401);
        }*/
    }


    public function agentAppUploadPreInspection(Request $request)
    {
        $vehiclePlate = $name = $request->vehiclePlate;
        $AgentPreInsepection = new AgentPreInsepection();
        $AgentPreInsepection->vehiclePlate = $request->vehiclePlate;
        $AgentPreInsepection->agentId = $request->agentId;

        $vehicle = Vehicle::where('vehiclePlate', $request->vehiclePlate)->first();

        foreach ($request->images as $key => $value) {
            switch ($key) {
                case 'front':

                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->savePreInspectionPath($key, $imageName, $vehiclePlate);
                    $AgentPreInsepection->front = $path;
                    $vehicle->front = $AgentPreInsepection->front;
                    break;

                case 'left':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->savePreInspectionPath($key, $imageName, $vehiclePlate);
                    $AgentPreInsepection->left = $path;
                    $vehicle->left = $AgentPreInsepection->left;
                    break;


                case 'back':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->savePreInspectionPath($key, $imageName, $vehiclePlate);
                    $AgentPreInsepection->back = $path;
                    $vehicle->back = $AgentPreInsepection->back;
                    break;

                case 'right':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->savePreInspectionPath($key, $imageName, $vehiclePlate);
                    $AgentPreInsepection->right = $path;
                    $vehicle->right = $AgentPreInsepection->right;
                    break;

                case 'vehicleRegistration':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->savePreInspectionPath($key, $imageName, $vehiclePlate);
//                    $AgentPreInsepection->vehicleRegistration = $path;
                    $vehicle->vehicleRegistration = $path;
                    break;

                case 'vehicleInvoice':
                    $imageName = $this->generateFileName($name, $value);
                    $path = $this->savePreInspectionPath($key, $imageName, $vehiclePlate);
                    $vehicle->vehicle_valuation = $path;
                    break;


                case 'default':
                    # code...
                    break;
            }
        }

        $saveStatus =  $AgentPreInsepection->save();

        if ($vehicle != null) {
            $vehicle->vehiclePlate = $request->vehiclePlate;
            $vehicle->save();
        }
        if ($vehicle != null && $vehicle->status == 2) {
            $vehicle->status = 3;
            $vehicle->save();
        }

        if ($saveStatus == true) {
            $customerDetail = Vehicle::where('vehiclePlate', $request->vehiclePlate)->first(['customer_id']);
            if ($customerDetail) {
                $customer = DB::select('call getCustomerCelllphone(?)', [$customerDetail->customer_id]);
                $cellPhone = $customer[0]->cellphone;
                if ($cellPhone) {
                    $smsMessaging = new SmsMessaging;
                    $smsMessaging->sendVehicleInspectionSMS(5, $cellPhone);
                }
            }
            return response()->json(['title' => 'Successful', 'description' => 'Preinspection photos have been successfully uploaded'], 200);
        } else {

            return response()->json(['title' => 'Failed', 'description' => 'Preinspection photos have been successfully failed'], 401);
        }
    }


    public function uploadKycImages(Request $request, $uploadType = null)
    {

        //Images from app side,(one by one uploading)

        $omangImage = $request->omang;
        $licenseImage = $request->license;
        $proofResidence = $request->residence;
        $proofIncome = $request->income;
        $bluebook = $request->vehicleRegistration;

        $userDetails  = Customer::where('id', $request->userId)->first();


        if (!$userDetails) {

            return response()->json('User not found', 401);
        }


        if ($omangImage != null) {

            try {
                //code...
                $file = 'data:image/jpeg;base64,' . $request->omang;

                $name = $userDetails->firstName . 'Omang';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $type = 'omang';
                $path = $this->saveKycPath($type, $request->userId, $imageName);
                $filePath = $path;

                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                DB::table('customer_kyc')
                    ->where('customer_id', $request->userId)
                    ->update(['omang' => $filePath]);
                if (File::exists($imageName)) {
                    File::delete($imageName);
                }
                return response()->json(['filePath' => $filePath], 200);
            } catch (\Exception $ex) {
                //throw $th;
                return response()->json(['error' => $ex->getMessage()], 500);
            }
        }

        if ($licenseImage != null) {

            try {
                //code...

                $file = 'data:image/jpeg;base64,' . $request->license;

                $name = $userDetails->firstName . 'License';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $type = 'license';
                $path = $this->saveKycPath($type, $request->userId, $imageName);
                $filePath = $path;

                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');

                DB::table('customer_kyc')
                    ->where('customer_id', $request->userId)
                    ->update(['driving_license' => $filePath]);

                if (File::exists($imageName)) {
                    File::delete($imageName);
                }

                return response()->json(['filePath' => $filePath], 200);
            } catch (\Exception $ex) {
                //throw $th;
                return response()->json(['error' => $ex->getMessage()], 500);
            }
        }

        if ($proofResidence != null) {

            try {
                //code...

                $file = 'data:image/jpeg;base64,' . $request->residence;

                $name = $userDetails->firstName . '-P.O.R';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $type = 'proofResidence';

                $path = $this->saveKycPath($type, $request->userId, $imageName);
                $filePath = $path;


                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');

                $userKYC =  KYC::where('customer_id', $request->userId)->first();
                $userKYC->proof_residence = $filePath;
                $userKYC->save();
                if (File::exists($imageName)) {
                    File::delete($imageName);
                }

                return response()->json(['filePath' => $filePath], 200);
            } catch (\Exception $ex) {
                //throw $th;
                return response()->json(['error' => $ex->getMessage()], 500);
            }
        }

        if ($proofIncome != null) {

            try {
                //code...
                $file = 'data:image/jpeg;base64,' . $request->income;

                $name = $userDetails->firstName . 's' . ' ' . 'Income Statement';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $type = 'proofIncome';

                $path = $this->saveKycPath($type, $request->userId, $imageName);
                $filePath = $path;


                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');

                $userKYC =  KYC::where('customer_id', $request->userId)->first();
                $userKYC->proof_income = $filePath;
                $userKYC->save();

                if (File::exists($imageName)) {
                    File::delete($imageName);
                }

                return response()->json(['filePath' => $filePath], 200);
            } catch (\Exception $ex) {
                //throw $th;
                return response()->json(['error' => $ex->getMessage()], 500);
            }
        }

        if ($bluebook != null) {

            try {
                $file = 'data:image/jpeg;base64,' . $request->vehicleRegistration;

                $name = $userDetails->firstName . 's' . ' ' . 'Vehicle Registration';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $filePath = 'MIS/' . $userDetails->id . '/' . 'Bluebook' . '/' . $userDetails->firstName . '/' . $imageName;

                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');


                $vehicle = Vehicle::where('policy_id', $request->policyId)->first();
                $vehicle->vehicleRegistration = $filePath;
                $vehicle->save();

                if (File::exists($imageName)) {
                    File::delete($imageName);
                }
            } catch (Exception $ex) {
                return response()->json($ex->getMessage());
            }
            return response()->json($filePath);
        }

        // BizSure commercial KYC docs: company_reg, company_extract, bors, tin
        $bizCommercialMap = [
            'company_reg'     => ['folder' => 'CompanyReg',     'col' => 'company_reg'],
            'company_extract' => ['folder' => 'CompanyExtract', 'col' => 'company_extract'],
            'bors'            => ['folder' => 'BORS',           'col' => 'bors'],
            'tin'             => ['folder' => 'TIN',            'col' => 'tin'],
        ];

        foreach ($bizCommercialMap as $field => $meta) {
            if ($request->$field != null) {
                try {
                    $raw = $request->$field;
                    $imageInfo = explode(";base64,", 'data:image/jpeg;base64,' . $raw);
                    $imgExt    = str_replace('data:image/', '', $imageInfo[0]);
                    $image     = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $userDetails->firstName . $meta['folder'] . '.' . $imgExt;

                    // Write the decoded image to a system temp file (NOT the
                    // CWD under an unsanitised firstName) before streaming to S3.
                    $tmpPath = tempnam(sys_get_temp_dir(), 'bzs_kyc_');
                    file_put_contents($tmpPath, base64_decode($image));

                    // NOTE: 'public' matches the rest of this controller's KYC
                    // uploads. The world-readable ACL on identity documents is a
                    // known systemic issue tracked separately (move all KYC to
                    // private + signed URLs); not changed here to avoid breaking
                    // the admin doc-viewer that reads these as public URLs.
                    $filePath = 'MIS/' . $userDetails->id . '/' . $meta['folder'] . '/' . $userDetails->firstName . '/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($tmpPath), 'public');

                    DB::table('customer_kyc')
                        ->updateOrInsert(
                            ['customer_id' => $request->userId],
                            [$meta['col'] => $filePath]
                        );

                    if (File::exists($tmpPath)) {
                        File::delete($tmpPath);
                    }
                    return response()->json(['filePath' => $filePath], 200);
                } catch (\Exception $ex) {
                    // Don't echo $ex->getMessage() to the partner — it can carry
                    // file paths / SQL with PII. Log it, return a generic error.
                    \Illuminate\Support\Facades\Log::error('[UploadController] BizSure commercial KYC upload failed', [
                        'field' => $field,
                        'error' => $ex->getMessage(),
                    ]);
                    return response()->json(['error' => 'KYC upload failed'], 500);
                }
            }
        }

        // BizSure director KYC: director_0_id, director_1_id, ... director_N_id
        foreach ($request->all() as $key => $value) {
            if ($value != null && preg_match('/^director_(\d+)_id$/', $key, $m)) {
                try {
                    $directorIndex = (int) $m[1];
                    $imageInfo = explode(";base64,", 'data:image/jpeg;base64,' . $value);
                    $imgExt    = str_replace('data:image/', '', $imageInfo[0]);
                    $image     = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = 'Director' . $directorIndex . '_' . $userDetails->firstName . '.' . $imgExt;

                    // Write to a system temp file, not the CWD (see commercial block).
                    $tmpPath = tempnam(sys_get_temp_dir(), 'bzs_dir_');
                    file_put_contents($tmpPath, base64_decode($image));

                    // 'public' ACL: systemic KYC issue tracked separately (see commercial block).
                    $filePath = 'MIS/' . $userDetails->id . '/Director/' . $directorIndex . '/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($tmpPath), 'public');

                    if ($request->policyId) {
                        DB::table('policy_kyc_documents')->insert([
                            'policy_id'   => $request->policyId,
                            'customer_id' => $userDetails->id,
                            'doc_type'    => 'director_id',
                            'doc_index'   => $directorIndex,
                            'file_path'   => $filePath,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    }

                    if (File::exists($tmpPath)) {
                        File::delete($tmpPath);
                    }
                    return response()->json(['filePath' => $filePath, 'directorIndex' => $directorIndex], 200);
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::error('[UploadController] BizSure director KYC upload failed', [
                        'director_index' => $m[1] ?? null,
                        'error'          => $ex->getMessage(),
                    ]);
                    return response()->json(['error' => 'KYC upload failed'], 500);
                }
            }
        }
    }


    public function uploadGlassClaimIncidentPhoto(Request $request)
    {
        //Incident images from app side,(one by one uploading)
        $frontImage = $request->front;
        $backImage = $request->back;
        $rightImage = $request->right;
        $leftImage = $request->left;

        $glassClaim  = GlassClaim::where('id', $request->claimId)->first();

        if ($frontImage != null) {

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();
            $incidentPhoto->claim_id = $request->claimId;

            $file = 'data:image/jpeg;base64,' . $request->front;

            $name = $this->gen_uuid();

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));

            $filePath = 'MIS/' . $glassClaim->user_id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $imageName;

            Storage::disk('s3')->put($filePath, file_get_contents($imageName));

            $incidentPhoto->front = $filePath;
            $incidentPhoto->save();

            if (File::exists($imageName)) {
                File::delete($imageName);
            }

            return response()->json($filePath);
        }

        if ($backImage != null) {

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();

            $file = 'data:image/jpeg;base64,' . $request->back;

            $name = $this->gen_uuid();

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));

            $filePath = 'MIS/' . $glassClaim->user_id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $imageName;

            Storage::disk('s3')->put($filePath, file_get_contents($imageName));

            $incidentPhoto->back = $filePath;
            $incidentPhoto->save();

            if (File::exists($imageName)) {
                File::delete($imageName);
            }

            return response()->json($filePath);
        }

        if ($leftImage != null) {

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();

            $file = 'data:image/jpeg;base64,' . $request->left;

            $name = $this->gen_uuid();

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));

            $filePath = 'MIS/' . $glassClaim->user_id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $imageName;

            Storage::disk('s3')->put($filePath, file_get_contents($imageName));

            $incidentPhoto->left = $filePath;
            $incidentPhoto->save();

            if (File::exists($imageName)) {
                File::delete($imageName);
            }

            return response()->json($filePath);
        }

        if ($rightImage != null) {

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();

            $file = 'data:image/jpeg;base64,' . $request->right;

            $name = $this->gen_uuid();

            $imageInfo = explode(";base64,", $file);
            $imgExt = str_replace('data:image/', '', $imageInfo[0]);
            $image = str_replace(' ', '+', $imageInfo[1]);
            $imageName = $name . "." . $imgExt;
            file_put_contents($imageName, base64_decode($image));

            $filePath = 'MIS/' . $glassClaim->user_id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $imageName;

            Storage::disk('s3')->put($filePath, file_get_contents($imageName));

            $incidentPhoto->right = $filePath;
            $incidentPhoto->save();

            if (File::exists($imageName)) {
                File::delete($imageName);
            }

            return response()->json($filePath);
        }
    }


    public function uploadIncidentPhotoFront(Request $request)
    {

        $glassClaim  = GlassClaim::where('id', $request->claimId)->first();
        $userDetails  = User::where('id', $glassClaim->user_id)->first();


        if ($request->hasFile('incidentFront')) {

            $file = $request->file('incidentFront');

            $name = $this->gen_uuid() . $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();
            $incidentPhoto->front = $filePath;
            $incidentPhoto->save();
        }

        Session::flash('frontIncident', 'Front Incident uploaded');
        return redirect()->back();
    }

    public function uploadIncidentPhotoBack(Request $request)
    {

        $glassClaim  = GlassClaim::where('id', $request->claimId)->first();
        $userDetails  = User::where('id', $glassClaim->user_id)->first();


        if ($request->hasFile('incidentBack')) {

            $file = $request->file('incidentBack');

            $name = $this->gen_uuid() . $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();
            $incidentPhoto->back = $filePath;
            $incidentPhoto->save();
        }

        Session::flash('backIncident', 'Back Incident uploaded');
        return redirect()->back();
    }

    public function uploadIncidentPhotoRight(Request $request)
    {

        $glassClaim  = GlassClaim::where('id', $request->claimId)->first();
        $userDetails  = User::where('id', $glassClaim->user_id)->first();


        if ($request->hasFile('incidentRight')) {

            $file = $request->file('incidentRight');

            $name = $this->gen_uuid() . $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();
            $incidentPhoto->right = $filePath;
            $incidentPhoto->save();
        }

        Session::flash('rightIncident', 'Back Incident uploaded');
        return redirect()->back();
    }

    public function uploadIncidentPhotoLeft(Request $request)
    {

        $glassClaim  = GlassClaim::where('id', $request->claimId)->first();
        $userDetails  = User::where('id', $glassClaim->user_id)->first();


        if ($request->hasFile('incidentLeft')) {

            $file = $request->file('incidentLeft');

            $name = $this->gen_uuid() . $file->getClientOriginalName();

            $filePath = 'MIS/' . $userDetails->id . '/' . 'Claims' . '/' . 'GlassID-' . $glassClaim->id . '/' . $name;

            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            $incidentPhoto = IncidentPhoto::where('claim_id', $request->claimId)->first();
            $incidentPhoto->left = $filePath;
            $incidentPhoto->save();
        }

        Session::flash('rightIncident', 'Back Incident uploaded');
        return redirect()->back();
    }

    /**function for uploading and saving apk files to S3 */
    public function uploadfile(Request $request)
    {
        try {
            //code...
            $this->validate($request, [
                'file' => 'required', // file is required
            ]);
            $version_number = $request->version_number;
            $file =  $request->file('file');
            $result = $this->verifyImage($file);
            $name = $file->getClientOriginalName();
            $refinedname = explode('.', $name);

            $filepath = 'public/' . $refinedname[1] . $version_number . '.apk';

            if (Storage::disk('s3')->exists($filepath)) { //check if file exists, if yes delete and overwrrite with new file on DB  and s3
                $currentFile = DB::table('apkFileUploads')->where('filepath', $filepath)->first();

                if ($currentFile != null) {
                    DB::table('apkFileUploads')->where('filepath', $filepath)->delete(); //delete on db
                    Storage::disk('s3')->delete($filepath);   //delete on s3

                }
            }

            /**ToDo Save file path to db */

            Storage::disk('s3')->put($filepath, file_get_contents($file), 'public'); //upload the file
            $query = DB::table('apkFileUploads')->insert([
                'version_number' => $version_number, 'filepath' => $filepath, 'created_by' => auth()->user()->id, 'created_at' =>  Carbon::now(),
            ]); //save data to DB


            return redirect()->back()->with('success', 'File successfully uploaded');
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage()]);
        }
    }

    private function verifyImage($image)
    {
        $exif = exif_read_data($image, 0, true);
        if (array_key_exists('EXIF', $exif)) {
            $DateTime = \Carbon\Carbon::parse($exif['EXIF']['DateTimeOriginal'])->timestamp;
            $createdDateTime = Carbon::createFromTimestamp($DateTime);
            $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
            $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
            if ($diff_in_hours > 24)
                return false;
            elseif ($diff_in_hours > 0 && $diff_in_hours < 24)
                return true;
            else
                return false;
        } else {
            return false;
        }
    }

    private function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),

            // 16 bits for "time_mid"
            Helper::gen_ustring(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            Helper::gen_ustring(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            Helper::gen_ustring(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff)
        );
    }

    private function saveClaimsPath(Request $request)
    {
    }

    public function savePreInspectionPath($type, $imageName, $plate)
    {

        $path = 'AlphaBucket/PreInspection/' . $plate . '/' . $type . '-' . $imageName;

        Storage::disk('s3')->put($path, file_get_contents($imageName));

        return $path;
    }

    public function saveKycPath($type, $imageName, $omangId = null, $passportId = null)
    {

        if ($omangId != null) {

            $path = 'AlphaBucket/AgentApp/KYC/' . $omangId . '/' . $type . '-' . $imageName;
        } else {

            $path = 'AlphaBucket/AgentApp/KYC/' . $passportId . '/' . $type . '-' . $imageName;
        }

        Storage::disk('s3')->put($path, file_get_contents($imageName));

        return $path;
    }
}
