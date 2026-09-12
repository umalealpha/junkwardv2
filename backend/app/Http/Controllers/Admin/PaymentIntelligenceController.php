<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class PaymentIntelligenceController extends Controller
{
    /**
     * Dashboard — summary of all reconciliation runs and anomalies.
     */
    public function dashboard()
    {
        $bySeverity = DB::table('reconciliation_anomalies')
            ->where('status', 'open')
            ->selectRaw("severity, COUNT(*) as count")
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $byType = DB::table('reconciliation_anomalies')
            ->where('status', 'open')
            ->selectRaw("anomaly_type, COUNT(*) as count")
            ->groupBy('anomaly_type')
            ->pluck('count', 'anomaly_type')
            ->toArray();

        $latestRun = DB::table('reconciliation_runs')->orderBy('id', 'desc')->first();
        $recentRuns = DB::table('reconciliation_runs')->orderBy('id', 'desc')->limit(10)->get();
        $totalOpen = DB::table('reconciliation_anomalies')->where('status', 'open')->count();
        $totalResolved = DB::table('reconciliation_anomalies')->where('status', 'resolved')->count();

        return view('admin.payment-intelligence.dashboard', compact(
            'bySeverity', 'byType', 'latestRun', 'recentRuns', 'totalOpen', 'totalResolved'
        ));
    }

    /**
     * List anomalies with DataTables.
     */
    public function anomalies(Request $request)
    {
        $types = DB::table('reconciliation_anomalies')
            ->selectRaw('DISTINCT anomaly_type')
            ->pluck('anomaly_type')
            ->toArray();

        return view('admin.payment-intelligence.anomalies', compact('types'));
    }

    /**
     * AJAX data for anomalies DataTable.
     */
    public function anomaliesData(Request $request)
    {
        $query = DB::table('reconciliation_anomalies')
            ->orderBy('id', 'desc');

        if ($request->filled('anomaly_type')) {
            $query->where('anomaly_type', $request->anomaly_type);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('status_filter')) {
            $query->where('status', $request->status_filter);
        }
        if ($request->filled('run_id')) {
            $query->where('run_id', $request->run_id);
        }

        // Search
        $search = $request->get('search');
        $searchValue = $search['value'] ?? '';
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('policy_number', 'like', "%{$searchValue}%")
                  ->orWhere('customer_name', 'like', "%{$searchValue}%")
                  ->orWhere('description', 'like', "%{$searchValue}%");
            });
        }

        $totalRecords = DB::table('reconciliation_anomalies')->count();
        $filteredRecords = $query->count();

        $start = $request->get('start', 0);
        $length = $request->get('length', 25);

        $data = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw' => intval($request->get('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Individual check pages — show anomalies filtered by type.
     */
    public function checkByType($type)
    {
        $validTypes = [
            'premium_mismatch' => 'Premium vs Debit Order Mismatch',
            'partial_payment' => 'Partial Payments',
            'unpaid_invoice' => 'Unpaid Invoices',
            'balance_accumulating' => 'Accumulating Balances',
            'payment_gap' => 'Payment Gaps',
            'cancelled_but_collecting' => 'Cancelled but Collecting',
        ];

        if (!isset($validTypes[$type])) {
            return redirect()->route('admin.payment-intelligence.dashboard')->with('error', 'Invalid check type.');
        }

        $title = $validTypes[$type];
        $anomalyType = $type;

        return view('admin.payment-intelligence.check-type', compact('title', 'anomalyType'));
    }

    /**
     * Trigger a manual reconciliation run.
     */
    public function triggerRun(Request $request)
    {
        try {
            Artisan::call('reconciliation:run', ['--type' => 'manual']);
            $output = Artisan::output();
            return redirect()->route('admin.payment-intelligence.dashboard')
                ->with('success', 'Reconciliation run completed. ' . $output);
        } catch (\Exception $e) {
            return redirect()->route('admin.payment-intelligence.dashboard')
                ->with('error', 'Run failed: ' . $e->getMessage());
        }
    }

    /**
     * Export anomalies as CSV.
     */
    public function export(Request $request)
    {
        $query = DB::table('reconciliation_anomalies')
            ->orderBy('id', 'desc');

        if ($request->filled('anomaly_type')) {
            $query->where('anomaly_type', $request->anomaly_type);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('status_filter')) {
            $query->where('status', $request->status_filter);
        }
        if ($request->filled('run_id')) {
            $query->where('run_id', $request->run_id);
        }

        $anomalies = $query->get();

        $filename = 'reconciliation_report_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($anomalies) {
            $file = fopen('php://output', 'w');

            // CSV header
            fputcsv($file, [
                'ID', 'Run ID', 'Policy Number', 'Customer Name', 'Anomaly Type',
                'Severity', 'Description', 'Expected Amount', 'Actual Amount',
                'Difference', 'Payment Method', 'Period From', 'Period To',
                'Status', 'Resolved By', 'Resolved At', 'Resolution Notes', 'Created At',
            ]);

            foreach ($anomalies as $a) {
                fputcsv($file, [
                    $a->id, $a->run_id, $a->policy_number, $a->customer_name,
                    str_replace('_', ' ', ucwords($a->anomaly_type, '_')),
                    ucfirst($a->severity), $a->description,
                    $a->expected_amount, $a->actual_amount, $a->difference,
                    $a->payment_method, $a->period_from, $a->period_to,
                    ucfirst($a->status), $a->resolved_by, $a->resolved_at,
                    $a->resolution_notes, $a->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Update anomaly status (acknowledge/resolve/false-positive).
     */
    public function updateStatus(Request $request, $id)
    {
        $action = $request->get('action');
        $anomaly = DB::table('reconciliation_anomalies')->where('id', $id)->first();

        if (!$anomaly) {
            return redirect()->back()->with('error', 'Anomaly not found.');
        }

        switch ($action) {
            case 'acknowledge':
                DB::table('reconciliation_anomalies')->where('id', $id)->update([
                    'status' => 'acknowledged', 'updated_at' => now(),
                ]);
                break;
            case 'resolve':
                DB::table('reconciliation_anomalies')->where('id', $id)->update([
                    'status' => 'resolved',
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                    'resolution_notes' => $request->get('resolution_notes', ''),
                    'updated_at' => now(),
                ]);
                break;
            case 'false_positive':
                DB::table('reconciliation_anomalies')->where('id', $id)->update([
                    'status' => 'false_positive',
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
                break;
        }

        return redirect()->back()->with('success', 'Anomaly updated.');
    }
}
