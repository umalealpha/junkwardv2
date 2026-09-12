<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * professional_indemnity_claims — Professional Indemnity claim sub-table.
     * Mirrors graphiteBWV8 create_professional_indemnity_claims_table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('professional_indemnity_claims')) {
            Schema::create('professional_indemnity_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();

                // Policy Details
                $table->string('policy_number')->nullable();
                $table->string('policyNumber')->nullable(); // V2 sub-claim insert injects this
                $table->integer('claim_sub_type_id')->nullable();
                $table->string('insured_name')->nullable();

                // Insured's Details
                $table->string('type_of_business')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('designation')->nullable();
                $table->string('insured_email')->nullable();
                $table->string('insured_cell_tel')->nullable();

                // Claimant / Potential Claimant Details
                $table->string('claimant_type')->nullable();
                $table->string('claimant_name_surname')->nullable();
                $table->string('claimant_email')->nullable();
                $table->string('claimant_cell_tel')->nullable();

                // Details of Contract and Claim
                $table->text('insured_retained_to_do')->nullable();
                $table->tinyInteger('contract_in_place')->nullable();
                $table->string('contract_copy')->nullable();
                $table->text('contract_no_details')->nullable();
                $table->date('work_performed_date')->nullable();
                $table->string('person_performed_work')->nullable();

                // Circumstances
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

                // Insured's Investigation
                $table->tinyInteger('own_investigation')->nullable();
                $table->string('investigation_findings')->nullable();
                $table->text('views_on_liability')->nullable();
                $table->text('views_on_amount_claimed')->nullable();
                $table->text('additional_details')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_indemnity_claims');
    }
};
