<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\DeduplicationChecks;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeduplicationActivityService
{
    /**
     * Log activity for a deduplication check
     */
    public function logActivity(DeduplicationChecks $check, string $action, string $description, array $metadata = []): void
    {
        try {
            // Update access logs
            $accessLogs = $check->access_logs ?? [];
            $accessLogs[] = [
                'action' => $action,
                'description' => $description,
                'timestamp' => Carbon::now()->toISOString(),
                'metadata' => $metadata,
                'source' => $metadata['source'] ?? 'system'
            ];
            
            $check->update(['access_logs' => $accessLogs]);
            
            // Log to application log
            Log::info("DeduplicationCheck #{$check->id}: {$action} - {$description}", [
                'check_id' => $check->id,
                'customer_id' => $check->customer_id,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to log activity for DeduplicationCheck #{$check->id}: " . $e->getMessage());
        }
    }

    /**
     * Log link generation activity
     */
    public function logLinkGenerated(DeduplicationChecks $check, string $token, Carbon $expiresAt, string $source = 'cron'): void
    {
        $this->logActivity($check, 'link_generated', 'Bank statement upload link generated', [
            'token' => $token,
            'expires_at' => $expiresAt->toISOString(),
            'source' => $source
        ]);
    }

    /**
     * Log link access activity
     */
    public function logLinkAccessed(DeduplicationChecks $check, string $ipAddress, string $userAgent): void
    {
        $this->logActivity($check, 'link_accessed', 'Bank statement upload link accessed', [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'source' => 'frontend'
        ]);
    }

    /**
     * Log OTP sent activity
     */
    public function logOtpSent(DeduplicationChecks $check, string $otpCode, string $channel = 'sms'): void
    {
        $this->logActivity($check, 'otp_sent', "OTP sent via {$channel}", [
            'otp_code' => $otpCode,
            'channel' => $channel,
            'source' => 'system'
        ]);
    }

    /**
     * Log OTP verification activity
     */
    public function logOtpVerified(DeduplicationChecks $check, bool $success, int $attempts): void
    {
        $action = $success ? 'otp_verified' : 'otp_verification_failed';
        $description = $success ? 'OTP verified successfully' : 'OTP verification failed';
        
        $this->logActivity($check, $action, $description, [
            'success' => $success,
            'attempts' => $attempts,
            'source' => 'frontend'
        ]);
    }

    /**
     * Log document upload activity
     */
    public function logDocumentUploaded(DeduplicationChecks $check, array $fileData): void
    {
        $this->logActivity($check, 'document_uploaded', 'Bank statement document uploaded', [
            'file_name' => $fileData['file_name'] ?? null,
            'file_size' => $fileData['file_size'] ?? null,
            'file_type' => $fileData['file_type'] ?? null,
            'file_hash' => $fileData['file_hash'] ?? null,
            'source' => 'frontend'
        ]);
    }

    /**
     * Log document verification activity
     */
    public function logDocumentVerified(DeduplicationChecks $check, string $status, string $verifiedBy, string $notes = null): void
    {
        $this->logActivity($check, 'document_verified', "Document verification {$status}", [
            'verification_status' => $status,
            'verified_by' => $verifiedBy,
            'notes' => $notes,
            'source' => 'admin'
        ]);
    }

    /**
     * Log status change activity
     */
    public function logStatusChange(DeduplicationChecks $check, string $oldStatus, string $newStatus, string $reason = null): void
    {
        $this->logActivity($check, 'status_changed', "Status changed from {$oldStatus} to {$newStatus}", [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'source' => 'admin'
        ]);
    }

    /**
     * Log suspension activity
     */
    public function logSuspension(DeduplicationChecks $check, string $reason, string $suspendedBy): void
    {
        $this->logActivity($check, 'suspended', 'Check suspended', [
            'reason' => $reason,
            'suspended_by' => $suspendedBy,
            'suspended_at' => Carbon::now()->toISOString(),
            'source' => 'admin'
        ]);
    }

    /**
     * Log reactivation activity
     */
    public function logReactivation(DeduplicationChecks $check, string $reactivatedBy): void
    {
        $this->logActivity($check, 'reactivated', 'Check reactivated', [
            'reactivated_by' => $reactivatedBy,
            'reactivated_at' => Carbon::now()->toISOString(),
            'source' => 'admin'
        ]);
    }

    /**
     * Log notification sent activity
     */
    public function logNotificationSent(DeduplicationChecks $check, string $channel, string $type, bool $success = true): void
    {
        $action = $success ? 'notification_sent' : 'notification_failed';
        $description = $success ? "Notification sent via {$channel}" : "Failed to send notification via {$channel}";
        
        $this->logActivity($check, $action, $description, [
            'channel' => $channel,
            'notification_type' => $type,
            'success' => $success,
            'source' => 'system'
        ]);
    }

    /**
     * Log error activity
     */
    public function logError(DeduplicationChecks $check, string $error, array $context = []): void
    {
        $this->logActivity($check, 'error', "Error occurred: {$error}", array_merge($context, [
            'error' => $error,
            'source' => 'system'
        ]));
    }

    /**
     * Get activity summary for a check
     */
    public function getActivitySummary(DeduplicationChecks $check): array
    {
        $accessLogs = $check->access_logs ?? [];
        
        $summary = [
            'total_activities' => count($accessLogs),
            'link_generated' => false,
            'link_accessed' => false,
            'otp_sent' => false,
            'otp_verified' => false,
            'document_uploaded' => false,
            'document_verified' => false,
            'last_activity' => null,
            'first_activity' => null
        ];
        
        if (!empty($accessLogs)) {
            $summary['first_activity'] = $accessLogs[0]['timestamp'] ?? null;
            $summary['last_activity'] = end($accessLogs)['timestamp'] ?? null;
            
            foreach ($accessLogs as $log) {
                switch ($log['action']) {
                    case 'link_generated':
                        $summary['link_generated'] = true;
                        break;
                    case 'link_accessed':
                        $summary['link_accessed'] = true;
                        break;
                    case 'otp_sent':
                        $summary['otp_sent'] = true;
                        break;
                    case 'otp_verified':
                        $summary['otp_verified'] = true;
                        break;
                    case 'document_uploaded':
                        $summary['document_uploaded'] = true;
                        break;
                    case 'document_verified':
                        $summary['document_verified'] = true;
                        break;
                }
            }
        }
        
        return $summary;
    }

    /**
     * Get activity timeline for a check
     */
    public function getActivityTimeline(DeduplicationChecks $check): array
    {
        $accessLogs = $check->access_logs ?? [];
        
        return array_map(function ($log) {
            return [
                'action' => $log['action'],
                'description' => $log['description'],
                'timestamp' => $log['timestamp'],
                'source' => $log['source'] ?? 'system',
                'metadata' => $log['metadata'] ?? []
            ];
        }, $accessLogs);
    }

    /**
     * Clean up old activity logs (keep only last 100 entries)
     */
    public function cleanupOldLogs(DeduplicationChecks $check): void
    {
        $accessLogs = $check->access_logs ?? [];
        
        if (count($accessLogs) > 100) {
            $recentLogs = array_slice($accessLogs, -100);
            $check->update(['access_logs' => $recentLogs]);
            
            $this->logActivity($check, 'logs_cleaned', 'Old activity logs cleaned up', [
                'removed_count' => count($accessLogs) - 100,
                'remaining_count' => 100,
                'source' => 'system'
            ]);
        }
    }
}
