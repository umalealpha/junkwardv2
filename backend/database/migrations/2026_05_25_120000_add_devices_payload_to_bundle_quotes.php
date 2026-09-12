<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bundle_quotes.devices_payload — captures the FE-sent device array
 * (deviceType/imei/make/model/value) alongside lines_payload.
 *
 * Background: the create-bundle endpoint validated and persisted lines
 * but silently dropped the devices array, leaving the materialiser with
 * no IMEI/make/model to seed policy_cellphone + pending_device_preinspection.
 * Customer had to re-enter device data during preinspection — UX bug
 * compounded by a hidden data-loss bug.
 *
 * Nullable: backfill-safe; older paid bundles without devices are valid.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bundle_quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('bundle_quotes', 'devices_payload')) {
                $table->json('devices_payload')->nullable()->after('lines_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bundle_quotes', function (Blueprint $table) {
            if (Schema::hasColumn('bundle_quotes', 'devices_payload')) {
                $table->dropColumn('devices_payload');
            }
        });
    }
};
