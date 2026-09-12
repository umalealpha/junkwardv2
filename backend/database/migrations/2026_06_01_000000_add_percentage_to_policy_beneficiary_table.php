<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-006 (Accidental Death): capture the "Percentage of Proceeds to
 * Beneficiary" the start.alphadirect.co.bw AD form now collects.
 *
 * policy_beneficiary tracks the payee identity + payout method (`payment`)
 * but had no column for the share-of-proceeds split, so the FE value had
 * nowhere to land. V8 left the percentage block commented out; V2 surfaces
 * it (proceeds must total 100% across beneficiaries — enforced FE-side).
 *
 * Nullable + unsigned decimal(5,2) holds 0.00–100.00. AccidentalDeath-
 * Controller writes it via array_intersect_key, so the create path stays
 * safe on environments where this migration hasn't run yet.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('policy_beneficiary')) return;

        Schema::table('policy_beneficiary', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_beneficiary', 'percentage')) {
                $table->decimal('percentage', 5, 2)->nullable()->after('payment');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('policy_beneficiary')) return;

        Schema::table('policy_beneficiary', function (Blueprint $table) {
            if (Schema::hasColumn('policy_beneficiary', 'percentage')) {
                $table->dropColumn('percentage');
            }
        });
    }
};
