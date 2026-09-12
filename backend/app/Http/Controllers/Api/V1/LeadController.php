<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Mail\UnderwritingReferralMail;
use AlphaDirect\Models\AppSetting;
use AlphaDirect\Models\PublicLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * Public lead capture endpoints for start.alphadirect.co.bw.
 *
 * Replaces the legacy graphite saveLead + alphaFEV2 ReportissueController
 * (which used to POST to devgraphite.alphadirect.co.bw/support/api/...).
 *
 * Hardening over the legacy implementation:
 *  - Server-side validation (the legacy version trusted whatever Blade sent)
 *  - Throttle middleware (5/min per IP) to keep bots/spam out
 *  - IP + UA captured for forensics
 *  - Status enum so ops can triage in the admin
 *  - Auditable via OwenIt — every status change is logged
 *  - No third-party reCAPTCHA roundtrip (the legacy hard-coded a key in
 *    the controller). Optionally re-enable via env('RECAPTCHA_SECRET').
 */
class LeadController extends Controller
{
    /**
     * POST /api/v1/public/leads/callback
     *
     * Body: { fullName, cellphone, email?, product?, message?, vehicleDetails?,
     *         sumInsured?, quoteReference?, notifyUnderwriting? }
     * Returns: { ok: true, id }  or  422 with field errors
     *
     * `notifyUnderwriting` is set by the Motor Comprehensive high-value
     * (>P500k) callback card — it routes the lead to underwriting@alphadirect.co.bw
     * in addition to the usual triage queue, since that vehicle can't be
     * quoted through the standard MIS flow.
     */
    public function submitCallback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fullName'           => 'required|string|max:120',
            'cellphone'          => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'email'              => 'nullable|email|max:160',
            'product'            => 'nullable|string|max:120',
            'message'            => 'nullable|string|max:2000',
            'vehicleDetails'     => 'nullable|string|max:255',
            'sumInsured'         => 'nullable|numeric|min:0',
            'quoteReference'     => 'nullable|string|max:64',
            'notifyUnderwriting' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'     => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = PublicLead::create([
            'kind'            => PublicLead::KIND_CALLBACK,
            'full_name'       => $request->input('fullName'),
            'cellphone'       => $request->input('cellphone'),
            'email'           => $request->input('email'),
            'product'         => $request->input('product'),
            'message'         => $request->input('message'),
            'vehicle_details' => $request->input('vehicleDetails'),
            'sum_insured'     => $request->input('sumInsured'),
            'quote_reference' => $request->input('quoteReference'),
            'status'          => PublicLead::STATUS_NEW,
            'ip'              => $request->ip(),
            'user_agent'      => substr((string) $request->userAgent(), 0, 500),
            'source'          => 'start_fe',
        ]);

        Log::info('public_lead.callback.created', ['id' => $lead->id]);

        if ($request->boolean('notifyUnderwriting')) {
            $underwritingEmail = AppSetting::get('underwriting_referral_email', 'underwriting@alphadirect.co.bw');
            $underwritingCc    = AppSetting::get('underwriting_referral_cc');
            $mail = Mail::to($underwritingEmail);
            if ($underwritingCc) {
                $mail->cc($underwritingCc);
            }
            $mail->send(new UnderwritingReferralMail($lead));
            $lead->forceFill(['underwriting_notified_at' => now()])->save();
            Log::info('public_lead.callback.underwriting_notified', [
                'id' => $lead->id, 'to' => $underwritingEmail, 'cc' => $underwritingCc,
            ]);
        }

        // TODO: dispatch SendCallbackNotificationJob once the notification
        // pipeline is wired (Slack / WhatsApp). For now ops watches the
        // admin queue.

        return response()->json(['ok' => true, 'id' => $lead->id], 201);
    }

    /**
     * POST /api/v1/public/leads/issue
     *
     * Body: { fullName, cellphone, email?, policyNumber?, category, message }
     * Returns: { ok: true, id }  or  422 with field errors
     */
    public function reportIssue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fullName'     => 'required|string|max:120',
            'cellphone'    => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'email'        => 'nullable|email|max:160',
            'policyNumber' => 'nullable|string|max:64',
            'category'     => 'required|string|in:site_bug,payment_issue,claims_question,policy_question,other',
            'message'      => 'required|string|min:10|max:4000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'     => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = PublicLead::create([
            'kind'          => PublicLead::KIND_ISSUE,
            'full_name'     => $request->input('fullName'),
            'cellphone'     => $request->input('cellphone'),
            'email'         => $request->input('email'),
            'policy_number' => $request->input('policyNumber'),
            'category'      => $request->input('category'),
            'message'       => $request->input('message'),
            'status'        => PublicLead::STATUS_NEW,
            'ip'            => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 500),
            'source'        => 'start_fe',
        ]);

        Log::info('public_lead.issue.created', [
            'id'       => $lead->id,
            'category' => $lead->category,
        ]);

        return response()->json(['ok' => true, 'id' => $lead->id], 201);
    }
}
