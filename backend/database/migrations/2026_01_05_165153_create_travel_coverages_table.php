<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTravelCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('travel_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('policy_coverage_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();
            
            // Policyholder Information
            $table->string('policyholder')->nullable();
            $table->string('passport')->nullable();
            $table->string('phone_num')->nullable();
            $table->string('policy_number')->nullable();
            $table->integer('number_passengers')->nullable();
            
            // Coverage Period
            $table->date('effective_from')->nullable();
            $table->date('expiry')->nullable();
            
            // Policy Financials
            $table->string('policy_period')->nullable();
            $table->decimal('policy_amount', 15, 2)->nullable();
            $table->decimal('vat', 15, 2)->nullable();
            $table->decimal('total', 15, 2)->nullable();
            
            // Destination and Origin Information
            $table->text('destination_area')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('product')->nullable();
            $table->string('code')->nullable();
            
            // Insurance Company
            $table->string('insurance_company')->nullable();
            $table->string('company_location')->nullable();
            
            // Benefits (stored as JSON for flexibility)
            $table->json('benefits')->nullable();
            
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
        Schema::dropIfExists('travel_coverages');
    }
}
