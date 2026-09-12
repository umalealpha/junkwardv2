<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\RekycLog;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycCampaign;
use AlphaDirect\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Exception;

class RekycLogService
{
    /**
     * Log an info message to the database
     */
    public static function info(string $action, string $message, array $context = [], $rekycLinkId = null, $customerId = null, $campaignId = null)
    {
        return self::log(RekycLog::LEVEL_INFO, $action, $message, $context, $rekycLinkId, $customerId, $campaignId);
    }

    /**
     * Log a warning message to the database
     */
    public static function warning(string $action, string $message, array $context = [], $rekycLinkId = null, $customerId = null, $campaignId = null)
    {
        return self::log(RekycLog::LEVEL_WARNING, $action, $message, $context, $rekycLinkId, $customerId, $campaignId);
    }

    /**
     * Log an error message to the database
     */
    public static function error(string $action, string $message, array $context = [], $rekycLinkId = null, $customerId = null, $campaignId = null)
    {
        return self::log(RekycLog::LEVEL_ERROR, $action, $message, $context, $rekycLinkId, $customerId, $campaignId);
    }

    /**
     * Log a debug message to the database
     */
    public static function debug(string $action, string $message, array $context = [], $rekycLinkId = null, $customerId = null, $campaignId = null)
    {
        return self::log(RekycLog::LEVEL_DEBUG, $action, $message, $context, $rekycLinkId, $customerId, $campaignId);
    }

    /**
     * Log a message to the database
     */
    public static function log(string $level, string $action, string $message, array $context = [], $rekycLinkId = null, $customerId = null, $campaignId = null, $deliveryMethod = null, $status = null)
    {
        try {
            // Also log to Laravel's log system for backup
            Log::channel('rekyc_system')->{$level}($message, $context);

            // Create database log entry
            $logData = [
                'rekyc_link_id' => $rekycLinkId,
                'customer_id' => $customerId,
                'campaign_id' => $campaignId,
                'level' => $level,
                'action' => $action,
                'message' => $message,
                'context' => $context,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'delivery_method' => $deliveryMethod,
                'status' => $status,
                'sent_at' => $status === RekycLog::STATUS_SENT ? now() : null
            ];

            return RekycLog::create($logData);

        } catch (Exception $e) {
            // Fallback to Laravel log if database logging fails
            Log::channel('rekyc_system')->error('Failed to log to database: ' . $e->getMessage(), [
                'original_message' => $message,
                'context' => $context,
                'action' => $action,
                'level' => $level
            ]);
            return null;
        }
    }

