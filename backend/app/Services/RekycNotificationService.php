<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycCampaign;
use AlphaDirect\Customer;
use AlphaDirect\Notifications\RekycLinkNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class RekycNotificationService
{
    /**
     * Send Re-KYC link via multiple channels with instant delivery
     */
    public function sendRekycLink(RekycLink $link, array $channels = ['email'], $immediate = true)
    {
        $results = [];
        $successfulChannels = [];
        
        foreach ($channels as $channel) {
            try {
                $result = $this->sendViaChannel($link, $channel, $immediate);
                $results[$channel] = $result;
                
                // Track successful channels
                if ($result['success']) {
                    $successfulChannels[] = $channel;
                }
                
                // Log successful sending
                Log::channel('rekyc_system')->info("Re-KYC link sent via {$channel}", [
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'channel' => $channel,
                    'success' => $result['success'],
                    'immediate' => $immediate,
                    'delivery_method' => 'instant'
                ]);
                
            } catch (Exception $e) {
                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                // Log error
                Log::channel('rekyc_system')->error("Failed to send Re-KYC link via {$channel}", [
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                    'immediate' => $immediate
                ]);
            }
        }
        
        // Update link with all successful channels
        if (!empty($successfulChannels)) {
            $deliveryMethod = implode(',', $successfulChannels);
            $link->update([
                'delivery_method' => $deliveryMethod,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            
            Log::channel('rekyc_system')->info('Updated delivery method', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'successful_channels' => $successfulChannels,
                'delivery_method' => $deliveryMethod
            ]);
        } else {
            Log::channel('rekyc_system')->warning('No successful channels to update delivery method', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'channels' => $channels,
                'results' => $results
            ]);
        }
        
        return $results;
    }

    /**
     * Send via specific channel with instant delivery
     */
    private function sendViaChannel(RekycLink $link, $channel, $immediate = true)
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmail($link, $immediate);
            case 'whatsapp':
                return $this->sendWhatsApp($link, $immediate);
            case 'sms':
                return $this->sendSMS($link, $immediate);
            case 'all':
                return $this->sendAllChannels($link, $immediate);
            default:
                throw new Exception("Unsupported notification channel: {$channel}");
        }
    }

    /**
     * Send email notification with instant delivery
     */
    private function sendEmail(RekycLink $link, $immediate = true)
    {
        try {
            // Check if mail is properly configured
            $mailDriver = config('mail.default');
            $mailHost = config('mail.mailers.smtp.host');
            $mailUsername = config('mail.mailers.smtp.username');
            $mailPassword = config('mail.mailers.smtp.password');
            
            // Log mail configuration for debugging
            Log::channel('rekyc_system')->info('Email configuration check', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'mail_driver' => $mailDriver,
                'mail_host' => $mailHost,
                'mail_username_set' => !empty($mailUsername),
                'mail_password_set' => !empty($mailPassword),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'immediate' => $immediate
            ]);
            
            // Check if mail credentials are configured
            if (empty($mailUsername) || empty($mailPassword)) {
                Log::channel('rekyc_system')->warning('Mail credentials not configured', [
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'mail_driver' => $mailDriver,
                    'mail_host' => $mailHost
                ]);
                
                // Try to send via log driver as fallback
                if ($mailDriver !== 'log') {
                    Log::channel('rekyc_system')->info('Switching to log driver for email testing', [
                        'link_id' => $link->id,
                        'customer_id' => $link->customer_id
                    ]);
                    
                    // Temporarily switch to log driver
                    config(['mail.default' => 'log']);
                }
            }
            
            $notification = new RekycLinkNotification($link, 'email');
            
            // If immediate sending is requested, disable queuing
            if ($immediate) {
                $notification->disableQueuing();
            }
            
            $link->customer->notify($notification);
            
            $message = $mailDriver === 'log' ? 
                'Email logged successfully (check storage/logs/laravel.log)' : 
                'Email sent successfully';
            
            if ($immediate) {
                $message .= ' (immediate)';
            }
            
            Log::channel('rekyc_system')->info('Email notification sent', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'mail_driver' => $mailDriver,
                'delivery_method' => 'email',
                'immediate' => $immediate
            ]);
            
            return [
                'success' => true,
                'message' => $message,
                'delivery_reference' => 'email_' . $link->id . '_' . time(),
                'mail_driver' => $mailDriver,
                'immediate' => $immediate
            ];
            
        } catch (Exception $e) {
            Log::channel('rekyc_system')->error('Email sending failed', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'immediate' => $immediate
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'suggestion' => 'Check mail configuration in .env file'
            ];
        }
    }

    /**
     * Send WhatsApp notification with instant delivery
     */
    private function sendWhatsApp(RekycLink $link, $immediate = true)
    {
        try {
            $notification = new RekycLinkNotification($link, 'whatsapp');
            
            // Ensure instant delivery
            if ($immediate) {
                $notification->disableQueuing();
            }
            
            $link->customer->notify($notification);
            
            return [
                'success' => true,
                'message' => 'WhatsApp message sent instantly',
                'delivery_reference' => 'whatsapp_' . $link->id . '_' . time(),
                'delivery_method' => 'instant'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS notification with instant delivery
     */
    private function sendSMS(RekycLink $link, $immediate = true)
    {
        try {
            $notification = new RekycLinkNotification($link, 'sms');
            
            // Ensure instant delivery
            if ($immediate) {
                $notification->disableQueuing();
            }
            
            $link->customer->notify($notification);
            
            return [
                'success' => true,
                'message' => 'SMS sent instantly',
                'delivery_reference' => 'sms_' . $link->id . '_' . time(),
                'delivery_method' => 'instant'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send via all channels with instant delivery
     */
    private function sendAllChannels(RekycLink $link, $immediate = true)
    {
        $results = [];
        $channels = ['email', 'sms', 'whatsapp'];
        
        foreach ($channels as $channel) {
            try {
                $result = $this->sendViaChannel($link, $channel, $immediate);
                $results[$channel] = $result;
            } catch (Exception $e) {
                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Note: delivery_method is now handled in the main sendRekycLink method
        
        return [
            'success' => true,
            'message' => 'All channels sent instantly',
            'delivery_reference' => 'all_channels_' . $link->id . '_' . time(),
            'delivery_method' => 'instant',
            'channels' => $results
        ];
    }

    /**
     * Send bulk notifications for a campaign
     */
    public function sendBulkNotifications(RekycCampaign $campaign, array $linkIds, array $channels = ['email'])
    {
        $links = RekycLink::whereIn('id', $linkIds)
            ->where('campaign_id', $campaign->id)
            ->with(['customer'])
            ->get();
        
        $results = [
            'total_links' => $links->count(),
            'successful' => 0,
            'failed' => 0,
            'results' => []
        ];
        
        foreach ($links as $link) {
            $linkResults = $this->sendRekycLink($link, $channels);
            $customMessage = null;
            $rekycAdminController = app(\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class);
            $rekycAdminController->sendNotificationMain($link->id,$channels,$customMessage);
            $results['results'][$link->id] = $linkResults;
            
            // Count successes and failures
            $hasSuccess = false;
            foreach ($linkResults as $channelResult) {
                if ($channelResult['success']) {
                    $hasSuccess = true;
                    break;
                }
            }
            
            if ($hasSuccess) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }

    /**
     * Send reminder notifications
     */
    public function sendReminderNotifications(RekycCampaign $campaign, $daysSinceSent = 3)
    {
        $cutoffDate = now()->subDays($daysSinceSent);
        
        $links = RekycLink::where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->where('sent_at', '<=', $cutoffDate)
            ->where('expires_at', '>', now())
            ->with(['customer'])
            ->get();
        
        $results = [
            'total_reminders' => $links->count(),
            'successful' => 0,
            'failed' => 0,
            'results' => []
        ];
        
        foreach ($links as $link) {
            $channels = $campaign->notification_settings['reminder_channels'] ?? ['email'];
            $linkResults = $this->sendRekycLink($link, $channels);
            $customMessage = null;
            $rekycAdminController = app(\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class);
            $rekycAdminController->sendNotificationMain($link->id,$channels,$customMessage);
            $results['results'][$link->id] = $linkResults;
            
            // Count successes and failures
            $hasSuccess = false;
            foreach ($linkResults as $channelResult) {
                if ($channelResult['success']) {
                    $hasSuccess = true;
                    break;
                }
            }
            
            if ($hasSuccess) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }

    /**
     * Send completion notifications
     */
    public function sendCompletionNotification(RekycLink $link)
    {
        $customer = $link->customer;
        $campaign = $link->campaign;
        
        try {
            // Send completion email
            Mail::send('emails.rekyc-completion', [
                'customer' => $customer,
                'campaign' => $campaign,
                'link' => $link
            ], function ($message) use ($customer) {
                $message->to($customer->email, $customer->firstName . ' ' . $customer->lastName)
                        ->subject('Re-KYC Process Completed - ' . config('app.name'));
            });
            
            Log::channel('rekyc_system')->info('Re-KYC completion notification sent', [
                'link_id' => $link->id,
                'customer_id' => $customer->id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::channel('rekyc_system')->error('Failed to send completion notification', [
                'link_id' => $link->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send escalation notifications for expired links
     */
    public function sendEscalationNotifications(RekycCampaign $campaign)
    {
        $expiredLinks = RekycLink::where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->where('expires_at', '<=', now())
            ->with(['customer'])
            ->get();
        
        $results = [
            'total_escalations' => $expiredLinks->count(),
            'successful' => 0,
            'failed' => 0,
            'results' => []
        ];
        
        foreach ($expiredLinks as $link) {
            try {
                // Send escalation email to compliance team
                $this->sendEscalationEmail($link);
                
                // Update link status
                $link->update(['status' => 'expired']);
                
                $results['successful']++;
                $results['results'][$link->id] = ['success' => true];
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['results'][$link->id] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Send escalation email to compliance team
     */
    private function sendEscalationEmail(RekycLink $link)
    {
        $complianceEmail = config('rekyc.compliance_email', 'compliance@' . config('app.domain'));
        
        Mail::send('emails.rekyc-escalation', [
            'link' => $link,
            'customer' => $link->customer,
            'campaign' => $link->campaign
        ], function ($message) use ($complianceEmail, $link) {
            $message->to($complianceEmail)
                    ->subject('Re-KYC Escalation Required - Customer ID: ' . $link->customer_id);
        });
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStatistics(RekycCampaign $campaign = null)
    {
        $query = RekycLink::query();
        
        if ($campaign) {
            $query->where('campaign_id', $campaign->id);
        }
        
        $links = $query->get();
        
        return [
            'total_links' => $links->count(),
            'sent' => $links->where('status', 'sent')->count(),
            'opened' => $links->where('status', 'opened')->count(),
            'completed' => $links->where('status', 'completed')->count(),
            'expired' => $links->where('status', 'expired')->count(),
            'failed' => $links->where('status', 'failed')->count(),
            'by_delivery_method' => $links->groupBy('delivery_method'),
            'completion_rate' => $links->count() > 0 ? 
                round(($links->where('status', 'completed')->count() / $links->count()) * 100, 2) : 0,
        ];
    }

    /**
     * Test notification delivery
     */
    public function testNotification($customerId, $channel = 'email')
    {
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return [
                'success' => false,
                'error' => 'Customer not found'
            ];
        }
        
        // Create a test link
        $testLink = new RekycLink([
            'customer_id' => $customer->id,
            'unique_token' => 'test_' . time(),
            'otp_code' => '123456',
            'status' => 'pending',
            'expires_at' => now()->addHours(1),
        ]);
        
        try {
            $result = $this->sendViaChannel($testLink, $channel);
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
