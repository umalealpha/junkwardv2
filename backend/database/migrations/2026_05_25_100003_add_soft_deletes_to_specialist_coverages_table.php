<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancel-and-Reinstate support for the ten specialist coverage tables.
 * The settled motor pattern uses a soft delete on the row (deleted_at)
 * together with a NEGATIVE pro_rate_premium write — Cancel never
 * physically removes the row, it scopes it out of the active set while
 * preserving the audit trail. Reinstate is a deleted_at = NULL update.
 *
 * Adding deleted_at here lets the same Cancel/Reinstate semantics apply
 * to non-motor specialist coverages without inventing a new pattern.
 *
 * Idempotent. Touches ONLY the listed tables.
 */
return new class extends Migration
{
    private array $tables = [
        'car_coverages',
        'par_coverages',
        'ear_coverages',
        'travel_coverages',
        'medical_malpractice_coverages',
        'machinery_breakdown_coverages',
        'professional_indemnity_coverages',
        'marine_directors_officers_coverages',
        'marine_cargo_once_off_coverages',
        'marine_cargo_open_coverages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'deleted_at')) {
                    $t->softDeletes();
                    $t->index('deleted_at', "{$table}_deleted_at_idx");
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
                if (Schema::hasColumn($table, 'deleted_at')) {
                    try { $t->dropIndex("{$table}_deleted_at_idx"); } catch (\Throwable $e) {}
                    $t->dropSoftDeletes();
                }
            });
        }
    }
};
