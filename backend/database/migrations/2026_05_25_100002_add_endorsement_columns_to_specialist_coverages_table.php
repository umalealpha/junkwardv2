<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endorsement support columns for the ten specialist coverage tables
 * (non-motor, non-COM/DOM). Mirrors the motor_traders pro-rate pattern
 * (see 2026_05_22_000001_add_pro_rate_columns_to_motor_traders_tables.php)
 * so the same calculator + BackdatedEndorseRefresher contract works here:
 *
 *   pro_rate_premium       decimal(20,2)  — signed; NEGATIVE on cancel
 *   previousActionIdCov    unsignedBigInt — points to the source row's
 *                          action_id when this row was replicated by an
 *                          ENDORSE (NULL/0 on the original NEWBUSINESS).
 *   endors_flag            tinyInt        — 1 once this row has been
 *                          stamped by an endorse calc; lets the UI tell
 *                          "untouched in this transaction" rows apart.
 *
 * Idempotent. Touches ONLY the listed tables. Motor + COM/DOM unaffected.
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
                if (!Schema::hasColumn($table, 'pro_rate_premium')) {
                    $t->decimal('pro_rate_premium', 20, 2)->default(0)->nullable();
                }
                if (!Schema::hasColumn($table, 'previousActionIdCov')) {
                    $t->unsignedBigInteger('previousActionIdCov')->default(0)->nullable();
                }
                if (!Schema::hasColumn($table, 'endors_flag')) {
                    $t->tinyInteger('endors_flag')->default(0)->nullable();
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
                foreach (['pro_rate_premium', 'previousActionIdCov', 'endors_flag'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
