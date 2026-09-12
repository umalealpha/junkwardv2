<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Accounts;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class SubLedgerController extends Controller
{
    /**
     * Show a list of all subledger entries.
     *
     * @return subledger index
     */
    public function subLedgerIndex(){
        if(Auth::user()->hasPermissionTo('subledger-list')){
            return view('admin.subLedger.subLedger');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    public function show(){

    }

    /*
    * Pass data through ajax call for all subledger entries
    */
    /**
     * @return mixed
     */
    public function subLedgerData(Request $request ){
        $subledger= Ledger::get();
        return DataTables::of($subledger)

            ->addColumn('account_name',function($subledger) {
                if(!empty($subledger->account_id)){
                    $account = Accounts::where('id',$subledger->account_id)->first();
                    if(empty($account->account_name)){
                        $accountName = 'N/A';
                    }
                    else{
                        $accountName = $account->account_name;
                    }
                    if(empty($account->account_num)){
                        $accountNumber = 'N/A';
                    }else{
                        $accountNumber = $account->account_num;
                    }

                    return $accountName.' - '.$accountNumber;
                }
                else{
                    return  '-';
                }
            })
            ->editColumn('debit',function($subledger) {
                if($subledger->debit != null){
                    $cred='<span class="kt-font-bold kt-font-danger">P'.$subledger->debit.'</span>';
                    return  $cred;
                }
                else{
                    $cred='<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return  $cred;
                }
            })
            ->editColumn('credit',function($subledger) {
                if($subledger->credit != null){
                    $cred='<span class="kt-font-bold kt-font-success">P'.$subledger->credit.'</span>';
                    return  $cred;
                }
                else{
                    $cred='<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return  $cred;
                }
            })
            ->addColumn('trans_ref',function($subledger) {
                $policyNumber = Policy::where('id',$subledger->policy_id)->first(array('policyNumber'));
                if($policyNumber && $policyNumber->policyNumber != null){
                    return $policyNumber->policyNumber;
                }
                else{
                    return '-';
                }
            })

            ->rawColumns(['credit','debit','policy_id','account_name'])
            ->make(true);
    }
}
