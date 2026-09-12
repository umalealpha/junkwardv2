<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduledTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scheduled_transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id')->nullable();
            $table->string('policy_number')->nullable();
            $table->integer('customer_id')->nullable();
            $table->integer('installment')->nullable();
            $table->integer('retry_count')->nullable();
            $table->decimal('premium', $precision = 8, $scale = 2)->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('token')->nullable();
            $table->string('subscription_token')->nullable();
            $table->string('customer_token')->nullable();
            $table->date('billing_date')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedInteger('status')->nullable()->comment('0=payment not initiated, 1=in progress,2=successful, 3=failed, 4=policy canceled');
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
        Schema::dropIfExists('scheduled_transactions');
    }
}
