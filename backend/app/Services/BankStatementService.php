<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use AlphaDirect\Models\DeduplicationChecks;
use AlphaDirect\Customer;

class BankStatementService
{
    /**
     * Create a new bank statement upload request
     */
    public function createUploadRequest(array $data): array
    {
        try {
            DB::beginTransaction();

            // Generate unique token
            $token = $this->generateUniqueToken();

            // Create deduplication check record
            $deduplicationCheck = DeduplicationChecks::create([
                'customer_id' => $data['customer_id'],
                'unique_customer_id' => $this->generateUniqueCustomerId($data['customer_id']),
                'omang_number' => $data['omang_number'] ?? null,
                'passport_number' => $data['passport_number'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'cellphone' => $data['cellphone'] ?? null,
                'email' => $data['email'] ?? null,
                'unique_access_token' => $token,
                'link_expires_at' => Carbon::now()->addDays(7), // 7 days expiry
                'status' => 'active',
                'document_upload_status' => 'pending',
                'manual_verification_status' => 'pending'
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'id' => $deduplicationCheck->id,
                    'token' => $token,
                    'expires_at' => $deduplicationCheck->link_expires_at->toISOString(),
                    'status' => 'active'
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bank statement upload request: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create upload request',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate unique access token
     */
    private function generateUniqueToken(): string
    {
        do {
            $token = 'BS_' . Str::random(32);
        } while (DeduplicationChecks::where('unique_access_token', $token)->exists());

        return $token;
    }

    /**
     * Generate unique customer ID
     */
    private function generateUniqueCustomerId(int $customerId): string
    {
        return 'CUST_' . $customerId . '_' . time();
    }

    /**
     * Access bank statement upload link
     */
    public function accessLink(string $token, string $ipAddress, string $userAgent): array
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }

            if ($deduplicationCheck->isExpired()) {
                $deduplicationCheck->update(['status' => 'expired']);
                return [
                    'success' => false,
                    'message' => 'Link has expired'
                ];
            }

            if ($deduplicationCheck->isUsed()) {
                return [
                    'success' => false,
                    'message' => 'Link already used'
                ];
            }

            // Update access tracking
            $deduplicationCheck->update([
                'link_opened_at' => Carbon::now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'status' => 'active'
            ]);

            return [
                'success' => true,
                'customer' => $deduplicationCheck->customer,
                'deduplication_check' => $deduplicationCheck
            ];

        } catch (\Exception $e) {
            Log::error('Failed to access bank statement link: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to access link',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send OTP for bank statement upload
     */
    public function sendOtp(string $token, array $methods, string $ipAddress, string $userAgent): array
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }

            if ($deduplicationCheck->isExpired()) {
                return [
                    'success' => false,
                    'message' => 'Token has expired'
                ];
            }

            // Generate OTP
            $otpCode = $this->generateOtpCode();

            // Update deduplication check with OTP
            $deduplicationCheck->update([
                'otp_code' => $otpCode,
                'otp_attempts' => 0,
                'otp_sent_at' => Carbon::now(),
                'otp_expires_at' => Carbon::now()->addMinutes(10),
                'status' => 'otp_sent'
            ]);

            return [
                'success' => true,
                'otp_code' => $otpCode,
                'deduplication_check' => $deduplicationCheck
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send OTP: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify OTP for bank statement upload
     */
    public function verifyOtp(string $token, string $otpCode, string $ipAddress, string $userAgent): array
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }

            if ($deduplicationCheck->isExpired()) {
                return [
                    'success' => false,
                    'message' => 'Token has expired'
                ];
            }

            if ($deduplicationCheck->otp_code !== $otpCode) {
                $deduplicationCheck->increment('otp_attempts');
                
                if ($deduplicationCheck->otp_attempts >= 3) {
                    $deduplicationCheck->update(['status' => 'expired']);
                    return [
                        'success' => false,
                        'message' => 'Maximum OTP attempts exceeded'
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Invalid OTP code'
                ];
            }

            if ($deduplicationCheck->isOtpExpired()) {
                return [
                    'success' => false,
                    'message' => 'OTP has expired'
                ];
            }

            // Update OTP verification
            $deduplicationCheck->update([
                'otp_verified_at' => Carbon::now(),
                'status' => 'otp_verified'
            ]);

            return [
                'success' => true,
                'deduplication_check' => $deduplicationCheck
            ];

        } catch (\Exception $e) {
            Log::error('Failed to verify OTP: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to verify OTP',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload bank statement file
     */
    public function uploadBankStatement(string $token, array $fileData, string $ipAddress, string $userAgent): array
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }

            if ($deduplicationCheck->status !== 'otp_verified') {
                return [
                    'success' => false,
                    'message' => 'OTP must be verified before uploading'
                ];
            }

            // Update deduplication check with bank statement info
            $deduplicationCheck->update([
                'bank_statement_upload_url' => $fileData['url'],
                'bank_statement_file_path' => $fileData['path'],
                'bank_statement_file_name' => $fileData['original_name'],
                'bank_statement_mime_type' => $fileData['mime_type'],
                'bank_statement_file_size' => $fileData['size'],
                'bank_statement_file_hash' => $fileData['hash'],
                'document_upload_status' => 'uploaded',
                'status' => 'completed'
            ]);

            return [
                'success' => true,
                'deduplication_check' => $deduplicationCheck
            ];

        } catch (\Exception $e) {
            Log::error('Failed to upload bank statement: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to upload bank statement',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate OTP code
     */
    private function generateOtpCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get upload requirements
     */
    public function getUploadRequirements(): array
    {
        return [
            'max_file_size' => 10485760, // 10MB
            'accepted_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
            'required_fields' => ['bank_name', 'account_number', 'statement_period']
        ];
    }

    /**
     * Get completion status
     */
    public function getCompletionStatus(string $token): array
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }

            return [
                'success' => true,
                'deduplication_check' => $deduplicationCheck
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get completion status: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to get completion status',
                'error' => $e->getMessage()
            ];
        }
    }
}
