<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PSI-01 (schema audit).
 *
 * policy_specified_items.motor_id is NOT NULL in prod. V2 writes NULL
 * when saving specified items against a non-motor coverage (legacy
 * behaviour — motor_id only binds motor coverages). The NOT NULL
 * constraint produces either a 500 or truncates via 0, corrupting
 * the per-motor filter on the Edit screen.
 *
 * Make the column nullable. Existing 0-values stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('policy_specified_items')) {
            return;
        }
        if (!Schema::hasColumn('policy_specified_items', 'motor_id')) {
            return;
        }

        // Raw ALTER — doctrine/dbal isn't required on this app and the
        // target column stays INT, only the NULL flag changes.
        DB::statement('ALTER TABLE policy_specified_items MODIFY motor_id INT(11) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('policy_specified_items')) {
            return;
        }
        if (!Schema::hasColumn('policy_specified_items', 'motor_id')) {
            return;
        }

        // Backfill any NULLs to 0 so the NOT NULL constraint can be
        // re-applied without a row-level error.
        DB::table('policy_specified_items')
            ->whereNull('motor_id')
            ->update(['motor_id' => 0]);

        DB::statement('ALTER TABLE policy_specified_items MODIFY motor_id INT(11) NOT NULL DEFAULT 0');
    }
};
