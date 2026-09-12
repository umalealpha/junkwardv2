<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * reinsurance_group_coverage has the same defect as reinsurance_group: no
 * PRIMARY KEY and an `id` column with no AUTO_INCREMENT, so every mapping row
 * inserted by the app carried id = NULL. That breaks edit/delete-by-id in the
 * Reinsurance coverage-grouping UI.
 *
 * Backfills NULL ids from MAX(id)+1, then makes `id` an AUTO_INCREMENT PRIMARY
 * KEY. Idempotent (skips if a PRIMARY KEY already exists). Assumes non-null ids
 * are unique — an ADD PRIMARY KEY over duplicates fails, so resolve duplicates
 * first if the table was hand-edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reinsurance_group_coverage')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'reinsurance_group_coverage'
              AND INDEX_NAME   = 'PRIMARY'
        ");
        if ($hasPk && (int) $hasPk->c > 0) {
            return;
        }

        DB::statement('SET @i := (SELECT COALESCE(MAX(id), 0) FROM reinsurance_group_coverage)');
        DB::statement('UPDATE reinsurance_group_coverage SET id = (@i := @i + 1) WHERE id IS NULL');

        DB::statement('ALTER TABLE reinsurance_group_coverage ADD PRIMARY KEY (id), MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if (! Schema::hasTable('reinsurance_group_coverage')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'reinsurance_group_coverage'
              AND INDEX_NAME   = 'PRIMARY'
        ");
        if (! $hasPk || (int) $hasPk->c === 0) {
            return;
        }

        DB::statement('ALTER TABLE reinsurance_group_coverage MODIFY COLUMN id INT NOT NULL');
        DB::statement('ALTER TABLE reinsurance_group_coverage DROP PRIMARY KEY');
        DB::statement('ALTER TABLE reinsurance_group_coverage MODIFY COLUMN id INT NULL');
    }
};
