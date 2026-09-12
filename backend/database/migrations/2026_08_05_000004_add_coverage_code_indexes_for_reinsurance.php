<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The reinsurance calc joins coverage codes as TEXT — every handler runs
 *   INNER JOIN tb_cvgpccoverages tc ON tc.s_CoverageCode = gd.coverage_name
 * (gd = reinsurance_group_coverage). Neither column was indexed, so MySQL did a
 * Block Nested Loop, scanning the whole lookup table for each group-coverage row
 * inside every handler — the dominant cost of a slow recompute.
 *
 * Prefix indexes on both columns turn the join into an index ref lookup:
 * measured ~2050ms -> ~420ms on the hot subquery (~5x). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('tb_cvgpccoverages', 'idx_s_CoverageCode', 's_CoverageCode(100)');
        $this->addIndex('reinsurance_group_coverage', 'idx_coverage_name', 'coverage_name(100)');
    }

    public function down(): void
    {
        $this->dropIndex('tb_cvgpccoverages', 'idx_s_CoverageCode');
        $this->dropIndex('reinsurance_group_coverage', 'idx_coverage_name');
    }

    private function addIndex(string $table, string $name, string $colspec): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$table, $name]
        );
        if ($exists && (int) $exists->c > 0) {
            return;
        }
        DB::statement("CREATE INDEX {$name} ON {$table} ({$colspec})");
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$table, $name]
        );
        if (! $exists || (int) $exists->c === 0) {
            return;
        }
        DB::statement("ALTER TABLE {$table} DROP INDEX {$name}");
    }
};
