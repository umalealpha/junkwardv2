<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedRealpayPaymentRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_realpay_payment_request', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('policy_id')->nullable(true);
            $table->string('clientNumber')->nullable(true);
            $table->string('client_response_sequence')->nullable(true);

            $table->integer('clientCreated')->nullable(true);
            $table->integer('contractCreated')->nullable(true);
            $table->string('first_premium')->nullable(true);
            $table->string('premium')->nullable(true);

            $table->string('premium_updated')->nullable(true);
            $table->string('billing_day')->nullable(true);
            $table->string('billing_date')->nullable(true);
            $table->string('first_premium_contract')->nullable(true);
            $table->string('contract')->nullable(true);

            $table->string('contract_response_sequence')->nullable(true);
            $table->string('frequency')->nullable(true);
            $table->string('response')->nullable(true);
            $table->string('status')->nullable(true);

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
        Schema::dropIfExists('archived_realpay_payment_request');
    }
}
