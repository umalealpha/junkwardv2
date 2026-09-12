<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RekycLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'rekyc_link_id',
        'customer_id',
        'campaign_id',
        'level',
        'action',
        'message',
        'context',
        'ip_address',
        'user_agent',
        'delivery_method',
        'status',
        'sent_at'
    ];

    protected $casts = [
        'context' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Log levels
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_DEBUG = 'debug';

    // Common actions
    const ACTION_LINK_CREATED = 'link_created';
    const ACTION_EMAIL_SENT = 'email_sent';
    const ACTION_SMS_SENT = 'sms_sent';
    const ACTION_WHATSAPP_SENT = 'whatsapp_sent';
    const ACTION_LINK_OPENED = 'link_opened';
    const ACTION_LINK_COMPLETED = 'link_completed';
    const ACTION_LINK_EXPIRED = 'link_expired';
    const ACTION_OTP_VERIFIED = 'otp_verified';
    const ACTION_OTP_FAILED = 'otp_failed';
    const ACTION_CAMPAIGN_CREATED = 'campaign_created';
    const ACTION_CAMPAIGN_STARTED = 'campaign_started';
    const ACTION_CAMPAIGN_COMPLETED = 'campaign_completed';
    const ACTION_ESCALATION_SENT = 'escalation_sent';
    const ACTION_REMINDER_SENT = 'reminder_sent';

    // Status values
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';

    // Delivery methods
    const DELIVERY_EMAIL = 'email';
    const DELIVERY_SMS = 'sms';
    const DELIVERY_WHATSAPP = 'whatsapp';
    const DELIVERY_ALL_CHANNELS = 'all_channels';

    /**
     * Get the rekyc link that owns the log.
     */
    public function rekycLink(): BelongsTo
    {
        return $this->belongsTo(RekycLink::class);
    }

    /**
     * Get the customer that owns the log.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the campaign that owns the log.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RekycCampaign::class);
    }

    /**
     * Scope a query to only include logs of a given level.
     */
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to only include logs of a given action.
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs for a specific customer.
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope a query to only include logs for a specific campaign.
     */
    public function scopeForCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope a query to only include logs for a specific rekyc link.
     */
    public function scopeForRekycLink($query, $rekycLinkId)
    {
        return $query->where('rekyc_link_id', $rekycLinkId);
    }

    /**
     * Scope a query to only include logs within a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include logs with a specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get logs by delivery method.
     */
    public function scopeDeliveryMethod($query, $method)
    {
        return $query->where('delivery_method', $method);
    }

    /**
     * Get recent logs (last 24 hours).
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    /**
     * Get error logs only.
     */
    public function scopeErrors($query)
    {
        return $query->where('level', self::LEVEL_ERROR);
    }

    /**
     * Get warning logs only.
     */
    public function scopeWarnings($query)
    {
        return $query->where('level', self::LEVEL_WARNING);
    }

    /**
     * Get successful logs only.
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_COMPLETED]);
    }

    /**
     * Get failed logs only.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Get logs for a specific time period.
     */
    public function scopeForPeriod($query, $period = 'today')
    {
        switch ($period) {
            case 'today':
                return $query->whereDate('created_at', today());
            case 'yesterday':
                return $query->whereDate('created_at', yesterday());
            case 'this_week':
                return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            case 'this_month':
                return $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            case 'last_7_days':
                return $query->where('created_at', '>=', now()->subDays(7));
            case 'last_30_days':
                return $query->where('created_at', '>=', now()->subDays(30));
            default:
                return $query;
        }
    }

    /**
     * Get formatted context data.
     */
    public function getFormattedContextAttribute()
    {
        if (empty($this->context)) {
            return null;
        }

        return json_encode($this->context, JSON_PRETTY_PRINT);
    }

    /**
     * Get human readable time ago.
     */
    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get log level badge color.
     */
    public function getLevelBadgeColorAttribute()
    {
        return match($this->level) {
            self::LEVEL_ERROR => 'danger',
            self::LEVEL_WARNING => 'warning',
            self::LEVEL_INFO => 'info',
            self::LEVEL_DEBUG => 'secondary',
            default => 'primary'
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            self::STATUS_SENT, self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_EXPIRED => 'warning',
            self::STATUS_PENDING => 'info',
            default => 'secondary'
        };
    }
}
