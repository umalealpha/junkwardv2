<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\AlphaDirect;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\ReviewAnswer;
use AlphaDirect\ReviewAnswerType;
use AlphaDirect\ReviewPages;
use AlphaDirect\ReviewQuestion;
use AlphaDirect\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Validator;
use Yajra\DataTables\Facades\DataTables;

class ReviewController extends Controller
{
    /**
     * Display a listing of the Review questions.
     *
     * @return review questions listings
     */
    public function index()
    {
        return view('admin.reviewQuestion.index');
    }

    /*
    * Pass data through ajax call
     * @return mixed
     */
    public function data()
    {

        $questions = ReviewQuestion::with(['answertype', 'reviewanswers', 'reviewpages'])->get();

        return DataTables::of($questions)
                ->addColumn('created_at', function ($questions) {
                    if ($questions->created_at != null) {
                        return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $questions->created_at)->format('Y-m-d H:i') ;
                    }
                })
            ->addColumn('answers', function ($questions) {
                if ($questions->reviewanswers != null) {
                    return $questions->reviewanswers;

                } else {
                    return '';
                }
            })

            ->editColumn('status', function ($questions) {
                if ($questions->status == 0) {
                    return '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
                } else {
                    return '<span class="kt-font-bold kt-font-brand">Activated</span>';
                }
            })
            ->addColumn('answer_type', function ($questions) {

                if ($questions->answertype !== null) {
                    return $answerType = $questions->answertype->type;
                } else {
                    return null;
                }

            })
            ->addColumn('actions', function ($questions) {
                $actions = '';

                $actions .= '<a href="' . route('admin.review.question.edit', $questions->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';

                $actions .= '<a href="" value="' . $questions->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';

                return $actions;
            })->rawColumns(['answers', 'actions', 'status', 'reviewpages'])
            ->make(true);

    }
    /**
     * Show a page to create new review question.
     *
     * @return View review question create page
     */
    public function createQuestion()
    {

        $reviewPages = ReviewPages::all();
        $answer_types = ReviewAnswerType::all();
        return view('admin.reviewQuestion.create', compact('reviewPages', 'answer_types'));
    }

    /**
     * Show a page to create new review answers.
     *
     * @return View review answer create page
     */
    public function createAnswer()
    {
        $questions = ReviewQuestion::with('reviewanswers')->get();

        return view('admin.reviewQuestion.reviewAnswer.create', compact('questions'));
    }

    /**
     * Show a page to edit specific review question.
     *review question id
     * @return View review question edit page
     */
    public function editQuestion($id)
    {
        $question = ReviewQuestion::with(['reviewanswers', 'reviewpages'])->findorFail($id);
        $answer_types = ReviewAnswerType::all();
        $reviewPages = ReviewPages::all();
        $answers = ReviewAnswer::all();
        return view('admin.reviewQuestion.edit', compact('question', 'reviewPages', 'answers', 'answer_types'));

    }

    /**
     * Show a page to edit specific review answer.
     *param: review answer id
     * @return View review answer edit page
     */
    public function editAnswer($id)
    {
        $questions = ReviewQuestion::all();
        $answer = ReviewAnswer::findorFail($id);
        return view('admin.reviewQuestion.reviewAnswers.edit', compact('questions', 'answer'));

    }

