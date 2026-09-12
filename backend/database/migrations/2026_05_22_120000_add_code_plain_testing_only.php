<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * TESTING-ONLY: temporary plaintext OTP column on public_otps.
     *
     * ⚠ DO NOT DEPLOY TO PRODUCTION. ⚠
     *
     * This reintroduces the legacy plaintext-OTP anti-pattern that the
     * 2026-04-29 hardening migration explicitly removed (see that file's
     * comment: "Plaintext never persists"). It exists solely so a
     * developer can read the issued OTP from the DB during local QA.
     *
     * Safety rails:
     *  - PublicOtpService writes NULL when APP_ENV === 'production'
     *  - Column is nullable so production rows stay clean even if env
     *    drifts and the guard is bypassed
     *  - This migration MUST be rolled back (down()) before any merge
     *    to main / deploy to stage or prod
     *
     * Audit: storing auth factors in clear violates AD-POL-AI-GOV-001 v1.2
     * and Botswana DPA classification of authentication credentials.
     * Requested for local testing only; remove ASAP.
     */
    public function up(): void
    {
        Schema::table('public_otps', function (Blueprint $table) {
            $table->string('code_plain', 10)
                  ->nullable()
                  ->after('code_hash')
                  ->comment('TESTING ONLY — drop before prod deploy. Plaintext OTP for local QA.');
        });
    }

    public function down(): void
    {
        Schema::table('public_otps', function (Blueprint $table) {
            $table->dropColumn('code_plain');
        });
    }
};
