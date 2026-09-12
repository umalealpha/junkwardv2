<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\ClaimEvent;
use AlphaDirect\Events\SendMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 1 of claims automation: when a claim is registered, email the claimant
 * the correct claim form for the claim_type, and ask them to fill it in and
 * attach their documents (police report, etc.).
 *
 * Subscribed to ClaimEvent alongside the existing ClaimEventListener (see
 * EventServiceProvider). Only acts on 'claim_created'. Completely inert unless
 * the runtime toggle IntegrationSettings::isEnabled('claims_automation') is on
 * (Admin > Integrations) AND config('claims.auto_email_form') is true, so it
 * ships dark and is armed from the UI — no redeploy.
 *
 * Reuses the existing mail path: event(SendMail) -> SendMailFired -> Mailgun.
 * Never throws into the caller — a mail hiccup must not affect claim creation.
 */
class ClaimFormEmailListener
{
    public function handle(ClaimEvent $event): void
    {
        if ($event->type !== 'claim_created') {
            return;
        }
        // Master switch is the runtime toggle (Admin > Integrations), defaults
        // off; auto_email_form is a finer per-step config sub-switch (default on).
        if (!\AlphaDirect\Services\IntegrationSettings::isEnabled('claims_automation', false)
            || !config('claims.auto_email_form', true)) {
            return;
        }

        try {
            // `claims` is authoritative; fall back to `new_claims` (legacy).
            $claim = DB::table('claims')
                ->where('id', $event->claimId)
                ->first(['id', 'claim_number', 'claim_type', 'customer_id']);
            if (!$claim) {
                $claim = DB::table('new_claims')
                    ->where('id', $event->claimId)
                    ->first(['id', 'claim_number', 'claim_type', 'customer_id']);
            }
            if (!$claim) {
                return;
            }

            $email = $claim->customer_id
                ? DB::table('customer')->where('id', $claim->customer_id)->value('email')
                : null;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::info('[ClaimFormEmail] skipped — no valid claimant email', [
                    'claim_id' => $claim->id, 'claim_number' => $claim->claim_number,
                ]);
                return;
            }

            // Match the mapped form by claim_type (case-insensitive), active only.
            $form = DB::table('claim_type_forms')
                ->whereRaw('LOWER(claim_type) = ?', [strtolower((string) $claim->claim_type)])
                ->where('active', 1)
                ->first();
            if (!$form) {
                Log::info('[ClaimFormEmail] skipped — no active form configured', [
                    'claim_id' => $claim->id, 'claim_type' => $claim->claim_type,
                ]);
                return;
            }

            // A row with no form_url has nothing to send. Without this, an active
            // row still produced an email whose entire content was "a member of
            // our claims team will send you the correct form shortly" — worse than
            // silence, and sent to every new claimant of that type.
            //
            // This matters because `active` is a SHARED switch: the claim-form
            // send button's seeder also writes these rows, so arming them for the
            // button would otherwise arm content-free auto-emails here too.
            if (trim((string) ($form->form_url ?? '')) === '') {
                Log::info('[ClaimFormEmail] skipped — form row has no link yet', [
                    'claim_id' => $claim->id, 'claim_type' => $claim->claim_type,
                ]);
                return;
            }

            $subject = "Your Alpha Direct claim {$claim->claim_number} — {$form->form_title}";
            $html    = $this->buildHtml($claim, $form);

            event(new SendMail($email, $subject, '', $html, '', [
                'hook'         => 'claim_form',
                'claim_id'     => $claim->id,
                'claim_number' => $claim->claim_number,
            ]));

            Log::info('[ClaimFormEmail] sent', [
                'claim_id' => $claim->id, 'claim_number' => $claim->claim_number, 'type' => $claim->claim_type,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ClaimFormEmail] failed', [
                'claim_id' => $event->claimId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Plain, warm, branded HTML (navy #0D1B2A + orange #F4A623). Client-facing,
     * so simple language only — no jargon.
     */
    private function buildHtml($claim, $form): string
    {
        $formTitle = htmlspecialchars((string) $form->form_title, ENT_QUOTES);
        $claimNo   = htmlspecialchars((string) $claim->claim_number, ENT_QUOTES);
        $url       = trim((string) ($form->form_url ?? ''));
        $extra     = trim((string) ($form->instructions ?? ''));

        $formBlock = $url !== ''
            ? '<p style="margin:0 0 14px;">Please open your claim form here, fill it in, and send it back to us with your documents:</p>'
              . '<p style="margin:0 0 18px;"><a href="' . htmlspecialchars($url, ENT_QUOTES)
              . '" style="background:#F4A623;color:#0D1B2A;text-decoration:none;font-weight:bold;padding:10px 18px;border-radius:6px;display:inline-block;">Open my claim form</a></p>'
            : '<p style="margin:0 0 18px;">A member of our claims team will send you the correct claim form shortly.</p>';

        $extraBlock = $extra !== ''
            ? '<p style="margin:0 0 14px;">' . nl2br(htmlspecialchars($extra, ENT_QUOTES)) . '</p>'
            : '';

        return '<div style="font-family:Arial,Helvetica,sans-serif;color:#0D1B2A;max-width:640px;margin:0 auto;line-height:1.55;">'
            . '<div style="background:#0D1B2A;padding:16px 22px;border-radius:8px 8px 0 0;">'
            . '<span style="color:#F4A623;font-size:17px;font-weight:bold;">' . $formTitle . '</span></div>'
            . '<div style="border:1px solid #e4e7ec;border-top:none;padding:22px;border-radius:0 0 8px 8px;background:#fff;">'
            . '<p style="margin:0 0 14px;">Good day,</p>'
            . '<p style="margin:0 0 14px;">Thank you for letting us know about your claim. We have registered it under reference <strong>' . $claimNo . '</strong>.</p>'
            . $formBlock
            . '<p style="margin:0 0 14px;">Please also attach your police report and any other documents that support your claim.</p>'
            . $extraBlock
            . '<p style="margin:0 0 14px;">Once we have your form and documents, we will take it from there and keep you updated.</p>'
            . '<p style="margin:0;">Kindly note this reference number ' . $claimNo . ' in any reply.</p>'
            . '</div>'
            . '<div style="text-align:center;color:#9ca3af;font-size:11px;padding:10px;">Alpha Direct Insurance</div></div>';
    }
}
