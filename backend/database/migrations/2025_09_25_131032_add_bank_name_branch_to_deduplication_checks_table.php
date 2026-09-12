<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBankNameBranchToDeduplicationChecksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deduplication_checks', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->index()->after('bank_account_number');
            $table->string('bank_branch')->nullable()->index()->after('bank_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deduplication_checks', function (Blueprint $table) {
            $table->dropIndex(['bank_name']);
            $table->dropIndex(['bank_branch']);
            $table->dropColumn(['bank_name', 'bank_branch']);
        });
    }
}
