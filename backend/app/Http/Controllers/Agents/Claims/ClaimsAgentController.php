<?php

namespace AlphaDirect\Http\Controllers\Agents\Claims;


use Illuminate\Http\Request;
use AlphaDirect\Vehicle;
use AlphaDirect\User;
use AlphaDirect\Notifications;
use AlphaDirect\GlassClaim;
use AlphaDirect\Policy;
use AlphaDirect\Supplier;
use AlphaDirect\Quote;
use AlphaDirect\CarList;
use AlphaDirect\PolicyPlan;
use AlphaDirect\IncidentPhoto;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use DB;
use Auth;
use Mail;
use Session;
use PDF;
use URL;
use Redirect;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;

class ClaimsAgentController extends Controller
{
    /*   public function __construct()
    {
        $this->middleware('auth');
    }   */

    /*
     * returns all the claims
     * return: claim view page
     */
    public function viewClaims()
    {

        return view('Agents/Claims/agentClaims');
    }

    /*
     * retrieves all the agent claims
     * return: JSON
     */
    public function getAllAgentClaims()
    {

        $glassClaims = DB::table('glass_claims')
            ->crossJoin('vehicle', 'glass_claims.user_id', '=', 'vehicle.user_id')
            ->leftJoin('policies', 'glass_claims.policy_id', '=', 'policies.id')
            ->leftJoin('policy_plan', 'policies.plan_id', '=', 'policy_plan.id')
            ->where('glass_claims.agent_id', 5)
            ->select(
                'policies.policyNumber',
                'policies.isActive',
                'vehicle.vehiclePlate',
                'vehicle.model',
                'vehicle.make',
                'vehicle.year',
                'vehicle.chassisNo',
                'vehicle.purpose',
                'glass_claims.damageExtent',
                'glass_claims.incidentDate',
                'glass_claims.brokenSize',
                'glass_claims.glassType',
                'glass_claims.id'
            )
            ->get();




        return response()->json($glassClaims);
    }

    /*
     * method to proceess claim
     * return viewprocess claim
     */
    public function processClaim($id)
    {



        $glass_claims = GlassClaim::find($id);
        $userDetails = User::find($glass_claims->user_id);
        $policyDetails = Policy::find($glass_claims->policy_id);
        $vehicleDetails = Vehicle::find($policyDetails->id);
        $incidentPhotos = IncidentPhoto::where('claim_id', $glass_claims->id)->first();
        $suppliers = Supplier::all();

        //return response()->json($suppliers);

        $carMake = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get();

        $supplierQuotes = DB::table('suppliers')
            ->crossJoin('quote_totals', 'suppliers.id', '=', 'quote_totals.supplier_id')
            ->select(
                'suppliers.supplierName',
                'suppliers.email',
                'suppliers.telephone',
                'suppliers.image',
                'quote_totals.total'
            )
            ->get();




        $suppliersCount = count($suppliers);
        return view('Agents/Claims/processClaim', compact('glass_claims', 'userDetails', 'policyDetails', 'vehicleDetails'))->with(compact('suppliers', 'suppliersCount', 'supplierQuotes', 'carMake', 'makeCount', 'carMake', 'incidentPhotos'));
    }


    public function authorizeClaim(Request $request)
    {



        switch ($request->claimAuthorization) {

            case 'authorized':

                //$response = InfobipSms::send('+267' . $request->customerCell, 'Dumelang ' . $request->firstName . ', Your claim has been approved and our suppliers have been contacted .');
                $response = event(new \AlphaDirect\Events\SendSms('+267' . $request->customerCell, 'Dumelang ' . $request->firstName . ', Your claim has been approved and our suppliers have been contacted .'));
                $glass_claims = GlassClaim::where('id', $request->claim_id)->first();
                $glass_claims->isActive = 1;
                $glass_claims->claimStep = 3;
                $glass_claims->save();

                $data = array(
                    'claim_id' => $request->claim_id,

                );

                Session::flash('claimSuccess', 'Suppliers and Customer have been notified');
                return redirect()->back();

                break;

            case 'unauthorized':

               // $response = InfobipSms::send('+267' . $request->customerCell, 'Dumelang ' . $request->firstName . ', Your claim has been denied , you have 30 days to appeal .');
                $response = event(new \AlphaDirect\Events\SendSms('+267' . $request->customerCell, 'Dumelang ' . $request->firstName . ', Your claim has been denied , you have 30 days to appeal .'));
                $glass_claims = GlassClaim::where('id', $request->claim_id)->first();

                $glass_claims->isActive = 0;
                $glass_claims->isClosed = 1;
                $glass_claims->save();

                Session::flash('claimSuccess', 'Suppliers and Customer have been notified');
                return redirect()->route('agent-allClaims');

                break;

            default:

                break;
        }
    }

