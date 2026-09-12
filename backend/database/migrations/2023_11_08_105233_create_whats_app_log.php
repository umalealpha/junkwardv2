<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsAppLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('whats_app_log')) {
            return;
        }
        Schema::create('whats_app_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->string('input')->nullable();
            $table->string('output')->nullable();
            $table->string('template_type')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('status')->nullable();
            $table->text('start_time')->nullable();
            $table->text('end_time')->nullable();
            $table->string('duration')->nullable();
            $table->time('time')->nullable();
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
        Schema::dropIfExists('whats_app_log');
    }
}
