<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\SmsLogs;
use AlphaDirect\User;
use AlphaDirect\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;

class PaymentReminderSmsLogController extends Controller
{
    public function index()
    {
        return view('admin.paymentReminderSmsLog.index');
    }

    public function data()
    {
        $smsLogs = SmsLogs::all();
        return DataTables::of($smsLogs)
            ->editColumn('sms_status', function ($item) {
                $status = 'Failed';
                if ($item->sms_status == 1) {
                    $status = 'SMS Send';
                }
                return $status;
            })
            ->editColumn('sms_limit', function ($item) {
                $smslimit = 0;
                if ($item->sms_limit != null) {
                    $smslimit = $item->sms_limit;
                }
                return $smslimit;
            })
            ->editColumn('sms_text', function ($item) {
                $text = 'NA';
                if ($item->sms_text != '' || !empty($item->sms_text)) {
                    $text = $item->sms_text;
                }
                return $text;
            })
            ->editColumn('created_by', function ($item) {
                $created_by = 'NA';
                if (!empty($item->created_by)) {
                    $user =   User::where('id', $item->created_by)->first(['firstName', 'lastName']);
                    if ($user) {
                        $created_by = $user->firstName . ' ' . $user->lastName;
                    }
                }
                return $created_by;
            })
            ->editColumn('created_at', function ($item) {
                if ($item->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $item->created_at)->format('Y-m-d H:i') ;
                }
            })
            //            ->rawColumns(['payment_status','policy_number'])
            ->make(true);
    }
}
