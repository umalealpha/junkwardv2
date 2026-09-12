<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_refunds — one row per refund request against a payment_transaction.
 * Tracks request, DPO's response, resulting state.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('payment_refunds')) return;

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_transaction_id')
                  ->comment('The original transaction being refunded');
            $table->string('original_transaction_token', 128)->nullable()
                  ->comment('DPO TransactionToken from original charge');
            $table->string('policy_number', 80)->nullable();
            $table->unsignedInteger('customer_id')->nullable();

            $table->decimal('amount', 13, 2)->comment('Refund amount (may be partial)');
            $table->string('currency', 8)->default('BWP');
            $table->string('reason', 500)->nullable();
            $table->string('reason_code', 40)->nullable()
                  ->comment('Categorised reason e.g. wrong_customer | duplicate_charge | goodwill');

            $table->enum('status', ['pending', 'submitted', 'succeeded', 'failed', 'cancelled'])
                  ->default('pending');
            $table->string('dpo_refund_reference', 80)->nullable()
                  ->comment('Reference returned by DPO refundToken response');
            $table->string('dpo_result_code', 16)->nullable()
                  ->comment('DPO Result code (000=success)');
            $table->string('dpo_result_explanation', 400)->nullable();
            $table->longText('dpo_request_xml')->nullable();
            $table->longText('dpo_response_xml')->nullable();

            $table->enum('refund_type', ['single', 'bulk'])->default('single');
            $table->unsignedBigInteger('bulk_refund_batch_id')->nullable();

            $table->unsignedInteger('initiated_by')->nullable()
                  ->comment('users.id who triggered the refund');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('payment_transaction_id', 'idx_refunds_tx');
            $table->index('policy_number', 'idx_refunds_policy');
            $table->index('customer_id', 'idx_refunds_customer');
            $table->index(['status', 'refund_type'], 'idx_refunds_status_type');
            $table->index('bulk_refund_batch_id', 'idx_refunds_batch');
            $table->index('dpo_refund_reference', 'idx_refunds_dpo_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
