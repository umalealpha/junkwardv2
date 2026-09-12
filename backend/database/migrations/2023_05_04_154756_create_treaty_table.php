<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTreatyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('treaty')) {
            Schema::create('treaty', function (Blueprint $table) {
                $table->id();
                $table->string('treaty_name')->nullable();
                $table->integer('treaty_number')->nullable();
                $table->string('effective_from')->nullable();
                $table->string('effective_to')->nullable();
                $table->string('provisional_commission')->nullable();
                $table->string('proportional_share')->nullable();
                $table->string('cash_loss_advise')->nullable();
                $table->string('event_limit')->nullable();
                $table->string('exclusions')->nullable();
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
        Schema::dropIfExists('treaty');
    }
}
