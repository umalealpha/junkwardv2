<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use AlphaDirect\Customer;

class AdGroupKycCampaign extends Model
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
        'updated_by',
        'employer_group_id',
        'product_id'
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
        return $this->hasMany(AdGroupKycLink::class, 'campaign_id');
    }

    /**
     * Get the activities for this campaign through links
     */
    public function activities()
    {
        return $this->hasManyThrough(
            AdGroupKycActivity::class,
            AdGroupKycLink::class,
            'campaign_id', // Foreign key on ad_group_kyc_links table
            'link_id', // Foreign key on ad_group_kyc_activities table
            'id', // Local key on ad_group_kyc_campaigns table
            'id' // Local key on ad_group_kyc_links table
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
     * Get the employer group for this campaign
     */
    public function employerGroup(): BelongsTo
    {
        return $this->belongsTo(EmployerGroup::class, 'employer_group_id', 'employer_group_id');
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
     * Get completion rate for this campaign
     */
    public function getCompletionRateAttribute()
    {
        $totalLinks = $this->links()->count();
        if ($totalLinks === 0) {
            return 0;
        }
        
        $completedLinks = $this->links()->where('status', 'completed')->count();
        return round(($completedLinks / $totalLinks) * 100, 1);
    }
}

