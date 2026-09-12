<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Refund Engine — workflow tables (Phase 1, arms-off).
 *
 * refund_requests is the GOVERNANCE record for the CFO's customer-refund SOP
 * (intake → review → approve → Omni handoff → paid → posted). It is a separate
 * entity from payment_refunds, which stays the MONEY-EXECUTION record: a
 * payment_refunds row is only created when an approved request is handed to
 * Omni (source='omni'), so the existing DPO refund tooling, settlement
 * reconciliation and Reporting netting are untouched.
 *
 * DPA: the customer bank account number is stored ONLY in
 * account_number_encrypted (Laravel `encrypted` cast on the model), with a
 * keyed-HMAC blind index for dedupe/fraud matching and last-4 for display —
 * mirroring the Omni (alpha-finance) treatment on the other end.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            Schema::create('refund_requests', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Identity. graphite_ref (RFND-000123) is the idempotency key
                // shared with Omni — its inbound endpoint dedupes on it.
                $table->string('graphite_ref', 64)->unique();
                $table->string('area', 12)->comment('mis | domestic | commercial — separate teams, no cross-access');

                // Subject
                $table->string('policy_number', 80);
                $table->unsignedBigInteger('policy_id')->nullable();
                $table->string('product_name', 191)->nullable();
                $table->unsignedInteger('customer_id')->nullable();
                $table->string('customer_name', 191)->nullable();
                $table->string('agent_name', 191)->nullable();

                // Money
                $table->decimal('refund_amount', 13, 2);
                $table->string('currency', 3)->default('BWP');
                $table->string('collection_method', 12)->nullable()->comment('DPO | RealPay — how the premium was collected (SOP field)');
                $table->string('reason', 500)->nullable();
                $table->string('reason_code', 40)->nullable();

                // Bank (DPA — number encrypted at rest; last-4 only for display)
                $table->string('bank_name', 120)->nullable();
                $table->string('branch_code', 20)->nullable();
                $table->text('account_number_encrypted')->nullable();
                $table->string('account_number_bindex', 64)->nullable()->index()
                      ->comment('Keyed HMAC-SHA256 of the digits-only account number — blind index');
                $table->string('account_last4', 4)->nullable();

                // SOP flags
                $table->boolean('vehicle_not_client')->default(false)
                      ->comment('When true a signed affidavit document is mandatory at submit');
                $table->boolean('bank_account_confirmed')->default(false)
                      ->comment('Administrator confirmed the client bank account before arming payment');
                $table->boolean('after_cutoff')->default(false)
                      ->comment('Submitted after the 15:00 Africa/Gaborone cut-off — processes next business day');

                // AI green light (evidence carried to Omni for its audit trail)
                $table->boolean('ai_greenlight')->default(false);
                $table->json('ai_evidence')->nullable();

                // Workflow
                $table->string('status', 24)->default('draft')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('cfo_approved_by')->nullable();
                $table->timestamp('cfo_approved_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejected_reason')->nullable();
                $table->unsignedBigInteger('escalated_by')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->text('escalated_reason')->nullable();

                // Money linkage / Omni lifecycle
                $table->unsignedBigInteger('payment_refund_id')->nullable()->index()
                      ->comment('payment_refunds row created at Omni handoff (source=omni)');
                $table->string('omni_status', 16)->default('not_sent')
                      ->comment('not_sent | sent | paid | failed');
                $table->string('omni_paid_ref', 120)->nullable()
                      ->comment('Omni fnb_reference from the paid callback — may be empty (manual EFT)');
                $table->timestamp('omni_paid_at')->nullable();
                $table->timestamp('handed_off_at')->nullable();
                $table->boolean('portal_flag')->default(false)
                      ->comment('Customer portal shows the policy as refunded');

                $table->timestamps();
                $table->softDeletes();

                $table->index(['area', 'status']);
                $table->index('policy_number');
                $table->index('created_by');
            });
        }

        if (!Schema::hasTable('refund_request_documents')) {
            Schema::create('refund_request_documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('refund_request_id')->index();
                $table->string('doc_type', 40)
                      ->comment('bank_statement | bank_confirmation | affidavit | other');
                $table->string('file_path', 512)->comment('S3 key — served only via the area-scoped API, never a public URL');
                $table->string('original_name', 255)->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('sha256', 64)->nullable()->comment('Integrity hash of the uploaded bytes');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['refund_request_id', 'doc_type']);
            });
        }

        if (!Schema::hasTable('refund_request_events')) {
            Schema::create('refund_request_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('refund_request_id')->index();
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24)->nullable();
                $table->string('action', 32)
                      ->comment('create|update|upload_document|submit|review|approve|reject|escalate|cfo_approve|handoff|handoff_failed|paid|posted');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 191)->nullable();
                $table->text('note')->nullable();
                $table->json('meta')->nullable()
                      ->comment('Amount at time, missing-docs list, callback payload refs — never PII');
                $table->timestamp('created_at')->nullable();

                $table->index(['refund_request_id', 'action']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_request_events');
        Schema::dropIfExists('refund_request_documents');
        Schema::dropIfExists('refund_requests');
    }
};
