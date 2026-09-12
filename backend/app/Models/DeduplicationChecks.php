<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Customer;

class DeduplicationChecks extends Model
{
    use HasFactory;

    protected $table = 'deduplication_checks';

    protected $fillable = [
        'customer_id',
        'unique_customer_id',
        'omang_number',
        'passport_number',
        'bank_account_number',
        'cellphone',
        'email',
        'bank_statement_upload_url',
        'bank_statement_file_path',
        'bank_statement_file_name',
        'bank_statement_mime_type',
        'bank_statement_file_size',
        'bank_statement_file_hash',
        'otp_code',
        'otp_sent_at',
        'otp_verified_at',
        'otp_attempts',
        'otp_expires_at',
        'unique_access_token',
        'ip_address',
        'user_agent',
        'link_opened_at',
        'link_expires_at',
        'document_upload_status',
        'manual_verification_status',
        'verification_notes',
        'verified_by',
        'verified_at',
        'policy_id',
        'policy_created_at',
        'suspension_due_date',
        'is_suspended',
        'suspended_at',
        'suspension_reason',
        'status',
        'access_logs',
        'device_info',
        'notes'
    ];

    protected $casts = [
        'otp_sent_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'link_opened_at' => 'datetime',
        'link_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'policy_created_at' => 'datetime',
        'suspension_due_date' => 'datetime',
        'suspended_at' => 'datetime',
        'access_logs' => 'array',
        'device_info' => 'array',
        'is_suspended' => 'boolean'
    ];

    /**
     * Get the customer that owns the deduplication check
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the policy associated with this deduplication check
     */
    public function policy()
    {
        return $this->belongsTo(\AlphaDirect\Policy::class, 'policy_id');
    }

    /**
     * Get the user who verified this check
     */
    public function verifier()
    {
        return $this->belongsTo(\AlphaDirect\User::class, 'verified_by');
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->link_expires_at && $this->link_expires_at->isPast();
    }

    /**
     * Check if the OTP is expired
     */
    public function isOtpExpired(): bool
    {
        return $this->otp_expires_at && $this->otp_expires_at->isPast();
    }

    /**
     * Check if the link has been used
     */
    public function isUsed(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the document has been uploaded
     */
    public function hasDocumentUploaded(): bool
    {
        return !empty($this->bank_statement_upload_url);
    }

    /**
     * Get the masked phone number
     */
    public function getMaskedPhoneAttribute(): string
    {
        if (!$this->cellphone) {
            return '';
        }

        if (strlen($this->cellphone) < 4) {
            return str_repeat('*', strlen($this->cellphone));
        }
        
        return substr($this->cellphone, 0, 2) . str_repeat('*', strlen($this->cellphone) - 4) . substr($this->cellphone, -2);
    }

    /**
     * Get the masked email
     */
    public function getMaskedEmailAttribute(): string
    {
        if (!$this->email) {
            return '';
        }

        if (strpos($this->email, '@') === false) {
            return $this->email;
        }
        
        list($local, $domain) = explode('@', $this->email);
        if (strlen($local) <= 2) {
            return str_repeat('*', strlen($local)) . '@' . $domain;
        }
        
        return substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1) . '@' . $domain;
    }

    /**
     * Scope for active deduplication checks
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for expired deduplication checks
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
                    ->orWhere('link_expires_at', '<', now());
    }

    /**
     * Scope for completed deduplication checks
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for suspended deduplication checks
     */
    public function scopeSuspended($query)
    {
        return $query->where('is_suspended', true);
    }
    public function getAccessUrl(): string
    {
        return env('START_URL')."bankstatement/access/{$this->unique_access_token}";
    }
}
