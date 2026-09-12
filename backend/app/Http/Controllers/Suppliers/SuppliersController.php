<?php

namespace AlphaDirect\Http\Controllers\Suppliers;

use AlphaDirect\Claim;
use AlphaDirect\ClaimQuote;
use AlphaDirect\ClaimQuoteDetail;
use AlphaDirect\ClaimVehicle;
use Illuminate\Http\Request;
use AlphaDirect\Supplier;
use AlphaDirect\Vehicle;
use AlphaDirect\GlassClaim;
use AlphaDirect\Quote;
use AlphaDirect\QuoteTotal;

use Illuminate\Support\Facades\Storage;
use Redirect;
use Session;
use Carbon;
use DB;
use AlphaDirect\Http\Controllers\Controller;



class SuppliersController extends Controller
{
    /*
     * create a new object
     */
    public function __construct()
    {
        $this->middleware('web');
    }


    public function uploadQuoteView($id)
    {

        $quote = ClaimQuote::where('id', $id)->first();

        $vehicleDetails = Vehicle::where('policy_id', $quote->policy_id)->first();

        $claimVehicle = ClaimVehicle::where('vehicle_id', $vehicleDetails->id)->first();
        $glassClaim = Claim::where('id', $claimVehicle->claim_id)->orderBy('created_at', 'desc')->first();

        $supplierDetails = Supplier::where('id', $quote->supplier_id)->first();

        return view('SupplierMailViews/SupplierViewQuote', compact('vehicleDetails', 'glassClaim', 'supplierDetails', 'quote'));
    }

    // to submit updated quote
    public function submitQuote(Request $request)
    {

        $quoteForm = $request->all();

        $glass = json_decode($request->glass, true);
        $cost = json_decode($request->cost, true);

        $data = array(array_values($glass[0])[0]);
        $dataCost = array(array_values($cost[0])[0]);

        $quoteTotal = ClaimQuote::where('id', $request->quote_id)->first();

        if (!$quoteTotal) {
            $quoteTotal = new ClaimQuote();
            $quoteTotal->supplier_id = $request->supplier_id;
            $quoteTotal->claim_id = $request->claim_id;
        }
        $quoteTotal->total = $request->finalTotal;

        if ($request->hasFile('invoiceFile')) {
            $file = $request->file('invoiceFile');
            $supplier = Supplier::where('id', $request->supplier_id)->first();
            $name = $this->gen_uuid() . $file->getClientOriginalName();
            $filePath = 'MIS/' . $supplier->email . '/' . 'Claims' . '/' . 'GlassID-' . $request->claim_id . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $quoteTotal->invoice_image = $filePath;
        }
        $quoteTotal->save();
        $elementCount  = count($glass);

        $sum = 0;
        for ($i = 0; $i < $elementCount; $i++) {

            $data = array(array_values($glass[$i])[0]);
            $dataCost = array(array_values($cost[$i])[0]);

            $sum += (int) $dataCost[0];

            if ((string) $data[0] != NULL) {
                $quote = new ClaimQuoteDetail();
                $quote->quote_id = $quoteTotal->id;
                $quote->claim_id = $quoteTotal->claim_id;
                $quote->supplier_id = $quoteTotal->supplier_id;
                $quote->item = (string) $data[0];
                $quote->cost = (string) $dataCost[0];
                $quote->save();
            }
        }

        Session::flash('quoteSent', 'Quote sent to Alpha Direct successfully');

        return redirect()->to('https://www.alphadirect.co.bw/');
    }

    public function uploadQuote(Request $request)
    {

        $glassClaim = Claim::find($request->claimID);
        if (Carbon::now()->greaterThan($glassClaim->created_at->addHours(24))) {
            // the glassClaim is more than 24 hours ago
            Session::flash('supplyExpired', 'This quote request has expired');
            return redirect()->back();
        }


        $uploadedFile = $request->file('quote');

        $request->validate([
            'quote' => 'required|filemimes:jpeg,pdf',
        ]);

        $fileName = $request->firstName . ' ' . $request->lastName . 's ' . 'Drivers License' . '.' . request()->bluebook->getClientOriginalExtension();



        $uploadedFile->storeAs('' . $request->firstName . ' ' . $request->lastName . ' ' . $request->id . '', $fileName);

        Session::flash('quoteSent', 'Quote sent to Alpha Direct');

        return redirect()->back();

    }

    /*
     * generates unique strings with helper function
     */
    private function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),

            // 16 bits for "time_mid"
            Helper::gen_ustring(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            Helper::gen_ustring(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            Helper::gen_ustring(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff)
        );
    }
}
