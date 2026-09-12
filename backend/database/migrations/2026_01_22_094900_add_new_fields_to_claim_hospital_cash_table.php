<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewFieldsToClaimHospitalCashTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('claim_hospital_cash', function (Blueprint $table) {
            // Patient Information - New fields
            $table->date('patient_dob')->nullable()->after('patient_name');
            $table->string('patient_identity_number')->nullable()->after('patient_dob');
            $table->string('relationship_other')->nullable()->after('relationship');
            
            // Claim Information - New fields
            $table->string('hospitalisation_type')->nullable()->after('discharge_time');
            $table->date('pregnancy_conception_date')->nullable()->after('symptoms_first_appeared');
            $table->date('pregnancy_delivery_date')->nullable()->after('pregnancy_conception_date');
            
            // Medical Scheme - Split into separate fields
            $table->string('medical_scheme_name')->nullable()->after('first_consultation_date');
            $table->string('medical_aid_number')->nullable()->after('medical_scheme_name');
            
            // Other Insurance - Split into separate fields
            $table->string('other_insurance_company_name')->nullable()->after('medical_aid_number');
            $table->string('other_insurance_policy_numbers')->nullable()->after('other_insurance_company_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('claim_hospital_cash', function (Blueprint $table) {
            // Drop Patient Information fields
            $table->dropColumn('patient_dob');
            $table->dropColumn('patient_identity_number');
            $table->dropColumn('relationship_other');
            
            // Drop Claim Information fields
            $table->dropColumn('hospitalisation_type');
            $table->dropColumn('pregnancy_conception_date');
            $table->dropColumn('pregnancy_delivery_date');
            
            // Drop Medical Scheme fields
            $table->dropColumn('medical_scheme_name');
            $table->dropColumn('medical_aid_number');
            
            // Drop Other Insurance fields
            $table->dropColumn('other_insurance_company_name');
            $table->dropColumn('other_insurance_policy_numbers');
        });
    }
}
