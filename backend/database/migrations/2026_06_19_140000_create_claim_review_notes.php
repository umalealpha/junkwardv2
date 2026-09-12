<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use AlphaDirect\EmailBroadcasting;

/**
 * Claim Review Notes — handler-authored notes on a claim review, with
 * explicit recipients (notified in-app + by email) and optional file
 * attachments. Mirrors the existing claim_complaint_log / claim_attachments
 * patterns. Email copy lives in the email_templates (EmailBroadcasting) table
 * under hook_slug `claim_review_note` so wording changes need no deploy.
 *
 *  - claim_review_notes            : one row per logged note (multiple per claim).
 *  - claim_review_note_recipients  : the people tagged on a note — resolved to a
 *                                    user_id where possible + the email actually
 *                                    notified; notified_at stamps a successful send.
 *  - claim_review_note_files       : attachments (S3 paths, same MIS/{claim}/... bucket
 *                                    convention as claim_attachments).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_review_notes')) {
            Schema::create('claim_review_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('policy_id')->nullable();
                // Short subject line shown in the list + email subject context.
                $table->string('title', 200)->nullable();
                $table->longText('note');
                // Author — denormalised name so the list survives a deleted user,
                // matching how claim_complaint_log resolves added_by.
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('claim_review_note_recipients')) {
            Schema::create('claim_review_note_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_note_id')->index();
                // Null when a free-text email was entered rather than a picked user.
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('email', 150);
                $table->string('name', 150)->nullable();
                // Stamped once the notification email is dispatched (best-effort).
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('claim_review_note_files')) {
            Schema::create('claim_review_note_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_note_id')->index();
                $table->string('name', 255);
                // S3 object path; served through Helper::getCloudFrontURL().
                $table->string('file_path', 512);
                $table->string('mime', 150)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        $this->seedEmailTemplate();
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_review_note_files');
        Schema::dropIfExists('claim_review_note_recipients');
        Schema::dropIfExists('claim_review_notes');
    }

    /**
     * Seed the editable email body. Placeholders are resolved by the
     * ClaimReviewNote mailable: {{claim_number}}, {{author_name}},
     * {{note_title}}, {{note_preview}}, {{review_url}}. Idempotent via
     * updateOrCreate on the hook_slug — re-running the migration after a
     * reset won't duplicate the row, and won't clobber admin edits beyond
     * resetting to the default body (acceptable: the row is only seeded once).
     */
    private function seedEmailTemplate(): void
    {
        // The "View Review" button + link is rendered by the blade view from
        // {{review_url}}, so the editable body holds only the message + preview.
        $text = "{{author_name}} has logged a review note on claim {{claim_number}}.\n\n{{note_preview}}";

        try {
            EmailBroadcasting::updateOrCreate(
                ['hook_slug' => 'claim_review_note'],
                [
                    'subject'    => 'Claim Review Note — Claim {{claim_number}}',
                    'text'       => $text,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            // Non-fatal: the mailable falls back to a coded default body when
            // the hook row is absent, so a seed failure never blocks migrate.
            \Illuminate\Support\Facades\Log::warning(
                'claim_review_note email template seed skipped: ' . $e->getMessage()
            );
        }
    }
};
