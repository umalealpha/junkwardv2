<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add a human-readable `reason` to public_otps (2026-06-25).
     *
     * Records WHY each OTP was sent, derived server-side from the
     * functionality that triggered it (see AlphaDirect\Services\OtpReason).
     * `purpose` stays the security replay-binding; `reason` is the audit-
     * friendly description, e.g. "Purchasing Legal Insurance cover (P49/month)".
     *
     * Forward-only: existing rows keep NULL.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('public_otps', 'reason')) {
            Schema::table('public_otps', function (Blueprint $table) {
                $table->string('reason', 191)->nullable()->after('purpose');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('public_otps', 'reason')) {
            Schema::table('public_otps', function (Blueprint $table) {
                $table->dropColumn('reason');
            });
        }
    }
};
