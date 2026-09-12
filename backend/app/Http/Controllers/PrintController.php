<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;

use PDF;
class PrintController extends Controller
{

    public function printReceipt(Request $request){

    // Fetch all customers from database
    $data =  $request->all();

    // Send data to the view using loadView function of PDF facade
    $pdf = PDF::loadView('Printouts.receipt', $data);
    // Finally, you can download the file using download function
    return $pdf->download($request->customerName.' reciept.pdf');

    }

    public function printInvoice(Request $request){

    // Fetch all customers from database
    $data =  $request->all();

    // Send data to the view using loadView function of PDF facade
    $pdf = PDF::loadView('Printouts.invoice', $data);
    // Finally, you can download the file using download function
    return $pdf->download($request->customerName.' invoice.pdf');

    }
}
