<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PolicyUpgrade extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_upgrade', function (Blueprint $table) {
       
            $table->id();
            $table->string('policy_id')->nullable();
            $table->string('product_id')->nullable();
            $table->string('old_plan_id')->nullable();
            $table->string('new_plan_id')->nullable();
            $table->string('agent_id')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('policy_upgrade');
    }
}
