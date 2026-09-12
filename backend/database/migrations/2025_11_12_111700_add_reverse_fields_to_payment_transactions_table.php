<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReverseFieldsToPaymentTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reveral_transaction_id')->nullable()->after('CompanyRef');
            $table->boolean('is_reverse')->default(0)->after('reveral_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
          Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['reveral_transaction_id', 'is_reverse']);
        });
    }
}
