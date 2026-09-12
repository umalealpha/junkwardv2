<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Exports\SlaReportExport;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\SlaReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel as ExcelWriter;

/**
 * Exportable SLA reports (Excel / CSV), gated to management roles. Types:
 *   compliance | breach | assignee | monthly
 *
 *   GET help-desk/sla/reports/{type}?format=xlsx|csv&from=YYYY-MM-DD&to=YYYY-MM-DD
 */
class SlaReportController extends Controller
{
    public function __construct(private SlaReportService $reports)
    {
    }

    public function export(Request $request, string $type)
    {
        $user  = Auth::user();
        $roles = (array) config('help_desk.sla.dashboard_roles', ['Super Admin', 'Manager', 'Admin']);
        if (!$user || !$user->hasAnyRole($roles)) {
            return response()->json(['message' => 'You are not authorised to export SLA reports.'], 403);
        }

        $validated = $request->validate([
            'format' => 'nullable|in:xlsx,csv',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date',
        ]);

        $from = !empty($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to   = !empty($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;

        try {
            [$headings, $rows, $name] = $this->reports->build($type, $from, $to);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $format = $validated['format'] ?? 'xlsx';
        $writer = $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        return (new SlaReportExport($headings, $rows))->download("{$name}.{$format}", $writer);
    }
}
