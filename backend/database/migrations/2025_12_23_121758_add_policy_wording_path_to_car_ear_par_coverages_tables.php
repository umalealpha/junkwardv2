<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPolicyWordingPathToCarEarParCoveragesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add policy_wording_path to car_coverages table
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->string('policy_wording_path')->nullable()->after('signature');
        });

        // Add policy_wording_path to ear_coverages table
        Schema::table('ear_coverages', function (Blueprint $table) {
            $table->string('policy_wording_path')->nullable()->after('signature');
        });

        // Add policy_wording_path to par_coverages table
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->string('policy_wording_path')->nullable()->after('signature');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove policy_wording_path from car_coverages table
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->dropColumn('policy_wording_path');
        });

        // Remove policy_wording_path from ear_coverages table
        Schema::table('ear_coverages', function (Blueprint $table) {
            $table->dropColumn('policy_wording_path');
        });

        // Remove policy_wording_path from par_coverages table
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->dropColumn('policy_wording_path');
        });
    }
}
