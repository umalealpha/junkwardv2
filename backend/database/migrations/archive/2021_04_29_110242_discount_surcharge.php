<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DiscountSurcharge extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discount_surcharge', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('role',191)->nullable(true);
            $table->integer('discount')->nullable(true);
            $table->integer('surcharge')->nullable(true);
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
        Schema::dropIfExists('discount_surcharge');
    }
}
