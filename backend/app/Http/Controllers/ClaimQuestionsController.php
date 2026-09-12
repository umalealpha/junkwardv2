<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\ClaimQuestions;
use Auth;
use AlphaDirect\Coverage;
use AlphaDirect\Master;
use AlphaDirect\ClaimQuestionValues;
use Illuminate\Http\Request;
use AlphaDirect\RiskType;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;
use Http\Client\Exception;

class ClaimQuestionsController extends Controller
{
    //
    protected function index()
    {
        $claimquestions = ClaimQuestions::all();
        return view('admin/questions/claimQuestions/index', compact('claimquestions'));
    }

    protected function edit($id)
    {
        $claimquestion = ClaimQuestions::findOrFail($id);
        $coverages = Coverage::pluck('name', 'id')->all();
        $riskTypes = RiskType::pluck('name', 'id')->all();
        $lookup = Master::where('key', 'factor_main_type')->get(array('id', 'value'));
        $clValues = ClaimQuestionValues::where('claim_question_id', $id)->get();
        return view('admin/questions/claimQuestions/edit', compact('claimquestion', 'coverages', 'riskTypes', 'lookup', 'clValues'));
    }


    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
    public function data()
    {
        $claimQuestions = ClaimQuestions::get(array('id', 'risk_type', 'coverage', 'question', 'status'));
        return DataTables::of($claimQuestions)

            ->editColumn('risk_type', function ($claimQuestions) {
                $riskName = RiskType::where('id', $claimQuestions->risk_type)->first(array('name'));

                return $riskName ? $riskName->name : '';
            })

            ->editColumn('coverage', function ($claimQuestions) {
                $coverageName = Coverage::where('id', $claimQuestions->coverage)->first(array('name'));

                return $coverageName ? $coverageName->name : '';
            })

            ->editColumn('status', function ($claimQuestions) {
                if ($claimQuestions->status) {
                    return 'Active';
                } else {
                    return 'In-Active';
                }
            })

            ->addColumn('actions', function ($claimQuestions) {
                $actions = '<a href="' . route('policy.question.edit', $claimQuestions->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>
                            <a href="" value="' . $claimQuestions->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
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
        return view('admin/questions/claimQuestions/create', compact('coverages', 'riskTypes', 'lookup'));
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
            $claimQuestion = new ClaimQuestions();
            $claimQuestion->risk_type = $request->risk_type;
            $claimQuestion->coverage = $request->coverage;
            $claimQuestion->question = $request->question;
            $claimQuestion->response_type = $request->response_type;
            if ($request->status == null) {
                $claimQuestion->status = "0";
            } else {
                $claimQuestion->status = "1";
            }
            $claimQuestion->created_by = Auth::id();
            if ($claimQuestion->save()) {
                foreach ($request->factorProductValues as $key => $value) {
                    if ($value['factor_type_values'] != null) {
                        $clValues = new ClaimQuestionValues();
                        $clValues->claim_question_id = $claimQuestion->id;
                        $clValues->name = $value['factor_type_values'];
                        $clValues->factor = $value['factor_type_factor'];
                        $clValues->status = $request->status;
                        $clValues->save();
                    }
                }
                // Redirect to the home page with success menu
                return Redirect::route('claim.question.display')->with('success', 'Claim Question Created Successfully');
            } else {
                return Redirect::route('claim.question.display')->with('error', 'Something Went Wrong');
            }
        } catch (\Throwable $th) {
            throw $th;
            return Redirect::route('claim.question.display')->with('error', 'Something Went Wrong');
        }
    }

    protected function update(Request $request, $id)
    {

        $factor_type_values_names = $request->factor_type_values_names;
        $factor_type_values_factors = $request->factor_type_factor_names;
        $factorProductValues = $request->factorProductValues;
        $claimQuestion = ClaimQuestions::where('id', $id)->first();
        $claimQuestion->risk_type = $request->risk_type;
        $claimQuestion->coverage = $request->coverage;
        $claimQuestion->question = $request->question;
        $claimQuestion->response_type = $request->response_type;
        if ($factor_type_values_names != null) {
            foreach ($factor_type_values_names as $key => $values) {
                if (!empty($values)) {
                    $checkValues = ClaimQuestionValues::where('id', $key)->first();
                    $checkValues->name = $values;
                    $checkValues->save();
                }
            }
        }
        if ($factor_type_values_factors != null) {
            foreach ($factor_type_values_factors as $key => $values) {
                if (!empty($values)) {
                    $checkValues = ClaimQuestionValues::where('id', $key)->first();
                    $checkValues->factor = $values;
                    $checkValues->save();
                }
            }
        }
        if ($factorProductValues != null) {

            foreach ($factorProductValues as $key => $value) {
                if ($value['factor_type_values'] != null && $value['factor_type_factor']) {
                    $clValues = new ClaimQuestionValues();
                    $clValues->name = $value['factor_type_values'];
                    $clValues->factor = $value['factor_type_factor'];
                    $clValues->status = $request->status;
                    $clValues->save();
                }
            }
        }

        if ($request->status == null) {
            $claimQuestion->status = "0";
        } else {
            $claimQuestion->status = "1";
        }

        if ($claimQuestion->save()) {
            // Redirect to the home page with success menu

            return Redirect::route('claim.question.display')->with('success', 'Claim Question Updated Successfully');
        } else {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    public function factorValueDelete($id)
    {
        $factorValues = ClaimQuestionValues::find($id)->delete();
        return redirect()->back()->with('success', 'Claim Question Values Deleted Successfully');
    }

    public function getModalDelete(Request $request)
    {
        $body = "Are you sure you want to delete the question ? ";
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
    }


    protected function destroy($id)
    {
        try {

            ClaimQuestions::where('id', $id)->delete();

            return Redirect::route('claim.question.display')->with('success', 'Claim Question Deleted Successfully');
        } catch (Exception $e) {
            return Redirect::route('claim.question.display')->with('error', 'Something Went Wrong');
        }
    }
}
