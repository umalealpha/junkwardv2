<?php

namespace AlphaDirect\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Events\SendSms;
use Illuminate\Support\Facades\Log;

class AdGroupKycLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $kycLink;
    public $via;
    public $tries = 3;
    public $timeout = 60;
    public $shouldQueue = false; // Changed to false for instant delivery by default

    public function __construct(AdGroupKycLink $kycLink, $via = 'email')
    {
        $this->kycLink = $kycLink;
        $this->via = $via;
     
        // Set queue name for better organization
        $this->onQueue('notifications');
        
        // For instant delivery, disable queuing
        $this->shouldQueue = false;
    }
    
    /**
     * Disable queuing for immediate sending
     */
    public function disableQueuing()
    {
        $this->shouldQueue = false;
        return $this;
    }
    
    /**
     * Enable queuing for delayed sending
     */
    public function enableQueuing()
    {
        $this->shouldQueue = true;
        return $this;
    }

    public function via($notifiable)
    {
        // For instant delivery, we need to handle all channels immediately
        switch ($this->via) {
            case 'email':
                return ['mail'];
            case 'sms':
                return ['database']; // We'll handle SMS via event in toDatabase()
            case 'whatsapp':
                return ['database']; // We'll handle WhatsApp via event in toDatabase()
            case 'all':
                // Send via all channels for comprehensive delivery
                return ['mail', 'database'];
            default:
                return ['mail'];
        }
    }

    public function toMail($notifiable)
    {
        $customer = $this->kycLink->customer;
        $policy = $this->kycLink->policy;
        $linkExpiryDays = 7; // AD Group KYC links expire in 7 days
     
        return (new MailMessage)
            ->subject('AD Group Insurance - KYC Verification Required for Your Policy')
            ->greeting('Dear ' . $customer->firstName . ',')
            ->line('As the policyholder for your AD Group insurance policy, you are required to complete KYC verification to ensure compliance and security.')
            ->line('**Your Policy Details:**')
            ->line('Policy Number: ' . $policy->policyNumber)
            ->line('Policyholder: ' . $customer->firstName . ' ' . $customer->lastName)
            ->line('OTP Code: **' . $this->kycLink->otp_code . '**')
            ->line('**Important:** Please use the OTP code above when completing your KYC verification.')
            ->action('Complete Your KYC Verification', $this->kycLink->kyc_url)
            ->line('**Important Security Information:**')
            ->line('• This link is secure and encrypted')
            ->line('• You will need to enter the OTP code above to access your information')
            ->line('• The OTP will be sent to your registered mobile number: ' . $this->maskPhoneNumber($customer->cellphone))
            ->line('• This link will expire in ' . $linkExpiryDays . ' days')
            ->line('• If you did not request this verification, please contact our support team immediately')
            ->line('This KYC verification is required for your individual policy and must be completed by you as the policyholder.')
            ->salutation('Best regards,')
            ->line('AlphaDirect Insurance Team');
    }

    public function toDatabase($notifiable)
    {
        $customer = $this->kycLink->customer;
        $policy = $this->kycLink->policy;
        $otpCode = $this->kycLink->otp_code;
        $otpExpiryMinutes = 10; // OTP expires in 10 minutes
        $linkExpiryDays = 7; // Link expires in 7 days
        
        // Handle SMS - Instant delivery
        if ($this->via === 'sms' || $this->via === 'all') {
            $this->sendSMSMessage($customer, $policy, $otpCode, $otpExpiryMinutes, $linkExpiryDays);
        }
        
        // Handle WhatsApp - Instant delivery
        if ($this->via === 'whatsapp' || $this->via === 'all') {
            $this->sendWhatsAppMessage($customer, $policy, $otpCode, $otpExpiryMinutes, $linkExpiryDays);
        }
        
        return [
            'kyc_link_id' => $this->kycLink->id,
            'policy_number' => $policy->policyNumber,
            'otp_code' => $otpCode,
            'kyc_url' => $this->kycLink->kyc_url,
            'expires_at' => $this->kycLink->expires_at,
            'channel' => $this->via,
            'sent_at' => now(),
            'delivery_method' => 'instant'
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'kyc_link_id' => $this->kycLink->id,
            'policy_number' => $this->kycLink->policy->policyNumber,
            'otp_code' => $this->kycLink->otp_code,
            'kyc_url' => $this->kycLink->kyc_url,
            'expires_at' => $this->kycLink->expires_at,
            'channel' => $this->via
        ];
    }

    /**
     * Send SMS message for instant delivery
     */
    private function sendSMSMessage($customer, $policy, $otpCode, $otpExpiryMinutes, $linkExpiryDays)
    {
        try {
            $message = "AD Group Insurance - KYC Verification Required\n\n";
            $message .= "Dear " . $customer->firstName . ",\n\n";
            $message .= "Your AD Group policy requires KYC verification for compliance.\n\n";
            $message .= "Policy: " . $policy->policyNumber . "\n";
            $message .= "Access your secure portal: " . $this->kycLink->kyc_url . "\n\n";
            $message .= "OTP Code: " . $otpCode . "\n";
            $message .= "Valid for " . $otpExpiryMinutes . " minutes\n\n";
            $message .= "This link expires in " . $linkExpiryDays . " days.\n\n";
            $message .= "If you didn't request this, contact support immediately.\n\n";
            $message .= "Best regards,\nAlphaDirect Insurance Team";

            // Fire SMS event for instant delivery
            event(new SendSms('+267' . $customer->cellphone, $message, [
                'policyNumber' => $policy->policyNumber,
                'kyc_link_id' => $this->kycLink->id,
                'delivery_method' => 'instant'
            ]));
            
            Log::info('AD Group KYC - SMS sent instantly', [
                'customer_id' => $customer->id,
                'link_id' => $this->kycLink->id,
                'phone' => $customer->cellphone
            ]);
            
        } catch (\Exception $e) {
            Log::error('AD Group KYC - SMS sending failed', [
                'customer_id' => $customer->id,
                'link_id' => $this->kycLink->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send WhatsApp message using existing WhatsApp controller for instant delivery
     */
    private function sendWhatsAppMessage($customer, $policy, $otpCode, $otpExpiryMinutes, $linkExpiryDays)
    {
        try {
            // Create formatted message for WhatsApp
            $message = "🔐 *AD Group Insurance - KYC Verification Required*\n\n";
            $message .= "Dear " . $customer->firstName . ",\n\n";
            $message .= "Your AD Group policy requires KYC verification for compliance with regulatory requirements.\n\n";
            $message .= "📋 *Policy Details:*\n";
            $message .= "Policy Number: " . $policy->policyNumber . "\n";
            $message .= "Policyholder: " . $customer->firstName . " " . $customer->lastName . "\n\n";
            $message .= "📱 *Access your secure portal:*\n";
            $message .= $this->kycLink->kyc_url . "\n\n";
            $message .= "🔑 *OTP Code:* " . $otpCode . "\n";
            $message .= "⏰ *Valid for:* " . $otpExpiryMinutes . " minutes\n\n";
            $message .= "⏳ *This link expires in:* " . $linkExpiryDays . " days\n\n";
            $message .= "⚠️ *Important:* If you didn't request this verification, please contact our support team immediately.\n\n";
            $message .= "Best regards,\nAlphaDirect Insurance Team";

            // Use existing WhatsApp controller with simple text message for instant delivery
            $whatsappController = app(\AlphaDirect\Http\Controllers\WhatsAppController::class);
            $whatsappController->sendMessage([
                'mobileNumber' => $customer->cellphone,
                'type' => 'text',
                'subType' => 'ad_group_kyc_verification',
                'message' => $message,
                'customer_id' => $customer->id,
                'policyNumber' => $policy->policyNumber,
                'kyc_link_id' => $this->kycLink->id,
                'delivery_method' => 'instant'
            ]);
            
            Log::info('AD Group KYC - WhatsApp message sent instantly', [
                'customer_id' => $customer->id,
                'link_id' => $this->kycLink->id,
                'phone' => $customer->cellphone
            ]);
            
        } catch (\Exception $e) {
            Log::error('AD Group KYC - WhatsApp message sending failed', [
                'customer_id' => $customer->id,
                'link_id' => $this->kycLink->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function maskPhoneNumber($phoneNumber)
    {
        if (strlen($phoneNumber) < 4) {
            return $phoneNumber;
        }
        
        return substr($phoneNumber, 0, 3) . str_repeat('*', strlen($phoneNumber) - 6) . substr($phoneNumber, -3);
    }
}