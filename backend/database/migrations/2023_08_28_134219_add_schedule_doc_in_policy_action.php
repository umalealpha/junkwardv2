<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScheduleDocInPolicyAction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_actions', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_actions', 'schedule_doc')) {
                $table->string('schedule_doc')->after('transaction_date')->nullable();
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
            $table->dropColumn('schedule_doc');
        });
    }
}
