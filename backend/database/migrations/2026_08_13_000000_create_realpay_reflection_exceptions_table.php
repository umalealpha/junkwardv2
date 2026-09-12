<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable ledger of RealPay debits that could NOT be reflected into Graphite.
 *
 * The failure this exists for: RealPay confirms a collection (InstalmentStatus
 * 'S' — the customer's account HAS been debited) and the Graphite write that
 * should follow does not happen. Until now that produced no record anywhere —
 * updateInstallment() returned an unresolved-policy path silently, or threw and
 * was swallowed into an HTTP 401 that the buffer drain then marked 'processed'.
 * The money moved and Graphite kept no evidence that it had ever been told.
 *
 * One row per (instalment reference + sequence). `resolved_at` is stamped when
 * the payment is finally written, so an operator's "what is still outstanding?"
 * is `WHERE resolved_at IS NULL` and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded so a re-run of `migrate --force` against a drifted state
        // cannot halt the pipeline (PR Guard, adopted 2026-06-15).
        if (! Schema::hasTable('realpay_reflection_exceptions')) {
            Schema::create('realpay_reflection_exceptions', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Natural key of a RealPay instalment delivery. Unique so the
                // every-minute buffer replay records the same failure once and
                // bumps a counter instead of growing the table without bound.
                $table->string('instalment_reference', 100);
                $table->string('instalment_sequence', 32)->nullable();

                $table->string('client_number', 100)->nullable();
                $table->string('contract_number', 100)->nullable();
                $table->string('policy_number', 64)->nullable();
                $table->unsignedInteger('policy_id')->nullable();

                $table->string('instalment_status', 8)->nullable();  // raw RealPay letter
                $table->decimal('amount', 13, 2)->nullable();
                $table->date('action_date')->nullable();

                // Why the reflection failed — 'policy_unresolved', 'write_failed',
                // 'exception'. Kept as a plain string: this is an operational log,
                // and a new failure mode must not need a migration to be recorded.
                $table->string('reason', 64);
                $table->text('error')->nullable();

                // The full webhook body, so the payment can be reconstructed even
                // if every other table is missing the data.
                $table->longText('payload')->nullable();

                $table->unsignedInteger('occurrences')->default(1);
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolved_by', 64)->nullable();
                $table->timestamps();

                $table->unique(['instalment_reference', 'instalment_sequence'], 'rre_ref_seq_unique');
                // The operator query: open exceptions, newest first.
                $table->index(['resolved_at', 'created_at'], 'rre_open_idx');
                $table->index('policy_number', 'rre_policy_number_idx');
                $table->index('contract_number', 'rre_contract_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('realpay_reflection_exceptions');
    }
};
