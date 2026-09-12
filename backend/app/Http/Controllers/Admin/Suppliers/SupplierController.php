<?php

namespace AlphaDirect\Http\Controllers\Admin\Suppliers;

use Illuminate\Http\Request;
use AlphaDirect\Supplier;

use Redirect;
use Session;
use Datatables;


use AlphaDirect\Http\Controllers\Controller;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    public function addSupplierView(){


        return view('Admin/Supplier/addNewSupplier');
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

    
    public function viewAddSupplier(){

        return view('Admin/Supplier/makeNewSupplier');

    }
    protected function getFileName($file)
    {
       return str_random(32) . '.' . $file->extension();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //save Suppliers
        $suppliers = new Supplier;

        $suppliers->supplierName = $request->sname;
        $suppliers->supplierLocation = $request->slocation;
        $suppliers->telephone = $request->telephone;
        $suppliers->email = $request->semail;
        $filename = $this->getFileName($request->image);
        $suppliers->image = $request->image->move(base_path('public\images'), $filename);
        $suppliers->save();

        response()->json(['Supplier Succesfully Added'],200);

        Session::flash('supplierSaved', 'Supplier Saved');
        return Redirect::back();
    }

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


    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }


    public function getAllSuppliers(){
        $suppliers = Supplier::all();
        return response()->json($suppliers);
    }
}
