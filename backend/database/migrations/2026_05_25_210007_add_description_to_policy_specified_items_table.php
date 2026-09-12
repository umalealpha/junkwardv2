<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a free-text per-item descriptor (make / model / serial / IMEI / year)
 * to policy_specified_items. BizSure's BUSINESSALLRISKS portable_items[]
 * payload carries this detail, and UW will also be able to edit it post-
 * creation via the existing Specified Items manage-coverages UI.
 *
 * Additive + nullable: existing manual-entry BAR / ELECTRONICEQUIPMENT /
 * PERSONALALLRISKS rows have no descriptor and continue to work.
 */
class AddDescriptionToPolicySpecifiedItemsTable extends Migration
{
    public function up(): void
    {
        Schema::table('policy_specified_items', function (Blueprint $table) {
            if (! Schema::hasColumn('policy_specified_items', 'description')) {
                $table->string('description', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('policy_specified_items', function (Blueprint $table) {
            if (Schema::hasColumn('policy_specified_items', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
}
