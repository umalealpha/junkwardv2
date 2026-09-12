<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\GetMisReport;
use AlphaDirect\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MisReportController extends Controller
{
    /**
     * Request MIS Report
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestReport(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'auth_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get user to retrieve email
            $user = User::find($request->auth_id);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Create entry in getmis_reports table
            $misReport = GetMisReport::create([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'auth_id' => $request->auth_id,
                'report_email' => $user->email,
                'status' => 'pending'
            ]);

            // Dispatch the command with parameters
            Artisan::call('getMISReport', [
                '--start-date' => $request->start_date,
                '--end-date' => $request->end_date,
                '--report-id' => $misReport->id,
                '--auth-id' => $request->auth_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'You will get MIS report on your email shortly'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
