<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Accounts;
use AlphaDirect\DiscountSurcharge;
use AlphaDirect\EditedPremiumQuote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\ReratedPremiumQuote;
use AlphaDirect\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Redirect;
use Yajra\DataTables\DataTables;

class ReratingController extends Controller
{
    public function index()
    {
        return view('admin.rerating.index');
    }

    public function data(){
        $data = DiscountSurcharge::get();
        return DataTables::of($data)
            ->addColumn('created_at', function ($data) {
                if ($data->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('Y-m-d H:i') ;
                }
            })
            ->addColumn('role', function ($data) {
               $roles = Role::where('id',$data->role)->first();
                if($roles && $roles->name){
                    return $roles->name;
                }else{
                    return 'N/A';
                }
            })
            ->editColumn('type', function($data){
                if($data->type == 1){
                    return 'Quote';
                }else if($data->type ==2)
                {
                    return 'Policy';
                }else if($data->type ==3){
                    return 'Policy Renewal';
                }else{
                    return '--';
                }
                return $data->type; 
            })
            ->addColumn('actions', function ($data) {
                $actions = '<a href="' . route('admin.re-rating.edit', $data->id) . '"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit value">
                                <i class="la la-edit"></i>
                            </a>';
                return $actions;
            })
            ->rawColumns(['role','actions'])
            ->make(true);
    }

    public function create(){
        try {
            $roles = Role::select('id','name')->get();
            return view('admin.rerating.create',compact('roles'));
        }catch(\Exception $ex){
            return Redirect::back()->with('error',$ex->getMessage());
        }
    }

    public function store(Request $request){
        try{
            DB::beginTransaction();
            $data = $request->all();
            
            $add = DiscountSurcharge::where('role',$data['role'])->where('type',$data['type'])->first();
            
            if($add == null)
                $add = new DiscountSurcharge();

            unset($data['_token']);
            foreach($data as $key=>$d){
                $add->$key = $d;
            }
            $add->save();

            DB::commit();
            return Redirect::route('admin.re-rating')->with('success','Discount/Surcharge entry added');
        }catch(\Exception $ex){
            DB::rollBack();
            return Redirect::back()->with('error',$ex->getMessage());
        }
    }

    public function edit($id){
        try{
            $roles = Role::select('id','name')->get();
            $data = DiscountSurcharge::where('id',$id)->first();
            return view('admin.rerating.edit',compact('roles','data'));
        }catch(\Exception $ex){
            return Redirect::back()->with('error',$ex->getMessage());
        }
    }

    public function update(Request $request,$id){
        try{
            DB::beginTransaction();
            $data = $request->all();
            $add = DiscountSurcharge::where('id',$id)->first();
            unset($data['_token']);
            foreach($data as $key=>$d){
                $add->$key = $d;
            }
            $add->save();

            DB::commit();
            return Redirect::route('admin.re-rating')->with('success','Discount/Surcharge entry updated');
        }catch(\Exception $ex){
            DB::rollBack();
            return Redirect::back()->with('error',$ex->getMessage());
        }
    }
}
