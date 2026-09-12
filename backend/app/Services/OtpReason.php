<?php

namespace AlphaDirect\Services;

/**
 * Server-side registry that turns the *functionality that triggered an OTP*
 * into a human-readable reason stored on public_otps.reason.
 *
 * Added 2026-06-25.
 *
 * Design rules (kept deliberately small — this only DETERMINES the reason,
 * it does not change any OTP send/verify logic):
 *   - The caller passes a trusted `source` key identifying the functionality
 *     (e.g. 'legal_insurance_purchase'). The mapping lives HERE on the server,
 *     so the stored reason can't be spoofed into something wrong by the client.
 *   - Unknown / missing source keys fall back to a per-`purpose` default, so
 *     every OTP still records *some* reason automatically.
 *   - An optional short `note` (e.g. 'P49/month') carries the dynamic specifics
 *     the static map can't know; it is sanitised and appended in parentheses.
 */
class OtpReason
{
    /** Trusted source key → human-readable reason. */
    public const REASONS = [
        // Product purchase flows (start.alphadirect.co.bw)
        'motor_comp_purchase'        => 'Purchasing Motor Comprehensive cover',
        'third_party_car_purchase'   => 'Purchasing Third-Party Motor cover',
        'legal_insurance_purchase'   => 'Purchasing Legal Insurance cover',
        'hospital_cashback_purchase' => 'Purchasing Hospital Cashback cover',
        'accidental_death_purchase'  => 'Purchasing Accidental Death cover',
        'mobile_electronic_purchase' => 'Purchasing Mobile & Electronic Device cover',
        'bundle_purchase'            => 'Purchasing a bundled cover',
        'travel_purchase'            => 'Purchasing Travel Insurance cover',

        // Servicing flows
        'customer_login'             => 'Customer sign-in / identity verification',
        'policy_edit'                => 'Authorising a change to an existing policy',
        'policy_upgrade'             => 'Authorising a policy upgrade',
        'policy_renewal'             => 'Authorising a policy renewal',
        'kyc_update'                 => 'Updating KYC / identity documents',
        'vehicle_inspection'         => 'Vehicle pre-inspection submission',
        'payment_authorize'          => 'Authorising a payment',
        'consent_capture'            => 'Capturing customer consent (identity verification)',
        // OTP-as-signature: the verified code stands in for the Insured's
        // Signature block on the Travel Insurance proposal form (ECTA 2014 s.17).
        'travel_proposal_sign'       => 'Signing the Travel Insurance proposal form',
        'agent_unlock'               => 'Call-centre agent unlocking policy for PII access',
        // Scanning the QR on the posted one-page policy cover sheet. Says
        // what the customer is actually doing — without it the fallback for
        // customer_auth reads "Verifying customer identity", which tells
        // someone holding a posted page nothing about why they got a code.
        // Length is deliberate: BW SMS is billed per 160-character segment,
        // and the surrounding template leaves 28 characters before it spills
        // into a second segment. "Downloading your policy document" (32) cost
        // two segments on every OTP; this fits in one.
        'cover_sheet_document'       => 'Policy document download',
    ];

    /** OTP purpose → fallback reason when no known source key is supplied. */
    public const PURPOSE_FALLBACK = [
        'customer_auth'     => 'Verifying customer identity',
        'policy_edit'       => 'Authorising a change to an existing policy',
        'kyc_update'        => 'Updating KYC / identity documents',
        'vehicle_inspect'   => 'Vehicle pre-inspection submission',
        'payment_authorize' => 'Authorising a payment',
        'agent_unlock'      => 'Call-centre agent unlocking policy for PII access',
    ];

    private const DEFAULT_REASON = 'Verifying customer identity';

    /**
     * Resolve the reason string to persist.
     *
     * @param  string|null  $source   Trusted functionality key (see REASONS).
     * @param  string       $purpose  OTP purpose, used as fallback.
     * @param  string|null  $note     Optional short dynamic detail (e.g. 'P49/month').
     */
    public static function resolve(?string $source, string $purpose, ?string $note = null): string
    {
        $base = ($source !== null && isset(self::REASONS[$source]))
            ? self::REASONS[$source]
            : (self::PURPOSE_FALLBACK[$purpose] ?? self::DEFAULT_REASON);

        $note = self::cleanNote($note);

        return $note !== '' ? "{$base} ({$note})" : $base;
    }

    /** Strip tags, collapse whitespace and cap length so the note stays a short label. */
    private static function cleanNote(?string $note): string
    {
        if ($note === null) {
            return '';
        }
        $note = trim(preg_replace('/\s+/', ' ', strip_tags($note)));

        return mb_substr($note, 0, 60);
    }
}
