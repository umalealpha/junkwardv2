<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public OTP endpoints for start.alphadirect.co.bw flows.
 *
 * Replaces legacy FrontendPay/CustomerController::sendOTPPolicy +
 * authenticateOTP. See PublicOtpService for the security model
 * (hashed codes, per-purpose binding, brute-force lockout).
 *
 * Routes:
 *   POST /api/v1/public/otp/customer/send    body: { identifier, purpose? }
 *   POST /api/v1/public/otp/customer/verify  body: { identifier, code, purpose? }
 *
 * `identifier` is the customer's cellphone (8-digit BW number — the +267
 * prefix is added by the service). `purpose` defaults to customer_auth.
 */
class OtpController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'purpose'    => 'nullable|string|in:' . implode(',', PublicOtpService::PURPOSES),
            'channels'   => 'nullable|array',
            'channels.*' => 'string|in:' . implode(',', PublicOtpService::CHANNELS),
            'email'      => 'nullable|email|max:160',
            // `source` = the functionality that triggered this OTP (e.g.
            // 'legal_insurance_purchase'). The backend maps it to a stored
            // reason via OtpReason; unknown/missing falls back to purpose.
            // `reason_note` carries short dynamic detail (e.g. 'P49/month').
            'source'      => 'nullable|string|max:64',
            'reason_note' => 'nullable|string|max:60',
        ]);

        $result = $this->otp->send(
            $request->input('identifier'),
            $request->input('purpose', 'customer_auth'),
            $request->ip(),
            $request->userAgent(),
            [
                'channels'    => $request->input('channels'),
                'email'       => $request->input('email'),
                'source'      => $request->input('source'),
                'reason_note' => $request->input('reason_note'),
            ],
        );

        $status = ($result['ok'] ?? false) ? 200 : 429;
        return response()->json($result, $status);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'code'       => 'required|string|regex:/^\d{4,6}$/',
            'purpose'    => 'nullable|string|in:' . implode(',', PublicOtpService::PURPOSES),
        ]);

        $result = $this->otp->verify(
            $request->input('identifier'),
            $request->input('purpose', 'customer_auth'),
            $request->input('code'),
            $request->ip(),
        );

        $status = ($result['ok'] ?? false) ? 200 : 401;
        return response()->json($result, $status);
    }

    /**
     * Send a one-tap verification link to the customer instead of a
     * 6-digit code. The link arrives via the same SMS / WhatsApp /
     * email chain — customer taps once, browser opens /v/{token},
     * server consumes the token and mints the session.
     *
     * POST /api/v1/public/otp/customer/magic-link
     * Body: { identifier, purpose?, channels[]?, email? }
     */
    public function sendMagicLink(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'purpose'    => 'nullable|string|in:' . implode(',', PublicOtpService::PURPOSES),
            'channels'   => 'nullable|array',
            'channels.*' => 'string|in:' . implode(',', PublicOtpService::CHANNELS),
            'email'      => 'nullable|email|max:160',
            'source'      => 'nullable|string|max:64',
            'reason_note' => 'nullable|string|max:60',
        ]);

        // Where the customer should be redirected after tapping. Comes
        // from the SPA at request time so the same backend can serve
        // localhost (dev), staging and prod without rebuilds.
        $linkBase = config('app.start_fe_url')
                  ?? env('START_SPA_URL', 'https://start.alphadirect.co.bw');

        $result = $this->otp->sendMagicLink(
            $request->input('identifier'),
            $request->input('purpose', 'customer_auth'),
            $linkBase,
            $request->ip(),
            $request->userAgent(),
            [
                'channels'    => $request->input('channels'),
                'email'       => $request->input('email'),
                'source'      => $request->input('source'),
                'reason_note' => $request->input('reason_note'),
            ],
        );

        $status = ($result['ok'] ?? false) ? 200 : 429;
        return response()->json($result, $status);
    }

    /**
     * Verify a magic-link token. Public, GET-by-design (the URL is
     * the credential and clicking the link IS the action).
     *
     * GET /api/v1/public/otp/customer/magic-link/verify?t=...
     * On success: { ok, token, cellphone, purpose, expires_in }
     */
    public function verifyMagicLink(Request $request): JsonResponse
    {
        $request->validate([
            't' => 'required|string|min:20|max:64',
        ]);

        $result = $this->otp->verifyMagicLink(
            $request->input('t'),
            $request->ip(),
        );

        $status = ($result['ok'] ?? false) ? 200 : 410; // 410 Gone = link consumed/expired
        return response()->json($result, $status);
    }

    // ─── Plate-based OTP (vehicle inspection) ──────────────────────────────
    // The depot staff has the car in front of them and types the plate.
    // We resolve plate → vehicle.policy_id → policy.customer_id →
    // customer.cellphone server-side so the real number never crosses to
    // the FE. The FE only sees a masked cellphone for confirmation.

    /** POST /api/v1/public/otp/customer/send-for-plate { plate, purpose? } */
    public function sendForPlate(Request $request): JsonResponse
    {
        $request->validate([
            'plate'   => ['required', 'string', 'regex:/^[A-Z0-9 \-]{3,15}$/i'],
            'purpose' => 'nullable|string|in:' . implode(',', PublicOtpService::PURPOSES),
        ]);

        $cellphone = $this->cellphoneForPlate($request->input('plate'));
        if (!$cellphone) {
            return response()->json(['ok' => false, 'error' => 'plate_not_found'], 404);
        }

        $result = $this->otp->send(
            $cellphone,
            $request->input('purpose', 'vehicle_inspect'),
            $request->ip(),
            $request->userAgent(),
            ['source' => 'vehicle_inspection'],
        );

        $status = ($result['ok'] ?? false) ? 200 : 429;
        return response()->json($result, $status);
    }

    /** POST /api/v1/public/otp/customer/verify-for-plate { plate, code, purpose? } */
    public function verifyForPlate(Request $request): JsonResponse
    {
        $request->validate([
            'plate'   => ['required', 'string', 'regex:/^[A-Z0-9 \-]{3,15}$/i'],
            'code'    => 'required|string|regex:/^\d{4,6}$/',
            'purpose' => 'nullable|string|in:' . implode(',', PublicOtpService::PURPOSES),
        ]);

        $cellphone = $this->cellphoneForPlate($request->input('plate'));
        if (!$cellphone) {
            return response()->json(['ok' => false, 'error' => 'plate_not_found'], 404);
        }

        $result = $this->otp->verify(
            $cellphone,
            $request->input('purpose', 'vehicle_inspect'),
            $request->input('code'),
            $request->ip(),
        );
        if (($result['ok'] ?? false) === true) {
            // Surface policy + holder context so the FE can render a
            // "You're inspecting X" confirmation card without a second
            // detail round-trip. Re-fetch by plate (cheap, indexed).
            $plate = strtoupper(preg_replace('/\s+/', '', $request->input('plate')));
            $vehicle = \AlphaDirect\Vehicle::where('vehiclePlate', $plate)->first();
            if ($vehicle) {
                $policy = \AlphaDirect\Policy::with('customer:id,firstName,lastName')
                    ->where('id', $vehicle->policy_id)->first();
                if ($policy) {
                    $result['policy_number'] = $policy->policyNumber;
                    $result['plate']         = $plate;
                    $result['holder']        = trim(
                        ($policy->customer->firstName ?? '') . ' ' . ($policy->customer->lastName ?? '')
                    );
                }
            }
        }

        $status = ($result['ok'] ?? false) ? 200 : 401;
        return response()->json($result, $status);
    }

    /** Resolve a vehicle plate to the owning customer's cellphone. */
    private function cellphoneForPlate(string $plate): ?string
    {
        $plate = strtoupper(preg_replace('/\s+/', '', $plate));
        $vehicle = \AlphaDirect\Vehicle::where('vehiclePlate', $plate)->first();
        if (!$vehicle) return null;
        $policy = \AlphaDirect\Policy::with('customer:id,cellphone')
            ->where('id', $vehicle->policy_id)
            ->first();
        return $policy && $policy->customer ? (string) $policy->customer->cellphone : null;
    }
}
