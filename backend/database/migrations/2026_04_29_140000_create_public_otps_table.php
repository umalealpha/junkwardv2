<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Hardened OTP table for the customer-facing start.alphadirect.co.bw
     * site. Replaces the legacy `otp` table which stored plaintext codes.
     *
     * Improvements over legacy:
     *  - codes stored hashed (SHA-256), never plaintext
     *  - per-purpose binding so a customer-auth OTP can't be replayed at
     *    the policy-edit endpoint
     *  - explicit expires_at + consumed_at (no global OTPTemp row)
     *  - ip + user_agent captured for fraud forensics
     *  - failed verification counter so we can lock out brute force
     */
    public function up(): void
    {
        Schema::create('public_otps', function (Blueprint $table) {
            $table->id();
            $table->string('cellphone', 24)->index();

            // What the OTP is good for. Restricts replay across flows.
            $table->enum('purpose', [
                'customer_auth',     // Generic identity confirmation
                'policy_edit',       // Editing an existing policy
                'kyc_update',        // KYC document refresh
                'vehicle_inspect',   // Vehicle pre-inspection submission
                'payment_authorize', // RealPay banking step
            ])->index();

            // SHA-256 of the 6-digit code. Plaintext never persists.
            $table->string('code_hash', 64);

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();

            // Brute-force counter. After N failures we mark the row consumed
            // so the customer must request a fresh OTP.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            // Active OTPs per phone+purpose are the only ones we look up.
            $table->index(['cellphone', 'purpose', 'consumed_at']);
        });

        // Session tokens minted on successful OTP verify. The FE sends
        // these as `Authorization: Bearer ...` for subsequent requests
        // in the flow (KYC update, policy edit). 10-minute TTL.
        Schema::create('public_session_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('cellphone', 24)->index();
            $table->string('purpose', 32);
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_session_tokens');
        Schema::dropIfExists('public_otps');
    }
};
