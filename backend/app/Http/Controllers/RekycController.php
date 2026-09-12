<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use AlphaDirect\Models\RekycCampaign;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycActivity;
use AlphaDirect\Models\RekycDocument;
use AlphaDirect\Customer;
use AlphaDirect\KYC;
use AlphaDirect\Helper;
use AlphaDirect\Services\RekycService;
use AlphaDirect\OTP;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;

class RekycController extends Controller
{
    protected $rekycService;

    public function __construct(RekycService $rekycService)
    {
        $this->rekycService = $rekycService;
    }
    /**
     * Create a new Re-KYC campaign
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_criteria' => 'required|array',
            'notification_settings' => 'required|array',
            'kyc_fields' => 'required|array',
            'ocr_enabled' => 'boolean',
            'fraud_detection_enabled' => 'boolean',
            'link_expiry_hours' => 'integer|min:1|max:168',
            'otp_expiry_minutes' => 'integer|min:1|max:60',
            'max_attempts' => 'integer|min:1|max:10',
            'start_date' => 'nullable|date|after:now',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = $this->rekycService->createCampaign($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Re-KYC campaign created successfully',
                'data' => $campaign
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create Re-KYC campaign: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Re-KYC campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Re-KYC links for customers
     */
    public function generateLinks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customer,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = RekycCampaign::findOrFail($request->campaign_id);
            
