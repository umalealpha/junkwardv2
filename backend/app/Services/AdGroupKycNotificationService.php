<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\AdGroupKycActivity;
use AlphaDirect\Notifications\AdGroupKycLinkNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Events\SendSms;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use Exception;

class AdGroupKycNotificationService
{
    /**
     * Send AD Group KYC link via multiple channels with instant delivery
     */
    public function sendKycLink(AdGroupKycLink $link, array $channels = ['email'], $immediate = true, ?string $customMessage = null)
    {
        $results = [];

        foreach ($channels as $channel) {
            try {
                $result = $this->sendViaChannel($link, $channel, $immediate, $customMessage);
                $results[$channel] = $result;

                // Log successful sending
                Log::info("AD Group KYC - Link sent via {$channel}", [
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'policy_id' => $link->policy_id,
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
                Log::error("AD Group KYC - Failed to send link via {$channel}", [
                    'link_id' => $link->id,
                    'customer_id' => $link->customer_id,
                    'policy_id' => $link->policy_id,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                    'immediate' => $immediate
                ]);
            }
        }

        return $results;
    }

    /**
     * Bulk-send KYC links for a campaign. The admin controller has always
     * called this, but the method never existed in V8 (the endpoint fataled).
     * Returns per-link results plus success/failure counts.
     */
    public function sendBulkNotifications($campaign, array $linkIds, array $channels = ['email'], $customMessage = null)
    {
        $links = AdGroupKycLink::whereIn('id', $linkIds)
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['completed', 'expired'])
            ->with('customer')
            ->get();

        return $this->dispatchToLinks($links, $channels, $customMessage);
    }

    /**
     * Re-send links that were sent >= $daysSinceSent days ago and are still
     * not completed. Same V8 gap as sendBulkNotifications.
     */
    public function sendReminderNotifications($campaign, $daysSinceSent = 3, array $channels = ['email'])
    {
        $links = AdGroupKycLink::where('campaign_id', $campaign->id)
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', now()->subDays((int) $daysSinceSent))
            ->whereNotIn('status', ['completed', 'expired', 'failed'])
            ->with('customer')
            ->get();

        return $this->dispatchToLinks($links, $channels);
    }

    /**
     * Escalation pass: links still incomplete past the campaign's
     * escalation_days window (from creation). Same V8 gap.
     */
    public function sendEscalationNotifications($campaign, array $channels = ['email'])
    {
        $escalationDays = (int) ($campaign->escalation_days ?: 7);

        $links = AdGroupKycLink::where('campaign_id', $campaign->id)
            ->where('created_at', '<=', now()->subDays($escalationDays))
            ->whereNotIn('status', ['completed', 'expired', 'failed'])
            ->with('customer')
            ->get();

        return $this->dispatchToLinks($links, $channels);
    }

    /**
     * Shared fan-out used by the bulk/reminder/escalation entry points.
     */
    private function dispatchToLinks($links, array $channels, ?string $customMessage = null)
    {
        $details = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($links as $link) {
            $results = $this->sendKycLink($link, $channels, true, $customMessage);
            $anySuccess = collect($results)->contains(fn ($r) => !empty($r['success']));
            $anySuccess ? $successCount++ : $failureCount++;
            $details[] = [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'success' => $anySuccess,
                'channels' => $results,
            ];
        }

        return [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'total' => count($details),
            'details' => $details,
        ];
    }

    /**
     * Send via specific channel with instant delivery
     */
    private function sendViaChannel(AdGroupKycLink $link, $channel, $immediate = true, ?string $customMessage = null)
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmail($link, $immediate, $customMessage);
            case 'whatsapp':
                return $this->sendWhatsApp($link, $immediate, $customMessage);
            case 'sms':
                return $this->sendSMS($link, $immediate, $customMessage);
            case 'all':
                return $this->sendAllChannels($link, $immediate, $customMessage);
            default:
                throw new Exception("Unsupported notification channel: {$channel}");
        }
    }

    /**
     * Send email notification with instant delivery
     */
    private function sendEmail(AdGroupKycLink $link, $immediate = true, ?string $customMessage = null)
    {
        try {
            // Check mail configuration
            $mailConfig = config('mail');
            Log::info('AD Group KYC - Email configuration check', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                // 'mail_driver' => $mailConfig['default'],
                // 'from_address' => config('mail.from.address'),
                // 'from_name' => config('mail.from.name'),
                'immediate' => $immediate
            ]);

            // Prepare email data using the same structure as EmployerGroupController
            $emailData = new \stdClass();
            // $emailData->user_id = $link->customer_id;
            $emailData->customer_id = $link->customer_id;
            $emailData->hook = 'ad_group_kyc_link';
            $emailData->email = $link->customer->email;
            $emailData->attachment = null;

            // Add AD Group KYC specific data to match template placeholders
            // Template placeholders: [[Customers Firstname_5]], [[AD Group Kyc Url_73]], etc.
            // $emailData->customer_firstname = $link->customer->firstName;
            $emailData->ad_group_kyc_url = $link->kyc_url;
            $emailData->ad_group_kyc_otp_code = $link->otp_code;
            $emailData->ad_group_kyc_otp_expiry = $link->otp_expires_at ? $link->otp_expires_at->format('Y-m-d H:i:s') : now()->addMinutes(15)->format('Y-m-d H:i:s');
            $emailData->ad_group_kyc_link_expiry_days = $link->expires_at ? $link->expires_at->diffInDays(now()) . ' days' : '7 days';
            $emailData->ad_group_kyc_custom_message = $customMessage
                ?: 'Please complete your KYC verification to maintain your policy coverage.';

            // Additional data for reference
            // $emailData->customer_name = $link->customer->firstName . ' ' . $link->customer->lastName;
            // $emailData->policy_number = $link->policy->policyNumber;
            // $emailData->employer_group_name = $link->campaign->employerGroup->name ?? 'AD Group';

            // Get email template
            $emailTemplate = EmailBroadcasting::where('hook_slug', $emailData->hook)->first();
            if (!$emailTemplate) {
                Log::error('AD Group KYC Email Template Not Found', [
                    'hook' => $emailData->hook,
                    'available_templates' => EmailBroadcasting::pluck('hook_slug')->toArray()
                ]);

                // Fallback to notification system if template not found
                return $this->sendEmailViaNotification($link, $immediate);
            }

            // Render email template
            $markdown = new MailTemplate($emailData);
            $html = $markdown->render('Mail.mailTemplate', ['data' => $emailData]);

            // Send email using the same event system
            event(new \AlphaDirect\Events\SendMail(
                $emailData->email,
                $emailTemplate->subject,
                "",
                $html,
                null,
                ['hook' => $emailData->hook]
            ));

            // Mark as sent
            $link->markAsSent('email', 'email_sent_' . time());

            // Log activity
            AdGroupKycActivity::logActivity(
                $link->id,
                $link->customer_id,
                $link->policy_id,
                AdGroupKycActivity::TYPE_EMAIL_SENT,
                'AD Group KYC email sent using template system',
                [
                    'delivery_method' => 'email',
                    'immediate' => $immediate,
                    'template_hook' => $emailData->hook,
                    'sent_at' => now()->toISOString()
                ]
            );

            Log::info('AD Group KYC - Email sent successfully using template system', [
                'link_id' => $link->id,
                'customer_email' => $link->customer->email,
                'template_hook' => $emailData->hook
            ]);

            return [
                'success' => true,
                'delivery_method' => 'email',
                'delivery_reference' => 'email_sent_' . time(),
                'template_used' => true,
                'sent_at' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('AD Group KYC - Email sending failed', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fallback method to send email via notification system
     */
    private function sendEmailViaNotification(AdGroupKycLink $link, $immediate = true)
    {
        try {
            // Create notification instance
            $notification = new AdGroupKycLinkNotification($link, 'email');

            // For immediate delivery, disable queuing
            if ($immediate) {
                $notification->disableQueuing();
            }

            // Send notification
            $link->customer->notify($notification);

            // Mark as sent
            $link->markAsSent('email', 'email_sent_' . time());

            // Log activity
            AdGroupKycActivity::logActivity(
                $link->id,
                $link->customer_id,
                $link->policy_id,
                AdGroupKycActivity::TYPE_EMAIL_SENT,
                'AD Group KYC email sent using notification fallback',
                [
                    'delivery_method' => 'email',
                    'immediate' => $immediate,
                    'fallback_method' => true,
                    'sent_at' => now()->toISOString()
                ]
            );

            Log::info('AD Group KYC - Email sent successfully using notification fallback', [
                'link_id' => $link->id,
                'customer_email' => $link->customer->email
            ]);

            return [
                'success' => true,
                'delivery_method' => 'email',
                'delivery_reference' => 'email_sent_' . time(),
                'template_used' => false,
                'fallback_method' => true,
                'sent_at' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('AD Group KYC - Fallback email sending failed', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWhatsApp(AdGroupKycLink $link, $immediate = true, ?string $customMessage = null)
    {
        try {
            $customer = $link->customer;
            $policy = $link->policy;

            if (!$customer || !$customer->cellphone) {
                return [
                    'success' => false,
                    'error' => 'Customer phone number not available'
                ];
            }

            // Prepare WhatsApp message
            $message = $this->prepareWhatsAppMessage($link, $customMessage);

            // Send WhatsApp message (implement your WhatsApp API here)
            // This is a placeholder - you'll need to implement actual WhatsApp sending
            $whatsappResult = $this->sendWhatsAppMessage($customer->cellphone, $message);

            if ($whatsappResult['success']) {
                // Mark link as sent
                $link->markAsSent('whatsapp', $whatsappResult['message_id']);

                // Log activity
                AdGroupKycActivity::logActivity(
                    $link->id,
                    $link->customer_id,
                    $link->policy_id,
                    AdGroupKycActivity::TYPE_WHATSAPP_SENT,
                    'AD Group KYC WhatsApp sent successfully',
                    [
                        'delivery_method' => 'whatsapp',
                        'phone_number' => $customer->cellphone,
                        'message_id' => $whatsappResult['message_id']
                    ]
                );

                return [
                    'success' => true,
                    'delivery_method' => 'whatsapp',
                    'delivery_reference' => $whatsappResult['message_id'],
                    'sent_at' => now()->toISOString()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $whatsappResult['error']
                ];
            }

        } catch (Exception $e) {
            Log::error('AD Group KYC - WhatsApp sending failed', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSMS(AdGroupKycLink $link, $immediate = true, ?string $customMessage = null)
    {
        try {
            $customer = $link->customer;

            if (!$customer || !$customer->cellphone) {
                return [
                    'success' => false,
                    'error' => 'Customer phone number not available'
                ];
            }

            // Prepare SMS message
            $message = $this->prepareSmsMessage($link, $customMessage);

            // Send SMS using your SMS service
            event(new SendSms($customer->cellphone, $message));

            // Mark link as sent
            $link->markAsSent('sms', 'sms_sent_' . time());

            // Log activity
            AdGroupKycActivity::logActivity(
                $link->id,
                $link->customer_id,
                $link->policy_id,
                AdGroupKycActivity::TYPE_SMS_SENT,
                'AD Group KYC SMS sent successfully',
                [
                    'delivery_method' => 'sms',
                    'phone_number' => $customer->cellphone
                ]
            );

            return [
                'success' => true,
                'delivery_method' => 'sms',
                'delivery_reference' => 'sms_sent_' . time(),
                'sent_at' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('AD Group KYC - SMS sending failed', [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'policy_id' => $link->policy_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send via all channels
     */
    private function sendAllChannels(AdGroupKycLink $link, $immediate = true, ?string $customMessage = null)
    {
        $results = [];

        $channels = ['email', 'sms', 'whatsapp'];

        foreach ($channels as $channel) {
            $results[$channel] = $this->sendViaChannel($link, $channel, $immediate, $customMessage);
        }

        return $results;
    }

    /**
     * Prepare WhatsApp message
     */
    private function prepareWhatsAppMessage(AdGroupKycLink $link, ?string $customMessage = null)
    {
        $customer = $link->customer;
        $policy = $link->policy;

        $message = "Hello {$customer->firstName},\n\n";
        $message .= "Your individual AD Group insurance policy requires KYC verification.\n\n";
        if ($customMessage) {
            $message .= trim($customMessage) . "\n\n";
        }
        $message .= "Policy Number: {$policy->policyNumber}\n";
        $message .= "Policyholder: {$customer->firstName} {$customer->lastName}\n";
        $message .= "OTP Code: {$link->otp_code}\n\n";
        $message .= "Please complete your KYC verification by clicking the link below:\n";
        $message .= $link->kyc_url . "\n\n";
        $message .= "This verification is required for your individual policy and must be completed by you as the policyholder.\n";
        $message .= "This link will expire in 7 days.\n\n";
        $message .= "Thank you,\nAlphaDirect Insurance";

        return $message;
    }

    /**
     * Prepare SMS message
     */
    private function prepareSmsMessage(AdGroupKycLink $link, ?string $customMessage = null)
    {
        $customer = $link->customer;
        $policy = $link->policy;

        $message = "AlphaDirect: Hello {$customer->firstName}, ";
        $message .= "Your individual AD Group policy {$policy->policyNumber} requires KYC verification. ";
        if ($customMessage) {
            $message .= trim($customMessage) . ' ';
        }
        $message .= "OTP: {$link->otp_code}. ";
        $message .= "Complete your verification at: " . $link->kyc_url;

        return $message;
    }

    /**
     * Send WhatsApp message (placeholder - implement your WhatsApp API)
     */
    private function sendWhatsAppMessage($phoneNumber, $message)
    {
        // This is a placeholder implementation
        // You'll need to implement your actual WhatsApp API integration here

        try {
            // Example implementation - replace with your actual WhatsApp API
            $apiUrl = config('whatsapp.api_url');
            $apiToken = config('whatsapp.api_token');

            if (!$apiUrl || !$apiToken) {
                return [
                    'success' => false,
                    'error' => 'WhatsApp API not configured'
                ];
            }

            // Make API call to send WhatsApp message
            // This is just a placeholder - implement your actual API call

            return [
                'success' => true,
                'message_id' => 'whatsapp_' . time() . '_' . rand(1000, 9999)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
