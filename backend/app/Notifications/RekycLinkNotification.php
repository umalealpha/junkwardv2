<?php

namespace AlphaDirect\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Events\SendSms;
use Illuminate\Support\Facades\Log;

class RekycLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $rekycLink;
    public $via;
    public $tries = 3;
    public $timeout = 60;
    public $shouldQueue = false; // Changed to false for instant delivery by default

    public function __construct(RekycLink $rekycLink, $via = 'email')
    {
        $this->rekycLink = $rekycLink;
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
       
        $accessUrl = $this->rekycLink->getAccessUrl();
        $linkExpiryDays = config('rekyc.security.link_expiry_days', 30);
     
        return (new MailMessage)
            ->subject('Re-KYC Verification Required - ' . config('app.name'))
            ->greeting('Dear ' . $notifiable->firstName . ',')
            ->line('We need to verify your KYC (Know Your Customer) information to ensure compliance with regulatory requirements.')
            ->line('Please click the link below to access your secure Re-KYC portal:')
            ->action('Access Re-KYC Portal', $accessUrl)
            ->line('**Important Security Information:**')
            ->line('• This link is secure and encrypted')
            ->line('• You will need to enter a 6-digit OTP code to access your information')
            ->line('• The OTP will be sent to your registered mobile number: ' . $this->maskPhoneNumber($notifiable->cellphone))
            ->line('• This link will expire in ' . $linkExpiryDays . ' days')
            ->line('• If you did not request this verification, please contact our support team immediately')
            ->salutation('Best regards,')
            ->line(config('app.name') . ' Compliance Team');
    }

    public function toDatabase($notifiable)
    {
        $accessUrl = $this->rekycLink->getAccessUrl();
        $otpCode = $this->rekycLink->otp_code;
        $otpExpiryMinutes = config('rekyc.security.otp_expiry_minutes', 15);
        $linkExpiryDays = config('rekyc.security.link_expiry_days', 30);
        
        // Handle SMS - Instant delivery
        if ($this->via === 'sms' || $this->via === 'all') {
            $this->sendSMSMessage($notifiable, $accessUrl, $otpCode, $otpExpiryMinutes, $linkExpiryDays);
        }
        
        // Handle WhatsApp - Instant delivery
        if ($this->via === 'whatsapp' || $this->via === 'all') {
            $this->sendWhatsAppMessage($notifiable, $accessUrl, $otpCode, $otpExpiryMinutes, $linkExpiryDays);
        }
        
        return [
            'rekyc_link_id' => $this->rekycLink->id,
            'access_url' => $accessUrl,
            'otp_code' => $otpCode,
            'expires_at' => $this->rekycLink->expires_at,
            'channel' => $this->via,
            'sent_at' => now(),
            'delivery_method' => 'instant'
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'rekyc_link_id' => $this->rekycLink->id,
            'access_url' => $this->rekycLink->getAccessUrl(),
            'otp_code' => $this->rekycLink->otp_code,
            'expires_at' => $this->rekycLink->expires_at,
            'channel' => $this->via
        ];
    }

    /**
     * Send SMS message for instant delivery
     */
    private function sendSMSMessage($notifiable, $accessUrl, $otpCode, $otpExpiryMinutes, $linkExpiryDays)
    {
        try {
            $message = "Re-KYC Verification Required\n\n";
            $message .= "Dear " . $notifiable->firstName . ",\n\n";
            $message .= "We need to verify your KYC information for compliance.\n\n";
            $message .= "Access your secure portal: " . $accessUrl . "\n\n";
            $message .= "OTP Code: " . $otpCode . "\n";
            $message .= "Valid for " . $otpExpiryMinutes . " minutes\n\n";
            $message .= "This link expires in " . $linkExpiryDays . " days.\n\n";
            $message .= "If you didn't request this, contact support immediately.\n\n";
            $message .= "Best regards,\n" . config('app.name') . " Compliance Team";

            // Fire SMS event for instant delivery
            event(new SendSms('+267' . $notifiable->cellphone, $message, [
                'policyNumber' => null,
                'rekyc_link_id' => $this->rekycLink->id,
                'delivery_method' => 'instant'
            ]));
            
            Log::channel('rekyc_system')->info('SMS sent instantly for Re-KYC link', [
                'customer_id' => $notifiable->id,
                'link_id' => $this->rekycLink->id,
                'phone' => $notifiable->cellphone
            ]);
            
        } catch (\Exception $e) {
            Log::channel('rekyc_system')->error('SMS sending failed for Re-KYC link', [
                'customer_id' => $notifiable->id,
                'link_id' => $this->rekycLink->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send WhatsApp message using existing WhatsApp controller for instant delivery
     */
    private function sendWhatsAppMessage($notifiable, $accessUrl, $otpCode, $otpExpiryMinutes, $linkExpiryDays)
    {
        try {
            // Create formatted message for WhatsApp
            $message = "🔐 *Re-KYC Verification Required*\n\n";
            $message .= "Dear " . $notifiable->firstName . ",\n\n";
            $message .= "We need to verify your KYC information for compliance with regulatory requirements.\n\n";
            $message .= "📋 *Access your secure portal:*\n";
            $message .= $accessUrl . "\n\n";
            $message .= "🔑 *OTP Code:* " . $otpCode . "\n";
            $message .= "⏰ *Valid for:* " . $otpExpiryMinutes . " minutes\n\n";
            $message .= "⏳ *This link expires in:* " . $linkExpiryDays . " days\n\n";
            $message .= "⚠️ *Important:* If you didn't request this verification, please contact our support team immediately.\n\n";
            $message .= "Best regards,\n" . config('app.name') . " Compliance Team";

            // Use existing WhatsApp controller with simple text message for instant delivery
            $whatsappController = app(\AlphaDirect\Http\Controllers\WhatsAppController::class);
            $whatsappController->sendMessage([
                'mobileNumber' => $notifiable->cellphone,
                'type' => 'text',
                'subType' => 'rekyc_verification_text',
                'message' => $message,
                'customer_id' => $notifiable->id,
                'policyNumber' => null,
                'delivery_method' => 'instant'
            ]);
            
            Log::channel('rekyc_system')->info('WhatsApp message sent instantly for Re-KYC link', [
                'customer_id' => $notifiable->id,
                'link_id' => $this->rekycLink->id,
                'phone' => $notifiable->cellphone
            ]);
            
        } catch (\Exception $e) {
            Log::channel('rekyc_system')->error('WhatsApp message sending failed for Re-KYC link', [
                'customer_id' => $notifiable->id,
                'link_id' => $this->rekycLink->id,
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
