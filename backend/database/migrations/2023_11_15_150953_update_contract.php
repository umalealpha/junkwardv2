<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateContract extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('update_contract', function (Blueprint $table) {
            $table->id();
            $table->string('policyNumber')->nullable();
            $table->string('new_payment_method')->nullable();
            $table->string('old_payment_method')->nullable();
            $table->string('old_contract_cancel')->nullable();
            $table->integer('agent')->nullable();
            $table->integer('status')->nullable();
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
        Schema::drop('update_contract');
    }
}
