<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * reinsurance_group was created WITHOUT a PRIMARY KEY, and its `id` column is
 * `int(11) DEFAULT NULL` with no AUTO_INCREMENT. Every group inserted through
 * the app therefore got id = NULL: groups collided, insertGetId() returned a
 * bogus value, and the reinsurance calc (which matches groups by id, e.g.
 * MotorTradersReinsurance's `n_GroupMaster_PK IN (13,31)`) never found newly
 * created groups.
 *
 * This migration:
 *   1. backfills any NULL ids with unique values continuing from MAX(id), then
 *   2. makes `id` a proper AUTO_INCREMENT PRIMARY KEY.
 *
 * Idempotent: it does nothing if a PRIMARY KEY already exists. It assumes the
 * non-null ids are already unique — an ADD PRIMARY KEY over duplicate ids fails,
 * so resolve any duplicates (SELECT id, COUNT(*) ... GROUP BY id HAVING COUNT(*)>1)
 * before running if this table was hand-edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reinsurance_group')) {
            return;
        }

        // Already fixed? (PRIMARY KEY present) -> nothing to do.
        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'reinsurance_group'
              AND INDEX_NAME   = 'PRIMARY'
        ");
        if ($hasPk && (int) $hasPk->c > 0) {
            return;
        }

        // 1. Give every NULL id a unique value so a PRIMARY KEY can be added.
        DB::statement('SET @i := (SELECT COALESCE(MAX(id), 0) FROM reinsurance_group)');
        DB::statement('UPDATE reinsurance_group SET id = (@i := @i + 1) WHERE id IS NULL');

        // 2. Make id an AUTO_INCREMENT PRIMARY KEY (auto-seeds from MAX(id)+1).
        DB::statement('ALTER TABLE reinsurance_group ADD PRIMARY KEY (id), MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if (! Schema::hasTable('reinsurance_group')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'reinsurance_group'
              AND INDEX_NAME   = 'PRIMARY'
        ");
        if (! $hasPk || (int) $hasPk->c === 0) {
            return;
        }

        // Strip AUTO_INCREMENT before dropping the key, then restore the
        // original nullable column definition. Data is left untouched.
        DB::statement('ALTER TABLE reinsurance_group MODIFY COLUMN id INT NOT NULL');
        DB::statement('ALTER TABLE reinsurance_group DROP PRIMARY KEY');
        DB::statement('ALTER TABLE reinsurance_group MODIFY COLUMN id INT NULL');
    }
};
