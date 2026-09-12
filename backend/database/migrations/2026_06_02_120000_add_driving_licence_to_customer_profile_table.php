<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third Party Car Insurance form gap (BUG-033 / BUG-034 / BUG-041 / BUG-042):
 * capture the driver's licence the start.alphadirect.co.bw TP-car form now
 * collects — licence number, class, and validity window.
 *
 * customer_profile already holds the policyholder PII (omang / passport /
 * employer columns) but had no place for the driving-licence details. Adding
 * them here lets ThirdPartyCarController persist the licence instead of
 * dropping it on the floor.
 *
 * All nullable so existing rows/callers that omit them stay valid; the
 * controller writes via array_intersect_key, so the create path is safe on
 * environments where this migration hasn't run yet.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_profile')) return;

        Schema::table('customer_profile', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_profile', 'driving_license')) {
                $table->string('driving_license', 40)->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'license_class')) {
                $table->string('license_class', 20)->nullable()->after('driving_license');
            }
            if (!Schema::hasColumn('customer_profile', 'license_valid_from')) {
                $table->date('license_valid_from')->nullable()->after('license_class');
            }
            if (!Schema::hasColumn('customer_profile', 'license_valid_to')) {
                $table->date('license_valid_to')->nullable()->after('license_valid_from');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_profile')) return;

        Schema::table('customer_profile', function (Blueprint $table) {
            foreach (['license_valid_to', 'license_valid_from', 'license_class', 'driving_license'] as $col) {
                if (Schema::hasColumn('customer_profile', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
