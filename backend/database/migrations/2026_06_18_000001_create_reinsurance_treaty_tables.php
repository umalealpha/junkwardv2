<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reinsurance treaty configuration + results store.
 * Schema per the RI spec — all rates/retentions/layers are READ from these
 * tables; the engine never hard-codes them.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('treaty_master')) {
            Schema::create('treaty_master', function (Blueprint $t) {
                $t->id();
                $t->string('treaty_id', 60)->index();
                $t->unsignedSmallInteger('treaty_year');
                $t->enum('treaty_type', ['QS', 'SURPLUS', 'XL', 'FAC']);
                $t->string('class_of_business', 80)->nullable();
                $t->string('currency', 8)->default('BWP');
                $t->date('effective_date')->nullable();
                $t->date('expiry_date')->nullable();
                $t->enum('basis', ['per_risk', 'per_event', 'per_policy'])->default('per_risk');
                $t->string('status', 20)->default('active');
                $t->boolean('is_demo')->default(false)->comment('DEMO config — not real treaty terms');
                $t->timestamps();
                $t->unique(['treaty_id', 'treaty_year']);
            });
        }
        if (!Schema::hasTable('treaty_proportional')) {
            Schema::create('treaty_proportional', function (Blueprint $t) {
                $t->id();
                $t->string('treaty_id', 60)->index();
                $t->unsignedSmallInteger('treaty_year');
                $t->decimal('cession_pct', 7, 4)->nullable()->comment('QS');
                $t->decimal('retention_amount', 18, 2)->nullable()->comment('Surplus/FAC line size');
                $t->unsignedSmallInteger('lines')->nullable()->comment('Surplus');
                $t->decimal('treaty_capacity', 18, 2)->nullable();
                $t->enum('commission_basis', ['FLAT', 'SLIDING', 'PROFIT'])->default('FLAT');
                $t->decimal('commission_rate_flat', 7, 4)->nullable();
                $t->decimal('slide_provisional_rate', 7, 4)->nullable();
                $t->decimal('slide_min_rate', 7, 4)->nullable();
                $t->decimal('slide_max_rate', 7, 4)->nullable();
                $t->json('slide_loss_ratio_bands')->nullable();
                $t->decimal('profit_commission_pct', 7, 4)->nullable();
                $t->decimal('reinsurer_mgmt_expense_pct', 7, 4)->nullable();
                $t->boolean('loss_carryforward_flag')->default(false);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('treaty_xl_layers')) {
            Schema::create('treaty_xl_layers', function (Blueprint $t) {
                $t->id();
                $t->string('treaty_id', 60)->index();
                $t->unsignedSmallInteger('treaty_year');
                $t->unsignedSmallInteger('layer_no');
                $t->decimal('layer_limit', 18, 2);
                $t->decimal('layer_attachment', 18, 2)->comment('= priority for layer 1');
                $t->enum('premium_basis', ['ROL', 'PCT_GNPI', 'FLAT'])->default('ROL');
                $t->decimal('layer_premium_or_rate', 18, 6)->nullable();
                $t->decimal('gnpi', 18, 2)->nullable();
                $t->unsignedSmallInteger('num_reinstatements')->default(0);
                $t->unsignedSmallInteger('free_reinstatements')->default(0);
                $t->decimal('reinstatement_pct', 7, 4)->default(1);
                $t->decimal('aggregate_deductible', 18, 2)->nullable();
                $t->decimal('aggregate_limit', 18, 2)->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('reinsurer_shares')) {
            Schema::create('reinsurer_shares', function (Blueprint $t) {
                $t->id();
                $t->string('treaty_id', 60)->index();
                $t->unsignedSmallInteger('treaty_year');
                $t->string('reinsurer_id', 60);
                $t->decimal('share_pct', 7, 4);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('ri_computations')) {
            Schema::create('ri_computations', function (Blueprint $t) {
                $t->id();
                $t->string('policy_number', 80)->nullable()->index();
                $t->string('claim_number', 80)->nullable()->index();
                $t->string('treaty_id', 60)->nullable();
                $t->unsignedSmallInteger('treaty_year')->nullable();
                $t->string('structure', 12)->nullable();
                $t->decimal('gross_premium', 18, 2)->nullable();
                $t->decimal('sum_insured', 18, 2)->nullable();
                $t->decimal('gross_claim', 18, 2)->nullable();
                $t->decimal('ceded_premium', 18, 2)->nullable();
                $t->decimal('ceding_commission', 18, 2)->nullable();
                $t->decimal('profit_commission', 18, 2)->nullable();
                $t->decimal('claim_recovery', 18, 2)->nullable();
                $t->decimal('reinstatement_premium', 18, 2)->nullable();
                $t->decimal('net_retained', 18, 2)->nullable();
                $t->json('breakdown')->nullable()->comment('full record incl. XL layers + reinsurer split');
                $t->boolean('reconciles')->default(false);
                $t->boolean('is_demo')->default(false);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['ri_computations', 'reinsurer_shares', 'treaty_xl_layers', 'treaty_proportional', 'treaty_master'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
