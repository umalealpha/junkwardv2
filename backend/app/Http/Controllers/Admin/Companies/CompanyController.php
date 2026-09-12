<?php

namespace AlphaDirect\Http\Controllers\Admin\Companies;

use Illuminate\Http\Request;
use AlphaDirect\Company;

use Session;
use Redirect;
use Datatables;

use AlphaDirect\Http\Controllers\Controller;

class CompanyController extends Controller
{
    /**
     * Display a listing of the companies.
     *
     * @return view: company index page.
     */
    public function index()
    {
        //
    }

    public function viewAddCompany(){

        return view('Admin/Company/makeNewCompany');
        
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
         //save Companies
         $company = new Company;

             $company->companyName = $request->cname;
             $company->companyLocation = $request->clocation;
             $company->email = $request->cemail;
             $company->telephone = $request->ctelephone;
             $company->ratio = $request->cratio;

             $company->save();

             response()->json(['Company Succesfully Added'],200);
             Session::flash('companySaved', 'Supplier Saved');
             return Redirect::back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /*
     * retrieving all companies
     * return: json
     */
    public function getAllCompanies(){
  

        $companies = Company::all();
                  

        return response()->json($companies);
    }
    
}
