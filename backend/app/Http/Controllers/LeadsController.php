<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Banks;
use AlphaDirect\Customer;
use AlphaDirect\DPOTransactionDummyData;
use AlphaDirect\Lead;
use AlphaDirect\Lookup;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\User;
use AlphaDirect\PolicyLead;
use AlphaDirect\RealpayTransactionDummyData;
use AlphaDirect\RealpayTransactionToAddInTxLog;
use AlphaDirect\TransactionToAddInTxLog;
use Auth;
use Carbon\Carbon;
use DB;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Response;
use Yajra\DataTables\DataTables;
use AlphaDirect\Models\PendingReccuringDPO;
use AlphaDirect\VcsTransactionDummyData;
use AlphaDirect\VcsTransactionToAddInTxLog;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Artisan;
use File;
use Log;
class LeadsController extends Controller
{
    public function index()
    {
        return view('admin.policy.leads.index');
    }

    public function data()
    {
        try {

            $allLeads = Lead::get(['id', 'customerId', 'leadCode', 'agentId', 'created_at']);
            return DataTables::of($allLeads)

                ->editColumn('created_at', function ($allLeads) {
                    return $allLeads->created_at->diffForHumans();
                })

                ->addColumn('names', function ($allLeads) {
                    if (isset($allLeads->customerLead->firstName)) {
                        return ucwords($allLeads->customerLead->firstName) . ' ' . ucwords($allLeads->customerLead->lastName);
                    } else {
                        return null;
                    }

                })

                ->addColumn('cellphone', function ($allLeads) {
                    if (isset($allLeads->customerLead->cellphone)) {
                        return $allLeads->customerLead->cellphone;
                    } else {
                        return null;
                    }

                })
                ->editColumn('agentId', function ($allLeads) {
                    if (isset($allLeads->createdByAgent)) {
                        return $allLeads->createdByAgent->firstName . ' ' . $allLeads->createdByAgent->lastName;
                    } else {
                        return null;
                    }

                })

                ->addColumn('email', function ($allLeads) {
                    if (isset($allLeads->customerLead->email)) {
                        return $allLeads->customerLead->email;
                    } else {
                        return null;
                    }

                })
                ->addColumn('actions', function ($allLeads) {
                    $actions = '<a href="' . route('lead.edit', $allLeads->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="la la-edit"></i>
                                </a>
                                <a href="" value="' . $allLeads->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                    <i class="la la-trash"></i>
                                </a>';
                    return $actions;
                })

                ->rawColumns(['names', 'phone', 'email', 'actions'])
                ->make(true);

        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }

    }

    public function edit($id)
    {
        $lead = lead::findorFail($id);

        $user = Customer::where('id', $lead->customerId)->first(array('id', 'firstName', 'lastName', 'email', 'cellphone'));
        $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle'));
        $vehicleMakes = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get(array('s_Make'));

        $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
        if ($lead->agentId != null) {
            $agent_name = User::where('id', $lead->agentId)->first(array('firstName', 'lastName'));
        }
        $banks = Banks::all();
        $motor_items = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));

