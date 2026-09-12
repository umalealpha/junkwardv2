<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRowTypeInPolicyCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverages', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverages', 'row_type')) {
                $table->enum('row_type',['OLD','NEW'])->after('coverage_id')->default('NEW');
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
        Schema::table('policy_coverages', function (Blueprint $table) {
            $table->dropColumn('row_type');
        });
    }
}