    public function createClaim($vehiclePlate)
    {

        $customerVehicle = Vehicle::where('vehiclePlate', $vehiclePlate)->first();
        $customerDetails = User::find($customerVehicle->user_id);
        $customerKYC = KYC::where('user_id', $customerVehicle->user_id)->first();



        if ($customerKYC->compliance == 0) {



            Session::flash('notCompliant', 'Customer not KYC compliant');
            return redirect()->route('agent-addPolicy');
        }

        $glassClaim = new GlassClaim;
        $glassClaim->user_id = $customerVehicle->user_id;
        $glassClaim->agent_id = Auth::user()->id;
        $glassClaim->policy_id = $customerVehicle->policy_id;
        $glassClaim->damageExtent = null;
        $glassClaim->glassType = null;
        $glassClaim->brokenSize = null;
        $glassClaim->incidentDate = null;
        $glassClaim->thirdPartyName = null;
        $glassClaim->thirdPartyaddress = null;
        $glassClaim->claimStep = 1;
        $glassClaim->save();

        $incidentPhotos = new IncidentPhoto;
        $incidentPhotos->claim_id = $glassClaim->id;
        $incidentPhotos->front = null;
        $incidentPhotos->back = null;
        $incidentPhotos->right = null;
        $incidentPhotos->left = null;
        $incidentPhotos->save();


        $agentNotifications = new Notifications;
        $agentNotifications->user_id = Auth::user()->id;
        $agentNotifications->type = 'Customer Glass Claim';
        $agentNotifications->data = 'You have been assigned a claim.';
        $agentNotifications->action = 'viewClaim/';
        $agentNotifications->save();

        // response()->json(['message'=>'Claim successfully submitted']);

        return redirect()->action(
            'Agents\Claims\ClaimsAgentController@processClaim',
            ['id' => $glassClaim->id]
        );
    }




    public function handleClaim(Request $request)
    {
        $glassClaimUpdate =  GlassClaim::where('id', $request->claim_id)->first();
        $glassClaimUpdate->causeOfDamage = $request->causeOfDamage;
        $glassClaimUpdate->incidentDate = $request->incidentDate;
        $glassClaimUpdate->damageExtent = $request->damageExtent;
        $glassClaimUpdate->glassType = $request->glassType;
        $glassClaimUpdate->breakSize = $request->breakSize;
        $glassClaimUpdate->glassAddress = $request->glassAddress;
        $glassClaimUpdate->claimStep = 2;
        $glassClaimUpdate->update();



        Session::flash('submittedClaim', 'Claim Step 1 completed successfully');
        // response()->json('Submitted claim successfully');
        return redirect()->back();
    }

