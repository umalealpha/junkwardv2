<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Customer;
use AlphaDirect\User;

class DuplicateCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'email',
        'cellphone',
        'omang_number',
        'passport_number',
        'bank_account_number',
        'bank_name',
        'bank_branch',
        'billing',
        'duplicate_type',
        'duplicate_reason',
        'duplicate_details',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'duplicate_details' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the customer that owns the duplicate record
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who resolved the duplicate
     */
    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope for pending duplicates
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for reviewed duplicates
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope for resolved duplicates
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope for ignored duplicates
     */
    public function scopeIgnored($query)
    {
        return $query->where('status', 'ignored');
    }

    /**
     * Scope by duplicate type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('duplicate_type', $type);
    }

    /**
     * Mark as reviewed
     */
    public function markAsReviewed($notes = null)
    {
        $this->update([
            'status' => 'reviewed',
            'notes' => $notes
        ]);
    }

    /**
     * Mark as resolved
     */
    public function markAsResolved($resolvedBy, $notes = null)
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $resolvedBy,
            'resolution_notes' => $notes,
            'resolved_at' => now()
        ]);
    }

    /**
     * Mark as ignored
     */
    public function markAsIgnored($notes = null)
    {
        $this->update([
            'status' => 'ignored',
            'notes' => $notes
        ]);
    }
}
