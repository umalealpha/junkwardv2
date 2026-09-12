<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BundledRerate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bundled_rerate', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id')->nullable();
            $table->integer('product_id')->nullable();
            $table->integer('plan_name')->nullable();
            $table->string('premium')->nullable();
            $table->string('frequency_mc')->nullable();
            $table->string('subtotal')->nullable();
            $table->string('final_premium')->nullable();
            $table->string('bundled_discount_precent')->nullable();
            $table->string('bundled_discount')->nullable();
            $table->string('paymenturl')->nullable();
            
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
        Schema::dropIfExists('bundled_rerate');
    }
}
