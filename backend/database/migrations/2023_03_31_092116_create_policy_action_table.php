<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyActionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_actions', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_actions', 'quotation_doc')) {
                $table->string('quotation_doc')->after('transaction_date')->nullable();
            }
            if (!Schema::hasColumn('policy_actions', 'rate_doc')) {
                $table->string('rate_doc')->after('transaction_date')->nullable();
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
        Schema::table('policy_actions', function (Blueprint $table) {
            $table->dropColumn('quotation_doc');
            $table->dropColumn('rate_doc');
        });
    }
}
