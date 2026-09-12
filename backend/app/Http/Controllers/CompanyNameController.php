<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Models\CompanyName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class CompanyNameController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasRole('Super Admin')) {
            $companies = CompanyName::all();
            return view('admin.companyname.index', compact('companies'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function create()
    {
        if (auth()->user()->hasRole('Super Admin')) {
            return view('admin.companyname.create');
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
       
    }

    public function store(Request $request)
    {
        //dd($request->all());
        // $request->validate([
        //     'name' => 'required|string|max:255',
        //     'email' => 'nullable|email',
        //     'vat_no' => 'nullable|string|max:255',
        //     'status' => 'nullable|string|max:50',
        // ]);

        $comp = new CompanyName();
        $comp->name = $request->name;
        $comp->email = $request->email ?? null;
        $comp->vat_no = $request->vat_no ?? null;
        $comp->status = $request->status ?? null;
        $comp->address = $request->address ?? null;
        $comp->save();
        return redirect()->route('admin.companyname.index')->with('success', 'Company created successfully.');
    }

    public function show($id)
    {
        $companyname = CompanyName::where('id',$id)->first();
        
        return view('admin.companyname.show', compact('companyname'));
    }

    public function edit($id)
    {
        if (auth()->user()->hasRole('Super Admin')) {
            $companyname = CompanyName::where('id',$id)->first();
      
            return view('admin.companyname.edit', compact('companyname'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
        
    }

    public function update(Request $request, $id)
    {
       

        $comp = CompanyName::where('id',$id)->first();
        $comp->name = $request->name;
        $comp->email = $request->email ?? null;
        $comp->vat_no = $request->vat_no ?? null;
        $comp->status = $request->status ?? null;
        $comp->address = $request->address ?? null;
        $comp->save();

        return redirect()->route('admin.companyname.index')->with('success', 'Company updated successfully.');
    }

    public function delete(Request $request, $id)
    {
        if (auth()->user()->hasRole('Super Admin')) {
        $companyname = CompanyName::where('id',$id)->first();
        $companyname->delete();

        return redirect()->route('admin.companyname.index')->with('success', 'Company deleted successfully.');
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }
}
