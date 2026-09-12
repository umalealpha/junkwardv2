<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialist coverage table for "Environmental Liability" under the
 * Commercial Liabilities product (id 20). Follows the commercial_crime_coverages
 * / medical_evacuation_coverages pattern — single `premium` column, JSON
 * section tables, endorsement/soft-delete/action+term bookkeeping baked in
 * directly. Field set mirrors the Environmental Liability Policy Schedule
 * (Named Insured, Period of Insurance, Insured Locations, Coverage Sections &
 * Limits of Indemnity, Deductibles/SIR, Premium, Pollutants Covered, Key
 * Exclusions, Endorsements).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('environmental_liability_coverages')) {
        Schema::create('environmental_liability_coverages', function (Blueprint $table) {
            $table->id();

            // Identity / linkage — ties this row to its one parent
            // policy_coverage and the specific action+term it was saved
            // under. Required for getEnvironmentalLiabilityTotal()/
            // getPremium() and every SpecialistCoverageRegistry-driven path
            // (delete cascade, action replication, refresher, pro-rata writer).
            $table->unsignedBigInteger('policy_id')->nullable()->index();
            $table->unsignedBigInteger('policy_coverage_id')->nullable()->index();
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();

            // Named Insured — Policy Schedule header + contact details.
            $table->string('policy_number')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('nature_of_business')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();

            // Period of Insurance / basis of cover.
            $table->date('inception_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('today_date')->nullable();
            $table->date('retroactive_date')->nullable();
            $table->string('basis_of_cover')->nullable();
            $table->string('policy_duration')->nullable();
            $table->string('is_renewable')->nullable();
            $table->string('currency')->nullable();
            $table->string('broker')->nullable();

            // Limits of Indemnity / Premium.
            $table->decimal('overall_annual_aggregate_limit', 15, 2)->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->decimal('tax_levies', 15, 2)->nullable();
            $table->decimal('total_premium_payable', 15, 2)->nullable();
            $table->string('premium_payment_terms')->nullable();
            $table->date('premium_due_date')->nullable();

            // Insured Locations / Coverage Sections / Deductibles-SIR /
            // Pollutants Covered / Key Exclusions / Endorsements — schedule
            // line items.
            $table->json('sites')->nullable();
            $table->json('coverage_sections')->nullable();
            $table->json('deductibles')->nullable();
            $table->json('pollutants_covered')->nullable();
            $table->json('key_exclusions')->nullable();
            $table->json('endorsements')->nullable();
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
            $table->index('deleted_at', 'environmental_liability_coverages_deleted_at_idx');

            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('environmental_liability_coverages');
    }
};
