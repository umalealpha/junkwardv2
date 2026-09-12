<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDebitAuthStatusColumnsToCustomerKycTable extends Migration
{
    /**
     * Run the migrations.
     *
     * The original bank-document migration
     * (2025_09_30_132306_add_bank_document_columns_to_customer_kyc_table)
     * added bank_statement_file_path / debit_authorization_form and the
     * bankStatementFileStatus / bankStatementFileRemark pair, but omitted
     * the matching status + remark columns for the debit authorization
     * form. The KYC document catalogue and the verify-document flow both
     * reference them (debitAuthFileStatus / debitAuthFileRemark), so add
     * them here to complete the schema.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_kyc', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_kyc', 'debitAuthFileStatus')) {
                $table->integer('debitAuthFileStatus')->nullable()->after('bankStatementFileRemark');
            }
            if (!Schema::hasColumn('customer_kyc', 'debitAuthFileRemark')) {
                $table->text('debitAuthFileRemark')->nullable()->after('debitAuthFileStatus');
            }
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
            $cols = array_filter(
                ['debitAuthFileStatus', 'debitAuthFileRemark'],
                fn ($c) => Schema::hasColumn('customer_kyc', $c)
            );
            if ($cols) {
                $table->dropColumn(array_values($cols));
            }
        });
    }
}
