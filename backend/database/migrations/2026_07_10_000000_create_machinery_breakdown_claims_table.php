<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * machinery_breakdown_claims — Machinery Breakdown claim sub-table
     * (form AD-CLM-MB-001). Mirrors the plant_all_risks_claims pattern:
     * newclaim_id FK to new_claims, policyNumber, and one nullable column per
     * form field grouped by the PDF sections. Yes/No questions are stored as
     * booleans; the free-text "Details" that follows each one is its own text
     * column. Idempotent create guard so it is safe to re-run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('machinery_breakdown_claims')) {
            Schema::create('machinery_breakdown_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->integer('claim_sub_type_id')->nullable();

                // Policy Details
                $table->string('insured')->nullable();
                $table->string('period_of_insurance')->nullable();
                $table->string('sum_insured')->nullable();

                // Insured Contact & Business
                $table->string('contact_person')->nullable();
                $table->string('designation')->nullable();
                $table->string('phone')->nullable();
                $table->string('cellphone')->nullable();
                $table->string('email')->nullable();
                $table->text('postal_physical_address')->nullable();
                $table->text('nature_of_business')->nullable();
                $table->string('years_in_operation')->nullable();

                // Damaged Machinery
                $table->text('item_description')->nullable();
                $table->string('make_model')->nullable();
                $table->string('serial_number')->nullable();
                $table->string('year_of_manufacture')->nullable();
                $table->date('date_commissioned')->nullable();
                $table->text('technical_specs')->nullable();
                $table->string('current_replacement_value')->nullable();
                $table->boolean('under_amc_contract')->nullable();
                $table->text('amc_contract_details')->nullable();

                // Loss / Damage Details
                $table->date('date_of_loss')->nullable();
                $table->string('time_of_loss')->nullable();
                $table->date('date_loss_discovered')->nullable();
                $table->text('site_location')->nullable();
                $table->string('equipment_status')->nullable();
                $table->text('cause_of_loss')->nullable();
                $table->text('damage_description')->nullable();

                // Repair, Replacement & Salvage
                $table->string('estimated_cost')->nullable();
                $table->text('proposed_repairer')->nullable();
                $table->text('salvage_location')->nullable();

                // Financier, Recovery & Other Insurance
                $table->boolean('sole_owner')->nullable();
                $table->text('sole_owner_details')->nullable();
                $table->text('co_owner_financier')->nullable();
                $table->boolean('subject_to_finance')->nullable();
                $table->text('finance_details')->nullable();
                $table->text('financier_bank_reference')->nullable();
                $table->boolean('third_party_responsible')->nullable();
                $table->text('third_party_details')->nullable();
                $table->text('third_party_name_contact')->nullable();
                $table->boolean('recovery_claim_lodged')->nullable();
                $table->text('recovery_details')->nullable();
                $table->boolean('other_insurance')->nullable();
                $table->text('other_insurance_details')->nullable();
                $table->text('other_insurer_policy')->nullable();
                $table->text('loss_history')->nullable();

                // Prevention & Declaration
                $table->text('procedural_improvements')->nullable();
                $table->string('declaration_name')->nullable();
                $table->string('declaration_capacity')->nullable();
                $table->date('declaration_date')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_breakdown_claims');
    }
};
