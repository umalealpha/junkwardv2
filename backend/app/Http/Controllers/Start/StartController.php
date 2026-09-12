<?php

namespace AlphaDirect\Http\Controllers\Start;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Vehicle;
use AlphaDirect\AgentKyc;

class StartController extends Controller
{
    public function Kycdata(Request $request)
    {
        if ($request->user_id) {
            $kycDetails = KYC::where('customer_id', $request->user_id)->first();
            return response()->json(['success' => true, 'kyc' => $kycDetails], 200);
        } else {
            return response()->json(['success' => false, 'Message' => 'User not found'], 401);
        }
    }

    public function updateKyc(Request $request)
    {
        if ($request->get('user_id')) {
            $kyc = AgentKyc::where('customer_id', base64_decode($request->get('user_id')))->first();
            if (!$kyc) {
                $kyc = new AgentKyc();
                $kyc->agentId = $request->get('user_id');
                $kyc->customer_id = base64_decode($request->get('user_id'));
            }
            $kyc->omangNumber = isset($request->omangNumber) && $request->get('omangNumber') != '' ? $request->get('omangNumber') : "";
            $kyc->passportNumber = isset($request->passportNumber) && $request->get('passportNumber') != "" ? $request->get('passportNumber') : "";

            $kyc->omang = isset($request->omangF) && $request->get('omangF') != "" ?  $request->get('omangF') : "";
            $kyc->omangBack = isset($request->omangB) && $request->get('omangB') != "" ? $request->get('omangB') : ""; 
            $kyc->omangExpiry = isset($request->omangExpiry) && $request->get('omangExpiry') != "" ?  date("Y-m-d", strtotime(strtr($request->get('omangExpiry'), '/', '-'))) : "";

            $kyc->passport = isset($request->passportF) && $request->get('passportF') != "" ? $request->get('passportF') : "";
            $kyc->passportBack = $request->get('passportB') != "" ? $request->get('passportB') : "";
            $kyc->passportExpiry = isset($request->passportExpiry) && $request->get('passportExpiry') != "" ? date("Y-m-d", strtotime(strtr($request->get('passportExpiry'), '/', '-'))): "";
            $kyc->passportIssuingCountry = isset($request->passportIssuingCountry) && $request->get('passportIssuingCountry') != "" ?  $request->get('passportIssuingCountry') : "";

            $kyc->driversLicense = isset($request->drivingLicenseFront) && $request->get('drivingLicenseFront') != "" ? $request->get('drivingLicenseFront') : "";
            $kyc->driving_license_back = isset($request->drivingLicenseBack) && $request->get('drivingLicenseBack') != "" ? $request->get('drivingLicenseBack') : "";
            $kyc->licenseExpiry = isset($request->licenseExpiry) && $request->get('licenseExpiry') != "" ? date("Y-m-d", strtotime(strtr($request->get('licenseExpiry'), '/', '-'))) : "";

            $kyc->proofResidence = isset($request->proofOfResidence) && $request->get('proofOfResidence') != "" ? $request->get('proofOfResidence') : "";
            $kyc->proof_residence_doc_type = isset($request->proof_residence_doc_type) && $request->get('proof_residence_doc_type') != "" ? $request->get('proof_residence_doc_type') : "";

            $kyc->proofIncome = isset($request->proofOfIncome) && $request->get('proofOfIncome') != "" ? $request->get('proofOfIncome') : "";
            $kyc->proof_income_doc_type = isset($request->proofIncome_type) && $request->get('proofIncome_type') != '' ? $request->get('proofIncome_type') : "";

            $kyc->save();
            $this->customerKycUpdate($request);
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKyc');;
        } else {
            return response()->json(['success' => false, 'Message' => 'Something went wrong'], 401)->withCallback('callbackKyc');;
        }
    }

    public function customerKycUpdate($request){
       
        $kyc = KYC::where('customer_id', base64_decode($request->get('user_id')))->first();
            if (!$kyc) {
                $kyc = new KYC();
                $kyc->agentId = $request->get('user_id');
                $kyc->customer_id = base64_decode($request->get('user_id'));
            }
            $kyc->omang_id = isset($request->omangNumber) && $request->get('omangNumber') != '' ? $request->get('omangNumber') : "";
            $kyc->passport_id = isset($request->passportNumber) && $request->get('passportNumber') != "" ? $request->get('passportNumber') : "";
            $kyc->omang = isset($request->omangF) && $request->get('omangF') != "" ?  $request->get('omangF') : "";
            $kyc->omang_back = isset($request->omangB) && $request->get('omangB') != "" ? $request->get('omangB') : ""; 
            $kyc->omangExpiry = isset($request->omangExpiry) && $request->get('omangExpiry') != "" ?  date("Y-m-d", strtotime(strtr($request->get('omangExpiry'), '/', '-'))) : "";

            $kyc->passport = isset($request->passportF) && $request->get('passportF') != "" ? $request->get('passportF') : "";
            $kyc->passport_back = $request->get('passportB') != "" ? $request->get('passportB') : "";
            $kyc->passportExpiry = isset($request->passportExpiry) && $request->get('passportExpiry') != "" ? date("Y-m-d", strtotime(strtr($request->get('passportExpiry'), '/', '-'))): "";
            $kyc->passportIssuingCountry = isset($request->passportIssuingCountry) && $request->get('passportIssuingCountry') != "" ?  $request->get('passportIssuingCountry') : "";

            $kyc->driving_license = isset($request->drivingLicenseFront) && $request->get('drivingLicenseFront') != "" ? $request->get('drivingLicenseFront') : "";
            $kyc->driving_license_back = isset($request->drivingLicenseBack) && $request->get('drivingLicenseBack') != "" ? $request->get('drivingLicenseBack') : "";
            $kyc->licenseExpiry = isset($request->licenseExpiry) && $request->get('licenseExpiry') != "" ? date("Y-m-d", strtotime(strtr($request->get('licenseExpiry'), '/', '-'))) : "";

            $kyc->proof_residence = isset($request->proofOfResidence) && $request->get('proofOfResidence') != "" ? $request->get('proofOfResidence') : "";
            $kyc->proof_residence_doc_type = isset($request->proof_residence_doc_type) && $request->get('proof_residence_doc_type') != "" ? $request->get('proof_residence_doc_type') : "";

            $kyc->proof_income = isset($request->proofOfIncome) && $request->get('proofOfIncome') != "" ? $request->get('proofOfIncome') : "";
            $kyc->proof_income_doc_type = isset($request->proofIncome_type) && $request->get('proofIncome_type') != '' ? $request->get('proofIncome_type') : "";
 
                $kyc->compliance = 0;
 
            $kyc->save();
    }

