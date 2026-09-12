<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialist (non-motor, non-COM/DOM) coverage tables that still lack
 * action_id / term_id scoping. Without these two columns, an ENDORSE
 * cannot replicate the prior action's schedule into a new PolicyAction —
 * rows would silently overwrite or collide with the original.
 *
 * Engineering trio (car/par/ear) and machinery + the marine trio already
 * have action_id (added by earlier migrations). This migration only
 * back-fills:
 *
 *   - travel_coverages
 *   - medical_malpractice_coverages
 *   - professional_indemnity_coverages
 *
 * Idempotent: hasTable + hasColumn guards make reruns safe across envs.
 * Does NOT touch motor*, com_*, dom_*, or any already-scoped table.
 */
return new class extends Migration
{
    private array $tables = [
        'travel_coverages',
        'medical_malpractice_coverages',
        'professional_indemnity_coverages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

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
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'term_id')) {
                    try { $t->dropIndex("{$table}_term_id_idx"); } catch (\Throwable $e) {}
                    $t->dropColumn('term_id');
                }
                if (Schema::hasColumn($table, 'action_id')) {
                    try { $t->dropIndex("{$table}_action_id_idx"); } catch (\Throwable $e) {}
                    $t->dropColumn('action_id');
                }
            });
        }
    }
};
