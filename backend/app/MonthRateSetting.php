<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MonthRateSetting extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    
    protected $auditTimestamps = true;
    protected $table = 'month_rate_settings';
    
    protected $fillable = [
        'min_months',
        'max_months', 
        'rate_percentage',
        'description',
        'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'rate_percentage' => 'decimal:2'
    ];
    
    /**
     * Get the applicable rate for given months
     * 
     * @param int $months
     * @return MonthRateSetting|null
     */
    public static function getRateForMonths($months)
    {
        return self::where('is_active', true)
            ->where('min_months', '<=', $months)
            ->where(function ($query) use ($months) {
                $query->whereNull('max_months')
                      ->orWhere('max_months', '>', $months);
            })
            ->first();
    }
    
    /**
     * Get all active rates ordered by min_months
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveRates()
    {
        return self::where('is_active', true)
            ->orderBy('min_months')
            ->get();
    }
    
    /**
     * Scope to get only active settings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}