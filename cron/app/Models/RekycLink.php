<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;
use AlphaDirect\Customer;

class RekycLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'customer_id',
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
        });
    }

    /**
     * Get the campaign for this link
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RekycCampaign::class, 'campaign_id');
    }

    /**
     * Get the customer for this link
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the activities for this link
     */
    public function activities(): HasMany
    {
        return $this->hasMany(RekycActivity::class, 'link_id');
    }

    /**
     * Get the documents for this link
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RekycDocument::class, 'link_id');
    }

    /**
     * Check if link is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Check if OTP is expired
     */
    public function isOtpExpired(): bool
    {
        if (!$this->otp_verified_at) {
            return false;
        }
        
        $otpExpiryMinutes = $this->campaign->otp_expiry_minutes ?? 10;
        return $this->otp_verified_at->addMinutes($otpExpiryMinutes) < now();
    }

    /**
     * Check if maximum OTP attempts exceeded
     */
    public function hasExceededMaxAttempts(): bool
    {
        $maxAttempts = $this->campaign->max_attempts ?? 3;
        return $this->otp_attempts >= $maxAttempts;
    }

    /**
     * Generate secure access URL
     */
    public function getAccessUrl(): string
    {
        return env('START_URL')."rekyc/access/{$this->unique_token}";
    }

    /**
     * Mark link as opened
     */
    public function markAsOpened(string $ipAddress = null, string $userAgent = null): void
    {
        if (!$this->opened_at) {
            $this->update([
                'opened_at' => now(),
                'status' => 'opened',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ]);
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(string $otpCode): bool
    {
        if ($this->hasExceededMaxAttempts() || $this->isExpired()) {
            return false;
        }

        $this->increment('otp_attempts');

        if ($this->otp_code === $otpCode) {
            $this->update([
                'otp_verified_at' => now(),
                'status' => 'otp_verified'
            ]);
            return true;
        }

        return false;
    }

    /**
     * Mark link as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'completed_at' => now(),
            'status' => 'completed'
        ]);
    }

    /**
     * Scope for active links
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'expired')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired links
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
                    ->orWhere('status', 'expired');
    }

    /**
     * Get decrypted consent data
     */
    public function getDecryptedConsentDataAttribute()
    {
        if (!$this->consent_data) {
            return null;
        }

        $securityService = app(\AlphaDirect\Services\RekycSecurityService::class);
        return $securityService->decryptData($this->consent_data);
    }
}
