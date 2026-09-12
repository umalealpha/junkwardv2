<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialist coverage table for "Bonds and Guarantees" under the Guarantee
 * product (id 23). Follows the commercial_crime_coverages /
 * environmental_liability_coverages pattern — single `premium` column, JSON
 * section tables, endorsement/soft-delete/action+term bookkeeping baked in
 * directly. Field set mirrors the Bonds Insurance Policy Schedule (Insured /
 * Principal, Bond Coverage Schedule line items, Key Policy Conditions).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bonds_coverages')) {
        Schema::create('bonds_coverages', function (Blueprint $table) {
            $table->id();

            // Identity / linkage — ties this row to its one parent
            // policy_coverage and the specific action+term it was saved
            // under. Required for getBondsTotal()/getPremium() and every
            // SpecialistCoverageRegistry-driven path (delete cascade,
            // action replication, refresher, pro-rata writer) to find it.
            $table->unsignedBigInteger('policy_id')->nullable()->index();
            $table->unsignedBigInteger('policy_coverage_id')->nullable()->index();
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();

            // Insured / Principal details — Bonds Insurance Policy Schedule header.
            $table->string('policy_number')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->text('risk_address')->nullable();
            $table->string('type_of_bond')->nullable();
            $table->decimal('limit_insured', 15, 2)->nullable();
            $table->date('inception_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('today_date')->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->decimal('excess', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('broker')->nullable();
            $table->string('is_renewable')->nullable();

            // Bond Coverage Schedule — line items (bond type / amount / parties).
            $table->json('bond_schedule')->nullable();

            // Key Policy Conditions — free-text clauses from the schedule.
            $table->text('insuring_agreement')->nullable();
            $table->text('trigger_event')->nullable();
            $table->text('subrogation_right')->nullable();
            $table->text('non_cancellable_clause')->nullable();
            $table->text('collateral_security')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('dispute_resolution')->nullable();
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
            $table->index('deleted_at', 'bonds_coverages_deleted_at_idx');

            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bonds_coverages');
    }
};
