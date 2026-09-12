<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BusinessInterruption extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
           Schema::create('business_interruption', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();
            $table->text('nature_of_interruption')->nullable();
            $table->text('details_and_estimated_amount_of_loss')->nullable();
            $table->boolean('previously_suffered_loss');
            $table->text('other_party_interest')->nullable();
            $table->boolean('other_insurance_covering');
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
        Schema::dropIfExists('business_interruption');
    }
}
