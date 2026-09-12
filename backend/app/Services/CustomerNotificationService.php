<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Events\SendSms;
use AlphaDirect\Events\SendMail;

class CustomerNotificationService
{
    /**
     * Payment failed — SMS + WhatsApp + Email
     */
    public function notifyPaymentFailed(int $policyId, string $policyNumber, float $amount, string $paymentMethod, ?string $failureReason = null): void
    {
        $customer = $this->getCustomerForPolicy($policyId);
        if (!$customer) return;

        $vars = [
            'firstName'     => $customer->firstName,
            'lastName'      => $customer->lastName ?? '',
            'amount'        => number_format($amount, 2),
            'policyNumber'  => $policyNumber,
            'date'          => now()->format('d M Y'),
            'paymentMethod' => $paymentMethod,
            'reason'        => $failureReason ?? '',
        ];

        // SMS (critical — always send for failed payments)
        $smsText = $this->getTemplate('sms', 'payment_failed', $vars,
            "Dear {firstName}, your payment of P{amount} for policy {policyNumber} failed. Please ensure sufficient funds. Call +267 390 1081. Alpha Direct.");
        $this->sendSms($customer, $smsText, 'payment_failed', $policyId);

        // WhatsApp
        $waText = $this->getTemplate('whatsapp', 'payment_failed', $vars);
        $this->sendWhatsApp($customer, $waText, 'payment_failed', $policyId);

        // Email
        $emailText = $this->getTemplate('email', 'payment_failed', $vars);
        $subject = $this->getEmailSubject('payment_failed', $vars, "Payment Failed — Policy {$policyNumber}");
        $this->sendEmail($customer, $subject, $emailText, 'payment_failed', $policyId);
    }

    /**
     * Payment success — WhatsApp + Email only (SMS too costly)
     */
    public function notifyPaymentSuccess(int $policyId, string $policyNumber, float $amount, string $paymentMethod): void
    {
        $customer = $this->getCustomerForPolicy($policyId);
        if (!$customer) return;

        $vars = [
            'firstName'     => $customer->firstName,
            'lastName'      => $customer->lastName ?? '',
            'amount'        => number_format($amount, 2),
            'policyNumber'  => $policyNumber,
            'paymentMethod' => $paymentMethod,
            'date'          => now()->format('d M Y'),
        ];

        $waText = $this->getTemplate('whatsapp', 'payment_success', $vars);
        $this->sendWhatsApp($customer, $waText, 'payment_success', $policyId);

        $emailText = $this->getTemplate('email', 'payment_success', $vars);
        $subject = $this->getEmailSubject('payment_success', $vars, "Payment Received — Policy {$policyNumber}");
        $this->sendEmail($customer, $subject, $emailText, 'payment_success', $policyId);
    }

    /**
     * Policy activated — WhatsApp + Email only
     */
    public function notifyPolicyActivated(int $policyId, string $policyNumber): void
    {
        $customer = $this->getCustomerForPolicy($policyId);
        if (!$customer) return;

        $productName = DB::table('policies')
            ->join('products', 'products.id', '=', 'policies.product_id')
            ->where('policies.id', $policyId)
            ->value('products.name') ?? 'Insurance';

        $vars = [
            'firstName'    => $customer->firstName,
            'lastName'     => $customer->lastName ?? '',
            'policyNumber' => $policyNumber,
            'productName'  => $productName,
        ];

        $waText = $this->getTemplate('whatsapp', 'policy_activated', $vars);
        $this->sendWhatsApp($customer, $waText, 'policy_activated', $policyId);

        // Use existing 'create_policy' email hook if available, fallback to 'policy_activated'
        $emailText = $this->getTemplate('email', 'policy_activated', $vars)
            ?? $this->getTemplate('email', 'create_policy', $vars);
        $subject = $this->getEmailSubject('policy_activated', $vars, "Policy Activated — {$policyNumber}");
        $this->sendEmail($customer, $subject, $emailText, 'policy_activated', $policyId);
    }

