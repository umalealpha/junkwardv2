<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedVcsNewTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_vcs_new_transactions', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('reference')->nullable(true);
            $table->string('originalReferenceNumber')->nullable(true);
            $table->string('name')->nullable(true);

            $table->string('amount')->nullable(true);
            $table->string('goods')->nullable(true);
            $table->string('occurrences')->nullable(true);
            $table->string('frequency')->nullable(true);

            $table->string('transType')->nullable(true);
            $table->string('terminal_id')->nullable(true);
            $table->string('start_date')->nullable(true);
            $table->string('status')->nullable(true);
            $table->string('statusRef')->nullable(true);
            $table->string('authorision_Date')->nullable(true);

            $table->string('settlement_Date')->nullable(true);
            $table->string('policyNumber')->nullable(true);
            $table->string('is_smsSent')->nullable(true);
            $table->string('is_ledger')->nullable(true);



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
        Schema::dropIfExists('archived_vcs_new_transactions');
    }
}
