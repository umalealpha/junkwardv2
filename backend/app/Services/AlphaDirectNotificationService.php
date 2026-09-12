<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\Log;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\whatsAppModel;

/**
 * AlphaDirectNotificationService
 *
 * Single entry point for all customer-facing notifications.
 * Priority order: WhatsApp → Email → SMS
 *
 * WhatsApp is attempted first. If it fails or the number has no WhatsApp,
 * we fall back to SMS. Email always fires in parallel when an address exists.
 *
 * Usage:
 *   $notif = app(AlphaDirectNotificationService::class);
 *   $notif->paymentFailed($firstName, $policyNumber, $amount, $cellphone, $email);
 */
class AlphaDirectNotificationService
{
    private WhatsAppController $wa;
    private SmsMessaging $sms;

    public function __construct(WhatsAppController $wa, SmsMessaging $sms)
    {
        $this->wa  = $wa;
        $this->sms = $sms;
    }

    // ──────────────────────────────────────────────────────────────
    // Public notification methods
    // ──────────────────────────────────────────────────────────────

    /**
     * Payment failed notification.
     * Template: payment_failed  |  vars: {{1}} firstName, {{2}} amount, {{3}} policyNumber
     */
    public function paymentFailed(
        string $firstName,
        string $policyNumber,
        $amount,
        string $cellphone,
        ?string $email = null,
        ?int $customerId = null
    ): void {
        $waData = [
            'hook_slug'    => 'payment_failed',
            'mobileNumber' => $cellphone,
            'firstName'    => $firstName,
            'policyNumber' => $policyNumber,
            'amount'       => number_format((float) $amount, 2),
            'customer_id'  => $customerId,
        ];

        $waSent = $this->sendWhatsApp($waData);

        // SMS fallback if WhatsApp failed
        if (!$waSent) {
            $this->sms->sendPaymentFailedSMS(21, $firstName, $policyNumber, $cellphone, $amount);
        }

        // Email always (if address provided)
        if ($email) {
            $this->sendEmail('payment_failed', $email, $firstName, $policyNumber, [
                'amount' => $amount,
            ]);
        }
    }

    /**
     * Payment success notification.
     * Template: payment_success  |  vars: {{1}} firstName, {{2}} premium, {{3}} policyNumber
     */
    public function paymentSuccess(
        string $firstName,
        string $policyNumber,
        $premium,
        string $cellphone,
        ?string $email = null,
        ?int $customerId = null
    ): void {
        $waData = [
            'hook_slug'    => 'payment_success',
            'mobileNumber' => $cellphone,
            'firstName'    => $firstName,
            'policyNumber' => $policyNumber,
            'premium'      => number_format((float) $premium, 2),
            'customer_id'  => $customerId,
        ];

        $waSent = $this->sendWhatsApp($waData);

        if (!$waSent) {
            $this->sms->sendPaySuccessSMS(4, $cellphone, $premium);
        }

        if ($email) {
            $this->sendEmail('payment_success', $email, $firstName, $policyNumber, [
                'premium' => $premium,
            ]);
        }
    }

    /**
     * Policy cancelled notification.
     * Template: policy_cancelled  |  vars: {{1}} firstName, {{2}} policyNumber
     */
    public function policyCancelled(
        string $firstName,
        string $policyNumber,
        string $cellphone,
        ?string $email = null,
        ?int $customerId = null
    ): void {
        $waData = [
            'hook_slug'    => 'policy_cancelled',
            'mobileNumber' => $cellphone,
            'firstName'    => $firstName,
            'policyNumber' => $policyNumber,
            'customer_id'  => $customerId,
        ];

        $waSent = $this->sendWhatsApp($waData);

        if (!$waSent) {
            $this->sms->SendSMSForCancellation(39, $policyNumber, $firstName, $cellphone);
        }

        if ($email) {
            $this->sendEmail('policy_cancelled', $email, $firstName, $policyNumber);
        }
    }

    /**
     * Policy pending activation notification.
     * Template: policy_pending_activation  |  vars: {{1}} firstName, {{2}} policyNumber, {{3}} premium
     */
    public function policyPendingActivation(
        string $firstName,
        string $policyNumber,
        $premium,
        string $cellphone,
        ?string $email = null,
        ?int $customerId = null
    ): void {
        $waData = [
            'hook_slug'    => 'policy_pending_activation',
            'mobileNumber' => $cellphone,
            'firstName'    => $firstName,
            'policyNumber' => $policyNumber,
            'premium'      => number_format((float) $premium, 2),
            'customer_id'  => $customerId,
        ];

        $waSent = $this->sendWhatsApp($waData);

        if (!$waSent) {
            $this->sms->sendPolicyPendingSMS(22, $firstName, $policyNumber, $premium, $cellphone);
        }

        if ($email) {
            $this->sendEmail('policy_pending_activation', $email, $firstName, $policyNumber, [
                'premium' => $premium,
            ]);
        }
    }

