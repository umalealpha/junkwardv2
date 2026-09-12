<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marine_cargo_open_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable()->index();
            $table->unsignedBigInteger('policy_coverage_id')->nullable()->index();
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->string('status')->default('0');
            $table->string('assured_name')->nullable();
            $table->text('assured_address')->nullable();
            $table->string('open_policy_no')->nullable();
            $table->string('agent_broker_code')->nullable();
            $table->string('conveyance')->nullable();
            $table->string('voyage_from')->nullable();
            $table->string('voyage_to')->nullable();
            $table->string('commodities_covered')->nullable();
            $table->string('nature_of_packing')->nullable();
            $table->string('terms_of_cover')->nullable();
            $table->decimal('annual_estimated_turnover', 15, 2)->nullable();
            $table->decimal('location_limit', 15, 2)->nullable();
            $table->decimal('premium_rate', 10, 4)->nullable();
            $table->decimal('sum_insured', 15, 2)->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->json('clauses')->nullable();
            $table->text('survey_claim_settlement')->nullable();
            $table->string('claim_payable_at')->nullable();
            $table->string('claim_payable_by')->nullable();
            $table->string('place')->nullable();
            $table->date('signing_date')->nullable();
            $table->string('examined_by')->nullable();
            $table->text('declaration')->nullable();
            $table->string('basis_of_valuation')->nullable();
            $table->decimal('per_conveyance_rail', 15, 2)->nullable();
            $table->decimal('per_conveyance_road', 15, 2)->nullable();
            $table->decimal('per_conveyance_air', 15, 2)->nullable();
            $table->decimal('per_conveyance_post', 15, 2)->nullable();
            $table->decimal('per_conveyance_vessel', 15, 2)->nullable();
            $table->text('deductible')->nullable();
            $table->date('policy_period_from')->nullable();
            $table->date('policy_period_to')->nullable();
            $table->text('notice_of_cancellation')->nullable();
            $table->text('refund')->nullable();
            $table->text('over_declaration')->nullable();
            $table->text('notes')->nullable();
            $table->date('today_date')->nullable();
            $table->string('new_altered')->nullable();
            $table->string('period_of_insurance')->nullable();
            $table->string('is_renewable')->nullable();
            $table->string('is_project_specific')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('policy_wording')->nullable();
            $table->string('policy_wording_path')->nullable();
            $table->string('policy_wording_filename')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marine_cargo_open_coverages');
    }
};
