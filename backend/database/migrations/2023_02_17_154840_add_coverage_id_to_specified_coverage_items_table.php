<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoverageIdToSpecifiedCoverageItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('specified_coverage_items', function (Blueprint $table) {
            if (!Schema::hasColumn('specified_coverage_items', 'coverage_id')) {
                $table->unsignedInteger('coverage_id')->after('effective_to')->nullable();
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
        Schema::table('specified_coverage_items', function (Blueprint $table) {
            $table->dropColumn('coverage_id');
        });
    }
}