    /**
     * Payment reminder notification.
     * Template: payment_reminder  |  vars: {{1}} firstName, {{2}} premium, {{3}} policyNumber, {{4}} dueDate
     */
    public function paymentReminder(
        string $firstName,
        string $policyNumber,
        $premium,
        string $dueDate,
        string $cellphone,
        ?string $email = null,
        ?int $customerId = null
    ): void {
        $waData = [
            'hook_slug'    => 'payment_reminder',
            'mobileNumber' => $cellphone,
            'firstName'    => $firstName,
            'policyNumber' => $policyNumber,
            'premium'      => number_format((float) $premium, 2),
            'dueDate'      => $dueDate,
            'customer_id'  => $customerId,
        ];

        $waSent = $this->sendWhatsApp($waData);

        if (!$waSent) {
            // Fall back to existing SMS reminder
            $this->sms->sendPolicyPendingPaymentSMS(33, $firstName, $policyNumber, $premium, $cellphone);
        }

        if ($email) {
            $this->sendEmail('payment_reminder', $email, $firstName, $policyNumber, [
                'premium' => $premium,
                'dueDate' => $dueDate,
            ]);
        }
    }

    /**
     * Policy created / welcome notification.
     * Template: policy_created  |  vars: {{1}} firstName, {{2}} policyNumber, {{3}} premium
     */
    public function policyCreated(
        string $firstName,
        string $policyNumber,
        $premium,
        string $cellphone,
        ?string $email = null,
        ?int $customerId = null
    ): void {
        $waData = [
            'hook_slug'    => 'policy_created',
            'mobileNumber' => $cellphone,
            'firstName'    => $firstName,
            'policyNumber' => $policyNumber,
            'premium'      => number_format((float) $premium, 2),
            'customer_id'  => $customerId,
        ];

        $waSent = $this->sendWhatsApp($waData);

        if (!$waSent) {
            $this->sms->sendPolicyPendingSMS(33, $firstName, $policyNumber, $premium, $cellphone);
        }

        if ($email) {
            $this->sendEmail('policy_created', $email, $firstName, $policyNumber, [
                'premium' => $premium,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Attempt WhatsApp send. Returns true if accepted by Meta, false on any error.
     */
    private function sendWhatsApp(array $data): bool
    {
        // Don't attempt if URL not configured
        if (empty(env('WHATSAPP_URL')) || empty(env('WHATSAPP_TOKEN'))) {
            return false;
        }

        // Skip blank/invalid numbers
        $number = preg_replace('/\D+/', '', $data['mobileNumber'] ?? '');
        if (strlen($number) < 7) {
            return false;
        }

        try {
            $this->wa->sendMetaData($data);

            // Check the last logged model to see if Meta accepted it
            $lastId = $this->wa->lastId;
            if ($lastId) {
                $log = whatsAppModel::find($lastId);
                if ($log) {
                    $output = json_decode($log->output ?? '{}', true);
                    // Meta returns 'messages' array on success
                    if (!empty($output['messages'])) {
                        return true;
                    }
                    // Log the error for debugging
                    Log::warning('WhatsApp send not accepted', [
                        'hook'   => $data['hook_slug'],
                        'number' => $number,
                        'output' => $output,
                    ]);
                }
            }
            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp send exception', [
                'hook'    => $data['hook_slug'],
                'number'  => $number,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Fire email via existing event system.
     */
    private function sendEmail(
        string $hookSlug,
        string $email,
        string $firstName,
        string $policyNumber,
        array $extra = []
    ): void {
        try {
            $emailObj              = new \stdClass();
            $emailObj->hook        = $hookSlug;
            $emailObj->email       = $email;
            $emailObj->firstName   = $firstName;
            $emailObj->policyNumber = $policyNumber;

            foreach ($extra as $k => $v) {
                $emailObj->$k = $v;
            }

            $template = \AlphaDirect\EmailBroadcasting::where('hook_slug', $hookSlug)->first(['subject']);
            if (!$template) {
                Log::warning("No email template for hook: {$hookSlug}");
                return;
            }

            $markdown = new \AlphaDirect\Mail\MailTemplate($emailObj);
            $html     = $markdown->render('Mail.mailTemplate', ['data' => $emailObj]);

            event(new \AlphaDirect\Events\SendMail(
                $email,
                $template->subject,
                '',
                $html,
                null,
                ['policyNumber' => $policyNumber, 'hook' => $hookSlug]
            ));
        } catch (\Exception $e) {
            Log::error("Email send failed for hook {$hookSlug}: " . $e->getMessage());
        }
    }
}
