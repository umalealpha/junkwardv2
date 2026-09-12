<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Http\Controllers\Admin\VaultController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DeepSeek over the FAC register.
 *
 * What it is FOR: reading the register's own exception lists — unmatched
 * policies, uncovered risks, missing rates, breached warranties, concentration
 * by counterparty — and writing the short human explanation of what they mean
 * and what to do first.
 *
 * What it is NOT for: computing money. Every figure handed to the model is
 * already computed in PHP and reconciled. The model never returns a number that
 * lands in the register. If it did, the register would be as trustworthy as a
 * guess.
 *
 * ── The wall ─────────────────────────────────────────────────────────────
 * Nothing that identifies a natural person leaves the building
 * (AD-POL-AI-GOV-001, not waivable). Commercial insured names and policy numbers
 * are business data and are still redacted here, because a FAC register can
 * contain a sole trader written in a person's own name and there is no reliable
 * way to tell one from the other at this layer.
 *
 * Redaction runs on the assembled payload. If it cannot run, nothing is sent.
 */
class FacIntelligenceService
{
    /**
     * Field names whose values are replaced with a stable token before sending.
     *
     * Deliberately over-broad. A field that should have been on this list and is
     * not becomes a privacy breach the moment somebody adds it to a payload, and
     * an over-redacted answer is merely less useful. So it covers everything the
     * register touches plus everything adjacent that a future payload might
     * reasonably carry: identity documents, addresses, banking, contact details.
     */
    private const REDACT_KEYS = [
        // Register fields
        'insured_name', 'insuredName', 'policy_number', 'policyNumber',
        'underwriter_name', 'underwriterName', 'notes', 'cancellation_reason',
        'client_name', 'customer_name', 'risk_carrier', 'riskCarrier',
        'settlement_reference', 'settlementReference', 'fac_slip_no', 'facSlipNo',
        // Contact
        'email', 'cellphone', 'phone', 'mobile', 'mobileNumber', 'contact',
        // Identity — never leaves, under any circumstances
        'omang', 'id_number', 'idNumber', 'national_id', 'passport',
        'passport_number', 'date_of_birth', 'dob',
        // Address
        'address', 'address_name', 'addressName', 'physical_address',
        'postal_address', 'street', 'city', 'plot',
        // Banking
        'bank_account', 'bankAccount', 'account_number', 'accountNumber',
        'branch_code', 'iban', 'swift',
        // Free text of any kind — dropped rather than tokenised. `summary` matters:
        // event summaries embed policy numbers and insured names in prose, which
        // key-based redaction cannot reach once it is inside a sentence.
        'description', 'description_of_risk', 'note', 'comment', 'reason',
        'summary', 'source_ref', 'sourceRef', 'notified_to', 'notifiedTo',
    ];

    public function isEnabled(): bool
    {
        return (bool) config('fac.intelligence.enabled', false) && $this->apiKey() !== '';
    }

    private function apiKey(): string
    {
        // Same Credentials Vault the Smart Underwriting extractor uses, so the
        // key is managed in one place and switching it needs no deploy.
        return (string) (VaultController::get('deepseek_api_key') ?: '');
    }

    /**
     * Explain a register position in plain English.
     *
     * @param  string $question  what the user asked, or a canned prompt key
     * @param  array  $facts     ALREADY-COMPUTED figures and exception lists
     * @return array{available:bool, answer:?string, model:?string, redactedFields:int, error:?string}
     */
    public function explain(string $question, array $facts): array
    {
        if (!$this->isEnabled()) {
            return [
                'available'      => false,
                'answer'         => null,
                'model'          => null,
                'redactedFields' => 0,
                'error'          => 'The FAC assistant is switched off, or no DeepSeek key is held in the Credentials Vault.',
            ];
        }

        try {
            [$safeFacts, $redacted] = $this->redact($facts);
        } catch (\Throwable $e) {
            // Redaction failed → send nothing. Fail closed, always.
            Log::error('FAC intelligence redaction failed — request not sent', ['error' => $e->getMessage()]);
            return [
                'available'      => false,
                'answer'         => null,
                'model'          => null,
                'redactedFields' => 0,
                'error'          => 'The request was not sent because the privacy check could not be completed.',
            ];
        }

        $payload = [
            'model' => config('fac.intelligence.model', 'deepseek-reasoner'),
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user',   'content' => $this->userPrompt($question, $safeFacts)],
            ],
            'temperature' => 0.2,
            'stream'      => false,
        ];

