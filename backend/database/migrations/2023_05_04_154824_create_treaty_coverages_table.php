<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTreatyCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('treaty')) {
            Schema::create('treaty_coverages', function (Blueprint $table) {
                $table->id();
                $table->integer('treaty_id')->nullable();
                $table->integer('coverage_id')->nullable();
                $table->string('lines')->nullable();
                $table->string('limit_each')->nullable();
                $table->string('limit_total')->nullable();
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
        Schema::dropIfExists('treaty_coverages');
    }
}
