<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExcelNgeniusAddTrxn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('excel_ngenius_add_trxn', function (Blueprint $table) {
            $table->id();
            $table->string('policy_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('reference')->nullable();
            $table->string('action')->nullable();
            $table->string('added_by')->nullable();
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
        Schema::dropIfExists('excel_ngenius_add_trxn');
    }
}
