<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public policy-creation endpoints for start.alphadirect.co.bw.
 *
 * Currently wired:
 *   POST /api/v1/public/policies/create  — Motor Comprehensive quote
 *
 * Auth: Bearer session token (purpose=payment_authorize) so a leaked
 * GET URL can't seed motor_quotes for a different customer. The
 * session's cellphone is normalised against the form's `phone` field
 * before persisting — they must match.
 *
 * The quote sits in motor_quotes until DPO confirms payment, at which
 * point a back-end worker (legacy createPolicy logic re-implemented
 * as a job — separate port) materialises it into Policy + Customer +
 * Motor rows. That keeps the customer site clean of half-formed
 * policies if the customer abandons before paying.
 */
class PublicPolicyCreateController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    public function createMotor(Request $request): JsonResponse
    {
        // Validation rules mirror the legacy Start.alphadirect.co.bw
        // MotorComprehensiveController so a quote that passed there
        // also passes here. Differences from legacy are noted inline.
        $request->validate([
            // Customer — name fields capped at 30 to match legacy
            // maxlength=30 + alphanumeric+dot+space charset.
            'firstName'         => ['required', 'string', 'max:30', 'regex:/^[A-Za-z. ]+$/'],
            'lastName'          => ['required', 'string', 'max:30', 'regex:/^[A-Za-z. ]+$/'],
            'middleName'        => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z. ]+$/'],
            // Omang: 9 digits, digit 5 is gender-coded (1 male / 2 female).
            // Passport: 5-20 chars alphanumeric + hyphen.
            'omang'             => ['nullable', 'string', 'regex:/^[0-9]{4}[12][0-9]{4}$/'],
            'passport'          => ['nullable', 'string', 'min:5', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            // Document expiry — when supplied, NOT_BEFORE today.
            'omangExpiry'       => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'passportExpiry'    => 'nullable|date_format:Y-m-d|after_or_equal:today',
            // Driver age band: 18 minimum, 75 maximum. (Legacy commented
            // 65 maximum — we use 75 since the underwriting band agreed
            // for V2 rolls back to 75.)
            'dob'               => [
                'required', 'date_format:Y-m-d',
                'before:' . now()->subYears(18)->toDateString(),
                'after_or_equal:' . now()->subYears(75)->toDateString(),
            ],
            'gender'            => 'required|string|in:Male,Female',
            'maritalStatus'     => 'required|string|in:Single,Married,Divorced,Widowed,Living Together,Living Separately',
            // Phone: BW local format, 8 digits, no country code (legacy).
            'phone'             => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            // Email: OPTIONAL on the legacy site (collected only at the
            // DPO / N-Genius step). When supplied, must be valid.
            'email'             => 'nullable|email|max:160',
            'residentialAddress'=> 'required|string|max:100',
            'nationality'       => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'occupation'        => 'nullable|string|max:100',
            'occupationLevel'   => 'nullable|string|in:Senior,Middle,Junior,Unemployed',
            'employerName'      => 'nullable|string|max:50',
            'country'           => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'plotNumber'        => 'nullable|string|max:120',
            'isPep'             => 'nullable|boolean',
            'pepType'           => 'nullable|string|max:255',
            'isPepRelated'      => 'nullable|boolean',
            'pepRelationship'   => 'nullable|string|max:50',
            'pepRelationshipSpecify' => 'nullable|string|max:255',
            'licenseNumber'     => 'nullable|string|max:32',
            'licenseClass'      => 'nullable|string|max:8',
            'licenseValidFrom'  => 'nullable|date_format:Y-m-d',
            'licenseValidTo'    => 'nullable|date_format:Y-m-d|after:today',

            // Vehicle
            'vehicleMake'       => 'required|string|max:60',
            'vehicleModel'      => 'nullable|string|max:80',
            'vehicleYear'       => [
                'required', 'digits:4', 'integer',
                'min:' . (int) (date('Y') - 60),
                'max:' . (int) (date('Y') + 1),
            ],
            // Plate: BW format B + 3 digits + 3 letters. Strip spaces
            // before regex check by using a custom rule? Simpler — accept
            // either spaced or unspaced and let the FE normalise.
            'vehicleReg'        => ['required', 'string', 'max:20', 'regex:/^[Bb]\s?\d{3}\s?[A-Za-z]{3}$/'],
            'vehicleEngine'     => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9]*$/'],
            'vehicleChassis'    => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9]*$/'],
            'vehicleColour'     => 'nullable|string|max:24',
            // Sum insured: legacy minimum P20,000 from quoteMotorComprehensive
            // modal. Upper bound is the auto-quote ceiling — half a million
            // pula. Vehicles above P500k need underwriter review and are
            // routed to a callback rather than quoted on the customer site.
            'sumInsured'        => 'required|numeric|min:20000|max:500000',
            'isImported'        => 'required|string|in:Yes,No',
            'claimCount'        => 'required|integer|min:0|max:3',
        ]);

        // Cross-field rule: at least one of Omang / passport. Legacy
        // enforces this via the conditional-required pair on the form
        // and we mirror that server-side as a 422.
        if (!$request->filled('omang') && !$request->filled('passport')) {
            return response()->json([
                'ok' => false,
                'error' => 'omang_or_passport_required',
                'message' => 'Either Omang or Passport is required.',
            ], 422);
        }

        // ─── Plate duplicate guard ───────────────────────────────────────
        // Block a new motor policy for a plate already on a NON-cancelled
        // policy (parity with graphiteBWV8). Cancelled policies free the plate.
        // Blocked here (create-motor) so the customer can't pay first.
        $normPlate = strtoupper(preg_replace('/\s+/', '', (string) $request->input('vehicleReg', '')));
        if ($normPlate !== '' && \AlphaDirect\Policy::plateInUseByActivePolicy($normPlate)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'vehicle_already_insured',
                'message' => 'A policy is already held for this vehicle registration.',
            ], 422);
        }

        // Auto-quote ceiling: vehicles above this sum insured need
        // underwriting and are not quoted on the customer-facing site.
        // Configurable via env so ops can adjust without a deploy.
        $autoLimit = (float) env('MOTOR_AUTO_SUM_INSURED_LIMIT', 500_000);
        if ((float) $request->input('sumInsured') >= $autoLimit) {
            return response()->json([
                'ok'    => false,
                'error' => 'underwriting_required',
                'message' => "Vehicles valued at P{$autoLimit} or above need underwriter review. Please request an agent callback — they'll quote you on the full graphite portal.",
                'callback_path' => '/get-callback',
                'limit' => $autoLimit,
            ], 422);
        }

        $request->validate([

            // Pricing — all four come from the rating engine response
            'rateId'            => 'nullable|integer',
            'premiumMonthly'    => 'nullable|numeric|min:0',
            'premiumQuarterly'  => 'nullable|numeric|min:0',
            'premiumAnnual'     => 'required|numeric|min:0',
            'premiumFrequency'  => 'required|string|in:monthly,quarterly,annual',
        ]);

        // Validate the session token AND the cellphone match (first validate ran above
        // before the underwriting check; this second validate covers the pricing fields).
        $bearer = $this->extractBearer($request);
        if (!$bearer) return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        $session = $this->otp->validateToken($bearer);
        if (!$session) return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);

        $sessionPhone = $this->normalize((string) ($session['cellphone'] ?? ''));
        $formPhone    = $this->normalize($request->input('phone'));
        if ($sessionPhone !== $formPhone) {
            return response()->json(['ok' => false, 'error' => 'session_phone_mismatch'], 403);
        }

        // Consent gate. The 3-step OTP-bound consent flow at
        // /api/public/v1/consents/* must have produced a non-revoked
        // accepted_at within the last 24h for product_scope=retail
        // before we'll accept a quote. This is the audit-evidence proof
        // required by DPA 2024 + ECTA 2014 + NBFIRA TCF. Stored as a
        // soft-fail (env flag) for the bedding-in window so we can
        // observe coverage % before flipping to hard-block.
        // The DB has rows in two formats — the new 3-step
        // /public/v1/consents/accept stores E.164 (+267XXXXXXXX), the
        // legacy /public/consent/privacy strips to 8-digit local
        // ("XXXXXXXX"). Match either so customers who accepted via the
        // legacy modal aren't locked out.
        $cellphoneCandidates = $this->consentLookupCandidates($formPhone);
        $consent = DB::table('customer_privacy_consents')
            ->whereIn('cellphone', $cellphoneCandidates)
            ->whereNull('revoked_at')
            ->where('accepted_at', '>=', Carbon::now()->subHours(24))
            ->where('product_scope', 'retail')
            ->orderByDesc('accepted_at')
            ->first();
        if (!$consent) {
            $hardBlock = filter_var(env('CONSENT_GATE_HARD_BLOCK', true), FILTER_VALIDATE_BOOLEAN);
            \Illuminate\Support\Facades\Log::warning('public_policy_create.consent_missing', [
                'cellphone_hash' => substr(hash('sha256', $cellphoneForConsent), 0, 8),
                'hard_block'     => $hardBlock,
            ]);
            if ($hardBlock) {
                return response()->json([
                    'ok' => false,
                    'error' => 'consent_required',
                    'message' => 'Please complete the consent flow before purchasing.',
                ], 403);
            }
        }

        // Pick the amount that goes to the gateway based on selected
        // frequency. We do this server-side so a client can't lower
        // the price by sending a different number.
        $freq = $request->input('premiumFrequency');
        $amounts = [
            'monthly'   => $request->input('premiumMonthly')   ?? null,
            'quarterly' => $request->input('premiumQuarterly') ?? null,
            'annual'    => $request->input('premiumAnnual'),
        ];
        $amountToPay = $amounts[$freq];
        if (!$amountToPay || $amountToPay <= 0) {
            return response()->json(['ok' => false, 'error' => 'invalid_premium_for_frequency'], 422);
        }

        // Generate a unique quote number — used as DPO CompanyRef.
        // Format: MQ-YYYYMMDD-<6char> so support can eyeball them.
        $quoteNumber = 'MQ-' . Carbon::now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $customerPayload = $request->only([
            'firstName','middleName','lastName','omang','passport',
            'omangExpiry','passportExpiry',
            'dob','gender',
            'maritalStatus','phone','email','residentialAddress',
            'nationality','occupation','occupationLevel','employerName','country','plotNumber',
            'isPep','pepType','isPepRelated','pepRelationship','pepRelationshipSpecify',
            'licenseNumber','licenseClass','licenseValidFrom','licenseValidTo',
        ]);
        $vehiclePayload = $request->only([
            'vehicleMake','vehicleModel','vehicleYear','vehicleReg',
            'vehicleEngine','vehicleChassis','vehicleColour',
            'sumInsured','isImported','claimCount',
        ]);

        DB::table('motor_quotes')->insert([
            'quote_number'      => $quoteNumber,
            'cellphone'         => $sessionPhone,
            'auth_token_hash'   => hash('sha256', ($session['cellphone'] ?? '') . config('app.key')),
            'rate_id'           => $request->input('rateId'),
            'customer_payload'  => json_encode($customerPayload),
            'vehicle_payload'   => json_encode($vehiclePayload),
            'sum_insured'       => $request->input('sumInsured'),
            'premium_monthly'   => $request->input('premiumMonthly'),
            'premium_quarterly' => $request->input('premiumQuarterly'),
            'premium_annual'    => $request->input('premiumAnnual'),
            'premium_frequency' => $freq,
            'amount_to_pay'     => $amountToPay,
            'status'            => 'pending_pay',
            'expires_at'        => Carbon::now()->addDays(7),
            'client_ip'         => $request->ip(),
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);

        Log::info('public_policy_create.motor_quote', [
            'quote_number' => $quoteNumber,
            'amount'       => $amountToPay,
            'freq'         => $freq,
            'cellphone'    => substr(hash('sha256', $sessionPhone), 0, 8),
        ]);

        return response()->json([
            'ok'             => true,
            'quote_number'   => $quoteNumber,
            'policy_number'  => $quoteNumber, // alias — what PaymentRedirect expects
            'amount_to_pay'  => $amountToPay,
            'premium_freq'   => $freq,
            'expires_at'     => Carbon::now()->addDays(7)->toIso8601String(),
        ], 201);
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }

    /**
     * Build the cellphone string the consent controller stored. The
     * consent flow normalises to E.164 with a leading "+267"; this
     * controller normalises sessions to 8-digit local. Translate so the
     * consent lookup hits.
     */
    private function consentLookupCellphone(string $localOrIntl): string
    {
        $digits = preg_replace('/\D/', '', $localOrIntl);
        if (strlen($digits) === 8) return '+267' . $digits;
        return '+' . ltrim($digits, '+');
    }

    /**
     * Every plausible string the consent row's `cellphone` column might
     * hold for the given phone. Covers both writers:
     *   • Public/ConsentController (3-step) → E.164 with "+267" prefix
     *   • V1/ConsentController (legacy 1-step) → 8-digit local, no plus
     * Plus a couple of historical variants we've seen in the wild.
     */
    private function consentLookupCandidates(string $localOrIntl): array
    {
        $digits = preg_replace('/\D/', '', $localOrIntl);
        $local  = strlen($digits) === 8 ? $digits : (str_starts_with($digits, '267') && strlen($digits) === 11 ? substr($digits, 3) : $digits);
        $intl   = strlen($local) === 8 ? '+267' . $local : '+' . ltrim($digits, '+');
        // De-dup in case $local === $digits and $intl already had no '+'.
        return array_values(array_unique([$intl, $local, $digits, '267' . $local]));
    }
}
