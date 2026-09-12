<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHospitalCashbackCoapplicantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hospital_Cashback_coapplicants', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id');
            $table->integer('term_id')->nullable();
            $table->integer('action_id')->nullable();
            $table->string('risk_id', 255)->nullable();
            $table->string('relation', 20);
            $table->string('first_name', 191);
            $table->string('middle_name', 200)->nullable();
            $table->string('last_name', 191);
            $table->string('dob', 20)->nullable();
            $table->integer('gender')->nullable();
            $table->string('email', 200)->nullable();
            $table->integer('under_18_is_allowed')->nullable();
            $table->string('cellphone', 200)->nullable();
            $table->integer('payment')->nullable();
            $table->string('omang', 45)->nullable();
            $table->string('passport', 45)->nullable();
            $table->string('legalOmangExpiry', 255)->nullable();
            $table->string('legalPassportExpiry', 255)->nullable();
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
        Schema::dropIfExists('hospital_Cashback_coapplicants');
    }
}
