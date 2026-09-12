<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * policy_specified_items.custom_name — holds the free-text description the
 * user types into a "+ Add Custom" specified-item row. Master picks (rows
 * with specified_coverage_id set) continue to join `specified_coverage_items`
 * for their display name; custom rows use this column.
 *
 * Schema::hasColumn-guarded so it's safe on hand-built environments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('policy_specified_items')) return;
        if (Schema::hasColumn('policy_specified_items', 'custom_name')) return;

        Schema::table('policy_specified_items', function (Blueprint $t) {
            $t->string('custom_name', 255)->nullable()->after('specified_coverage_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('policy_specified_items')) return;
        if (!Schema::hasColumn('policy_specified_items', 'custom_name')) return;

        Schema::table('policy_specified_items', function (Blueprint $t) {
            $t->dropColumn('custom_name');
        });
    }
};