    /**
     * Log email sending activity
     */
    public static function logEmailSent($rekycLinkId, $customerId, $campaignId, $message = 'Email sent successfully', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_EMAIL_SENT,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            RekycLog::DELIVERY_EMAIL,
            RekycLog::STATUS_SENT
        );
    }

    /**
     * Log email sending failure
     */
    public static function logEmailFailed($rekycLinkId, $customerId, $campaignId, $errorMessage, $context = [])
    {
        return self::log(
            RekycLog::LEVEL_ERROR,
            RekycLog::ACTION_EMAIL_SENT,
            'Email sending failed: ' . $errorMessage,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            RekycLog::DELIVERY_EMAIL,
            RekycLog::STATUS_FAILED
        );
    }

    /**
     * Log SMS sending activity
     */
    public static function logSmsSent($rekycLinkId, $customerId, $campaignId, $message = 'SMS sent successfully', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_SMS_SENT,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            RekycLog::DELIVERY_SMS,
            RekycLog::STATUS_SENT
        );
    }

    /**
     * Log SMS sending failure
     */
    public static function logSmsFailed($rekycLinkId, $customerId, $campaignId, $errorMessage, $context = [])
    {
        return self::log(
            RekycLog::LEVEL_ERROR,
            RekycLog::ACTION_SMS_SENT,
            'SMS sending failed: ' . $errorMessage,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            RekycLog::DELIVERY_SMS,
            RekycLog::STATUS_FAILED
        );
    }

    /**
     * Log WhatsApp sending activity
     */
    public static function logWhatsappSent($rekycLinkId, $customerId, $campaignId, $message = 'WhatsApp message sent successfully', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_WHATSAPP_SENT,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            RekycLog::DELIVERY_WHATSAPP,
            RekycLog::STATUS_SENT
        );
    }

    /**
     * Log WhatsApp sending failure
     */
    public static function logWhatsappFailed($rekycLinkId, $customerId, $campaignId, $errorMessage, $context = [])
    {
        return self::log(
            RekycLog::LEVEL_ERROR,
            RekycLog::ACTION_WHATSAPP_SENT,
            'WhatsApp sending failed: ' . $errorMessage,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            RekycLog::DELIVERY_WHATSAPP,
            RekycLog::STATUS_FAILED
        );
    }

    /**
     * Log link creation
     */
    public static function logLinkCreated($rekycLinkId, $customerId, $campaignId, $message = 'Re-KYC link created', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_LINK_CREATED,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId
        );
    }

    /**
     * Log link opened
     */
    public static function logLinkOpened($rekycLinkId, $customerId, $campaignId, $message = 'Re-KYC link opened', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_LINK_OPENED,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId
        );
    }

    /**
     * Log link completion
     */
    public static function logLinkCompleted($rekycLinkId, $customerId, $campaignId, $message = 'Re-KYC process completed', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_LINK_COMPLETED,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            null,
            RekycLog::STATUS_COMPLETED
        );
    }

    /**
     * Log link expiration
     */
    public static function logLinkExpired($rekycLinkId, $customerId, $campaignId, $message = 'Re-KYC link expired', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_WARNING,
            RekycLog::ACTION_LINK_EXPIRED,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId,
            null,
            RekycLog::STATUS_EXPIRED
        );
    }

    /**
     * Log OTP verification
     */
    public static function logOtpVerified($rekycLinkId, $customerId, $campaignId, $message = 'OTP verified successfully', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_OTP_VERIFIED,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId
        );
    }

    /**
     * Log OTP verification failure
     */
    public static function logOtpFailed($rekycLinkId, $customerId, $campaignId, $message = 'OTP verification failed', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_WARNING,
            RekycLog::ACTION_OTP_FAILED,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId
        );
    }

    /**
     * Log campaign creation
     */
    public static function logCampaignCreated($campaignId, $message = 'Re-KYC campaign created', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_CAMPAIGN_CREATED,
            $message,
            $context,
            null,
            null,
            $campaignId
        );
    }

    /**
     * Log campaign started
     */
    public static function logCampaignStarted($campaignId, $message = 'Re-KYC campaign started', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_CAMPAIGN_STARTED,
            $message,
            $context,
            null,
            null,
            $campaignId
        );
    }

    /**
     * Log campaign completion
     */
    public static function logCampaignCompleted($campaignId, $message = 'Re-KYC campaign completed', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_CAMPAIGN_COMPLETED,
            $message,
            $context,
            null,
            null,
            $campaignId
        );
    }

    /**
     * Log escalation sent
     */
    public static function logEscalationSent($rekycLinkId, $customerId, $campaignId, $message = 'Escalation notification sent', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_WARNING,
            RekycLog::ACTION_ESCALATION_SENT,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId
        );
    }

    /**
     * Log reminder sent
     */
    public static function logReminderSent($rekycLinkId, $customerId, $campaignId, $message = 'Reminder notification sent', $context = [])
    {
        return self::log(
            RekycLog::LEVEL_INFO,
            RekycLog::ACTION_REMINDER_SENT,
            $message,
            $context,
            $rekycLinkId,
            $customerId,
            $campaignId
        );
    }

    /**
     * Get logs for a specific rekyc link
     */
    public static function getLogsForLink($rekycLinkId, $limit = 50)
    {
        return RekycLog::forRekycLink($rekycLinkId)
            ->with(['customer', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs for a specific customer
     */
    public static function getLogsForCustomer($customerId, $limit = 50)
    {
        return RekycLog::forCustomer($customerId)
            ->with(['rekycLink', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs for a specific campaign
     */
    public static function getLogsForCampaign($campaignId, $limit = 100)
    {
        return RekycLog::forCampaign($campaignId)
            ->with(['rekycLink', 'customer'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get error logs
     */
    public static function getErrorLogs($limit = 100)
    {
        return RekycLog::errors()
            ->with(['rekycLink', 'customer', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by date range
     */
    public static function getLogsByDateRange($startDate, $endDate, $limit = 200)
    {
        return RekycLog::dateRange($startDate, $endDate)
            ->with(['rekycLink', 'customer', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by action
     */
    public static function getLogsByAction($action, $limit = 100)
    {
        return RekycLog::action($action)
            ->with(['rekycLink', 'customer', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by delivery method
     */
    public static function getLogsByDeliveryMethod($method, $limit = 100)
    {
        return RekycLog::deliveryMethod($method)
            ->with(['rekycLink', 'customer', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics for logs
     */
    public static function getLogStatistics($campaignId = null, $period = 'last_30_days')
    {
        $query = RekycLog::query();
        
        if ($campaignId) {
            $query->forCampaign($campaignId);
        }
        
        $query->forPeriod($period);
        
        $total = $query->count();
        $errors = $query->clone()->errors()->count();
        $warnings = $query->clone()->warnings()->count();
        $successful = $query->clone()->successful()->count();
        $failed = $query->clone()->failed()->count();
        
        $byAction = $query->clone()
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get();
            
        $byLevel = $query->clone()
            ->selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->orderBy('count', 'desc')
            ->get();
            
        $byDeliveryMethod = $query->clone()
            ->selectRaw('delivery_method, COUNT(*) as count')
            ->groupBy('delivery_method')
            ->orderBy('count', 'desc')
            ->get();
        
        return [
            'total' => $total,
            'errors' => $errors,
            'warnings' => $warnings,
            'successful' => $successful,
            'failed' => $failed,
            'by_action' => $byAction,
            'by_level' => $byLevel,
            'by_delivery_method' => $byDeliveryMethod,
            'error_rate' => $total > 0 ? round(($errors / $total) * 100, 2) : 0,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0
        ];
    }

    /**
     * Clean up old logs (older than specified days)
     */
    public static function cleanupOldLogs($days = 90)
    {
        $cutoffDate = now()->subDays($days);
        
        $deletedCount = RekycLog::where('created_at', '<', $cutoffDate)->delete();
        
        self::info(
            'log_cleanup',
            "Cleaned up {$deletedCount} old log entries older than {$days} days",
            ['deleted_count' => $deletedCount, 'cutoff_date' => $cutoffDate]
        );
        
        return $deletedCount;
    }
}
