<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PenddingReccuringDPO extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pendding_reccuring_d_p_o', function (Blueprint $table) {
            $table->bigIncrements('id');
           
            $table->string('policy_id')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('installment')->nullable();
            $table->string('retry_count')->nullable();
            $table->string('premium')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('token')->nullable();
            $table->string('subscription_token')->nullable();
            $table->string('customer_token')->nullable();
            $table->date('billing_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('reason')->nullable();
            $table->integer('status')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
            $table->string('is_custome')->nullable();
            $table->string('processed_by')->nullable();
            $table->string('added_by')->nullable();
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
        Schema::drop('pendding_reccuring_d_p_o');
    }
}
