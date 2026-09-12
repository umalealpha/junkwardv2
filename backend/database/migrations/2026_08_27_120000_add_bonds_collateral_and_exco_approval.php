<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bonds (product 23) governance columns.
 *
 * Two controls UW asked for, both enforced by
 * AlphaDirect\Services\Bonds\BondsIssuanceGate:
 *
 *  1. EXCO approval — policy_actions gets the stamp of WHO approved the
 *     transaction. `status = APPROVED` alone never recorded the approver,
 *     so there was nothing to check at issue time.
 *  2. Collateral — bonds_coverages gets structured collateral capture plus
 *     a confirmation stamp. The existing `collateral_security` text column
 *     stays as the policy-wording clause; it is pre-filled with boilerplate
 *     by the schedule form (SpecialistCoveragePage FIELD_DEFAULTS), so it
 *     can never serve as the gate — "non-empty" is always true.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bonds_coverages')) {
            Schema::table('bonds_coverages', function (Blueprint $table) {
                if (!Schema::hasColumn('bonds_coverages', 'collateral_type')) {
                    // Cash Deposit / Bank Guarantee / Parental Guarantee /
                    // Cession / Property — free string, the UI offers a list.
                    $table->string('collateral_type')->nullable()->after('collateral_security');
                }
                if (!Schema::hasColumn('bonds_coverages', 'collateral_value')) {
                    $table->decimal('collateral_value', 15, 2)->nullable()->after('collateral_type');
                }
                if (!Schema::hasColumn('bonds_coverages', 'collateral_reference')) {
                    // Guarantee number / deposit receipt / cession reference.
                    $table->string('collateral_reference')->nullable()->after('collateral_value');
                }
                if (!Schema::hasColumn('bonds_coverages', 'collateral_expiry_date')) {
                    $table->date('collateral_expiry_date')->nullable()->after('collateral_reference');
                }
                if (!Schema::hasColumn('bonds_coverages', 'collateral_confirmed')) {
                    // Written ONLY by the confirm-collateral endpoint, never by
                    // the schedule save form.
                    $table->tinyInteger('collateral_confirmed')->default(0)->after('collateral_expiry_date');
                }
                if (!Schema::hasColumn('bonds_coverages', 'collateral_confirmed_by')) {
                    $table->unsignedBigInteger('collateral_confirmed_by')->nullable()->after('collateral_confirmed');
                }
                if (!Schema::hasColumn('bonds_coverages', 'collateral_confirmed_at')) {
                    $table->timestamp('collateral_confirmed_at')->nullable()->after('collateral_confirmed_by');
                }
            });
        }

        if (Schema::hasTable('policy_actions')) {
            Schema::table('policy_actions', function (Blueprint $table) {
                if (!Schema::hasColumn('policy_actions', 'exco_approved_by')) {
                    $table->unsignedBigInteger('exco_approved_by')->nullable();
                }
                if (!Schema::hasColumn('policy_actions', 'exco_approved_at')) {
                    $table->timestamp('exco_approved_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bonds_coverages')) {
            Schema::table('bonds_coverages', function (Blueprint $table) {
                foreach ([
                    'collateral_type', 'collateral_value', 'collateral_reference',
                    'collateral_expiry_date', 'collateral_confirmed',
                    'collateral_confirmed_by', 'collateral_confirmed_at',
                ] as $col) {
                    if (Schema::hasColumn('bonds_coverages', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('policy_actions')) {
            Schema::table('policy_actions', function (Blueprint $table) {
                foreach (['exco_approved_by', 'exco_approved_at'] as $col) {
                    if (Schema::hasColumn('policy_actions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
