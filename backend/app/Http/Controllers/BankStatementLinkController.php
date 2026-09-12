<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use AlphaDirect\Services\BankStatementService;
use AlphaDirect\Customer;

class BankStatementLinkController extends Controller
{
    protected $bankStatementService;

    public function __construct(BankStatementService $bankStatementService)
    {
        $this->bankStatementService = $bankStatementService;
    }

    /**
     * Generate bank statement upload links for customers
     */
    public function generateLinks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customer,id',
            'omang_numbers' => 'nullable|array',
            'passport_numbers' => 'nullable|array',
            'bank_account_numbers' => 'nullable|array',
            'cellphones' => 'nullable|array',
            'emails' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $links = [];
            $errors = [];

            foreach ($request->customer_ids as $index => $customerId) {
                try {
                    $customer = Customer::findOrFail($customerId);
                    
                    $data = [
                        'customer_id' => $customerId,
                        'omang_number' => $request->omang_numbers[$index] ?? null,
                        'passport_number' => $request->passport_numbers[$index] ?? null,
                        'bank_account_number' => $request->bank_account_numbers[$index] ?? null,
                        'cellphone' => $request->cellphones[$index] ?? $customer->cellphone,
                        'email' => $request->emails[$index] ?? $customer->email
                    ];

                    $result = $this->bankStatementService->createUploadRequest($data);

                    if ($result['success']) {
                        $links[] = [
                            'customer_id' => $customerId,
                            'customer_name' => $customer->firstName . ' ' . $customer->lastName,
                            'token' => $result['data']['token'],
                            'expires_at' => $result['data']['expires_at'],
                            'status' => $result['data']['status'],
                            'upload_url' => url('/bankstatement/access/' . $result['data']['token'])
                        ];
                    } else {
                        $errors[] = [
                            'customer_id' => $customerId,
                            'error' => $result['message']
                        ];
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'customer_id' => $customerId,
                        'error' => 'Failed to create link: ' . $e->getMessage()
                    ];
                    Log::error("Failed to create bank statement link for customer {$customerId}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank statement upload links generated successfully',
                'data' => [
                    'links' => $links,
                    'total_generated' => count($links),
                    'errors' => $errors
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to generate bank statement links: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate bank statement links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bank statement upload statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_requests' => \AlphaDirect\Models\DeduplicationChecks::count(),
                'active_requests' => \AlphaDirect\Models\DeduplicationChecks::where('status', 'active')->count(),
                'completed_uploads' => \AlphaDirect\Models\DeduplicationChecks::where('status', 'completed')->count(),
                'expired_requests' => \AlphaDirect\Models\DeduplicationChecks::where('status', 'expired')->count(),
                'pending_verification' => \AlphaDirect\Models\DeduplicationChecks::where('manual_verification_status', 'pending')->count(),
                'verified_uploads' => \AlphaDirect\Models\DeduplicationChecks::where('manual_verification_status', 'approved')->count(),
                'rejected_uploads' => \AlphaDirect\Models\DeduplicationChecks::where('manual_verification_status', 'rejected')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get bank statement stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bank statement upload requests with pagination
     */
    public function getRequests(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|in:active,completed,expired,pending,verified,rejected',
            'customer_id' => 'nullable|exists:customer,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = \AlphaDirect\Models\DeduplicationChecks::with(['customer', 'verifier']);

            // Apply filters
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'active':
                        $query->where('status', 'active');
                        break;
                    case 'completed':
                        $query->where('status', 'completed');
                        break;
                    case 'expired':
                        $query->where('status', 'expired');
                        break;
                    case 'pending':
                        $query->where('manual_verification_status', 'pending');
                        break;
                    case 'verified':
                        $query->where('manual_verification_status', 'approved');
                        break;
                    case 'rejected':
                        $query->where('manual_verification_status', 'rejected');
                        break;
                }
            }

            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            $perPage = $request->get('per_page', 15);
            $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get bank statement requests: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update manual verification status
     */
    public function updateVerificationStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:1000',
            'verified_by' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = \AlphaDirect\Models\DeduplicationChecks::findOrFail($id);

            $deduplicationCheck->update([
                'manual_verification_status' => $request->status,
                'verification_notes' => $request->notes,
                'verified_by' => $request->verified_by,
                'verified_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification status updated successfully',
                'data' => [
                    'id' => $deduplicationCheck->id,
                    'status' => $request->status,
                    'verified_at' => $deduplicationCheck->verified_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update verification status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update verification status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
