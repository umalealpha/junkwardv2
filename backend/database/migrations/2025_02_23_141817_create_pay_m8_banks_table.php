<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayM8BanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pay_m8_banks', function (Blueprint $table) {
            $table->id();
            $table->string('bankid');
            $table->string('bankName');
            $table->string('country');
            $table->string('alias')->nullable();
            $table->string('merchantid');
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
        Schema::dropIfExists('pay_m8_banks');
    }
}
