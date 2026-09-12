<?php

namespace AlphaDirect\Http\Controllers\admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\KycFields;
use Exception;
use Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\Facades\DataTables as FacadesDataTables;

use function GuzzleHttp\Psr7\str;

class KycFieldsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            return view('admin.kycFields.index');
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }


    public function data()
    {

        $data = KycFields::all();
        return FacadesDataTables::of($data)
            ->addColumn('created_at', function ($data) {
                if ($data->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('Y-m-d H:i') ;
                }
            })
            ->editColumn('name',function($data){
                  if($data->name != null)
                  {
                      return ucwords($data->name);
                  }
            })  
            ->addColumn('actions', function ($data) {
                $actions = '';

                    $actions .= '<a href="' . route('admin.KycFields.edit', $data->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                        <i class="la la-edit"></i>
                    </a>';

                $actions .= '<a href="" value="'.$data->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                <i class="la la-trash"></i>
                </a>';

                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function create()
    {
        try {

            $kyc_fields= new KYC();
            $table = $kyc_fields->getTable();
            $columns  = Schema::getColumnListing($table);

          // dd($column);
            return view('admin.kycFields.create',compact('columns'));
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'name'   => 'required|min:3|max:30|regex:/^[A-Za-z0-9-_ ]+$/|unique:kyc_fields,name',
        ]);

            DB::beginTransaction();
        try{
            Str::slug('slug');
            $kycFields = KycFields::create([
                'name'   => $request->name,
                'slug' => ($request->name),
                'customer_kyc_column'   => $request->customer_kyc_column
            ]);

            DB::commit();
            return Redirect::route('admin.KycFields.index')->with('success', 'KYC Fields "'. $kycFields->name .'"Created Successfully');
        }catch(\Exception $e){
            DB::rollback();
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        try {
            $kyc_fields= new KYC();
            $table = $kyc_fields->getTable();
            $columns  = Schema::getColumnListing($table);
            $kycFields = KycFields::where('id', $id)->orderBy('id', 'DESC')->first();
            return view('admin.kycFields.edit',compact('kycFields','columns'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $kyc_fields_update = KycFields::findOrFail($id);
        $request->validate([
            'name'   => 'required',
        ]);
            DB::beginTransaction();
        try{
            Str::slug('slug');
            $kyc_fields_update->update([
                'name'   => $request->name,
                'slug' => ($request->name),
                'customer_kyc_column'   => $request->customer_kyc_column
            ]);
            DB::commit();
            return Redirect::route('admin.KycFields.index')->with('success', 'KYC Fields "'. $kyc_fields_update->name .'"Updated Successfully');
        }catch(\Exception $e){
            DB::rollback();
            return Redirect::back()->with('error', $e->getMessage());
        }


        // try {
        //     $center = KycFields::where('id',$id)->first();
        //     $center->name = $request->name;
        //     $center->save();

        //     return Redirect::route('admin.kycFields.index')->with('success', 'KYC Fields Updated Successfully');
        // } catch (Exception $e) {
        //     return redirect()::back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        // }
    }

    public function getModalDelete(Request $request)
    {
        dd('Working');
        $check = KycFields::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves

        $body = 'Are you sure you want to delete the KYC Fields ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        $kycFields = KycFields::where('id',$id)->delete();
        return Redirect::route('admin.kycFields.index')->with('success', 'KYC Fields Deleted Successfully');
    }
}
