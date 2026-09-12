<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReversalColumnsToPaymentTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->boolean('is_reverse')->default(0)->nullable()->after('CompanyRef');
            $table->date('reversal_date')->nullable()->after('is_reverse');
            $table->unsignedInteger('reversal_by')->nullable()->after('reversal_date');
            $table->text('reversal_comment')->nullable()->after('reversal_by');
            $table->unsignedBigInteger('reveral_transaction_id')->nullable()->after('reversal_comment');
            
            $table->index('is_reverse');
            $table->index('reversal_date');
            $table->index('reveral_transaction_id');
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
            $table->dropIndex(['is_reverse']);
            $table->dropIndex(['reversal_date']);
            $table->dropIndex(['reversal_transaction_id']);
            $table->dropColumn(['is_reverse', 'reversal_date', 'reversal_by', 'reversal_comment', 'reversal_transaction_id']);
        });
    }
}

