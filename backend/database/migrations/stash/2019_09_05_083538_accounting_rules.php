<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AccountingRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //

        Schema::create('accounting_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('action_id');
            $table->integer('product_id');
            $table->integer('action_type');
            $table->string('action');
            $table->string('account_name');
            $table->enum('entry_type',['Credit','Debit']);
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
        //
    }
}
