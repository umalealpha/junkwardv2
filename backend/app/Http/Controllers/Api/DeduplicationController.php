<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\DeduplicationCheck;
use AlphaDirect\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DeduplicationController extends Controller
{
    /**
     * Create a new deduplication check for a customer
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customer,id',
            'omang_number' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'cellphone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'policy_id' => 'nullable|exists:policy,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check for existing deduplication check
            $existingCheck = DeduplicationCheck::where('customer_id', $request->customer_id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($existingCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deduplication check already exists for this customer',
                    'data' => $existingCheck
                ], 409);
            }

            // Create new deduplication check
            $deduplicationCheck = DeduplicationCheck::create([
                'customer_id' => $request->customer_id,
                'omang_number' => $request->omang_number,
                'passport_number' => $request->passport_number,
                'bank_account_number' => $request->bank_account_number,
                'bank_name' => $request->bank_name,
                'bank_branch' => $request->bank_branch,
                'cellphone' => $request->cellphone,
                'email' => $request->email,
                'policy_id' => $request->policy_id,
                'device_info' => $request->header('User-Agent')
            ]);

            // Set policy if provided
            if ($request->policy_id) {
                $deduplicationCheck->setPolicy($request->policy_id);
            }

            // Check for duplicates
            $duplicates = $deduplicationCheck->checkForDuplicates();

            return response()->json([
                'success' => true,
                'message' => 'Deduplication check created successfully',
                'data' => [
                    'deduplication_check' => $deduplicationCheck,
                    'access_url' => $deduplicationCheck->getAccessUrl(),
                    'duplicates_found' => count($duplicates) > 0,
                    'duplicates' => $duplicates
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create deduplication check',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Access deduplication check via token
     */
    public function access(Request $request, string $token): JsonResponse
    {
        try {
            $deduplicationCheck = DeduplicationCheck::where('unique_access_token', $token)->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token'
                ], 404);
            }

            if ($deduplicationCheck->isLinkExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access link has expired'
                ], 410);
            }

            // Mark as opened and track access
            $deduplicationCheck->markAsOpened(
                $request->ip(),
                $request->header('User-Agent')
            );

            return response()->json([
                'success' => true,
                'message' => 'Access granted',
                'data' => [
                    'deduplication_check' => $deduplicationCheck,
                    'customer' => $deduplicationCheck->customer,
                    'otp_required' => !$deduplicationCheck->otp_verified_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to access deduplication check',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send OTP for verification
     */
    public function sendOtp(Request $request, string $token): JsonResponse
    {
        try {
            $deduplicationCheck = DeduplicationCheck::where('unique_access_token', $token)->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token'
                ], 404);
            }

            if ($deduplicationCheck->otp_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP already verified'
                ], 400);
            }

            // Generate new OTP
            $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $deduplicationCheck->update([
                'otp_code' => $otpCode,
                'otp_sent_at' => now(),
                'otp_expires_at' => now()->addMinutes(10),
                'otp_attempts' => 0
            ]);

            // Here you would typically send the OTP via SMS/Email
            // For now, we'll return it in the response (remove in production)
            
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                'data' => [
                    'otp_code' => $otpCode, // Remove this in production
                    'expires_at' => $deduplicationCheck->otp_expires_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = DeduplicationCheck::where('unique_access_token', $token)->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token'
                ], 404);
            }

            $isValid = $deduplicationCheck->verifyOtp($request->otp_code);

            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP or maximum attempts exceeded'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'deduplication_check' => $deduplicationCheck
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload bank statement
     */
    public function uploadBankStatement(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_statement' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240' // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = DeduplicationCheck::where('unique_access_token', $token)->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token'
                ], 404);
            }

            if (!$deduplicationCheck->otp_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP verification required'
                ], 403);
            }

            $file = $request->file('bank_statement');
            $fileName = 'bank_statement_' . $deduplicationCheck->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('bank_statements', $fileName, 'private');
            $fileHash = hash_file('sha256', $file->getRealPath());

            $fileData = [
                'file_path' => $filePath,
                'file_name' => $fileName,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash
            ];

            $deduplicationCheck->uploadBankStatement($fileData);

            return response()->json([
                'success' => true,
                'message' => 'Bank statement uploaded successfully',
                'data' => [
                    'file_name' => $fileName,
                    'file_size' => $file->getSize(),
                    'uploaded_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload bank statement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deduplication check status
     */
    public function getStatus(Request $request, string $token): JsonResponse
    {
        try {
            $deduplicationCheck = DeduplicationCheck::where('unique_access_token', $token)->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'deduplication_check' => $deduplicationCheck,
                    'customer' => $deduplicationCheck->customer,
                    'policy' => $deduplicationCheck->policy,
                    'verification_status' => $deduplicationCheck->manual_verification_status,
                    'document_upload_status' => $deduplicationCheck->document_upload_status,
                    'is_suspended' => $deduplicationCheck->is_suspended,
                    'suspension_due_date' => $deduplicationCheck->suspension_due_date
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all deduplication checks (Admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DeduplicationCheck::with(['customer', 'policy', 'verifier']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by verification status
            if ($request->has('verification_status')) {
                $query->where('manual_verification_status', $request->verification_status);
            }

            // Filter by document upload status
            if ($request->has('document_status')) {
                $query->where('document_upload_status', $request->document_status);
            }

            // Filter by suspension status
            if ($request->has('is_suspended')) {
                $query->where('is_suspended', $request->boolean('is_suspended'));
            }

            $deduplicationChecks = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $deduplicationChecks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch deduplication checks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve manual verification (Admin)
     */
    public function approveVerification(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verified_by' => 'required|exists:users,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = DeduplicationCheck::findOrFail($id);
            
            $deduplicationCheck->approveVerification(
                $request->verified_by,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Verification approved successfully',
                'data' => $deduplicationCheck
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject manual verification (Admin)
     */
    public function rejectVerification(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verified_by' => 'required|exists:users,id',
            'notes' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = DeduplicationCheck::findOrFail($id);
            
            $deduplicationCheck->rejectVerification(
                $request->verified_by,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Verification rejected successfully',
                'data' => $deduplicationCheck
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
