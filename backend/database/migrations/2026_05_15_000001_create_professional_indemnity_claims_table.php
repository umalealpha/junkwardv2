<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professional Indemnity sub-claim table — V2 port of V8's
 * 2026_03_06_000005_create_professional_indemnity_claims_table.php.
 *
 * V8's claim blade is a single flat form mapping to one table; the
 * `professional_indemnity_coverages` / `_insured_persons` / `_extensions`
 * / `_excesses` tables already present in V2 belong to the policy product,
 * NOT to the claim. So no nested 1:N tables are needed for the claim
 * itself — only this flat sub-claim table.
 *
 * Five sections, mirrored exactly from V8:
 *   A. Insured's Details          (type_of_business, contact_person…)
 *   B. Claimant Details           (claimant_type, claimant_name_surname…)
 *   C. Details of Contract & Claim (insured_retained_to_do, contract_in_place + file/details)
 *   D. Circumstances              (circumstances, demand/summons/attorney follow-ups)
 *   E. Insured's Investigation    (own_investigation + findings file, views, additional details)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_indemnity_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newclaim_id')->nullable();

            // Policy / Insured root
            $table->string('policy_number')->nullable();
            $table->string('insured_name')->nullable();

            // Section A — Insured's Details
            $table->string('type_of_business')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('designation')->nullable();
            $table->string('insured_email')->nullable();
            $table->string('insured_cell_tel')->nullable();

            // Section B — Claimant Details
            $table->string('claimant_type')->nullable(); // Business / Individual
            $table->string('claimant_name_surname')->nullable();
            $table->string('claimant_email')->nullable();
            $table->string('claimant_cell_tel')->nullable();

            // Section C — Details of Contract and Claim
            $table->text('insured_retained_to_do')->nullable();
            $table->tinyInteger('contract_in_place')->nullable();
            $table->string('contract_copy')->nullable(); // S3 path
            $table->text('contract_no_details')->nullable();
            $table->date('work_performed_date')->nullable();
            $table->string('person_performed_work')->nullable();

            // Section D — Circumstances
            $table->text('circumstances')->nullable();
            $table->date('first_aware_date')->nullable();
            $table->text('reason_for_reporting')->nullable();
            $table->tinyInteger('notification_purposes_only')->nullable();
            $table->tinyInteger('verbal_written_demand')->nullable();
            $table->date('demand_received_date')->nullable();
            $table->tinyInteger('served_with_summons')->nullable();
            $table->date('summons_served_date')->nullable();
            $table->tinyInteger('attorney_appointed')->nullable();
            $table->text('attorney_details')->nullable();
            $table->string('amount_claimed')->nullable();

            // Section E — Insured's Investigation
            $table->tinyInteger('own_investigation')->nullable();
            $table->string('investigation_findings')->nullable(); // S3 path
            $table->text('views_on_liability')->nullable();
            $table->text('views_on_amount_claimed')->nullable();
            $table->text('additional_details')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_indemnity_claims');
    }
};
