<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endorsement support columns for policy_specified_items (Miscellaneous
 * Items). Mirrors the specialist-coverages pattern
 * (see 2026_05_25_100002_add_endorsement_columns_to_specialist_coverages_table.php)
 * so writeLineLevelProRata + the $deltaSpec pro-rata sum can stamp and read
 * the per-row delta for ALL products (COM/DOM Fire misc included), not just
 * the specialist set:
 *
 *   pro_rate_premium       decimal(20,2)  — signed; NEGATIVE on cancel/refund
 *   previousActionIdCov    unsignedBigInt — set to the ENDORSE action_id when
 *                          this row is added/changed in that transaction;
 *                          NULL/0 otherwise. The gate writeLineLevelProRata
 *                          and $deltaSpec use to pick up only wizard-touched
 *                          rows. Without it an added misc item rates P0.
 *   endors_flag            tinyInt        — 1 once stamped by an endorse calc.
 *
 * Idempotent + column-guarded: a no-op on environments where the legacy
 * schema already carries these columns. Touches ONLY policy_specified_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('policy_specified_items')) {
            return;
        }

        Schema::table('policy_specified_items', function (Blueprint $t) {
            if (!Schema::hasColumn('policy_specified_items', 'pro_rate_premium')) {
                $t->decimal('pro_rate_premium', 20, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('policy_specified_items', 'previousActionIdCov')) {
                $t->unsignedBigInteger('previousActionIdCov')->default(0)->nullable();
            }
            if (!Schema::hasColumn('policy_specified_items', 'endors_flag')) {
                $t->tinyInteger('endors_flag')->default(0)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('policy_specified_items')) {
            return;
        }

        Schema::table('policy_specified_items', function (Blueprint $t) {
            foreach (['pro_rate_premium', 'previousActionIdCov', 'endors_flag'] as $col) {
                if (Schema::hasColumn('policy_specified_items', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
