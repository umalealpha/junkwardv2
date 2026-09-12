<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PayMContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pay_m_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('policyNumber');
            $table->string('contractid');
            $table->string('status');
            $table->string('primaryChannelTypeToString');
            $table->integer('firstPaymentAmountInCents');
            $table->date('firstPaymentDate')->nullable();
            $table->date('lastPaymentDate')->nullable();
            $table->string('merchantContractNumber');
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
        Schema::dropIfExists('pay_m_contracts');
    }
}
