<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * medical_malpractice_claims — Medical Malpractice claim sub-table.
     * Mirrors graphiteBWV8 create_medical_malpractice_claims_table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('medical_malpractice_claims')) {
            Schema::create('medical_malpractice_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable(); // V2 sub-claim insert injects this
                $table->integer('claim_sub_type_id')->nullable();

                // Section 1: Insured Party Information
                $table->string('insured_full_name')->nullable();
                $table->string('professional_title_role')->nullable();
                $table->string('license_registration_number')->nullable();
                $table->string('facility_practice_name')->nullable();
                $table->text('address_of_practice')->nullable();
                $table->string('insured_contact_number')->nullable();
                $table->string('insured_email')->nullable();

                // Section 2: Claimant (Patient) Information
                $table->string('claimant_full_name')->nullable();
                $table->date('claimant_date_of_birth')->nullable();
                $table->string('claimant_contact_number')->nullable();
                $table->text('claimant_mailing_address')->nullable();

                // Section 3: Details of Allegation
                $table->date('date_of_alleged_incident')->nullable();
                $table->text('nature_of_services_provided')->nullable();
                $table->date('date_of_notification')->nullable();
                $table->string('how_notified')->nullable();
                $table->text('description_of_allegation')->nullable();

                // Section 4: Supporting Documents (S3 paths)
                $table->string('notification_letter')->nullable();
                $table->string('patient_records')->nullable();
                $table->string('investigation_reports')->nullable();
                $table->string('correspondence')->nullable();
                $table->string('expert_legal_opinions')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_malpractice_claims');
    }
};
