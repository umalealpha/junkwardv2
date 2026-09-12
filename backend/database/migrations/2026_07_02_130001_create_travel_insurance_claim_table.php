<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * travel_insurance_claim — coverage-based Travel Insurance claim sub-table.
     * Mirrors graphiteBWV8 (create_travel_insurance_table +
     * add_document_columns_to_travel_insurance_claim), all columns nullable.
     */
    public function up(): void
    {
        if (! Schema::hasTable('travel_insurance_claim')) {
            Schema::create('travel_insurance_claim', function (Blueprint $table) {
                $table->id();
                $table->string('newclaim_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->integer('claim_sub_type_id')->nullable();

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
                $table->string('policy_number')->nullable();
                $table->string('issued_by')->nullable();
                $table->string('issued_on')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->string('beneficiary')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_address')->nullable();
                $table->string('account_number')->nullable();
                $table->string('iban')->nullable();
                $table->string('swift_code')->nullable();
                $table->string('bic_code')->nullable();
                $table->string('other_insurance_policy')->nullable();
                $table->string('name_insurance_company')->nullable();
                $table->string('address')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('type_of_refund')->nullable();
                $table->string('type_of_refund_other')->nullable();
                $table->string('compulsory_doc_all_claims')->nullable();
                $table->string('medical_dental_care')->nullable();
                $table->string('claim_delayed_luggage')->nullable();
                $table->string('claim_loss_personal_doc')->nullable();
                $table->string('claim_lost_luggage')->nullable();
                $table->string('claim_trip_cancel')->nullable();
                $table->string('claim_delayed_flight')->nullable();

                // Document upload paths (S3)
                $table->string('compulsory_doc_proof_of_residence')->nullable();
                $table->string('compulsory_doc_claim_form')->nullable();
                $table->string('compulsory_doc_insurance_policy')->nullable();
                $table->string('compulsory_doc_detailed_letter')->nullable();
                $table->string('compulsory_doc_receipts')->nullable();
                $table->string('compulsory_doc_passport_copy')->nullable();
                $table->string('medical_dental_care_doc_1')->nullable();
                $table->string('medical_dental_care_doc_2')->nullable();
                $table->string('medical_dental_care_doc_3')->nullable();
                $table->string('claim_delayed_luggage_doc_1')->nullable();
                $table->string('claim_delayed_luggage_doc_2')->nullable();
                $table->string('claim_delayed_luggage_doc_3')->nullable();
                $table->string('claim_loss_personal_doc_doc_1')->nullable();
                $table->string('claim_loss_personal_doc_doc_2')->nullable();
                $table->string('claim_lost_luggage_doc_1')->nullable();
                $table->string('claim_lost_luggage_doc_2')->nullable();
                $table->string('claim_lost_luggage_doc_3')->nullable();
                $table->string('claim_lost_luggage_doc_4')->nullable();
                $table->string('claim_trip_cancel_doc_1')->nullable();
                $table->string('claim_trip_cancel_doc_2')->nullable();
                $table->string('claim_trip_cancel_doc_3')->nullable();
                $table->string('claim_trip_cancel_doc_4')->nullable();
                $table->string('claim_delayed_flight_doc_1')->nullable();
                $table->string('claim_delayed_flight_doc_2')->nullable();
                $table->string('claim_delayed_flight_doc_3')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_insurance_claim');
    }
};
