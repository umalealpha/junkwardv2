<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public consent capture for start.alphadirect.co.bw.
 *
 * Botswana customers often don't have WhatsApp on the same number they
 * use for the policy, and data is expensive — so we never default to
 * WhatsApp. This endpoint asks the customer once at the top of the
 * onboarding flow:
 *   "Do you have WhatsApp on this number? Should we send your OTP there?"
 *
 * The yes/no answer is stored on customer_contact_preferences keyed by
 * cellphone, with consent_at + consent_ip for audit. After capture the
 * OTP service uses these prefs to pick the channel chain.
 */
class ConsentController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    /**
     * POST /api/v1/public/consent/contact
     * Body: {
     *   cellphone:           "71234567",
     *   has_whatsapp:        true|false,
     *   whatsapp_consent:    true|false,   // explicit opt-in to receive OTP via WhatsApp
     *   sms_opt_in:          true|false,   // default true; customer can opt out
     *   email_opt_in:        true|false,
     *   preferred_email:     "..." (required if email_opt_in)
     *   preferred_channel:   "whatsapp"|"sms"|"email"|"auto"
     * }
     */
    public function contact(Request $request): JsonResponse
    {
        $request->validate([
            'cellphone'         => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'has_whatsapp'      => 'sometimes|boolean',
            'whatsapp_consent'  => 'sometimes|boolean',
            'sms_opt_in'        => 'sometimes|boolean',
            'email_opt_in'      => 'sometimes|boolean',
            'preferred_email'   => 'required_if:email_opt_in,true|nullable|email|max:160',
            'preferred_channel' => 'sometimes|string|in:whatsapp,sms,email,auto',
        ]);

        $result = $this->otp->recordPreferences(
            $request->input('cellphone'),
            $request->only(['has_whatsapp','whatsapp_consent','sms_opt_in','email_opt_in','preferred_email','preferred_channel']),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json($result, 200);
    }

    /**
     * POST /api/v1/public/consent/privacy
     *
     * Body: {
     *   cellphone, email?,
     *   accepted_terms: true,           // mandatory
     *   accepted_privacy: true,         // mandatory
     *   accepted_data_processing: true, // mandatory (KYC)
     *   accepted_marketing: bool,       // optional
     *   terms_version: "v1.0",
     *   privacy_version: "v1.0",
     *   source: "landing|motor|kyc|edit"
     * }
     *
     * Returns: { ok, id, accepted_at }
     *
     * Refuses (422) if any of the three mandatory boxes is false. The
     * customer can't proceed in the FE without them anyway, but the
     * server enforces it as a safety net so a tampered FE can't bypass.
     */
    public function privacy(Request $request): JsonResponse
    {
        // Post-OTP callers (e.g. vehicle inspection, which authenticates via
        // plate→OTP and only ever sees a masked '57****46' on the FE) supply
        // a Bearer token instead of an unmasked cellphone. Resolve it here
        // BEFORE validation so the cellphone-regex doesn't bounce a
        // legitimately-masked value. Pre-OTP callers (landing, motor quote)
        // still hit the strict regex below.
        $bearerCellphone = null;
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            $session = $this->otp->validateToken(trim($m[1]));
            if ($session) {
                $bearerCellphone = (string) ($session['cellphone'] ?? '');
                if ($bearerCellphone) {
                    // Override the request cellphone so validation + the
                    // DB row both see the real number, not the FE's mask.
                    $request->merge(['cellphone' => $bearerCellphone]);
                }
            }
        }

        $request->validate([
            'cellphone'                => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'email'                    => 'nullable|email|max:160',
            'accepted_terms'           => 'required|boolean',
            'accepted_privacy'         => 'required|boolean',
            'accepted_data_processing' => 'required|boolean',
            'accepted_marketing'       => 'sometimes|boolean',
            'terms_version'            => 'sometimes|string|max:32',
            'privacy_version'          => 'sometimes|string|max:32',
            'source'                   => 'sometimes|string|in:landing,motor,travel,kyc,edit,start',
            // Strong-evidence fields (agent-assisted sales). All optional —
            // kept on the same row so audit can pull a single record.
            'signature_data_url'       => 'nullable|string|max:500000', // ~500KB base64 PNG
            'geo_lat'                  => 'nullable|numeric|between:-90,90',
            'geo_lon'                  => 'nullable|numeric|between:-180,180',
            'geo_accuracy_m'           => 'nullable|numeric|min:0|max:100000',
            'user_agent'               => 'nullable|string|max:512',
        ]);

        $mandatoryAll = $request->boolean('accepted_terms')
                     && $request->boolean('accepted_privacy')
                     && $request->boolean('accepted_data_processing');
        if (!$mandatoryAll) {
            return response()->json([
                'ok'    => false,
                'error' => 'mandatory_consents_missing',
                'message' => 'You must accept the terms, privacy notice and data-processing consent to continue.',
            ], 422);
        }

        $cellphone = preg_replace('/\D/', '', $request->input('cellphone'));
        if (str_starts_with($cellphone, '267') && strlen($cellphone) === 11) $cellphone = substr($cellphone, 3);

        // Defensive: only write evidence columns that exist. New columns
        // are added by 2026_05_03_120000_add_consent_evidence_columns —
        // older deploys without the migration still record the base row.
        $payload = [
            'cellphone'                => $cellphone,
            'email'                    => $request->input('email'),
            'accepted_terms'           => true,
            'accepted_privacy'         => true,
            'accepted_data_processing' => true,
            'accepted_marketing'       => $request->boolean('accepted_marketing'),
            'terms_version'            => $request->input('terms_version', 'v1.0'),
            'privacy_version'          => $request->input('privacy_version', 'v1.0'),
            'source'                   => $request->input('source', 'landing'),
            'accepted_at'              => now(),
            'ip'                       => $request->ip(),
            'user_agent'               => substr((string) ($request->input('user_agent') ?? $request->userAgent()), 0, 512),
            'created_at'               => now(),
            'updated_at'               => now(),
        ];

        $evidence = [
            'signature_data_url' => $request->input('signature_data_url'),
            'geo_lat'            => $request->input('geo_lat'),
            'geo_lon'            => $request->input('geo_lon'),
            'geo_accuracy_m'     => $request->input('geo_accuracy_m'),
        ];
        foreach ($evidence as $col => $val) {
            if ($val !== null && \Illuminate\Support\Facades\Schema::hasColumn('customer_privacy_consents', $col)) {
                $payload[$col] = $val;
            }
        }

        // Stamp product_scope so PolicyCreate's `where('product_scope', 'retail')`
        // lookup sees this row. All sources this legacy endpoint accepts
        // (landing/motor/kyc/edit/start) are retail-product flows — without
        // this, the consent row is invisible to create-motor / create-bundle
        // and the customer gets a `consent_required` 403 right after
        // accepting the privacy notice. The 3-step OTP-bound /consents/accept
        // endpoint already stamps product_scope explicitly; this brings the
        // legacy single-step endpoint to parity.
        if (\Illuminate\Support\Facades\Schema::hasColumn('customer_privacy_consents', 'product_scope')) {
            $payload['product_scope'] = 'retail';
        }

        $id = \DB::table('customer_privacy_consents')->insertGetId($payload);

        \Illuminate\Support\Facades\Log::info('public_consent.privacy_recorded', [
            'id'        => $id,
            'cellphone' => substr(hash('sha256', $cellphone), 0, 8),
            'source'    => $request->input('source', 'landing'),
        ]);

        return response()->json([
            'ok'          => true,
            'id'          => $id,
            'accepted_at' => now()->toIso8601String(),
        ], 201);
    }

    /**
     * GET /api/v1/public/consent/contact?cellphone=...
     * Returns the stored preferences (or a default shape if none yet),
     * so the FE can pre-fill the consent toggle on a returning visit.
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'cellphone' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
        ]);
        $cellphone = preg_replace('/\D/', '', $request->input('cellphone'));
        if (str_starts_with($cellphone, '267') && strlen($cellphone) === 11) {
            $cellphone = substr($cellphone, 3);
        }

        $row = DB::table('customer_contact_preferences')
            ->where('cellphone', $cellphone)
            ->first();

        return response()->json([
            'cellphone'         => $cellphone,
            'has_whatsapp'      => (bool) ($row->has_whatsapp ?? false),
            'whatsapp_consent'  => (bool) ($row->whatsapp_consent ?? false),
            'sms_opt_in'        => (bool) ($row->sms_opt_in ?? true),
            'email_opt_in'      => (bool) ($row->email_opt_in ?? false),
            'preferred_email'   => $row->preferred_email ?? null,
            'preferred_channel' => $row->preferred_channel ?? 'auto',
            'consent_at'        => $row->consent_at ?? null,
        ]);
    }
}
