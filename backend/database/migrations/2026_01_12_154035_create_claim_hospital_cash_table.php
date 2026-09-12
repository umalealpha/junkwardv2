<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClaimHospitalCashTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('claim_hospital_cash', function (Blueprint $table) {
            $table->id();
            $table->integer('claim_id');
            
            // Patient Information
            $table->string('patient_name')->nullable();
            $table->string('patient_dob_id')->nullable();
            $table->string('relationship')->nullable();
            $table->date('occupation_date')->nullable();
            
            // General Practitioner Information
            $table->string('gp_name')->nullable();
            $table->text('gp_postal_address')->nullable();
            $table->string('gp_cellular_no')->nullable();
            $table->string('gp_telephone_no')->nullable();
            $table->string('gp_fax_no')->nullable();
            
            // Hospital Information
            $table->string('hospital_name')->nullable();
            $table->string('hospital_tel_fax')->nullable();
            $table->string('admitting_doctor')->nullable();
            $table->string('admitting_doctor_tel_fax')->nullable();
            $table->date('admission_date')->nullable();
            $table->string('admission_time')->nullable();
            $table->date('discharge_date')->nullable();
            $table->string('discharge_time')->nullable();
            
            // Claim Information
            $table->text('hospitalisation_reason')->nullable();
            $table->string('accident_reported')->nullable();
            $table->date('symptoms_first_appeared')->nullable();
            $table->string('pregnancy_info')->nullable();
            $table->date('injury_date')->nullable();
            $table->text('accident_circumstances')->nullable();
            $table->date('first_consultation_date')->nullable();
            $table->string('medical_scheme')->nullable();
            $table->string('other_insurance')->nullable();
            
            // Signature and Date
            $table->string('signed_by')->nullable();
            $table->date('claim_date')->nullable();
            
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
        Schema::dropIfExists('claim_hospital_cash');
    }
}
