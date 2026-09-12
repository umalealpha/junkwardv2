<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Coverage;
use AlphaDirect\Master;
use AlphaDirect\PolicyQuestions;
use AlphaDirect\PolicyQuestionValues;
use AlphaDirect\RiskType;
use Auth;
use Illuminate\Http\Request;
use Redirect;
use Yajra\DataTables\DataTables;

class PolicyQuestionsController extends Controller
{
    //
    protected function index()
    {
        $policyQuestions = PolicyQuestions::all();
        return view('admin/questions/policyQuestions/index', compact('policyQuestions'));
    }

    protected function edit($id)
    {
        //return edit page
        $policyQuestion = PolicyQuestions::find($id);
        $coverages = Coverage::pluck('name', 'id')->all();
        $riskTypes = RiskType::pluck('name', 'id')->all();
        $lookup = Master::where('key', 'factor_main_type')->get(array('id', 'value'));
        $pqValues = PolicyQuestionValues::where('policy_question_id', $id)->get();
        return view('admin/questions/policyQuestions/edit', compact('policyQuestion', 'coverages', 'riskTypes', 'lookup', 'pqValues'));
    }

    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
    public function data()
    {
        $policyQuestions = PolicyQuestions::get(array('id', 'risk_type', 'coverage', 'question', 'status'));
        return DataTables::of($policyQuestions)

            ->editColumn('risk_type', function ($policyQuestions) {
                $riskName = RiskType::where('id', $policyQuestions->risk_type)->first(array('name'));

                return $riskName ? $riskName->name : '';
            })

            ->editColumn('coverage', function ($policyQuestions) {
                $coverageName = Coverage::where('id', $policyQuestions->coverage)->first(array('name'));

                return $coverageName ? $coverageName->name : '';
            })

            ->editColumn('status', function ($policyQuestions) {
                if ($policyQuestions->status) {
                    return 'Active';
                } else {
                    return 'In-Active';
                }
            })

            ->addColumn('actions', function ($policyQuestions) {
                $actions = '<a href="' . route('policy.question.edit', $policyQuestions->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>
                            <a href="" value="' . $policyQuestions->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
    protected function create()
    {
        $coverages = Coverage::all();
        $riskTypes = RiskType::all();
        $lookup = Master::where('key', 'factor_main_type')->get(array('id', 'value'));
        return view('admin/questions/policyQuestions/create', compact('coverages', 'riskTypes', 'lookup'));
    }

    protected function show($id)
    {
        $policyQuestion = PolicyQuestions::findOrFail($id);
        return view('admin/questions/policyQuestions/show', compact('policyQuestion'));
    }

    protected function store(Request $request)
    {
        $this->validate($request, [
            'risk_type' => 'required',
            'coverage' => 'required',
            'question' => 'required',
            'response_type' => 'required',
        ]);
        try {
            //code..
            $policyQuestion = new PolicyQuestions();
            $policyQuestion->risk_type = $request->risk_type;
            $policyQuestion->coverage = $request->coverage;
            $policyQuestion->question = $request->question;
            $policyQuestion->response_type = $request->response_type;
            if ($request->status == null) {
                $policyQuestion->status = "0";
            } else {
                $policyQuestion->status = "1";
            }
            $policyQuestion->created_by = Auth::id();
            if ($policyQuestion->save()) {
                foreach ($request->factorProductValues as $key => $value) {
                    if ($value['factor_type_values'] != null) {
                        $pqValues = new PolicyQuestionValues();
                        $pqValues->policy_question_id = $policyQuestion->id;
                        $pqValues->name = $value['factor_type_values'];
                        $pqValues->factor = $value['factor_type_factor'];
                        $pqValues->status = $request->status;
                        $pqValues->save();
                    }
                }
                // Redirect to the home page with success menu
                return Redirect::route('policy.question.display')->with('success', 'Policy Question Created Successfully');
            } else {
                return Redirect::route('policy.question.display')->with('error', 'Something Went Wrong');
            }
        } catch (\Exception $ex) {
            return Redirect::route('policy.question.display')->with('error', $ex->getMessage());
        }
    }

    protected function update(Request $request, $id)
    {
        $factor_type_values_names = $request->factor_type_values_names;
        $factor_type_values_factors = $request->factor_type_factor_names;
        $factorProductValues = $request->factorProductValues;
        $policyQuestion = PolicyQuestions::where('id', $id)->first();
        $policyQuestion->risk_type = $request->risk_type;
        $policyQuestion->coverage = $request->coverage;
        $policyQuestion->question = $request->question;
        $policyQuestion->response_type = $request->response_type;
        if ($factor_type_values_names != null) {
            foreach ($factor_type_values_names as $key => $values) {
                if (!empty($values)) {
                    $checkValues = PolicyQuestionValues::where('id', $key)->first();
                    $checkValues->name = $values;
                    $checkValues->save();
                }
            }
        }
        if ($factor_type_values_factors != null) {
            foreach ($factor_type_values_factors as $key => $values) {
                if (!empty($values)) {
                    $checkValues = PolicyQuestionValues::where('id', $key)->first();
                    $checkValues->factor = $values;
                    $checkValues->save();
                }
            }
        }
        if ($factorProductValues != null) {

            foreach ($factorProductValues as $key => $value) {
                if ($value['factor_type_values'] != null && $value['factor_type_factor']) {
                    $pqValues = new PolicyQuestionValues();
                    $pqValues->policy_question_id = $policyQuestion->id;
                    $pqValues->name = $value['factor_type_values'];
                    $pqValues->factor = $value['factor_type_factor'];
                    $pqValues->status = $request->status;
                    $pqValues->save();
                }
            }
        }

        if ($request->status == null) {
            $policyQuestion->status = "0";
        } else {
            $policyQuestion->status = "1";
        }

        if ($policyQuestion->save()) {
            // Redirect to the home page with success menu

            return Redirect::route('policy.question.display')->with('success', 'Policy Question Updated Successfully');
        } else {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    public function factorValueDelete($id)
    {
        $factorValues = PolicyQuestionValues::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Policy Question Values Deleted Successfully');
    }

    public function getModalDelete(Request $request)
    {
        $body = "Are you sure you want to delete the question ? ";
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
    }

    protected function destroy($id)
    {
        try {

            PolicyQuestions::where('id', $id)->delete();

            return Redirect::route('policy.question.display')->with('success', 'Policy Question Deleted Successfully');
        } catch (Exception $e) {
            return Redirect::route('policy.question.display')->with('error', $e->getMessage());
        }
    }
}
