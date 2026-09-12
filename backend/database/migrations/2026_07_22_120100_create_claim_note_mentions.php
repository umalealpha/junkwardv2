<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-3 Claims-Tracker migration — @mention support for claim review notes.
 *
 * Review notes already live in claim_review_notes (ClaimReviewNote). This table
 * records the @mentions parsed out of a note's body when a note is created with
 * the `claims_mentions` feature flag enabled (default OFF). One row per parsed
 * handle:
 *   - resolved handles carry the matched mentioned_user_id;
 *   - unresolved handles (unknown user) are kept with a NULL user id so the UI
 *     can show them as un-highlighted / unresolved.
 *
 * Additive + idempotent (Schema::hasTable guard). No change to any existing flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_note_mentions')) {
            Schema::create('claim_note_mentions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_note_id')->index();
                $table->unsignedBigInteger('claim_id')->index();
                // The raw handle matched in the note body (without the leading @),
                // stored lowercased. Kept even when unresolved for UI display.
                $table->string('handle', 100);
                // NULL when the handle could not be resolved to a Graphite user.
                $table->unsignedBigInteger('mentioned_user_id')->nullable()->index();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_note_mentions');
    }
};
