<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claim-form dispatch — the handler presses a button on the claim and the
 * claimant is emailed the correct form: a pre-filled PDF plus a no-password
 * link where they can complete it online.
 *
 * A handler CHOOSES the form (defaulted from claim_type, overridable), so this
 * never guesses on the ambiguous types — notably `Accident`, 41% of the book,
 * whose motor/non-motor flags are unreliable (see the claim-form memory note).
 *
 * Everything here is ADDITIVE and idempotent (pr-guard). Nothing existing reads
 * the new columns or tables, and the feature is dark behind the
 * `claims_form_dispatch` runtime flag.
 */
class AddClaimFormDispatch extends Migration
{
    public function up(): void
    {
        // ── claim_type_forms: point each type at its official blank PDF and,
        // where we have one, the pre-fill template that drives both the PDF we
        // generate and the online form. The table already carries claim_type /
        // form_title / form_url / instructions / active.
        if (Schema::hasTable('claim_type_forms')) {
            Schema::table('claim_type_forms', function (Blueprint $table) {
                if (!Schema::hasColumn('claim_type_forms', 'pdf_path')) {
                    // Store key of the official blank PDF (same store as claim
                    // attachments), resolved for mail via Helper::getCloudFrontURL.
                    $table->string('pdf_path', 500)->nullable()->after('form_url');
                }
                if (!Schema::hasColumn('claim_type_forms', 'template_key')) {
                    // 'motor_accident' | 'glass' | null (null => generic template).
                    $table->string('template_key', 60)->nullable()->after('pdf_path');
                }
                if (!Schema::hasColumn('claim_type_forms', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(100)->after('active');
                }
            });
        }

        // ── claim_access_links: the existing claimant link is OTP-gated by
        // design (status page). A form-fill link is a different purpose and the
        // token itself is the credential — 48 random bytes, unguessable — so it
        // opens without a password, which is what a claimant filling a form
        // needs. Defaults preserve today's behaviour exactly for every existing
        // row and for the status flow: purpose 'status', requires_otp true.
        if (Schema::hasTable('claim_access_links')) {
            Schema::table('claim_access_links', function (Blueprint $table) {
                if (!Schema::hasColumn('claim_access_links', 'purpose')) {
                    $table->string('purpose', 30)->default('status')->after('token')->index();
                }
                if (!Schema::hasColumn('claim_access_links', 'requires_otp')) {
                    $table->boolean('requires_otp')->default(true)->after('purpose');
                }
            });
        }

        // ── Audit of every button press: who sent which form, to which address.
        // claim_notification_log already records the email itself (masked), but
        // it does not record the human's CHOICE — and the choice is the control.
        if (!Schema::hasTable('claim_form_dispatches')) {
            Schema::create('claim_form_dispatches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('claim_type_form_id')->nullable()->index();
                $table->string('form_title', 200)->nullable();
                // Masked at write time (DPA) — the raw address is never stored.
                $table->string('recipient_masked', 120)->nullable();
                $table->boolean('sent_pdf')->default(false);
                $table->boolean('sent_link')->default(false);
                $table->unsignedBigInteger('access_link_id')->nullable()->index();
                $table->string('sent_by', 120)->nullable();
                $table->string('status', 20)->default('sent')->index();
                $table->string('note', 300)->nullable(); // failure reason, or an unusual-but-allowed condition (e.g. sent off-record)
                $table->timestamps();
            });
        }

        // ── What the claimant filled in online. One row per submission; a
        // claimant may submit more than once (we keep every version rather than
        // overwrite, so nothing a customer told us is ever lost).
        if (!Schema::hasTable('claim_form_submissions')) {
            Schema::create('claim_form_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('access_link_id')->nullable()->index();
                $table->string('template_key', 60)->nullable();
                // The answers, as submitted. Claimant PII — stays server-side,
                // never leaves Graphite, never goes to an external model.
                $table->json('payload')->nullable();
                $table->string('submitted_ip', 64)->nullable();
                $table->dateTime('submitted_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_form_submissions');
        Schema::dropIfExists('claim_form_dispatches');

        if (Schema::hasTable('claim_access_links')) {
            Schema::table('claim_access_links', function (Blueprint $table) {
                foreach (['requires_otp', 'purpose'] as $col) {
                    if (Schema::hasColumn('claim_access_links', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('claim_type_forms')) {
            Schema::table('claim_type_forms', function (Blueprint $table) {
                foreach (['sort_order', 'template_key', 'pdf_path'] as $col) {
                    if (Schema::hasColumn('claim_type_forms', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}
