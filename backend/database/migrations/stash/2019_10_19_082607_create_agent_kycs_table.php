<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAgentKycsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agentapp_kycs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('omangNumber')->nullable();
            $table->string('driversLicense')->nullable();
            $table->string('omang')->nullable();
            $table->string('proofResidence')->nullable();
            $table->string('proofIncome')->nullable();
            $table->string('passport')->nullable();
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
        Schema::dropIfExists('agent_kycs');
    }
}
