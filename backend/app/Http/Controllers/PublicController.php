<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\PolicyPlan;


class PublicController extends Controller
{
    //

    public function getAllPolicyPlans(){


        $policyPlan = PolicyPlan::where('status',1)->get();

        return response()->json($policyPlan);
    }
}
