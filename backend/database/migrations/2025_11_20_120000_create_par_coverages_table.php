<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateParCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('par_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('policy_coverage_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();
            
            // Dynamic Insured Items (JSON)
            $table->json('insured_items')->nullable();
            
            // Additional fields
            $table->text('allow_addition')->nullable();
            
            // Execution Details
            $table->string('executed_at')->nullable();
            $table->date('execution_date')->nullable();
            $table->string('signature')->nullable();
            
            // Additional Notes
            $table->text('additional_notes')->nullable();
            
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
        Schema::dropIfExists('par_coverages');
    }
}