    public function sendQuote($vehiclePlate, $supplierEmail, $claimId)
    {
        $customerVehicle = Vehicle::where('vehiclePlate', $vehiclePlate)->first();
        $userDetails = User::find($customerVehicle->user_id)->first();
        $email = $supplierEmail;


        $mailData = array('supplierMail' => $supplierEmail);

        /*$data = array(
            $customerVehicle = Vehicle::where('vehiclePlate',$vehiclePlate)->first(),
            $userDetails = User::find($customerVehicle->user_id)
        );*/

        $data = [
            'vehicleMake' => $customerVehicle->make,
            'vehicleModel' => $customerVehicle->model,
            'vehicleYear' => $customerVehicle->year,
            'vehiclePlate' => $customerVehicle->vehiclePlate,
            'customerFirstName' => $userDetails->firstName,
            'vehcileID' => $customerVehicle->id,
            'supplierMail' => $email
        ];

        $pdf = PDF::loadView('Mail.rfq', $data);

        $mail = new PHPMailer;
        // date_default_timezone_set('Asia/Calcutta');

        $sender = 'crmuser01@alphadirect.co.bw'; // this will be overwritten by GMail

        $header = "X-Mailer: PHP/" . phpversion() . "Return-Path: $sender";

        $mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = 'smtp.mailgun.org';  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = env('U_NAME_POSTMASTER');                     // SMTP username
        $mail->Password   = env('PW_POSTMASTER');                       // SMTP password
        $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = 587;                                    // TCP port to connect to

        $mail->SMTPOptions = array(
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->From = $sender;
        $mail->AddAddress($email);

        $mail->IsHTML(true);
        $mail->CreateHeader($header);

        $mail->Subject = 'Supplier Quote Request';
        $mail->Body    = 'This is a request for glass repair for a ' . $customerVehicle->model . ' ' . $customerVehicle->year . '. Please use this link to upload your quote: <a href="http://localhost:8000/supplierUploadQuoteView/' . $customerVehicle->id . '/supplierEmail/' . $supplierEmail . '"> Please click here to upload quote</a>';
        // $mail->Body    = 'This is a request for glass repair for '.$userDetails->firstName.'\'s '.$customerVehicle->make.''.$customerVehicle->model.' '.$customerVehicle->year.'. Please use this link to upload your quote: <a href="{{ URL }}::route("'+supplierViewQuote+'",['+id+'=>$customerVehicle->id,"'+supplierEmail+'"=>$supplierMail])}}"> Please click here to upload quote</a>';
        $mail->AltBody = 'this is altbody';
        // return an array with two keys: error & message
        if (!$mail->Send()) {
            return array('error' => true, 'message' => 'Mailer Error: ' . $mail->ErrorInfo);
        } else {



            $glassClaimUpdate =  GlassClaim::where('id', $claimId)->first();
            $glassClaimUpdate->claimStep = 4;
            $glassClaimUpdate->update();


            Session::flash('quoteSent', 'Request for quote sent to ' . $mailData['supplierMail']);

            return redirect()->back();
        }
    }

    public function sendAllSuppliers($vehiclePlate)
    {
        $urlValue = \Config::get('values.graphite_url');
        $customerVehicle = Vehicle::where('vehiclePlate', $vehiclePlate)->first();
        $userDetails = User::find($customerVehicle->user_id)->first();

        $allSuppliers = Supplier::all();
        $mail = new PHPMailer;
        $sender = 'crmuser01@alphadirect.co.bw'; // this will be overwritten by GMail

        $header = "X-Mailer: PHP/" . phpversion() . "Return-Path: $sender";

        $mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = 'smtp.mailgun.org';  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = 'postmaster@sandbox9ed48993b579418d91ef16b75ef99406.mailgun.org';                     // SMTP username
        $mail->Password   = 'be930e68ed91e2e7107d512e2a3a2dc6-52b0ea77-6e488ee0';                               // SMTP password
        $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = 587;                                    // TCP port to connect to

        $mail->SMTPOptions = array(
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->From = $sender;
        foreach ($allSuppliers as $supplier) {

            // date_default_timezone_set('Asia/Calcutta');

            $mail->AddAddress($supplier["email"]);
        }
        $mail->IsHTML(true);
        $mail->CreateHeader($header);

        $mail->Subject = 'Supplier Quote Request test1';
        $mail->Body    = 'This is a request for glass repair for a ' . $customerVehicle->model . ' ' . $customerVehicle->year . '. Please use this link to upload your quote: <a href="' . $urlValue . 'supplierUploadQuoteView/' . $customerVehicle->id . '/supplierEmail/' . $supplier->email . '"> Please click here to upload quote</a>';
        $mail->AltBody = 'this is altbody';
        // return an array with two keys: error & message
        if (!$mail->Send()) {
            return array('error' => true, 'message' => 'Mailer Error: ' . $mail->ErrorInfo);
        }


        Session::flash('quoteSent', 'Request for quote sent to all suppliers');

        return redirect()->back();
    }

    public function acceptQuote($cellphone, $email)
    {
        $userDetails = User::where('cellphone', $cellphone)->first();
        $supplierDetails = Supplier::where('email', $email)->first();
        $mailData = array('supplierMail' => $email);
        //$response = InfobipSms::send('+267' . $userDetails['cellphone'], 'Dumelang ' . $userDetails->firstName . ', Your car will be serviced by:' . $supplierDetails->supplierName);
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $userDetails['cellphone'], 'Dumelang ' . $userDetails->firstName . ', Your car will be serviced by:' . $supplierDetails->supplierName));
        $mail = new PHPMailer;
        // date_default_timezone_set('Asia/Calcutta');

        $sender = 'crmuser01@alphadirect.co.bw'; // this will be overwritten by GMail

        $header = "X-Mailer: PHP/" . phpversion() . "Return-Path: $sender";

        $mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = 'smtp.mailgun.org';  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = env('U_NAME_POSTMASTER');                     // SMTP username
        $mail->Password   = env('PW_POSTMASTER');                               // SMTP password
        $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = 587;                                    // TCP port to connect to

        $mail->SMTPOptions = array(
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->From = $sender;
        $mail->AddAddress($email);

        $mail->IsHTML(true);
        $mail->CreateHeader($header);

        $mail->Subject = 'Supplier Quote Request';
        $mail->Body    = 'This is an email to let you know that you have been selected for our request';
        // $mail->Body    = 'This is a request for glass repair for '.$userDetails->firstName.'\'s '.$customerVehicle->make.''.$customerVehicle->model.' '.$customerVehicle->year.'. Please use this link to upload your quote: <a href="{{ URL }}::route("'+supplierViewQuote+'",['+id+'=>$customerVehicle->id,"'+supplierEmail+'"=>$supplierMail])}}"> Please click here to upload quote</a>';
        $mail->AltBody = 'this is altbody';
        // return an array with two keys: error & message
        if (!$mail->Send()) {
            return array('error' => true, 'message' => 'Mailer Error: ' . $mail->ErrorInfo);
        } else {
            Session::flash('quoteSent', 'Request for quote sent to ' . $mailData['supplierMail']);

            return redirect()->back();
        }

        Session::flash('quoteSent', 'Client and Supplier have been notified.');

        return redirect()->route('agent-allClaims');
    }

    public function testMail()
    {

        $mail = new PHPMailer;
        // date_default_timezone_set('Asia/Calcutta');

        $sender = 'kkatolkar@alphadirect.co.bw'; // this will be overwritten by GMail

        $header = "X-Mailer: PHP/" . phpversion() . "Return-Path: $sender";

        $mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = 'smtp.mailgun.org';  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = env('U_NAME_POSTMASTER');                     // SMTP username
        $mail->Password   = env('PW_POSTMASTER');                               // SMTP password
        $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = 587;                                    // TCP port to connect to

        $mail->SMTPOptions = array(
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->From = $sender;
        $mail->FromName = 'kamlesh';
        $mail->AddCC('kkatolkar@alphadirect.co.bw', 'Arjun Iyer');
       # $mail->AddCC('', 'Arjun Iyer');
        $mail->AddAddress('kamlesh.katolkar@gmail.com');

        $mail->IsHTML(true);
        $mail->CreateHeader($header);

        $mail->Subject = 'this is subject';
        $mail->Body    = 'this is body';
        $mail->AltBody = 'this is altbody';
        // return an array with two keys: error & message
        if (!$mail->Send()) {
            return array('error' => true, 'message' => 'Mailer Error: ' . $mail->ErrorInfo);
        } else {
            return array('error' => false, 'message' =>  "Message sent!");
        }
    }
}