    /**
     * Policy cancelled — WhatsApp + Email only
     */
    public function notifyPolicyCancelled(int $policyId, string $policyNumber): void
    {
        $customer = $this->getCustomerForPolicy($policyId);
        if (!$customer) return;

        $vars = [
            'firstName'    => $customer->firstName,
            'lastName'     => $customer->lastName ?? '',
            'policyNumber' => $policyNumber,
        ];

        $waText = $this->getTemplate('whatsapp', 'policy_cancelled', $vars);
        $this->sendWhatsApp($customer, $waText, 'policy_cancelled', $policyId);

        // Use existing 'cancel_policy' email hook if available
        $emailText = $this->getTemplate('email', 'policy_cancelled', $vars)
            ?? $this->getTemplate('email', 'cancel_policy', $vars);
        $subject = $this->getEmailSubject('policy_cancelled', $vars, "Policy Cancelled — {$policyNumber}");
        $this->sendEmail($customer, $subject, $emailText, 'policy_cancelled', $policyId);
    }

    /**
     * Renewal reminder — WhatsApp + Email (+ SMS if ≤7 days)
     */
    public function notifyPolicyRenewalReminder(int $policyId, string $policyNumber, int $daysUntilExpiry): void
    {
        $customer = $this->getCustomerForPolicy($policyId);
        if (!$customer) return;

        $vars = [
            'firstName'    => $customer->firstName,
            'lastName'     => $customer->lastName ?? '',
            'policyNumber' => $policyNumber,
            'days'         => $daysUntilExpiry,
        ];

        $waText = $this->getTemplate('whatsapp', 'renewal_reminder', $vars);
        $this->sendWhatsApp($customer, $waText, 'renewal_reminder', $policyId);

        // Use existing 'policy_expired_soon' or 'send_renewal' email hooks
        $emailText = $this->getTemplate('email', 'renewal_reminder', $vars)
            ?? $this->getTemplate('email', 'policy_expired_soon', $vars);
        $subject = $this->getEmailSubject('renewal_reminder', $vars, "Renewal Reminder — Policy {$policyNumber}");
        $this->sendEmail($customer, $subject, $emailText, 'renewal_reminder', $policyId);

        // SMS only if 7 days or less — urgent
        if ($daysUntilExpiry <= 7) {
            $smsText = $this->getTemplate('sms', 'renewal_reminder', $vars)
                ?? $this->getTemplate('sms', 'policy_expired_soon', $vars);
            $this->sendSms($customer, $smsText, 'renewal_reminder', $policyId);
        }
    }

    /**
     * Claim status update — WhatsApp + Email only
     */
    public function notifyClaimStatusUpdate(int $claimId, string $claimNumber, string $newStatus): void
    {
        $claim = DB::table('claims')->where('id', $claimId)->first(['policy_id', 'customer_id']);
        if (!$claim) return;

        $customer = DB::table('customer')->where('id', $claim->customer_id)
            ->first(['id', 'firstName', 'lastName', 'cellphone', 'email']);
        if (!$customer) return;

        $vars = [
            'firstName'   => $customer->firstName,
            'lastName'    => $customer->lastName ?? '',
            'claimNumber' => $claimNumber,
            'claim_number'=> $claimNumber,
            'status'      => $newStatus,
        ];

        $waText = $this->getTemplate('whatsapp', 'claim_update', $vars);
        $this->sendWhatsApp($customer, $waText, 'claim_update', $claim->policy_id);

        $emailText = $this->getTemplate('email', 'claim_update', $vars)
            ?? $this->getTemplate('email', 'claim_rejected', $vars);
        $subject = $this->getEmailSubject('claim_update', $vars, "Claim Update — {$claimNumber}");
        $this->sendEmail($customer, $subject, $emailText, 'claim_update', $claim->policy_id);
    }

    /**
     * KYC reminder — WhatsApp + Email only
     */
    public function notifyKycReminder(int $customerId): void
    {
        $customer = DB::table('customer')->where('id', $customerId)
            ->first(['id', 'firstName', 'lastName', 'cellphone', 'email']);
        if (!$customer) return;

        $vars = [
            'firstName' => $customer->firstName,
            'lastName'  => $customer->lastName ?? '',
        ];

        $waText = $this->getTemplate('whatsapp', 'kyc_reminder', $vars);
        $this->sendWhatsApp($customer, $waText, 'kyc_reminder', null);

        $emailText = $this->getTemplate('email', 'kyc_reminder', $vars)
            ?? $this->getTemplate('email', 'rekyc_verification', $vars);
        $subject = $this->getEmailSubject('kyc_reminder', $vars, "KYC Documents Required");
        $this->sendEmail($customer, $subject, $emailText, 'kyc_reminder', null);
    }

