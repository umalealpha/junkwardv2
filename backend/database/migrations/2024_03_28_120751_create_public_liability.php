<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicLiability extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('public_liability', function (Blueprint $table) {
            $table->id();
            $table->integer('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('insured_name')->nullable();
            $table->string('insured_treding_name')->nullable();
            $table->string('insured_postal_address')->nullable();
            $table->string('insured_email')->nullable();
            $table->string('insured_telephone_no')->nullable();
            $table->string('insured_facsimile')->nullable();
            $table->string('insured_mobile_no')->nullable();
            $table->string('accident_date')->nullable();
            $table->string('accident_time')->nullable();
            $table->string('accident_incident')->nullable();
            $table->string('accident_injuries')->nullable();
            $table->string('accident_liability')->nullable();
            $table->string('accident_person')->nullable();
            $table->string('accident_contacted')->nullable();
            $table->string('attach_contractor')->nullable();
            $table->string('attach_employee')->nullable();
            $table->string('attach_employed')->nullable();
            $table->string('attach_blame')->nullable();
            $table->string('attach_circumstances')->nullable();
            $table->string('attach_property')->nullable();
            $table->string('attach_details')->nullable();
            $table->string('attach_owner')->nullable();
            $table->string('attach_damage')->nullable();
            $table->string('claim_name')->nullable();
            $table->string('claim_telephone')->nullable();
            $table->string('claim_mobile')->nullable();
            $table->string('claim_postal')->nullable();
            $table->string('claim_solicitor')->nullable();
            $table->string('witness1_name')->nullable();
            $table->string('witness1_telephone')->nullable();
            $table->string('witness1_mobile')->nullable();
            $table->string('witness1_postal')->nullable();
            $table->string('witness1_relationship')->nullable();
            $table->string('witness2_name')->nullable();
            $table->string('witness2_telephone')->nullable();
            $table->string('witness2_mobile')->nullable();
            $table->string('witness2_postal')->nullable();
            $table->string('witness2_relationship')->nullable();
            $table->string('witness2_damage')->nullable();
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
        Schema::dropIfExists('public_liability');
    }
}
