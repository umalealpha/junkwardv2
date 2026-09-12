<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTermIdActionIdInPolicyledgerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_ledger', 'term_id')) {
                $table->string('term_id')->after('policy_id')->nullable();
            }
            if (!Schema::hasColumn('policy_ledger', 'action_id')) {
                $table->string('action_id')->after('term_id')->nullable();
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
        Schema::table('policy_ledger', function (Blueprint $table) {
            $table->dropColumn('term_id');
            $table->dropColumn('action_id');
        });
    }
}
