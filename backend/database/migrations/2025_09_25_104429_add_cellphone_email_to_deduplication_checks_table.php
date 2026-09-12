<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCellphoneEmailToDeduplicationChecksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deduplication_checks', function (Blueprint $table) {
            $table->string('cellphone')->nullable()->index()->after('bank_account_number');
            $table->string('email')->nullable()->index()->after('cellphone');
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
            $table->dropIndex(['cellphone']);
            $table->dropIndex(['email']);
            $table->dropColumn(['cellphone', 'email']);
        });
    }
}
