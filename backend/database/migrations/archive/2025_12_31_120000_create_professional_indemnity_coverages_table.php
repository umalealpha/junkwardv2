<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessionalIndemnityCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('professional_indemnity_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('policy_coverage_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();
            
            // Basic Information
            $table->string('insured')->nullable();
            $table->string('profession_business')->nullable();
            $table->string('basis_of_cover')->nullable();
            $table->string('period_of_insurance')->nullable();
            $table->date('retroactive_date')->nullable();
            $table->text('free_text_area')->nullable();
            $table->text('notes')->nullable();
            
            // Dynamic Data (JSON)
            $table->json('descriptions')->nullable();
            $table->json('insured_persons')->nullable();
            $table->json('extensions')->nullable();
            $table->json('additional_extensions')->nullable();
            $table->json('excesses')->nullable();
            
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
        Schema::dropIfExists('professional_indemnity_coverages');
    }
}

