<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Agency;
use AlphaDirect\Http\Controllers\ConfigController;
use AlphaDirect\User;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;

class AgencyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.agency.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $config = new ConfigController();
        $states = $config->getStates();
        return view('admin.agency.create',compact('states'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $agecyData = $request->validate([
            'name' => 'required|max:255',
            'status' => 'max:1',
            'id'=>'nullable'
        ]);

        $agency = Agency::store($agecyData);

        return Redirect::route('admin.agency.index')->with($agency['Response'], $agency['Message']);
    }

    public function data()
    {
        $agency = Agency::orderBy('id','DESC')->get(array('id','name', 'status','created_at'));
        return DataTables::of($agency)
                 ->editColumn('name',function($agency){
                     if($agency->name != null)
                     {
                         return ucwords($agency->name);
                     }
                 })
         
                ->addColumn('created_at', function ($agency) {
                    if ($agency->created_at != null) {
                        return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $agency->created_at)->format('Y-m-d H:i') ;
                    }
                })
            ->addColumn('actions',function($agency) {
                $actions = '';
                if(Auth::user()->can('agency-edit')) {
                    $actions .= '<a href="' . route('admin.agency.edit', $agency->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-edit"></i>
                            </a>';
                }else{
                    $actions = '-';
                }

                return $actions;
            })
            ->addColumn('status',function($agency) {
                if($agency->status == 1){
                    return 'Active';
                }else{
                    return 'In-active';
                }
            })
            ->rawColumns(['actions','status'])
            ->make(true);
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
        $data = Agency::findOrFail($id);
        return view('admin.agency.edit', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
       // Guard the rename path: without this, an agency could be renamed onto
       // an existing broker's name and saved silently (the source of the
       // historical duplicates). Normalise first so whitespace variants collide
       // too; collation handles case. Kept outside the try below so a
       // ValidationException renders proper inline field errors, not the
       // generic catch message.
       $request->merge(['name' => Agency::normalizeName($request->name)]);
       $request->validate([
           'id'   => 'required|integer|exists:agencies,id',
           'name' => "required|max:255|unique:agencies,name,{$request->id}",
       ]);

       try{
           if(isset($request->status) && $request->status == 1)
               $request->status = 1;
           else
               $request->status = 0;



           $agency = Agency::where('id',$request->id)->first();
           $agency->name = $request->name;
           $agency->status = $request->status;
           $agency->save();

           $user = User::where('agency_id',$agency->id)->get(array('id'));

           if($agency->status == 0 && count($user) > 0){
               foreach($user as $key=>$us) {
                   $user = User::where('id',$us->id)->first();
                   $user->active = 2;
                   $user->save();
               }
           }

           return Redirect::back()->with('success', 'Data updated successfully');
       }catch(\Exception $ex){
           return Redirect::back()->with('error', $ex->getMessage());
       }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $agency = Agency::findOrFail($id);
        $agency->delete();

        return redirect('admin/agency')->with('success', 'Agency deleted successfully');
    }
}
