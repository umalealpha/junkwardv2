<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;
use AlphaDirect\Customer;

class DeduplicationCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'unique_customer_id',
        'omang_number',
        'passport_number',
        'bank_account_number',
        'bank_name',
        'bank_branch',
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
        'is_suspended' => 'boolean',
    ];

    /**
     * Boot method to generate unique tokens and customer ID
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->unique_customer_id)) {
                $model->unique_customer_id = $model->generateUniqueCustomerId();
            }
            if (empty($model->unique_access_token)) {
                $model->unique_access_token = Str::random(32);
            }
            if (empty($model->otp_code)) {
                $model->otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            if (empty($model->link_expires_at)) {
                $model->link_expires_at = now()->addDays(30); // 30 days expiry
            }
            if (empty($model->otp_expires_at)) {
                $model->otp_expires_at = now()->addMinutes(10); // 10 minutes OTP expiry
            }
        });
    }

    /**
     * Generate unique customer ID based on customer data
     */
    public function generateUniqueCustomerId(): string
    {
        $customer = Customer::find($this->customer_id);
        if (!$customer) {
            return 'CUST_' . strtoupper(Str::random(8));
        }

        // Create a unique ID based on customer data
        $baseId = 'CUST_' . strtoupper(substr($customer->first_name, 0, 2)) . 
                  strtoupper(substr($customer->last_name, 0, 2)) . 
                  substr($customer->id, -4);
        
        // Ensure uniqueness
        $counter = 1;
        $uniqueId = $baseId;
        while (static::where('unique_customer_id', $uniqueId)->exists()) {
            $uniqueId = $baseId . '_' . $counter;
            $counter++;
        }
        
        return $uniqueId;
    }

    /**
     * Get the customer for this deduplication check
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the policy for this deduplication check
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(\AlphaDirect\Models\Policy::class, 'policy_id');
    }

    /**
     * Get the user who verified this check
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'verified_by');
    }

    /**
     * Check if OTP is expired
     */
    public function isOtpExpired(): bool
    {
        return $this->otp_expires_at && $this->otp_expires_at < now();
    }

    /**
     * Check if link is expired
     */
    public function isLinkExpired(): bool
    {
        return $this->link_expires_at && $this->link_expires_at < now();
    }

    /**
     * Check if maximum OTP attempts exceeded
     */
    public function hasExceededMaxOtpAttempts(): bool
    {
        return $this->otp_attempts >= 3; // Maximum 3 attempts
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(string $otpCode): bool
    {
        if ($this->hasExceededMaxOtpAttempts() || $this->isOtpExpired()) {
            return false;
        }

        $this->increment('otp_attempts');

        if ($this->otp_code === $otpCode) {
            $this->update([
                'otp_verified_at' => now(),
                'status' => 'active'
            ]);
            return true;
        }

        return false;
    }

    /**
     * Mark link as opened
     */
    public function markAsOpened(string $ipAddress = null, string $userAgent = null): void
    {
        if (!$this->link_opened_at) {
            $this->update([
                'link_opened_at' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ]);
        }

        // Log access attempt
        $accessLogs = $this->access_logs ?? [];
        $accessLogs[] = [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'accessed_at' => now()->toISOString()
        ];
        $this->update(['access_logs' => $accessLogs]);
    }

    /**
     * Generate secure access URL
     */
    public function getAccessUrl(): string
    {
        return env('START_URL') . "deduplication/access/{$this->unique_access_token}";
    }

    /**
     * Upload bank statement
     */
    public function uploadBankStatement(array $fileData): bool
    {
        $this->update([
            'bank_statement_file_path' => $fileData['file_path'],
            'bank_statement_file_name' => $fileData['file_name'],
            'bank_statement_mime_type' => $fileData['mime_type'],
            'bank_statement_file_size' => $fileData['file_size'],
            'bank_statement_file_hash' => $fileData['file_hash'],
            'document_upload_status' => 'uploaded'
        ]);

        return true;
    }

    /**
     * Check for duplicate documents
     */
    public function checkForDuplicates(): array
    {
        $duplicates = [];

        // Check for duplicate Omang
        if ($this->omang_number) {
            $omangDuplicates = static::where('omang_number', $this->omang_number)
                ->where('id', '!=', $this->id)
                ->get();
            if ($omangDuplicates->count() > 0) {
                $duplicates['omang'] = $omangDuplicates;
            }
        }

        // Check for duplicate Passport
        if ($this->passport_number) {
            $passportDuplicates = static::where('passport_number', $this->passport_number)
                ->where('id', '!=', $this->id)
                ->get();
            if ($passportDuplicates->count() > 0) {
                $duplicates['passport'] = $passportDuplicates;
            }
        }

        // Check for duplicate Bank Account
        if ($this->bank_account_number) {
            $bankDuplicates = static::where('bank_account_number', $this->bank_account_number)
                ->where('id', '!=', $this->id)
                ->get();
            if ($bankDuplicates->count() > 0) {
                $duplicates['bank_account'] = $bankDuplicates;
            }
        }

        // Check for duplicate Cellphone
        if ($this->cellphone) {
            $cellphoneDuplicates = static::where('cellphone', $this->cellphone)
                ->where('id', '!=', $this->id)
                ->get();
            if ($cellphoneDuplicates->count() > 0) {
                $duplicates['cellphone'] = $cellphoneDuplicates;
            }
        }

        // Check for duplicate Email
        if ($this->email) {
            $emailDuplicates = static::where('email', $this->email)
                ->where('id', '!=', $this->id)
                ->get();
            if ($emailDuplicates->count() > 0) {
                $duplicates['email'] = $emailDuplicates;
            }
        }

        return $duplicates;
    }

    /**
     * Set policy and calculate suspension date
     */
    public function setPolicy(int $policyId): void
    {
        $this->update([
            'policy_id' => $policyId,
            'policy_created_at' => now(),
            'suspension_due_date' => now()->addDays(90) // 90 days from policy creation
        ]);
    }

    /**
     * Check if policy should be suspended
     */
    public function shouldSuspendPolicy(): bool
    {
        if (!$this->policy_id || $this->is_suspended) {
            return false;
        }

        // Check if 90 days have passed and bank statement not uploaded
        return $this->suspension_due_date && 
               $this->suspension_due_date <= now() && 
               $this->document_upload_status !== 'uploaded';
    }

    /**
     * Suspend policy
     */
    public function suspendPolicy(string $reason = null): void
    {
        $this->update([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_reason' => $reason ?? 'Bank statement not uploaded within 90 days',
            'status' => 'suspended'
        ]);
    }

    /**
     * Approve manual verification
     */
    public function approveVerification(int $verifiedBy, string $notes = null): void
    {
        $this->update([
            'manual_verification_status' => 'approved',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'verification_notes' => $notes,
            'status' => 'completed'
        ]);
    }

    /**
     * Reject manual verification
     */
    public function rejectVerification(int $verifiedBy, string $notes): void
    {
        $this->update([
            'manual_verification_status' => 'rejected',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'verification_notes' => $notes,
            'status' => 'cancelled'
        ]);
    }

    /**
     * Scope for active checks
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for suspended checks
     */
    public function scopeSuspended($query)
    {
        return $query->where('is_suspended', true);
    }

    /**
     * Scope for pending verification
     */
    public function scopePendingVerification($query)
    {
        return $query->where('manual_verification_status', 'pending');
    }

    /**
     * Scope for due suspension
     */
    public function scopeDueForSuspension($query)
    {
        return $query->where('suspension_due_date', '<=', now())
                    ->where('is_suspended', false)
                    ->where('document_upload_status', '!=', 'uploaded');
    }
}
