<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\OrganizationDocument;
use AlphaDirect\Region;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Redirect;

class OrganizationDocumentController extends Controller
{
    //
    protected $document;

    //constructor
    public function __construct()
    {
        $this->document = new OrganizationDocument(); //create the OrganzationDocument Instance to be used across all controller functions
    }

    public function index()
    {
        if(Auth::user()->hasPermissionTo('org-doc-list')){
            return view('admin/organizationDocuments/index');
        }else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function create()
    {
        $regions = Region::all();
        return view('admin/organizationDocuments/create', compact('regions'));
    }

    public function data()
    {

        try {
            //code...
            $response = $this->document->orgaizationDocumentData();
            $data = $response->getData();
            return response()->json($data);//dataTables is expecting a JSON response
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage()]);
        }

    }

    public function edit(Request $request, $id)
    {
        $regions = Region::all();
        $document = OrganizationDocument::findorFail($id);
         if(Auth::user()->hasPermissionTo('org-doc-edit')) {
             return view('admin/organizationDocuments/edit', compact('regions', 'document'));
         }elseif(Auth::user()->hasPermissionTo('org-doc-list')){
             return view('admin/organizationDocuments/view', compact('regions', 'document'));
         }
         else{
             return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
         }
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'slug' => 'required',
            'region' => 'required',
            'text' => 'required',
        ]);
        try {
            $response = $this->document->storeDocument($request); //call functions defined on the model
            $data = $response->getData();

            if ($data->status == 'success') { //get response

                return Redirect::route('organizationdocument.index')->with('success', 'Document Created Successfully');
            } else {
                return back()->withErrors('error', 'Error Something Went Wrong, Try Again');
            }
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required',
            'slug' => 'required',
            'text' => 'required|',
        ]);
        try {
            $response = $this->document->updateDocument($id, $request);
            $data = $response->getData();

            if ($data->status == 'success') {

                return Redirect::route('organizationdocument.index')->with('success', 'Document Updated Successfully');
            } else {
                return back()->withErrors('error', 'Error Something Went Wrong, Try Again');
            }
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()]);
        }
    }

    public function getModalDelete(Request $request)
    {
        $body = "Are you sure you want to delete the Document ? ";
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
    }

    public function destroy($id)
    {
        try {

            $response = $this->document->destroyDocument($id);
            $data = $response->getData();

            if ($data->status == 'success') { //get response

                return Redirect::route('organizationdocument.index')->with('success', 'Document Deleted Successfully');
            } else {
                return back()->withErrors('error', 'Error Something Went Wrong, Try Again');
            }
        } catch (Exception $ex) {
            return response()->json(['error' => $ex]);
        }
    }
}
