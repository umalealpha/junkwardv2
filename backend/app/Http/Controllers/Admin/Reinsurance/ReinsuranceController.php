<?php

namespace AlphaDirect\Http\Controllers\Admin\Reinsurance;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;

use Session;
use Redirect;
use Datatables;

class ReinsuranceController extends Controller
{
    /*
     * returns view page for new reinsurance
     * return view page: reinsurance create page
     */
    public function viewAddReinsurance(){

        return view('Admin/Reinsurance/makeNewReinsurance');
        
    }

    /*
     * returns view page for new reinsurance treaty
     * return view page: reinsurance treaty create page
     */
    public function addTreatyView(){


        return view('Admin/Reinsurance/addNewTreaty');
    }
}