    /**
     * Policy / quote created — SMS + Email (both, always).
     *
     * Fires immediately when any create endpoint succeeds, including for
     * draft/quote policies (status 0). The message carries the policy number,
     * product name, plan name and monthly premium.
     *
     * Product name, plan name and premium are resolved from the policy row
     * (policies → products / product_plans) when not supplied; bundles pass
     * their own aggregated product/plan names. $monthlyPremium overrides the
     * stored premium and falls back to policies.premium — which may itself be
     * null for not-yet-rated quotes (shown as "to be confirmed").
     *
     * Wrapped in try/catch so a notification failure never breaks the create.
     */
    public function notifyPolicyCreated(int $policyId, string $policyNumber, $monthlyPremium = null, ?string $productName = null, ?string $planName = null): void
    {
        try {
            $customer = $this->getCustomerForPolicy($policyId);
            if (!$customer) return;

            $row = DB::table('policies as p')
                ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
                ->leftJoin('product_plans as pp', 'pp.id', '=', 'p.plan_id')
                ->where('p.id', $policyId)
                ->first(['pr.name as product_name', 'pp.name as plan_name', 'p.premium']);

            $productName = $productName ?: ($row->product_name ?? 'Insurance');
            $planName    = $planName    ?: ($row->plan_name ?? null);
            $premium     = $monthlyPremium ?? ($row->premium ?? null);

            $premiumStr = (is_numeric($premium) && (float) $premium > 0)
                ? 'P' . number_format((float) $premium, 2)
                : 'to be confirmed';
            $planSuffix = $planName ? " ({$planName})" : '';

            $vars = [
                'firstName'    => $customer->firstName,
                'lastName'     => $customer->lastName ?? '',
                'policyNumber' => $policyNumber,
                'productName'  => $productName,
                'planName'     => $planName ?? '',
                'premium'      => $premiumStr,
            ];

            // SMS
            $smsText = $this->getTemplate('sms', 'policy_created', $vars,
                "Dear {firstName}, your {productName}{$planSuffix} policy {policyNumber} has been created. Monthly premium: {premium}. Thank you for choosing Alpha Direct Insurance.");
            $this->sendSms($customer, $smsText, 'policy_created', $policyId);

            // Email
            $emailText = $this->getTemplate('email', 'policy_created', $vars,
                "Dear {firstName},\n\nThank you for choosing Alpha Direct Insurance. Your policy has been created successfully.\n\nProduct: {productName}\nPlan: {planName}\nPolicy Number: {policyNumber}\nMonthly Premium: {premium}\n\nWe will be in touch regarding payment and activation. For any queries call +267 390 1081.\n\nAlpha Direct Insurance");
            $subject = $this->getEmailSubject('policy_created', $vars, "Policy Created — {$policyNumber}");
            $this->sendEmail($customer, $subject, $emailText, 'policy_created', $policyId);
        } catch (\Throwable $e) {
            Log::error("notifyPolicyCreated failed: {$e->getMessage()}", ['policy_id' => $policyId]);
        }
    }

    // ─── Template engine ─────────────────────────────────────────

    /**
     * Fetch template from DB by channel + hook_slug, replace variables.
     */
    private function getTemplate(string $channel, string $hookSlug, array $vars, ?string $fallback = null): string
    {
        $table = match ($channel) {
            'sms'      => 'sms_templates',
            'whatsapp' => 'whatsapp_templates',
            'email'    => 'email_templates',
            default    => null,
        };

        $text = null;
        if ($table) {
            try {
                $template = DB::table($table)
                    ->where('hook_slug', $hookSlug)
                    ->first(['text']);
                $text = $template->text ?? null;
            } catch (\Exception $e) {
                // Table might not exist or column mismatch
            }
        }

        $text = $text ?? $fallback ?? "Dear {firstName}, Alpha Direct Insurance notification regarding your policy.";

        return $this->replaceVars($text, $vars);
    }

    /**
     * Get email subject from template, with variable replacement.
     */
    private function getEmailSubject(string $hookSlug, array $vars, string $fallback): string
    {
        try {
            $subject = DB::table('email_templates')
                ->where('hook_slug', $hookSlug)
                ->value('subject');
            if ($subject) return $this->replaceVars($subject, $vars);
        } catch (\Exception $e) {}

        return $this->replaceVars($fallback, $vars);
    }

    /**
     * Replace {variableName} placeholders in template text.
     */
    private function replaceVars(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return $text;
    }

    // ─── Channel senders ─────────────────────────────────────────

