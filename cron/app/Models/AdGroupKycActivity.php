<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use AlphaDirect\Customer;

class AdGroupKycActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_id',
        'customer_id',
        'policy_id',
        'activity_type',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'device_info',
        'occurred_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'device_info' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the action attribute (alias for activity_type)
     */
    public function getActionAttribute()
    {
        return $this->activity_type;
    }

    /**
     * Set the action attribute (alias for activity_type)
     */
    public function setActionAttribute($value)
    {
        $this->activity_type = $value;
    }

    /**
     * Get the created_at attribute (alias for occurred_at)
     */
    public function getCreatedAtAttribute()
    {
        return $this->occurred_at;
    }

    /**
     * Set the created_at attribute (alias for occurred_at)
     */
    public function setCreatedAtAttribute($value)
    {
        $this->occurred_at = $value;
    }

    /**
     * Get the link for this activity
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(AdGroupKycLink::class, 'link_id');
    }

    /**
     * Get the customer for this activity
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the policy for this activity
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(\AlphaDirect\Policy::class, 'policy_id');
    }

    /**
     * Create a new activity log entry
     */
    public static function logActivity(
        ?int $linkId,
        ?int $customerId,
        ?int $policyId,
        string $activityType,
        string $description,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $deviceInfo = []
    ) {
        return self::create([
            'link_id' => $linkId,
            'customer_id' => $customerId,
            'policy_id' => $policyId,
            'activity_type' => $activityType,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_info' => $deviceInfo,
            'occurred_at' => now()
        ]);
    }

    // Activity type constants
    const TYPE_LINK_CREATED = 'link_created';
    const TYPE_EMAIL_SENT = 'email_sent';
    const TYPE_SMS_SENT = 'sms_sent';
    const TYPE_WHATSAPP_SENT = 'whatsapp_sent';
    const TYPE_LINK_OPENED = 'link_opened';
    const TYPE_OTP_VERIFIED = 'otp_verified';
    const TYPE_OTP_FAILED = 'otp_failed';
    const TYPE_KYC_COMPLETED = 'kyc_completed';
    const TYPE_KYC_FAILED = 'kyc_failed';
    const TYPE_LINK_EXPIRED = 'link_expired';
    const TYPE_DOCUMENT_UPLOADED = 'document_uploaded';
    const TYPE_DOCUMENT_VERIFIED = 'document_verified';
    const TYPE_DOCUMENT_REJECTED = 'document_rejected';
}

