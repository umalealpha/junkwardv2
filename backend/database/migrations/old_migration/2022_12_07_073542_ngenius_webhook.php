<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NgeniusWebhook extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ngenius_webhook', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('input')->nullable();
            $table->string('output')->nullable();
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
        Schema::drop('ngenius_webhook');
    }
}
