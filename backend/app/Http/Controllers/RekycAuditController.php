<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use AlphaDirect\Services\RekycAuditService;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RekycAuditController extends Controller
{
    protected $auditService;

    public function __construct(RekycAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get audit trail for a specific link
     */
    public function getLinkAuditTrail(Request $request, $linkId): JsonResponse
    {
        try {
            $auditTrail = $this->auditService->getLinkAuditTrail($linkId);

            return response()->json([
                'success' => true,
                'data' => $auditTrail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit trail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit trail for a specific customer
     */
    public function getCustomerAuditTrail(Request $request, $customerId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $limit = $request->get('limit', 100);
            $auditTrail = $this->auditService->getCustomerAuditTrail($customerId, $limit);

            return response()->json([
                'success' => true,
                'data' => $auditTrail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer audit trail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security events
     */
    public function getSecurityEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'nullable|in:low,medium,high,critical',
            'limit' => 'integer|min:1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $severity = $request->get('severity');
            $limit = $request->get('limit', 100);
            $securityEvents = $this->auditService->getSecurityEvents($severity, $limit);

            return response()->json([
                'success' => true,
                'data' => $securityEvents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve security events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compliance violations
     */
    public function getComplianceViolations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : null;
            $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : null;
            
            $violations = $this->auditService->getComplianceViolations($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $violations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve compliance violations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'nullable|exists:rekyc_campaigns,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaignId = $request->get('campaign_id');
            $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : null;
            $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : null;
            
            $report = $this->auditService->generateComplianceReport($campaignId, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate compliance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit statistics
     */
    public function getAuditStatistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'nullable|exists:rekyc_campaigns,id',
            'days' => 'integer|min:1|max:365'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaignId = $request->get('campaign_id');
            $days = $request->get('days', 30);
            
            $statistics = $this->auditService->getAuditStatistics($campaignId, $days);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive old audit logs
     */
    public function archiveOldLogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_old' => 'integer|min:30|max:1095' // 30 days to 3 years
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $daysOld = $request->get('days_old', 365);
            $archivedCount = $this->auditService->archiveOldLogs($daysOld);

            return response()->json([
                'success' => true,
                'message' => "Successfully archived {$archivedCount} old audit logs",
                'data' => [
                    'archived_count' => $archivedCount,
                    'days_old' => $daysOld
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive old logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export audit data for compliance
     */
    public function exportAuditData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'nullable|exists:rekyc_campaigns,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'format' => 'in:json,csv'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaignId = $request->get('campaign_id');
            $startDate = Carbon::parse($request->get('start_date'));
            $endDate = Carbon::parse($request->get('end_date'));
            $format = $request->get('format', 'json');
            
            $report = $this->auditService->generateComplianceReport($campaignId, $startDate, $endDate);
            
            // Generate export file
            $filename = 'rekyc_audit_export_' . Carbon::now()->format('Y-m-d_H-i-s') . '.' . $format;
            $filepath = storage_path('exports/' . $filename);
            
            // Ensure directory exists
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }
            
            if ($format === 'csv') {
                $this->exportToCsv($report, $filepath);
            } else {
                file_put_contents($filepath, json_encode($report, JSON_PRETTY_PRINT));
            }

            return response()->json([
                'success' => true,
                'message' => 'Audit data exported successfully',
                'data' => [
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'download_url' => url('storage/exports/' . $filename)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export audit data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV format
     */
    private function exportToCsv($data, $filepath)
    {
        $file = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($file, ['Activity Type', 'Customer ID', 'Description', 'IP Address', 'User Agent', 'Occurred At']);
        
        // Write data
        if (isset($data['compliance_events'])) {
            foreach ($data['compliance_events'] as $event) {
                fputcsv($file, [
                    $event->activity_type,
                    $event->customer_id,
                    $event->description,
                    $event->ip_address,
                    $event->user_agent,
                    $event->occurred_at
                ]);
            }
        }
        
        fclose($file);
    }
}
