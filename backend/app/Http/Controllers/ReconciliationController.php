<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\Transaction;
use AlphaDirect\Policy;
use AlphaDirect\VcsReportTemp;
use AlphaDirect\VcsModel;
use DB;
use Auth;
use Yajra\DataTables\DataTables;


class ReconciliationController extends Controller
{
    //

    public function index()
    {
        return view('admin/reconciliation/index');
    }


    public function data()
    {
        try {
            $customerData = DB::table('vcs_data_dump')
                ->join('transactions', 'vcs_data_dump.actualReferenceNumber', '=', 'transactions.referenceNumber')
                ->leftjoin('policies', 'transactions.policyNumber', '=', 'policies.policyNumber')
                ->leftjoin('customer', 'policies.customer_id', '=', 'customer.id')
                ->select('vcs_data_dump.ReferenceNumber',
                    'vcs_data_dump.BankResponse',
                    'vcs_data_dump.TransactionSettled',
                    'vcs_data_dump.Amount',
                    'vcs_data_dump.DescriptionOfGoods',
                    'policies.policyNumber',
                    'customer.firstName',
                    'customer.lastName',
                    'customer.cellphone')
                ->get();
            return DataTables::of($customerData)
            ->editColumn('TransactionSettled', function ($customerData) {
                if ($customerData->TransactionSettled != null) {
                    return   \Carbon\Carbon::parse($customerData->TransactionSettled)->format('Y-m-d H:i') ;
                }
            })
                ->addColumn('checkBox', function ($customerData) {
                    if($customerData){
                        if($customerData->firstName)
                        {
                            return '<input type="checkbox" class="kt-checkbox kt-checkable" data-policyid="' . $customerData->firstName . '" name="policy_id" value="' . $customerData->firstName . '"></br>';
                        }
                        else{
                            return '<input type="checkbox" class="kt-checkbox kt-checkable" data-policyid="" name="policy_id" value=""></br>';
                        }
                    }
                })
                ->addColumn('customerName', function ($customerData) {

                    if($customerData)
                    {
                        if ($customerData->firstName != NULL && $customerData->lastName != NULL)
                        {
                            $customerName = ucwords($customerData->firstName) . ' ' . ucwords($customerData->lastName);
                            return $customerName;
                        }else{
                            return '--';
                        }
                    }

                })
                ->addColumn('actions', function ($customerData) {
                    $actions = '';
                    $actions .= '<a id ="" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>
                        <a class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-eye"></i>
                            </a>';
                    return $actions;
                })
                ->rawColumns(['checkBox', 'actions'])
                ->make(true);

        } catch (Exception $ex) {
            response()->json($ex->getMessage());
        }
    }


    public function uploadCsv(Request $request)
    {
        if ($request->hasFile('vcsReport')) {
            $request->vcsReport->storeAs('vcsReport', 'report.csv');
            return redirect()->route('reconciliation.index')->with('message', 'Report Sucessfully added');
        } else {
            return view('admin/reconciliation/index')->with('message', 'No csv file was uploaded');
        }
    }


    public function executeNodeRequest()
    {
        $client = new \GuzzleHttp\Client();
        $url = "localhost:8083/VCS/v1/csvUpload";
        try {
            $apiRequest = $client->request('POST', $url);
            $response = $apiRequest->getBody()->getContents();
            return $response;
        } catch (RequestException $re) {
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

}
