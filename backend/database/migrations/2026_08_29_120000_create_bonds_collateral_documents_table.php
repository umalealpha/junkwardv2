<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bonds (product 23): the collateral DOCUMENT store.
 *
 * 2026_08_27_120000 added the structured collateral capture on
 * bonds_coverages (type / value / reference / expiry + the EXCO confirmation
 * trio). That records WHAT security is held; this table holds the PROOF —
 * the bank guarantee, the cession, the deposit receipt — as an uploaded file.
 *
 * UW rule, 2026-08-29:
 *   - Anyone may upload a collateral document.
 *   - Only EXCO (the `bonds-approve` permission) may approve or reject one.
 *   - A Bonds transaction cannot be issued until an approved document exists
 *     for it — see Services\Bonds\BondsIssuanceGate::collateralDocumentBlocker().
 *
 * Rows are scoped to policy AND action so each transaction (new business,
 * endorsement, renewal, reinstatement) carries its own proof — a renewal that
 * moves the exposure must not ride on the bond's original guarantee.
 *
 * Deliberately its own table rather than more columns on bonds_coverages:
 * a bond can be secured by several instruments, each approved separately,
 * and every upload/approval must stay on file as an audit trail even after
 * it is superseded.
 */
return new class extends Migration {
    public function up(): void
    {
        // Guard shape is deliberate: PR Guard (.github/workflows/pr-guard.yml)
        // requires the literal `if (!Schema::hasTable('table'))` wrapper around
        // every Schema::create — an early `return` reads as non-idempotent to it.
        if (!Schema::hasTable('bonds_collateral_documents')) {
            Schema::create('bonds_collateral_documents', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('policy_id');
                // The transaction this proof belongs to. Nullable only so an
                // upload on a policy with no action row yet never fails; the
                // issue gate reads it and treats NULL as policy-level.
                $table->unsignedBigInteger('action_id')->nullable();
                // The bond schedule row this document evidences, when the
                // operator uploaded it from an open schedule.
                $table->unsignedBigInteger('bonds_coverage_id')->nullable();
                $table->unsignedBigInteger('policy_coverage_id')->nullable();

                // What the file is: mirrors bonds_coverages.collateral_type
                // (Bank Guarantee / Cash Deposit / Cession …) plus a free note.
                $table->string('document_type')->nullable();
                $table->string('notes', 1000)->nullable();

                $table->string('file_path', 1000);
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                // Which disk storeWithFallback landed on (s3 / documents /
                // public), so download never has to guess.
                $table->string('disk', 50)->nullable();

                // PENDING → APPROVED | REJECTED. Only EXCO moves it off PENDING.
                $table->string('status', 20)->default('PENDING');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('rejection_reason', 1000)->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['policy_id', 'action_id'], 'bcd_policy_action_idx');
                $table->index(['policy_id', 'status'], 'bcd_policy_status_idx');
                $table->index('bonds_coverage_id', 'bcd_bond_row_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bonds_collateral_documents');
    }
};
