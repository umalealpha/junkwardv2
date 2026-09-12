<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marine_cargo_once_off_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable()->index();
            $table->unsignedBigInteger('policy_coverage_id')->nullable()->index();
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('assured_name')->nullable();
            $table->text('assured_address')->nullable();
            $table->string('open_policy_no')->nullable();
            $table->string('agent_broker_code')->nullable();
            $table->string('conveyance')->nullable();
            $table->date('voyage_from')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('voyage_to')->nullable();
            $table->date('arrival_date')->nullable();
            $table->text('commodities_covered')->nullable();
            $table->string('nature_of_packing')->nullable();
            $table->text('terms_of_cover')->nullable();
            $table->decimal('annual_estimated_turnover', 15, 2)->nullable();
            $table->decimal('location_limit', 15, 2)->nullable();
            $table->decimal('premium_rate', 10, 4)->nullable();
            $table->decimal('sum_insured', 15, 2)->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->date('inception_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('today_date')->nullable();
            $table->json('clauses')->nullable();
            $table->text('survey_claim_settlement')->nullable();
            $table->string('claim_payable_at')->nullable();
            $table->string('claim_payable_by')->nullable();
            $table->text('basis_of_valuation')->nullable();
            $table->json('per_conveyance_limits')->nullable();
            $table->decimal('per_conveyance_rail', 15, 2)->nullable();
            $table->decimal('per_conveyance_road', 15, 2)->nullable();
            $table->decimal('per_conveyance_air', 15, 2)->nullable();
            $table->decimal('per_conveyance_post', 15, 2)->nullable();
            $table->decimal('per_conveyance_vessel', 15, 2)->nullable();
            $table->text('deductible')->nullable();
            $table->text('notice_of_cancellation')->nullable();
            $table->text('refund')->nullable();
            $table->string('period_of_insurance')->nullable();
            $table->string('new_altered')->nullable();
            $table->string('is_renewable')->nullable();
            $table->string('is_project_specific')->nullable();
            $table->string('currency')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('policy_wording')->nullable();
            $table->string('policy_wording_path')->nullable();
            $table->string('policy_wording_filename')->nullable();
            $table->string('place')->nullable();
            $table->string('examined_by')->nullable();
            $table->date('signing_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('0');
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->date('policy_period_from')->nullable();
            $table->date('policy_period_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marine_cargo_once_off_coverages');
    }
};
