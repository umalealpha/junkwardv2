<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreviousActionIdToPolicyActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_actions', function (Blueprint $table) {
            $table->bigInteger('previous_action_id')->after('term_id')->nullable();
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
            $table->dropColumn('previous_action_id');
        });
    }
}
