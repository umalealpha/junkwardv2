<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\AdGroupKycCampaign;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\AdGroupKycActivity;
use AlphaDirect\Models\EmployerGroup;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\Services\AdGroupKycNotificationService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class AdGroupKycService
{
    protected $notificationService;

    public function __construct(AdGroupKycNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new AD Group KYC campaign
     */
    public function createCampaign(array $data)
    {
        $campaign = AdGroupKycCampaign::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            // Honor caller-supplied status (admin create passes 'active');
            // legacy callers that omit it keep the old 'draft' default.
            'status' => $data['status'] ?? 'draft',
            'target_criteria' => $data['target_criteria'] ?? null,
            'notification_settings' => $data['notification_settings'] ?? null,
            'kyc_fields' => $data['kyc_fields'] ?? null,
            'ocr_enabled' => $data['ocr_enabled'] ?? false,
            'fraud_detection_enabled' => $data['fraud_detection_enabled'] ?? false,
            'link_expiry_hours' => $data['link_expiry_hours'] ?? 72,
            'otp_expiry_minutes' => $data['otp_expiry_minutes'] ?? 10,
            'max_attempts' => $data['max_attempts'] ?? 3,
            'escalation_days' => $data['escalation_days'] ?? 7,
            'reminder_days' => $data['reminder_days'] ?? [3, 7, 14],
            'settings' => $data['settings'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'employer_group_id' => $data['employer_group_id'] ?? null,
            'product_id' => $data['product_id'] ?? 12, // Default to AD Group product
            'created_by' => auth()->id(),
        ]);

        return $campaign;
    }

    /**
     * Generate KYC links for AD Group policies
     */
    public function generateLinksForPolicies(AdGroupKycCampaign $campaign, array $policyIds)
    {
        $links = [];

        foreach ($policyIds as $policyId) {
            $policy = Policy::with('customer')->find($policyId);

            if (!$policy || !$policy->customer) {
                Log::warning("Policy or customer not found for policy ID: {$policyId}");
                continue;
            }

            // Check if link already exists for this policy
            $existingLink = AdGroupKycLink::where('campaign_id', $campaign->id)
                ->where('policy_id', $policyId)
                ->first();

            if ($existingLink) {
                Log::info("KYC link already exists for policy ID: {$policyId}");
                $links[] = $existingLink;
                continue;
            }

            $link = AdGroupKycLink::create([
                'campaign_id' => $campaign->id,
                'customer_id' => $policy->customer->id,
                'policy_id' => $policyId,
                'unique_token' => Str::random(12),
                'otp_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'expires_at' => Carbon::now()->addHours($campaign->link_expiry_hours),
            ]);

            // Log activity
            AdGroupKycActivity::logActivity(
                $link->id,
                $policy->customer->id,
                $policyId,
                AdGroupKycActivity::TYPE_LINK_CREATED,
                'KYC link created for AD Group policy',
                ['policy_number' => $policy->policyNumber]
            );

            $links[] = $link;
        }

        return $links;
    }

    /**
     * Generate KYC links for customers in an employer group
     */
    public function generateLinksForEmployerGroup(AdGroupKycCampaign $campaign, $employerGroupId)
    {
        // Get all AD Group policies for the employer group
        $policies = Policy::whereHas('employerGroupPolicies', function($query) use ($employerGroupId) {
            $query->where('employer_group_id', $employerGroupId);
        })
        ->where('product_id', 12) // AD Group product
        ->where('is_test_policy', 0)
        ->with('customer')
        ->get();

        $policyIds = $policies->pluck('id')->toArray();

        return $this->generateLinksForPolicies($campaign, $policyIds);
    }

    /**
     * Send KYC links via multiple channels
     */
    public function sendKycLinks(array $linkIds, array $channels = ['email'], $immediate = true)
    {
        $results = [];

        foreach ($linkIds as $linkId) {
            $link = AdGroupKycLink::with(['customer', 'policy'])->find($linkId);

            if (!$link) {
                $results[$linkId] = [
                    'success' => false,
                    'error' => 'Link not found'
                ];
                continue;
            }

            try {
                $result = $this->notificationService->sendKycLink($link, $channels, $immediate);
                $results[$linkId] = $result;

                // Log successful sending
                Log::info("AD Group KYC link sent", [
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'policy_id' => $link->policy_id,
                    'channels' => $channels,
                    'immediate' => $immediate
                ]);

            } catch (Exception $e) {
                $results[$linkId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                Log::error("Failed to send AD Group KYC link", [
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'policy_id' => $link->policy_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create a default campaign for AD Group policies
     */
    public function createDefaultAdGroupCampaign($employerGroupId, $employerGroupName = null)
    {
        $campaignData = [
            'name' => 'AD Group KYC Campaign - ' . ($employerGroupName ?: $employerGroupId),
            'description' => 'Automatic KYC campaign for AD Group policies',
            'status' => 'active',
            'target_criteria' => [
                'product_id' => 12,
                'employer_group_id' => $employerGroupId,
                'is_test_policy' => false
            ],
            'notification_settings' => [
                'email' => true,
                'sms' => true,
                'whatsapp' => true
            ],
            'kyc_fields' => [
                'personal_info' => true,
                'contact_info' => true,
                'identity_documents' => true,
                'address_verification' => true
            ],
            'link_expiry_hours' => 168, // 7 days
            'otp_expiry_minutes' => 15,
            'max_attempts' => 3,
            'escalation_days' => 7,
            'reminder_days' => [3, 7, 14],
            'employer_group_id' => $employerGroupId,
            'product_id' => 12,
            'start_date' => now(),
            'end_date' => now()->addDays(30)
        ];

        return $this->createCampaign($campaignData);
    }

    /**
     * Process KYC completion
     */
    public function processKycCompletion(AdGroupKycLink $link, array $kycData)
    {
        try {
            // Mark link as completed
            $link->markAsCompleted();

            // Log completion activity
            AdGroupKycActivity::logActivity(
                $link->id,
                $link->customer_id,
                $link->policy_id,
                AdGroupKycActivity::TYPE_KYC_COMPLETED,
                'KYC process completed successfully',
                $kycData
            );

            // Update customer KYC status if needed
            if ($link->customer) {
                // You can add logic here to update customer KYC status
                // For example, update a KYC status field in the customer table
            }

            Log::info("AD Group KYC completed successfully", [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id
            ]);

            return true;

        } catch (Exception $e) {
            Log::error("Failed to process AD Group KYC completion", [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStats(AdGroupKycCampaign $campaign)
    {
        $totalLinks = $campaign->links()->count();
        $sentLinks = $campaign->links()->where('status', 'sent')->count();
        $openedLinks = $campaign->links()->where('status', 'opened')->count();
        $completedLinks = $campaign->links()->where('status', 'completed')->count();
        $expiredLinks = $campaign->links()->where('status', 'expired')->count();
        $pendingLinks = $campaign->links()->where('status', 'pending')->count();
        $otpVerified = $campaign->links()->where('status', 'otp_verified')->count();

        // Average sent -> opened response time in minutes across links that
        // have both timestamps (the campaign stats tiles reference this).
        $responseTime = 0;
        $openedWithSent = $campaign->links()
            ->whereNotNull('sent_at')
            ->whereNotNull('opened_at')
            ->get(['sent_at', 'opened_at']);
        if ($openedWithSent->count() > 0) {
            $totalMinutes = $openedWithSent->sum(
                fn ($link) => $link->sent_at->diffInMinutes($link->opened_at)
            );
            $responseTime = round($totalMinutes / $openedWithSent->count(), 0);
        }

        return [
            'total_links' => $totalLinks,
            'sent_links' => $sentLinks,
            'opened_links' => $openedLinks,
            'completed_links' => $completedLinks,
            'expired_links' => $expiredLinks,
            'pending_links' => $pendingLinks,
            'otp_verified' => $otpVerified,
            'response_time' => $responseTime,
            'completion_rate' => $totalLinks > 0 ? round(($completedLinks / $totalLinks) * 100, 1) : 0,
            'open_rate' => $sentLinks > 0 ? round(($openedLinks / $sentLinks) * 100, 1) : 0,
        ];
    }
}

