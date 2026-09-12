<?php

namespace AlphaDirect\Mail;

use AlphaDirect\EmailBroadcasting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Notifies a tagged recipient that a review note was logged on a claim.
 *
 * The email body copy is pulled from the editable email_templates row
 * (hook_slug `claim_review_note`); placeholders {{claim_number}},
 * {{author_name}}, {{note_preview}}, {{review_url}} are substituted here — the
 * same hook_slug approach used by AccidentAssessment / SendPO. Falls back to a
 * coded default body when the template row is absent so the email always sends.
 *
 * The SUBJECT is supplied pre-built by the caller (data['subject']) because it
 * is fully dynamic per claim — "CLAIM REVIEW - [Policy] [Customer] [Item]
 * [Type] CLAIM#: [No]" — and can't be a static template string.
 *
 * The note body itself ($preview) is included per the feature spec. Recipients
 * are internal staff acting on the claim; the CLAIM NUMBER is the primary
 * reference and the "View Review" button deep-links into Graphite.
 */
class ClaimReviewNote extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $body;
    public string $reviewUrl;

    /**
     * @param array{claim_number:?string,author_name:?string,note_preview:?string,review_url:?string,subject:?string} $data
     */
    public function __construct(array $data)
    {
        $claimNumber     = $data['claim_number'] ?? '';
        $authorName      = $data['author_name'] ?? 'A claim handler';
        $preview         = $data['note_preview'] ?? '';
        $reviewUrl       = $data['review_url'] ?? '';
        $providedSubject = trim((string) ($data['subject'] ?? ''));

        $this->reviewUrl = $reviewUrl;

        $replacements = [
            '{{claim_number}}' => $claimNumber,
            '{{author_name}}'  => $authorName,
            '{{note_preview}}' => $preview,
            '{{review_url}}'   => $reviewUrl,
            // Defensive: blank out the legacy title placeholder so an
            // un-migrated template row never shows a literal {{note_title}}.
            '{{note_title}}'   => '',
        ];

        $subject = 'Claim Review Note — Claim {{claim_number}}';
        $text    = "{{author_name}} has logged a review note on claim {{claim_number}}.\n\n{{note_preview}}";

        try {
            $template = EmailBroadcasting::where('hook_slug', 'claim_review_note')
                ->first(['subject', 'text']);
            if ($template) {
                $subject = $template->subject ?: $subject;
                $text    = $template->text ?: $text;
            }
        } catch (\Throwable $e) {
            Log::warning('ClaimReviewNote: email template lookup failed, using default: ' . $e->getMessage());
        }

        // Caller-supplied dynamic subject wins; fall back to the template only
        // when none was provided.
        $this->subjectLine = $providedSubject !== ''
            ? $providedSubject
            : strtr($subject, $replacements);
        // nl2br so the editable plain-text body keeps its line breaks in HTML.
        $this->body = nl2br(e(strtr($text, $replacements)));
    }

    public function build()
    {
        // Attachments are deliberately not added — files live on the note and
        // are viewed in the Claim Review tab, never emailed.
        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($this->subjectLine)
            ->markdown('Mail.claimReviewNote', [
                'body'      => $this->body,
                'reviewUrl' => $this->reviewUrl,
            ]);
    }
}
