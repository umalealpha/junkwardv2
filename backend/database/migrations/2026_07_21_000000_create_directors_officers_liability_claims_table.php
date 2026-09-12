<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * directors_officers_liability_claims — Directors & Officers Liability
     * claim / notification sub-table (form AD D&O Liability v1.0). Claims-made
     * D&O notification. Mirrors the machinery_breakdown_claims pattern:
     * newclaim_id FK to new_claims, policyNumber, one nullable column per form
     * field grouped by the PDF sections. "Tick all that apply" groups are stored
     * as one boolean per option (notif_*, side_*, claimant_*, form_*, alleg_*);
     * standalone Yes/No questions are booleans with a paired details text column;
     * money figures are free-text strings. Idempotent create guard — re-runnable.
     */
    public function up(): void
    {
        if (! Schema::hasTable('directors_officers_liability_claims')) {
            Schema::create('directors_officers_liability_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->integer('claim_sub_type_id')->nullable();

                // Type of Notification (tick one or more)
                $table->boolean('notif_claim')->nullable();
                $table->boolean('notif_circumstance')->nullable();
                $table->boolean('notif_investigation')->nullable();
                $table->boolean('notif_subpoena')->nullable();

                // Policy Details
                $table->string('period_of_insurance')->nullable();
                $table->date('retroactive_date')->nullable();
                $table->string('limit_aggregate')->nullable();
                $table->string('limit_each_claim')->nullable();
                $table->string('self_insured_retention')->nullable();

                // Policy Cover Sides (tick all applicable)
                $table->boolean('side_a')->nullable();
                $table->boolean('side_b')->nullable();
                $table->boolean('side_c')->nullable();
                $table->boolean('epl_extension')->nullable();

                // Insured Entity
                $table->string('insured_company')->nullable();
                $table->string('company_registration_number')->nullable();
                $table->string('regulator_license_number')->nullable();
                $table->string('industry_sector')->nullable();
                $table->text('registered_address')->nullable();
                $table->text('company_secretary_contact')->nullable();

                // Insured Person(s) Against Whom Claim Is Made
                $table->string('person1_name_id')->nullable();
                $table->string('person1_position')->nullable();
                $table->date('person1_appointment_date')->nullable();
                $table->string('person1_current_former')->nullable();
                $table->string('person2_name_id')->nullable();
                $table->string('person2_position')->nullable();
                $table->date('person2_appointment_date')->nullable();
                $table->string('person2_current_former')->nullable();
                $table->text('additional_insured_persons')->nullable();

                // Claim Trigger Dates (claims-made policy)
                $table->date('date_wrongful_act')->nullable();
                $table->date('date_claim_first_made')->nullable();
                $table->date('date_insured_first_aware')->nullable();

                // Claimant Details — Identity of Claimant (tick all)
                $table->boolean('claimant_shareholder')->nullable();
                $table->boolean('claimant_regulator')->nullable();
                $table->boolean('claimant_liquidator')->nullable();
                $table->boolean('claimant_employee')->nullable();
                $table->boolean('claimant_customer')->nullable();
                $table->boolean('claimant_creditor')->nullable();
                $table->boolean('claimant_government')->nullable();
                $table->boolean('claimant_other')->nullable();
                $table->text('claimant_names')->nullable();
                $table->text('claimant_legal_counsel')->nullable();

                // Form of the Claim (tick all)
                $table->boolean('form_letter_demand')->nullable();
                $table->boolean('form_summons')->nullable();
                $table->boolean('form_subpoena')->nullable();
                $table->boolean('form_regulator_inquiry')->nullable();
                $table->boolean('form_criminal_charge')->nullable();
                $table->boolean('form_internal_investigation')->nullable();
                $table->boolean('form_other')->nullable();

                // Nature of Allegation (tick all)
                $table->boolean('alleg_fiduciary_breach')->nullable();
                $table->boolean('alleg_misstatement')->nullable();
                $table->boolean('alleg_insolvent_trading')->nullable();
                $table->boolean('alleg_misappropriation')->nullable();
                $table->boolean('alleg_regulatory_breach')->nullable();
                $table->boolean('alleg_employment_practices')->nullable();
                $table->boolean('alleg_negligence')->nullable();
                $table->boolean('alleg_criminal')->nullable();
                $table->boolean('alleg_defamation')->nullable();
                $table->boolean('alleg_other')->nullable();
                $table->text('allegation_description')->nullable();
                $table->string('total_quantum_claimed')->nullable();
                $table->string('stage_of_proceedings')->nullable();
                $table->string('court_forum')->nullable();
                $table->string('case_reference_number')->nullable();

                // Defense & Counsel
                $table->boolean('counsel_engaged')->nullable();
                $table->text('counsel_engaged_details')->nullable();
                $table->text('counsel_firm_attorney')->nullable();
                $table->text('counsel_contact_rate')->nullable();
                $table->boolean('ad_prior_consent')->nullable();
                $table->text('ad_prior_consent_details')->nullable();
                $table->string('estimated_defense_costs')->nullable();
                $table->date('next_hearing_deadline')->nullable();
                $table->boolean('settlement_offer_made')->nullable();
                $table->text('settlement_offer_details')->nullable();

                // Co-Defendants & Other Insurance
                $table->boolean('codefendants_insured_persons')->nullable();
                $table->text('codefendants_details')->nullable();
                $table->text('codefendant_names')->nullable();
                $table->boolean('company_named')->nullable();
                $table->text('company_named_details')->nullable();
                $table->boolean('outside_parties_named')->nullable();
                $table->text('outside_parties_details')->nullable();
                $table->text('outside_party_names')->nullable();
                $table->boolean('other_insurance')->nullable();
                $table->text('other_insurance_details')->nullable();
                $table->text('other_policy_details')->nullable();
                $table->boolean('previously_notified')->nullable();
                $table->text('previously_notified_details')->nullable();
                $table->text('prior_notification_reference')->nullable();

                // Declaration
                $table->string('declaration_name')->nullable();
                $table->string('declaration_capacity')->nullable();
                $table->date('declaration_date')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('directors_officers_liability_claims');
    }
};
