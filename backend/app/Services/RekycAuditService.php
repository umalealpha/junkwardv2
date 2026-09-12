<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\RekycActivity;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycCampaign;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class RekycAuditService
{
    /**
     * Log activity (wrapper for logAuditTrail for backward compatibility)
     */
    public function logActivity($linkId, $customerId, $action, $description, $metadata = [], $ipAddress = null, $userAgent = null)
    {
        // For system events without specific links/customers, use logSystemEvent
        if (is_null($linkId) && is_null($customerId)) {
            return $this->logSystemEvent($action, $description, $metadata);
        }
        
        return $this->logAuditTrail($linkId, $customerId, $action, $description, $metadata, $ipAddress, $userAgent);
    }

    /**
     * Log comprehensive audit trail
     */
    public function logAuditTrail($linkId, $customerId, $action, $description, $metadata = [], $ipAddress = null, $userAgent = null)
    {
        try {
            // Create activity log entry
            RekycActivity::logActivity(
                $linkId,
                $customerId,
                $action, // This will be mapped to activity_type in the model
                $description,
                $metadata,
                $ipAddress,
                $userAgent
            );

            // Log to Laravel log for additional tracking
            Log::channel('rekyc_audit')->info('Re-KYC Audit Trail', [
                'link_id' => $linkId,
                'customer_id' => $customerId,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'timestamp' => Carbon::now()->toISOString(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to log audit trail: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log data access for compliance
     */
    public function logDataAccess($linkId, $customerId, $dataType, $accessReason, $ipAddress = null, $userAgent = null)
    {
        return $this->logAuditTrail(
            $linkId,
            $customerId,
            'data_access',
            "Accessed {$dataType} data",
            [
                'data_type' => $dataType,
                'access_reason' => $accessReason,
                'compliance_required' => true,
            ],
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Log data modification for compliance
     */
    public function logDataModification($linkId, $customerId, $field, $oldValue, $newValue, $ipAddress = null, $userAgent = null)
    {
        return $this->logAuditTrail(
            $linkId,
            $customerId,
            'data_modification',
            "Modified {$field}",
            [
                'field' => $field,
                'old_value' => $this->maskSensitiveValue($oldValue),
                'new_value' => $this->maskSensitiveValue($newValue),
                'compliance_required' => true,
            ],
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Log consent changes
     */
    public function logConsentChange($linkId, $customerId, $consentType, $consentGiven, $ipAddress = null, $userAgent = null)
    {
        return $this->logAuditTrail(
            $linkId,
            $customerId,
            'consent_change',
            "Consent {$consentType} " . ($consentGiven ? 'given' : 'withdrawn'),
            [
                'consent_type' => $consentType,
                'consent_given' => $consentGiven,
                'timestamp' => Carbon::now()->toISOString(),
                'compliance_required' => true,
            ],
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Log security events
     */
    public function logSecurityEvent($linkId, $customerId, $eventType, $description, $severity = 'medium', $metadata = [], $ipAddress = null, $userAgent = null)
    {
        return $this->logAuditTrail(
            $linkId,
            $customerId,
            'security_event',
            $description,
            array_merge($metadata, [
                'event_type' => $eventType,
                'severity' => $severity,
                'compliance_required' => true,
            ]),
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Log system events
     */
    public function logSystemEvent($eventType, $description, $metadata = [])
    {
        try {
            Log::channel('rekyc_system')->info('Re-KYC System Event', [
                'event_type' => $eventType,
                'description' => $description,
                'metadata' => $metadata,
                'timestamp' => Carbon::now()->toISOString(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to log system event: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport($campaignId = null, $startDate = null, $endDate = null)
    {
        $query = RekycActivity::query();

        if ($campaignId) {
            $query->whereHas('link', function ($q) use ($campaignId) {
                $q->where('campaign_id', $campaignId);
            });
        }

        if ($startDate) {
            $query->where('occurred_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('occurred_at', '<=', $endDate);
        }

        $activities = $query->with(['link.campaign', 'customer'])
            ->orderBy('occurred_at', 'desc')
            ->get();

        return [
            'total_activities' => $activities->count(),
            'activities_by_type' => $activities->groupBy('activity_type'),
            'activities_by_customer' => $activities->groupBy('customer_id'),
            'compliance_events' => $activities->where('metadata.compliance_required', true),
            'security_events' => $activities->where('activity_type', 'security_event'),
            'data_access_events' => $activities->where('activity_type', 'data_access'),
            'data_modification_events' => $activities->where('activity_type', 'data_modification'),
            'consent_events' => $activities->where('activity_type', 'consent_change'),
            'report_generated_at' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Get audit trail for specific link
     */
    public function getLinkAuditTrail($linkId)
    {
        return RekycActivity::where('link_id', $linkId)
            ->with(['customer'])
            ->orderBy('occurred_at', 'desc')
            ->get();
    }

    /**
     * Get audit trail for specific customer
     */
    public function getCustomerAuditTrail($customerId, $limit = 100)
    {
        return RekycActivity::where('customer_id', $customerId)
            ->with(['link.campaign'])
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get security events
     */
    public function getSecurityEvents($severity = null, $limit = 100)
    {
        $query = RekycActivity::where('activity_type', 'security_event');

        if ($severity) {
            $query->whereJsonContains('metadata->severity', $severity);
        }

        return $query->with(['link.campaign', 'customer'])
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get compliance violations
     */
    public function getComplianceViolations($startDate = null, $endDate = null)
    {
        $query = RekycActivity::where('activity_type', 'security_event')
            ->whereJsonContains('metadata->severity', 'high');

        if ($startDate) {
            $query->where('occurred_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('occurred_at', '<=', $endDate);
        }

        return $query->with(['link.campaign', 'customer'])
            ->orderBy('occurred_at', 'desc')
            ->get();
    }

    /**
     * Mask sensitive values in audit logs
     */
    private function maskSensitiveValue($value)
    {
        if (is_string($value) && strlen($value) > 4) {
            return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
        }

        return $value;
    }

    /**
     * Archive old audit logs
     */
    public function archiveOldLogs($daysOld = 365)
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $oldLogs = RekycActivity::where('occurred_at', '<', $cutoffDate)->get();
        
        // Archive to file or external storage
        $archiveData = $oldLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'link_id' => $log->link_id,
                'customer_id' => $log->customer_id,
                'activity_type' => $log->activity_type,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'occurred_at' => $log->occurred_at,
                'created_at' => $log->created_at,
            ];
        });

        // Save to archive file
        $archiveFile = storage_path('logs/rekyc_audit_archive_' . Carbon::now()->format('Y-m-d') . '.json');
        file_put_contents($archiveFile, json_encode($archiveData, JSON_PRETTY_PRINT));

        // Delete old logs
        $deletedCount = RekycActivity::where('occurred_at', '<', $cutoffDate)->delete();

        $this->logSystemEvent('audit_archive', "Archived {$deletedCount} old audit logs", [
            'archived_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toISOString(),
            'archive_file' => $archiveFile,
        ]);

        return $deletedCount;
    }

    /**
     * Get audit statistics
     */
    public function getAuditStatistics($campaignId = null, $days = 30)
    {
        $startDate = Carbon::now()->subDays($days);
        
        $query = RekycActivity::where('occurred_at', '>=', $startDate);

        if ($campaignId) {
            $query->whereHas('link', function ($q) use ($campaignId) {
                $q->where('campaign_id', $campaignId);
            });
        }

        $activities = $query->get();

        return [
            'total_activities' => $activities->count(),
            'activities_by_day' => $activities->groupBy(function ($activity) {
                return $activity->occurred_at->format('Y-m-d');
            }),
            'activities_by_type' => $activities->groupBy('activity_type'),
            'unique_customers' => $activities->pluck('customer_id')->unique()->count(),
            'unique_links' => $activities->pluck('link_id')->unique()->count(),
            'security_events' => $activities->where('activity_type', 'security_event')->count(),
            'compliance_events' => $activities->where('metadata.compliance_required', true)->count(),
            'period' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => Carbon::now()->toISOString(),
                'days' => $days,
            ],
        ];
    }
}
