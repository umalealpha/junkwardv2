<?php

namespace AlphaDirect\Http\Controllers\admin;

use AlphaDirect\Config;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Requests\StoreKycCompliaceRequest;
use AlphaDirect\Http\Requests\UpdateKycCompliaceRequest;
use AlphaDirect\KycCompliance;
use AlphaDirect\KycFields;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use DataTables;
use User;
use Yajra\DataTables\Contracts\DataTable;
use Yajra\DataTables\Facades\DataTables as FacadesDataTables;

class KycComplianceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $mati_enable = Config::where('key','enable_mati')->first(array('id','value'));
            return view('admin.kycCompliance.index',compact('mati_enable'));
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }
    public function data()
    {
        $data = KycCompliance::orderBy('id', 'DESC')->get();
        return FacadesDataTables::of($data)
                ->addColumn('created_at', function ($data) {
                    if ($data->created_at != null) {
                        return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('Y-m-d H:i') ;
                    }
                })->editColumn('name', function($data){
                    if($data->name !=null)
                    {
                        return ucwords($data->name);
                    }
                })
            ->addColumn('actions', function ($data) {
                $actions = '';
                if (Auth::user()->hasPermissionTo('kyc-compliance-edit'))
                {
                    $actions .= '<a href="' . route('admin.kycCompliance.edit', $data->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                        <i class="la la-edit"></i>
                    </a>';
                }

                if (Auth::user()->hasPermissionTo('kyc-compliance-delete'))
                {
                $actions .= '<a href="" value="'.$data->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                <i class="la la-trash"></i>
                </a>';
                }
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
             $kyc_fields = KycFields::get();
            return view('admin.kycCompliance.create', compact('kyc_fields'));
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
    public function store(StoreKycCompliaceRequest $request)
    {
        try {
            $kycCompliance = new KycCompliance();
            $kycCompliance->fields = json_encode($request->data, true);
            $kycCompliance->name = $request->compliance_name;
            $kycCompliance->flow_id = $request->flow_id;
            $kycCompliance->save();
            return Redirect::route('admin.kycCompliance.index')->with('success', 'KYC Compliance Created Successfully');
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function enableMati(Request $request)
    {
        try {
            $Mati = Config::where('key','enable_mati')->first(array('id','value'));
            if ($request->enable_mati != null) {
                $matiValue = $request->enable_mati;
            }else{
                $matiValue = 0;
            }

            $Mati->value = $matiValue;
            $Mati->save();

            return Redirect::route('admin.kycCompliance.index')->with('success', 'Successfully Done');
        } catch (Exception $e) {
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
            $kyc_others    = KycFields::get();
            $kycCompliance = KycCompliance::where('id', $id)->orderBy('id', 'DESC')->first();
            return view('admin.kycCompliance.edit',compact('kycCompliance','kyc_others'));
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
    public function update(UpdateKycCompliaceRequest $request,$id)
    {
        try {
            $center                       = KycCompliance::where('id',$id)->first();
            $center->fields               = json_encode($request->data, true);
            $center->name                 = $request->compliance_name;
            $center->flow_id              = $request->flow_id;
            $center->save();

            return Redirect::route('admin.kycCompliance.index')->with('success', 'KYC Compliance Updated Successfully');
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }


    public function getModalDelete(Request $request)
    {
        $check = KycCompliance::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves

        $body = 'Are you sure you want to delete the KYC Compliance ?';
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
        $kycCompliance = KycCompliance::where('id',$id)->delete();
        return Redirect::route('admin.kycCompliance.index')->with('success', 'KYC Compliance Deleted Successfully');
    }
}