        try {
            $res = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey(),
                    'Content-Type'  => 'application/json',
                ])
                ->timeout((int) config('fac.intelligence.timeout', 90))
                ->post(rtrim((string) config('fac.intelligence.base_url'), '/') . '/chat/completions', $payload);

            if (!$res->successful()) {
                Log::warning('FAC intelligence call failed', ['status' => $res->status()]);
                return [
                    'available'      => false,
                    'answer'         => null,
                    'model'          => $payload['model'],
                    'redactedFields' => $redacted,
                    'error'          => 'The assistant did not respond (status ' . $res->status() . '). The figures on this screen are unaffected.',
                ];
            }

            $answer = $res->json('choices.0.message.content');

            return [
                'available'      => true,
                'answer'         => is_string($answer) ? trim($answer) : null,
                'model'          => $payload['model'],
                'redactedFields' => $redacted,
                'error'          => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('FAC intelligence exception', ['error' => $e->getMessage()]);
            return [
                'available'      => false,
                'answer'         => null,
                'model'          => $payload['model'],
                'redactedFields' => $redacted,
                'error'          => 'The assistant could not be reached. The figures on this screen are unaffected.',
            ];
        }
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You are a reinsurance analyst reading Alpha Direct Insurance's facultative (FAC)
register. Botswana; the currency is Pula (BWP); VAT is 14% and does not apply to
every placement.

Rules you must follow:
- Every figure you are given has already been computed and reconciled. Use them
  as given. Do not recompute, re-add, restate or estimate any amount, and never
  invent one.
- Policies and insured parties are shown to you as tokens (POL_1, INSURED_3).
  Refer to them by their token. Do not guess what they stand for.
- Answer in plain English, short sentences, for a reader who is not technical.
- Lead with what matters most and what should be done first.
- If the facts do not support an answer, say so plainly. Do not fill the gap.
- No more than three recommendations. No nested lists.
TXT;
    }

    private function userPrompt(string $question, array $facts): string
    {
        return $question . "\n\nRegister facts (already computed, reconciled):\n"
            . json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Replace identifying values with stable tokens, recursively.
     *
     * Stable within one request, so the model can reason about "POL_4 again"
     * without ever seeing the real number.
     *
     * @return array{0:array, 1:int}  the safe payload and how many values were replaced
     */
    private function redact(array $facts): array
    {
        // NO OFF-SWITCH. AD-POL-AI-GOV-001 is not waivable, so a config flag that
        // could disable redaction should not exist at all — a branch that must
        // never be taken is a branch that will eventually be taken.

        $map     = [];
        $counter = ['POL' => 0, 'INSURED' => 0, 'PERSON' => 0, 'TEXT' => 0];
        $count   = 0;

        $tokenFor = static function (string $bucket, string $value) use (&$map, &$counter): string {
            $key = $bucket . '|' . strtolower(trim($value));
            if (!isset($map[$key])) {
                $map[$key] = $bucket . '_' . (++$counter[$bucket]);
            }
            return $map[$key];
        };

        $walk = function ($node, ?string $key = null) use (&$walk, $tokenFor, &$count) {
            if (is_array($node)) {
                $out = [];
                foreach ($node as $k => $v) {
                    $out[$k] = $walk($v, is_string($k) ? $k : $key);
                }
                return $out;
            }

            if (!is_string($node) || $node === '' || $key === null) {
                return $node;
            }

            if (!in_array($key, self::REDACT_KEYS, true)) {
                return $node;
            }

            $count++;

            return match ($key) {
                'policy_number', 'policyNumber', 'fac_slip_no', 'facSlipNo',
                'settlement_reference', 'settlementReference'
                    => $tokenFor('POL', $node),

                'insured_name', 'insuredName', 'client_name', 'customer_name',
                'risk_carrier', 'riskCarrier'
                    => $tokenFor('INSURED', $node),

                'underwriter_name', 'underwriterName', 'email', 'cellphone',
                'phone', 'mobile', 'mobileNumber', 'contact'
                    => $tokenFor('PERSON', $node),

                // Identity documents, addresses, banking and free text are DROPPED,
                // not tokenised. A token is still a stable handle on a person, and
                // for these there is no legitimate reason for the model to reason
                // about the value at all — so nothing goes, not even a placeholder
                // it could correlate on.
                default => '[redacted]',
            };
        };

        return [$walk($facts), $count];
    }
}
