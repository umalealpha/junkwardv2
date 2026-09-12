<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrangeTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('orange_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('status');
            $table->string('policyNumber');
            $table->string('notif_token');
            $table->string('txnid');
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
        Schema::dropIfExists('orange_transactions');
    }
}
