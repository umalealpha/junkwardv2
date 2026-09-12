<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOM/COM CRITICAL-02 (schema audit).
 *
 * policy_coverages, policy_specified_items and policy_coverage_notes don't
 * carry audit columns in the live legacy schema. Legacy writes go through
 * Livewire controllers that stamp user ids in app code, but the columns
 * are missing on the tables. V2 writes through PolicyCreateController
 * which also has nothing to stamp into — rebuilds / audits can't answer
 * "who changed this coverage".
 *
 * Adds created_by / updated_by / signed_by as nullable bigints. Controllers
 * will start filling them (see follow-up commit); existing rows stay NULL.
 */
return new class extends Migration
{
    private array $tables = [
        'policy_coverages',
        'policy_specified_items',
        'policy_coverage_notes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['created_by', 'updated_by', 'signed_by'] as $col) {
                    if (!Schema::hasColumn($table, $col)) {
                        $t->unsignedBigInteger($col)->nullable()->after('updated_at');
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['signed_by', 'updated_by', 'created_by'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
