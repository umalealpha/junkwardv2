<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * reinsurance_formula and reinsurance_formula_details have no PRIMARY KEY and no
 * AUTO_INCREMENT on `id` — the column is a plain nullable int. Their sibling
 * tables (reinsurance_group, reinsurance_group_coverage) both have it.
 *
 * Consequence: creating a formula through the API inserts a row with id = NULL,
 * insertGetId() returns 0, and the reinsurance_formula_details row is written
 * with formula_id = 0. The RI compute joins fd.formula_id = fm.id, so a formula
 * added this way is orphaned on creation and never applies to any policy. The 65
 * existing formulas only have usable ids because they were migrated in with
 * explicit values.
 *
 * This migration makes `id` a real auto-increment primary key on both tables and
 * seeds AUTO_INCREMENT past the highest existing id, so legacy rows keep their
 * ids and new rows get the next free one.
 *
 * Structural only. No row is inserted, updated or deleted, and no reinsurance
 * calculation changes — this only lets new formulas receive an id.
 *
 * Idempotent: each step is guarded on the current schema state, so it is safe to
 * run on production and safe to re-run. It refuses to touch a table whose id
 * column holds NULLs or duplicates rather than silently mangling the data.
 */
return new class extends Migration
{
    /** @var string[] */
    private array $tables = [
        'reinsurance_formula',
        'reinsurance_formula_details',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id')) {
                continue;
            }

            $this->assertIdSafeForPrimaryKey($table);

            if (!$this->hasPrimaryKey($table)) {
                // id must be NOT NULL before it can carry a primary key.
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL");
                DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
            }

            if (!$this->isAutoIncrement($table)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
            }

            // Start after the highest legacy id so nothing collides.
            $next = ((int) DB::table($table)->max('id')) + 1;
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id')) {
                continue;
            }

            // Drop AUTO_INCREMENT first — MySQL will not drop the key under it.
            if ($this->isAutoIncrement($table)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL");
            }
            if ($this->hasPrimaryKey($table)) {
                DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY");
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT(11) NULL");
        }
    }

    /**
     * A NULL or duplicate id would make ADD PRIMARY KEY fail midway, or worse,
     * coerce NULLs to 0. Refuse loudly instead.
     */
    private function assertIdSafeForPrimaryKey(string $table): void
    {
        $row = DB::selectOne(
            "SELECT COUNT(*) AS rows_total, COUNT(DISTINCT `id`) AS ids_distinct,
                    SUM(CASE WHEN `id` IS NULL THEN 1 ELSE 0 END) AS ids_null
             FROM `{$table}`"
        );

        if ((int) $row->ids_null > 0 || (int) $row->rows_total !== (int) $row->ids_distinct) {
            throw new RuntimeException(
                "Cannot add a primary key to {$table}: id holds {$row->ids_null} NULL(s) and "
                . "{$row->ids_distinct} distinct value(s) across {$row->rows_total} row(s). "
                . 'Deduplicate and backfill id first.'
            );
        }
    }

    private function hasPrimaryKey(string $table): bool
    {
        return DB::selectOne(
            "SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = 'PRIMARY' LIMIT 1",
            [$table]
        ) !== null;
    }

    private function isAutoIncrement(string $table): bool
    {
        return DB::selectOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'id'
               AND extra LIKE '%auto_increment%' LIMIT 1",
            [$table]
        ) !== null;
    }
};
