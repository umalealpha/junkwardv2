<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Coverage;
use AlphaDirect\RiskType;
use Illuminate\Http\Request;
use Redirect;

class CoverageController extends Controller
{
    //
    protected $coverage;

    //constructor
    public function __construct()
    {
        $this->coverage = new Coverage(); //create the Coverage Instance to be used across all controller functions
    }

    public function index()
    {
        $coverages = Coverage::all();
        return view('/admin/risk/coverage/index', compact('coverages'));
    }

    public function create()
    {
        $riskTypes = RiskType::where('active', 1)->get();
        return view('/admin/risk/coverage/create', compact('riskTypes'));
    }

    //a single view of the coverage
    public function edit($id)
    {
        $coverage = Coverage::findOrFail($id);
        $riskTypes = RiskType::all();
        return view('/admin/risk/coverage/edit', compact('coverage', 'riskTypes'));

    }
    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
    public function data()
    {
        try {
            //code...
            $response = $this->coverage->coverageData();
           
            $data = $response->getData();
        
            return response()->json($data); //dataTables is expecting a JSON response
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage());
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'limit' => 'required',
            'risk_type' => 'required',
        ]);
        try {
            
            $response = $this->coverage->storeCoverage($request);
            $data = $response->getData();

            if ($data->status == 'success') {
                return Redirect::route('coverage.display')->with('success', 'Coverage Created Successfully');
            } else {
                return back()->withErrors('error', 'Error Something Went Wrong, Try Again');
            }
        } catch (Exception $er) {
            $errormsg = ' error! ' . $er->getCode();
            return Response::json(['errormsg' => $errormsg]);

        }

    }
    public function getRiskLimit(Request $request)
    {

        $risklimit = RiskType::where('id', $request->id)->get(array('limit'));
        if ($risklimit->count() == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
        } else {
            return response()->json(['status' => 'success', 'limit' => $risklimit]);
        }

    }

    public function update(Request $request, $id)
    {

        try {
            $coverage = Coverage::where('id', $id)->first();
            $coverage->name = $request->name;
            //check status before submitting
            if ($request->status == null) {
                $coverage->status = "0";
            } else {
                $coverage->status = "1";
            }
            $coverage->limit = $request->limit;
            $coverage->risk_type = $request->risk_type;
            $coverage->save();

            return Redirect::route('coverage.display')->with('success', 'Coverage Updated Successfully');

        } catch (Exception $er) {
            $errormsg = ' error! ' . $er->getCode();
            return Response::json(['errormsg' => $errormsg]);

        }

    }
    public function getModalDelete(Request $request)
    {
        $check = Coverage::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves

        $body = 'Are you sure you want to delete the Coverage ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);

    }

    public function checkRiskLimit(Request $request)
    {
        try {
            $risklimit = RiskType::where('id', '=', $request->risk_id)->get(array('limit'));
            if ($risklimit != null) {
                foreach ($risklimit as $risk) {
                    if ($risk->limit < $request->coverageValue) {
                        return response()->json(['error' => 1, 'limit' => json_encode($risk->limit)]);
                    }
                }
            } else {
                return response()->json(['error' => 0]);
            }

        } catch (Exception $ex) {
            return response()->json(['error' => $ex]);
        }

    }

    public function destroy($id)
    {
        $coverage = Coverage::findOrFail($id);
        $coverage->delete();
        // Redirect to the home page with success menu
        return Redirect::route('coverage.display')->with('success', 'Coverage Deleted Successfully');
    }

}
