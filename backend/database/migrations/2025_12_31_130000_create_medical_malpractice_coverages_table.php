<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMedicalMalpracticeCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    
    public function up()
    {
        Schema::create('medical_malpractice_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('policy_coverage_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();
            
            // Policy Details
            $table->string('policy_number')->nullable();
            $table->string('type_of_document')->nullable();
            $table->string('insured')->nullable();
            $table->string('insured_vat_number')->nullable();
            $table->string('company_registration_number')->nullable();
            $table->text('insured_business_description')->nullable();
            $table->text('insured_postal_address')->nullable();
            $table->string('intermediary')->nullable();
            $table->string('period_of_insurance')->nullable();
            $table->date('anniversary_renewal_date')->nullable();
            $table->date('retroactive_date')->nullable();
            $table->string('type_of_contract')->nullable();
            $table->string('payment_frequency')->nullable();
            $table->decimal('annual_premium', 15, 2)->nullable();
            
            // Risk Details
            $table->decimal('limit_of_indemnity', 15, 2)->nullable();
            $table->string('basis_of_limit')->nullable();
            $table->decimal('cumulative_limit', 15, 2)->nullable();
            $table->string('automatic_reinstatement')->nullable();
            $table->string('additional_reporting_period')->nullable();
            
            // Extensions (stored as JSON)
            $table->json('extensions')->nullable();
            
            // Specific Deductibles
            $table->json('specific_deductibles')->nullable();
            
            // Standard Policy Conditions
            $table->text('standard_policy_conditions')->nullable();
            $table->text('policy_wording')->nullable();
            
            // Additional fields
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Index for performance
            $table->index('policy_id');
            $table->index('policy_coverage_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('medical_malpractice_coverages');
    }
}

