<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medical Malpractice sub-claim table — V2 port of V8's
 * 2026_03_09_000001_create_medical_malpractice_claims_table.php.
 *
 * Four sections, mirrored from V8:
 *   Section 1: Insured Party Information (insured_full_name, etc.)
 *   Section 2: Claimant (Patient) Information
 *   Section 3: Details of Allegation
 *   Section 4: Supporting Documents (5 S3 file path columns)
 *
 * Note: V8's medical_malpractice.blade.php only renders Sections 2-4 in
 * the create form, but the controller accepts Section 1 fields too and
 * the schema has columns for them. V2's create form surfaces all four
 * sections so the Insured Party block becomes a usable input (V8 left
 * those columns orphaned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_malpractice_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newclaim_id')->nullable();

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

    public function down(): void
    {
        Schema::dropIfExists('medical_malpractice_claims');
    }
};
