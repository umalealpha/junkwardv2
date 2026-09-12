<?php

namespace AlphaDirect\Helpers;

/**
 * Always-on PII scrubber for data leaving the app to the external LLM (Groq).
 *
 * Pen-test C3 / directive from Arjun (2026-07-30, cc Lakshmi): "ensure no PII
 * can be sent via the AI platform." This is DELIBERATELY independent of the
 * privilege-aware {@see PiiMask} used for in-app display — cross-border egress
 * to a third-party model must ALWAYS be scrubbed, regardless of who is asking
 * (the pen-test's elevated account is exactly why privilege-aware masking is
 * not enough here).
 *
 * Applied to every tool RESULT before it is json_encoded to the LLM, and
 * (pattern-based) to the user's free-text message. Only the LLM-bound copy is
 * scrubbed — the raw result is still captured separately for the in-app UI.
 *
 * Redacts DIRECT IDENTIFIERS only — name, email, phone, Omang/national ID,
 * passport, bank account, address, DOB. Operational data the assistant needs
 * (policy numbers, amounts, dates, product, status, counts) is preserved.
 *
 * RESIDUAL (documented, not solved here): a user can TYPE a person's name into
 * the chat — free-text names cannot be reliably pattern-matched, so they are
 * not scrubbed. The only absolute guarantee of zero PII egress is a
 * self-hosted / in-country model.
 */
class AiEgressPiiScrubber
{
    private const REDACTED = '[redacted]';

    /**
     * Normalised (lowercase, alphanumeric-only) field / column labels that
     * carry a direct identifier. Exact-match against the normalised label so
     * non-PII look-alikes ("product", "productName", "policyNumber") are kept.
     */
    private const PII_LABELS = [
        // names
        'customername', 'customer', 'firstname', 'lastname', 'fullname', 'middlename',
        'clientname', 'insuredname', 'holdername', 'beneficiary', 'beneficiaryname', 'nextofkin',
        // contact
        'email', 'emailaddress',
        'phone', 'phonenumber', 'cellphone', 'cell', 'mobile', 'mobilenumber', 'telephone', 'contactnumber', 'contact',
        // identity
        'omang', 'idnumber', 'nationalid', 'identitynumber', 'passport', 'passportnumber',
        // banking
        'bankaccount', 'accountnumber', 'accountno', 'iban', 'branchcode',
        // address / dob
        'address', 'residentialaddress', 'physicaladdress', 'postaladdress', 'homeaddress',
        'dateofbirth', 'dob', 'birthdate',
    ];

    /**
     * Scrub a tool-result structure before it is sent to the LLM. Handles the
     * two shapes the tools return: a {columns, rows} table, and keyed / nested
     * arrays. Recursive. Non-array input falls through to text scrubbing.
     */
    public static function scrub($data)
    {
        if (is_array($data)) {
            // {columns: [...], rows: [[...], ...]} table — redact PII columns by label.
            if (isset($data['columns'], $data['rows']) && is_array($data['columns']) && is_array($data['rows'])) {
                $piiCols = [];
                foreach ($data['columns'] as $i => $label) {
                    if (self::isPiiLabel((string) $label)) {
                        $piiCols[$i] = true;
                    }
                }
                foreach ($data['rows'] as $r => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    foreach ($row as $c => $val) {
                        if (isset($piiCols[$c])) {
                            $data['rows'][$r][$c] = self::REDACTED;
                        } elseif (is_string($val)) {
                            $data['rows'][$r][$c] = self::scrubText($val);
                        }
                    }
                }
                foreach ($data as $k => $v) {
                    if ($k === 'rows' || $k === 'columns') {
                        continue;
                    }
                    $data[$k] = self::scrub($v);
                }
                return $data;
            }

            // Keyed / nested — redact values whose key is a PII label; recurse otherwise.
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = (is_string($k) && self::isPiiLabel($k)) ? self::REDACTED : self::scrub($v);
            }
            return $out;
        }

        if (is_string($data)) {
            return self::scrubText($data);
        }

        return $data;
    }

    /**
     * Pattern-scrub a free-text string: emails, BW phone numbers, and bare
     * Omang / national-ID numbers. Deliberately conservative — the Omang match
     * requires a standalone 9-digit token (letter/digit-guarded) so policy
     * numbers (which contain letters, e.g. MIS2026215207) and decimal amounts
     * are NOT corrupted.
     */
    public static function scrubText(?string $text): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', self::REDACTED, $text);              // email
        $text = preg_replace('/\+?267[\s-]?\d{7,8}\b/', self::REDACTED, $text);                 // BW phone with country code
        $text = preg_replace('/(?<![A-Za-z0-9.])\d{9}(?![A-Za-z0-9.])/', self::REDACTED, $text); // bare Omang / national ID
        return (string) $text;
    }

    private static function isPiiLabel(string $label): bool
    {
        $norm = preg_replace('/[^a-z0-9]/', '', strtolower($label));
        return $norm !== '' && in_array($norm, self::PII_LABELS, true);
    }
}
