<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GRA-0117 — rename gra0117_backfill_control.`cursor` -> scan_cursor.
 *
 * `cursor` is a RESERVED WORD in MariaDB: manual SQL (Workbench / the runbook
 * progress queries) fails with a 1064 syntax error unless back-ticked. The
 * Laravel query builder back-ticks automatically (so the command worked), but
 * ops will hit this constantly. Rename it once, here.
 *
 * Guarded both ways so it is a no-op on fresh installs (where the create
 * migration already used scan_cursor) and safe under the fail-loud entrypoint.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('gra0117_backfill_control')
            && Schema::hasColumn('gra0117_backfill_control', 'cursor')
            && !Schema::hasColumn('gra0117_backfill_control', 'scan_cursor')) {
            // back-tick the reserved word in the raw ALTER
            DB::statement('ALTER TABLE gra0117_backfill_control CHANGE `cursor` scan_cursor BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gra0117_backfill_control')
            && Schema::hasColumn('gra0117_backfill_control', 'scan_cursor')
            && !Schema::hasColumn('gra0117_backfill_control', 'cursor')) {
            DB::statement('ALTER TABLE gra0117_backfill_control CHANGE scan_cursor `cursor` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};
