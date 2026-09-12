<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Holding table for unconfirmed Motor Comprehensive quotes that
     * have been priced + accepted by the customer but haven't paid yet.
     *
     * Quote lifecycle:
     *   draft        — customer is still on /quote/motor (we don't write here)
     *   pending_pay  — POST /public/policies/create persisted the form,
     *                  DPO redirect URL has been minted with the
     *                  quote_number as CompanyRef
     *   paid         — DPO success webhook flipped the row; the admin
     *                  side worker (legacy graphite createPolicy logic
     *                  re-implemented as a job) materialises this into
     *                  Policy + Customer + Motor rows
     *   abandoned    — DPO declined or timed out (PTL=2 hours)
     *   expired      — never paid; reaper sweeps after 7 days
     *
     * Keeping the customer-facing flow on its own table means we don't
     * pollute `policies` with rows that may never become real policies
     * — and the admin worker can be deployed independently of the
     * customer site.
     */
    public function up(): void
    {
        Schema::create('motor_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 32)->unique();

            // Authorising session (so a refresh / re-enter can resume).
            $table->string('cellphone', 24)->index();
            $table->string('auth_token_hash', 64)->nullable();

            // Rating engine references — lets us re-quote from the same
            // inputs without re-hitting the upstream service.
            $table->unsignedBigInteger('rate_id')->nullable();

            // Form payload — JSON to keep schema flat and forwards-compatible.
            // The shape mirrors src/pages/tiles/MotorComprehensive.tsx MotorForm.
            $table->json('customer_payload');
            $table->json('vehicle_payload');

            // Pricing snapshot — what the customer agreed to.
            $table->decimal('sum_insured', 12, 2);
            $table->decimal('premium_monthly', 10, 2)->nullable();
            $table->decimal('premium_quarterly', 10, 2)->nullable();
            $table->decimal('premium_annual', 10, 2)->nullable();
            $table->enum('premium_frequency', ['monthly', 'quarterly', 'annual'])->default('annual');
            $table->decimal('amount_to_pay', 10, 2);

            $table->enum('status', ['pending_pay', 'paid', 'abandoned', 'expired'])
                  ->default('pending_pay')->index();
            $table->string('dpo_trans_token', 100)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('materialised_policy_id')->nullable()->index();

            $table->timestamp('expires_at')->index();
            $table->string('client_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motor_quotes');
    }
};
