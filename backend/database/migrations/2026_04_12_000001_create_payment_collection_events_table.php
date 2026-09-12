<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_collection_events
 *
 * Records every manual "Collect Now" action triggered from the v2 admin UI.
 * Used for management showcase — shows how the new system actively drives
 * premium collection rather than waiting for cron jobs.
 *
 * Columns:
 *   policy_id         — FK to policies.id
 *   policy_number     — denormalised for quick reporting
 *   customer_id       — FK to customer.id
 *   payment_method    — 'DPO' | 'REALPAY'
 *   amount            — amount attempted
 *   status            — pending | success | failed
 *   gateway_reference — DPO TransactionToken or RealPay InstalmentReferenceNumber
 *   failure_reason    — gateway error message on failure
 *   triggered_by      — admin user ID who clicked the button
 *   created_at / updated_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_collection_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('policy_id')->index();
            $table->string('policy_number', 60)->index();
            $table->unsignedBigInteger('customer_id')->index();

            $table->enum('payment_method', ['DPO', 'REALPAY'])->index();
            $table->decimal('amount', 12, 2);

            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->index();

            // Gateway-specific reference on success
            $table->string('gateway_reference', 120)->nullable();

            // Error detail on failure
            $table->string('failure_reason', 500)->nullable();

            // Which admin user triggered it (nullable = triggered by cron / system)
            $table->unsignedBigInteger('triggered_by')->nullable()->index();

            $table->timestamps();

            // Useful for "did we already try this today?" checks
            $table->index(['policy_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_collection_events');
    }
};
