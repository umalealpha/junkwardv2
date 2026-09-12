<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\RekycCampaign;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycActivity;
use AlphaDirect\Models\RekycDocument;
use AlphaDirect\Customer;
use AlphaDirect\KYC;
use AlphaDirect\Notifications\RekycLinkNotification;
use AlphaDirect\Services\RekycSecurityService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class RekycService
{
    protected $securityService;

    public function __construct(RekycSecurityService $securityService)
    {
        $this->securityService = $securityService;
    }
    /**
     * Create a new Re-KYC campaign
     */
    public function createCampaign(array $data)
    {
        $campaign = RekycCampaign::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'target_criteria' => $data['target_criteria'] ?? null,
            'notification_settings' => $data['notification_settings'] ?? null,
            'kyc_fields' => $data['kyc_fields'] ?? null,
            'ocr_enabled' => $data['ocr_enabled'] ?? false,
            'fraud_detection_enabled' => $data['fraud_detection_enabled'] ?? false,
            'link_expiry_hours' => $data['link_expiry_hours'] ?? 72,
            'otp_expiry_minutes' => $data['otp_expiry_minutes'] ?? 10,
            'max_attempts' => $data['max_attempts'] ?? 3,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return $campaign;
    }

    /**
     * Generate Re-KYC links for customers
     */
    public function generateLinks(RekycCampaign $campaign, array $customerIds)
    {
        $links = [];
        
        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                continue;
            }

            // Check if link already exists for this customer and campaign
            $existingLink = RekycLink::where('campaign_id', $campaign->id)
                ->where('customer_id', $customerId)
                ->first();

            if ($existingLink) {
                $links[] = $existingLink;
                continue;
            }

            $link = RekycLink::create([
                'campaign_id' => $campaign->id,
                'customer_id' => $customerId,
                'unique_token' => $this->generateUniqueToken(),
                'otp_code' => $this->generateOTP(),
                'status' => 'pending',
                'expires_at' => Carbon::now()->addHours($campaign->link_expiry_hours),
            ]);

            $links[] = $link;
        }

        return $links;
    }

    /**
     * Send Re-KYC links to customers
     */
    public function sendLinks(array $linkIds, $deliveryMethod = 'email')
    {
        $links = RekycLink::whereIn('id', $linkIds)
            ->with(['customer', 'campaign'])
            ->get();

        $sentCount = 0;

        foreach ($links as $link) {
            try {
                // Update delivery method
                $link->update([
                    'delivery_method' => $deliveryMethod,
                    'status' => 'sent',
                    'sent_at' => Carbon::now(),
                ]);

                // Send notification
                $notification = new RekycLinkNotification($link, $deliveryMethod);
                $link->customer->notify($notification);

                // Log activity
                RekycActivity::logActivity(
                    $link->id,
                    $link->customer_id,
                    'link_sent',
                    'Re-KYC link sent via ' . $deliveryMethod,
                    [
                        'delivery_method' => $deliveryMethod,
                        'sent_at' => Carbon::now()->toISOString(),
                    ]
                );

                $sentCount++;
            } catch (Exception $e) {
                // Log error
                RekycActivity::logActivity(
                    $link->id,
                    $link->customer_id,
                    'link_send_failed',
                    'Failed to send Re-KYC link: ' . $e->getMessage(),
                    [
                        'error' => $e->getMessage(),
                        'delivery_method' => $deliveryMethod,
                    ]
                );
            }
        }

        return $sentCount;
    }

    /**
     * Access Re-KYC link and verify token
     */
    public function accessLink($token, $ipAddress = null, $userAgent = null)
    {
        $link = RekycLink::where('unique_token', $token)
            ->with(['customer', 'campaign'])
            ->first();

        if (!$link) {
            return ['success' => false, 'message' => 'Invalid or expired link'];
        }

        if ($link->status === 'expired' || $link->expires_at < Carbon::now()) {
            $link->update(['status' => 'expired']);
            return ['success' => false, 'message' => 'Link has expired'];
        }

        if ($link->status === 'completed') {
            return ['success' => false, 'message' => 'This link has already been used'];
        }

        // Update link status to opened
        $link->update([
            'status' => 'opened',
            'opened_at' => Carbon::now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Log activity
        RekycActivity::logActivity(
            $link->id,
            $link->customer_id,
            'link_opened',
            'Re-KYC link accessed',
            [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'opened_at' => Carbon::now()->toISOString(),
            ]
        );

        return [
            'success' => true,
            'link' => $link,
            'customer' => $this->maskCustomerData($link->customer),
            'campaign' => $link->campaign,
        ];
    }

    /**
     * Verify OTP code
     */
    public function verifyOTP($token, $otpCode, $ipAddress = null, $userAgent = null)
    {
        $link = RekycLink::where('unique_token', $token)->first();

        if (!$link) {
            return ['success' => false, 'message' => 'Invalid link'];
        }

        if ($link->status !== 'opened') {
            return ['success' => false, 'message' => 'Link must be accessed first'];
        }

        // Enforce max OTP attempts — blocks brute-force of the 6-digit code.
        // Checked BEFORE incrementing, so max_attempts=3 allows 3 real tries.
        if ($link->otp_attempts >= $link->campaign->max_attempts) {
            $link->update(['status' => 'failed']);
            return ['success' => false, 'message' => 'Maximum OTP attempts exceeded'];
        }

        // Enforce OTP expiry. Anchor the window on when the customer OPENED the
        // link (opened_at), NOT link creation — links are issued in batches and
        // opened later, so a created_at anchor would expire every real OTP (that
        // is why this check was disabled). verifyOTP requires status 'opened', so
        // opened_at is always set here.
        $otpAnchor = $link->opened_at ?? $link->created_at;
        $otpExpiry = $otpAnchor->copy()->addMinutes($link->campaign->otp_expiry_minutes);
        if (Carbon::now()->greaterThan($otpExpiry)) {
            return ['success' => false, 'message' => 'OTP has expired, please request a new link'];
        }

        // Increment attempts
        $link->increment('otp_attempts');

        if ($link->otp_code !== $otpCode) {
            RekycActivity::logActivity(
                $link->id,
                $link->customer_id,
                'otp_verification_failed',
                'Invalid OTP code entered',
                [
                    'attempted_otp' => $otpCode,
                    'attempts' => $link->otp_attempts,
                ]
            );

            return ['success' => false, 'message' => 'Invalid OTP code'];
        }

        // OTP verified successfully
        $link->update([
            'status' => 'otp_verified',
            'otp_verified_at' => Carbon::now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Log activity
        RekycActivity::logActivity(
            $link->id,
            $link->customer_id,
            'otp_verified',
            'OTP code verified successfully',
            [
                'verified_at' => Carbon::now()->toISOString(),
                'ip_address' => $ipAddress,
            ]
        );

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
            'link' => $link,
        ];
    }

    /**
     * Submit Re-KYC data
     */
    public function submitRekycData($token, array $data, $ipAddress = null, $userAgent = null)
    {
        $link = RekycLink::where('unique_token', $token)->first();

        if (!$link) {
            return ['success' => false, 'message' => 'Invalid link'];
        }

        if ($link->status !== 'otp_verified') {
            return ['success' => false, 'message' => 'OTP must be verified first'];
        }

        try {
            // Handle different response types
            if (isset($data['response_type'])) {
                switch ($data['response_type']) {
                    case 'no_change':
                        $this->handleNoChangeResponse($link, $data);
                        break;
                    case 'update':
                        $this->handleUpdateResponse($link, $data);
                        break;
                    case 'no_action':
                        $this->handleNoActionResponse($link, $data);
                        break;
                    default:
                        return ['success' => false, 'message' => 'Invalid response type'];
                }
            }

            // Mark as completed
            $link->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            // Log activity
            RekycActivity::logActivity(
                $link->id,
                $link->customer_id,
                'rekyc_completed',
                'Re-KYC process completed',
                [
                    'response_type' => $data['response_type'] ?? 'unknown',
                    'completed_at' => Carbon::now()->toISOString(),
                ]
            );

            return ['success' => true, 'message' => 'Re-KYC data submitted successfully'];

        } catch (Exception $e) {
            RekycActivity::logActivity(
                $link->id,
                $link->customer_id,
                'submission_failed',
                'Failed to submit Re-KYC data: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]
            );

            return ['success' => false, 'message' => 'Failed to submit data: ' . $e->getMessage()];
        }
    }

    /**
     * Handle no change response
     */
    private function handleNoChangeResponse(RekycLink $link, array $data)
    {
        // Record consent
        $link->update([
            'consent_data' => [
                'consent_given' => true,
                'consent_timestamp' => Carbon::now()->toISOString(),
                'no_change_confirmed' => true,
                'device_info' => $data['device_info'] ?? null,
            ],
        ]);

        RekycActivity::logActivity(
            $link->id,
            $link->customer_id,
            'consent_given',
            'Customer confirmed no changes needed',
            $data
        );
    }

    /**
     * Handle update response
     */
    private function handleUpdateResponse(RekycLink $link, array $data)
    {
        // Update KYC data
        $kycData = $data['kyc_data'] ?? [];
        
        if (!empty($kycData)) {
            $kyc = $link->customer->KYC ?? new KYC();
            $kyc->customer_id = $link->customer_id;
            
            foreach ($kycData as $field => $value) {
                if (in_array($field, $kyc->getFillable())) {
                    // Encrypt sensitive data before storing
                    $kyc->$field = $this->securityService->encryptData($value);
                }
            }
            
            $kyc->save();
        }

        // Handle document uploads
        if (isset($data['documents']) && is_array($data['documents'])) {
            $this->handleDocumentUploads($link, $data['documents']);
        }

        // Record consent
        $link->update([
            'consent_data' => [
                'consent_given' => true,
                'consent_timestamp' => Carbon::now()->toISOString(),
                'data_updated' => true,
                'updated_fields' => array_keys($kycData),
                'device_info' => $data['device_info'] ?? null,
            ],
        ]);

        RekycActivity::logActivity(
            $link->id,
            $link->customer_id,
            'data_updated',
            'Customer updated KYC information',
            [
                'updated_fields' => array_keys($kycData),
                'documents_uploaded' => count($data['documents'] ?? []),
            ]
        );
    }

    /**
     * Handle no action response
     */
    private function handleNoActionResponse(RekycLink $link, array $data)
    {
        $link->update([
            'consent_data' => [
                'consent_given' => false,
                'no_action_timestamp' => Carbon::now()->toISOString(),
                'reason' => $data['reason'] ?? 'No reason provided',
                'device_info' => $data['device_info'] ?? null,
            ],
        ]);

        RekycActivity::logActivity(
            $link->id,
            $link->customer_id,
            'no_action',
            'Customer took no action',
            $data
        );
    }

    /**
     * Handle document uploads
     */
    private function handleDocumentUploads(RekycLink $link, array $documents)
    {
        foreach ($documents as $document) {
            if (isset($document['file_path']) && isset($document['document_type'])) {
                RekycDocument::create([
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'document_type' => $document['document_type'],
                    'file_path' => $this->securityService->encryptData($document['file_path']),
                    'file_name' => $this->securityService->encryptData($document['file_name'] ?? 'document'),
                    'mime_type' => $document['mime_type'] ?? 'application/octet-stream',
                    'file_size' => $document['file_size'] ?? 0,
                    'file_hash' => $this->securityService->hashSensitiveData($document['file_hash'] ?? ''),
                    'status' => 'uploaded',
                ]);
            }
        }
    }

    /**
     * Generate unique token for link
     */
    private function generateUniqueToken()
    {
        do {
            $token = $this->securityService->generateSecureToken(64);
        } while (RekycLink::where('unique_token', $token)->exists());

        return $token;
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateOTP()
    {
        return $this->securityService->generateOTP(6);
    }

    /**
     * Mask customer data for security
     */
    private function maskCustomerData(Customer $customer)
    {
        return [
            'id' => $customer->id,
            'firstName' => $this->securityService->maskSensitiveData($customer->firstName, 'name'),
            'lastName' => $this->securityService->maskSensitiveData($customer->lastName, 'name'),
            'email' => $this->securityService->maskSensitiveData($customer->email, 'email'),
            'cellphone' => $this->securityService->maskSensitiveData($customer->cellphone, 'phone'),
        ];
    }
}
