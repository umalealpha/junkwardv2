<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LlmApi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('llm_api', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('payment_transection_id')->nullable();
            $table->string('post_data')->nullable();
            $table->string('response_data')->nullable();
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
        Schema::drop('llm_api');
    }
}
