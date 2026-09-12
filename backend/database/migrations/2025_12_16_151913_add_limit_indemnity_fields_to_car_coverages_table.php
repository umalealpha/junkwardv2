<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLimitIndemnityFieldsToCarCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            // Add limit of indemnity fields for Section 1 Risk Types
            $table->decimal('section1_earthquake_limit_indemnity', 15, 2)->nullable()->after('section1_risk_earthquake');
            $table->decimal('section1_storm_limit_indemnity', 15, 2)->nullable()->after('section1_risk_storm');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'section1_earthquake_limit_indemnity',
                'section1_storm_limit_indemnity'
            ]);
        });
    }
}