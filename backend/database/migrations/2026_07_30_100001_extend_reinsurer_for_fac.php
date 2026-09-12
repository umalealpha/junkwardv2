<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC Register — step 1 of 5.
 *
 * `reinsurer` exists (company_name / email / cellphone) but has never held a row.
 * The FAC register needs it to carry both sides of the counterparty relationship:
 * who we PAY (reinsurer or broker) and the terms that seed a placement.
 *
 * Additive only — nothing existing changes behaviour.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('reinsurer')) {
            return; // nothing to extend; the base migration will create it
        }

        Schema::table('reinsurer', function (Blueprint $t) {
            if (!Schema::hasColumn('reinsurer', 'short_code')) {
                $t->string('short_code', 40)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('reinsurer', 'counterparty_type')) {
                // Who we PAY. A broker fronts the placement; the risk carrier is
                // recorded separately on the placement row (free text panel).
                $t->string('counterparty_type', 20)->default('reinsurer')->after('short_code');
            }
            if (!Schema::hasColumn('reinsurer', 'country')) {
                $t->string('country', 2)->default('BW')->after('cellphone');
            }
            if (!Schema::hasColumn('reinsurer', 'default_currency')) {
                $t->string('default_currency', 3)->default('BWP')->after('country');
            }
            if (!Schema::hasColumn('reinsurer', 'vat_applicable')) {
                // Seeds the per-placement VAT flag. Several offshore counterparties
                // are VAT-exclusive — proved on the June master sheet, where
                // Genesis / Maksure / Nile Capital / Reinsurance Solutions /
                // Solid Risk / Oak Tree carry gross == excl-VAT on every line.
                $t->boolean('vat_applicable')->default(true)->after('default_currency');
            }
            if (!Schema::hasColumn('reinsurer', 'default_commission_pct')) {
                $t->decimal('default_commission_pct', 7, 4)->nullable()->after('vat_applicable');
            }
            if (!Schema::hasColumn('reinsurer', 'settlement_terms_days')) {
                $t->unsignedSmallInteger('settlement_terms_days')->nullable()->after('default_commission_pct');
            }
            if (!Schema::hasColumn('reinsurer', 'status')) {
                $t->tinyInteger('status')->default(1)->after('settlement_terms_days');
            }
            if (!Schema::hasColumn('reinsurer', 'notes')) {
                $t->text('notes')->nullable()->after('status');
            }
            if (!Schema::hasColumn('reinsurer', 'deleted_at')) {
                $t->softDeletes();
            }
        });

        // Unique on short_code where present — reports key off it.
        if (Schema::hasColumn('reinsurer', 'short_code')) {
            try {
                Schema::table('reinsurer', function (Blueprint $t) {
                    $t->index('short_code', 'reinsurer_short_code_idx');
                });
            } catch (\Throwable $e) {
                // index already there — harmless
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('reinsurer')) {
            return;
        }
        Schema::table('reinsurer', function (Blueprint $t) {
            foreach ([
                'short_code', 'counterparty_type', 'country', 'default_currency',
                'vat_applicable', 'default_commission_pct', 'settlement_terms_days',
                'status', 'notes', 'deleted_at',
            ] as $col) {
                if (Schema::hasColumn('reinsurer', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
