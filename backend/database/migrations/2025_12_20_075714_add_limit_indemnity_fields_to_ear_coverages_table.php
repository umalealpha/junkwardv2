<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLimitIndemnityFieldsToEarCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ear_coverages', function (Blueprint $table) {
            // Add limit of indemnity fields for Risk Types
            $table->decimal('risk_earthquake_limit_indemnity', 15, 2)->nullable()->after('risk_earthquake_covered');
            $table->decimal('risk_storm_limit_indemnity', 15, 2)->nullable()->after('risk_storm_covered');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ear_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'risk_earthquake_limit_indemnity',
                'risk_storm_limit_indemnity'
            ]);
        });
    }
}
