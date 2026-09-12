<?php

namespace AlphaDirect\Http\Controllers\Api\Public;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Public consent capture flow — OTP-gated, P0/event, NBFIRA + DPA 2024 + ECTA 2014 compliant.
 *
 * Three endpoints, three moments captured:
 *   POST /api/public/v1/consents/start    → OTP dispatched (channel: sms+whatsapp+voice fallback)
 *   POST /api/public/v1/consents/verify   → customer enters OTP — IDENTITY-VERIFIED here
 *   POST /api/public/v1/consents/accept   → customer accepts terms, full row persisted
 *
 * Audit-grade fields captured per consent:
 *   - 3 timestamps: otp_sent_at, otp_verified_at, accepted_at
 *   - 2 derived gaps: sec_send_to_verify, sec_verify_to_accept (bot-detection signal)
 *   - identity: cellphone, otp_id, otp_channel, otp_attempts
 *   - context: ip, user_agent, signature_data_url, geo_lat/lon/accuracy
 *   - scope: product_scope, policy_id
 *   - integrity: evidence_hash chain (tamper-evident)
 */
class ConsentController extends Controller
{
    private const VERIFY_SESSION_TTL_MIN = 15;
    private const OTP_TTL_MIN = 5;

    /**
     * STEP 1 — issue OTP, log into public_otps.
     * Body: { cellphone }
     * Returns: { otp_session_id, expires_at }
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cellphone' => 'required|string|max:24',
        ]);
        $cell = self::normaliseCellphone($validated['cellphone']);

        // Generate OTP code (6-digit numeric).
        $code     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = hash('sha256', $code);

        $otpId = DB::connection('mysql_system')->table('public_otps')->insertGetId([
            'cellphone'        => $cell,
            'purpose'          => 'customer_auth',
            // Why this OTP was sent (audit). This flow's functionality is
            // consent capture; resolved server-side via OtpReason.
            'reason'           => \AlphaDirect\Services\OtpReason::resolve('consent_capture', 'customer_auth'),
            'code_hash'        => $codeHash,
            'expires_at'       => now()->addMinutes(self::OTP_TTL_MIN),
            'attempts'         => 0,
            'ip'               => $request->ip(),
            'user_agent'       => substr((string) $request->userAgent(), 0, 255),
            'attempted_channels' => json_encode(['whatsapp', 'sms', 'voice']),
            'delivery_status'  => 'queued',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Dispatch via existing OTP delivery service (handles WhatsApp template
        // fallback to SMS fallback to voice). Reuses approved channels — P0
        // for WhatsApp, P0.20 for SMS, only used if WhatsApp fails.
        try {
            \AlphaDirect\Services\PublicOtpService::dispatch($otpId, $cell, $code);
        } catch (\Throwable $e) {
            Log::error('Consent OTP dispatch failed: ' . $e->getMessage(), ['otp_id' => $otpId]);
            return response()->json(['error' => 'otp_dispatch_failed'], 503);
        }

        return response()->json([
            'otp_session_id' => $otpId,
            'expires_at'     => now()->addMinutes(self::OTP_TTL_MIN)->toIso8601String(),
            'channels'       => ['whatsapp', 'sms', 'voice'],
        ]);
    }

    /**
     * STEP 2 — verify OTP, return short-lived session token.
     * Body: { otp_session_id, code }
     * Returns: { auth_session_id, otp_verified_at }
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp_session_id' => 'required|integer',
            'code'           => 'required|string|min:4|max:8',
        ]);

        $otp = DB::connection('mysql_system')->table('public_otps')->where('id', $validated['otp_session_id'])->first();
        if (!$otp) {
            return response()->json(['error' => 'otp_not_found'], 404);
        }
        if ($otp->consumed_at) {
            return response()->json(['error' => 'otp_already_used'], 409);
        }
        if (now()->greaterThan($otp->expires_at)) {
            return response()->json(['error' => 'otp_expired'], 410);
        }

        // Increment attempts BEFORE the hash check (covers brute-force).
        DB::connection('mysql_system')->table('public_otps')->where('id', $otp->id)->increment('attempts', 1, ['updated_at' => now()]);
        if ($otp->attempts >= 5) {
            return response()->json(['error' => 'otp_locked'], 429);
        }

        if (!hash_equals($otp->code_hash, hash('sha256', $validated['code']))) {
            return response()->json(['error' => 'otp_wrong'], 422);
        }

        // Success — mark consumed, mint a session token (consent_id presented later at /accept).
        $authSessionId = Str::random(64);
        $verifiedAt    = now();
        DB::connection('mysql_system')->table('public_otps')->where('id', $otp->id)->update([
            'consumed_at' => $verifiedAt,
            'updated_at'  => $verifiedAt,
        ]);

        // Cache the auth_session_id → otp_id binding for /accept step (15 min TTL).
        // Using DB so it survives container restart; small table, low contention.
        DB::table('public_session_tokens')->insert([
            'token_hash' => hash('sha256', $authSessionId),
            'cellphone'  => $otp->cellphone,
            'purpose'    => 'consent_capture',
            'expires_at' => now()->addMinutes(self::VERIFY_SESSION_TTL_MIN),
            'ip'         => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'auth_session_id'  => $authSessionId,
            'otp_verified_at'  => $verifiedAt->toIso8601String(),
            'cellphone'        => $otp->cellphone,
            'session_expires_at' => now()->addMinutes(self::VERIFY_SESSION_TTL_MIN)->toIso8601String(),
        ]);
    }

    /**
     * STEP 3 — customer accepts terms, full audit row written.
     * Body: { auth_session_id, accepted_terms, accepted_privacy, accepted_data_processing,
     *         accepted_marketing, terms_version, privacy_version, product_scope, policy_id?,
     *         email?, signature_data_url?, geo_lat?, geo_lon?, geo_accuracy_m? }
     * Returns: { consent_id, evidence_hash, accepted_at }
     */
    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auth_session_id'           => 'required|string|size:64',
            'accepted_terms'            => 'required|boolean|accepted',
            'accepted_privacy'          => 'required|boolean|accepted',
            'accepted_data_processing'  => 'required|boolean|accepted',
            'accepted_marketing'        => 'required|boolean',
            'terms_version'             => 'required|string|max:32',
            'privacy_version'           => 'required|string|max:32',
            'product_scope'             => 'required|string|in:retail,domcom,engineering,specialist',
            'policy_id'                 => 'nullable|integer',
            'email'                     => 'nullable|email|max:160',
            'signature_data_url'        => 'nullable|string',
            'geo_lat'                   => 'nullable|numeric|between:-90,90',
            'geo_lon'                   => 'nullable|numeric|between:-180,180',
            'geo_accuracy_m'            => 'nullable|integer|min:0|max:100000',
        ]);

        // Resolve auth session → consumed OTP record.
        $tokenHash = hash('sha256', $validated['auth_session_id']);
        $session   = DB::table('public_session_tokens')
            ->where('token_hash', $tokenHash)
            ->where('purpose', 'consent_capture')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if (!$session) {
            return response()->json(['error' => 'session_invalid_or_expired'], 401);
        }

        // Find the consumed OTP for this cellphone (most recent).
        $otp = DB::connection('mysql_system')->table('public_otps')
            ->where('cellphone', $session->cellphone)
            ->where('purpose', 'customer_auth')
            ->whereNotNull('consumed_at')
            ->orderByDesc('consumed_at')
            ->first();
        if (!$otp) {
            return response()->json(['error' => 'no_verified_otp'], 422);
        }

        $acceptedAt        = now();
        $secSendToVerify   = (int) (strtotime((string) $otp->consumed_at) - strtotime((string) $otp->created_at));
        $secVerifyToAccept = (int) ($acceptedAt->getTimestamp() - strtotime((string) $otp->consumed_at));

        // Evidence hash chain — pulls previous_hash from latest consent for this customer.
        $prevHash = DB::table('customer_privacy_consents')
            ->where('cellphone', $session->cellphone)
            ->orderByDesc('id')
            ->value('evidence_hash');

        $payloadForHash = json_encode([
            'cellphone'         => $session->cellphone,
            'accepted_at'       => $acceptedAt->toIso8601String(),
            'terms_version'     => $validated['terms_version'],
            'privacy_version'   => $validated['privacy_version'],
            'otp_id'            => $otp->id,
            'otp_verified_at'   => (string) $otp->consumed_at,
            'ip'                => $request->ip(),
            'user_agent'        => substr((string) $request->userAgent(), 0, 255),
            'product_scope'     => $validated['product_scope'],
            'policy_id'         => $validated['policy_id'] ?? null,
            'previous_hash'     => $prevHash,
        ]);
        $evidenceHash = hash('sha256', $payloadForHash);

        $consentId = DB::table('customer_privacy_consents')->insertGetId([
            'cellphone'                 => $session->cellphone,
            'email'                     => $validated['email'] ?? null,
            'accepted_terms'            => 1,
            'accepted_privacy'          => 1,
            'accepted_data_processing'  => 1,
            'accepted_marketing'        => $validated['accepted_marketing'] ? 1 : 0,
            'terms_version'             => $validated['terms_version'],
            'privacy_version'           => $validated['privacy_version'],
            'source'                    => 'start_fe',
            'accepted_at'               => $acceptedAt,
            'ip'                        => $request->ip(),
            'user_agent'                => substr((string) $request->userAgent(), 0, 255),
            // OTP-bound captures
            'otp_id'                    => $otp->id,
            'otp_sent_at'               => $otp->created_at,
            'otp_verified_at'           => $otp->consumed_at,
            'otp_channel'               => $otp->delivered_via,
            'otp_attempts'              => $otp->attempts,
            'auth_session_id'           => $tokenHash, // store hash, not raw
            // Derived time gaps (denormalised for fast filtering)
            'sec_send_to_verify'        => max(0, $secSendToVerify),
            'sec_verify_to_accept'      => max(0, $secVerifyToAccept),
            // Tamper evidence
            'evidence_hash'             => $evidenceHash,
            'previous_hash'             => $prevHash,
            // Optional captures
            'signature_data_url'        => $validated['signature_data_url'] ?? null,
            'geo_lat'                   => $validated['geo_lat'] ?? null,
            'geo_lon'                   => $validated['geo_lon'] ?? null,
            'geo_accuracy_m'            => $validated['geo_accuracy_m'] ?? null,
            // Scope + linkage
            'product_scope'             => $validated['product_scope'],
            'policy_id'                 => $validated['policy_id'] ?? null,
            'created_at'                => $acceptedAt,
            'updated_at'                => $acceptedAt,
        ]);

        // Revoke session token to prevent replay.
        DB::table('public_session_tokens')
            ->where('id', $session->id)
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        Log::info('Consent recorded', [
            'consent_id' => $consentId,
            'cellphone'  => self::redactCellphone($session->cellphone),
            'scope'      => $validated['product_scope'],
            'gap_verify_to_accept_s' => $secVerifyToAccept,
        ]);

        return response()->json([
            'consent_id'      => $consentId,
            'evidence_hash'   => $evidenceHash,
            'accepted_at'     => $acceptedAt->toIso8601String(),
            'otp_sent_at'     => (string) $otp->created_at,
            'otp_verified_at' => (string) $otp->consumed_at,
        ]);
    }

    /**
     * Customer-initiated consent withdrawal.
     *
     * POST /api/v1/customers/me/consents/{id}/revoke
     * Auth: Bearer session token (any purpose, must match the consent's cellphone)
     * Body: { reason? }
     *
     * Revokes by setting revoked_at + revoke_reason. Append-only — the
     * original row stays for audit. The policy gate then refuses the
     * customer's next purchase attempt until they re-consent.
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Bearer session — same shape as the rest of the public flow.
        $auth = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['error' => 'auth_required'], 401);
        }
        $bearer = trim($m[1]);
        $session = app(\AlphaDirect\Services\PublicOtpService::class)->validateToken($bearer);
        if (!$session) {
            return response()->json(['error' => 'session_invalid_or_expired'], 401);
        }

        $consent = DB::table('customer_privacy_consents')->where('id', $id)->first();
        if (!$consent) {
            return response()->json(['error' => 'consent_not_found'], 404);
        }

        // Cellphone match — store consents in E.164, sessions in 8-digit
        // local form. Normalise both sides before compare.
        $sessionCell = self::normaliseCellphone((string) ($session['cellphone'] ?? ''));
        $consentCell = self::normaliseCellphone((string) $consent->cellphone);
        if ($sessionCell !== $consentCell) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        if ($consent->revoked_at) {
            return response()->json(['ok' => true, 'already_revoked' => true]);
        }

        DB::table('customer_privacy_consents')->where('id', $id)->update([
            'revoked_at'    => now(),
            'revoke_reason' => $request->input('reason'),
            'updated_at'    => now(),
        ]);

        Log::info('Consent revoked', [
            'consent_id' => $id,
            'cellphone'  => self::redactCellphone($consentCell),
            'reason'     => $request->input('reason'),
        ]);

        return response()->json([
            'ok'         => true,
            'consent_id' => $id,
            'revoked_at' => now()->toIso8601String(),
        ]);
    }

    private static function normaliseCellphone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        // Botswana national → +267 prefix if 8-digit local.
        if (strlen($digits) === 8 && str_starts_with($digits, '7')) {
            return '+267' . $digits;
        }
        return '+' . ltrim($digits, '+');
    }

    private static function redactCellphone(string $cell): string
    {
        return substr($cell, 0, 4) . '****' . substr($cell, -3);
    }
}
