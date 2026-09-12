<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CronMail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cron_mail', function (Blueprint $table) {
            $table->id();
            $table->string('cron_name')->nullable();
            $table->text('production_emails')->nullable();
            $table->text('development_emails')->nullable();
            $table->string('default')->default('kkatolkar@alphadirect.co.bw');
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
        Schema::drop('cron_mail');
    }
}
