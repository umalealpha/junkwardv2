<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * reinsurance_type had no PRIMARY KEY and no AUTO_INCREMENT on `id` — the same
 * defect fixed on the group, formula and treaty tables. Its admin CRUD uses
 * insertGetId (ReinsuranceApiController::storeType), so creating a reinsurance
 * type would insert id = NULL and be un-updatable.
 *
 * Backfills any NULL ids, then makes `id` an AUTO_INCREMENT PRIMARY KEY.
 * Idempotent: does nothing if a PRIMARY KEY already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reinsurance_type')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reinsurance_type' AND INDEX_NAME = 'PRIMARY'
        ");
        if ($hasPk && (int) $hasPk->c > 0) {
            return;
        }

        DB::statement('SET @i := (SELECT COALESCE(MAX(id), 0) FROM reinsurance_type)');
        DB::statement('UPDATE reinsurance_type SET id = (@i := @i + 1) WHERE id IS NULL');
        DB::statement('ALTER TABLE reinsurance_type ADD PRIMARY KEY (id), MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if (! Schema::hasTable('reinsurance_type')) {
            return;
        }

        $hasPk = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reinsurance_type' AND INDEX_NAME = 'PRIMARY'
        ");
        if (! $hasPk || (int) $hasPk->c === 0) {
            return;
        }

        DB::statement('ALTER TABLE reinsurance_type MODIFY COLUMN id INT NOT NULL');
        DB::statement('ALTER TABLE reinsurance_type DROP PRIMARY KEY');
        DB::statement('ALTER TABLE reinsurance_type MODIFY COLUMN id INT NULL');
    }
};
