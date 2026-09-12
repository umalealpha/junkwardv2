<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bulk_refund_batches — parent row for a bulk refund run.
 * Child rows live in payment_refunds with bulk_refund_batch_id set.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bulk_refund_batches')) return;

        Schema::create('bulk_refund_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('Human label shown in admin UI');
            $table->text('description')->nullable();
            $table->string('reason_code', 40)->nullable()
                  ->comment('wrong_customer | duplicate_charge | goodwill | other');
            $table->string('reason', 500)->nullable();

            $table->enum('source', ['csv', 'filter', 'manual'])->default('manual')
                  ->comment('How transactions were selected into this batch');
            $table->longText('source_payload')->nullable()
                  ->comment('CSV snapshot or JSON filter used for traceability');

            $table->enum('status', ['draft', 'queued', 'processing', 'completed', 'failed', 'cancelled'])
                  ->default('draft');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('refunded_amount', 15, 2)->default(0);

            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('approved_by')->nullable()
                  ->comment('Required for batches over the approval threshold');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_bulk_refunds_status_date');
            $table->index('created_by', 'idx_bulk_refunds_creator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_refund_batches');
    }
};
