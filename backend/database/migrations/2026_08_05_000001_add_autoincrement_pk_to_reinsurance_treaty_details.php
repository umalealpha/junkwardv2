<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * reinsurance_treaty_details has the same defect as reinsurance_treaty: no
 * PRIMARY KEY and an `id` column with no AUTO_INCREMENT, so every formula-
 * attachment row inserted by the app carried id = NULL. (Attachments created
 * against a broken NULL-id treaty also landed with treaty_id = 0.)
 *
 * Backfills NULL ids from MAX(id)+1, then makes `id` an AUTO_INCREMENT PRIMARY
 * KEY. Idempotent (skips if a PRIMARY KEY already exists). Assumes non-null ids
 * are unique. Orphaned rows (treaty_id = 0 / NULL) should be removed separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reinsurance_treaty_details')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reinsurance_treaty_details' AND INDEX_NAME = 'PRIMARY'
        ");
        if ($hasPk && (int) $hasPk->c > 0) {
            return;
        }

        DB::statement('SET @i := (SELECT COALESCE(MAX(id), 0) FROM reinsurance_treaty_details)');
        DB::statement('UPDATE reinsurance_treaty_details SET id = (@i := @i + 1) WHERE id IS NULL');
        DB::statement('ALTER TABLE reinsurance_treaty_details ADD PRIMARY KEY (id), MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if (! Schema::hasTable('reinsurance_treaty_details')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reinsurance_treaty_details' AND INDEX_NAME = 'PRIMARY'
        ");
        if (! $hasPk || (int) $hasPk->c === 0) {
            return;
        }

        DB::statement('ALTER TABLE reinsurance_treaty_details MODIFY COLUMN id INT NOT NULL');
        DB::statement('ALTER TABLE reinsurance_treaty_details DROP PRIMARY KEY');
        DB::statement('ALTER TABLE reinsurance_treaty_details MODIFY COLUMN id INT NULL');
    }
};
