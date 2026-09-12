<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBankDocumentColumnsToCustomerBankingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Banking documents (Bank Statement + Debit Authorization Form) live on
     * the Banking Details tab and are stored on customer_banking alongside
     * the rest of the banking record. The CustomerBanking model already
     * lists these in $fillable; this migration creates the backing columns
     * plus the per-document approval status + remark used by the
     * approve/reject flow. Status: 0 = pending, 1 = approved, 2 = rejected.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_banking', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_banking', 'bank_statement_file_path')) {
                $table->string('bank_statement_file_path')->nullable()->after('accountNumber');
            }
            if (!Schema::hasColumn('customer_banking', 'debit_authorization_form')) {
                $table->string('debit_authorization_form')->nullable()->after('bank_statement_file_path');
            }
            if (!Schema::hasColumn('customer_banking', 'bankStatementFileStatus')) {
                $table->integer('bankStatementFileStatus')->nullable()->after('debit_authorization_form');
            }
            if (!Schema::hasColumn('customer_banking', 'bankStatementFileRemark')) {
                $table->text('bankStatementFileRemark')->nullable()->after('bankStatementFileStatus');
            }
            if (!Schema::hasColumn('customer_banking', 'debitAuthFileStatus')) {
                $table->integer('debitAuthFileStatus')->nullable()->after('bankStatementFileRemark');
            }
            if (!Schema::hasColumn('customer_banking', 'debitAuthFileRemark')) {
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
        Schema::table('customer_banking', function (Blueprint $table) {
            $cols = array_filter([
                'bank_statement_file_path',
                'debit_authorization_form',
                'bankStatementFileStatus',
                'bankStatementFileRemark',
                'debitAuthFileStatus',
                'debitAuthFileRemark',
            ], fn ($c) => Schema::hasColumn('customer_banking', $c));
            if ($cols) {
                $table->dropColumn(array_values($cols));
            }
        });
    }
}
