<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No Claims Declaration: the APPROVAL record.
 *
 * Split of duties UW asked for, 2026-08-31:
 *   - upload  — Underwriting, i.e. the existing `policy-claims-waiver-upload`
 *               permission. Upload only; UW cannot approve its own document.
 *   - approve — only a holder of the approver ROLE (see
 *               Services\ClaimsWaiverApprovalGate::ROLE), which is created
 *               once in /roles and assigned to the two named approvers.
 *               No Super Admin / Manager bypass — the whole point is that a
 *               named person signs.
 *
 * Deliberately its own table rather than columns on `policy_attachments`:
 * that table is shared legacy storage for every attachment type on a policy
 * (KYC, banking, OCR, generic documents), read by half a dozen unrelated
 * screens. Approval state belongs to this one document type only.
 *
 * Rows are kept after the document they describe is replaced or removed —
 * `attachment_id` then points at a deleted row and `file_name` carries the
 * snapshot, so the approval history stays readable. The CURRENT state of a
 * policy's declaration is the newest row (highest id).
 */
return new class extends Migration {
    public function up(): void
    {
        // Guard shape is deliberate: PR Guard (.github/workflows/pr-guard.yml)
        // requires the literal `if (!Schema::hasTable('table'))` wrapper around
        // every Schema::create — an early `return` reads as non-idempotent to it.
        if (!Schema::hasTable('policy_claims_waiver_approvals')) {
            Schema::create('policy_claims_waiver_approvals', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('policy_id');
                // policy_attachments.id of the uploaded declaration. Nullable
                // and NOT a foreign key on purpose: the attachment row is hard
                // deleted when the document is replaced, and this audit row
                // must survive that.
                $table->unsignedBigInteger('attachment_id')->nullable();
                // Snapshot of the uploaded file's name, so a superseded row
                // still says what was approved.
                $table->string('file_name')->nullable();

                // PENDING -> APPROVED | REJECTED. Only an approver-role holder
                // moves it off PENDING.
                $table->string('status', 20)->default('PENDING');

                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('rejection_reason', 1000)->nullable();

                $table->timestamps();

                $table->index(['policy_id', 'id'], 'pcwa_policy_idx');
                $table->index('attachment_id', 'pcwa_attachment_idx');
                $table->index('status', 'pcwa_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_claims_waiver_approvals');
    }
};
