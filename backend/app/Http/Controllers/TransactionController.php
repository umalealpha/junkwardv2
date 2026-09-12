<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Transaction;
use Exception;

class TransactionController extends Controller
{
    //
    public function index()
    {
        return view('admin/transaction/index');
    }

    public function data()
    {
//        try {
            $transaction = new \AlphaDirect\Transaction();
            $response = $transaction->transactionData();
            $data = $response->getData();
            return response()->json($data); //dataTables is expecting a JSON response
//        } catch (Exception $ex) {
//            return response()->json($ex->getMessage());
//        }
    }
}
