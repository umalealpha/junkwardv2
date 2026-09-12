<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycActivity;
use AlphaDirect\Models\RekycDocument;
use AlphaDirect\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class RekycDpaComplianceService
{
    protected $auditService;
    protected $securityService;

    public function __construct(RekycAuditService $auditService, RekycSecurityService $securityService)
    {
        $this->auditService = $auditService;
        $this->securityService = $securityService;
    }

    /**
     * Process data subject request (access, rectification, erasure, portability)
     */
    public function processDataSubjectRequest(int $customerId, string $requestType, array $requestData = []): array
    {
        try {
            $this->auditService->logActivity(
                $customerId,
                'data_subject_request',
                "Data subject request: {$requestType}",
                $requestData
            );

            switch ($requestType) {
                case 'access':
                    return $this->handleAccessRequest($customerId, $requestData);
                case 'rectification':
                    return $this->handleRectificationRequest($customerId, $requestData);
                case 'erasure':
                    return $this->handleErasureRequest($customerId, $requestData);
                case 'portability':
                    return $this->handlePortabilityRequest($customerId, $requestData);
                case 'restriction':
                    return $this->handleRestrictionRequest($customerId, $requestData);
                case 'objection':
                    return $this->handleObjectionRequest($customerId, $requestData);
                default:
                    throw new Exception("Invalid request type: {$requestType}");
            }

        } catch (Exception $e) {
            Log::error("Data subject request failed: " . $e->getMessage());
            
            $this->auditService->logActivity(
                $customerId,
                'data_subject_request_failed',
                "Data subject request failed: {$requestType}",
                ['error' => $e->getMessage()]
            );

            return [
                'success' => false,
                'message' => 'Data subject request failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle data access request
     */
    protected function handleAccessRequest(int $customerId, array $requestData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        // Get all Re-KYC data for the customer
        $rekycLinks = RekycLink::where('customer_id', $customerId)->get();
        $rekycActivities = RekycActivity::where('customer_id', $customerId)->get();
        $rekycDocuments = RekycDocument::where('customer_id', $customerId)->get();

        $data = [
            'customer_id' => $customerId,
            'personal_data' => $this->getPersonalData($customer),
            'rekyc_links' => $this->formatRekycLinks($rekycLinks),
            'rekyc_activities' => $this->formatRekycActivities($rekycActivities),
            'rekyc_documents' => $this->formatRekycDocuments($rekycDocuments),
            'data_categories' => $this->getDataCategories($customerId),
            'processing_purposes' => $this->getProcessingPurposes(),
            'retention_periods' => $this->getRetentionPeriods(),
            'data_sources' => $this->getDataSources($customerId),
            'third_party_sharing' => $this->getThirdPartySharing($customerId),
        ];

        $this->auditService->logActivity(
            $customerId,
            'data_access_granted',
            'Data access request granted',
            ['data_categories' => array_keys($data)]
        );

        return [
            'success' => true,
            'message' => 'Data access request processed successfully',
            'data' => $data,
            'generated_at' => now()->toISOString(),
            'request_id' => $this->generateRequestId($customerId, 'access')
        ];
    }

    /**
     * Handle data rectification request
     */
    protected function handleRectificationRequest(int $customerId, array $requestData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        // Validate rectification data
        $validation = $this->validateRectificationData($requestData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Invalid rectification data',
                'errors' => $validation['errors']
            ];
        }

        // Update customer data
        $updatedFields = [];
        foreach ($requestData['fields'] as $field => $value) {
            if (in_array($field, $this->getUpdatableFields())) {
                $customer->update([$field => $value]);
                $updatedFields[] = $field;
            }
        }

        // Update Re-KYC links if needed
        $this->updateRekycLinks($customerId, $requestData);

        $this->auditService->logActivity(
            $customerId,
            'data_rectification',
            'Data rectification completed',
            ['updated_fields' => $updatedFields]
        );

        return [
            'success' => true,
            'message' => 'Data rectification completed successfully',
            'updated_fields' => $updatedFields,
            'request_id' => $this->generateRequestId($customerId, 'rectification')
        ];
    }

    /**
     * Handle data erasure request (right to be forgotten)
     */
    protected function handleErasureRequest(int $customerId, array $requestData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        // Check if erasure is legally permissible
        $erasureCheck = $this->checkErasurePermissibility($customerId);
        if (!$erasureCheck['permissible']) {
            return [
                'success' => false,
                'message' => 'Data erasure not permissible',
                'reasons' => $erasureCheck['reasons']
            ];
        }

        // Perform data erasure
        $erasedData = $this->performDataErasure($customerId, $requestData);

        $this->auditService->logActivity(
            $customerId,
            'data_erasure',
            'Data erasure completed',
            ['erased_data' => $erasedData]
        );

        return [
            'success' => true,
            'message' => 'Data erasure completed successfully',
            'erased_data' => $erasedData,
            'request_id' => $this->generateRequestId($customerId, 'erasure')
        ];
    }

    /**
     * Handle data portability request
     */
    protected function handlePortabilityRequest(int $customerId, array $requestData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        // Get portable data
        $portableData = $this->getPortableData($customerId, $requestData);

        $this->auditService->logActivity(
            $customerId,
            'data_portability',
            'Data portability request processed',
            ['data_categories' => array_keys($portableData)]
        );

        return [
            'success' => true,
            'message' => 'Data portability request processed successfully',
            'data' => $portableData,
            'format' => $requestData['format'] ?? 'json',
            'request_id' => $this->generateRequestId($customerId, 'portability')
        ];
    }

    /**
     * Handle data processing restriction request
     */
    protected function handleRestrictionRequest(int $customerId, array $requestData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        // Apply processing restrictions
        $restrictions = $this->applyProcessingRestrictions($customerId, $requestData);

        $this->auditService->logActivity(
            $customerId,
            'data_restriction',
            'Data processing restrictions applied',
            ['restrictions' => $restrictions]
        );

        return [
            'success' => true,
            'message' => 'Data processing restrictions applied successfully',
            'restrictions' => $restrictions,
            'request_id' => $this->generateRequestId($customerId, 'restriction')
        ];
    }

    /**
     * Handle objection to processing request
     */
    protected function handleObjectionRequest(int $customerId, array $requestData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        // Process objection
        $objection = $this->processObjection($customerId, $requestData);

        $this->auditService->logActivity(
            $customerId,
            'data_objection',
            'Data processing objection processed',
            ['objection' => $objection]
        );

        return [
            'success' => true,
            'message' => 'Data processing objection processed successfully',
            'objection' => $objection,
            'request_id' => $this->generateRequestId($customerId, 'objection')
        ];
    }

    /**
     * Get personal data for access request
     */
    protected function getPersonalData(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
            // Add other personal data fields as needed
        ];
    }

    /**
     * Format Re-KYC links for access request
     */
    protected function formatRekycLinks($links): array
    {
        return $links->map(function ($link) {
            return [
                'id' => $link->id,
                'campaign_id' => $link->campaign_id,
                'status' => $link->status,
                'created_at' => $link->created_at,
                'sent_at' => $link->sent_at,
                'opened_at' => $link->opened_at,
                'otp_verified_at' => $link->otp_verified_at,
                'completed_at' => $link->completed_at,
                'expires_at' => $link->expires_at,
                'consent_data' => $link->consent_data ? 
                    json_decode($this->securityService->decryptData($link->consent_data), true) : null,
            ];
        })->toArray();
    }

    /**
     * Format Re-KYC activities for access request
     */
    protected function formatRekycActivities($activities): array
    {
        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'entity_type' => $activity->entity_type,
                'entity_id' => $activity->entity_id,
                'action' => $activity->action,
                'description' => $activity->description,
                'metadata' => $activity->metadata,
                'ip_address' => $activity->ip_address,
                'user_agent' => $activity->user_agent,
                'created_at' => $activity->created_at,
            ];
        })->toArray();
    }

    /**
     * Format Re-KYC documents for access request
     */
    protected function formatRekycDocuments($documents): array
    {
        return $documents->map(function ($document) {
            return [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'file_name' => $this->securityService->decryptData($document->file_name),
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'status' => $document->status,
                'uploaded_at' => $document->uploaded_at,
                'verified_at' => $document->verified_at,
                'ocr_data' => $document->ocr_data ? 
                    json_decode($this->securityService->decryptData($document->ocr_data), true) : null,
            ];
        })->toArray();
    }

    /**
     * Get data categories for the customer
     */
    protected function getDataCategories(int $customerId): array
    {
        return [
            'personal_identifiers' => ['name', 'email', 'phone', 'id_number'],
            'demographic_data' => ['age', 'gender', 'address'],
            'financial_data' => ['account_balance', 'transaction_history'],
            'behavioral_data' => ['login_patterns', 'usage_statistics'],
            'technical_data' => ['ip_address', 'device_info', 'browser_info'],
        ];
    }

    /**
     * Get processing purposes
     */
    protected function getProcessingPurposes(): array
    {
        return [
            'kyc_verification' => 'Know Your Customer verification and compliance',
            'risk_assessment' => 'Risk assessment and fraud prevention',
            'regulatory_compliance' => 'Regulatory compliance and reporting',
            'service_delivery' => 'Service delivery and customer support',
            'marketing' => 'Marketing and promotional activities (with consent)',
        ];
    }

    /**
     * Get retention periods
     */
    protected function getRetentionPeriods(): array
    {
        return [
            'personal_data' => '7 years from last interaction',
            'kyc_documents' => '7 years from verification date',
            'transaction_data' => '7 years from transaction date',
            'audit_logs' => '1 year from creation date',
            'marketing_data' => 'Until consent withdrawal',
        ];
    }

    /**
     * Get data sources
     */
    protected function getDataSources(int $customerId): array
    {
        return [
            'direct_collection' => 'Data provided directly by the customer',
            'third_party_verification' => 'Data verified through third-party services',
            'public_records' => 'Data obtained from public records',
            'cookies_tracking' => 'Data collected through website cookies and tracking',
        ];
    }

    /**
     * Get third-party sharing information
     */
    protected function getThirdPartySharing(int $customerId): array
    {
        return [
            'regulatory_authorities' => 'Data shared with regulatory authorities as required by law',
            'service_providers' => 'Data shared with trusted service providers under strict agreements',
            'fraud_prevention' => 'Data shared for fraud prevention and risk management',
            'legal_requirements' => 'Data shared when required by legal process',
        ];
    }

    /**
     * Validate rectification data
     */
    protected function validateRectificationData(array $data): array
    {
        $errors = [];
        
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            $errors[] = 'Fields to update must be specified';
        }

        $updatableFields = $this->getUpdatableFields();
        foreach ($data['fields'] as $field => $value) {
            if (!in_array($field, $updatableFields)) {
                $errors[] = "Field '{$field}' cannot be updated";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get updatable fields
     */
    protected function getUpdatableFields(): array
    {
        return [
            'name', 'email', 'phone', 'address', 'date_of_birth',
            'occupation', 'employer', 'income', 'marital_status'
        ];
    }

    /**
     * Update Re-KYC links if needed
     */
    protected function updateRekycLinks(int $customerId, array $requestData): void
    {
        // Update consent data if personal information changed
        if (isset($requestData['fields']['name']) || isset($requestData['fields']['email'])) {
            RekycLink::where('customer_id', $customerId)
                ->whereNotNull('consent_data')
                ->update([
                    'consent_data' => $this->securityService->encryptData(json_encode([
                        'updated_at' => now()->toISOString(),
                        'updated_fields' => array_keys($requestData['fields'])
                    ]))
                ]);
        }
    }

    /**
     * Check if data erasure is permissible
     */
    protected function checkErasurePermissibility(int $customerId): array
    {
        $reasons = [];
        
        // Check if customer has active policies
        $activePolicies = $this->getActivePolicies($customerId);
        if (!empty($activePolicies)) {
            $reasons[] = 'Customer has active insurance policies';
        }

        // Check if data is required for legal compliance
        $legalRequirements = $this->checkLegalRequirements($customerId);
        if ($legalRequirements['required']) {
            $reasons[] = 'Data required for legal compliance: ' . implode(', ', $legalRequirements['reasons']);
        }

        // Check if data is needed for legitimate business interests
        $businessInterests = $this->checkBusinessInterests($customerId);
        if ($businessInterests['required']) {
            $reasons[] = 'Data required for legitimate business interests: ' . implode(', ', $businessInterests['reasons']);
        }

        return [
            'permissible' => empty($reasons),
            'reasons' => $reasons
        ];
    }

    /**
     * Perform data erasure
     */
    protected function performDataErasure(int $customerId, array $requestData): array
    {
        $erasedData = [];

        // Erase Re-KYC specific data
        $rekycLinks = RekycLink::where('customer_id', $customerId)->get();
        foreach ($rekycLinks as $link) {
            $link->update([
                'consent_data' => null,
                'status' => 'erased'
            ]);
        }
        $erasedData['rekyc_links'] = $rekycLinks->count();

        // Erase Re-KYC activities
        $activities = RekycActivity::where('customer_id', $customerId)->get();
        foreach ($activities as $activity) {
            $activity->update([
                'metadata' => $this->securityService->encryptData(json_encode(['erased' => true])),
                'description' => 'Data erased per DPA request'
            ]);
        }
        $erasedData['rekyc_activities'] = $activities->count();

        // Erase Re-KYC documents
        $documents = RekycDocument::where('customer_id', $customerId)->get();
        foreach ($documents as $document) {
            $document->update([
                'file_path' => null,
                'file_name' => null,
                'ocr_data' => null,
                'status' => 'erased'
            ]);
        }
        $erasedData['rekyc_documents'] = $documents->count();

        return $erasedData;
    }

    /**
     * Get portable data
     */
    protected function getPortableData(int $customerId, array $requestData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        $portableData = [
            'personal_data' => $this->getPersonalData($customer),
            'rekyc_data' => $this->formatRekycLinks(
                RekycLink::where('customer_id', $customerId)->get()
            ),
        ];

        // Add specific data categories if requested
        if (isset($requestData['categories'])) {
            foreach ($requestData['categories'] as $category) {
                $portableData[$category] = $this->getDataByCategory($customerId, $category);
            }
        }

        return $portableData;
    }

    /**
     * Apply processing restrictions
     */
    protected function applyProcessingRestrictions(int $customerId, array $requestData): array
    {
        $restrictions = [];

        // Mark customer as restricted
        Customer::where('id', $customerId)->update([
            'data_processing_restricted' => true,
            'restriction_reason' => $requestData['reason'] ?? 'Data subject request',
            'restriction_applied_at' => now()
        ]);

        $restrictions['customer_restricted'] = true;

        // Restrict Re-KYC processing
        RekycLink::where('customer_id', $customerId)
            ->where('status', '!=', 'completed')
            ->update(['status' => 'restricted']);

        $restrictions['rekyc_processing_restricted'] = true;

        return $restrictions;
    }

    /**
     * Process objection to processing
     */
    protected function processObjection(int $customerId, array $requestData): array
    {
        $objection = [
            'customer_id' => $customerId,
            'objection_reason' => $requestData['reason'] ?? 'No specific reason provided',
            'objection_date' => now()->toISOString(),
            'status' => 'pending_review'
        ];

        // Log the objection
        $this->auditService->logActivity(
            $customerId,
            'processing_objection',
            'Objection to data processing received',
            $objection
        );

        // Update customer record
        Customer::where('id', $customerId)->update([
            'processing_objection' => true,
            'objection_reason' => $objection['objection_reason'],
            'objection_date' => now()
        ]);

        return $objection;
    }

    /**
     * Generate request ID
     */
    protected function generateRequestId(int $customerId, string $requestType): string
    {
        return 'DPA-' . $requestType . '-' . $customerId . '-' . now()->format('YmdHis');
    }

    /**
     * Get active policies for customer
     */
    protected function getActivePolicies(int $customerId): array
    {
        // This would integrate with the main insurance system
        // For now, return empty array
        return [];
    }

    /**
     * Check legal requirements
     */
    protected function checkLegalRequirements(int $customerId): array
    {
        // Check if data is required for regulatory compliance
        $reasons = [];
        
        // Check if customer has recent transactions
        $recentTransactions = $this->getRecentTransactions($customerId);
        if ($recentTransactions > 0) {
            $reasons[] = 'Recent financial transactions require data retention';
        }

        return [
            'required' => !empty($reasons),
            'reasons' => $reasons
        ];
    }

    /**
     * Check business interests
     */
    protected function checkBusinessInterests(int $customerId): array
    {
        $reasons = [];
        
        // Check if customer has outstanding claims
        $outstandingClaims = $this->getOutstandingClaims($customerId);
        if ($outstandingClaims > 0) {
            $reasons[] = 'Outstanding insurance claims require data retention';
        }

        return [
            'required' => !empty($reasons),
            'reasons' => $reasons
        ];
    }

    /**
     * Get recent transactions
     */
    protected function getRecentTransactions(int $customerId): int
    {
        // This would integrate with the main system
        // For now, return 0
        return 0;
    }

    /**
     * Get outstanding claims
     */
    protected function getOutstandingClaims(int $customerId): int
    {
        // This would integrate with the main system
        // For now, return 0
        return 0;
    }

    /**
     * Get data by category
     */
    protected function getDataByCategory(int $customerId, string $category): array
    {
        // Return data based on category
        switch ($category) {
            case 'personal_identifiers':
                return $this->getPersonalIdentifiers($customerId);
            case 'demographic_data':
                return $this->getDemographicData($customerId);
            case 'financial_data':
                return $this->getFinancialData($customerId);
            case 'behavioral_data':
                return $this->getBehavioralData($customerId);
            case 'technical_data':
                return $this->getTechnicalData($customerId);
            default:
                return [];
        }
    }

    /**
     * Get personal identifiers
     */
    protected function getPersonalIdentifiers(int $customerId): array
    {
        $customer = Customer::findOrFail($customerId);
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];
    }

    /**
     * Get demographic data
     */
    protected function getDemographicData(int $customerId): array
    {
        // This would return demographic data if available
        return [];
    }

    /**
     * Get financial data
     */
    protected function getFinancialData(int $customerId): array
    {
        // This would return financial data if available
        return [];
    }

    /**
     * Get behavioral data
     */
    protected function getBehavioralData(int $customerId): array
    {
        // This would return behavioral data if available
        return [];
    }

    /**
     * Get technical data
     */
    protected function getTechnicalData(int $customerId): array
    {
        $activities = RekycActivity::where('customer_id', $customerId)
            ->whereNotNull('ip_address')
            ->get(['ip_address', 'user_agent', 'created_at']);

        return $activities->toArray();
    }
}
