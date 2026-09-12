<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPoint extends Model
{
    use HasFactory;

    protected $table = 'customer_point';

    protected $fillable = [
        'customer_id',
        'name',
        'point',
        'expire_at',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
        'point' => 'integer',
    ];

    /**
     * Get the customer that owns the point.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope to get active (non-expired) points
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expire_at')
              ->orWhere('expire_at', '>', now());
        });
    }

    /**
     * Scope to get expired points
     */
    public function scopeExpired($query)
    {
        return $query->where('expire_at', '<=', now());
    }
} 