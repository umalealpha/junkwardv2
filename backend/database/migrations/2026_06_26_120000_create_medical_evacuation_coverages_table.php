<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialist coverage table for "Medical Evacuation" under the Miscellaneous
 * product (id 24). Follows the machinery_breakdown_coverages pattern (the
 * Engineering specialist-coverage template) and bakes in the endorsement /
 * soft-delete / action+term columns directly, rather than relying on the
 * generic ALTER migrations that only targeted the original ten tables.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('medical_evacuation_coverages')) {
        Schema::create('medical_evacuation_coverages', function (Blueprint $table) {
            $table->id();

            // Identity / linkage — ties this row to its one parent
            // policy_coverage and the specific action+term it was saved
            // under. Required for getMedicalEvacuationTotal()/getPremium()
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

            // Cover specifics — type of evacuation cover, limits, premium.
            $table->string('type_of_cover')->nullable();
            $table->string('original_insured_scheme')->nullable();
            $table->string('territorial_limit')->nullable();
            $table->string('broker')->nullable();
            $table->decimal('sum_insured', 15, 2)->nullable();
            $table->decimal('aggregate_limit', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->string('is_renewable')->nullable();

            // Description of Cover / Extensions — schedule line items.
            $table->json('description_items')->nullable();
            $table->json('extension_items')->nullable();
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
            $table->index('deleted_at', 'medical_evacuation_coverages_deleted_at_idx');

            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_evacuation_coverages');
    }
};
