<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Admin\CustomerFeedbackOption;
use AlphaDirect\Admin\CustomerFeedbackSubOption;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CustomerFeedbackSubOptionController extends Controller
{
    public function data()
    {
        $suboptions = CustomerFeedbackSubOption::with('option')->where('status', 1)->orderBy('id', 'desc')->get();
        if($suboptions != null)
        {
            return response()->json(['suboptions' => $suboptions], 200);
        }else{
            return response()->json(['message' => 'Sub-Options not found.'], 419);
        }
    }

    public function tableData()
    {
        $options = CustomerFeedbackSubOption::with('option')->orderBy('id', 'desc')->get();
        return DataTables::of($options)
            ->editColumn('created_at', function ($option)
            {
                return $option->created_at->diffForHumans();
            })
            ->editColumn('option', function ($option)
            {
                return $option->option->name;
            })            
            ->addColumn('status', function ($option)
            {
                if($option->status == 1)
                    $status = '<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Active</span>';
                else
                    $status = '<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">In-active</span>';
                
                return $status;
            })
            ->addColumn('actions', function ($option)
            {
                $actions = '';
                $actions .= '<a href="' . route('customer-feedback.suboptions.edit', $option->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="la la-edit"></i>
                                </a>';
                                
                $actions .= '<a class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete" data-id='.$option->id.' data-name="'.$option->name.'">
                                <i class="la la-trash"></i>
                            </a>';

                return $actions;
            })
            ->rawColumns(['actions', 'status', 'option'])
            ->make(true);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.customerSubOptions.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = CustomerFeedbackOption::getOptions('status',1);
        if($data['Response'] == "success"){
            $options = $data['options'];
        }else{
            return redirect()->back()->with('error', $data['Message']);
        }
        return view('admin.customerSubOptions.create')->with('options', $options);
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
            'name'      => "required|min:3|max:300|regex:/^[A-Za-z0-9. _~\-!@#\$%\^&\*\(\)]+$/|unique:customer_feedback_sub_options,name",
            'option_id' => 'required|min:1|integer',
            'status'    => 'sometimes|required|in:0,1,on'
        ]);
            DB::beginTransaction();
        try{
            $option = CustomerFeedbackSubOption::create([
                'name'      => $request->name,
                'option_id' => $request->option_id,
                'status'    => isset($request->status) ? 1 : 0,
            ]);
            DB::commit();
            return redirect()->route('customer-feedback.suboptions.index')->with('success', 'Sub-option "'. $option->name .'" added successfully');
        }catch(\Exception $e){
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to add sub-option. Please try again');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \AlphaDirect\Admin\CustomerFeedbackSubOption  $customerFeedbackSubOption
     * @return \Illuminate\Http\Response
     */
    public function show(CustomerFeedbackSubOption $customerFeedbackSubOption)
    {
        return view('admin.customerOptions.create')->with('customerFeedbackSubOption', $customerFeedbackSubOption);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \AlphaDirect\Admin\CustomerFeedbackSubOption  $customerFeedbackSubOption
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        $data = CustomerFeedbackOption::getOptions('status',1);
        if($data['Response'] == "success"){
            $options = $data['options'];
        }else{
            return redirect()->back()->with('error', $data['Message']);
        }
        $customerFeedbackSubOption = CustomerFeedbackSubOption::with('option')->findOrFail($id); 
        return view('admin.customerSubOptions.create')->with(['customerFeedbackSubOption'=> $customerFeedbackSubOption ,
                                                              'options' => $options
                                                            ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \AlphaDirect\Admin\CustomerFeedbackSubOption  $customerFeedbackSubOption
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $customerFeedbackSubOption = CustomerFeedbackSubOption::findOrFail($id);

        $request->validate([
            'name'      => 'required|min:3|max:300|regex:/^[A-Za-z0-9-_ ]+$/|unique:customer_feedback_sub_options,name,'. $customerFeedbackSubOption->id,
            'option_id' => 'required|min:1|integer',
            'status'    => 'sometimes|required|in:0,1,on'
        ]);

            DB::beginTransaction();
        try{
            $customerFeedbackSubOption->update([
                'name'      => $request->name,
                'option_id' => $request->option_id,
                'status'    => isset($request->status) ? 1 : 0,
            ]);
            DB::commit();
            return redirect()->route('customer-feedback.suboptions.index')->with('success', 'Option "'. $customerFeedbackSubOption->name .'" edited successfully.');
        }catch(\Exception $e){ 
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to add aption. Please try again');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \AlphaDirect\Admin\CustomerFeedbackSubOption  $customerFeedbackSubOption
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try{            
            $customerFeedbackSubOption = CustomerFeedbackSubOption::findOrFail($id);
            $customerFeedbackSubOption->delete();
            return redirect()->route('customer-feedback.suboptions.index')->with('success', 'Option deleted.');
        }catch(\Exception $ex){
            DB::rollback();
            return redirect()->route('customer-feedback.suboptions.index')->with('error', 'Failed. Please try again');
        }
    }
}
