<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\AdGroupKycActivity;
use AlphaDirect\Models\AdGroupKycSubmission;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;

class AdGroupKycController extends Controller
{
    /**
     * Show the KYC verification page
     */
    public function showVerifyPage($token)
    {
        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return view('ad-group-kyc.error', [
                    'message' => 'Invalid or expired verification link',
                    'token' => $token
                ]);
            }

            if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
                $link->update(['status' => 'expired']);
                return view('ad-group-kyc.error', [
                    'message' => 'This verification link has expired',
                    'token' => $token
                ]);
            }

            if ($link->status === 'completed') {
                return view('ad-group-kyc.complete', [
                    'message' => 'KYC verification already completed',
                    'token' => $token
                ]);
            }

            return view('ad-group-kyc.verify', [
                'token' => $token,
                'link' => $link,
                'customer' => $link->customer,
                'policy' => $link->policy
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC verify page error: ' . $e->getMessage());
            return view('ad-group-kyc.error', [
                'message' => 'An error occurred while loading the verification page',
                'token' => $token
            ]);
        }
    }

    /**
     * Process KYC verification
     */
    public function processVerification(Request $request, $token)
    {
        $validator = Validator::make($request->all(), [
            'otp_code' => 'required|string|size:6',
            'customer_name' => 'required|string|max:255',
            'id_number' => 'required|string|max:50',
            'phone_number' => 'required|string|max:20',
            'document_type' => 'required|in:omang,passport,drivers_license',
            'front_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'back_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return redirect()->back()->with('error', 'Invalid or expired verification link');
            }

            if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
                return redirect()->back()->with('error', 'This verification link has expired');
            }

            // Verify OTP
            if ($link->otp_code !== $request->otp_code) {
                return redirect()->back()->with('error', 'Invalid OTP code');
            }

            if ($link->otp_expires_at && $link->otp_expires_at < Carbon::now()) {
                return redirect()->back()->with('error', 'OTP code has expired');
            }

            // Update customer information
            $customer = $link->customer;
            $customer->firstName = explode(' ', $request->customer_name)[0] ?? $customer->firstName;
            $customer->lastName = implode(' ', array_slice(explode(' ', $request->customer_name), 1)) ?? $customer->lastName;
            $customer->cellphone = $request->phone_number;
            $customer->save();

            // Update customer profile
            $profile = $customer->customerProfile;
            if (!$profile) {
                $profile = new CustomerProfile();
                $profile->customer_id = $customer->id;
            }
            $profile->id_number = $request->id_number;
            $profile->save();

            // Upload documents
            $uploadedFiles = [];
            $timestamp = time();
            $documentType = $request->input('document_type');

            // Upload front image
            if ($request->hasFile('front_image')) {
                $frontFile = $request->file('front_image');
                $frontFilename = $token . '_front_' . $timestamp . '.' . $frontFile->getClientOriginalExtension();
                $frontPath = 'ad-group-kyc/documents/' . $frontFilename;
                
                Storage::disk('s3')->put($frontPath, file_get_contents($frontFile), 'public');
                $frontUrl = config('app.S3_BASE_URL') . $frontPath;
                
                $uploadedFiles[] = [
                    'type' => 'front_image',
                    'document_type' => $documentType,
                    'filename' => $frontFilename,
                    'original_name' => $frontFile->getClientOriginalName(),
                    'path' => $frontPath,
                    'url' => $frontUrl,
                    'size' => $frontFile->getSize(),
                    'mime_type' => $frontFile->getMimeType()
                ];
            }

            // Upload back image if provided
            if ($request->hasFile('back_image')) {
                $backFile = $request->file('back_image');
                $backFilename = $token . '_back_' . $timestamp . '.' . $backFile->getClientOriginalExtension();
                $backPath = 'ad-group-kyc/documents/' . $backFilename;
                
                Storage::disk('s3')->put($backPath, file_get_contents($backFile), 'public');
                $backUrl = config('app.S3_BASE_URL') . $backPath;
                
                $uploadedFiles[] = [
                    'type' => 'back_image',
                    'document_type' => $documentType,
                    'filename' => $backFilename,
                    'original_name' => $backFile->getClientOriginalName(),
                    'path' => $backPath,
                    'url' => $backUrl,
                    'size' => $backFile->getSize(),
                    'mime_type' => $backFile->getMimeType()
                ];
            }

            // Update link status
            $link->status = 'completed';
            $link->completed_at = Carbon::now();
            $link->save();

            // Log activity
            AdGroupKycActivity::create([
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'activity_type' => 'kyc_completed',
                'description' => 'KYC verification completed successfully',
                'metadata' => [
                    'uploaded_files' => $uploadedFiles,
                    'document_type' => $documentType,
                    'completed_at' => Carbon::now()->toISOString()
                ],
                'occurred_at' => Carbon::now()
            ]);

            Log::info('AD Group KYC verification completed', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'uploaded_files_count' => count($uploadedFiles)
            ]);

            return redirect()->route('ad-group-kyc.complete', $token)
                ->with('success', 'KYC verification completed successfully!');

        } catch (\Exception $e) {
            Log::error('AD Group KYC verification error: ' . $e->getMessage(), [
                'token' => $token,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred during verification. Please try again.')
                ->withInput();
        }
    }

    /**
     * Save full AD Group KYC payload (API)
     * Endpoint: POST /adgroupkyc/save-kyc
     */
    public function saveKycSubmission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required|integer|exists:customers,id',
            // 'policy_id' => 'required|integer|exists:policies,id',
            'employer_group_id' => 'nullable|string|max:191',
            'kyc_data' => 'required|array',
            // 'consent' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customerId = (int) $request->customer_id;
            $policyId = (int) $request->policy_id;
            $employerGroupId = $request->input('employer_group_id');
            $kycData = $request->input('kyc_data', []);
            $consent = (bool) $request->boolean('consent');

            $submission = AdGroupKycSubmission::create([
                'customer_id' => $customerId,
                'policy_id' => $policyId,
                'employer_group_id' => $employerGroupId,
                // company
                'form_last_completed' => $kycData['form_last_completed'] ?? null,
                'company_name' => $kycData['company_name'] ?? null,
                'registration_no' => $kycData['registration_no'] ?? null,
                'tin_number' => $kycData['tin_number'] ?? null,
                'vat_number' => $kycData['vat_number'] ?? null,
                'country_of_incorporation' => $kycData['country_of_incorporation'] ?? null,
                'corporate_email' => $kycData['corporate_email'] ?? null,
                'postal_address' => $kycData['postal_address'] ?? null,
                'corporate_physical_address' => $kycData['corporate_physical_address'] ?? null,
                'website' => $kycData['website'] ?? null,
                'corporate_telephone' => $kycData['corporate_telephone'] ?? null,
                'type_of_business' => $kycData['type_of_business'] ?? null,
                'business_description' => $kycData['business_description'] ?? null,
                // primary contact
                'contact_title' => $kycData['contact_title'] ?? null,
                'contact_names' => $kycData['contact_names'] ?? null,
                'contact_surname' => $kycData['contact_surname'] ?? null,
                'contact_date_of_birth' => $kycData['contact_date_of_birth'] ?? null,
                'contact_national_id' => $kycData['contact_national_id'] ?? null,
                'contact_nationality' => $kycData['contact_nationality'] ?? null,
                'contact_position' => $kycData['contact_position'] ?? null,
                'contact_email' => $kycData['contact_email'] ?? null,
                'contact_telephone' => $kycData['contact_telephone'] ?? null,
                'contact_fax' => $kycData['contact_fax'] ?? null,
                'contact_physical_address' => $kycData['contact_physical_address'] ?? null,
                'contact_village' => $kycData['contact_village'] ?? null,
                'contact_country' => $kycData['contact_country'] ?? null,
                // additional contact
                'additional_contact_name' => $kycData['additional_contact_name'] ?? null,
                'additional_contact_surname' => $kycData['additional_contact_surname'] ?? null,
                'additional_contact_telephone' => $kycData['additional_contact_telephone'] ?? null,
                'additional_contact_mobile' => $kycData['additional_contact_mobile'] ?? null,
                'additional_contact_email' => $kycData['additional_contact_email'] ?? null,
                // banking
                'account_name' => $kycData['account_name'] ?? null,
                'account_number' => $kycData['account_number'] ?? null,
                'bank_name' => $kycData['bank_name'] ?? null,
                'bank_branch' => $kycData['bank_branch'] ?? null,
                'branch_code' => $kycData['branch_code'] ?? null,
                // risk
                'high_risk_country_involvement' => $kycData['high_risk_country_involvement'] ?? null,
                'high_risk_country_details' => $kycData['high_risk_country_details'] ?? null,
                'complex_ownership_structure' => $kycData['complex_ownership_structure'] ?? null,
                'complex_ownership_details' => $kycData['complex_ownership_details'] ?? null,
                'other_high_risk_indicators' => $kycData['other_high_risk_indicators'] ?? null,
                // declaration
                'consent' => $consent,
                'declaration_full_name' => $kycData['declaration_full_name'] ?? null,
                'declaration_designation' => $kycData['declaration_designation'] ?? null,
                'declaration_date' => $kycData['declaration_date'] ?? null,
                'declaration_place' => $kycData['declaration_place'] ?? null,
                'declaration_signature' => $kycData['declaration_signature'] ?? null,
            ]);

            // Save directors as rows
            $directors = $kycData['directors'] ?? [];
            foreach ($directors as $d) {
                \AlphaDirect\Models\AdGroupKycDirector::create([
                    'submission_id' => $submission->id,
                    'full_name' => $d['full_name'] ?? null,
                    'residential_address' => $d['residential_address'] ?? null,
                    'date_of_birth' => $d['date_of_birth'] ?? null,
                    'nationality' => $d['nationality'] ?? null,
                    'pip_declaration' => $d['pip_declaration'] ?? null,
                    'source_of_wealth' => $d['source_of_wealth'] ?? null,
                ]);
            }

            // Save shareholders as rows
            $shareholders = $kycData['shareholders'] ?? [];
            foreach ($shareholders as $s) {
                \AlphaDirect\Models\AdGroupKycShareholder::create([
                    'submission_id' => $submission->id,
                    'full_name' => $s['full_name'] ?? null,
                    'residential_address' => $s['residential_address'] ?? null,
                    'date_of_birth' => $s['date_of_birth'] ?? null,
                    'nationality' => $s['nationality'] ?? null,
                    'ownership_percentage' => isset($s['ownership_percentage']) ? (float) $s['ownership_percentage'] : null,
                    'pip_declaration' => $s['pip_declaration'] ?? null,
                    'source_of_wealth' => $s['source_of_wealth'] ?? null,
                ]);
            }

            // Handle documents[] uploads as rows
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $field => $file) {
                    if (!$file) { continue; }
                    $path = 'ad-group-kyc/submissions/' . $policyId . '/' . time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    Storage::disk('s3')->put($path, file_get_contents($file), 'public');
                    \AlphaDirect\Models\AdGroupKycDocument::create([
                        'submission_id' => $submission->id,
                        'field_key' => $field,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'path' => $path,
                        'url' => config('app.S3_BASE_URL') . $path,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'KYC saved successfully',
                'data' => [
                    'submission_id' => $submission->id,
                    'customer_id' => $submission->customer_id,
                    'policy_id' => $submission->policy_id,
                    'directors_saved' => count($directors),
                    'shareholders_saved' => count($shareholders),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to save AD Group KYC submission: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save KYC',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show completion page
     */
    public function showCompletePage($token)
    {
        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return view('ad-group-kyc.error', [
                    'message' => 'Invalid verification link',
                    'token' => $token
                ]);
            }

            return view('ad-group-kyc.complete', [
                'token' => $token,
                'link' => $link,
                'customer' => $link->customer,
                'policy' => $link->policy
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC complete page error: ' . $e->getMessage());
            return view('ad-group-kyc.error', [
                'message' => 'An error occurred while loading the completion page',
                'token' => $token
            ]);
        }
    }

    /**
     * Get customer data via AJAX
     */
    public function getCustomerData(Request $request, $token): JsonResponse
    {
        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();
                
            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
                $link->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Link has expired'
                ], 410);
            }

            $customer = $link->customer;
            $profile = $customer->customerProfile;

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->firstName . ' ' . $customer->lastName,
                        'email' => $customer->email,
                        'cellphone' => $customer->cellphone
                    ],
                    'policy' => [
                        'id' => $link->policy->id,
                        'policy_number' => $link->policy->policyNumber,
                        'premium' => $link->policy->premium
                    ],
                    'campaign' => [
                        'id' => $link->campaign->id,
                        'name' => $link->campaign->name
                    ],
                    'link' => [
                        'id' => $link->id,
                        'status' => $link->status,
                        'expires_at' => $link->expires_at,
                        'otp_expires_at' => $link->otp_expires_at
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC get customer data error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load customer data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Access AD Group KYC link (API endpoint)
     */
    public function accessLink(Request $request, string $token): JsonResponse
    {
        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
                $link->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'This verification link has expired'
                ], 410);
            }

            if ($link->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'KYC verification already completed'
                ], 400);
            }

            // Mark as opened
            $link->markAsOpened($request->ip(), $request->userAgent());

            // Check if this is an employer group KYC link (no policy)
            $isEmployerGroupLink = is_null($link->policy_id);

            if ($isEmployerGroupLink) {
                // For employer group links: only return employer group data (no customer/policy)
                $employerGroup = null;
                if ($link->campaign && $link->campaign->employer_group_id) {
                    $employerGroup = \AlphaDirect\Models\EmployerGroup::where('employer_group_id', $link->campaign->employer_group_id)->first();
                }

                if (!$employerGroup) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Employer group not found'
                    ], 404);
                }

                Log::info('AD Group KYC - Employer Group Link accessed', [
                    'link_id' => $link->id,
                    'employer_group_id' => $employerGroup->employer_group_id,
                    'campaign_id' => $link->campaign_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Link accessed successfully',
                    'data' => [
                        'employer_group' => [
                            'id' => $employerGroup->id,
                            'employer_group_id' => $employerGroup->employer_group_id,
                            'name' => $employerGroup->name,
                            'contact_email' => $employerGroup->contact_email,
                            'contact_name' => $employerGroup->contact_name,
                            'contact_telephone' => $employerGroup->contact_phone
                        ],
                        'campaign' => [
                            'id' => $link->campaign->id,
                            'name' => $link->campaign->name
                        ],
                        'requires_otp' => true,
                        'link_type' => 'employer_group'
                    ]
                ]);
            }

            // For employee links (AD Group KYC form): Keep existing code as is
            // Debug: Log customer data
            Log::info('AD Group KYC - Access Link Customer data debug', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'customer_data' => [
                    'id' => $link->customer->id,
                    'firstName' => $link->customer->firstName,
                    'lastName' => $link->customer->lastName,
                    'email' => $link->customer->email,
                    'cellphone' => $link->customer->cellphone
                ],
                'policy_data' => [
                    'id' => $link->policy->id,
                    'policyNumber' => $link->policy->policyNumber,
                    'premium' => $link->policy->premium
                ]
            ]);

            // Additional debug: Check if policy customer matches link customer
            $policyCustomer = $link->policy->customer;
            if ($policyCustomer && $policyCustomer->id !== $link->customer_id) {
                Log::warning('AD Group KYC - Access Link Customer mismatch detected', [
                    'link_customer_id' => $link->customer_id,
                    'link_customer_name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                    'policy_customer_id' => $policyCustomer->id,
                    'policy_customer_name' => $policyCustomer->firstName . ' ' . $policyCustomer->lastName,
                    'policy_id' => $link->policy_id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Link accessed successfully',
                'data' => [
                    'customer' => [
                        'id' => $link->customer->id,
                        'name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                        'email' => $link->customer->email,
                        'cellphone' => $link->customer->cellphone
                    ],
                    'policy' => [
                        'id' => $link->policy->id,
                        'policy_number' => $link->policy->policyNumber,
                        'premium' => $link->policy->premium
                    ],
                    'campaign' => [
                        'id' => $link->campaign->id,
                        'name' => $link->campaign->name
                    ],
                    'requires_otp' => true,
                    'debug' => [
                        'link_customer_id' => $link->customer_id,
                        'link_customer_name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                        'policy_customer_id' => $policyCustomer ? $policyCustomer->id : null,
                        'policy_customer_name' => $policyCustomer ? $policyCustomer->firstName . ' ' . $policyCustomer->lastName : null,
                        'customer_match' => $policyCustomer ? ($policyCustomer->id === $link->customer_id) : false
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to access AD Group KYC link: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to access link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send OTP for AD Group KYC verification
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:ad_group_kyc_links,unique_token',
            'methods' => 'required|array|min:1',
            'methods.*' => 'in:email,sms,whatsapp',
            'cellphone' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the AD Group KYC link
            $link = AdGroupKycLink::where('unique_token', $request->token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
                $link->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Link has expired'
                ], 410);
            }

            // Generate new OTP
            $otp = new \AlphaDirect\OTP();
            $otp_response = $otp->OTPStore($link->customer->cellphone);
            $otp_data = $otp_response->getData();
            $otp_code = $otp_data->otp_code;

            // Update the link with new OTP
            $link->update([
                'otp_code' => $otp_code,
                'otp_attempts' => 0,
                'otp_expires_at' => Carbon::now()->addMinutes(15),
                'status' => 'opened'
            ]);

            $customer = $link->customer;
            $deliveryMethods = [];
            $errors = [];

            // Send OTP via requested methods
            foreach ($request->methods as $method) {
                try {
                    switch ($method) {
                        case 'sms':
                            if ($customer->cellphone) {
                                $sms_status = event(new \AlphaDirect\Events\SendSms(
                                    '+267' . $customer->cellphone, 
                                    'Alpha Direct, Your AD Group KYC Verification Code is: ' . $otp_code
                                ));
                                $deliveryMethods[] = 'SMS';
                            }
                            break;

                        case 'whatsapp':
                            if ($customer->cellphone) {
                                $whatsappData = [
                                    "type" => "template",
                                    "subType" => "policy_create_otp",
                                    "mobileNumber" => '267' . $customer->cellphone,
                                    "policyOtp" => $otp_code,
                                    "policyNumber" => $link->policy->policyNumber ?? null,
                                    "customer_id" => $customer->id
                                ];
                                
                                $whatsappController = new \AlphaDirect\Http\Controllers\WhatsAppController();
                                $whatsappController->sendMessage($whatsappData);
                                $deliveryMethods[] = 'WhatsApp';
                            }
                            break;

                        case 'email':
                            if ($customer->email) {
                                $data = new \stdClass();
                                $data->user_id = $otp_data->id;
                                $data->customer_id = $customer->id;
                                $data->hook = 'otp_mail';
                                $data->attachment = null;
                                
                                $emailTemplate = \AlphaDirect\EmailBroadcasting::where('hook_slug', $data->hook)->first(['subject']);
                                if ($emailTemplate) {
                                    $markdown = new \AlphaDirect\Mail\MailTemplate($data);
                                    $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                                    event(new \AlphaDirect\Events\SendMail(
                                        $customer->email,
                                        $emailTemplate->subject,
                                        "",
                                        $html,
                                        null,
                                        ['hook' => $data->hook]
                                    ));
                                    $deliveryMethods[] = 'Email';
                                }
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to send via {$method}: " . $e->getMessage();
                    Log::error("Failed to send AD Group KYC OTP via {$method}: " . $e->getMessage());
                }
            }

            // Log activity
            AdGroupKycActivity::create([
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'activity_type' => 'otp_resent',
                'description' => 'OTP resent via: ' . implode(', ', $deliveryMethods),
                'metadata' => [
                    'delivery_methods' => $deliveryMethods,
                    'sent_at' => Carbon::now()->toISOString(),
                ],
                'occurred_at' => Carbon::now()
            ]);

            $response = [
                'success' => true,
                'message' => 'OTP sent successfully via: ' . implode(', ', $deliveryMethods),
                'delivery_methods' => $deliveryMethods,
                'data' => [
                    'otp_code' => $otp_code,
                    'expires_at' => $link->otp_expires_at->toISOString()
                ]
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Failed to send AD Group KYC OTP: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP for AD Group KYC
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
            // Load link with campaign, then conditionally load customer/policy
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['campaign'])
                ->first();
            
            if ($link) {
                // Only load customer and policy if they exist (for employee links)
                if ($link->customer_id) {
                    $link->load('customer');
                }
                if ($link->policy_id) {
                    $link->load('policy');
                }
            }

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This verification link has expired'
                ], 410);
            }

            // Check if this is an employer group KYC link (no policy)
            $isEmployerGroupLink = is_null($link->policy_id);

            // Verify OTP (trim whitespace for comparison)
            $providedOtp = trim($request->otp_code);
            $storedOtp = trim($link->otp_code);

            Log::info('AD Group KYC - OTP Verification Attempt', [
                'link_id' => $link->id,
                'is_employer_group_link' => $isEmployerGroupLink,
                'provided_otp' => $providedOtp,
                'stored_otp' => $storedOtp,
                'otp_match' => ($providedOtp === $storedOtp)
            ]);

            if ($providedOtp !== $storedOtp) {
                $link->increment('otp_attempts');
                Log::warning('AD Group KYC - Invalid OTP code', [
                    'link_id' => $link->id,
                    'provided_otp' => $providedOtp,
                    'stored_otp' => $storedOtp,
                    'otp_attempts' => $link->otp_attempts
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP code'
                ], 400);
            }

            if ($link->otp_expires_at && $link->otp_expires_at < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP code has expired'
                ], 410);
            }

            // Mark OTP as verified
            $link->update([
                'otp_verified_at' => Carbon::now(),
                'status' => 'otp_verified'
            ]);

            if ($isEmployerGroupLink) {
                // For employer group links: only return employer group data
                $employerGroup = null;
                if ($link->campaign && $link->campaign->employer_group_id) {
                    $employerGroup = \AlphaDirect\Models\EmployerGroup::where('employer_group_id', $link->campaign->employer_group_id)->first();
                }

                if (!$employerGroup) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Employer group not found'
                    ], 404);
                }

                Log::info('AD Group KYC - Employer Group OTP verified', [
                    'link_id' => $link->id,
                    'employer_group_id' => $employerGroup->employer_group_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP verified successfully',
                    'data' => [
                        'employer_group' => [
                            'id' => $employerGroup->id,
                            'employer_group_id' => $employerGroup->employer_group_id,
                            'name' => $employerGroup->name,
                            'contact_email' => $employerGroup->contact_email,
                            'contact_name' => $employerGroup->contact_name,
                            'contact_telephone' => $employerGroup->contact_phone
                        ],
                        'campaign' => [
                            'id' => $link->campaign->id,
                            'name' => $link->campaign->name
                        ],
                        'link_type' => 'employer_group'
                    ]
                ]);
            }

            // For employee links (AD Group KYC form): Keep existing code as is
            // Debug: Log customer data
            Log::info('AD Group KYC - Customer data debug', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'customer_data' => [
                    'id' => $link->customer->id,
                    'firstName' => $link->customer->firstName,
                    'lastName' => $link->customer->lastName,
                    'email' => $link->customer->email,
                    'cellphone' => $link->customer->cellphone
                ],
                'policy_data' => [
                    'id' => $link->policy->id,
                    'policyNumber' => $link->policy->policyNumber,
                    'premium' => $link->policy->premium
                ]
            ]);

            // Additional debug: Check if policy customer matches link customer
            $policyCustomer = $link->policy->customer;
            if ($policyCustomer && $policyCustomer->id !== $link->customer_id) {
                Log::warning('AD Group KYC - Customer mismatch detected', [
                    'link_customer_id' => $link->customer_id,
                    'link_customer_name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                    'policy_customer_id' => $policyCustomer->id,
                    'policy_customer_name' => $policyCustomer->firstName . ' ' . $policyCustomer->lastName,
                    'policy_id' => $link->policy_id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'customer' => [
                        'id' => $link->customer->id,
                        'name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                        'email' => $link->customer->email,
                        'cellphone' => $link->customer->cellphone
                    ],
                    'policy' => [
                        'id' => $link->policy->id,
                        'policy_number' => $link->policy->policyNumber,
                        'premium' => $link->policy->premium
                    ],
                    'campaign' => [
                        'id' => $link->campaign->id,
                        'name' => $link->campaign->name
                    ],
                    'debug' => [
                        'link_customer_id' => $link->customer_id,
                        'link_customer_name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                        'policy_customer_id' => $policyCustomer ? $policyCustomer->id : null,
                        'policy_customer_name' => $policyCustomer ? $policyCustomer->firstName . ' ' . $policyCustomer->lastName : null,
                        'customer_match' => $policyCustomer ? ($policyCustomer->id === $link->customer_id) : false
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to verify OTP: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit AD Group KYC data
     */
    public function submitAdGroupKycData(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'id_number' => 'required|string|max:50',
            'phone_number' => 'required|string|max:20',
            'document_type' => 'required|in:omang,passport,drivers_license',
            'front_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'back_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This verification link has expired'
                ], 410);
            }

            // Update customer information
            $customer = $link->customer;
            $customer->firstName = explode(' ', $request->customer_name)[0] ?? $customer->firstName;
            $customer->lastName = implode(' ', array_slice(explode(' ', $request->customer_name), 1)) ?? $customer->lastName;
            $customer->cellphone = $request->phone_number;
            $customer->save();

            // Update customer profile
            $profile = $customer->customerProfile;
            if (!$profile) {
                $profile = new CustomerProfile();
                $profile->customer_id = $customer->id;
            }
            $profile->id_number = $request->id_number;
            $profile->save();

            // Upload documents
            $uploadedFiles = [];
            $timestamp = time();
            $documentType = $request->input('document_type');

            // Upload front image
            if ($request->hasFile('front_image')) {
                $frontFile = $request->file('front_image');
                $frontFilename = $token . '_front_' . $timestamp . '.' . $frontFile->getClientOriginalExtension();
                $frontPath = 'ad-group-kyc/documents/' . $frontFilename;
                
                Storage::disk('s3')->put($frontPath, file_get_contents($frontFile), 'public');
                $frontUrl = config('app.S3_BASE_URL') . $frontPath;
                
                $uploadedFiles[] = [
                    'type' => 'front_image',
                    'document_type' => $documentType,
                    'filename' => $frontFilename,
                    'original_name' => $frontFile->getClientOriginalName(),
                    'path' => $frontPath,
                    'url' => $frontUrl,
                    'size' => $frontFile->getSize(),
                    'mime_type' => $frontFile->getMimeType()
                ];
            }

            // Upload back image if provided
            if ($request->hasFile('back_image')) {
                $backFile = $request->file('back_image');
                $backFilename = $token . '_back_' . $timestamp . '.' . $backFile->getClientOriginalExtension();
                $backPath = 'ad-group-kyc/documents/' . $backFilename;
                
                Storage::disk('s3')->put($backPath, file_get_contents($backFile), 'public');
                $backUrl = config('app.S3_BASE_URL') . $backPath;
                
                $uploadedFiles[] = [
                    'type' => 'back_image',
                    'document_type' => $documentType,
                    'filename' => $backFilename,
                    'original_name' => $backFile->getClientOriginalName(),
                    'path' => $backPath,
                    'url' => $backUrl,
                    'size' => $backFile->getSize(),
                    'mime_type' => $backFile->getMimeType()
                ];
            }

            // Update link status
            $link->status = 'completed';
            $link->completed_at = Carbon::now();
            $link->save();

            // Log activity
            AdGroupKycActivity::create([
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'activity_type' => 'kyc_completed',
                'description' => 'KYC verification completed successfully',
                'metadata' => [
                    'uploaded_files' => $uploadedFiles,
                    'document_type' => $documentType,
                    'completed_at' => Carbon::now()->toISOString()
                ],
                'occurred_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KYC verification completed successfully',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->firstName . ' ' . $customer->lastName,
                        'email' => $customer->email,
                        'cellphone' => $customer->cellphone
                    ],
                    'policy' => [
                        'id' => $link->policy->id,
                        'policy_number' => $link->policy->policyNumber
                    ],
                    'uploaded_files_count' => count($uploadedFiles)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC verification error: ' . $e->getMessage(), [
                'token' => $token,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get required documents for AD Group KYC
     */
    public function getRequiredDocuments(Request $request, string $token): JsonResponse
    {
        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            $requiredDocuments = [
                [
                    'type' => 'omang',
                    'name' => 'Omang',
                    'description' => 'Botswana National ID',
                    'required' => true,
                    'front_required' => true,
                    'back_required' => true
                ],
                [
                    'type' => 'passport',
                    'name' => 'Passport',
                    'description' => 'International Passport',
                    'required' => true,
                    'front_required' => true,
                    'back_required' => false
                ],
                [
                    'type' => 'drivers_license',
                    'name' => 'Driver\'s License',
                    'description' => 'Botswana Driver\'s License',
                    'required' => true,
                    'front_required' => true,
                    'back_required' => true
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'required_documents' => $requiredDocuments,
                    'max_file_size' => '5MB',
                    'allowed_formats' => ['jpg', 'jpeg', 'png', 'pdf']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get required documents: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load required documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload documents for AD Group KYC
     */
    public function uploadDocuments(Request $request, $token)
    {
        // $validator = Validator::make($request->all(), [
        //     'document_type' => 'required|in:omang,passport,drivers_license',
        //     'front_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        //     'back_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'
        // ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Validation failed',
        //         'errors' => $validator->errors()
        //     ], 422);
        // }

        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            // Upload documents
            $uploadedFiles = [];
            $timestamp = time();
            $documentType = $request->input('document_type');
            $customer = $link->customer;

            // Get or create customer KYC record
            $customerKyc = \AlphaDirect\KYC::where('customer_id', $customer->id)->first();
            if (!$customerKyc) {
                $customerKyc = new \AlphaDirect\KYC();
                $customerKyc->customer_id = $customer->id;
                $customerKyc->save();
            }

            // Upload front image
            if ($request->hasFile('front_image')) {
                $frontFile = $request->file('front_image');
                $frontFilename = $token . '_front_' . $timestamp . '.' . $frontFile->getClientOriginalExtension();
                $frontPath = 'ad-group-kyc/documents/' . $frontFilename;
                
                Storage::disk('s3')->put($frontPath, file_get_contents($frontFile), 'public');
                $frontUrl = config('app.S3_BASE_URL') . $frontPath;
                
                $uploadedFiles[] = [
                    'type' => 'front_image',
                    'document_type' => $documentType,
                    'filename' => $frontFilename,
                    'original_name' => $frontFile->getClientOriginalName(),
                    'path' => $frontPath,
                    'url' => $frontUrl,
                    'size' => $frontFile->getSize(),
                    'mime_type' => $frontFile->getMimeType()
                ];

                // Store in customer_kyc table based on document type
                switch ($documentType) {
                    case 'omang':
                        $customerKyc->omang_front = $frontPath;
                        // $customerKyc->omangFrontStatus = 'uploaded';
                        break;
                    case 'passport':
                        $customerKyc->passport = $frontPath;
                        // $customerKyc->passportStatus = 'uploaded';
                        break;
                    case 'drivers_license':
                        $customerKyc->drivers_license_front_image = $frontPath;
                        // $customerKyc->driversLicenseStatus = 'uploaded';
                        break;
                }
            }

            // Upload back image if provided
            if ($request->hasFile('back_image')) {
                $backFile = $request->file('back_image');
                $backFilename = $token . '_back_' . $timestamp . '.' . $backFile->getClientOriginalExtension();
                $backPath = 'ad-group-kyc/documents/' . $backFilename;
                
                Storage::disk('s3')->put($backPath, file_get_contents($backFile), 'public');
                $backUrl = config('app.S3_BASE_URL') . $backPath;
                
                $uploadedFiles[] = [
                    'type' => 'back_image',
                    'document_type' => $documentType,
                    'filename' => $backFilename,
                    'original_name' => $backFile->getClientOriginalName(),
                    'path' => $backPath,
                    'url' => $backUrl,
                    'size' => $backFile->getSize(),
                    'mime_type' => $backFile->getMimeType()
                ];

                // Store in customer_kyc table based on document type
                switch ($documentType) {
                    case 'omang':
                        $customerKyc->omang_back = $backPath;
                        // $customerKyc->omangBackStatus = 'uploaded';
                        break;
                    case 'passport':
                        $customerKyc->passport_back = $backPath;
                        // $customerKyc->passportBackStatus = 'uploaded';
                        break;
                    case 'drivers_license':
                        $customerKyc->driving_license_back = $backPath;
                        // $customerKyc->driversLicenseStatus = 'uploaded';
                        break;
                }
            }

            // Save the customer KYC record
            $customerKyc->save();

            // Log activity
            AdGroupKycActivity::create([
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'activity_type' => 'documents_uploaded',
                'description' => 'Documents uploaded for ' . $documentType,
                'metadata' => [
                    'document_type' => $documentType,
                    'uploaded_files' => $uploadedFiles,
                    'uploaded_at' => Carbon::now()->toISOString(),
                ],
                'occurred_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully',
                'data' => [
                    'uploaded_files' => $uploadedFiles,
                    'document_type' => $documentType,
                    'customer_kyc_id' => $customerKyc->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload documents: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save KYC data for AD Group KYC
     */
    public function saveKyc(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'id_number' => 'required|string|max:50',
            'phone_number' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            // Update customer information
            $customer = $link->customer;
            $customer->firstName = explode(' ', $request->customer_name)[0] ?? $customer->firstName;
            $customer->lastName = implode(' ', array_slice(explode(' ', $request->customer_name), 1)) ?? $customer->lastName;
            $customer->cellphone = $request->phone_number;
            $customer->save();

            // Update customer profile
            $profile = $customer->customerProfile;
            if (!$profile) {
                $profile = new CustomerProfile();
                $profile->customer_id = $customer->id;
            }
            $profile->id_number = $request->id_number;
            $profile->save();

            return response()->json([
                'success' => true,
                'message' => 'KYC data saved successfully',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->firstName . ' ' . $customer->lastName,
                        'email' => $customer->email,
                        'cellphone' => $customer->cellphone
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save KYC data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save KYC data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Skip documents for AD Group KYC
     */
    public function skipDocuments(Request $request, string $token): JsonResponse
    {
        try {
            $link = AdGroupKycLink::where('unique_token', $token)
                ->with(['customer', 'policy', 'campaign'])
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            // Mark as completed without documents
            $link->status = 'completed';
            $link->completed_at = Carbon::now();
            $link->save();

            // Log activity
            AdGroupKycActivity::create([
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'activity_type' => 'kyc_completed',
                'description' => 'KYC verification completed without documents',
                'metadata' => [
                    'documents_skipped' => true,
                    'completed_at' => Carbon::now()->toISOString()
                ],
                'occurred_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KYC verification completed successfully',
                'data' => [
                    'customer' => [
                        'id' => $link->customer->id,
                        'name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                        'email' => $link->customer->email
                    ],
                    'policy' => [
                        'id' => $link->policy->id,
                        'policy_number' => $link->policy->policyNumber
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to skip documents: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete KYC verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create AD Group KYC campaign (placeholder)
     */
    public function createCampaign(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Campaign creation not implemented yet'
        ], 501);
    }

    /**
     * Get AD Group KYC campaign stats (placeholder)
     */
    public function getCampaignStats(Request $request, $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Campaign stats not implemented yet'
        ], 501);
    }

    /**
     * Generate AD Group KYC links (placeholder)
     */
    public function generateLinks(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Link generation not implemented yet'
        ], 501);
    }
}
