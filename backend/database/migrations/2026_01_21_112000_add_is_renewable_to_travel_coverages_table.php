<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsRenewableToTravelCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('travel_coverages', function (Blueprint $table) {
            $table->string('is_renewable')->nullable()->after('policy_period_months');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('travel_coverages', function (Blueprint $table) {
            $table->dropColumn('is_renewable');
        });
    }
}
