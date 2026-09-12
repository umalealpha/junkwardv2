<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use AlphaDirect\Customer;

class RekycCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'settings',
        'escalation_days',
        'reminder_days',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'settings' => 'array',
        'reminder_days' => 'array',
        'escalation_days' => 'integer',
    ];

    /**
     * Get the links for this campaign
     */
    public function links(): HasMany
    {
        return $this->hasMany(RekycLink::class, 'campaign_id');
    }

    /**
     * Get the activities for this campaign through links
     */
    public function activities()
    {
        return $this->hasManyThrough(
            RekycActivity::class,
            RekycLink::class,
            'campaign_id', // Foreign key on rekyc_links table
            'link_id', // Foreign key on rekyc_activities table
            'id', // Local key on rekyc_campaigns table
            'id' // Local key on rekyc_links table
        );
    }

    /**
     * Get the user who created this campaign
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this campaign
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for active campaigns
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for campaigns within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if campaign is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get campaign statistics
     */
    public function getStatistics(): array
    {
        $links = $this->links();
        
        return [
            'total_links' => $links->count(),
            'sent_links' => $links->whereNotNull('sent_at')->count(),
            'opened_links' => $links->whereNotNull('opened_at')->count(),
            'completed_links' => $links->where('status', 'completed')->count(),
            'expired_links' => $links->where('expires_at', '<', now())->count(),
            'completion_rate' => $links->count() > 0 ? 
                round(($links->where('status', 'completed')->count() / $links->count()) * 100, 2) : 0
        ];
    }
}
