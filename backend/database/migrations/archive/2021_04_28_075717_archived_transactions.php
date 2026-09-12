<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_transactions', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('orangeTransaction_id')->nullable(true);
            $table->string('vcsTransaction_id')->nullable(true);
            $table->string('referenceNumber')->nullable(true);

            $table->string('realPayTransaction_id')->nullable(true);
            $table->string('flutterwave_id')->nullable(true);
            $table->string('transactionType')->nullable(true);
            $table->string('customer_id')->nullable(true);

            $table->string('policyNumber')->nullable(true);
            $table->string('status')->nullable(true);
            $table->string('paymentDescription')->nullable(true);
            $table->string('amount')->nullable(true);

            $table->string('created_at')->nullable(true);
            $table->string('updated_at')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('archived_transactions');
    }
}
