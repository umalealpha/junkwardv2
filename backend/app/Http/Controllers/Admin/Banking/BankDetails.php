<?php

namespace AlphaDirect\Http\Controllers\Admin\Banking;

use AlphaDirect\FinancialInterest;
use Illuminate\Http\Request;
use AlphaDirect\Banks;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use AlphaDirect\User;
use Redirect;

use AlphaDirect\Http\Controllers\Controller;

class BankDetails extends Controller
{
    /*
    * method returns listing page of all banks
    */
    public function viewAllBanks(){

        return view('Admin/Banking/bankDetail');
    }

    /*
    * method returns listing page of all clients
    */
    public function viewAllClients(){

        return view('Admin/Banking/clientDetails');
    }

    /*
    * stores banks information from create bank page
    * bank listing page
    */
    public function storeBanks(Request $request){

        //save active Banks
        $bank = new Banks;
        $bank->bank_number = $request->bankNum;
        $bank->save();

        response()->json(['Banks Succesfully Added'],200);
        Session::flash('bankSaved', 'Bank Saved');
        return Redirect::back();

    }

    /*
    * show edit page to update client data
     * return : client edit page
     * param: client id
    */
    public function editClients(){

        return view('Admin/Banking/editClient');
    }

    public function financialInterest()
    {
        return view('admin/banking/financial_interest');
    }

    public function data()
    {
        $banks = FinancialInterest::get(array('id','bank_name', 'emails'));
        return DataTables::of($banks)
            ->addColumn('actions',function($banks) {
                $actions = '';
                if(Auth::user()->can('account-edit')){
                    $actions .= '<a href="'. route('admin.financialInterest.edit', $banks->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }else{
                    $actions .= '<a href="'. route('admin.financialInterest.edit', $banks->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if(Auth::user()->can('account-delete')) {
                    $actions .= '<a href="" value="' . $banks->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function financialInterestCreate(){

        if(Auth::user()->hasPermissionTo('account-create')){
            return view('admin.banking.create');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    public function financialInterestStore(Request $request)
    {
        $banks = new FinancialInterest();
        $banks->bank_name = htmlspecialchars(strip_tags($request->input('bank_name', '')));
        $emails = explode(',',$request->emails);
        $new = array();
        $invCount = 0;
        $valid = 0;
        if(count($emails) == 0)
            return redirect()->back()->with('error', 'Emails can not be empty');

        foreach($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid += 1;
                array_push($new,$email);
            } else {
                $invCount += 1;
            }
        }

        $im = implode(',',$new);
        $banks->emails = $im;

        $banks->getChanges();
        if($banks->save()) {
            activity('Banks')
                ->performedOn($banks)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Financial Interest Emails Updated');
            if ($invCount > 0 && $valid > 0) {
                return Redirect::route('admin.financialInterest')->with('success', 'Emails to Financial Interest added successfully but failed ' . $invCount . ' failed as these are not valid');
            } elseif ($invCount == 0 && $valid > 0) {
                return Redirect::route('admin.financialInterest')->with('success', 'EMails to Financial Interest added successfully');
            } elseif ($invCount > 0 && $valid == 0) {
                return Redirect::route('admin.financialInterest')->with('error', 'Invalid emails');
            } else {
                return Redirect::route('admin.financialInterest')->with('success', 'Financial Interest added successfully');
            }
        }else{
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    public function financialInterestEdit($id){


        if(Auth::user()->hasPermissionTo('account-edit')){
            $bank = FinancialInterest::get(array('id','bank_name'));
            $banks = FinancialInterest::where('id',$id)->first();
            return view('admin.banking.edit',compact('banks','bank'));
        }
        // elseif(Auth::user()->hasPermissionTo('account-list')){
        //     $banks = Banks::where('id',$id)->first();
        //     return view('admin.accounts.view',compact('banks'));
        // }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }
    public function financialInterestUpdate(Request $request,$id  )
    {
        $banks = FinancialInterest::where('id', $id)->first();
        $banks->bank_name = htmlspecialchars(strip_tags($request->input('bank_name', '')));
        $emails = explode(',',$request->emails);
        $new = array();
        $invCount = 0;
        $valid = 0;
        if(count($emails) == 0)
            return redirect()->back()->with('error', 'Emails can not be empty');

        foreach($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) == true && preg_match('/[\'^£$%&*()}{#~?><>,|=_+¬-]/', $email) == 0) {
                $valid += 1;
                array_push($new,$email);
            } else {
                $invCount += 1;
            }
        }

        $im = implode(',',$new);
        $banks->emails = $im;

        $banks->getChanges();
        if($banks->save()) {
            activity('Banks')
                ->performedOn($banks)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Financial Interest Emails Updated');
            if ($invCount > 0 && $valid > 0) {
                return Redirect::route('admin.financialInterest')->with('success', 'Emails to Financial Interest added successfully but failed ' . $invCount . ' failed as these are not valid');
            } elseif ($invCount == 0 && $valid > 0) {
                return Redirect::route('admin.financialInterest')->with('success', 'EMails to Financial Interest added successfully');
            } elseif ($invCount > 0 && $valid == 0) {
                return Redirect::route('admin.financialInterest')->with('error', 'Invalid emails');
            } else {
                return Redirect::route('admin.financialInterest')->with('success', 'Financial Interest added successfully');
            }
        }else{
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    //            $find1 = strpos($email, '@');
//            $find2 = strpos($email, '.');
//            $r = ($find1 !== false && $find2 !== false && $find2 > $find1 && ($find1 != 0 && $find2 != 0));

    public function getModalDelete(Request $request)
    {

            $body = 'Are you sure you want to delete the Financial Interest ?';
            return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);

    }

    public function financialInterestDelete($id){
        try{

            $banks = FinancialInterest::where('id',$id)->delete();

            return Redirect::route('admin.financialInterest')->with('success', 'Financial Interest Deleted Successfully');

        }catch(Exception $e){
            return Redirect::route('admin.financialInterest')->with('error', 'Something Went Wrong');
        }

    }


}
