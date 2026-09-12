<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PolicyAppliedDiscount extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $auditTimestamps = true;
    protected $table = 'policy_applied_discounts';

    protected $fillable = [
        'policyNumber',
        'discount_rate',
        'discount_amount',
        'original_premium',
        'calculated_months',
        'status',
        'action_by',
        'notes',
        'applied_at'
    ];

    protected $casts = [
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'original_premium' => 'decimal:2',
        'applied_at' => 'datetime'
    ];

    protected $dates = [
        'applied_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the user who applied the discount
     */
    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    /**
     * Get the policy associated with this discount
     */
    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policyNumber', 'policyNumber');
    }

    /**
     * Check if a policy has an active discount
     */
    public static function hasActiveDiscount($policyNumber)
    {
        return self::where('policyNumber', $policyNumber)
                   ->where('status', 'applied')
                   ->exists();
    }

    /**
     * Get the latest active discount for a policy
     */
    public static function getActiveDiscount($policyNumber)
    {
        return self::where('policyNumber', $policyNumber)
                   ->where('status', 'applied')
                   ->latest('applied_at')
                   ->first();
    }

    /**
     * Check if policy qualifies for new discount (higher rate)
     */
    public static function qualifiesForNewDiscount($policyNumber, $newDiscountRate)
    {
        $activeDiscount = self::getActiveDiscount($policyNumber);
        
        if (!$activeDiscount) {
            return true; // No discount applied yet
        }
        
        return $newDiscountRate > $activeDiscount->discount_rate;
    }

    /**
     * Supersede previous discount with new one
     */
    public static function supersedePreviousDiscount($policyNumber, $reason = 'Higher discount rate available')
    {
        return self::where('policyNumber', $policyNumber)
                   ->where('status', 'applied')
                   ->update([
                       'status' => 'superseded',
                       'notes' => $reason,
                       'updated_at' => now()
                   ]);
    }
}