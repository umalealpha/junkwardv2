<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plant All Risks sub-claim table — V2 consolidation of V8's three
 * migrations:
 *   2026_03_06_000002_create_plant_all_risks_claims_table.php
 *   2026_03_06_000003_add_party_responsible_fields_to_plant_all_risks_claims_table.php
 *   2026_03_06_000004_change_documentary_evidence_in_plant_all_risks_claims_table.php
 *
 * V8 blade has four sections (Responsible Person on Site / Site Details /
 * Plant Details / Loss-Damage Details) — mirrored exactly, all nullable.
 * `documentary_evidence` is the V8 legacy JSON-encoded multi-file field;
 * it's kept on the schema as TEXT for V8-import round-trip, but the V2
 * blade doesn't upload to it directly (operators attach docs via the
 * generic Attachments tab instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_all_risks_claims', function (Blueprint $table) {
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

            // Site Details
            $table->text('site_physical_address')->nullable();
            $table->string('site_code')->nullable();

            // Plant Details
            $table->text('item_description')->nullable();
            $table->string('item_number_sum_insured')->nullable();

            // Loss / Damage Details
            $table->date('date_of_loss')->nullable();
            $table->string('time_of_loss')->nullable();
            $table->text('details_of_loss')->nullable();
            $table->text('cause_of_loss')->nullable();
            $table->string('party_responsible')->nullable();         // legacy V8 combined
            $table->string('party_responsible_name')->nullable();
            $table->string('party_responsible_contact')->nullable();
            $table->string('estimated_cost')->nullable();
            $table->text('documentary_evidence')->nullable();        // legacy JSON array of S3 paths
            $table->tinyInteger('uneconomical_to_repair')->nullable();
            $table->tinyInteger('subject_to_finance')->nullable();
            $table->tinyInteger('on_hire_at_time')->nullable();
            $table->string('police_station')->nullable();
            $table->string('police_reference')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_all_risks_claims');
    }
};
