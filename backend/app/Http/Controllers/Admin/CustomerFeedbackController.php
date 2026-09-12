<?php

namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CustomerFeedbackController extends Controller
{
    /**
     * Show a list of all customer feedback options
     *
     * @return View customer feedback index page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('customer-feedback')) {
            return view("admin.customerFeedback.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function create(){
        if (Auth::user()->hasPermissionTo('customer-feedback')) {
            return view("admin.customerFeedback.create");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function store(Request $request){
        if (Auth::user()->hasPermissionTo('customer-feedback')) {
            dd($request->all());
        } else {
            return \Illuminate\Support\Facades\Redirect::back()->with('error', 'Sorry! You do not have permission to create feedback option!');
        }
    }
}
