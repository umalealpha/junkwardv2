<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayMSchuduleTransectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pay_m_schudule_transections', function (Blueprint $table) {
            $table->id();
            $table->string('policyNumber');
            $table->integer('installment')->nullable();
            $table->integer('installmentid')->nullable();
            $table->integer('paymentArrangementId')->nullable();
            $table->decimal('premium', 10, 2)->nullable();
            $table->date('billing_date')->nullable();
            $table->string('status')->nullable();
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
        Schema::dropIfExists('pay_m_schudule_transections');
    }
}
