<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\User;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use PDF;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Log;
class ReinsuranceController extends Controller
{
    // Show page with form (same page)
    public function reinsuranceSlip($policyId, $actionId,$groupId)
    {
       
        return view("v2.livewire-page", [
            'page' => "reinsurance.reinsurance-data",
            'pageName' => 'Reinsurance Slip',
            'policyId' => $policyId,
            'actionId' => $actionId,
            'groupId'=>$groupId
        ]);
        
        
    }

    // Display reinsurance document with data
    public function reinsuranceDocument($policyId, $actionId)
    {
        // Retrieve data from session
        $data = session('reinsuranceDocumentData');
        $coverageDetails = session('coverageDetails');
        
        if (!$data) {
            return redirect()->route('policy.reinsuranceSlip', ['policyId' => $policyId, 'actionId' => $actionId])
                ->with('error', 'No data found. Please submit the form first.');
        }
        // return view(
        //     'v2.livewire.reinsurance.reinsurance-document',
        //     [
        //         'data' => $data,
        //         'coverageDetails' => $coverageDetails
        //     ]
        // );
        
        $policySlipNumber = $data['policySlipNumber'];
        
        $pdf = SnappyPdf::loadView(
            'v2.livewire.reinsurance.reinsurance-document',
            [
                'data' => $data,
                'coverageDetails' => $coverageDetails
            ]
        )->setOptions([
            'enable-local-file-access' => true,
            'load-error-handling' => 'ignore',
            'load-media-error-handling' => 'ignore',
            'disable-smart-shrinking' => true,
        ]);
        
        return $pdf->download($policySlipNumber.'.pdf');
         
    }

    // Save data and redirect to PDF
    public function store(Request $request)
    {
        $data = $request->validate([
            'policy_no' => 'required',
            'sum_insured' => 'required|numeric',
        ]);

        // Save to DB
        $slip = ReinsuranceSlip::create($data);

        // Redirect to PDF page
        return redirect()->route(
            'admin.policy.generateReinsuranceSlip',
            $slip->id
        );
    }

    // Generate PDF
    public function generateReinsuranceSlip($id)
    {
        $slip = ReinsuranceSlip::findOrFail($id);

        $pdf = PDF::loadView(
            'v2.livewire.pdf.reinsurance-document',
            compact('slip')
        );

        return $pdf->stream('reinsurance-slip.pdf');
    }
}


?>
