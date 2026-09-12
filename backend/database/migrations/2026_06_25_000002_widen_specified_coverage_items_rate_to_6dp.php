<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure the master specified-item rate (specified_coverage_items.rate) can
 * hold 6 decimal places so new policy items pull the true rate (e.g.
 * 0.005593%) rather than a 4-dp rounded value.
 *
 * SAFE/conditional: only widens to decimal(12,6) when the column's current
 * scale is BELOW 6 — so it never narrows a column that already stores more
 * precision, and is a no-op when the master already holds >= 6 dp (which the
 * admin UI showing 0.005593 suggests it does). Existing nullability is
 * preserved. No data backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('specified_coverage_items')
            || !Schema::hasColumn('specified_coverage_items', 'rate')) {
            return;
        }

        $info = DB::selectOne(
            "SELECT NUMERIC_SCALE AS scale, IS_NULLABLE AS nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'specified_coverage_items'
               AND COLUMN_NAME = 'rate'"
        );

        // Only widen when scale is known and currently below 6 dp.
        if (!$info || $info->scale === null || (int) $info->scale >= 6) {
            return;
        }

        $null = strtoupper((string) $info->nullable) === 'YES' ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `specified_coverage_items` MODIFY `rate` DECIMAL(12,6) {$null}");
    }

    public function down(): void
    {
        // No-op: widening rate precision is non-destructive and we don't store
        // the original (per-environment) precision to safely revert to.
    }
};
