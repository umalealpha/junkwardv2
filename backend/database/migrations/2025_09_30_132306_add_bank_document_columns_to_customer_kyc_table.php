<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBankDocumentColumnsToCustomerKycTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_kyc', function (Blueprint $table) {
            $table->string('bank_statement_file_path')->nullable()->after('proof_incomeRemark');
            $table->string('debit_authorization_form')->nullable()->after('bank_statement_file_path');
            $table->integer('bankStatementFileStatus')->nullable()->after('debit_authorization_form');
            $table->text('bankStatementFileRemark')->nullable()->after('bankStatementFileStatus');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_kyc', function (Blueprint $table) {
            $table->dropColumn(['bank_statement_file_path', 'debit_authorization_form', 'bankStatementFileStatus', 'bankStatementFileRemark']);
        });
    }
}