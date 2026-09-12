<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\SmsLogs;

class SmsLogsController extends Controller
{
    /**
     * Show a list of all sms logs.
     *
     * @return View
     */
    public function index()
    {
        return view('admin.smsMessaging.smsLogs.index');
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
            $smslogs = new SmsLogs();
            $response = $smslogs->smsLogsData();
            $data = $response->getData();
            
            return response()->json($data);
        } catch (\Exception $th) {
            //throw $th;
            return response()->json($th->getMessage());
        }
    }

    /**
     * Stores data from a page to create sms log .
     *
     * @return View
     */
    public function store($cellphone, $policyNumber = null, $sms_template_id = null, $sms_status, $sms_text = null)
    {
        try {
            //code...

            $smsLogsStore = new SmsLogs();
            $smsLogsStore->policyNumber = $policyNumber;
            $smsLogsStore->cellphone = $cellphone;
            $smsLogsStore->sms_template_id = $sms_template_id;
            $smsLogsStore->sms_text = $sms_text;
            $smsLogsStore->sms_status = $sms_status;
            $smsLogsStore->created_by = auth()->user()->id;
            $smsLogsStore->save();
            return response()->json(['status' => 'success']);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getFile(), 'status' => 'failed']);
        }
    }
}
