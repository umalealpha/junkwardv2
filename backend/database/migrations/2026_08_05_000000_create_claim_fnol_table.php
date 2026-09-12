<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FNOL (First Notification of Loss) intake table.
 *
 * A lightweight, additive intake ledger for losses reported to Graphite that
 * are NOT yet registrable as a full claim (missing docs / policy not yet
 * resolvable). An operator records the loss immediately; the send-gated
 * `claims:fnol-doc-reminders` command chases outstanding documents by email;
 * once complete the FNOL is CONVERTED into a real Graphite claim (reusing the
 * existing claim-create path) and marked converted.
 *
 * Fully additive + inert: nothing reads or writes this table until the
 * `claims_fnol` runtime flag (Admin > Integrations, default OFF) is enabled.
 * Idempotent (Schema::hasTable guard) — the repo standard, because some V2
 * environments were hand-built. No change to any existing flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_fnol')) {
            Schema::create('claim_fnol', function (Blueprint $table) {
                $table->id();
                // Human reference, e.g. FNOL2026000001. Unique so it can be
                // quoted to the claimant and used as a stable lookup key.
                $table->string('fnol_number', 40)->unique();
                $table->string('claimant_name', 255);
                // Policy linkage is deliberately soft — an FNOL can exist before
                // the policy is resolved. Convert requires it to be resolvable.
                $table->string('policy_number', 100)->nullable();
                $table->unsignedBigInteger('policy_id')->nullable();
                $table->string('claim_type', 100)->nullable();
                $table->date('loss_date')->nullable();
                $table->text('description');
                $table->string('contact_phone', 40)->nullable();
                $table->string('contact_email', 191)->nullable();
                $table->decimal('estimate_amount', 15, 2)->nullable();
                // JSON array of doc names still outstanding (drives reminders).
                $table->json('outstanding_docs')->nullable();
                // open | converted | closed.
                $table->string('status', 20)->default('open');
                // Documentation-reminder bookkeeping (send-gated command).
                $table->unsignedInteger('reminder_count')->default(0);
                $table->timestamp('last_reminder_at')->nullable();
                // Set on successful convert -> the real claims.id.
                $table->unsignedBigInteger('converted_claim_id')->nullable();
                // manual | tracker_migration | ...
                $table->string('source', 40)->default('manual');
                // Originating tracker id — upsert key for idempotent imports.
                $table->string('external_ref', 100)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('status', 'claim_fnol_status_index');
                $table->index('converted_claim_id', 'claim_fnol_converted_claim_id_index');
                // Unique so a re-run of the tracker import upserts (never
                // duplicates) on the originating id. NULL external_ref (manual
                // FNOLs) is exempt — MySQL permits many NULLs in a unique index.
                $table->unique('external_ref', 'claim_fnol_external_ref_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_fnol');
    }
};
