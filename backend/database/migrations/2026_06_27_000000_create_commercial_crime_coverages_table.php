<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialist coverage table for "Commercial Crime" under the Miscellaneous
 * product (id 24). Follows the medical_evacuation_coverages pattern (its
 * sibling under the same product) — single `premium` column, JSON section
 * tables, endorsement/soft-delete/action+term bookkeeping baked in directly.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('commercial_crime_coverages')) {
        Schema::create('commercial_crime_coverages', function (Blueprint $table) {
            $table->id();

            // Identity / linkage — ties this row to its one parent
            // policy_coverage and the specific action+term it was saved
            // under. Required for getCommercialCrimeTotal()/getPremium()
            // and every SpecialistCoverageRegistry-driven path (delete
            // cascade, action replication, refresher, pro-rata writer).
            $table->unsignedBigInteger('policy_id')->nullable()->index();
            $table->unsignedBigInteger('policy_coverage_id')->nullable()->index();
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();

            // Insured details — Policy Schedule header.
            $table->string('policy_number')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->date('inception_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('today_date')->nullable();

            // Cover specifics — basis of cover, limits, premium.
            $table->string('insured_group')->nullable();
            $table->string('coverage_basis')->nullable();
            $table->string('cover_type')->nullable();
            $table->string('industry_sector')->nullable();
            $table->date('retroactive_date')->nullable();
            $table->decimal('total_limit', 15, 2)->nullable();
            $table->decimal('excess', 15, 2)->nullable();
            $table->decimal('annual_aggregate_limit', 15, 2)->nullable();
            $table->string('broker')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->string('is_renewable')->nullable();

            // Insuring Clauses / Excess Layers / Endorsements — schedule line items.
            $table->json('insuring_clauses')->nullable();
            $table->json('excess_layers')->nullable();
            $table->json('endorsements_extensions')->nullable();
            $table->text('notes')->nullable();

            // Policy wording attachment — single writer is the
            // /upload-wording endpoint; the main save form must never
            // touch these three columns (see SpecialistCoverageController).
            $table->text('policy_wording')->nullable();
            $table->string('policy_wording_path')->nullable();
            $table->string('policy_wording_filename')->nullable();

            // Approval — stamped by approvePeriod() for 24/36-month terms.
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Endorsement bookkeeping — read by BackdatedEndorseRefresher /
            // SpecialistEndorseCalculator via SpecialistCoverageRegistry,
            // NOT by the CRUD save path.
            $table->decimal('pro_rate_premium', 20, 2)->default(0)->nullable();
            $table->unsignedBigInteger('previousActionIdCov')->default(0)->nullable();
            $table->tinyInteger('endors_flag')->default(0)->nullable();

            $table->string('status')->default('0');

            $table->softDeletes();
            $table->index('deleted_at', 'commercial_crime_coverages_deleted_at_idx');

            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_crime_coverages');
    }
};
