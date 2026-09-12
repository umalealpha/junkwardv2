<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\Treaty;

use Session;
use Redirect;

class TreatyController extends Controller
{
    

    public function saveTreaty (Request $request){

         //save user treaty
         $treaty = new Treaty;
             $treaty->treatyName = $request->treatyName;
             $treaty->treatyNumber = $request->treatyNumber;
             $treaty->status = $request->status;
             $treaty->formula = $request->formula;
             $treaty->save();

        Session::flash('treatySaved', 'treaty saved and assigned');
         return Redirect::back();

     }


     public function getAllTreaties(){
  

        $treaties = Treaty::all();
                  

        return response()->json($treaties);
    }

     
}
