<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use AlphaDirect\Customer;

class RekycActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_id',
        'customer_id',
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
        return $this->belongsTo(RekycLink::class, 'link_id');
    }

    /**
     * Get the customer for this activity
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Create a new activity log entry
     */
    public static function logActivity(
        ?int $linkId,
        ?int $customerId,
        string $activityType,
        string $description = null,
        array $metadata = null,
        string $ipAddress = null,
        string $userAgent = null,
        array $deviceInfo = null
    ): self {
        return self::create([
            'link_id' => $linkId,
            'customer_id' => $customerId,
            'activity_type' => $activityType,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_info' => $deviceInfo,
            'occurred_at' => now()
        ]);
    }

    /**
     * Scope for specific activity types
     */
    public function scopeOfType($query, string $activityType)
    {
        return $query->where('activity_type', $activityType);
    }

    /**
     * Scope for activities within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Scope for activities by customer
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope for activities by link
     */
    public function scopeForLink($query, int $linkId)
    {
        return $query->where('link_id', $linkId);
    }
}
