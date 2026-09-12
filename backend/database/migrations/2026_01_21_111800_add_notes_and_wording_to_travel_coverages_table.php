<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotesAndWordingToTravelCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('travel_coverages', function (Blueprint $table) {
            $table->text('additional_notes')->nullable()->after('custom_benefits');
            $table->string('policy_wording_path')->nullable()->after('additional_notes');
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
            $table->dropColumn(['additional_notes', 'policy_wording_path']);
        });
    }
}
