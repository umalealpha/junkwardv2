<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * machinery_breakdown_lop_claims — Machinery Breakdown Loss of Profit claim
     * sub-table (form AD Machinery Breakdown Loss of Profit v1.0). Consequential
     * financial loss following a Machinery Breakdown event; completed after the
     * physical-damage MB claim. Mirrors the machinery_breakdown_claims pattern:
     * newclaim_id FK to new_claims, policyNumber, one nullable column per form
     * field grouped by the PDF sections. Money / count figures are stored as
     * strings (best-estimate free text; final quantum is agreed with the
     * appointed forensic accountant). Yes/No questions are booleans with a
     * paired details column. Idempotent create guard — safe to re-run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('machinery_breakdown_lop_claims')) {
            Schema::create('machinery_breakdown_lop_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->integer('claim_sub_type_id')->nullable();

                // Linked Machinery Breakdown Claim
                $table->string('mb_claim_number')->nullable();
                $table->date('date_of_breakdown')->nullable();
                $table->string('mb_physical_claim_status')->nullable();

                // Insured Contact & Business
                $table->string('insured')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('designation')->nullable();
                $table->string('phone_cellphone')->nullable();
                $table->string('email')->nullable();
                $table->text('postal_physical_address')->nullable();
                $table->text('site_premises_affected')->nullable();
                $table->text('nature_of_business')->nullable();
                $table->string('years_in_operation')->nullable();

                // Operating Profile (Pre-Loss Baseline)
                $table->string('production_capacity')->nullable();
                $table->string('operating_hours_per_day')->nullable();
                $table->string('number_of_shifts')->nullable();
                $table->string('operating_days_per_week')->nullable();
                $table->string('standard_turnover_prior_12m')->nullable();
                $table->string('standard_output_prior_12m')->nullable();
                $table->boolean('is_seasonal')->nullable();
                $table->text('seasonal_details')->nullable();
                $table->text('peak_months_pattern')->nullable();
                $table->string('comparable_period_turnover')->nullable();
                $table->string('comparable_period_output')->nullable();

                // Indemnity Period & Downtime
                $table->text('damaged_item_description')->nullable();
                $table->date('date_production_halted')->nullable();
                $table->string('time_excess_start_end')->nullable();
                $table->date('date_production_partial_resumed')->nullable();
                $table->date('date_production_full_resumed')->nullable();
                $table->string('total_full_shutdown_days')->nullable();
                $table->string('total_reduced_capacity_days')->nullable();
                $table->date('indemnity_period_max_end_date')->nullable();
                $table->boolean('loss_continuing')->nullable();
                $table->text('loss_continuing_details')->nullable();
                $table->text('production_impact_description')->nullable();

                // Loss of Profit — Computation A: Reduction in Turnover / Output
                $table->string('standard_turnover_indemnity')->nullable();
                $table->string('actual_turnover_indemnity')->nullable();
                $table->string('reduction_in_turnover')->nullable();
                $table->string('gross_profit_rate')->nullable();
                $table->string('gross_profit_lost')->nullable();

                // Computation B: Increased Cost of Working (ICW)
                $table->string('icw_outsourcing')->nullable();
                $table->string('icw_equipment_hire')->nullable();
                $table->string('icw_express_freight')->nullable();
                $table->string('icw_overtime_labour')->nullable();
                $table->string('icw_temporary_premises')->nullable();
                $table->string('icw_other')->nullable();
                $table->string('icw_total')->nullable();

                // Computation C: Savings (variable costs not incurred)
                $table->string('savings_raw_materials')->nullable();
                $table->string('savings_power_utilities')->nullable();
                $table->string('savings_wages')->nullable();
                $table->string('savings_other')->nullable();
                $table->string('savings_total')->nullable();

                // Computation D: Estimated Loss of Profit Claim
                $table->string('gross_loss_of_profit')->nullable();
                $table->string('less_time_excess')->nullable();
                $table->string('less_self_insured_retention')->nullable();
                $table->string('net_estimated_claim')->nullable();

                // Mitigation Actions
                $table->text('mitigation_steps')->nullable();
                $table->boolean('alt_production_available')->nullable();
                $table->text('alt_production_details')->nullable();
                $table->boolean('replacement_equipment_sourced')->nullable();
                $table->text('replacement_supplier_terms')->nullable();

                // Other Insurance & Loss History
                $table->boolean('other_bi_cover')->nullable();
                $table->text('other_bi_details')->nullable();
                $table->text('other_insurer_policy')->nullable();
                $table->text('loss_history')->nullable();

                // Declaration
                $table->string('declaration_name')->nullable();
                $table->string('declaration_capacity')->nullable();
                $table->date('declaration_date')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_breakdown_lop_claims');
    }
};
