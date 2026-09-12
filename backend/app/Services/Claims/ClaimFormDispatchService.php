<?php

namespace AlphaDirect\Services\Claims;

use AlphaDirect\Events\SendMail;
use AlphaDirect\Models\ClaimAccessLink;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ClaimFormDispatchService — the handler presses one button on a claim and the
 * claimant is emailed the right claim form: a PRE-FILLED PDF plus a link they
 * can open with no password and complete online.
 *
 * WHY A BUTTON AND NOT AN AUTOMATIC SEND (CFO, 11-Aug-2026): a human confirms
 * the form and the address. That is what makes this safe to ship now — the
 * biggest claim type in the book, `Accident` (1,208 claims, 41%), cannot be
 * mapped to motor-or-not from data (`ClaimSlaService::resolveClass` defaults
 * everything unmatched to non_motor, and `is_motor_claim` reads 0 on every glass
 * claim), so a machine choosing the form would send the wrong one to four
 * claimants in ten. A person choosing removes the guess entirely.
 *
 * Dark until the `claims_form_dispatch` runtime flag is armed
 * (Admin > Integrations). Never throws into the caller.
 */
class ClaimFormDispatchService
{
    public const FLAG = 'claims_form_dispatch';

    /** Link lifetime for a form-fill link. Matches the tracker's 90 days. */
    public const LINK_TTL_DAYS = 90;

    public function __construct(private ClaimFormPrefill $prefill)
    {
    }

    public static function enabled(): bool
    {
        return IntegrationSettings::isEnabled(self::FLAG, (bool) config('claims.form_dispatch_enabled', false));
    }

    /**
     * What the handler sees before pressing send: the form we suggest, every
     * form they can pick instead, and the address we hold.
     *
     * The suggestion is only ever a SUGGESTION — the response says so, and the
     * UI must let the handler change it without friction.
     */
    public function options(int $claimId): array
    {
        // Also checked in the controller. Repeated here because this service is
        // callable from a job or console command that does not pass through HTTP,
        // and a dark feature must not leak the form list or a customer's email.
        if (!self::enabled()) {
            return ['ok' => false, 'reason' => 'feature_off'];
        }

        $claim = $this->findClaim($claimId);
        if (!$claim) {
            return ['ok' => false, 'reason' => 'claim_not_found'];
        }

        $forms = DB::table('claim_type_forms')
            ->where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('form_title')
            ->get(['id', 'claim_type', 'form_title', 'template_key']);

        $suggested = $this->suggestForm((string) ($claim->claim_type ?? ''), $forms);
        $email     = $claim->customer_id
            ? DB::table('customer')->where('id', $claim->customer_id)->value('email')
            : null;

        return [
            'ok'             => true,
            'claimNumber'    => $claim->claim_number ?? null,
            'claimType'      => $claim->claim_type ?? null,
            'suggestedFormId' => $suggested->id ?? null,
            // Deliberately explicit: the claim type does NOT reliably tell us
            // motor from non-motor, so the handler is choosing, not confirming.
            'suggestionIsCertain' => $this->suggestionIsCertain((string) ($claim->claim_type ?? '')),
            'defaultEmail'   => $email,
            'forms'          => $forms,
            'alreadySent'    => DB::table('claim_form_dispatches')
                ->where('claim_id', $claimId)->where('status', 'sent')->count(),
        ];
    }

    /**
     * Send the form. Returns a plain result the UI can show verbatim.
     *
     * @param int    $claimId
     * @param int    $formId    the form the HUMAN chose
     * @param string $toEmail   the address the HUMAN confirmed
     * @param bool   $withPdf   attach the pre-filled PDF
     * @param bool   $withLink  include the no-password online form link
     * @param string $actor     who pressed the button (for the audit row)
     */
    public function dispatch(
        int $claimId,
        int $formId,
        string $toEmail,
        bool $withPdf = true,
        bool $withLink = true,
        string $actor = 'system'
    ): array {
        if (!self::enabled()) {
            return ['ok' => false, 'reason' => 'feature_off'];
        }
        if (!$withPdf && !$withLink) {
            return ['ok' => false, 'reason' => 'nothing_to_send'];
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'reason' => 'invalid_email'];
        }

