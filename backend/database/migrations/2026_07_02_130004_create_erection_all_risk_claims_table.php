<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * erection_all_risk_claims — Erection All Risk claim sub-table. Mirrors
     * graphiteBWV8 (create + contact + split + probable_cause + witness +
     * recovery/third-party ALTER migrations). No file uploads.
     */
    public function up(): void
    {
        if (! Schema::hasTable('erection_all_risk_claims')) {
            Schema::create('erection_all_risk_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->integer('claim_sub_type_id')->nullable();
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
                $table->string('date_time_of_occurrence')->nullable();
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
                $table->text('witness_name_address')->nullable();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('erection_all_risk_claims');
    }
};
