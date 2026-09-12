<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PI-01 (schema audit).
 *
 * SpecialistCoverageController::store() already accepts
 * policy_inception_date, policy_expiry_date and today_date on the
 * professional-indemnity form (see FORM_CONFIGS['professional-indemnity']),
 * but the prod DB has no columns for them — the buildPayload filter drops
 * every save silently (lastDropped fires but the operator rarely notices).
 *
 * Adds the three dates as nullable DATEs so the save round-trips.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('professional_indemnity_coverages')) {
            return;
        }

        Schema::table('professional_indemnity_coverages', function (Blueprint $t) {
            if (!Schema::hasColumn('professional_indemnity_coverages', 'policy_inception_date')) {
                $t->date('policy_inception_date')->nullable()->after('period_of_insurance');
            }
            if (!Schema::hasColumn('professional_indemnity_coverages', 'policy_expiry_date')) {
                $t->date('policy_expiry_date')->nullable()->after('policy_inception_date');
            }
            if (!Schema::hasColumn('professional_indemnity_coverages', 'today_date')) {
                $t->date('today_date')->nullable()->after('policy_expiry_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('professional_indemnity_coverages')) {
            return;
        }

        Schema::table('professional_indemnity_coverages', function (Blueprint $t) {
            foreach (['today_date', 'policy_expiry_date', 'policy_inception_date'] as $col) {
                if (Schema::hasColumn('professional_indemnity_coverages', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
