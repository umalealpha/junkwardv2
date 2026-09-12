<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\PaymentUrls;
use Exception;
use Illuminate\Http\Request;
use Redirect;

class PaymentUrlsController extends Controller
{
    //

    public function store(Request $request)
    {
        try {
            //code...
            $paymentUrl = new PaymentUrls();
            $paymentUrl->cellphone = $request->cellphone;
            $paymentUrl->policy_id = $request->policy_id;
            $paymentUrl->amount = $request->amount;
            $paymentUrl->status = 0;
            $paymentUrl->url = $request->url;

            if ($paymentUrl->save()) {
                return response()->json(['paymentDetails' => $paymentUrl], 200);
            } else {
                return response()->json(['message' => 'failed'], 419);
            }

        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }

    }

    public function delete($id)
    {

        try {

            $paymentUrl = PaymentUrls::findorFail($id);

            if ($paymentUrl->delete()) {
                return redirect()->back()->with('message', 'success');
            } else {
                return response()->json(['message' => 'failed'], 404);
            }

        } catch (Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }
}