        $claim = $this->findClaim($claimId);
        if (!$claim) {
            return ['ok' => false, 'reason' => 'claim_not_found'];
        }

        $form = DB::table('claim_type_forms')->where('id', $formId)->where('active', 1)->first();
        if (!$form) {
            return ['ok' => false, 'reason' => 'form_not_found'];
        }

        try {
            $data = $this->prefill->build($claimId);

            // Order matters: produce the PDF and bail out BEFORE minting a link.
            // Issuing the link first meant an aborted send left a live,
            // unrevoked form link on the claim that nobody had been told about.
            $attachmentKey = null;
            if ($withPdf) {
                $attachmentKey = $this->renderPdf($claimId, $claim, $form, $data);

                // A PDF was asked for and could not be produced: stop, rather
                // than send an email promising an attachment it does not carry.
                if (!$attachmentKey) {
                    $this->audit($claimId, $form, $toEmail, false, null, $actor, 'failed', 'the form PDF could not be produced');

                    return ['ok' => false, 'reason' => 'pdf_failed'];
                }
            }

            // The official blank form goes alongside the pre-filled copy, when we
            // actually hold it. SendMailFired takes an ARRAY of store keys
            // (`:59-67`), so this is the second element and nothing else changes.
            //
            // Existence is CHECKED rather than assumed: the blanks are uploaded
            // to the store by an operator after this ships, so until then
            // pdf_path names a file that is not there. Attaching a missing key
            // would produce a broken attachment on a customer-facing email.
            $attachments = array_filter([$attachmentKey]);
            $blank       = trim((string) ($form->pdf_path ?? ''));
            if ($blank !== '') {
                try {
                    if (Storage::disk('s3')->exists($blank)) {
                        $attachments[] = $blank;
                    } else {
                        Log::info('[ClaimFormDispatch] blank form not in the store yet, sending the pre-filled copy only', [
                            'claim_id' => $claimId, 'pdf_path' => $blank,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // A store hiccup must not stop the claimant getting their form.
                }
            }

            $link = null;
            if ($withLink) {
                $link = $this->issueFormLink($claimId, $claim, $actor);
            }

            $subject = 'Your Alpha Direct claim ' . ($claim->claim_number ?? '') . ' — ' . $form->form_title;

            // Reuses the existing mail path end to end: SendMail -> SendMailFired
            // -> Mailgun, with the attachment resolved by Helper::getCloudFrontURL.
            // The extradata carries claim_id + a `claim*` hook, which is exactly
            // what ClaimNotificationRecorder watches for — so this send is logged
            // to claim_notification_log automatically, with no logging code here.
            event(new SendMail(
                $toEmail,
                $subject,
                '',
                $this->buildEmailHtml($claim, $form, $link, (bool) $attachmentKey),
                $attachments,
                [
                    'hook'         => 'claim_form_dispatch',
                    'claim_id'     => $claimId,
                    'claim_number' => $claim->claim_number ?? null,
                ]
            ));

            // A handler may send to an address other than the one we hold — the
            // CFO chose that deliberately, because the email on file is often
            // stale and the claimant is on the phone. It is NOT blocked. But it
            // IS recorded: pre-filled claim details are leaving the building, so
            // the audit row must show when they went somewhere unexpected.
            $onFile = $claim->customer_id
                ? trim((string) DB::table('customer')->where('id', $claim->customer_id)->value('email'))
                : '';

            // No email on file is the LEAST verified case of all, not the most —
            // there is nothing to compare against, so it must be noted too. An
            // earlier version treated a blank on-file address as "matches",
            // which silently skipped the very sends most worth recording.
            $note = null;
            if ($onFile === '') {
                $note = 'sent to an address not on the customer record (no email on file)';
            } elseif (strcasecmp($onFile, trim($toEmail)) !== 0) {
                $note = 'sent to an address not on the customer record';
            }

            $this->audit($claimId, $form, $toEmail, (bool) $attachmentKey, $link, $actor, 'sent', $note);

            return [
                'ok'        => true,
                'formTitle' => $form->form_title,
                'sentPdf'   => (bool) $attachmentKey,
                'sentLink'  => (bool) $link,
                'linkUrl'   => $link ? $this->formUrl($link->token) : null,
            ];
        } catch (\Throwable $e) {
            Log::error('[ClaimFormDispatch] failed', ['claim_id' => $claimId, 'error' => $e->getMessage()]);

            // If the send blew up after a link was minted, revoke it. The token
            // never left the building so the exposure is nil, but "a live link
            // means a form was sent" should be exactly true, not nearly true.
            if (isset($link) && $link) {
                try {
                    $link->status     = 'revoked';
                    $link->revoked_at = now();
                    $link->revoked_by = $actor;
                    $link->save();
                } catch (\Throwable $ignored) {
                    // best effort — never mask the original failure
                }
            }

            $this->audit($claimId, $form, $toEmail, false, null, $actor, 'failed', Str::limit($e->getMessage(), 280));

            return ['ok' => false, 'reason' => 'send_failed'];
        }
    }

    // ── internals ───────────────────────────────────────────────────────────

    /**
     * Suggest a form from the claim type. Exact match first, then a contained
     * match, so live types that are inconsistently cased and spaced
     * (`WORKERSCOMPENSATION`, `Hospital CashBack`, `HOUSEOWNER-BUILDINGS`) still
     * land on their form.
     */
    private function suggestForm(string $claimType, $forms)
    {
        $needle = strtolower(trim($claimType));
        if ($needle === '') {
            return null;
        }

        foreach ($forms as $f) {
            if (strtolower(trim((string) $f->claim_type)) === $needle) {
                return $f;
            }
        }
        foreach ($forms as $f) {
            $ct = strtolower(trim((string) $f->claim_type));
            if ($ct !== '' && (str_contains($needle, $ct) || str_contains($ct, $needle))) {
                return $f;
            }
        }

        return null;
    }

    /**
     * Is the claim type unambiguous enough that the suggestion can be trusted?
     *
     * `Accident` WAS the problem here — 41% of the book with no reliable signal
     * for motor or not. **The CFO confirmed on 11-Aug-2026 that 'Accident' is a
     * motor accident**, so it is now certain and the handler is no longer warned
     * on 4 claims in 10, which is what made the warning worth reading.
     *
     * Still uncertain, and still warned:
     *   - MONEY: the claims team's own document lists TWO forms for it
     *     (Burglary or Fidelity) and has not said which.
     *   - ACCIDENTALDAMAGE / Accidental Death: no form has been supplied, and
     *     they are deliberately NOT motor despite the word (see the ordering
     *     note in config/claims_sla.php).
     */
    private function suggestionIsCertain(string $claimType): bool
    {
        $t = strtolower(trim($claimType));

        return $t !== '' && !in_array($t, ['accidental damage', 'accidentaldamage', 'accidental death', 'money'], true);
    }

    /**
     * Render the pre-filled form to PDF and put it in the file store.
     *
     * Uses the project's existing `PDF::` wrapper (Puppeteer first, Snappy and
     * DomPDF as fallbacks) against the SAME blade the online form renders, so
     * the paper copy and the web copy can never drift apart.
     *
     * NOTE none of the official Alpha Direct PDFs can be filled programmatically
     * except MOTOR ACCIDENT (143 AcroForm fields); the other twelve are flat.
     * Filling those would need pdftk added to the image. So we generate our own
     * branded pre-filled copy — one code path that works for every form — and
     * the official blank is attached by the email when the form has one.
     */
    private function renderPdf(int $claimId, $claim, $form, array $data): ?string
    {
        $view = $this->templateFor($form->template_key ?? null);

        $binary = \PDF::loadView($view, [
            'mode'  => 'pdf',
            'claim' => $claim,
            'form'  => $form,
            'data'  => $data,
        ])->setPaper('a4')->output();

        if (!$binary) {
            return null;
        }

        // Same store and shape as claim attachments: MIS/<claimId>/Documents/...
        $key = 'MIS/' . $claimId . '/Documents/claim-form-' . Str::random(24) . '.pdf';
        Storage::disk('s3')->put($key, $binary);

        return $key;
    }

    /**
     * Mint the claimant's no-password form link.
     *
     * The 64-char random token IS the credential — this is the standard
     * emailed-magic-link pattern, and it is why the claimant is not asked for a
     * password to fill in their own claim form. It is a DIFFERENT purpose from
     * the OTP-gated status link, so the existing status flow is untouched:
     * purpose 'fill_form', requires_otp false.
     */
    private function issueFormLink(int $claimId, $claim, string $actor): ClaimAccessLink
    {
        // REVOKE every previous form link on this claim before minting a new one.
        //
        // The first version reused an existing active link. That is wrong: a
        // handler who sends the form to the wrong address and then re-sends it
        // to the right one would leave the FIRST recipient holding a working
        // link to a stranger's claim — name, policy, cover, vehicle, and the
        // ability to upload against it. Re-sending must therefore cancel what
        // came before. One live link per claim, always the most recent.
        //
        // Revoke-then-issue runs in ONE transaction with a row lock: two
        // handlers pressing send at the same moment would otherwise race and
        // leave two live links on the claim, which is the very exposure the
        // revocation exists to close.
        return DB::transaction(function () use ($claimId, $claim, $actor) {
            // Lock the CLAIM row, not the links. Locking the links only guards
            // rows that already exist, so two simultaneous FIRST sends would
            // both find nothing to lock and both insert an active link. Taking
            // the claim row serialises every dispatch for this claim, first send
            // included.
            DB::table('claims')->where('id', $claimId)->lockForUpdate()->first(['id']);

            ClaimAccessLink::where('claim_id', $claimId)
                ->where('purpose', 'fill_form')
                ->where('status', 'active')
                ->lockForUpdate()
                ->update([
                    'status'     => 'revoked',
                    'revoked_at' => now(),
                    'revoked_by' => $actor,
                    'updated_at' => now(),
                ]);

            $link = new ClaimAccessLink();
            $link->claim_id      = $claimId;
            $link->customer_id   = $claim->customer_id ?? null;
            $link->purpose       = 'fill_form';
            $link->requires_otp  = false;
            $link->status        = 'active';
            $link->issued_by     = $actor;
            $link->issue_channel = 'form_dispatch';
            $link->expires_at    = now()->addDays(self::LINK_TTL_DAYS);
            // The token is generated by ClaimAccessLink::boot()'s creating hook
            // — 48 random bytes to 64 url-safe characters — exactly as
            // ClaimTrackingService::issueLink() relies on.
            $link->save();

            return $link;
        });
    }

    private function formUrl(string $token): string
    {
        return rtrim((string) config('app.url'), '/') . '/claim-form/' . $token;
    }

    private function templateFor(?string $key): string
    {
        $map = [
            'motor_accident' => 'claim_forms.motor_accident',
            'glass'          => 'claim_forms.glass',
        ];

        return $map[$key] ?? 'claim_forms.generic';
    }

    /** Plain, warm, branded email. Client-facing, so no jargon at all. */
    private function buildEmailHtml($claim, $form, ?ClaimAccessLink $link, bool $hasPdf = true): string
    {
        $title  = htmlspecialchars((string) $form->form_title, ENT_QUOTES);
        $number = htmlspecialchars((string) ($claim->claim_number ?? ''), ENT_QUOTES);

        $linkBlock = $link
            ? '<p style="margin:0 0 14px;">The quickest way is to fill it in online. No password is needed — this link is just for you:</p>'
              . '<p style="margin:0 0 20px;"><a href="' . htmlspecialchars($this->formUrl($link->token), ENT_QUOTES)
              . '" style="background:#F4A623;color:#0D1B2A;text-decoration:none;font-weight:bold;padding:11px 20px;'
              . 'border-radius:6px;display:inline-block;">Open my claim form</a></p>'
              . '<p style="margin:0 0 18px;font-size:13px;color:#555;">You can also upload your documents there — '
              . 'the police report, quotations and any photographs.</p>'
            : '';

        return '<div style="font-family:Arial,Helvetica,sans-serif;color:#0D1B2A;max-width:640px;margin:0 auto;line-height:1.55;">'
            . '<div style="background:#0D1B2A;padding:16px 22px;border-radius:8px 8px 0 0;">'
            . '<span style="color:#F4A623;font-size:17px;font-weight:bold;">' . $title . '</span></div>'
            . '<div style="border:1px solid #e4e7ec;border-top:none;padding:22px;border-radius:0 0 8px 8px;background:#fff;">'
            . '<p style="margin:0 0 14px;">Good day,</p>'
            . '<p style="margin:0 0 14px;">Thank you for telling us about your claim. We have registered it as '
            . '<strong>' . $number . '</strong>.</p>'
            . '<p style="margin:0 0 14px;">We have already filled in everything we hold, so there is not much left for you to do. '
            . 'Please check it, complete the parts only you can know, and send it back.</p>'
            . $linkBlock
            . ($hasPdf ? '<p style="margin:0 0 14px;">A copy is attached if you would rather print it.</p>' : '')
            . '<p style="margin:0 0 14px;">If anything looks wrong, please tell us and we will correct it.</p>'
            . '<p style="margin:18px 0 4px;">Thank you.</p>'
            . '<p style="margin:0;">Alpha Direct Claims</p>'
            . '</div></div>';
    }

    /** Who sent which form where. The recipient is MASKED at write time (DPA). */
    private function audit(int $claimId, $form, string $email, bool $pdf, ?ClaimAccessLink $link, string $actor, string $status, ?string $reason): void
    {
        try {
            DB::table('claim_form_dispatches')->insert([
                'claim_id'           => $claimId,
                'claim_type_form_id' => $form->id ?? null,
                'form_title'         => $form->form_title ?? null,
                'recipient_masked'   => $this->mask($email),
                'sent_pdf'           => $pdf,
                'sent_link'          => (bool) $link,
                'access_link_id'     => $link->id ?? null,
                'sent_by'            => $actor,
                'status'             => $status,
                'note'               => $reason,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // An audit hiccup must never lose the send that already happened.
            Log::warning('[ClaimFormDispatch] audit write failed', ['claim_id' => $claimId]);
        }
    }

    private function mask(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $head = mb_substr($user, 0, 2);

        return $head . str_repeat('*', max(1, mb_strlen($user) - 2)) . '@' . $domain;
    }

    /**
     * `claims` ONLY — deliberately no `new_claims` fallback.
     *
     * Graphite carries two claim tables with overlapping id ranges: `claims`
     * (authoritative, what the tracker screens and the claims-v2 list read) and
     * `new_claims` (legacy). Falling back by bare id across both means the same
     * number can name two different claims, and this feature emails a
     * claimant's own policy and cover details — sending them against the wrong
     * claim is the one mistake that must be impossible, not merely unlikely.
     *
     * So there is one table here. An id that is only in `new_claims` gets a
     * clean "claim not found" rather than a plausible-looking wrong answer.
     */
    private function findClaim(int $claimId)
    {
        return DB::table('claims')->where('id', $claimId)->first();
    }
}
