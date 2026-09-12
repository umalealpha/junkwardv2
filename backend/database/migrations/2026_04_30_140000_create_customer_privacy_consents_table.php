<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Append-only audit trail of customer privacy / T&C consents.
     * Different from customer_contact_preferences (which tracks how
     * we should reach a customer + WhatsApp opt-in) — this table
     * records the legal acceptance of the privacy policy + T&C +
     * data-processing for KYC. NBFIRA + DPA-grade audit requires:
     *
     *   - exact text version the customer agreed to (so we can
     *     prove what they consented to even if the policy is
     *     edited later)
     *   - timestamp + IP + user-agent (forensics)
     *   - per-checkbox state (separate accepted_* booleans, not a
     *     single "agreed all" — DPA expects granular consent)
     *
     * Append-only: never UPDATE a row. A customer revoking consent
     * adds a new row with revoked_at populated, leaving the original
     * in place for audit.
     */
    public function up(): void
    {
        Schema::create('customer_privacy_consents', function (Blueprint $table) {
            $table->id();
            $table->string('cellphone', 24)->index();
            $table->string('email', 160)->nullable()->index();

            // Per-checkbox state — DPA-style granular consent.
            $table->boolean('accepted_terms')->default(false);
            $table->boolean('accepted_privacy')->default(false);
            $table->boolean('accepted_data_processing')->default(false);
            $table->boolean('accepted_marketing')->default(false); // optional

            // Version pins so we can prove which text was agreed to.
            $table->string('terms_version', 32)->default('v1.0');
            $table->string('privacy_version', 32)->default('v1.0');

            // Where the consent was captured — landing, motor, kyc, edit.
            $table->string('source', 32)->default('landing');

            // Forensics
            $table->timestamp('accepted_at')->useCurrent()->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Revocation. The original consent row stays intact; a new row
            // is inserted with revoked_at + a reference to the prior id.
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revokes_id')->nullable();
            $table->string('revoke_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['cellphone', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_privacy_consents');
    }
};
