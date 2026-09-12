<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Models\RekycCampaign;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycActivity;
use AlphaDirect\Models\RekycDocument;
use AlphaDirect\Services\RekycAuditService;
use Carbon\Carbon;

class RekycComplianceController extends Controller
{
    protected $auditService;

    public function __construct(RekycAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get compliance dashboard overview
     */
    public function getDashboardOverview(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request);
            
            $overview = [
                'campaigns' => $this->getCampaignOverview($dateRange),
                'completion_rates' => $this->getCompletionRates($dateRange),
                'compliance_metrics' => $this->getComplianceMetrics($dateRange),
                'security_events' => $this->getSecurityEvents($dateRange),
                'data_retention' => $this->getDataRetentionMetrics($dateRange),
                'audit_trail' => $this->getAuditTrailSummary($dateRange),
            ];

            return response()->json([
                'success' => true,
                'data' => $overview
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get compliance dashboard overview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign performance metrics
     */
    public function getCampaignMetrics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'nullable|exists:rekyc_campaigns,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $dateRange = $this->getDateRange($request);
            $campaignId = $request->campaign_id;

            $metrics = [
                'total_campaigns' => $this->getTotalCampaigns($dateRange, $campaignId),
                'active_campaigns' => $this->getActiveCampaigns($dateRange, $campaignId),
                'completed_campaigns' => $this->getCompletedCampaigns($dateRange, $campaignId),
                'links_generated' => $this->getLinksGenerated($dateRange, $campaignId),
                'links_sent' => $this->getLinksSent($dateRange, $campaignId),
                'links_opened' => $this->getLinksOpened($dateRange, $campaignId),
                'otp_verified' => $this->getOtpVerified($dateRange, $campaignId),
                'completed' => $this->getCompleted($dateRange, $campaignId),
                'completion_rate' => $this->getCompletionRate($dateRange, $campaignId),
                'response_time' => $this->getAverageResponseTime($dateRange, $campaignId),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get campaign metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get campaign metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compliance violations
     */
    public function getComplianceViolations(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request);
            
            $violations = [
                'data_retention_violations' => $this->getDataRetentionViolations($dateRange),
                'consent_violations' => $this->getConsentViolations($dateRange),
                'security_violations' => $this->getSecurityViolations($dateRange),
                'audit_violations' => $this->getAuditViolations($dateRange),
                'dpa_violations' => $this->getDpaViolations($dateRange),
            ];

            return response()->json([
                'success' => true,
                'data' => $violations
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get compliance violations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get compliance violations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data protection metrics
     */
    public function getDataProtectionMetrics(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request);
            
            $metrics = [
                'data_subjects' => $this->getDataSubjectsCount($dateRange),
                'consent_records' => $this->getConsentRecordsCount($dateRange),
                'data_requests' => $this->getDataRequestsCount($dateRange),
                'erasure_requests' => $this->getErasureRequestsCount($dateRange),
                'portability_requests' => $this->getPortabilityRequestsCount($dateRange),
                'retention_compliance' => $this->getRetentionCompliance($dateRange),
                'consent_withdrawal' => $this->getConsentWithdrawalRate($dateRange),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get data protection metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get data protection metrics',
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
            'report_type' => 'required|in:summary,detailed,audit,data_protection',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'format' => 'nullable|in:json,pdf,csv'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $reportType = $request->report_type;
            $dateRange = $this->getDateRange($request);
            $format = $request->get('format', 'json');

            $report = $this->generateReport($reportType, $dateRange, $format);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate compliance report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate compliance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit trail for compliance
     */
    public function getAuditTrail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'link_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'action' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = RekycActivity::query();
            
            if ($request->link_id) {
                $query->where('link_id', $request->link_id);
            }
            
            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }
            
            if ($request->action) {
                $query->where('activity_type', 'like', '%' . $request->action . '%');
            }
            
            if ($request->date_from) {
                $query->where('occurred_at', '>=', $request->date_from);
            }
            
            if ($request->date_to) {
                $query->where('occurred_at', '<=', $request->date_to);
            }

            $limit = $request->get('limit', 100);
            $activities = $query->orderBy('occurred_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get audit trail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get audit trail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get date range from request
     */
    protected function getDateRange(Request $request): array
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        
        return [
            'from' => Carbon::parse($dateFrom)->startOfDay(),
            'to' => Carbon::parse($dateTo)->endOfDay()
        ];
    }

    /**
     * Get campaign overview
     */
    protected function getCampaignOverview(array $dateRange): array
    {
        return [
            'total' => RekycCampaign::whereBetween('created_at', [$dateRange['from'], $dateRange['to']])->count(),
            'active' => RekycCampaign::where('status', 'active')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
            'completed' => RekycCampaign::where('status', 'completed')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
        ];
    }

    /**
     * Get completion rates
     */
    protected function getCompletionRates(array $dateRange): array
    {
        $totalLinks = RekycLink::whereBetween('created_at', [$dateRange['from'], $dateRange['to']])->count();
        $completedLinks = RekycLink::where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();

        return [
            'total_links' => $totalLinks,
            'completed_links' => $completedLinks,
            'completion_rate' => $totalLinks > 0 ? round(($completedLinks / $totalLinks) * 100, 2) : 0,
        ];
    }

    /**
     * Get compliance metrics
     */
    protected function getComplianceMetrics(array $dateRange): array
    {
        return [
            'dpa_compliance' => $this->getDpaComplianceScore($dateRange),
            'consent_rate' => $this->getConsentRate($dateRange),
            'data_retention_compliance' => $this->getDataRetentionCompliance($dateRange),
            'audit_completeness' => $this->getAuditCompleteness($dateRange),
        ];
    }

    /**
     * Get security events
     */
    protected function getSecurityEvents(array $dateRange): array
    {
        return [
            'failed_otp_attempts' => RekycActivity::where('action', 'otp_verification_failed')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
            'suspicious_activities' => RekycActivity::where('action', 'like', '%suspicious%')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
            'data_breaches' => RekycActivity::where('action', 'data_breach')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
        ];
    }

    /**
     * Get data retention metrics
     */
    protected function getDataRetentionMetrics(array $dateRange): array
    {
        $retentionPeriod = config('rekyc.compliance.dpa_2018.data_retention_days', 2555);
        $cutoffDate = now()->subDays($retentionPeriod);
        
        return [
            'data_older_than_retention' => RekycLink::where('created_at', '<', $cutoffDate)->count(),
            'documents_older_than_retention' => RekycDocument::where('created_at', '<', $cutoffDate)->count(),
            'activities_older_than_retention' => RekycActivity::where('created_at', '<', $cutoffDate)->count(),
        ];
    }

    /**
     * Get audit trail summary
     */
    protected function getAuditTrailSummary(array $dateRange): array
    {
        return [
            'total_activities' => RekycActivity::whereBetween('occurred_at', [$dateRange['from'], $dateRange['to']])->count(),
            'by_action' => RekycActivity::whereBetween('occurred_at', [$dateRange['from'], $dateRange['to']])
                ->groupBy('activity_type')
                ->selectRaw('activity_type, count(*) as count')
                ->pluck('count', 'activity_type'),
            'by_entity' => RekycActivity::whereBetween('occurred_at', [$dateRange['from'], $dateRange['to']])
                ->groupBy('link_id')
                ->selectRaw('link_id, count(*) as count')
                ->pluck('count', 'link_id'),
        ];
    }

    // Additional helper methods for specific metrics...
    protected function getTotalCampaigns(array $dateRange, ?int $campaignId): int
    {
        $query = RekycCampaign::whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('id', $campaignId);
        }
        return $query->count();
    }

    protected function getActiveCampaigns(array $dateRange, ?int $campaignId): int
    {
        $query = RekycCampaign::where('status', 'active')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('id', $campaignId);
        }
        return $query->count();
    }

    protected function getCompletedCampaigns(array $dateRange, ?int $campaignId): int
    {
        $query = RekycCampaign::where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('id', $campaignId);
        }
        return $query->count();
    }

    protected function getLinksGenerated(array $dateRange, ?int $campaignId): int
    {
        $query = RekycLink::whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        return $query->count();
    }

    protected function getLinksSent(array $dateRange, ?int $campaignId): int
    {
        $query = RekycLink::whereNotNull('sent_at')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        return $query->count();
    }

    protected function getLinksOpened(array $dateRange, ?int $campaignId): int
    {
        $query = RekycLink::whereNotNull('opened_at')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        return $query->count();
    }

    protected function getOtpVerified(array $dateRange, ?int $campaignId): int
    {
        $query = RekycLink::whereNotNull('otp_verified_at')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        return $query->count();
    }

    protected function getCompleted(array $dateRange, ?int $campaignId): int
    {
        $query = RekycLink::where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        return $query->count();
    }

    protected function getCompletionRate(array $dateRange, ?int $campaignId): float
    {
        $total = $this->getLinksGenerated($dateRange, $campaignId);
        $completed = $this->getCompleted($dateRange, $campaignId);
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    protected function getAverageResponseTime(array $dateRange, ?int $campaignId): float
    {
        $query = RekycLink::whereNotNull('sent_at')
            ->whereNotNull('opened_at')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        
        $links = $query->get();
        
        if ($links->isEmpty()) {
            return 0;
        }
        
        $totalMinutes = $links->sum(function ($link) {
            return $link->sent_at->diffInMinutes($link->opened_at);
        });
        
        return round($totalMinutes / $links->count(), 2);
    }

    // Additional compliance methods...
    protected function getDpaComplianceScore(array $dateRange): float
    {
        // Calculate DPA compliance score based on various factors
        $consentRate = $this->getConsentRate($dateRange);
        $retentionCompliance = $this->getDataRetentionCompliance($dateRange);
        $auditCompleteness = $this->getAuditCompleteness($dateRange);
        
        return round(($consentRate + $retentionCompliance + $auditCompleteness) / 3, 2);
    }

    protected function getConsentRate(array $dateRange): float
    {
        $totalLinks = RekycLink::whereBetween('created_at', [$dateRange['from'], $dateRange['to']])->count();
        $consentGiven = RekycLink::whereNotNull('consent_data')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
        
        return $totalLinks > 0 ? round(($consentGiven / $totalLinks) * 100, 2) : 0;
    }

    protected function getDataRetentionCompliance(array $dateRange): float
    {
        // Check if data retention policies are being followed
        $retentionPeriod = config('rekyc.compliance.dpa_2018.data_retention_days', 2555);
        $cutoffDate = now()->subDays($retentionPeriod);
        
        $totalRecords = RekycLink::count();
        $compliantRecords = RekycLink::where('created_at', '>=', $cutoffDate)->count();
        
        return $totalRecords > 0 ? round(($compliantRecords / $totalRecords) * 100, 2) : 100;
    }

    protected function getAuditCompleteness(array $dateRange): float
    {
        // Check if all required activities are being logged
        $totalLinks = RekycLink::whereBetween('created_at', [$dateRange['from'], $dateRange['to']])->count();
        $loggedActivities = RekycActivity::whereBetween('created_at', [$dateRange['from'], $dateRange['to']])->count();
        
        // Expected activities per link (simplified calculation)
        $expectedActivities = $totalLinks * 3; // Assuming 3 activities per link on average
        
        return $expectedActivities > 0 ? round(min(($loggedActivities / $expectedActivities) * 100, 100), 2) : 100;
    }

    // Additional violation detection methods...
    protected function getDataRetentionViolations(array $dateRange): array
    {
        $retentionPeriod = config('rekyc.compliance.dpa_2018.data_retention_days', 2555);
        $cutoffDate = now()->subDays($retentionPeriod);
        
        return [
            'expired_links' => RekycLink::where('created_at', '<', $cutoffDate)->count(),
            'expired_documents' => RekycDocument::where('created_at', '<', $cutoffDate)->count(),
            'expired_activities' => RekycActivity::where('created_at', '<', $cutoffDate)->count(),
        ];
    }

    protected function getConsentViolations(array $dateRange): array
    {
        return [
            'missing_consent' => RekycLink::whereNull('consent_data')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
            'withdrawn_consent' => RekycActivity::where('action', 'consent_withdrawn')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
        ];
    }

    protected function getSecurityViolations(array $dateRange): array
    {
        return [
            'failed_otp_attempts' => RekycActivity::where('action', 'otp_verification_failed')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
            'suspicious_activities' => RekycActivity::where('action', 'like', '%suspicious%')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->count(),
        ];
    }

    protected function getAuditViolations(array $dateRange): array
    {
        return [
            'missing_audit_logs' => 0, // This would need more complex logic
            'incomplete_audit_logs' => 0, // This would need more complex logic
        ];
    }

    protected function getDpaViolations(array $dateRange): array
    {
        return [
            'data_retention_violations' => $this->getDataRetentionViolations($dateRange),
            'consent_violations' => $this->getConsentViolations($dateRange),
            'security_violations' => $this->getSecurityViolations($dateRange),
        ];
    }

    // Data protection metrics methods...
    protected function getDataSubjectsCount(array $dateRange): int
    {
        return RekycLink::whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->distinct('customer_id')
            ->count('customer_id');
    }

    protected function getConsentRecordsCount(array $dateRange): int
    {
        return RekycLink::whereNotNull('consent_data')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
    }

    protected function getDataRequestsCount(array $dateRange): int
    {
        return RekycActivity::where('action', 'data_request')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
    }

    protected function getErasureRequestsCount(array $dateRange): int
    {
        return RekycActivity::where('action', 'erasure_request')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
    }

    protected function getPortabilityRequestsCount(array $dateRange): int
    {
        return RekycActivity::where('action', 'portability_request')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
    }

    protected function getRetentionCompliance(array $dateRange): float
    {
        return $this->getDataRetentionCompliance($dateRange);
    }

    protected function getConsentWithdrawalRate(array $dateRange): float
    {
        $totalConsent = RekycLink::whereNotNull('consent_data')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
        
        $withdrawnConsent = RekycActivity::where('action', 'consent_withdrawn')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
        
        return $totalConsent > 0 ? round(($withdrawnConsent / $totalConsent) * 100, 2) : 0;
    }

    /**
     * Generate compliance report
     */
    protected function generateReport(string $reportType, array $dateRange, string $format): array
    {
        $report = [
            'report_type' => $reportType,
            'generated_at' => now()->toISOString(),
            'date_range' => $dateRange,
            'format' => $format,
        ];

        switch ($reportType) {
            case 'summary':
                $report['data'] = $this->getDashboardOverview(new Request($dateRange));
                break;
            case 'detailed':
                $report['data'] = [
                    'campaign_metrics' => $this->getCampaignMetrics(new Request($dateRange)),
                    'compliance_violations' => $this->getComplianceViolations(new Request($dateRange)),
                    'data_protection_metrics' => $this->getDataProtectionMetrics(new Request($dateRange)),
                ];
                break;
            case 'audit':
                $report['data'] = $this->getAuditTrail(new Request($dateRange));
                break;
            case 'data_protection':
                $report['data'] = $this->getDataProtectionMetrics(new Request($dateRange));
                break;
        }

        return $report;
    }
}
