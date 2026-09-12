<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Admin\CustomerFeedbackOption;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CustomerFeedbackOptionController extends Controller
{
    public function data()
    {       
        $options = CustomerFeedbackOption::getActiveSuboptions();                                   
        if($options != null)
        {
            return response()->json(['options' => $options], 200);
        }else{
            return response()->json(['message' => 'Options not found.'], 419);
        }
    }

    public function tableData()
    {
        $options = CustomerFeedbackOption::orderBy('id', 'desc')->get();
        return DataTables::of($options)
            ->editColumn('created_at', function ($option)
            {
                return $option->created_at->diffForHumans();
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
                $actions .= '<a href="' . route('customer-feedback.options.edit', $option->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="la la-edit"></i>
                                </a>';
                                
                $actions .= '<a class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete" data-id='.$option->id.' data-name="'.$option->name.'">
                                <i class="la la-trash"></i>
                            </a>';

                return $actions;
            })
            ->rawColumns(['actions', 'status', ])
            ->make(true);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.customerOptions.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.customerOptions.create');
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
            'name'        => 'required|min:3|max:300|regex:/^[A-Za-z0-9-_\' ]+$/|unique:customer_feedback_options,name',
            'description' => 'nullable|max:255',
            'input_type'  => 'required|in:2,1',
            'status'      => 'sometimes|required|in:0,1,on'
        ]);

            DB::beginTransaction();
        try{
            $option = CustomerFeedbackOption::create([
                'name'        => $request->name,
                'description' => $request->description,
                'input_type'  => $request->input_type,
                'status'      => isset($request->status) ? 1 : 0,
            ]);
            DB::commit();
            return redirect()->route('customer-feedback.options.index')->with('success', 'Option "'. $option->name .'" added successfully');
        }catch(\Exception $e){
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to add aption. Please try again');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \AlphaDirect\Admin\CustomerFeedbackOption  $customerFeedbackOption
     * @return \Illuminate\Http\Response
     */
    public function show(CustomerFeedbackOption $customerFeedbackOption)
    {
        return view('admin.customerOptions.create')->with('customerFeedbackOption', $customerFeedbackOption);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \AlphaDirect\Admin\CustomerFeedbackOption  $customerFeedbackOption
     * @return \Illuminate\Http\Response
     */
    public function edit(/* CustomerFeedbackOption $customerFeedbackOption */ $id)
    {
        
        $customerFeedbackOption = CustomerFeedbackOption::findOrFail($id);
        return view('admin.customerOptions.create')->with('customerFeedbackOption', $customerFeedbackOption);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \AlphaDirect\Admin\CustomerFeedbackOption  $customerFeedbackOption
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // dd($request->all());
        $customerFeedbackOption = CustomerFeedbackOption::findOrFail($id);

        $request->validate([
            'name'        => 'required|min:3|max:300|regex:/^[A-Za-z0-9-_ ]+$/|unique:customer_feedback_options,name,'. $customerFeedbackOption->id,
            'description' => 'nullable|max:255',
            'input_type'  => 'required|in:2,1',
            'status'      => 'sometimes|required|in:0,1,on'
        ]);

            DB::beginTransaction();
        try{
            $customerFeedbackOption->update([
                'name'        => $request->name,
                'description' => $request->description,
                'input_type'  => $request->input_type,
                'status'      => isset($request->status) ? 1 : 0,
            ]);
            DB::commit();
            return redirect()->route('customer-feedback.options.index')->with('success', 'Option "'. $customerFeedbackOption->name .'" edited successfully.');
        }catch(\Exception $e){ 
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to add aption. Please try again');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \AlphaDirect\Admin\CustomerFeedbackOption  $customerFeedbackOption
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try{            
            $customerFeedbackOption = CustomerFeedbackOption::with('suboptions')->findOrFail($id);
            $customerFeedbackOption->suboptions()->delete();
            $customerFeedbackOption->delete();
            return redirect()->route('customer-feedback.options.index')->with('success', 'Option deleted.');
        }catch(\Exception $ex){
            DB::rollback();
            return redirect()->route('customer-feedback.options.index')->with('error', 'Failed. Please try again');
        }
    }
}
