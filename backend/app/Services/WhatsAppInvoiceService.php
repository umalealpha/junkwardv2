<?php

namespace AlphaDirect\Services;

use AlphaDirect\Customer;
use AlphaDirect\Helper;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppInvoiceService
{
    private string $whatsappUrl;
    private string $whatsappToken;

    public function __construct()
    {
        $this->whatsappUrl = env('WHATSAPP_URL', '');
        $this->whatsappToken = env('WHATSAPP_TOKEN', '');
    }

    /**
     * Send an invoice PDF to a customer via WhatsApp.
     * Called after invoice is generated in the ledger.
     */
    public function sendInvoice(int $ledgerId): bool
    {
        try {
            $ledger = Ledger::where('id', $ledgerId)
                ->whereNotNull('invoice_no')
                ->first(['id', 'policy_id', 'customer_id', 'invoice_no', 'invoice_amount', 'invoice_date']);

            if (!$ledger) {
                Log::warning("WhatsApp invoice: Ledger {$ledgerId} not found or no invoice_no");
                return false;
            }

            $customer = Customer::where('id', $ledger->customer_id)
                ->first(['id', 'firstName', 'lastName', 'cellphone', 'is_whatsapp']);

            if (!$customer || !$customer->cellphone) {
                return false;
            }

            // Only send to customers confirmed on WhatsApp
            if ($customer->is_whatsapp !== 1 && $customer->is_whatsapp !== '1') {
                Log::info("WhatsApp invoice skipped: customer {$customer->id} not on WhatsApp (is_whatsapp={$customer->is_whatsapp})");
                return false;
            }

            $phone = $this->formatPhone($customer->cellphone);
            if (empty($phone)) {
                return false;
            }

            if (empty($this->whatsappUrl) || empty($this->whatsappToken)) {
                Log::warning('WhatsApp invoice: WHATSAPP_URL or WHATSAPP_TOKEN not configured');
                return false;
            }

            $policy = Policy::where('id', $ledger->policy_id)->first(['id', 'policyNumber']);
            $policyNumber = $policy ? $policy->policyNumber : 'N/A';
            $policyId = $policy ? $policy->id : null;

            // Generate the invoice PDF and get its S3 URL
            $pdfUrl = $this->getInvoicePdfUrl($ledger);

            if ($pdfUrl) {
                return $this->sendDocumentMessage($phone, $pdfUrl, $customer, $ledger, $policyNumber, $policyId);
            } else {
                return $this->sendTextInvoiceNotification($phone, $customer, $ledger, $policyNumber, $policyId);
            }

        } catch (\Throwable $e) {
            Log::error("WhatsApp invoice delivery failed: {$e->getMessage()}", [
                'ledger_id' => $ledgerId,
            ]);
            return false;
        }
    }

    /**
     * Send any WhatsApp message (for general use / testing).
     */
    public function sendText(string $phone, string $message): array
    {
        $phone = $this->formatPhone($phone);

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message],
        ], JSON_UNESCAPED_SLASHES);

        $result = $this->callWhatsAppApi($payload);

        $this->logMessage([
            'phone_number'  => $phone,
            'message_type'  => 'text',
            'purpose'       => 'manual_send',
            'message_body'  => $message,
            'wa_message_id' => $result['wa_message_id'],
            'status'        => $result['success'] ? 'sent' : 'failed',
            'http_code'     => $result['http_code'],
            'api_response'  => $result['response'],
            'error'         => $result['error'],
        ]);

        return $result;
    }

    /**
     * Send invoice as a WhatsApp document (PDF).
     */
    private function sendDocumentMessage(string $phone, string $pdfUrl, $customer, $ledger, string $policyNumber, ?int $policyId): bool
    {
        $amount = number_format(abs($ledger->invoice_amount ?? 0), 2);
        $invoiceDate = $ledger->invoice_date ? Carbon::parse($ledger->invoice_date)->format('d M Y') : now()->format('d M Y');
        $caption = "Hi {$customer->firstName}, your invoice {$ledger->invoice_no} for policy {$policyNumber} (P{$amount}) dated {$invoiceDate} is attached. Alpha Direct Insurance.";

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'document',
            'document' => [
                'link' => $pdfUrl,
                'caption' => $caption,
                'filename' => "{$ledger->invoice_no}.pdf",
            ],
        ], JSON_UNESCAPED_SLASHES);

        $result = $this->callWhatsAppApi($payload);

        if (!$result['success'] && $result['error_code'] == 131026) {
            $this->markNotOnWhatsApp($customer->id);
        }

        $this->logMessage([
            'customer_id'   => $customer->id,
            'policy_id'     => $policyId,
            'ledger_id'     => $ledger->id,
            'phone_number'  => $phone,
            'message_type'  => 'document',
            'purpose'       => 'invoice_delivery',
            'message_body'  => $caption,
            'document_url'  => $pdfUrl,
            'wa_message_id' => $result['wa_message_id'],
            'status'        => $result['success'] ? 'sent' : 'failed',
            'http_code'     => $result['http_code'],
            'api_response'  => $result['response'],
            'error'         => $result['error'],
        ]);

        return $result['success'];
    }

    /**
     * Fallback: send text-only invoice notification.
     */
    private function sendTextInvoiceNotification(string $phone, $customer, $ledger, string $policyNumber, ?int $policyId): bool
    {
        $amount = number_format(abs($ledger->invoice_amount ?? 0), 2);
        $invoiceDate = $ledger->invoice_date ? Carbon::parse($ledger->invoice_date)->format('d M Y') : now()->format('d M Y');

        $message = "Hi {$customer->firstName},\n\n"
            . "Your invoice *{$ledger->invoice_no}* has been generated for policy *{$policyNumber}*.\n\n"
            . "Amount: *P{$amount}*\n"
            . "Date: {$invoiceDate}\n\n"
            . "Please ensure timely payment. For queries, contact us at +267 390 1081.\n\n"
            . "Alpha Direct Insurance";

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message],
        ], JSON_UNESCAPED_SLASHES);

        $result = $this->callWhatsAppApi($payload);

        if (!$result['success'] && $result['error_code'] == 131026) {
            $this->markNotOnWhatsApp($customer->id);
        }

        $this->logMessage([
            'customer_id'   => $customer->id,
            'policy_id'     => $policyId,
            'ledger_id'     => $ledger->id,
            'phone_number'  => $phone,
            'message_type'  => 'text',
            'purpose'       => 'invoice_delivery',
            'message_body'  => $message,
            'wa_message_id' => $result['wa_message_id'],
            'status'        => $result['success'] ? 'sent' : 'failed',
            'http_code'     => $result['http_code'],
            'api_response'  => $result['response'],
            'error'         => $result['error'],
        ]);

        return $result['success'];
    }

    /**
     * Execute WhatsApp API call and return structured result.
     */
    private function callWhatsAppApi(string $payload): array
    {
        $ch = curl_init($this->whatsappUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->whatsappToken}",
                "Content-Type: application/json",
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = $httpCode >= 200 && $httpCode < 300;
        $data = json_decode($resp, true);

        $waMessageId = $data['messages'][0]['id'] ?? null;
        $errorCode = $data['error']['code'] ?? null;
        $errorMsg = $data['error']['message'] ?? null;

        return [
            'success'       => $success,
            'http_code'     => $httpCode,
            'response'      => $resp,
            'wa_message_id' => $waMessageId,
            'error_code'    => $errorCode,
            'error'         => $success ? null : ($errorMsg ?? "HTTP {$httpCode}"),
        ];
    }

    /**
     * Log message to whatsapp_message_log table for audit.
     */
    private function logMessage(array $data): void
    {
        try {
            DB::connection('mysql_write')->table('whatsapp_message_log')->insert(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (\Throwable $e) {
            // Fallback to default connection
            try {
                DB::table('whatsapp_message_log')->insert(array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } catch (\Throwable $e2) {
                Log::error("WhatsApp log failed: {$e2->getMessage()}");
            }
        }
    }

    /**
     * Get the public URL for the invoice PDF (from S3/CloudFront).
     */
    private function getInvoicePdfUrl($ledger): ?string
    {
        try {
            $ids = [$ledger->id];
            $path = Helper::generateInvoice($ids);

            if ($path && Storage::disk('s3')->exists($path)) {
                return Helper::getCloudFrontURL($path);
            }
        } catch (\Throwable $e) {
            Log::warning("Invoice PDF generation failed for ledger {$ledger->id}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Mark a customer as not on WhatsApp (based on send failure).
     */
    private function markNotOnWhatsApp(int $customerId): void
    {
        try {
            DB::connection('mysql_write')->table('customer')
                ->where('id', $customerId)
                ->update(['is_whatsapp' => 0, 'whatsapp_checked_at' => Carbon::now()]);
        } catch (\Throwable $e) {
            try {
                Customer::where('id', $customerId)
                    ->update(['is_whatsapp' => 0, 'whatsapp_checked_at' => Carbon::now()]);
            } catch (\Throwable $e2) {}
        }
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if (empty($phone)) return '';
        if (str_starts_with($phone, '0')) $phone = substr($phone, 1);
        if (!str_starts_with($phone, '267')) $phone = '267' . $phone;
        return $phone;
    }
}
