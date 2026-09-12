<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use AlphaDirect\Services\RekycNotificationService;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycCampaign;
use Illuminate\Support\Facades\Validator;

class RekycNotificationController extends Controller
{
    protected $notificationService;

    public function __construct(RekycNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send Re-KYC link to specific customer
     */
    public function sendLink(Request $request, $linkId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,whatsapp,sms'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $link = RekycLink::with(['customer', 'campaign'])->findOrFail($linkId);
            $channels = $request->get('channels', ['email']);
            
            $results = $this->notificationService->sendRekycLink($link, $channels);

            return response()->json([
                'success' => true,
                'message' => 'Notifications sent successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk notifications for a campaign
     */
    public function sendBulkNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
            'link_ids' => 'required|array|min:1',
            'link_ids.*' => 'exists:rekyc_links,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,whatsapp,sms'
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
            $linkIds = $request->get('link_ids');
            $channels = $request->get('channels');
            
            $results = $this->notificationService->sendBulkNotifications($campaign, $linkIds, $channels);

            return response()->json([
                'success' => true,
                'message' => 'Bulk notifications sent successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send reminder notifications
     */
    public function sendReminders(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
            'days_since_sent' => 'integer|min:1|max:30'
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
            $daysSinceSent = $request->get('days_since_sent', 3);
            
            $results = $this->notificationService->sendReminderNotifications($campaign, $daysSinceSent);

            return response()->json([
                'success' => true,
                'message' => 'Reminder notifications sent successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send escalation notifications
     */
    public function sendEscalations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:rekyc_campaigns,id'
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
            $results = $this->notificationService->sendEscalationNotifications($campaign);

            return response()->json([
                'success' => true,
                'message' => 'Escalation notifications sent successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send escalation notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'nullable|exists:rekyc_campaigns,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = $request->get('campaign_id') ? 
                RekycCampaign::findOrFail($request->campaign_id) : null;
            
            $statistics = $this->notificationService->getNotificationStatistics($campaign);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test notification delivery
     */
    public function testNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customer,id',
            'channel' => 'required|in:email,whatsapp,sms'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customerId = $request->get('customer_id');
            $channel = $request->get('channel');
            
            $result = $this->notificationService->testNotification($customerId, $channel);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Test notification sent successfully' : 'Failed to send test notification',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send completion notification
     */
    public function sendCompletionNotification(Request $request, $linkId): JsonResponse
    {
        try {
            $link = RekycLink::with(['customer', 'campaign'])->findOrFail($linkId);
            
            if ($link->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Link must be completed before sending completion notification'
                ], 400);
            }
            
            $result = $this->notificationService->sendCompletionNotification($link);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Completion notification sent successfully' : 'Failed to send completion notification'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send completion notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification history for a link
     */
    public function getNotificationHistory(Request $request, $linkId): JsonResponse
    {
        try {
            $link = RekycLink::with(['customer', 'campaign'])->findOrFail($linkId);
            
            $history = [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'campaign_id' => $link->campaign_id,
                'delivery_method' => $link->delivery_method,
                'sent_at' => $link->sent_at,
                'opened_at' => $link->opened_at,
                'otp_verified_at' => $link->otp_verified_at,
                'completed_at' => $link->completed_at,
                'expires_at' => $link->expires_at,
                'status' => $link->status,
                'delivery_reference' => $link->delivery_reference,
            ];

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
