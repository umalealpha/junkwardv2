<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxPolicyCoverageIdToMotorTradersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_motor_traders_policy_coverage_id` ON `motor_traders`;");
        DB::statement("CREATE INDEX `idx_motor_traders_policy_coverage_id` ON `motor_traders` (`policy_coverage_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_motor_traders_policy_coverage_id` ON `motor_traders`;");
    }
}