    /**
     * method to store review question data from create page.
     *
     * @return View
     */
    public function storeQuestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'answer_type' => 'required',
        ]);
        if ($validator->fails()) {
            return Redirect::back()
                ->withInput()
                ->withErrors($validator);
        } else {
            $question = new ReviewQuestion();
            $question->question = $request->question;
            $question->status = $request->status;
            $question->answer_type_id = $request->answer_type;
            if ($question->save()) {
                foreach ($request->reviewPage as $key => $value) {
                    $question->reviewpages()->attach($value);
                }
                activity('Create')
                    ->performedOn($question)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Customer Review Question has been created');
                // Redirect to the home page with success menu
                return Redirect::route('admin.review.index')->with('success', 'review Question Created Successfully');
            } else {
                return Redirect::route('admin.review.index')->with('error', 'Something Went Wrong');
            }
        }

    }

    /**
     * method to store review answer data from create page.
     *
     * @return View
     */
    public function storeAnswer(Request $request)
    {

        $messages = [
            'questionAnswers.*.answer.required' => 'The selected question requires one or more answers',
        ];
        $validator = Validator::make($request->all(), [
            'questionAnswers.*.answer' => 'required',
            'question' => 'required',
        ], $messages);

        if ($validator->fails()) {
            return Redirect::back()
                ->withInput()
                ->withErrors($validator);
        } else {

            if ($request->get('questionAnswers') != null) {
                foreach ($request->get('questionAnswers') as $key => $question_answers) {
                    if ($question_answers['answer'] != null) {
                        $answer = new ReviewAnswer();
                        $answer->question_id = $request->question;
                        $answer->answer = $question_answers['answer'];
                        $answer->save();
                    }
                }
                return Redirect::route('admin.review.index')->with('success', 'review Answer Created Successfully');
            } else {
                return Redirect::route('admin.review.index')->with('error', 'Answers are requireed');
            }

        }

    }

    /**
     * method to update review question data from edit page.
     *param: question id ($id)
     * @return View
     */
    public function updateQuestion($id, Request $request)
    {
        try {

            $question = ReviewQuestion::findorFail($id);
            $question->question = $request->question;
            $question->status = $request->status;
            $question->answer_type_id = $request->answer_type;
            if ($question->save()) {
                $question->reviewpages()->detach();
                foreach ($request->reviewPage as $key => $value) {
                    $question->reviewpages()->attach($value);
                }
                activity('Create')
                    ->performedOn($question)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Customer Review Question updated');
                // Redirect to the home page with success menu
                return Redirect::route('admin.review.index')->with('success', 'review Question Created Successfully');
            } else {
                return Redirect::route('admin.review.index')->with('error', 'Something Went Wrong');
            }

        } catch (Exception $ex) {
            return response()->json($ex->getMessage());
        }
    }

    /**
     * method to return modal body for confirm-delete of review question.
     *
     * @return View
     */
    public function getModalDeleteQuestion(Request $request)
    {
        $body = 'Are you sure you want to delete teh question ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
    }

    /**
     *deletes specific review question
     *param: question id ($id)
     * @return question listing page
     */
    public function destroy($id)
    {
        try {

            $question = ReviewQuestion::where('id', $id)->first();
            $question->reviewpages()->detach();
            $question->delete();

            return Redirect::route('admin.review.index')->with('success', 'review Question Deleted Successfully');

        } catch (Exception $ex) {
            return response()->json($ex->getMessage());
        }
    }

    /**Api call to get revuew questions for LeadsReviewPage, the question has answers and the answers have an answer Type, ref Laravel Eager Loading */
    public function getLeadsReviewQuestions()
    {
        try {

            $questions = ReviewQuestion::with(['answertype', 'reviewanswers', 'leadsreviewpage'])->where('status', 1)->whereHas('leadsreviewpage', function ($var) {
                $var->where('review_question_review_page.review_pages_id', 1);
            })->get();
            return $questions;
        } catch (Exception $ex) {
            return response()->json($ex->getMessage());
        }
    }

    public function getPolicyReviewQuestions()
    {
        try {
            /**Api call to get revie questions for PolicyReviewPage, the question has answers and the answers have an answer Type e.g CheckBox, ref Laravel Eager Loading */
            /** we also get intermediate relation betwee a ReviewQuestion and the Page it belongs To. we then check if the relation is ther */
            $query = ReviewQuestion::with(['answertype', 'reviewanswers', 'policyreviewpage'])->where('status', 1)->whereHas('policyreviewpage', function ($var) {
                $var->where('review_question_review_page.review_pages_id', 2);
            })->get();
            return response()->json($query);
        } catch (Exception $ex) {
            return response()->json($ex->getMessage());
        }
    }

}
