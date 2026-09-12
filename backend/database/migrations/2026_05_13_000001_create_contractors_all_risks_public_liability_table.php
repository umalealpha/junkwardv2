<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contractors All Risks / Public Liability sub-claim table.
 *
 * Mirrors graphiteBWV8 migrations
 *   2026_02_25_000001_create_contractors_all_risks_public_liability_table.php
 *   2026_03_05_000001_add_police_fields_to_contractors_all_risks_public_liability_table.php
 *
 * V8 has two file-path columns (works_claim_documentary_evidence,
 * works_claim_bill_of_quantities) that store S3 paths; ClaimsController
 * uploads + stitches CDN URLs the same way it does for GIT and Fire.
 *
 * V8 stores the responsible-party as a single concatenated column
 * `party_responsible_name_contact`; for V2 parity we keep both shapes —
 * the combined column for V8-imported rows, and separate
 * `party_responsible_name` / `party_responsible_contact` columns so the
 * FE doesn't need to concat client-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors_all_risks_public_liability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('insured')->nullable();

            // Responsible Person on Site & Contact Numbers
            $table->string('responsible_person_name')->nullable();
            $table->string('responsible_person_phone')->nullable();
            $table->string('responsible_person_cellphone')->nullable();
            $table->string('responsible_person_email')->nullable();
            $table->string('responsible_person_fax')->nullable();

            // Contract Details
            $table->string('parties_to_contract')->nullable();
            $table->string('contract_value')->nullable();
            $table->string('contract_number')->nullable();
            $table->text('description_of_contract')->nullable();
            $table->text('site_physical_address')->nullable();
            $table->string('code')->nullable();
            $table->date('contract_commencement_date')->nullable();
            $table->date('expected_contract_completion_date')->nullable();

            // Insurance Responsibility (V8: tinyint 1/0)
            $table->boolean('responsible_contract_works_claim')->nullable();
            $table->boolean('responsible_public_liability_claim')->nullable();

            // Loss / Damage Details
            $table->date('loss_date')->nullable();
            $table->string('loss_time')->nullable();
            $table->text('loss_details')->nullable();
            $table->text('cause_of_loss')->nullable();
            // V8 stores `party_responsible_name_contact` as a single
            // concatenated text column ("Name | Contact"). We add
            // separate columns so the FE doesn't have to concat, and
            // keep the legacy combined column for V8-import parity.
            $table->text('party_responsible_name_contact')->nullable();
            $table->string('party_responsible_name')->nullable();
            $table->string('party_responsible_contact')->nullable();
            $table->string('estimated_cost_of_repair_replacement')->nullable();
            // S3 paths for the two file uploads (V8 longText).
            $table->longText('works_claim_documentary_evidence')->nullable();
            $table->longText('works_claim_bill_of_quantities')->nullable();
            // Legacy combined column (V8 schema) + the explicit columns
            // added in V8's 2026-03-05 migration.
            $table->string('police_station_reference')->nullable();
            $table->string('police_station')->nullable();
            $table->string('police_reference')->nullable();

            // Declaration (V8 supports these fields; blade has no UI for
            // them but the controller writes them when present).
            $table->string('declaration_name')->nullable();
            $table->string('declaration_capacity')->nullable();
            $table->date('declaration_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors_all_risks_public_liability');
    }
};
