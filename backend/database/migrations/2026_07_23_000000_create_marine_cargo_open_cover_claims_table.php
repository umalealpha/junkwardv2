<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * marine_cargo_open_cover_claims — Marine Cargo Open Cover claim sub-table
     * (form AD-CLM-MAR-001 v1.0). Same shape as marine_cargo_once_off_claims
     * except the policy header captures the open-cover / per-consignment
     * declaration fields instead of single-voyage cover. Tick-all groups are
     * one boolean per option (mode_*, loss_*); Yes/No questions are booleans
     * with a paired details column. Idempotent create guard — safe to re-run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marine_cargo_open_cover_claims')) {
            Schema::create('marine_cargo_open_cover_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->integer('claim_sub_type_id')->nullable();

                // Policy & Open Cover Details
                $table->string('open_cover_policy_number')->nullable();
                $table->string('insured')->nullable();
                $table->string('period_of_insurance')->nullable();
                $table->string('annual_aggregate_sum_insured')->nullable();
                $table->string('certificate_declaration_number')->nullable();
                $table->date('date_of_declaration')->nullable();
                $table->string('insured_value_declared')->nullable();

                // Insured Contact & Business
                $table->string('contact_person')->nullable();
                $table->string('designation')->nullable();
                $table->string('phone')->nullable();
                $table->string('cellphone')->nullable();
                $table->string('email')->nullable();
                $table->text('postal_physical_address')->nullable();
                $table->text('nature_of_business')->nullable();

                // Consignment / Cargo Details
                $table->text('description_of_goods')->nullable();
                $table->string('number_type_packages')->nullable();
                $table->string('marks_numbers')->nullable();
                $table->string('gross_weight')->nullable();
                $table->string('net_weight')->nullable();
                $table->string('commercial_invoice_number')->nullable();
                $table->string('invoice_value')->nullable();
                $table->string('cif_value')->nullable();
                $table->string('container_number')->nullable();
                $table->string('seal_numbers')->nullable();

                // Voyage / Transit Details — mode of transport (tick all legs)
                $table->boolean('mode_sea')->nullable();
                $table->boolean('mode_air')->nullable();
                $table->boolean('mode_road')->nullable();
                $table->boolean('mode_rail')->nullable();
                $table->boolean('mode_multimodal')->nullable();
                $table->text('multimodal_route_description')->nullable();
                $table->text('origin')->nullable();
                $table->text('destination')->nullable();
                $table->string('vessel_aircraft_truck_reg')->nullable();
                $table->string('voyage_flight_trip_no')->nullable();
                $table->date('date_of_departure')->nullable();
                $table->date('date_of_arrival')->nullable();
                $table->string('bill_of_lading_number')->nullable();
                $table->string('carrier')->nullable();
                $table->text('freight_forwarder')->nullable();

                // Loss / Damage Details
                $table->date('date_of_loss')->nullable();
                $table->date('date_loss_discovered')->nullable();
                $table->text('place_stage_of_loss')->nullable();
                // Type of Loss (tick all)
                $table->boolean('loss_shortage')->nullable();
                $table->boolean('loss_pilferage')->nullable();
                $table->boolean('loss_non_delivery')->nullable();
                $table->boolean('loss_damage_handling')->nullable();
                $table->boolean('loss_wet_seawater')->nullable();
                $table->boolean('loss_freshwater')->nullable();
                $table->boolean('loss_fire_explosion')->nullable();
                $table->boolean('loss_hijacking')->nullable();
                $table->boolean('loss_sea_perils')->nullable();
                $table->boolean('loss_other')->nullable();
                $table->text('loss_description')->nullable();
                $table->string('estimated_value_of_loss')->nullable();

                // Carrier Notice, Survey & Recovery
                $table->boolean('notice_of_loss_issued')->nullable();
                $table->text('notice_of_loss_details')->nullable();
                $table->text('notice_date_reference')->nullable();
                $table->boolean('carrier_acknowledged')->nullable();
                $table->text('carrier_acknowledged_details')->nullable();
                $table->text('carrier_reply_reference')->nullable();
                $table->boolean('joint_survey_held')->nullable();
                $table->text('joint_survey_details')->nullable();
                $table->text('surveyor_agent')->nullable();
                $table->boolean('survey_report_attached')->nullable();
                $table->text('survey_report_details')->nullable();
                $table->boolean('police_report_attached')->nullable();
                $table->text('police_report_details')->nullable();
                $table->text('police_station_ob')->nullable();
                $table->boolean('recovery_claim_lodged')->nullable();
                $table->text('recovery_claim_details')->nullable();
                $table->text('carrier_name_address')->nullable();

                // Financier, Other Insurance & Loss History
                $table->boolean('goods_financed')->nullable();
                $table->text('goods_financed_details')->nullable();
                $table->text('bank_financier_reference')->nullable();
                $table->boolean('other_insurance')->nullable();
                $table->text('other_insurance_details')->nullable();
                $table->text('other_insurer_policy')->nullable();
                $table->text('loss_history')->nullable();
                $table->text('procedural_improvements')->nullable();

                // Declaration & Subrogation
                $table->string('declaration_name')->nullable();
                $table->string('declaration_capacity')->nullable();
                $table->date('declaration_date')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marine_cargo_open_cover_claims');
    }
};
