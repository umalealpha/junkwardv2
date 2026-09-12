<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marine cargo schedule tables — ensure the per-conveyance limit columns
 * the schedule UI saves actually exist in the DB. On envs where the
 * original create migrations ran against an older revision (or a snapshot
 * was restored that pre-dates these columns), buildPayload() drops them
 * silently and operators get "Saved, but these fields were ignored…"
 * warnings without the data round-tripping.
 *
 * Idempotent — each check is hasColumn-gated so reruns are safe.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['marine_cargo_once_off_coverages', 'marine_cargo_open_coverages'] as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'per_conveyance_rail'))   $t->decimal('per_conveyance_rail',   15, 2)->nullable();
                if (!Schema::hasColumn($table, 'per_conveyance_road'))   $t->decimal('per_conveyance_road',   15, 2)->nullable();
                if (!Schema::hasColumn($table, 'per_conveyance_air'))    $t->decimal('per_conveyance_air',    15, 2)->nullable();
                if (!Schema::hasColumn($table, 'per_conveyance_post'))   $t->decimal('per_conveyance_post',   15, 2)->nullable();
                if (!Schema::hasColumn($table, 'per_conveyance_vessel')) $t->decimal('per_conveyance_vessel', 15, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // No-op: these are additive safety columns and dropping them would
        // destroy legitimate schedule data. If a clean rollback is needed,
        // drop the whole table via the original create migration's down().
    }
};
