<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePolicyPlan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_plan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('plan');
            $table->string('premium');
            $table->string('termType');
            $table->boolean('status')->default(0);
            $table->integer('sumInsured');
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
        Schema::dropIfExists('policy_plan');
    }
}
