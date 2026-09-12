<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Travel Insurance sub-claim table — V2 consolidation.
 *
 * The original V2 base migration (2024_03_26_113517_create_travel_insurance_table)
 * shipped the V8 schema with NOT NULL columns and was missing the 25 file-path
 * document columns added by V8's follow-up migrations
 * (2026_04_17_100000 / 2026_04_17_100001). On envs where the base migration
 * had been run the table existed in an unusable shape; on this env it had
 * since been dropped, leaving the dispatch broken even after the
 * subTableMap fix from 'travel_ins' → 'travel_insurance_claim'.
 *
 * This consolidated migration drops the legacy shape and recreates the
 * table V8-parity: every textual column nullable + the full set of
 * compulsory and refund-specific document path columns. Mirrors V8's six
 * migrations:
 *   2024_03_26_113517_create_travel_insurance_table.php
 *   2026_04_17_100000_add_document_columns_to_travel_insurance_claim.php
 *   2026_04_17_100001_make_travel_insurance_claim_columns_nullable.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('travel_insurance_claim');

        Schema::create('travel_insurance_claim', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();

            // Claimant details
            $table->string('title')->nullable();
            $table->string('other_title')->nullable();
            $table->string('surname')->nullable();
            $table->string('forename')->nullable();
            $table->date('dob')->nullable();
            $table->string('passport_no')->nullable();
            $table->string('nationality')->nullable();
            $table->string('telephone')->nullable();
            $table->string('post_code')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('home_address')->nullable();

            // Travel Policy and Journey Details
            $table->string('policy_number')->nullable();
            $table->string('issued_by')->nullable();
            $table->string('issued_on')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Bank Details (reimbursement)
            $table->string('beneficiary')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_address')->nullable();
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('bic_code')->nullable();

            // Other Insurance Policy
            $table->string('other_insurance_policy')->nullable();
            $table->string('name_insurance_company')->nullable();
            $table->text('address')->nullable();
            $table->string('phone_number')->nullable();

            // Type of Refund
            $table->string('type_of_refund')->nullable();
            $table->string('type_of_refund_other')->nullable();

            // Compulsory Documents (V8 add_document_columns migration)
            $table->string('compulsory_doc_proof_of_residence')->nullable();
            $table->string('compulsory_doc_claim_form')->nullable();
            $table->string('compulsory_doc_insurance_policy')->nullable();
            $table->string('compulsory_doc_detailed_letter')->nullable();
            $table->string('compulsory_doc_receipts')->nullable();
            $table->string('compulsory_doc_passport_copy')->nullable();

            // Medical / Dental Care
            $table->string('medical_dental_care_doc_1')->nullable();
            $table->string('medical_dental_care_doc_2')->nullable();
            $table->string('medical_dental_care_doc_3')->nullable();

            // Delayed Luggage
            $table->string('claim_delayed_luggage_doc_1')->nullable();
            $table->string('claim_delayed_luggage_doc_2')->nullable();
            $table->string('claim_delayed_luggage_doc_3')->nullable();

            // Loss of Personal Documents
            $table->string('claim_loss_personal_doc_doc_1')->nullable();
            $table->string('claim_loss_personal_doc_doc_2')->nullable();

            // Lost Luggage
            $table->string('claim_lost_luggage_doc_1')->nullable();
            $table->string('claim_lost_luggage_doc_2')->nullable();
            $table->string('claim_lost_luggage_doc_3')->nullable();
            $table->string('claim_lost_luggage_doc_4')->nullable();

            // Trip Cancellation / Curtailment
            $table->string('claim_trip_cancel_doc_1')->nullable();
            $table->string('claim_trip_cancel_doc_2')->nullable();
            $table->string('claim_trip_cancel_doc_3')->nullable();
            $table->string('claim_trip_cancel_doc_4')->nullable();

            // Delayed Flight
            $table->string('claim_delayed_flight_doc_1')->nullable();
            $table->string('claim_delayed_flight_doc_2')->nullable();
            $table->string('claim_delayed_flight_doc_3')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_insurance_claim');
    }
};
