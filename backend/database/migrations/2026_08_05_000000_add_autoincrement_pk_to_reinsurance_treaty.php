<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * reinsurance_treaty was created WITHOUT a PRIMARY KEY, and its `id` column is
 * `int` with no AUTO_INCREMENT. Treaties created through the admin API
 * (insertGetId) therefore got id = NULL and returned 0, so the row could never
 * be updated — every "update" inserted another broken duplicate (observed on
 * test: three identical GENERAL_QS_2026_2027 rows, all id NULL).
 *
 * Backfills any NULL ids from MAX(id)+1, then makes `id` an AUTO_INCREMENT
 * PRIMARY KEY. Idempotent: does nothing if a PRIMARY KEY already exists.
 * Assumes non-null ids are unique — resolve duplicates before running if the
 * table was hand-edited. NULL-id rows are typically broken duplicates; review
 * and dedupe after this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reinsurance_treaty')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reinsurance_treaty' AND INDEX_NAME = 'PRIMARY'
        ");
        if ($hasPk && (int) $hasPk->c > 0) {
            return;
        }

        DB::statement('SET @i := (SELECT COALESCE(MAX(id), 0) FROM reinsurance_treaty)');
        DB::statement('UPDATE reinsurance_treaty SET id = (@i := @i + 1) WHERE id IS NULL');
        DB::statement('ALTER TABLE reinsurance_treaty ADD PRIMARY KEY (id), MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if (! Schema::hasTable('reinsurance_treaty')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reinsurance_treaty' AND INDEX_NAME = 'PRIMARY'
        ");
        if (! $hasPk || (int) $hasPk->c === 0) {
            return;
        }

        DB::statement('ALTER TABLE reinsurance_treaty MODIFY COLUMN id INT NOT NULL');
        DB::statement('ALTER TABLE reinsurance_treaty DROP PRIMARY KEY');
        DB::statement('ALTER TABLE reinsurance_treaty MODIFY COLUMN id INT NULL');
    }
};
