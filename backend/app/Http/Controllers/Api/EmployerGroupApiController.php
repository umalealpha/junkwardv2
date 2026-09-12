<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\EmployerGroupApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EmployerGroupApiController extends Controller
{
    /**
     * Update an existing employer group application
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate JSON structure - ID can be either top-level or in application_data
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|integer',
                'application_data' => 'required|array',
                'application_data.employer_group_id' => 'nullable|string',
                'device_info' => 'required|array',
                'submission_metadata' => 'required|array',
                // File upload validation - both old and new structure
                'certificate_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
                'tax_certificate_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'proof_address_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                // New file structure validation
                'files' => 'nullable|array',
                'files.certificate_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'files.tax_certificate_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'files.proof_address_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create service instance
            $employerGroupService = new EmployerGroupApiService();

            // Additional validation using service
            $validationErrors = $employerGroupService->validateData($request->all());
            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data validation failed',
                    'errors' => $validationErrors
                ], 422);
            }

            // Update the application data
            $result = $employerGroupService->updateApplication($request->all());

            if ($result['success']) {
                return response()->json($result, 200);
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            \Log::error('Employer Group API Controller Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Display a listing of employer groups
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $employerGroups = \AlphaDirect\Models\EmployerGroup::select([
                'id',
                'employer_group_id',
                'employer_group_name',
                'contact_name',
                'contact_email',
                'status',
                'created_at'
            ])->latest()->paginate(20);

            // Update the data to use id as employer_group_id
            $employerGroups->getCollection()->transform(function ($item) {
                $item->employer_group_id = $item->id;
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $employerGroups
            ]);

        } catch (\Exception $e) {
            \Log::error('Employer Group API Index Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employer groups'
            ], 500);
        }
    }

    /**
     * Display the specified employer group
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $employerGroup = \AlphaDirect\Models\EmployerGroup::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $employerGroup->id,
                    'employer_group_id' => $employerGroup->id, // Now using the same ID
                    'employer_group_name' => $employerGroup->name,
                    'industry_section' => $employerGroup->industry,
                    'other_industry' => $employerGroup->other_industry,
                    'street_address' => $employerGroup->address,
                    'postal_address' => $employerGroup->postal_code,
                    'city_town' => $employerGroup->town,
                    'contact_name' => $employerGroup->contact_name,
                    'contact_email' => $employerGroup->contact_email,
                    'contact_phone' => $employerGroup->contact_phone,
                    'broker_referral' => $employerGroup->broker,
                    'payment_method' => $employerGroup->payment_method,
                    'account_name' => $employerGroup->account_name,
                    'account_number' => $employerGroup->account_number,
                    'bank_name_branch' => $employerGroup->bank_name_branch,
                    'certificate_file' => $employerGroup->certificate_file,
                    'certificate_filename' => $employerGroup->certificate_filename,
                    'tax_certificate_file' => $employerGroup->tax_certificate_file,
                    'tax_certificate_filename' => $employerGroup->tax_certificate_filename,
                    'proof_address_file' => $employerGroup->proof_address_file,
                    'proof_address_filename' => $employerGroup->proof_address_filename,
                    'terms_agreement' => $employerGroup->terms_agreement,
                    'initials' => $employerGroup->initials,
                    'status' => $employerGroup->status,
                    'notes' => $employerGroup->notes,
                    'metadata' => $employerGroup->metadata,
                    'created_at' => $employerGroup->created_at,
                    'updated_at' => $employerGroup->updated_at,
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employer group not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Employer Group API Show Error', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employer group'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Implementation for update if needed
        return response()->json([
            'success' => false,
            'message' => 'Update functionality not implemented'
        ], 501);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // Implementation for delete if needed
        return response()->json([
            'success' => false,
            'message' => 'Delete functionality not implemented'
        ], 501);
    }

    /**
     * Send OTP to employer group contact email and phone
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOtp(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'employer_group_id' => 'required|integer|exists:employer_groups,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create service instance
            $employerGroupService = new EmployerGroupApiService();

            // Send OTP
            $result = $employerGroupService->sendOtp($request->employer_group_id);

            if ($result['success']) {
                return response()->json($result, 200);
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            \Log::error('Employer Group Send OTP API Controller Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Verify OTP for employer group
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'employer_group_id' => 'required|integer|exists:employer_groups,id',
                'otp_code' => 'required|min:4|max:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create service instance
            $employerGroupService = new EmployerGroupApiService();

            // Verify OTP
            $result = $employerGroupService->verifyOtp($request->employer_group_id, $request->otp_code);

            if ($result['success']) {
                return response()->json($result, 200);
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            \Log::error('Employer Group Verify OTP API Controller Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }
}
