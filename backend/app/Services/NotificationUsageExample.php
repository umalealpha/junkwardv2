<?php

namespace AlphaDirect\Services;

/**
 * Example usage of GenericNotificationService
 * 
 * This class demonstrates how to use the GenericNotificationService
 * for different types of notifications across the application.
 */
class NotificationUsageExample
{
    protected $notificationService;

    public function __construct()
    {
        $this->notificationService = app(GenericNotificationService::class);
    }

    /**
     * Example: Send Re-KYC notification
     */
    public function sendRekycNotification($customerEmail, $customerPhone, $customMessage = '')
    {
        $additionalData = [
            'customer_id' => 123,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'rekyc_url' => 'https://example.com/rekyc/abc123',
            'rekyc_link_expiry_days' => '30 days',
            'policyNumber' => 'POL123456'
        ];

        // Send via multiple channels
        $results = $this->notificationService->sendMultiChannel(
            'rekyc_verification',
            $customerEmail,
            $customerPhone,
            ['email', 'sms', 'whatsapp'],
            $customMessage,
            $additionalData
        );

        return $results;
    }

    /**
     * Example: Send bank statement upload notification
     */
    public function sendBankStatementNotification($customerEmail, $customerPhone, $customMessage = '')
    {
        $additionalData = [
            'customer_id' => 456,
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'upload_url' => 'https://example.com/bank-statement/xyz789',
            'expiry_days' => '7 days',
            'bank_name' => 'First National Bank',
            'account_number' => '****1234',
            'template_id' => 48
        ];

        // Send via email only
        $emailResult = $this->notificationService->emailSend(
            'bank_statement_upload',
            $customerEmail,
            $customMessage,
            $additionalData
        );

        // Send via SMS only
        $smsResult = $this->notificationService->smsSend(
            'bank_statement_upload',
            $customerPhone,
            $customMessage,
            $additionalData
        );

        return [
            'email' => $emailResult,
            'sms' => $smsResult
        ];
    }

    /**
     * Example: Send policy renewal notification
     */
    public function sendPolicyRenewalNotification($customerEmail, $customerPhone, $customMessage = '')
    {
        $additionalData = [
            'customer_id' => 789,
            'firstName' => 'Bob',
            'lastName' => 'Johnson',
            'policyNumber' => 'POL789012',
            'renewal_date' => '2024-12-31',
            'premium_amount' => '500.00',
            'template_id' => 49
        ];

        // Send via all channels
        $results = $this->notificationService->sendMultiChannel(
            'policy_renewal',
            $customerEmail,
            $customerPhone,
            ['email', 'sms', 'whatsapp'],
            $customMessage,
            $additionalData
        );

        return $results;
    }

    /**
     * Example: Send payment reminder notification
     */
    public function sendPaymentReminderNotification($customerEmail, $customerPhone, $customMessage = '')
    {
        $additionalData = [
            'customer_id' => 101,
            'firstName' => 'Alice',
            'lastName' => 'Brown',
            'policyNumber' => 'POL101112',
            'due_date' => '2024-01-15',
            'amount_due' => '250.00',
            'payment_url' => 'https://example.com/payment/abc123',
            'template_id' => 50
        ];

        // Send via email and WhatsApp only
        $results = $this->notificationService->sendMultiChannel(
            'payment_reminder',
            $customerEmail,
            $customerPhone,
            ['email', 'whatsapp'],
            $customMessage,
            $additionalData
        );

        return $results;
    }

    /**
     * Example: Send custom notification with minimal data
     */
    public function sendCustomNotification($hook_slug, $email, $phone, $customMessage = '')
    {
        $additionalData = [
            'customer_id' => 999,
            'firstName' => 'Custom',
            'lastName' => 'User'
        ];

        // Send via all channels
        $results = $this->notificationService->sendMultiChannel(
            $hook_slug,
            $email,
            $phone,
            ['email', 'sms', 'whatsapp'],
            $customMessage,
            $additionalData
        );

        return $results;
    }
}
