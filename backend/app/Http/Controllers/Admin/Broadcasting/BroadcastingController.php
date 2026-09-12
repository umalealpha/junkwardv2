<?php

namespace AlphaDirect\Http\Controllers\Admin\Broadcasting;

use Illuminate\Http\Request;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Support\Facades\Input;

use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\Treaty;
use AlphaDirect\PolicyPlan;
use AlphaDirect\KYC;
use AlphaDirect\Staff;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\Storage;

use Session;
use DB;
use Illuminate\Support\Carbon;
use AlphaDirect\Http\Controllers\Controller;

class BroadcastingController extends Controller
{
    /*
     * implementation of Auth using midddleware
     */
    public function __construct()
    {
        $this->middleware('auth');
    }   
    
    //view Email Broadcasting message
    public function viewEmailBroadcasting(){

        return view('Admin/Administration/emailBroadcasting');
    }
    //view SMS Broadcasting message
    public function viewSMSBroadcasting(){

        return view('Admin/Administration/smsBroadcasting');
    }


}