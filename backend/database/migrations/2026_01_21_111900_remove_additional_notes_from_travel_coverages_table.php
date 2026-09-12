<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveAdditionalNotesFromTravelCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('travel_coverages', function (Blueprint $table) {
            $table->dropColumn('additional_notes');
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
            $table->text('additional_notes')->nullable()->after('custom_benefits');
        });
    }
}
