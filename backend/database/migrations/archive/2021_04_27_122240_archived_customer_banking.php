<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedCustomerBanking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_customer_banking', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('customer_id')->nullable(true);
            $table->string('policy_id')->nullable(true);
            $table->string('claim_id')->nullable(true);

            $table->string('billing')->nullable(true);
            $table->string('billingCell')->nullable(true);
            $table->string('bankName')->nullable(true);
            $table->string('branchCode')->nullable(true);

            $table->string('accountType')->nullable(true);
            $table->string('billingStartDate')->nullable(true);
            $table->string('billing_day')->nullable(true);
            $table->string('accountNumber')->nullable(true);

            $table->string('merge_ref')->nullable(true);
            $table->string('myzaka')->nullable(true);
            $table->string('orangeMoney')->nullable(true);
            $table->string('prefered')->nullable(true);
            $table->string('client_number')->nullable(true);

            $table->string('contract_number')->nullable(true);
            $table->string('failed_installments')->nullable(true);
            $table->string('active')->nullable(true);
            $table->string('reference_number')->nullable(true);

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
        Schema::dropIfExists('archived_customer_banking');
    }
}
