<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomBenefitsToTravelCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('travel_coverages', function (Blueprint $table) {
            $table->json('custom_benefits')->nullable()->after('benefits');
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
            $table->dropColumn('custom_benefits');
        });
    }
}