    public function deleteKyc(Request $request)
    {
        $user_id = base64_decode($request->get('user_id')); 
        $kyc = AgentKyc::where('customer_id', $user_id)->where('omang', $request->get('url'))->first();
        if ($kyc) {
            $kyc->omang = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = AgentKyc::where('customer_id', $user_id)->where('omangBack', $request->get('url'))->first();
        if ($kyc) {
            $kyc->omangBack = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document deleted succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = AgentKyc::where('customer_id', $user_id)->where('passport', $request->get('url'))->first();
        if ($kyc) {
            $kyc->passport = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = AgentKyc::where('customer_id', $user_id)->where('passportBack', $request->get('url'))->first();
        if ($kyc) {
            $kyc->passportBack = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = AgentKyc::where('customer_id', $user_id)->where('drivingLicense', $request->get('url'))->first();
        if ($kyc) {
            $kyc->drivingLicense = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = AgentKyc::where('customer_id', $user_id)->where('driving_license_back', $request->get('url'))->first();
        if ($kyc) {
            $kyc->driving_license_back = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

 
        $kyc = AgentKyc::where('customer_id', $user_id)->where('proofResidence', $request->get('url'))->first();
        if ($kyc) {
            $kyc->proofResidence = "";
            $kyc->proof_residence_doc_type = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = AgentKyc::where('customer_id', $user_id)->where('proofIncome', $request->get('url'))->first();
        if ($kyc) {
            $kyc->proofIncome = "";
            $kyc->proof_income_doc_type = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $this->customerKycDelete($user_id,$request);    
        return response()->json(['success' => false, 'Message' => 'Something went wrong'], 401)->withCallback('callbackKyc');;
    }

    public function customerKycDelete($user_id,$request){
        $kyc = KYC::where('customer_id', $user_id)->where('omang', $request->get('url'))->first();
        if ($kyc) {
            $kyc->omang = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = KYC::where('customer_id', $user_id)->where('omang_back', $request->get('url'))->first();
        if ($kyc) {
            $kyc->omang_back = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document deleted succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = KYC::where('customer_id', $user_id)->where('passport', $request->get('url'))->first();
        if ($kyc) {
            $kyc->passport = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = KYC::where('customer_id', $user_id)->where('passport_back', $request->get('url'))->first();
        if ($kyc) {
            $kyc->passport_back = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = KYC::where('customer_id', $user_id)->where('driving_license', $request->get('url'))->first();
        if ($kyc) {
            $kyc->driving_license = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = KYC::where('customer_id', $user_id)->where('driving_license_back', $request->get('url'))->first();
        if ($kyc) {
            $kyc->driving_license_back = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

 
        $kyc = KYC::where('customer_id', $user_id)->where('proofResidence', $request->get('url'))->first();
        if ($kyc) {
            $kyc->proofResidence = "";
            $kyc->proof_residence_doc_type = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }

        $kyc = KYC::where('customer_id', $user_id)->where('proofIncome', $request->get('url'))->first();
        if ($kyc) {
            $kyc->proofIncome = "";
            $kyc->proof_income_doc_type = "";
            $kyc->compliance = 0;
            $kyc->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackKycDelete');
        }
    }
    
    public function updatePreinspection(Request $request)
    {

        $vehicle = Vehicle::where('vehiclePlate', $request->vehiclePlate)->first();
        if ($vehicle != null) {
            $vehicle->vehiclePlate = $request->vehiclePlate;
            $vehicle->right = $request->right;
            $vehicle->back = $request->back;
            $vehicle->front = $request->front;
            $vehicle->left = $request->left;
            $vehicle->save();
            return response()->json(['success' => 200, 'Message' => 'Document saved succesfully'], 200)->withCallback('callbackPreinspection');;
        } else {
            return response()->json(['success' => false, 'Message' => 'Something went wrong'], 401)->withCallback('callbackPreinspection');;
        }
    }

    public function deleteVehiclePreinspection(Request $request)
    {
        $vehicle = Vehicle::where('vehiclePlate', $request->vehicle_plate)->first();

        switch (strtolower($request->url)) {
            case strtolower($vehicle->front):

                $vehicle->front = '';
                break;

            case  strtolower($vehicle->right):
                $vehicle->right = '';

                break;

            case strtolower($vehicle->back):
                $vehicle->back = '';

                break;

            case strtolower($vehicle->left):
                $vehicle->left = '';

                break;

            default:
                break;
        }
        $vehicle->save();

        return response()->json(['success' => 200, 'Message' => 'Document deleted succesfully'], 200)->withCallback('callbackPreinspectionDelete');;

        #return response()->json(['success' => false, 'Message' => 'Something went wrong'], 401)->withCallback('callbackPreinspectionDelete');

    }
}