            if (!$campaign->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign is not active'
                ], 400);
            }

            $links = $this->rekycService->generateLinks($campaign, $request->customer_ids);

            return response()->json([
                'success' => true,
                'message' => 'Re-KYC links generated successfully',
                'data' => [
                    'campaign' => $campaign,
                    'links' => $links,
                    'total_generated' => count($links)
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to generate Re-KYC links: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Re-KYC links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Access Re-KYC link (first step - show OTP form)
     */
    public function accessLink(Request $request, string $token): JsonResponse
    {
        try {
            $result = $this->rekycService->accessLink(
                $token,
                $request->ip(),
                $request->userAgent()
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $result['message'] === 'Invalid or expired link' ? 404 : 410);
            }

            return response()->json([
                'success' => true,
                'message' => 'Link accessed successfully',
                'data' => [
                    'customer' => [
                        'id' => $result['customer']['id'],
                        'name' => $result['customer']['firstName'] . ' ' . $result['customer']['lastName'],
                        'email' => $result['customer']['email'],
                        'cellphone' => $result['customer']['cellphone']
                    ],
                    'campaign' => [
                        'id' => $result['campaign']->id,
                        'name' => $result['campaign']->name,
                        'kyc_fields' => $result['campaign']->kyc_fields
                    ],
                    'requires_otp' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to access Re-KYC link: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to access link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP and proceed to Re-KYC form
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
            $result = $this->rekycService->verifyOTP(
                $token,
                $request->otp_code,
                $request->ip(),
                $request->userAgent()
            );

            if (!$result['success']) {
                $statusCode = 400;
                if (strpos($result['message'], 'expired') !== false) {
                    $statusCode = 410;
                } elseif (strpos($result['message'], 'exceeded') !== false) {
                    $statusCode = 429;
                } elseif (strpos($result['message'], 'Invalid link') !== false) {
                    $statusCode = 404;
                }

                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $statusCode);
            }

            // Get current KYC data for the customer
            $link = $result['link'];
            $customer = $link->customer;
            $kyc = $customer->KYC;

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->fullName,
                        'email' => $customer->email,
                        'cellphone' => $this->maskPhoneNumber($customer->cellphone)
                    ],
                    'current_kyc' => $this->maskKycData($kyc),
                    'kyc_fields' => $link->campaign->kyc_fields,
                    'access_token' => $this->generateAccessToken($link)
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
     * Submit Re-KYC data (consent or updates)
     */
    public function submitRekycData(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'action' => 'required|in:confirm,update',
            'kyc_data' => 'required_if:action,update|array',
            'consent_data' => 'required|array',
            'documents' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare data for service
            $data = [
                'response_type' => $request->action === 'confirm' ? 'no_change' : 'update',
                'kyc_data' => $request->kyc_data ?? [],
                'consent_data' => $request->consent_data,
                'documents' => $request->documents ?? [],
                'device_info' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ];

            $result = $this->rekycService->submitRekycData(
                $token,
                $data,
                $request->ip(),
                $request->userAgent()
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Re-KYC data submitted successfully',
                'data' => [
                    'action' => $request->action,
                    'completed_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit Re-KYC data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit Re-KYC data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStats(Request $request, int $campaignId): JsonResponse
    {
        try {
            $campaign = RekycCampaign::findOrFail($campaignId);
            $stats = $campaign->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get campaign stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get campaign statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mask phone number for display
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) < 4) {
            return str_repeat('*', strlen($phoneNumber));
        }
        
        return substr($phoneNumber, 0, 2) . str_repeat('*', strlen($phoneNumber) - 4) . substr($phoneNumber, -2);
    }

    /**
     * Mask KYC data for display
     */
    private function maskKycData($kyc): array
    {
        if (!$kyc) {
            return [];
        }

        return [
            'omang' => $this->maskIdentifier($kyc->omang),
            'drivers_license' => $this->maskIdentifier($kyc->driversLicense),
            'passport' => $this->maskIdentifier($kyc->passportIssuingCountry),
            'vehicle_registration' => $this->maskIdentifier($kyc->vehicleRegistration)
        ];
    }

    /**
     * Mask identifier for display
     */
    private function maskIdentifier(?string $identifier): ?string
    {
        if (!$identifier || strlen($identifier) < 4) {
            return $identifier;
        }
        
        return substr($identifier, 0, 2) . str_repeat('*', strlen($identifier) - 4) . substr($identifier, -2);
    }

    /**
     * Generate access token for authenticated session
     */
    private function generateAccessToken(RekycLink $link): string
    {
        return Hash::make($link->id . $link->unique_token . now()->timestamp);
    }

    /**
     * Verify access token
     */
    private function verifyAccessToken(string $token, RekycLink $link): bool
    {
        // Simple verification - in production, use JWT or similar
        return Hash::check($link->id . $link->unique_token . now()->timestamp, $token);
    }

    /**
     * Process document uploads
     */
    private function processDocumentUploads(RekycLink $link, array $documents): void
    {
        foreach ($documents as $document) {
            // Handle file upload and storage
            // This is a simplified version - implement proper file handling
            RekycDocument::create([
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'document_type' => $document['type'],
                'file_path' => $document['path'],
                'file_name' => $document['name'],
                'mime_type' => $document['mime_type'],
                'file_size' => $document['size'],
                'file_hash' => $document['hash'],
                'status' => 'uploaded'
            ]);
        }
    }
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:rekyc_links,unique_token',
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
            // Get the Re-KYC link
            $link = RekycLink::where('unique_token', $request->token)
                ->with(['customer', 'campaign'])
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
            $otp = new OTP();
            $otp_response = $otp->OTPStore($link->customer->cellphone);
            $otp_data = $otp_response->getData();
            $otp_code = $otp_data->otp_code;

            // Update the link with new OTP
            $link->update([
                'otp_code' => $otp_code,
                'otp_attempts' => 0,
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
                                    'Alpha Direct, Your Re-KYC Verification Code is: ' . $otp_code
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
                                    "policyNumber" => null,
                                    "customer_id" => $customer->id
                                ];
                                
                                $whatsappController = new WhatsAppController();
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
                                
                                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(['subject']);
                                if ($emailTemplate) {
                                    $markdown = new MailTemplate($data);
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
                    Log::error("Failed to send OTP via {$method}: " . $e->getMessage());
                }
            }

            // Log activity
            RekycActivity::logActivity(
                $link->id,
                $link->customer_id,
                'otp_resent',
                'OTP resent via: ' . implode(', ', $deliveryMethods),
                [
                    'delivery_methods' => $deliveryMethods,
                    'sent_at' => Carbon::now()->toISOString(),
                ]
            );

            $response = [
                'success' => true,
                'message' => 'OTP sent successfully via: ' . implode(', ', $deliveryMethods),
                'delivery_methods' => $deliveryMethods
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Failed to send OTP: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getCustomerData(Request $request, string $token): JsonResponse
    {
        try {
            $link = RekycLink::where('unique_token', $token)
                ->with(['customer', 'campaign'])
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
            $kyc = $customer->KYC;
            $customerProfile = $customer->profile;

            // Format customer data for frontend form
            $customerData = [
                'id' => $customer->id,
                'first_name' => $customer->firstName ?? '',
                'last_name' => $customer->lastName ?? '',
                'date_of_birth' => $customerProfile->dob ?? $kyc->dateOfBirth ?? '',
                'gender' => $customerProfile->gender ?? $kyc->gender ?? '',
                'mobile_number' => $customer->cellphone ?? '',
                'email' => $customer->email ?? '',
                'address' => $customerProfile->address ?? $kyc->address ?? '',
                'id_type' => $this->determineIdType($kyc, $customerProfile),
                'id_number' => $this->getIdNumber($kyc, $customerProfile),
                //'nationality' => $customerProfile->countryId ?? $kyc->nationality ?? 'Botswana',
                'occupation' => $customerProfile->occupation ?? $kyc->occupation ?? '',
                'marital_status' => $customerProfile->maritalstatus ?? $kyc->maritalStatus ?? '',
                //'emergency_contact' => $customerProfile->e_name ?? $kyc->emergencyContact ?? '',
                'kyc_fields' => $link->campaign->kyc_fields ?? [],
                'campaign_id' => $link->campaign->id,
                'link_status' => $link->status
            ];

            return response()->json([
                'success' => true,
                'message' => 'Customer data retrieved successfully',
                'data' => $customerData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get customer data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine ID type based on available KYC and CustomerProfile data
     */
    private function determineIdType($kyc, $customerProfile = null): string
    {
        // Check CustomerProfile first, then KYC
        if ($customerProfile && !empty($customerProfile->omang)) {
            return 'omang';
        } elseif ($customerProfile && !empty($customerProfile->passport)) {
            return 'passport';
        } elseif ($customerProfile && !empty($customerProfile->driving_license_number)) {
            return 'drivers_license';
        } elseif ($kyc && !empty($kyc->omang)) {
            return 'omang';
        } elseif ($kyc && !empty($kyc->passportIssuingCountry)) {
            return 'passport';
        } elseif ($kyc && !empty($kyc->driversLicense)) {
            return 'drivers_license';
        }
        return '';
    }

    /**
     * Get ID number based on ID type
     */
    private function getIdNumber($kyc, $customerProfile = null): string
    {
        // Check CustomerProfile first, then KYC
        if ($customerProfile && !empty($customerProfile->omang)) {
            return $customerProfile->omang;
        } elseif ($customerProfile && !empty($customerProfile->passport)) {
            return $customerProfile->passport;
        } elseif ($customerProfile && !empty($customerProfile->driving_license_number)) {
            return $customerProfile->driving_license_number;
        } elseif ($kyc && !empty($kyc->omang)) {
            return $kyc->omang;
        } elseif ($kyc && !empty($kyc->passportIssuingCountry)) {
            return $kyc->passportIssuingCountry;
        } elseif ($kyc && !empty($kyc->driversLicense)) {
            return $kyc->driversLicense;
        }
        return '';
    }

    /**
     * Get required documents for Re-KYC verification
     */
    public function getRequiredDocuments(Request $request, string $token): JsonResponse
    {
        try {
            $link = RekycLink::where('unique_token', $token)
                ->with(['customer', 'campaign'])
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
            $kyc = $customer->KYC;
            $campaign = $link->campaign;

            // Define required documents based on campaign settings and customer data
            $requiredDocuments = $this->determineRequiredDocuments($customer, $kyc, $campaign);

            // Get already uploaded documents
            $uploadedDocuments = RekycDocument::where('link_id', $link->id)
                ->where('status', 'uploaded')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'original_name' => $doc->file_name,
                        'size' => $doc->file_size,
                        'uploaded_at' => $doc->created_at->format('Y-m-d H:i:s'),
                        'status' => $doc->status
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Required documents retrieved successfully',
                'data' => [
                    'required_documents' => $requiredDocuments,
                    'uploaded_documents' => $uploadedDocuments,
                    'link_status' => $link->status,
                    'campaign_id' => $campaign->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get required documents: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve required documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload documents for Re-KYC verification
     */
    public function uploadDocuments(Request $request, $token)
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:omang,passport,drivers_license,proof_of_address',
            'front_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            'back_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            'full_name' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $link = RekycLink::where('unique_token', $token)
                ->with(['customer', 'campaign'])
                ->first();
                
            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired link'
                ], 404);
            }

            // if ($link->status !== 'otp_verified') {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'OTP must be verified before uploading documents'
            //     ], 400);
            // }

            $uploadedFiles = [];
            $timestamp = time();
            $documentType = $request->input('document_type');
            $customer = $link->customer;
            $kyc = $customer->KYC;
            
            // Upload front image to S3
            if ($request->hasFile('front_image')) {
                $frontFile = $request->file('front_image');
                $frontFilename = $token . '_front_' . $timestamp . '.' . $frontFile->getClientOriginalExtension();
                $frontPath = 'rekyc/documents/' . $frontFilename;
                
                // Upload to S3
                Storage::disk('s3')->put($frontPath, file_get_contents($frontFile), 'public');
                $frontUrl = config('app.S3_BASE_URL') . '' . $frontPath;
                
                // Create document record for front image
                $frontDocument = RekycDocument::create([
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'document_type' => $documentType . '_front',
                    'file_path' => $frontPath,
                    'file_name' => $frontFile->getClientOriginalName(),
                    'mime_type' => $frontFile->getMimeType(),
                    'file_size' => $frontFile->getSize(),
                    'file_hash' => hash_file('sha256', $frontFile->getRealPath()),
                    'status' => 'uploaded'
                ]);
                
                $uploadedFiles[] = [
                    'type' => 'front_image',
                    'document_type' => $documentType,
                    'filename' => $frontFilename,
                    'original_name' => $frontFile->getClientOriginalName(),
                    'path' => $frontPath,
                    'url' => $frontUrl,
                    'size' => $frontFile->getSize(),
                    'mime_type' => $frontFile->getMimeType(),
                    'document_id' => $frontDocument->id
                ];

                // Update KYC table with document information
                $this->updateKycWithDocument($kyc, $documentType, $frontPath, 'front');

                // Log activity
                RekycActivity::logActivity(
                    $link->id,
                    $link->customer_id,
                    'document_uploaded',
                    'Front image uploaded: ' . $frontFile->getClientOriginalName(),
                    [
                        'document_type' => $documentType . '_front',
                        'file_size' => $frontFile->getSize(),
                        'file_url' => $frontUrl,
                        'uploaded_at' => Carbon::now()->toISOString(),
                    ]
                );
            }
            
            // Upload back image to S3 if provided
            if ($request->hasFile('back_image')) {
                $backFile = $request->file('back_image');
                $backFilename = $token . '_back_' . $timestamp . '.' . $backFile->getClientOriginalExtension();
                $backPath = 'rekyc/documents/' . $backFilename;
                
                // Upload to S3
                Storage::disk('s3')->put($backPath, file_get_contents($backFile), 'public');
                $backUrl = config('app.S3_BASE_URL') . '' . $backPath;
                
                // Create document record for back image
                $backDocument = RekycDocument::create([
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'document_type' => $documentType . '_back',
                    'file_path' => $backPath,
                    'file_name' => $backFile->getClientOriginalName(),
                    'mime_type' => $backFile->getMimeType(),
                    'file_size' => $backFile->getSize(),
                    'file_hash' => hash_file('sha256', $backFile->getRealPath()),
                    'status' => 'uploaded'
                ]);
                
                $uploadedFiles[] = [
                    'type' => 'back_image',
                    'document_type' => $documentType,
                    'filename' => $backFilename,
                    'original_name' => $backFile->getClientOriginalName(),
                    'path' => $backPath,
                    'url' => $backUrl,
                    'size' => $backFile->getSize(),
                    'mime_type' => $backFile->getMimeType(),
                    'document_id' => $backDocument->id
                ];

                // Update KYC table with back document information
                $this->updateKycWithDocument($kyc, $documentType, $backPath, 'back');

                // Log activity
                RekycActivity::logActivity(
                    $link->id,
                    $link->customer_id,
                    'document_uploaded',
                    'Back image uploaded: ' . $backFile->getClientOriginalName(),
                    [
                        'document_type' => $documentType . '_back',
                        'file_size' => $backFile->getSize(),
                        'file_url' => $backUrl,
                        'uploaded_at' => Carbon::now()->toISOString(),
                    ]
                );
            }

            // Check if at least one file was uploaded
            if (empty($uploadedFiles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No files were uploaded. Please upload at least one document.',
                ], 400);
            }

            // Update link status to completed
            $link->update([
                'status' => 'completed',
                'completed_at' => Carbon::now()
            ]);
            
            // Refresh the link object to get updated data
            $link->refresh();
            
            // Log completion activity
            RekycActivity::logActivity(
                $link->id,
                $link->customer_id,
                'documents_upload_completed',
                'Documents uploaded successfully and Re-KYC process completed',
                [
                    'document_type' => $documentType,
                    'uploaded_count' => count($uploadedFiles),
                    'completed_at' => Carbon::now()->toISOString(),
                    'final_status' => 'completed'
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully and Re-KYC process completed!',
                'data' => [
                    'uploaded_files' => $uploadedFiles,
                    'document_type' => $documentType,
                    'link_status' => 'completed',
                    'uploaded_at' => Carbon::now()->toISOString(),
                    'completed_at' => Carbon::now()->toISOString(),
                    'process_status' => 'completed'
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Re-KYC Document Upload Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading documents. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update KYC table with document information
     */
    private function updateKycWithDocument($kyc, $documentType, $fileUrl, $side = 'front')
    {
        if (!$kyc) {
            return;
        }

        $updateData = [];

        switch ($documentType) {
            case 'omang':
                if ($side === 'front') {
                    $updateData['omang'] = $fileUrl;
                    $updateData['omangFrontStatus'] = 0;
                    $updateData['status'] = 'pending';
                    $updateData['compliance'] = 0;
                    $updateData['compliance'] = 'ReKYC Uploaded';
                    
                } else {
                    $updateData['omangBack'] = $fileUrl;
                    $updateData['omangBackStatus'] = 0;
                    $updateData['status'] = 'pending';
                    $updateData['compliance'] = 0;
                }
                break;
            case 'passport':
                if ($side === 'front') {
                    $updateData['passport'] = $fileUrl;
                    $updateData['passportStatus'] = 0;
                    $updateData['status'] = 'pending';
                    $updateData['compliance'] = 0;
                    $updateData['compliance'] = 'ReKYC Uploaded';
                } else {
                    $updateData['passport_back'] = $fileUrl;
                   
                }
                break;
            case 'drivers_license':
                if ($side === 'front') {
                    $updateData['drivers_license_front_image'] = $fileUrl;
                } else {
                    $updateData['driving_license_back'] = $fileUrl;
                }
                break;
            case 'proof_of_address':
                $updateData['proof_of_address_image'] = $fileUrl;
                break;
        }

        if (!empty($updateData)) {
            try {
                
                $kyc->update($updateData);
                Log::info('KYC document image updated successfully', [
                    'customer_id' => $kyc->customer_id,
                    'document_type' => $documentType,
                    'side' => $side,
                    'file_url' => $fileUrl
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update KYC document image', [
                    'customer_id' => $kyc->customer_id,
                    'document_type' => $documentType,
                    'side' => $side,
                    'file_url' => $fileUrl,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Determine required documents based on customer data and campaign settings
     */
    private function determineRequiredDocuments($customer, $kyc, $campaign): array
    {
        $requiredDocuments = [];
        $documentIndex = 0;

        // Check if ID document is required and not already provided
        if ($this->isIdDocumentRequired($kyc)) {
            $idType = $this->determineIdType($kyc);
            $requiredDocuments[] = [
                'index' => $documentIndex++,
                'type' => 'id_document',
                'name' => $this->getDocumentDisplayName($idType),
                'description' => 'Please upload a clear photo of your ' . str_replace('_', ' ', $idType),
                'required' => true,
                'accepted_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => 5120 // 5MB in KB
            ];
        }

        // Check if proof of address is required
        if ($this->isProofOfAddressRequired($kyc)) {
            $requiredDocuments[] = [
                'index' => $documentIndex++,
                'type' => 'proof_of_address',
                'name' => 'Proof of Address',
                'description' => 'Recent utility bill, bank statement, or official document showing your current address',
                'required' => true,
                'accepted_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => 5120
            ];
        }

        // Check campaign-specific requirements
        if (!empty($campaign->kyc_fields)) {
            foreach ($campaign->kyc_fields as $field) {
                if (isset($field['require_document']) && $field['require_document']) {
                    $requiredDocuments[] = [
                        'index' => $documentIndex++,
                        'type' => $field['name'],
                        'name' => $field['label'] ?? ucfirst(str_replace('_', ' ', $field['name'])),
                        'description' => $field['document_description'] ?? 'Please upload the required document',
                        'required' => true,
                        'accepted_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
                        'max_size' => 5120
                    ];
                }
            }
        }

        return $requiredDocuments;
    }

    /**
     * Check if ID document is required
     */
    private function isIdDocumentRequired($kyc): bool
    {
        return empty($kyc->omang) && empty($kyc->passportIssuingCountry) && empty($kyc->driversLicense);
    }

    /**
     * Check if proof of address is required
     */
    private function isProofOfAddressRequired($kyc): bool
    {
        return empty($kyc->address) || strlen(trim($kyc->address)) < 10;
    }

    /**
     * Get document display name
     */
    private function getDocumentDisplayName(string $idType): string
    {
        $names = [
            'omang' => 'Omang Card',
            'passport' => 'Passport',
            'drivers_license' => 'Driver\'s License'
        ];

        return $names[$idType] ?? 'ID Document';
    }

    /**
     * Save KYC update data for customer
     */
    public function saveKyc(Request $request, string $token): JsonResponse
    {
        try {
            // Get the Re-KYC link
            $link = RekycLink::where('unique_token', $token)
                ->with(['customer', 'campaign'])
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
            $kyc = $customer->KYC;
            $customerProfile = $customer->profile;

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'kyc_data' => 'required|array',
                'kyc_data.date_of_birth' => 'nullable|date',
               
                'kyc_data.mobile_number' => 'nullable|string|max:20',
                'kyc_data.email' => 'nullable|email|max:255',
                'kyc_data.address' => 'nullable|string|max:500',
                'kyc_data.id_type' => 'nullable|string|in:omang,passport,drivers_license',
                'kyc_data.id_number' => 'nullable|string|max:50',
           
                'kyc_data.occupation' => 'nullable|string|max:255',
             
                'kyc_data.data_processing_consent' => 'nullable|string',
                'kyc_data.marketing_consent' => 'nullable|string',
                'consent' => 'required|accepted',
                'device_info' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Extract kyc_data from request
                $kycData = $request->input('kyc_data', []);
                
                // Update customer basic information (excluding first_name and last_name)
                $customerUpdates = [];
                // Note: first_name and last_name are intentionally excluded from updates
                if (isset($kycData['mobile_number'])) {
                    $customerUpdates['cellphone'] = $kycData['mobile_number'];
                }
                if (isset($kycData['email'])) {
                    $customerUpdates['email'] = $kycData['email'];
                }

                if (!empty($customerUpdates)) {
                    $customer->update($customerUpdates);
                }

                // Update KYC information
                $kycUpdates = [];
                
                // Basic KYC fields
                if (isset($kycData['date_of_birth'])) {
                    $kycUpdates['dateOfBirth'] = $kycData['date_of_birth'];
                }
                if (isset($kycData['gender'])) {
                    $kycUpdates['gender'] = $kycData['gender'];
                }
                if (isset($kycData['address'])) {
                    $kycUpdates['address'] = $kycData['address'];
                }
               
                if (isset($kycData['occupation'])) {
                    $kycUpdates['occupation'] = $kycData['occupation'];
                }
                if (isset($kycData['marital_status'])) {
                    $kycUpdates['maritalStatus'] = $kycData['marital_status'];
                }

                // ID document fields
                if (isset($kycData['id_type']) && isset($kycData['id_number'])) {
                    $idType = $kycData['id_type'];
                    $idNumber = $kycData['id_number'];
                    
                    switch ($idType) {
                        case 'omang':
                            $kycUpdates['omang'] = $idNumber;
                            $kycUpdates['omangFrontStatus'] = 0;
                            break;
                        case 'passport':
                            $kycUpdates['passportIssuingCountry'] = $idNumber;
                            $kycUpdates['passportStatus'] = 0;
                            break;
                        case 'drivers_license':
                            $kycUpdates['driversLicense'] = $idNumber;
                            $kycUpdates['driversLicenseStatus'] = 0;
                            break;
                    }
                }

                // Set status and compliance
                $kycUpdates['status'] = 'pending';
                $kycUpdates['compliance'] = 'ReKYC Updated';

                // Update or create KYC record
               

                // Handle additional KYC data from campaign
               

                // Update CustomerProfile with corresponding data
                $profileUpdates = [];
                
                // Map KYC fields to CustomerProfile fields
                if (isset($kycData['date_of_birth'])) {
                    $profileUpdates['dob'] = $kycData['date_of_birth'];
                }
                if (isset($kycData['gender'])) {
                    $profileUpdates['gender'] = $kycData['gender'];
                }
                if (isset($kycData['address'])) {
                    $profileUpdates['address'] = $kycData['address'];
                }
                if (isset($kycData['marital_status'])) {
                    $profileUpdates['maritalstatus'] = $kycData['marital_status'];
                }
                if (isset($kycData['nationality'])) {
                    $profileUpdates['countryId'] = $kycData['nationality'];
                }
                if (isset($kycData['occupation'])) {
                    $profileUpdates['occupation'] = $kycData['occupation'];
                }

                // ID document fields for CustomerProfile
                if (isset($kycData['id_type']) && isset($kycData['id_number'])) {
                    $idType = $kycData['id_type'];
                    $idNumber = $kycData['id_number'];
                    
                    switch ($idType) {
                        case 'omang':
                            $profileUpdates['omang'] = $idNumber;
                            break;
                        case 'passport':
                            $profileUpdates['passport'] = $idNumber;
                            $profileUpdates['countryId'] = $kycData['nationality'] ?? $profileUpdates['countryId'] ?? null;
                            break;
                        case 'drivers_license':
                            $profileUpdates['driving_license_number'] = $idNumber;
                            break;
                    }
                }

                // Update or create CustomerProfile record
                if ($customerProfile) {
                    if (!empty($profileUpdates)) {
                        $customerProfile->update($profileUpdates);
                    }
                } else {
                    $profileUpdates['customer_id'] = $customer->id;
                    $customerProfile = \AlphaDirect\CustomerProfile::create($profileUpdates);
                }

                // Update consent data
                $consentData = [
                    'data_processing_consent' => $kycData['data_processing_consent'] ?? null,
                    'marketing_consent' => $kycData['marketing_consent'] ?? null,
                    'general_consent' => $request->input('consent'),
                    'device_info' => $request->input('device_info', [])
                ];
                $link->update(['consent_data' => $consentData]);

                // Update link status
                $link->update([
                    'status' => 'update Data',
                    'completed_at' => Carbon::now()
                ]);

                // Log activity
                RekycActivity::logActivity(
                    $link->id,
                    $link->customer_id,
                    'kyc_data_updated',
                    'KYC data updated successfully',
                    [
                        'updated_fields' => array_keys(array_merge($customerUpdates, $kycUpdates, $profileUpdates)),
                        'updated_at' => Carbon::now()->toISOString(),
                        'final_status' => 'completed'
                    ]
                );

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'KYC data saved successfully',
                    'data' => [
                        'customer_id' => $customer->id,
                        'kyc_id' => $kyc->id,
                        'profile_id' => $customerProfile->id ?? null,
                        'updated_fields' => array_keys(array_merge($customerUpdates, $kycUpdates, $profileUpdates)),
                        'status' => 'completed',
                        'updated_at' => Carbon::now()->toISOString()
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

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
     * Skip document upload and complete Re-KYC process
     */
    public function skipDocuments(Request $request, string $token): JsonResponse
    {
        try {
            // Get the Re-KYC link
            $link = RekycLink::where('unique_token', $token)
                ->with(['customer', 'campaign'])
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
            $kyc = $customer->KYC;
            $customerProfile = $customer->profile;

            // Validate the request data
            

            

            DB::beginTransaction();

            try {
                // Update KYC status to completed
                if ($kyc) {
                    $kyc->update([
                        
                        'remark' => 'ReKYC Completed - No Documents'
                    ]);
                }

                // Update CustomerProfile if it exists
               
                // Update link status
                $link->update([
                    'status' => 'completed',
                    'completed_at' => Carbon::now(),
                    'consent_data' => array_merge($link->consent_data ?? [], [
                        'skip_documents' => true,
                        'no_document_update' => $request->input('no_document_update', true),
                        'device_info' => $request->input('device_info', []),
                        'skipped_at' => Carbon::now()->toISOString()
                    ])
                ]);

                // Log activity
                RekycActivity::logActivity(
                    $link->id,
                    $link->customer_id,
                    'documents_skipped',
                    'Customer chose to skip document upload and complete Re-KYC process',
                    [
                        'skip_documents' => true,
                        'no_document_update' => $request->input('no_document_update', true),
                        'kyc_status' => $request->input('kyc_status', 'completed'),
                        'skipped_at' => Carbon::now()->toISOString(),
                        'final_status' => 'completed'
                    ]
                );

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'KYC process completed successfully without document upload',
                    'data' => [
                        'customer_id' => $customer->id,
                        'kyc_id' => $kyc->id ?? null,
                        'profile_id' => $customerProfile->id ?? null,
                        'status' => 'completed',
                        'skip_documents' => true,
                        'completed_at' => Carbon::now()->toISOString()
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to skip documents: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to skip documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show success page after document upload
     */
    public function showSuccess(Request $request, string $token)
    {
        $link = RekycLink::where('unique_token', $token)
            ->with(['customer', 'campaign'])
            ->first();

        if (!$link) {
            return redirect()->route('rekyc.error')->with('error', 'Invalid or expired link');
        }

        return view('rekyc.success', compact('link'));
    }
}
