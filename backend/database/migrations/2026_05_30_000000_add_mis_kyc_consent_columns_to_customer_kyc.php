<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V8 parity for the MIS Customer KYC tab — the legacy
 * policyDetails_View blade renders two extra slots beyond Omang /
 * Passport / Driving License / Proof of Residence / Proof of Income:
 *
 *   - data_protection_consent   (Data Protection Consent upload)
 *   - Canceled_document          (Cancellation Acknowledgement upload —
 *                                 note V8 stored it with a capital C)
 *
 * Production graphite already carries these columns on `customer_kyc`,
 * but freshly built V2 envs don't, so the FE's per-doc upload card
 * silently no-ops when the upload catalogue maps to a column that
 * doesn't exist. Add them here, additive + nullable, so the same
 * `hasColumn` guards in PolicyCreateController write through.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_kyc')) return;

        Schema::table('customer_kyc', function (Blueprint $table) {
            foreach (['data_protection_consent', 'Canceled_document'] as $col) {
                if (!Schema::hasColumn('customer_kyc', $col)) {
                    // longText to match the other S3-path columns on this
                    // table (the live schema stores paths up to a couple
                    // hundred chars but nothing longer).
                    $table->longText($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_kyc')) return;

        Schema::table('customer_kyc', function (Blueprint $table) {
            foreach (['data_protection_consent', 'Canceled_document'] as $col) {
                if (Schema::hasColumn('customer_kyc', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
