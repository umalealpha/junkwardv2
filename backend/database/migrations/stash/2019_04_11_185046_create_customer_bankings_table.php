<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerBankingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_banking', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('policy_id')->unsigned()->nullable();
            $table->string('billing')->nullable();
            $table->string('billingCell')->nullable();
            $table->string('bankName')->nullable();
            $table->string('branchCode')->nullable();
            $table->string('accountType')->nullable();
            $table->string('billingStartDate');
            $table->string('accountNumber')->nullable();
            $table->string('prefered')->nullable();
            $table->string('myzaka')->nullable();
            $table->string('orangeMoney')->nullable();
            $table->boolean('active')->default(0);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('policy_id')->references('id')->on('policies')->onDelete('cascade');
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
        Schema::dropIfExists('customer_bankings');
    }
}
