<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * plant_all_risks_claims — Plant All Risks claim sub-table. Mirrors
     * graphiteBWV8 (create + party_responsible fields + documentary_evidence
     * as text, which stores a JSON array of uploaded file paths).
     */
    public function up(): void
    {
        if (! Schema::hasTable('plant_all_risks_claims')) {
            Schema::create('plant_all_risks_claims', function (Blueprint $table) {
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

                $table->text('site_physical_address')->nullable();
                $table->string('site_code')->nullable();

                $table->text('item_description')->nullable();
                $table->string('item_number_sum_insured')->nullable();

                $table->date('date_of_loss')->nullable();
                $table->string('time_of_loss')->nullable();
                $table->text('details_of_loss')->nullable();
                $table->text('cause_of_loss')->nullable();
                $table->string('party_responsible')->nullable();
                $table->string('party_responsible_name')->nullable();
                $table->string('party_responsible_contact')->nullable();
                $table->string('estimated_cost')->nullable();
                $table->text('documentary_evidence')->nullable(); // JSON array of S3 paths
                $table->boolean('uneconomical_to_repair')->nullable();
                $table->boolean('subject_to_finance')->nullable();
                $table->boolean('on_hire_at_time')->nullable();
                $table->string('police_station')->nullable();
                $table->string('police_reference')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_all_risks_claims');
    }
};
