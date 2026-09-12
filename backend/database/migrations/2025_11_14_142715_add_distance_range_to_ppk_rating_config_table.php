<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDistanceRangeToPpkRatingConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ppk_rating_config', function (Blueprint $table) {
            $table->integer('distance_range_min')->default(200)->after('base_rate_per_km')->comment('Minimum distance range in km');
            $table->integer('distance_range_max')->default(1000)->after('distance_range_min')->comment('Maximum distance range in km');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ppk_rating_config', function (Blueprint $table) {
            $table->dropColumn(['distance_range_min', 'distance_range_max']);
        });
    }
}
