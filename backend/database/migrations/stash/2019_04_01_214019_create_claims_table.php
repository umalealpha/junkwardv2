<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClaimsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('glass_claims', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('agent_id')->unsigned()->nullable();
            $table->integer('policy_id')->unsigned()->nullable();
            $table->string('claimType')->nullable();
            $table->string('causeOfDamage')->nullable();
            $table->string('damageExtent')->nullable();
            $table->string('glassType')->nullable();
            $table->string('brokenSize')->nullable();
            $table->string('incidentDate')->nullable();
            $table->string('thirdPartyName')->nullable();
            $table->string('thirdPartyaddress')->nullable();
            $table->string('glassAddress')->nullable();
            $table->string('breakSize')->nullable();
            $table->string('claimStep');
            $table->boolean('thirdPartyInsurance')->default(0);
            $table->boolean('isActive')->default(0);
            $table->boolean('isClosed')->default(0);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('claims');
    }
}