        return view('admin/policy/leads/edit', compact('lead', 'user', 'products', 'agent_name', 'motor_items', 'vehicle_purpose', 'banks', 'vehicleMakes'));
    }

    public function store(Request $request)
    {

        try {

            $user = new Customer();
            $user->firstName = $request->firstName;
            $user->lastName = $request->lastName;
            $user->cellphone = $request->cellphone;
            $user->email = $request->email;
            $user->save();
            if ($user != null) {
                //Latestid for Lead Number
                $latest = PolicyLead::latest()->first(array('id'));

                $lead = new Lead();
                $lead->customerId = $user->id;
                $lead->product = $request->product_name;
                $lead->hasPurchased = $request->has_purchased;
                $lead->agentId = Auth::id() == null ? $request->agent_id: Auth::id() ;
                $lead->save();
                return Response::json(['message' => 'Lead saved'], 200);

            }

        } catch (Exception $ex) {

            return Response::json(['errormsg' => $ex->getMessage()], 500);
        }

    }

    public function getModalDelete(Request $request)
    {
        $body = "Are you sure you want to delete the Lead Source ? ";
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);

    }

    public function destroy($id)
    {
        try {
            $lead = Lead::findorFail($id);
            $lead->delete();
            return redirect()->back()->with('message', 'Lead Successful Deletd');

        } catch (Exception $ex) {
            return Response::json(['errormsg' => $ex->getMessage()], 500);
        }
    }
    public function transFormview(Request $request)
    {
        if($request->file('transfile')){
            ini_set('max_execution_time', '300');
            //Get File
           $upload     = $request->file('transfile');
           $file_path  = $upload->getRealPath();
           //OPen file and read file
           $file           = fopen($file_path,'r');

           $header         = fgetcsv($file);
           $escapeheader   = [];

           foreach($header as $key=>$value){
               $lowerHeader    = strtolower($value);
               $escapedItems   = preg_replace("/[^a-z]/", "", $lowerHeader);
               $noSpaceString  = preg_replace("/\s+/", "", $escapedItems);
               array_push($escapeheader,$noSpaceString);
           }


           while ($columns = fgetcsv($file)) {
               if($columns[0] ==''){
                   continue;
               }
              $data[] = array_combine($escapeheader,$columns);

       }

        foreach ($data as $key => $data) {
            $policyNumber = null;
            if (isset($data['bookingref'])) {
                if(str_contains($data['bookingref'], '/')){
                    $policyNumber = strtok($data['bookingref'], '/');
                } else {
                    $policyNumber = $data['bookingref'];
                }
            }

            $add = DPOTransactionDummyData::updateOrCreate([
                'token' => isset($data['token']) ? $data['token'] : null,
            ], [
                "ref" => isset($data['ref']) ? $data['ref'] : null,
                "policynumber" => isset($policyNumber) ? $policyNumber : null,
                "token" => isset($data['token']) ? $data['token'] : null,
                "companyname" => isset($data['companyname']) ? $data['companyname'] : null,
                "date" => isset($data['date']) ? Carbon::parse($data['date'])->format('Y-m-d') : null,
                "bookingref" => isset($data['bookingref']) ? $data['bookingref'] : null,
                "servicedate" => isset($data['servicedate']) ? Carbon::parse($data['servicedate'])->format('Y-m-d') : null,
                "customername" => isset($data['customername']) ? $data['customername'] : null,
                "customeraddress" => isset($data['customeraddress']) ? $data['customeraddress'] : null,
                "customeremail" => isset($data['customeremail']) ? $data['customeremail'] : null,
                "customerphonenumber" => isset($data['customerphonenumber']) ? $data['customerphonenumber'] : null,
                "status" => isset($data['status']) ? $data['status'] : null,
                "total" => isset($data['total']) ? $data['total'] : null,
                "dpofee" => isset($data['dpofee']) ? $data['dpofee'] : null,
                "currency" => isset($data['currency']) ? $data['currency'] : null,
                "approval" => isset($data['approval']) ? $data['approval'] : null,
                "paymentdate" => isset($data['paymentdate']) ? Carbon::parse($data['paymentdate'])->format('Y-m-d') : null,
                "paymentmethod" => isset($data['paymentmethod']) ? $data['paymentmethod'] : null,
                "bankname" => isset($data['bankname']) ? $data['bankname'] : null,
                "mnoname" => isset($data['mnoname']) ? $data['mnoname'] : null,
                "cardholder" => isset($data['cardholder']) ? $data['cardholder'] : null,
                "user" => isset($data['user']) ? $data['user'] : null,
                "finalpayment" => isset($data['finalpayment']) ? $data['finalpayment'] : null,
                "finalcurrency" => isset($data['finalcurrency']) ? $data['finalcurrency'] : null,
                "mcc" => isset($data['mcc']) ? $data['mcc'] : null,
                "netamount" => isset($data['netamount']) ? $data['netamount'] : null,
                "grossamountusd" => isset($data['grossamountusd']) ? $data['grossamountusd'] : null,
            ]);
        }

        // return redirect()->back()->with('success', 'Record added successfully.');
        // return DataTables::of($data)

        // // ->rawColumns(['status'])
        // ->make(true);

        return view('/admin/transFormcsv', compact('data'));

        }else{
            $data = null;
            return view('/admin/transFormcsv',compact('data'));
        }


    }

    public function transFormviewRealpay(Request $request)
    {
        if($request->file('transfile')){
            ini_set('max_execution_time', '300');
            //Get File
           $upload     = $request->file('transfile');
           $file_path  = $upload->getRealPath();
           //OPen file and read file
           $file           = fopen($file_path,'r');

           $header         = fgetcsv($file);
           $escapeheader   = [];

           foreach($header as $key=>$value){
               $lowerHeader    = strtolower($value);
               $escapedItems   = preg_replace("/[^a-z]/", "", $lowerHeader);
               $noSpaceString  = preg_replace("/\s+/", "", $escapedItems);
               array_push($escapeheader,$noSpaceString);
           }


           while ($columns = fgetcsv($file)) {
               if($columns[0] ==''){
                   continue;
               }
              $data[] = array_combine($escapeheader,$columns);

       }


        foreach ($data as $key => $data) {
            if(str_contains($data['clientnumber'], '/')){
                $policyNumber = strtok($data['clientnumber'], '/');
            } else {
                $policyNumber = $data['clientnumber'];
            }

            $policy = Policy::where('policyNumber',$policyNumber)->first();

            if (isset($policy)) {
                $update = RealpayTransactionDummyData::where('clientNumber',$data['clientnumber'])->where('contractNumber',$data['contractnumber'])->where('instSeq',$data['instseq'])->first();
                if (isset($update)) {
                    $update->clientName = isset($data['clientname']) ? $data['clientname'] : null;
                    $update->merchant = isset($data['merchant']) ? $data['merchant'] : null;
                    $update->clientNumber = isset($data['clientnumber']) ? $data['clientnumber'] : null;
                    $update->installmentDate = isset($data['installmentdate']) ? Carbon::parse($data['installmentdate'])->format('Y-m-d') : null;
                    $update->contractNumber = isset($data['contractnumber']) ? $data['contractnumber'] : null;
                    $update->contractSequence = isset($data['contractsequence']) ? $data['contractsequence'] : null;
                    $update->instSeq = isset($data['instseq']) ? $data['instseq'] : null;
                    $update->installmentAmount = isset($data['installmentamount']) ? $data['installmentamount'] : null;
                    $update->totalAmount = isset($data['totalamount']) ? $data['totalamount'] : null;
                    $update->collectedAmount = isset($data['collectedamount']) ? $data['collectedamount'] : null;
                    $update->currentCycleHits = isset($data['currentcyclehits']) ? $data['currentcyclehits'] : null;
                    $update->hitsAllowed = isset($data['hitsallowedcurrenttracking']) ? $data['hitsallowedcurrenttracking'] : null;
                    $update->tracking = isset($data['tracking']) ? $data['tracking'] : null;
                    $update->reportStatus = isset($data['reportstatus']) ? $data['reportstatus'] : null;
                    $update->currentStatus = isset($data['currentstatus']) ? $data['currentstatus'] : null;
                    $update->result = isset($data['result']) ? $data['result'] : null;
                    $update->clientBank = isset($data['clientbank']) ? $data['clientbank'] : null;
                    $update->save();

                } else {
                    $add = new RealpayTransactionDummyData();
                    $add->clientName = isset($data['clientname']) ? $data['clientname'] : null;
                    $add->merchant = isset($data['merchant']) ? $data['merchant'] : null;
                    $add->clientNumber = isset($data['clientnumber']) ? $data['clientnumber'] : null;
                    $add->installmentDate = isset($data['installmentdate']) ? Carbon::parse($data['installmentdate'])->format('Y-m-d') : null;
                    $add->contractNumber = isset($data['contractnumber']) ? $data['contractnumber'] : null;
                    $add->contractSequence = isset($data['contractsequence']) ? $data['contractsequence'] : null;
                    $add->instSeq = isset($data['instseq']) ? $data['instseq'] : null;
                    $add->installmentAmount = isset($data['installmentamount']) ? $data['installmentamount'] : null;
                    $add->totalAmount = isset($data['totalamount']) ? $data['totalamount'] : null;
                    $add->collectedAmount = isset($data['collectedamount']) ? $data['collectedamount'] : null;
                    $add->currentCycleHits = isset($data['currentcyclehits']) ? $data['currentcyclehits'] : null;
                    $add->hitsAllowed = isset($data['hitsallowedcurrenttracking']) ? $data['hitsallowedcurrenttracking'] : null;
                    $add->tracking = isset($data['tracking']) ? $data['tracking'] : null;
                    $add->reportStatus = isset($data['reportstatus']) ? $data['reportstatus'] : null;
                    $add->currentStatus = isset($data['currentstatus']) ? $data['currentstatus'] : null;
                    $add->result = isset($data['result']) ? $data['result'] : null;
                    $add->clientBank = isset($data['clientbank']) ? $data['clientbank'] : null;
                    $add->save();
                }
            }

        }

        // return redirect()->back()->with('success', 'Record added successfully.');
        // return DataTables::of($data)

        // // ->rawColumns(['status'])
        // ->make(true);

        return view('/admin/transFormviewRealpay', compact('data'));

        }else{
            $data = null;
            return view('/admin/transFormviewRealpay',compact('data'));
        }

    }


    public function transFormviewVcs()
    {
        return view('/admin/transFormviewVcs');
    }

    public function storeTransFormVcs(Request $request)
    {
        if($request->file('transfile')){
            ini_set('max_execution_time', '300');
            //Get File
           $upload     = $request->file('transfile');
           $file_path  = $upload->getRealPath();
           //OPen file and read file
           $file           = fopen($file_path,'r');

           $header         = fgetcsv($file);
           $escapeheader   = [];

           foreach($header as $key=>$value){
               $lowerHeader    = strtolower($value);
               $escapedItems   = preg_replace("/[^a-z]/", "", $lowerHeader);
               $noSpaceString  = preg_replace("/\s+/", "", $escapedItems);
               array_push($escapeheader,$noSpaceString);
           }


           while ($columns = fgetcsv($file)) {
               if($columns[0] ==''){
                   continue;
               }
              $data[] = array_combine($escapeheader,$columns);

            }
            // $request->validate([
            //     'transfile' => 'required'
            // ]);

            // $file  = file($request->transfile->getRealPath());

            // // $data = array_slice($file,1);

            // $parts =(array_chunk($file,5000));
            // $directory = public_path('pending-files');

            // if(!File::exists($directory)) {
            //     File::makeDirectory($directory);
            // }

            // foreach ($parts as $key => $part) {
            //     $fileName = public_path('pending-files/'.date('y-m-d-H-i-s').$key. '.csv');
           //     file_put_contents($fileName,$part);
          // }

          // (new VcsTransactionDummyData())->importData();

          // session()->flash('status','Queued for importing');

          // Log::info('Vcs csv uploaded...');




        foreach ($data as $key => $data) {
            if (isset($data['accountholder'])) {
                $data['name'] = $data['accountholder'];
            }

            // if (isset($data['transactiondate'])) {

            //     if (str_contains($data['transactiondate'], '/')){
            //         $data['transactiondate'] = date_create_from_format('d/m/Y H:i:s', $data['transactiondate']);
            //     }
            // }

            if (isset($data['settlementdate'])) {
                if (str_contains($data['settlementdate'], ' AM') || str_contains($data['settlementdate'], ' PM')) {
                    $replace = array(' AM',' PM');
                    $data['settlementdate'] = str_replace($replace, '', $data['settlementdate']);
                }

                if (str_contains($data['settlementdate'], '/')){
                    $data['settlementdate'] = date_create_from_format('d/m/Y H:i:s', $data['settlementdate']);
                }

            }

            $add = VcsTransactionDummyData::updateOrCreate([
                'reference' => isset($data['reference']) ? $data['reference'] : null,
            ], [
                "reference" => isset($data['reference']) ? $data['reference'] : null,
                "originalreference" => isset($data['originalreference']) ? $data['originalreference'] : null,
                "name" => isset($data['name']) ? $data['name'] : null,
                "goods" => isset($data['goods']) ? $data['goods'] : null,
                "amount" => isset($data['amount']) ? $data['amount'] : null,
                "bp" => isset($data['bp']) ? $data['bp'] : null,
                "code" => isset($data['code']) ? $data['code'] : null,
                "response" => isset($data['response']) ? $data['response'] : null,
                "settlementdate" => isset($data['settlementdate']) ? Carbon::parse($data['settlementdate'])->format('Y-m-d H:i:s') : null,
                "transactiondate" => isset($data['transactiondate']) ? Carbon::parse($data['transactiondate'])->format('Y-m-d H:i:s') : null,
                "settlementreference" => isset($data['settlementreference']) ? $data['settlementreference'] : null,
                "interface" => isset($data['interface']) ? $data['interface'] : null,
                "status" => isset($data['resultdescription']) ? $data['resultdescription'] : null,
            ]);
        }

          return view('/admin/transFormviewVcs');
        }else{
          $data = null;
          return view('/admin/transFormviewRealpay',compact('data'));
        }
    }

    public function dpoTransDataStore(Request $request)
    {
        try {
            $data = DPOTransactionDummyData::where('id',$request->id)->first();
            if (isset($data)) {
                $add = TransactionToAddInTxLog::updateOrCreate([
                    'token' => isset($data['token']) ? $data['token'] : null,
                ], [
                    "ref" => isset($data['ref']) ? $data['ref'] : null,
                    "token" => isset($data['token']) ? $data['token'] : null,
                    "companyname" => isset($data['companyname']) ? $data['companyname'] : null,
                    "date" => isset($data['date']) ? Carbon::parse($data['date'])->format('Y-m-d') : null,
                    "bookingref" => isset($data['bookingref']) ? $data['bookingref'] : null,
                    "servicedate" => isset($data['servicedate']) ? Carbon::parse($data['servicedate'])->format('Y-m-d') : null,
                    "customername" => isset($data['customername']) ? $data['customername'] : null,
                    "customeraddress" => isset($data['customeraddress']) ? $data['customeraddress'] : null,
                    "customeremail" => isset($data['customeremail']) ? $data['customeremail'] : null,
                    "customerphonenumber" => isset($data['customerphonenumber']) ? $data['customerphonenumber'] : null,
                    "status" => isset($data['status']) ? $data['status'] : null,
                    "total" => isset($data['total']) ? $data['total'] : null,
                    "dpofee" => isset($data['dpofee']) ? $data['dpofee'] : null,
                    "currency" => isset($data['currency']) ? $data['currency'] : null,
                    "approval" => isset($data['approval']) ? $data['approval'] : null,
                    "paymentdate" => isset($data['paymentdate']) ? Carbon::parse($data['paymentdate'])->format('Y-m-d') : null,
                    "paymentmethod" => isset($data['paymentmethod']) ? $data['paymentmethod'] : null,
                    "bankname" => isset($data['bankname']) ? $data['bankname'] : null,
                    "mnoname" => isset($data['mnoname']) ? $data['mnoname'] : null,
                    "cardholder" => isset($data['cardholder']) ? $data['cardholder'] : null,
                    "user" => isset($data['user']) ? $data['user'] : null,
                    "finalpayment" => isset($data['finalpayment']) ? $data['finalpayment'] : null,
                    "finalcurrency" => isset($data['finalcurrency']) ? $data['finalcurrency'] : null,
                    "mcc" => isset($data['mcc']) ? $data['mcc'] : null,
                    "netamount" => isset($data['netamount']) ? $data['netamount'] : null,
                    "grossamountusd" => isset($data['grossamountusd']) ? $data['grossamountusd'] : null,
                ]);

                $update = DPOTransactionDummyData::where('id',$request->id)->first();
                if (isset($update)) {
                    $update->add_transaction = 1;
                    $update->save();
                }
                return response()->json(['status' => 'success', 'message' => 'Record added successfully.'], 200);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Record not found.'], 401);

            }
        } catch (\Exception $ex) {
            return response()->json(['status' => 'error', 'message' => $ex->getMessage()], 401);
        }
    }

    public function realpayTransDataStore(Request $request)
    {
        try {
            $data = RealpayTransactionDummyData::where('id',$request->id)->first();
                if (isset($data)) {
                    $update = RealpayTransactionToAddInTxLog::where('clientNumber',$data['clientnumber'])->where('contractNumber',$data['contractnumber'])->where('instSeq',$data['instseq'])->first();
                if (isset($update)) {
                    $update->clientName = isset($data['clientName']) ? $data['clientName'] : null;
                    $update->merchant = isset($data['merchant']) ? $data['merchant'] : null;
                    $update->clientNumber = isset($data['clientNumber']) ? $data['clientNumber'] : null;
                    $update->installmentDate = isset($data['installmentDate']) ? Carbon::parse($data['installmentDate'])->format('Y-m-d') : null;
                    $update->contractNumber = isset($data['contractNumber']) ? $data['contractNumber'] : null;
                    $update->contractSequence = isset($data['contractSequence']) ? $data['contractSequence'] : null;
                    $update->instSeq = isset($data['instSeq']) ? $data['instSeq'] : null;
                    $update->installmentAmount = isset($data['installmentAmount']) ? $data['installmentAmount'] : null;
                    $update->totalAmount = isset($data['totalAmount']) ? $data['totalAmount'] : null;
                    $update->collectedAmount = isset($data['collectedAmount']) ? $data['collectedAmount'] : null;
                    $update->currentCycleHits = isset($data['currentCycleHits']) ? $data['currentCycleHits'] : null;
                    $update->hitsAllowed = isset($data['hitsAllowed']) ? $data['hitsAllowed'] : null;
                    $update->tracking = isset($data['tracking']) ? $data['tracking'] : null;
                    $update->reportStatus = isset($data['reportStatus']) ? $data['reportStatus'] : null;
                    $update->currentStatus = isset($data['currentStatus']) ? $data['currentStatus'] : null;
                    $update->result = isset($data['result']) ? $data['result'] : null;
                    $update->clientBank = isset($data['clientBank']) ? $data['clientBank'] : null;
                    $update->save();

                } else {
                    $add = new RealpayTransactionToAddInTxLog();
                    $add->clientName = isset($data['clientName']) ? $data['clientName'] : null;
                    $add->merchant = isset($data['merchant']) ? $data['merchant'] : null;
                    $add->clientNumber = isset($data['clientNumber']) ? $data['clientNumber'] : null;
                    $add->installmentDate = isset($data['installmentDate']) ? Carbon::parse($data['installmentDate'])->format('Y-m-d') : null;
                    $add->contractNumber = isset($data['contractNumber']) ? $data['contractNumber'] : null;
                    $add->contractSequence = isset($data['contractSequence']) ? $data['contractSequence'] : null;
                    $add->instSeq = isset($data['instSeq']) ? $data['instSeq'] : null;
                    $add->installmentAmount = isset($data['installmentAmount']) ? $data['installmentAmount'] : null;
                    $add->totalAmount = isset($data['totalAmount']) ? $data['totalAmount'] : null;
                    $add->collectedAmount = isset($data['collectedAmount']) ? $data['collectedAmount'] : null;
                    $add->currentCycleHits = isset($data['currentCycleHits']) ? $data['currentCycleHits'] : null;
                    $add->hitsAllowed = isset($data['hitsAllowed']) ? $data['hitsAllowed'] : null;
                    $add->tracking = isset($data['tracking']) ? $data['tracking'] : null;
                    $add->reportStatus = isset($data['reportStatus']) ? $data['reportStatus'] : null;
                    $add->currentStatus = isset($data['currentStatus']) ? $data['currentStatus'] : null;
                    $add->result = isset($data['result']) ? $data['result'] : null;
                    $add->clientBank = isset($data['clientBank']) ? $data['clientBank'] : null;
                    $add->save();
                }

                $update = RealpayTransactionDummyData::where('id',$request->id)->first();
                if (isset($update)) {
                    $update->add_transaction = 1;
                    $update->save();
                }
                return response()->json(['status' => 'success', 'message' => 'Record added successfully.'], 200);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Record not found.'], 401);

            }
        } catch (\Exception $ex) {
            return response()->json(['status' => 'error', 'message' => $ex->getMessage()], 401);
        }
    }

    public function getTransFormview(Request $request)
    {
        $data = DPOTransactionDummyData::where('status','!=','Cancelled')->orderBy('id','desc')->get();

        if ($request->status_filter != -1) {
            $data = DPOTransactionDummyData::where('status', $request->status_filter)->orderBy('id','desc')->get();
        }

        return DataTables::of($data)

        ->addColumn('policy_number', function ($data) {
            $policy_number = 'N/A';
            if (isset($data->bookingref)) {
                if(str_contains($data->bookingref, '/')){
                    $policy_number = strtok($data->bookingref, '/');
                } else {
                    $policy_number = $data->bookingref;
                }
            }

            return  $policy_number;
        })

        ->editColumn('reason', function ($data) {
            $reason = 'N/A';
            if (isset($data->reason) && $data->reason != '') {
                $reason = $data->reason;
            }

            return $reason;

        })

        ->editColumn('graphite_amount', function ($data) {
            $graphite_amount = 'N/A';
            if (isset($data->graphite_amount) && $data->graphite_amount != '') {
                $graphite_amount = $data->graphite_amount;
            }

            return $graphite_amount;

        })

        ->editColumn('graphite_date', function ($data) {
            $graphite_date = 'N/A';
            if (isset($data->graphite_date) && $data->graphite_date != '') {
                $graphite_date = $data->graphite_date;
            }

            return $graphite_date;

        })

        ->editColumn('graphite_cust_email', function ($data) {
            $graphite_cust_email = 'N/A';
            if (isset($data->graphite_cust_email) && $data->graphite_cust_email != '') {
                $graphite_cust_email = $data->graphite_cust_email;
            }

            return $graphite_cust_email;

        })

        ->editColumn('mnoname', function ($data) {
            $mnoname = 'N/A';
            if (isset($data->mnoname) && $data->mnoname != '') {
                $mnoname = $data->mnoname;
            }

            return $mnoname;

        })

        ->editColumn('cardholder', function ($data) {
            $cardholder = 'N/A';
            if (isset($data->cardholder) && $data->cardholder != '') {
                $cardholder = $data->cardholder;
            }

            return $cardholder;

        })

        ->editColumn('finalcurrency', function ($data) {
            $finalcurrency = 'N/A';
            if (isset($data->finalcurrency) && $data->finalcurrency != '') {
                $finalcurrency = $data->finalcurrency;
            }

            return $finalcurrency;

        })

        ->editColumn('mcc', function ($data) {
            $mcc = 'N/A';
            if (isset($data->mcc) && $data->mcc != '') {
                $mcc = $data->mcc;
            }

            return $mcc;

        })

        ->editColumn('netamount', function ($data) {
            $netamount = 'N/A';
            if (isset($data->netamount) && $data->netamount != '') {
                $netamount = $data->netamount;
            }

            return $netamount;

        })

        ->editColumn('grossamountusd', function ($data) {
            $grossamountusd = 'N/A';
            if (isset($data->grossamountusd) && $data->grossamountusd != '') {
                $grossamountusd = $data->grossamountusd;
            }

            return $grossamountusd;

        })

        // ->editColumn('infostatus', function ($data) {
        //     $infostatus = "Not Found";

        //     $paymenttrx = PaymentTransaction::where('referenceNumber',$data->token)
        //                ->first(['status','amount','policyNumber']);
        //     if (isset($paymenttrx)) {
        //         $infostatus = "Found";
        //     }

        //     return $infostatus;

        // })

        ->addColumn('actions', function ($data) {
            $actions = '';
            // $paymenttrx = PaymentTransaction::where('referenceNumber',$data->token)
            //            ->first(['status','amount','policyNumber']);
            // $idValue = "addTransaction".$data->id;
            if (isset($data->add_transaction) && $data->add_transaction == 1) {
                $actions = '<input id="addTransaction'. $data->id .'" type="checkbox" name="addTransaction" value="'. $data->id .'" onchange="addTransactionInTable(this)" checked>';
            } else {
                $actions = '<input id="addTransaction'. $data->id .'" type="checkbox" name="addTransaction" value="'. $data->id .'" onchange="addTransactionInTable(this)">';
            }

            return  $actions;
        })
        ->rawColumns(['policy_number','actions'])
        ->make(true);

    }


    public function getTransFormviewRealpay(Request $request)
    {
        $data = RealpayTransactionDummyData::where('currentStatus','!=','CANCELLED')->orderBy('id','desc')->get();

        if ($request->status_filter != -1) {
            $data = RealpayTransactionDummyData::where('currentStatus', $request->status_filter)->orderBy('id','desc')->get();
        }

        return DataTables::of($data)


        ->addColumn('actions', function ($data) {
            $actions = '';
            // $paymenttrx = PaymentTransaction::where('referenceNumber',$data->token)
            //            ->first(['status','amount','policyNumber']);
            // $idValue = "addTransaction".$data->id;
            if (isset($data->add_transaction) && $data->add_transaction == 1) {
                $actions = '<input id="addTransaction'. $data->id .'" type="checkbox" name="addTransaction" value="'. $data->id .'" onchange="addRealpayTransactionInTable(this)" checked>';
            } else {
                $actions = '<input id="addTransaction'. $data->id .'" type="checkbox" name="addTransaction" value="'. $data->id .'" onchange="addRealpayTransactionInTable(this)">';
            }

            return  $actions;
        })
        ->rawColumns(['actions'])
        ->make(true);

    }

    public function penddingReccuring(Request $request)
    {
        return view('/admin/penddingReccuringUploadCSV');
    }
    public function penddingReccuringUploadCSV(Request $request)
    {
        if (auth::user()->hasPermissionTo('pendingRecurringUploadCSV')) {
        //dd($request->all());
        if($request->file('penddingReccuring')){
            ini_set('max_execution_time', '300');
            //Get File
           $upload     = $request->file('penddingReccuring');

           $file_path  = $upload->getRealPath();

           //OPen file and read file
           $file           = fopen($file_path,'r');

           $header         = fgetcsv($file);
          // dd($header);
           $escapeheader   = [];

           foreach($header as $key=>$value){
               $lowerHeader    = strtolower($value);
               $escapedItems   = preg_replace("/[^a-z]/", "", $lowerHeader);
               $noSpaceString  = preg_replace("/\s+/", "", $escapedItems);
               array_push($escapeheader,$noSpaceString);
           }


           while ($columns = fgetcsv($file)) {
               if($columns[0] ==''){
                   continue;
               }
              $data[] = array_combine($escapeheader,$columns);

       }

        //dd($data);
        foreach ($data as $key => $data) {

            $add = new PendingReccuringDPO();
            $add->id = isset($data['id']) ? $data['id'] : null;
            $add->policy_id = isset($data['policyid']) ? $data['policyid'] : null;
            $add->policy_number = isset($data['policynumber']) ? $data['policynumber'] : null;
            $add->billing_date = isset($data['billingdate']) ? Carbon::parse($data['billingdate'])->format('Y-m-d') : null;
            $add->customer_id = isset($data['customerid']) ? $data['customerid'] : null;
            $add->installment = isset($data['installment']) ? $data['installment'] : null;
            $add->retry_count = isset($data['retrycount']) ? $data['retrycount'] : null;
            $add->premium = isset($data['premium']) ? $data['premium'] : null;
            $add->email = isset($data['email']) ? $data['email'] : null;
            $add->city = isset($data['city']) ? $data['city'] : null;
            $add->token = isset($data['token']) ? $data['token'] : null;
            $add->subscription_token = isset($data['subscriptiontoken']) ? $data['subscriptiontoken'] : null;
            $add->customer_token = isset($data['customertoken']) ? $data['customertoken'] : null;
            $add->payment_method = isset($data['paymentmethod']) ? $data['paymentmethod'] : null;
            $add->reason = isset($data['reason']) ? $data['reason'] : null;
            $add->status = isset($data['status']) ? $data['status'] : null;
            $add->is_custome = isset($data['iscustom']) ? $data['iscustom'] : null;
            $add->processed_by = isset($data['processedby']) ? $data['processedby'] : null;
            $add->added_by = isset($data['addedby']) ? $data['addedby'] : null;
            $add->createdat = isset($data['createdat']) ? Carbon::parse($data['createdat'])->format('Y-m-d H:i:s') : null;
            $add->updatedat = isset($data['updatedat']) ? Carbon::parse($data['updatedat'])->format('Y-m-d H:i:s') : null;
            $add->save();
        }


        return Redirect()->back()->with('success',"Record added successfully.");

        }else{

            return Redirect()->back()->with('error',"Failed To Add");
        }

       }else{
        return Redirect:: back()->with('error', 'Sorry! You do not have permission to access this page!');
    }


    }
    public function getPendingRecurringData(Request $request)
    {
        $data = PendingReccuringDPO::all();



        return DataTables::of($data)

        ->editColumn('status', function ($data) {
            if ($data->status == 0 ) {
                $return = '<span class="kt-font-bold text-info">Payment not initiated</span>';
            }elseif ($data->status == 1) {
                $return = '<span class="kt-font-bold text-primary">Payment initiated</span>';
            }elseif ($data->status == 2 ) {
                $return = '<span class="kt-font-bold text-success">Payment Success</span>';
            }elseif ($data->status == 3 ) {
                $return = '<span class="kt-font-bold text-warning">Payment Failed</span>';
            }elseif ($data->status == 4 ) {
                $return = '<span class="kt-font-bold text-danger">Canceled</span>';
            }else {
                $return = '<span class="kt-font-bold text-muted">-</span>';
            }

            return $return;
        })
        ->rawColumns(['status'])
        ->make(true);

    }
    public function GenerateReccuringToken(Request $request)
    {
        if (auth::user()->hasPermissionTo('pendingRecurringPay')) {


              Artisan::call('policy:pendingRecurringTokenCreate');


            return Redirect()->back()->with('success',"Generate Reccuring Token successfully.");
          } else {
              return Redirect:: back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }
    public function pendingRecurringdatadelete(Request $request)
    {
        if (auth::user()->hasPermissionTo('pendingRecurringPay')) {


          $data =   PendingReccuringDPO::all();
           foreach($data as $x){
            $x->delete();
           }

            return Redirect()->back()->with('success',"Pending Reccuring DPO Data Delete successfully.");
          } else {
              return Redirect:: back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }


    public function vcsTransDataStore(Request $request)
    {
        try {
            $data = VcsTransactionDummyData::where('id',$request->id)->first();

            if (isset($data)) {
                $add = VcsTransactionToAddInTxLog::updateOrCreate([
                    'reference' => isset($data['reference']) ? $data['reference'] : null,
                ], [
                    "reference" => isset($data['reference']) ? $data['reference'] : null,
                    "originalreference" => isset($data['originalreference']) ? $data['originalreference'] : null,
                    "name" => isset($data['name']) ? $data['name'] : null,
                    "goods" => isset($data['goods']) ? $data['goods'] : null,
                    "amount" => isset($data['amount']) ? $data['amount'] : null,
                    "bp" => isset($data['bp']) ? $data['bp'] : null,
                    "code" => isset($data['code']) ? $data['code'] : null,
                    "response" => isset($data['response']) ? $data['response'] : null,
                    "settlementdate" => isset($data['settlementdate']) ? Carbon::parse($data['settlementdate'])->format('Y-m-d') : null,
                    "settlementreference" => isset($data['settlementreference']) ? $data['settlementreference'] : null,
                    "interface" => isset($data['interface']) ? $data['interface'] : null,
                    "status" => isset($data['status']) ? $data['status'] : null,
                ]);

                $update = VcsTransactionDummyData::where('id',$request->id)->first();
                if (isset($update)) {
                    $update->add_transaction = 1;
                    $update->save();
                }
                return response()->json(['status' => 'success', 'message' => 'Record added successfully.'], 200);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Record not found.'], 401);

            }
        } catch (\Exception $ex) {
            return response()->json(['status' => 'error', 'message' => $ex->getMessage()], 401);
        }
    }

    public function getTransFormviewVcs(Request $request)
    {
        $data = VcsTransactionDummyData::orderBy('id','desc')->get();

        // if ($request->status_filter != -1) {
        //     $data = VcsTransactionDummyData::where('currentStatus', $request->status_filter)->orderBy('id','desc')->get();
        // }

        return DataTables::of($data)


        ->addColumn('actions', function ($data) {
            $actions = '';
            // $paymenttrx = PaymentTransaction::where('referenceNumber',$data->token)
            //            ->first(['status','amount','policyNumber']);
            // $idValue = "addTransaction".$data->id;
            if (isset($data->add_transaction) && $data->add_transaction == 1) {
                $actions = '<input id="addTransaction'. $data->id .'" type="checkbox" name="addTransaction" value="'. $data->id .'" onchange="addVcsTransactionInTable(this)" checked>';
            } else {
                $actions = '<input id="addTransaction'. $data->id .'" type="checkbox" name="addTransaction" value="'. $data->id .'" onchange="addVcsTransactionInTable(this)">';
            }

            return  $actions;
        })
        ->rawColumns(['actions'])
        ->make(true);

    }

}
