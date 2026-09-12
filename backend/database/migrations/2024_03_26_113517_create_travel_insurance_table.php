<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTravelInsuranceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('travel_insurance_claim', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();
            $table->string('title');
            $table->string('other_title');
            $table->string('surname');
            $table->string('forename');
            $table->date('dob');
            $table->string('passport_no');
            $table->string('nationality');
            $table->string('telephone');
            $table->string('post_code');
            $table->string('mobile');
            $table->string('email');
            $table->string('home_address');
            $table->string('policy_number');
            $table->string('issued_by');
            $table->string('issued_on');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('beneficiary');
            $table->string('bank_name');
            $table->string('bank_address');
            $table->string('account_number');
            $table->string('iban');
            $table->string('swift_code');
            $table->string('bic_code');
            $table->string('other_insurance_policy');
            $table->string('name_insurance_company');
            $table->string('address');
            $table->string('phone_number');
            $table->string('type_of_refund');
            $table->string('type_of_refund_other');
            $table->string('compulsory_doc_all_claims');
            $table->string('medical_dental_care');
            $table->string('claim_delayed_luggage');
            $table->string('claim_loss_personal_doc');
            $table->string('claim_lost_luggage');
            $table->string('claim_trip_cancel');
            $table->string('claim_delayed_flight');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('travel_insurance');
    }
}
