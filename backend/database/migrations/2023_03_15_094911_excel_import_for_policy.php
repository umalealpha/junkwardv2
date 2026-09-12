<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExcelImportForPolicy extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('excel_import_for_policy')) {
            Schema::create('excel_import_for_policy', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_id');
                $table->string('policyNumber');
                $table->string('action')->nullable();
                $table->text('remark')->nullable();
                $table->integer('status')->nullable();
                $table->string('added_by')->nullable();
                $table->integer('last_success_transection_id')->nullable();
                $table->date('last_success_transection_date')->nullable();
                $table->string('customer_age')->nullable();
                $table->integer('issue_credit_note')->nullable();
                $table->integer('generate_invoice')->nullable();
                $table->integer('email_sent')->nullable();
                $table->integer('sms_sent')->nullable();
                $table->timestamps();
            });
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('excel_import_for_policy');
    }
}
