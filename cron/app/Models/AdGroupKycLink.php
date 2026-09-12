<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;
use AlphaDirect\Customer;

class AdGroupKycLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'customer_id',
        'policy_id',
        'unique_token',
        'otp_code',
        'status',
        'delivery_method',
        'delivery_reference',
        'sent_at',
        'opened_at',
        'otp_verified_at',
        'completed_at',
        'expires_at',
        'otp_attempts',
        'ip_address',
        'user_agent',
        'device_info',
        'consent_data'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'device_info' => 'array',
        'consent_data' => 'array',
    ];

    /**
     * Boot method to generate unique token
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->unique_token)) {
                $model->unique_token = Str::random(12);
            }
            if (empty($model->otp_code)) {
                $model->otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            if (empty($model->expires_at)) {
                $model->expires_at = Carbon::now()->addDays(30);
            }
        });
    }

    /**
     * Get the campaign for this link
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdGroupKycCampaign::class, 'campaign_id');
    }

    /**
     * Get the customer for this link
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the policy for this link
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(\AlphaDirect\Policy::class, 'policy_id');
    }

    /**
     * Get the activities for this link
     */
    public function activities(): HasMany
    {
        return $this->hasMany(AdGroupKycActivity::class, 'link_id');
    }

    /**
     * Check if the link is expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the link is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the link is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the link is sent
     */
    public function isSent()
    {
        return $this->status === 'sent';
    }

    /**
     * Mark link as sent
     */
    public function markAsSent($deliveryMethod = null, $deliveryReference = null)
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'delivery_method' => $deliveryMethod,
            'delivery_reference' => $deliveryReference
        ]);
    }

    /**
     * Mark link as opened
     */
    public function markAsOpened($ipAddress = null, $userAgent = null)
    {
        $this->update([
            'status' => 'opened',
            'opened_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    /**
     * Mark link as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * Generate the KYC link URL using START_URL
     */
    public function getKycUrlAttribute()
    {
        return env('START_URL')."adgroupkyc/access/{$this->unique_token}";
    }

    /**
     * Generate secure access URL (alias for getKycUrlAttribute)
     */
    public function getAccessUrl(): string
    {
        return env('START_URL')."adgroupkyc/access/{$this->unique_token}";
    }
}
