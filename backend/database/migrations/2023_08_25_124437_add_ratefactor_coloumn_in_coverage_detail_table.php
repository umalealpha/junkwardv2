<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRatefactorColoumnInCoverageDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverage_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverage_detail', 'ratefactor_type')) {
                $table->string('ratefactor_type')->after('limit_id')->nullable();
            }
            if (!Schema::hasColumn('policy_coverage_detail', 'ratefactor_value')) {
                $table->string('ratefactor_value')->after('ratefactor_type')->nullable();
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
            $table->dropColumn('ratefactor_type');
            $table->dropColumn('ratefactor_value');
        });
    }
}


