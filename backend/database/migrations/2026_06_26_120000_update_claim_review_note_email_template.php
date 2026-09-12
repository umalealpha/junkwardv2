<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use AlphaDirect\EmailBroadcasting;

/**
 * Refresh the `claim_review_note` email body after the Title field was dropped.
 *
 * The original create migration seeded a body with a "Note: {{note_title}}"
 * line. Title is no longer captured, so that line is removed. The notification
 * SUBJECT is now built dynamically in code (ClaimsV2Controller::buildReviewSubject)
 * and no longer read from this row — the subject column is left only as a
 * fallback. updateOrCreate keyed on hook_slug → idempotent and safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $text = "{{author_name}} has logged a review note on claim {{claim_number}}.\n\n{{note_preview}}";

        try {
            EmailBroadcasting::updateOrCreate(
                ['hook_slug' => 'claim_review_note'],
                [
                    // Kept only as a fallback; the live subject is code-generated.
                    'subject'    => 'CLAIM REVIEW - Claim {{claim_number}}',
                    'text'       => $text,
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('claim_review_note email template update skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // No-op: reverting to the title-bearing body isn't meaningful.
    }
};