    private function sendSms(object $customer, string $message, string $type, ?int $policyId): void
    {
        $phone = $this->formatPhone($customer->cellphone ?? $customer->phone ?? '');
        if (empty($phone)) {
            $this->logNotification($customer->id, 'sms', $type, $message, false, 'No phone number', $policyId);
            return;
        }

        try {
            event(new SendSms($phone, $message, ['policyId' => $policyId]));
            $this->logNotification($customer->id, 'sms', $type, $message, true, null, $policyId);
        } catch (\Exception $e) {
            Log::error("SMS failed: {$e->getMessage()}", ['customer_id' => $customer->id, 'type' => $type]);
            $this->logNotification($customer->id, 'sms', $type, $message, false, $e->getMessage(), $policyId);
        }
    }

    private function sendWhatsApp(object $customer, string $message, string $type, ?int $policyId): void
    {
        $phone = $this->formatPhone($customer->cellphone ?? $customer->phone ?? '');
        if (empty($phone)) {
            $this->logNotification($customer->id, 'whatsapp', $type, $message, false, 'No phone number', $policyId);
            return;
        }

        try {
            $url = env('WHATSAPP_URL');
            $token = env('WHATSAPP_TOKEN');

            if (empty($url) || empty($token)) {
                $this->logNotification($customer->id, 'whatsapp', $type, $message, false, 'WhatsApp not configured', $policyId);
                return;
            }

            $payload = json_encode([
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ], JSON_UNESCAPED_SLASHES);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", "Content-Type: application/json"],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 15,
            ]);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $success = $httpCode >= 200 && $httpCode < 300;
            $this->logNotification($customer->id, 'whatsapp', $type, $message, $success, $success ? null : "HTTP {$httpCode}", $policyId);
        } catch (\Exception $e) {
            Log::error("WhatsApp failed: {$e->getMessage()}", ['customer_id' => $customer->id, 'type' => $type]);
            $this->logNotification($customer->id, 'whatsapp', $type, $message, false, $e->getMessage(), $policyId);
        }
    }

    private function sendEmail(object $customer, string $subject, string $message, string $type, ?int $policyId): void
    {
        $email = $customer->email ?? '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logNotification($customer->id, 'email', $type, $message, false, 'No valid email', $policyId);
            return;
        }

        try {
            $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
                . '<div style="background:#1e3a5f;padding:15px 20px;border-radius:8px 8px 0 0;">'
                . '<h2 style="color:#fff;margin:0;font-size:18px;">Alpha Direct Insurance</h2></div>'
                . '<div style="padding:20px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;">'
                . '<p style="color:#374151;line-height:1.6;">' . nl2br(e($message)) . '</p>'
                . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0;">'
                . '<p style="color:#9ca3af;font-size:12px;">Alpha Direct Insurance · Gaborone, Botswana · +267 390 1081</p>'
                . '</div></div>';

            event(new \AlphaDirect\Events\SendMail($email, $subject, $message, $html));
            $this->logNotification($customer->id, 'email', $type, $message, true, null, $policyId);
        } catch (\Exception $e) {
            Log::error("Email failed: {$e->getMessage()}", ['customer_id' => $customer->id, 'type' => $type]);
            $this->logNotification($customer->id, 'email', $type, $message, false, $e->getMessage(), $policyId);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function getCustomerForPolicy(int $policyId): ?object
    {
        return DB::table('policies as p')
            ->join('customer as c', 'c.id', '=', 'p.customer_id')
            ->where('p.id', $policyId)
            ->first(['c.id', 'c.firstName', 'c.lastName', 'c.cellphone', 'c.email']);
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if (empty($phone)) return '';
        if (str_starts_with($phone, '0')) $phone = substr($phone, 1);
        if (!str_starts_with($phone, '267')) $phone = '267' . $phone;
        return $phone;
    }

    private function logNotification(int $customerId, string $channel, string $type, string $message, bool $success, ?string $error = null, ?int $policyId = null): void
    {
        try {
            DB::connection('mysql_write')->table('notification_log')->insert([
                'customer_id'       => $customerId,
                'policy_id'         => $policyId,
                'channel'           => $channel,
                'notification_type' => $type,
                'message'           => mb_substr($message, 0, 1000),
                'status'            => $success ? 'sent' : 'failed',
                'error'             => $error ? mb_substr($error, 0, 500) : null,
                'created_at'        => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log notification: {$e->getMessage()}");
        }
    }
}
