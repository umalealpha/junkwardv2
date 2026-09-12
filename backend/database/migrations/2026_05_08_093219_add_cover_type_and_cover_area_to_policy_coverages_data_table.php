<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoverTypeAndCoverAreaToPolicyCoveragesDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverages_data', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverages_data', 'cover_type')) {
                $table->string('cover_type')->nullable()->after('policyCoverageID');
            }
            if (!Schema::hasColumn('policy_coverages_data', 'cover_area')) {
                $table->string('cover_area')->nullable()->after('cover_type');
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
        Schema::table('policy_coverages_data', function (Blueprint $table) {
            $table->dropColumn(['cover_type', 'cover_area']);
        });
    }
}
