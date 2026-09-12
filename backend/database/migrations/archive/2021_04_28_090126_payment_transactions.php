<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PaymentTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_payment_transactions', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('policyNumber')->nullable(true);
            $table->string('referenceNumber')->nullable(true);
            $table->string('amount')->nullable(true);

            $table->string('status')->nullable(true);
            $table->string('paymentDate')->nullable(true);
            $table->string('paymentMethod')->nullable(true);
            $table->string('is_ledger')->nullable(true);

            $table->string('numberOfInstalmentsPaid')->nullable(true);
            $table->string('paymentFrequency')->nullable(true);
            $table->string('paymentLoggedBy')->nullable(true);
            $table->string('cashRecipient')->nullable(true);
            $table->string('note')->nullable(true);
            $table->string('payment_proof_link')->nullable(true);

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
        Schema::dropIfExists('archived_payment_transactions');
    }
}
