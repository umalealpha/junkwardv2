<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Customer;

class RekycDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_id',
        'customer_id',
        'document_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'file_hash',
        'status',
        'ocr_data',
        'validation_results',
        'fraud_analysis',
        'verification_notes',
        'verified_by',
        'verified_at'
    ];

    protected $casts = [
        'ocr_data' => 'array',
        'validation_results' => 'array',
        'fraud_analysis' => 'array',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the link for this document
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(RekycLink::class, 'link_id');
    }

    /**
     * Get the customer for this document
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the user who verified this document
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the file URL
     */
    public function getFileUrl(): string
    {
        // Check if the file path is already a full URL (S3 URL)
        if (filter_var($this->file_path, FILTER_VALIDATE_URL)) {
            return $this->file_path;
        }
        
        // Otherwise, generate URL from storage
        return Storage::url($this->file_path);
    }

    /**
     * Get the file content
     */
    public function getFileContent(): string
    {
        // Check if the file path is already a full URL (S3 URL)
        if (filter_var($this->file_path, FILTER_VALIDATE_URL)) {
            // For S3 URLs, we need to use the S3 disk
            $s3BaseUrl = config('filesystems.disks.s3.url') ?: 'https://' . config('filesystems.disks.s3.bucket') . '.s3.' . config('filesystems.disks.s3.region') . '.amazonaws.com/';
            $s3Path = str_replace($s3BaseUrl, '', $this->file_path);
            return Storage::disk('s3')->get($s3Path);
        }
        
        // Otherwise, get from default storage
        return Storage::get($this->file_path);
    }

    /**
     * Check if document is verified
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Check if document is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Mark document as verified
     */
    public function markAsVerified(int $verifiedBy, string $notes = null): void
    {
        $this->update([
            'status' => 'verified',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'verification_notes' => $notes
        ]);
    }

    /**
     * Mark document as rejected
     */
    public function markAsRejected(int $verifiedBy, string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'verification_notes' => $notes
        ]);
    }

    /**
     * Scope for verified documents
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Scope for pending documents
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['uploaded', 'processing']);
    }

    /**
     * Scope for rejected documents
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for specific document types
     */
    public function scopeOfType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }
}
