<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPolicyQuoteNoInPolicyAction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_actions', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_actions', 'policy_quote_no')) {
                $table->string('policy_quote_no')->after('transaction_type')->nullable();
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
            $table->dropColumn('policy_quote_no');
        });
    }
}
