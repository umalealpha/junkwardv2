<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NgeniusTransection extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('ngenius_transection')) {
            Schema::create('ngenius_transection', function (Blueprint $table) {
                $table->id();
                $table->string('policy_number')->nullable();
                $table->string('input')->nullable();
                $table->string('output')->nullable();
                $table->integer('status')->nullable();
                $table->string('recurring_data')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('ngenius_transection');
    }
}
