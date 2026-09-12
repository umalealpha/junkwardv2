<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Performance-only: index the reinsurance join columns the RI compute uses.
 *
 * EXPLAIN of the per-group "master query" in
 * PolicyCoverage::getReinsuranceCoverageCalculations showed full table scans
 * (type=ALL) with "Using join buffer (Block Nested Loop)" on the reinsurance
 * config tables because they had NO index on their join columns:
 *   - reinsurance_treaty_details  → nothing on formula_attached / treaty_id
 *   - reinsurance_formula_details → nothing on group_id / formula_id
 *   - policy_coverage_entities    → nothing on policy_coverage_id / entity_id
 * This query runs once per group × ~6 coverage-family passes, so the scans add
 * up. Indexing the join columns lets the optimiser do index lookups instead.
 *
 * Index-only. NO table/column/data change and NO calculation change — the RI
 * numbers are identical before and after; only the query plan changes.
 * (reinsurance_treaty.effective_from/to are deliberately NOT indexed: they are
 * TEXT columns — indexing needs a prefix — and the table is ~27 rows, so a scan
 * is already negligible.)
 *
 * Idempotent: every index is guarded on table/column existence and skipped if
 * an equivalent index is already present, so it is safe to run on production
 * (and to re-run) whatever the current schema state.
 */
return new class extends Migration
{
    /** @var array<int, array{0:string,1:string,2:string}> [table, column, index_name] */
    private array $indexes = [
        ['reinsurance_treaty_details',  'formula_attached',   'idx_rtd_formula_attached'],
        ['reinsurance_treaty_details',  'treaty_id',          'idx_rtd_treaty_id'],
        ['reinsurance_formula_details', 'group_id',           'idx_rfd_group_id'],
        ['reinsurance_formula_details', 'formula_id',         'idx_rfd_formula_id'],
        ['policy_coverage_entities',    'policy_coverage_id', 'idx_pce_policy_coverage_id'],
        ['policy_coverage_entities',    'entity_id',          'idx_pce_entity_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $column, $name]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            // Skip if this named index already exists, or a column is already
            // indexed as an index's leading column (avoids a redundant dup).
            if ($this->indexExists($table, $name) || $this->columnAlreadyLeads($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($column, $name) {
                $t->index($column, $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $column, $name]) {
            if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
                Schema::table($table, function (Blueprint $t) use ($name) {
                    $t->dropIndex($name);
                });
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $name]
        ) !== null;
    }

    private function columnAlreadyLeads(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND seq_in_index = 1 LIMIT 1',
            [$table, $column]
        ) !== null;
    }
};
