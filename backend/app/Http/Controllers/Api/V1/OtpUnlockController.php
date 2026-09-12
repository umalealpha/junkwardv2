<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Helpers\OtpUnlock;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Call-centre OTP unlock — POPIA / Botswana DPA design (2026-06-22).
 *
 * A non-privileged agent sends an OTP to the customer's registered mobile; the
 * customer reads it back on the call; the agent enters it and the policy is
 * unlocked for that agent's session (Cache-backed — see OtpUnlock). Admins and
 * Managers (AuthGate::ADMIN_ROLES) are always unlocked and never need an OTP.
 * Every successful unlock is written to the activity log for DPO audit.
 *
 * Reuses the hardened PublicOtpService (WhatsApp->SMS->email via Infobip,
 * hashed codes, 60s cooldown, 5-attempt lockout). Purpose: 'agent_unlock'.
 *
 * NOTE (for review): these endpoints only manage the unlock STATE. Gating the
 * sensitive action endpoints and revealing the policy on screen are separate,
 * deliberately-deferred steps — see the build spec; the exact agent-side action
 * endpoints must be confirmed (some are customer self-service routes) before
 * any gate is wired.
 */
class OtpUnlockController extends \AlphaDirect\Http\Controllers\Controller
{
    public function __construct(private PublicOtpService $otp) {}

    /** Is this policy currently unlocked for the calling agent? */
    public function status($id)
    {
        return response()->json(['unlocked' => OtpUnlock::active((int) $id)]);
    }

    /** Send an OTP to the policy's customer. No-op for privileged users. */
    public function send(Request $request, $id)
    {
        if (OtpUnlock::privileged()) {
            return response()->json(['ok' => true, 'unlocked' => true, 'privileged' => true]);
        }
        $cell = $this->customerCell($id);
        if ($cell === null) {
            return response()->json(['ok' => false, 'error' => 'no_customer_cellphone'], 422);
        }
        $result = $this->otp->send($cell, OtpUnlock::OTP_PURPOSE, $request->ip(), (string) $request->userAgent(), ['source' => 'agent_unlock']);
        return response()->json($result, ($result['ok'] ?? false) ? 200 : 429);
    }

    /** Verify the relayed OTP; on success unlock the policy + log it. */
    public function verify(Request $request, $id)
    {
        $request->validate(['code' => 'required|string|regex:/^\d{4,6}$/']);
        if (OtpUnlock::privileged()) {
            return response()->json(['ok' => true, 'unlocked' => true, 'privileged' => true]);
        }
        $policy = DB::table('policies')->where('id', $id)->select('id', 'customer_id', 'policyNumber')->first();
        if (!$policy) {
            return response()->json(['ok' => false, 'error' => 'policy_not_found'], 422);
        }
        $cell = DB::table('customer')->where('id', $policy->customer_id)->value('cellphone');
        if (!$cell) {
            return response()->json(['ok' => false, 'error' => 'no_customer_cellphone'], 422);
        }
        $result = $this->otp->verify((string) $cell, OtpUnlock::OTP_PURPOSE, (string) $request->input('code'), $request->ip());
        if ($result['ok'] ?? false) {
            OtpUnlock::markUnlocked((int) $policy->id);
            // Reveal-action audit (POPIA): who unlocked which customer, when.
            activity('OTP Unlock')
                ->causedBy(Auth::user())
                ->withProperties([
                    'agent_id'    => Auth::id(),
                    'policy_id'   => (int) $policy->id,
                    'customer_id' => (int) $policy->customer_id,
                    'action'      => 'policy_unlock',
                ])
                ->log('Call-centre agent OTP-unlocked policy ' . $policy->policyNumber);
        }
        return response()->json($result, ($result['ok'] ?? false) ? 200 : 401);
    }

    /** Resolve the policy's customer cellphone via direct query (no relation dependency). */
    private function customerCell($policyId): ?string
    {
        $policy = DB::table('policies')->where('id', $policyId)->select('customer_id')->first();
        if (!$policy) {
            return null;
        }
        $cell = DB::table('customer')->where('id', $policy->customer_id)->value('cellphone');
        return $cell ? (string) $cell : null;
    }
}
