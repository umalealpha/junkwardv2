<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engineering specialist tables (CAR / PAR / EAR) need action_id + term_id
 * so each schedule row is scoped to a specific PolicyAction (NEWBUSINESS,
 * RENEW, ENDORSE, …) and PolicyTerm — matching the rest of the V2 coverage
 * stack which keys every persisted artefact on those two columns. Without
 * this, endorsements that re-quote a policy would silently overwrite or
 * collide with the original schedule.
 *
 * Idempotent — guarded with hasColumn so reruns on envs where these were
 * already added by hand stay safe.
 */
return new class extends Migration
{
    private array $tables = ['ear_coverages', 'car_coverages', 'par_coverages'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'action_id')) {
                    $t->unsignedBigInteger('action_id')->nullable()->after('policy_coverage_id');
                    $t->index('action_id', "{$table}_action_id_idx");
                }
                if (!Schema::hasColumn($table, 'term_id')) {
                    $t->unsignedBigInteger('term_id')->nullable()->after('action_id');
                    $t->index('term_id', "{$table}_term_id_idx");
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'action_id')) {
                    try { $t->dropIndex("{$table}_action_id_idx"); } catch (\Throwable $e) {}
                    $t->dropColumn('action_id');
                }
                if (Schema::hasColumn($table, 'term_id')) {
                    try { $t->dropIndex("{$table}_term_id_idx"); } catch (\Throwable $e) {}
                    $t->dropColumn('term_id');
                }
            });
        }
    }
};
