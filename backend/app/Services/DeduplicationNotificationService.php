<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\DeduplicationChecks;
use AlphaDirect\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class DeduplicationNotificationService
{
    /**
     * Send bank statement upload notification via multiple channels
     */
    public function sendBankStatementNotification(DeduplicationChecks $check, array $channels = ['email'], $immediate = true)
    {
        $results = [];
        
        foreach ($channels as $channel) {
            try {
                $result = $this->sendViaChannel($check, $channel, $immediate);
                $results[$channel] = $result;
                
                // Log successful sending
                Log::info("Bank statement notification sent via {$channel}", [
                    'check_id' => $check->id,
                    'customer_id' => $check->customer_id,
                    'channel' => $channel,
                    'success' => $result['success'],
                    'immediate' => $immediate
                ]);
                
            } catch (Exception $e) {
                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                // Log error
                Log::error("Failed to send bank statement notification via {$channel}", [
                    'check_id' => $check->id,
                    'customer_id' => $check->customer_id,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                    'immediate' => $immediate
                ]);
            }
        }
        
        return $results;
    }

    /**
     * Send via specific channel
     */
    private function sendViaChannel(DeduplicationChecks $check, $channel, $immediate = true)
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmail($check, $immediate);
            case 'whatsapp':
                return $this->sendWhatsApp($check, $immediate);
            case 'sms':
                return $this->sendSMS($check, $immediate);
            case 'all':
                return $this->sendAllChannels($check, $immediate);
            default:
                throw new Exception("Unsupported notification channel: {$channel}");
        }
    }

    /**
     * Send email notification
     */
    private function sendEmail(DeduplicationChecks $check, $immediate = true)
    {
        try {
            $customer = $check->customer;
            if (!$customer) {
                throw new Exception("Customer not found for check ID: {$check->id}");
            }

            // Generate the bank statement upload link
            $uploadUrl = $this->generateUploadUrl($check);
            
            // Use generic notification service
            $notificationService = app(\AlphaDirect\Services\GenericNotificationService::class);
            
            $additionalData = [
                'customer_id' => $check->customer_id,
                'upload_url' => $uploadUrl,
                'expiry_days' => $check->link_expires_at ? $check->link_expires_at->format('M d, Y H:i') : '7 days',
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'bank_name' => $check->bank_name ?? 'N/A',
                'account_number' => $check->bank_account_number ?? 'N/A'
            ];
            
            $result = $notificationService->emailSend(
                'bank_statement_upload',
                $customer->email,
                '', // custom message will be handled by the template
                $additionalData
            );

            return [
                'success' => $result,
                'message' => $result ? 'Email sent successfully' : 'Failed to send email',
                'channel' => 'email'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'email'
            ];
        }
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWhatsApp(DeduplicationChecks $check, $immediate = true)
    {
        try {
            $customer = $check->customer;
            if (!$customer || !$customer->cellphone) {
                throw new Exception("Customer phone number not found for check ID: {$check->id}");
            }

            $uploadUrl = $this->generateUploadUrl($check);
            
            // Use generic notification service
            $notificationService = app(\AlphaDirect\Services\GenericNotificationService::class);
            
            $additionalData = [
                'customer_id' => $check->customer_id,
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'accessUrl' => $uploadUrl,
                'expiryDays' => $check->link_expires_at ? $check->link_expires_at->format('M d, Y H:i') : '7 days',
                'bank_name' => $check->bank_name ?? 'N/A',
                'account_number' => $check->bank_account_number ?? 'N/A',
                'delivery_method' => 'instant'
            ];
            
            $result = $notificationService->whatsappSend(
                'bank_statement_upload',
                $customer->cellphone,
                '', // custom message will be handled by the template
                $additionalData
            );

            return [
                'success' => $result,
                'message' => $result ? 'WhatsApp sent successfully' : 'Failed to send WhatsApp',
                'channel' => 'whatsapp'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'whatsapp'
            ];
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSMS(DeduplicationChecks $check, $immediate = true)
    {
        try {
            $customer = $check->customer;
            if (!$customer || !$customer->cellphone) {
                throw new Exception("Customer phone number not found for check ID: {$check->id}");
            }

            $uploadUrl = $this->generateUploadUrl($check);
            
            // Use generic notification service
            $notificationService = app(\AlphaDirect\Services\GenericNotificationService::class);
            
            $additionalData = [
                'customer_id' => $check->customer_id,
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'upload_url' => $uploadUrl,
                'expiry_days' => $check->link_expires_at ? $check->link_expires_at->format('M d, Y H:i') : '7 days',
                'bank_name' => $check->bank_name ?? 'N/A',
                'account_number' => $check->bank_account_number ?? 'N/A',
                'template_id' => 48 // Bank statement upload template ID
            ];
            
            $result = $notificationService->smsSend(
                'bank_statement_upload',
                $customer->cellphone,
                '', // custom message will be handled by the template
                $additionalData
            );

            return [
                'success' => $result,
                'message' => $result ? 'SMS sent successfully' : 'Failed to send SMS',
                'channel' => 'sms'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'sms'
            ];
        }
    }

    /**
     * Send via all channels
     */
    private function sendAllChannels(DeduplicationChecks $check, $immediate = true)
    {
        $results = [];
        $channels = ['email', 'whatsapp', 'sms'];
        
        foreach ($channels as $channel) {
            $results[$channel] = $this->sendViaChannel($check, $channel, $immediate);
        }
        
        return $results;
    }

    /**
     * Generate bank statement upload URL
     */
    private function generateUploadUrl(DeduplicationChecks $check)
    {
        if ($check->unique_access_token) {
            return url("/bank-statement/upload/{$check->unique_access_token}");
        }
        
        // Generate new token if not exists
        $token = \Str::random(60);
        $expiresAt = \Carbon\Carbon::now()->addDays(7);
        
        $check->update([
            'unique_access_token' => $token,
            'link_expires_at' => $expiresAt,
            'status' => 'link_generated'
        ]);
        
        return url("/bank-statement/upload/{$token}");
    }

    /**
     * Get WhatsApp message template
     */
    private function getWhatsAppMessage($customer, $check, $uploadUrl)
    {
        return "Hello {$customer->firstName},\n\n" .
               "You are required to upload your bank statement for verification purposes.\n\n" .
               "Please click the link below to upload your bank statement:\n" .
               "{$uploadUrl}\n\n" .
               "This link will expire on " . ($check->link_expires_at ? $check->link_expires_at->format('M d, Y H:i') : '7 days') . ".\n\n" .
               "If you have any questions, please contact our support team.\n\n" .
               "Best regards,\nAlphaDirect Insurance Team";
    }

    /**
     * Get SMS message template
     */
    private function getSMSMessage($customer, $check, $uploadUrl)
    {
        return "Hi {$customer->firstName}, please upload your bank statement at: {$uploadUrl} (Expires: " . 
               ($check->link_expires_at ? $check->link_expires_at->format('M d') : '7 days') . ") - AlphaDirect";
    }
}
