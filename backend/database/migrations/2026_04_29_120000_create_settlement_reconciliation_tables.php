<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settlement reconciliation tables — daily DPO / RealPay export vs our
 * payment_transactions. Schema is provider-agnostic: a `provider` column
 * on `settlement_runs` distinguishes 'dpo' from 'realpay' so the same
 * upload UI + matching engine handle both.
 *
 * Three tables, normalised:
 *
 *   settlement_runs           one per CSV upload
 *   settlement_batches        per "settlement sum" / settlement instalment to bank
 *   settlement_transactions   per individual transaction inside a batch
 *
 * Findings = settlement_transactions WHERE match_status != 'matched' OR
 * batches with non-zero drift. Single source of truth keeps reporting
 * simple — every unreconciled row is the same shape and resolvable from
 * the admin UI.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('settlement_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['dpo', 'realpay']);
            $table->string('file_name', 255);
            $table->string('file_path', 500)->nullable()
                ->comment('S3 key under settlement-reconciliation/<provider>/<yyyy-mm>/');
            $table->string('file_hash', 64)->nullable()
                ->comment('SHA-256 of uploaded CSV — prevents accidental re-upload of same file');
            $table->date('settlement_date_from')->nullable();
            $table->date('settlement_date_to')->nullable();
            $table->string('currency', 3)->default('BWP');
            $table->enum('status', ['uploaded', 'parsing', 'parsed', 'matched', 'reviewed', 'closed', 'failed'])
                  ->default('uploaded');
            $table->integer('batch_count')->default(0);
            $table->integer('transaction_count')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('unmatched_count')->default(0);
            $table->integer('drift_count')->default(0)
                ->comment('Batches with non-zero drift OR transactions with mismatched amounts');
            $table->decimal('total_dpo_amount', 15, 2)->default(0);
            $table->decimal('total_local_amount', 15, 2)->default(0);
            $table->decimal('drift_total', 15, 2)->default(0)
                ->comment('Sum of unmatched + amount-drift');
            $table->text('parse_error')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()
                ->comment('users.id of operator who uploaded');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('parse_started_at')->nullable();
            $table->timestamp('parse_completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['provider', 'status']);
            $table->index(['settlement_date_from', 'settlement_date_to']);
            $table->unique(['provider', 'file_hash'], 'uniq_run_provider_hash');
        });

        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('settlement_runs')->cascadeOnDelete();
            $table->string('provider_batch_id', 100)
                ->comment('DPO settlement_sum_id or RealPay equivalent — provider-supplied');
            $table->date('batch_date')->nullable()
                ->comment('Provider payment_date — when DPO posted this batch');
            $table->date('settlement_date')->nullable()
                ->comment('Date the batch hit our bank account');
            $table->string('account_number', 50)->nullable();
            $table->string('currency', 3)->default('BWP');
            $table->decimal('batch_total_amount', 15, 2)
                ->comment('Provider-reported total for this batch (positive = credit to merchant)');
            $table->decimal('matched_local_total', 15, 2)->default(0)
                ->comment('Sum of payment_transactions.amount for matched transactions in this batch');
            $table->decimal('drift_amount', 15, 2)->default(0)
                ->comment('batch_total_amount - matched_local_total (zero-tolerance: any non-zero is a finding)');
            $table->integer('transaction_count')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('unmatched_count')->default(0);
            $table->json('raw_row')->nullable()
                ->comment('Original CSV row for audit replay');
            $table->timestamps();
            $table->index(['run_id']);
            $table->index(['provider_batch_id']);
            $table->unique(['run_id', 'provider_batch_id'], 'uniq_batch_run_pid');
        });

        Schema::create('settlement_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('settlement_runs')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('settlement_batches')->nullOnDelete()
                ->comment('Null when provider tx not yet linked to a batch (e.g. settlement_sum_id=0 placeholder)');
            $table->enum('provider_type', ['Transaction', 'Refund', 'Manual', 'Other'])
                  ->default('Transaction')
                  ->comment('DPO row type — Refund maps to payment_refunds, Manual flagged for review');
            $table->string('provider_trans_ref', 100)->nullable()
                ->comment('DPO trans ref e.g. R78628712');
            $table->string('provider_ref_id', 100)->nullable()
                ->comment('DPO numeric ref id — primary key for matching back to payment_transactions.TransactionToken');
            $table->string('provider_external_ref', 200)->nullable()
                ->comment('DPO provider ref / RealPay equivalent — usually our policy ref like MIS2025193925/3/4');
            $table->string('account_type', 100)->nullable()
                ->comment('"Settlement", "Settlement - Chargeback", etc.');
            $table->date('transaction_date')->nullable();
            $table->date('refund_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->date('settlement_date')->nullable();
            $table->decimal('transaction_amount', 15, 4)->default(0);
            $table->decimal('paid_amount', 15, 4)->default(0);
            $table->decimal('dpo_fee', 15, 4)->default(0);
            $table->decimal('dpo_vat', 15, 4)->default(0);
            $table->decimal('net_settlement_amount', 15, 4)->default(0)
                ->comment('Provider-reported net to merchant (DPO uses negative sign for credits)');
            $table->string('currency', 3)->default('BWP');
            $table->decimal('conversion_rate', 15, 8)->default(1);
            $table->string('card_type', 50)->nullable();
            $table->string('card_number_masked', 30)->nullable();
            $table->string('approval_number', 50)->nullable();
            $table->string('card_level', 50)->nullable();

            // Matching outcome
            $table->enum('match_status', [
                'pending',          // not yet matched
                'matched',          // perfect match on token + amount + date
                'amount_mismatch',  // matched but amount differs
                'date_mismatch',    // matched but transaction_date differs > tolerance
                'status_mismatch',  // matched but local says one thing, DPO another (e.g. local=Pending, DPO=Settled)
                'orphan_dpo',       // DPO has it, we don't — money received we don't know about
                'orphan_local',     // we have it, DPO doesn't (filtered separately, this row won't be created)
                'duplicate',        // multiple local matches found
                'manual_review',    // type=Manual or anything we can't auto-match
            ])->default('pending');
            $table->enum('match_method', ['token', 'trans_ref', 'external_ref', 'none'])
                  ->nullable();
            $table->unsignedBigInteger('local_payment_transaction_id')->nullable();
            $table->unsignedBigInteger('local_payment_refund_id')->nullable();
            $table->decimal('drift_amount', 15, 4)->default(0)
                ->comment('paid_amount - local_amount; zero when match_status=matched');

            // Workflow
            $table->enum('finding_status', ['none', 'open', 'accepted', 'disputed', 'resolved'])
                  ->default('none')
                  ->comment('none = match_status=matched (no finding); rest are workflow states');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->json('raw_row')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'match_status']);
            $table->index(['batch_id']);
            $table->index(['provider_ref_id']);
            $table->index(['provider_external_ref']);
            $table->index(['local_payment_transaction_id']);
            $table->index(['finding_status']);
            $table->unique(['run_id', 'provider_trans_ref', 'provider_ref_id'], 'uniq_tx_run_provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_transactions');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('settlement_runs');
    }
};
