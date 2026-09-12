<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * contractors_all_risks_public_liability — Contractors All Risks / Public
     * Liability claim sub-table. Mirrors graphiteBWV8 (create + police fields).
     */
    public function up(): void
    {
        if (! Schema::hasTable('contractors_all_risks_public_liability')) {
            Schema::create('contractors_all_risks_public_liability', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->integer('claim_sub_type_id')->nullable();
                $table->string('insured')->nullable();

                $table->string('responsible_person_name')->nullable();
                $table->string('responsible_person_phone')->nullable();
                $table->string('responsible_person_cellphone')->nullable();
                $table->string('responsible_person_email')->nullable();
                $table->string('responsible_person_fax')->nullable();

                $table->string('parties_to_contract')->nullable();
                $table->string('contract_value')->nullable();
                $table->string('contract_number')->nullable();
                $table->text('description_of_contract')->nullable();
                $table->text('site_physical_address')->nullable();
                $table->string('code')->nullable();
                $table->date('contract_commencement_date')->nullable();
                $table->date('expected_contract_completion_date')->nullable();

                $table->boolean('responsible_contract_works_claim')->nullable();
                $table->boolean('responsible_public_liability_claim')->nullable();

                $table->date('loss_date')->nullable();
                $table->string('loss_time')->nullable();
                $table->text('loss_details')->nullable();
                $table->text('cause_of_loss')->nullable();
                $table->text('party_responsible_name_contact')->nullable();
                // V2 create/edit forms capture the responsible party's name and
                // contact separately (the V8 blade had two inputs though its
                // controller only kept the combined column). Persist both.
                $table->string('party_responsible_name')->nullable();
                $table->string('party_responsible_contact')->nullable();
                $table->string('estimated_cost_of_repair_replacement')->nullable();
                $table->string('works_claim_documentary_evidence')->nullable();
                $table->string('works_claim_bill_of_quantities')->nullable();
                $table->string('police_station_reference')->nullable();

                $table->string('declaration_name')->nullable();
                $table->string('declaration_capacity')->nullable();
                $table->date('declaration_date')->nullable();

                $table->string('police_station')->nullable();
                $table->string('police_reference')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors_all_risks_public_liability');
    }
};
