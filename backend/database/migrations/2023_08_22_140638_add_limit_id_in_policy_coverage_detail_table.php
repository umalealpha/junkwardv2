<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLimitIdInPolicyCoverageDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverage_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverage_detail', 'limit_id')) {
                $table->string('limit_id')->after('coverage_value')->nullable();
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
        Schema::table('policy_coverage_detail', function (Blueprint $table) {
            $table->dropColumn('limit_id');
        });
    }
}
