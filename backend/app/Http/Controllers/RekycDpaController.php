<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Services\RekycDpaComplianceService;
use AlphaDirect\Models\Customer;

class RekycDpaController extends Controller
{
    protected $dpaService;

    public function __construct(RekycDpaComplianceService $dpaService)
    {
        $this->dpaService = $dpaService;
    }

    /**
     * Process data subject request
     */
    public function processDataSubjectRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customer,id',
            'request_type' => 'required|in:access,rectification,erasure,portability,restriction,objection',
            'request_data' => 'nullable|array',
            'verification_method' => 'required|in:otp,email,phone,id_verification',
            'verification_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify the request
            $verification = $this->verifyDataSubjectRequest(
                $request->customer_id,
                $request->verification_method,
                $request->verification_code
            );

            if (!$verification['verified']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request verification failed',
                    'error' => $verification['error']
                ], 401);
            }

            // Process the request
            $result = $this->dpaService->processDataSubjectRequest(
                $request->customer_id,
                $request->request_type,
                $request->request_data ?? []
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Data subject request processing failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Data subject request processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data subject rights information
     */
    public function getDataSubjectRights(Request $request): JsonResponse
    {
        try {
            $rights = [
                'access_right' => [
                    'description' => 'Right to access personal data',
                    'scope' => 'All personal data processed by the organization',
                    'timeline' => 'Response within 30 days',
                    'format' => 'Machine-readable format (JSON, CSV, XML)',
                ],
                'rectification_right' => [
                    'description' => 'Right to rectify inaccurate personal data',
                    'scope' => 'Inaccurate or incomplete personal data',
                    'timeline' => 'Response within 30 days',
                    'requirements' => 'Proof of identity and supporting documentation',
                ],
                'erasure_right' => [
                    'description' => 'Right to erasure (right to be forgotten)',
                    'scope' => 'Personal data no longer necessary for original purpose',
                    'timeline' => 'Response within 30 days',
                    'exceptions' => [
                        'Legal obligation to retain data',
                        'Public interest in data retention',
                        'Legitimate business interests',
                        'Data required for legal claims',
                    ],
                ],
                'portability_right' => [
                    'description' => 'Right to data portability',
                    'scope' => 'Data provided by data subject and processed by automated means',
                    'timeline' => 'Response within 30 days',
                    'format' => 'Machine-readable format (JSON, CSV, XML)',
                ],
                'restriction_right' => [
                    'description' => 'Right to restrict processing',
                    'scope' => 'Personal data subject to dispute or objection',
                    'timeline' => 'Response within 30 days',
                    'effects' => 'Data marked as restricted, limited processing allowed',
                ],
                'objection_right' => [
                    'description' => 'Right to object to processing',
                    'scope' => 'Processing based on legitimate interests or public task',
                    'timeline' => 'Response within 30 days',
                    'effects' => 'Processing stopped unless compelling legitimate grounds',
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $rights,
                'contact_information' => [
                    'email' => config('rekyc.compliance.dpa_2018.contact_email', 'dpo@alphadirect.co.bw'),
                    'phone' => config('rekyc.compliance.dpa_2018.contact_phone', '+267 123 4567'),
                    'address' => config('rekyc.compliance.dpa_2018.contact_address', 'Gaborone, Botswana'),
                ],
                'complaint_procedures' => [
                    'internal' => 'Submit complaint through customer service',
                    'external' => 'Contact Data Protection Authority of Botswana',
                    'external_contact' => 'info@dpa.gov.bw',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get data subject rights: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get data subject rights',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get privacy notice
     */
    public function getPrivacyNotice(Request $request): JsonResponse
    {
        try {
            $privacyNotice = [
                'data_controller' => [
                    'name' => 'AlphaDirect Insurance',
                    'address' => 'Gaborone, Botswana',
                    'contact_email' => 'privacy@alphadirect.co.bw',
                    'dpo_email' => 'dpo@alphadirect.co.bw',
                ],
                'data_categories' => [
                    'personal_identifiers' => 'Name, email, phone, ID number',
                    'demographic_data' => 'Age, gender, address, occupation',
                    'financial_data' => 'Account information, transaction history',
                    'behavioral_data' => 'Usage patterns, preferences',
                    'technical_data' => 'IP address, device information, cookies',
                ],
                'processing_purposes' => [
                    'kyc_verification' => 'Know Your Customer verification and compliance',
                    'risk_assessment' => 'Risk assessment and fraud prevention',
                    'regulatory_compliance' => 'Regulatory compliance and reporting',
                    'service_delivery' => 'Service delivery and customer support',
                    'marketing' => 'Marketing and promotional activities (with consent)',
                ],
                'legal_basis' => [
                    'consent' => 'Explicit consent for marketing communications',
                    'contract' => 'Performance of insurance contract',
                    'legal_obligation' => 'Regulatory compliance requirements',
                    'legitimate_interests' => 'Risk assessment and fraud prevention',
                ],
                'data_retention' => [
                    'personal_data' => '7 years from last interaction',
                    'kyc_documents' => '7 years from verification date',
                    'transaction_data' => '7 years from transaction date',
                    'audit_logs' => '1 year from creation date',
                ],
                'data_sharing' => [
                    'regulatory_authorities' => 'As required by law',
                    'service_providers' => 'Under strict data processing agreements',
                    'fraud_prevention' => 'For fraud prevention and risk management',
                    'legal_requirements' => 'When required by legal process',
                ],
                'data_subject_rights' => [
                    'access' => 'Right to access personal data',
                    'rectification' => 'Right to rectify inaccurate data',
                    'erasure' => 'Right to erasure (right to be forgotten)',
                    'portability' => 'Right to data portability',
                    'restriction' => 'Right to restrict processing',
                    'objection' => 'Right to object to processing',
                ],
                'security_measures' => [
                    'encryption' => 'AES-256 encryption for data at rest',
                    'transmission' => 'TLS 1.3 for data in transit',
                    'access_control' => 'Role-based access control',
                    'monitoring' => 'Continuous security monitoring',
                    'backup' => 'Regular encrypted backups',
                ],
                'contact_information' => [
                    'dpo_email' => 'dpo@alphadirect.co.bw',
                    'privacy_email' => 'privacy@alphadirect.co.bw',
                    'phone' => '+267 123 4567',
                    'address' => 'Gaborone, Botswana',
                ],
                'complaints' => [
                    'internal' => 'Submit complaint through customer service',
                    'external' => 'Contact Data Protection Authority of Botswana',
                    'external_contact' => 'info@dpa.gov.bw',
                ],
                'updates' => [
                    'last_updated' => now()->toISOString(),
                    'version' => '1.0',
                    'change_notification' => 'Customers will be notified of material changes',
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $privacyNotice
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get privacy notice: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get privacy notice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get consent management information
     */
    public function getConsentManagement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customer,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customerId = $request->customer_id;
            
            // Get consent history
            $consentHistory = $this->getConsentHistory($customerId);
            
            // Get current consent status
            $currentConsent = $this->getCurrentConsentStatus($customerId);
            
            // Get consent withdrawal options
            $withdrawalOptions = $this->getConsentWithdrawalOptions();

            return response()->json([
                'success' => true,
                'data' => [
                    'customer_id' => $customerId,
                    'current_consent' => $currentConsent,
                    'consent_history' => $consentHistory,
                    'withdrawal_options' => $withdrawalOptions,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get consent management: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get consent management',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Withdraw consent
     */
    public function withdrawConsent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customer,id',
            'consent_type' => 'required|string',
            'reason' => 'nullable|string|max:500',
            'verification_method' => 'required|in:otp,email,phone,id_verification',
            'verification_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify the request
            $verification = $this->verifyDataSubjectRequest(
                $request->customer_id,
                $request->verification_method,
                $request->verification_code
            );

            if (!$verification['verified']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request verification failed',
                    'error' => $verification['error']
                ], 401);
            }

            // Process consent withdrawal
            $result = $this->processConsentWithdrawal(
                $request->customer_id,
                $request->consent_type,
                $request->reason
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Consent withdrawal failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Consent withdrawal failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify data subject request
     */
    protected function verifyDataSubjectRequest(int $customerId, string $method, string $code): array
    {
        try {
            $customer = Customer::findOrFail($customerId);
            
            switch ($method) {
                case 'otp':
                    return $this->verifyOTP($customer, $code);
                case 'email':
                    return $this->verifyEmail($customer, $code);
                case 'phone':
                    return $this->verifyPhone($customer, $code);
                case 'id_verification':
                    return $this->verifyId($customer, $code);
                default:
                    return [
                        'verified' => false,
                        'error' => 'Invalid verification method'
                    ];
            }

        } catch (\Exception $e) {
            return [
                'verified' => false,
                'error' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify OTP
     */
    protected function verifyOTP(Customer $customer, string $code): array
    {
        // This would integrate with the OTP system
        // For now, return true for demonstration
        return [
            'verified' => true,
            'method' => 'otp'
        ];
    }

    /**
     * Verify email
     */
    protected function verifyEmail(Customer $customer, string $code): array
    {
        // This would verify email verification code
        return [
            'verified' => true,
            'method' => 'email'
        ];
    }

    /**
     * Verify phone
     */
    protected function verifyPhone(Customer $customer, string $code): array
    {
        // This would verify phone verification code
        return [
            'verified' => true,
            'method' => 'phone'
        ];
    }

    /**
     * Verify ID
     */
    protected function verifyId(Customer $customer, string $code): array
    {
        // This would verify ID verification
        return [
            'verified' => true,
            'method' => 'id_verification'
        ];
    }

    /**
     * Get consent history
     */
    protected function getConsentHistory(int $customerId): array
    {
        // This would get consent history from the database
        return [
            [
                'consent_type' => 'marketing',
                'status' => 'given',
                'date' => now()->subDays(30)->toISOString(),
                'method' => 'email',
            ],
            [
                'consent_type' => 'data_processing',
                'status' => 'given',
                'date' => now()->subDays(30)->toISOString(),
                'method' => 'web_form',
            ],
        ];
    }

    /**
     * Get current consent status
     */
    protected function getCurrentConsentStatus(int $customerId): array
    {
        return [
            'marketing' => true,
            'data_processing' => true,
            'data_sharing' => false,
            'analytics' => true,
        ];
    }

    /**
     * Get consent withdrawal options
     */
    protected function getConsentWithdrawalOptions(): array
    {
        return [
            'marketing' => 'Withdraw consent for marketing communications',
            'data_processing' => 'Withdraw consent for data processing',
            'data_sharing' => 'Withdraw consent for data sharing',
            'analytics' => 'Withdraw consent for analytics tracking',
        ];
    }

    /**
     * Process consent withdrawal
     */
    protected function processConsentWithdrawal(int $customerId, string $consentType, ?string $reason): array
    {
        try {
            // Update customer consent status
            $customer = Customer::findOrFail($customerId);
            $customer->update([
                'consent_' . $consentType => false,
                'consent_withdrawn_at' => now(),
                'consent_withdrawal_reason' => $reason,
            ]);

            // Log the withdrawal
            app(\AlphaDirect\Services\RekycAuditService::class)->logActivity(
                $customerId,
                'consent_withdrawn',
                "Consent withdrawn for: {$consentType}",
                [
                    'consent_type' => $consentType,
                    'reason' => $reason,
                    'withdrawn_at' => now()->toISOString(),
                ]
            );

            return [
                'success' => true,
                'message' => 'Consent withdrawn successfully',
                'consent_type' => $consentType,
                'withdrawn_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Consent withdrawal failed',
                'error' => $e->getMessage()
            ];
        }
    }
}
