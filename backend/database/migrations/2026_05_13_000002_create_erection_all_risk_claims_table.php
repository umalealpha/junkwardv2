<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erection All Risk sub-claim table.
 *
 * Consolidates graphiteBWV8's six migrations into one:
 *   2026_03_05_000002_create_erection_all_risk_claims_table.php
 *   2026_03_05_000003_add_contact_fields_to_...
 *   2026_03_05_000004_add_split_fields_to_...        (date/time/railway)
 *   2026_03_05_000005_add_probable_cause_to_...
 *   2026_03_06_000001_add_witness_fields_to_...
 *   2026_04_03_000001_add_recovery_and_third_party_details_to_...
 *
 * Keeps the V8 legacy columns (date_time_of_occurrence,
 * witness_name_address) alongside the split columns the blade actually
 * uses, so V8-imported rows still validate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erection_all_risk_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('insured')->nullable();

            // Section A — Details of Insured
            $table->string('insured_name')->nullable();
            $table->text('insured_address')->nullable();
            $table->string('insured_contact')->nullable();
            $table->string('insured_contact_number')->nullable();
            $table->string('insured_email')->nullable();
            $table->string('insured_occupation')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('supervisor_engineer_name')->nullable();

            // Section B — Particulars of Accident
            $table->string('date_time_of_occurrence')->nullable(); // legacy combined
            $table->string('date_of_occurrence')->nullable();
            $table->string('time_of_occurrence')->nullable();
            $table->text('site_of_damage')->nullable();
            $table->text('nearest_railway_station')->nullable();
            $table->text('damage_contract_works')->nullable();
            $table->text('damage_plant_equipment')->nullable();
            $table->text('damage_third_party_property')->nullable();
            $table->text('cause_of_damage')->nullable();
            $table->boolean('responsible_for_damage')->nullable();
            $table->text('responsible_for_damage_details')->nullable();
            $table->boolean('possibility_of_recovery')->nullable();
            $table->text('recovery_details')->nullable();

            // Section C — Details of the Damaged Section/Works
            $table->text('how_damage_occurred')->nullable();
            $table->text('probable_cause')->nullable();
            $table->text('progress_of_construction')->nullable();
            $table->text('how_items_repaired')->nullable();
            $table->boolean('alterations_during_repairs')->nullable();
            $table->text('witness_name_address')->nullable(); // legacy combined
            $table->string('witness_name')->nullable();
            $table->text('witness_address')->nullable();
            $table->boolean('surrounding_properties_damaged')->nullable();
            $table->boolean('third_party_liability')->nullable();
            $table->text('third_party_liability_details')->nullable();
            $table->string('estimated_cost_contract_works')->nullable();
            $table->string('estimated_cost_plant_machinery')->nullable();
            $table->string('estimated_cost_third_party_property')->nullable();
            $table->string('estimated_cost_owners_surrounding')->nullable();

            // Section D — Other Insurances
            $table->text('other_insurance_details')->nullable();

            // Section E — Previous Losses
            $table->text('previous_losses_details')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erection_all_risk_claims');
    }
};
