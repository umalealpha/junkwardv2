<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToPolicyCoverageDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverage_detail_policy_coverage_id` ON `policy_coverage_detail`;");
        DB::statement("CREATE INDEX `idx_policy_coverage_detail_policy_coverage_id` ON `policy_coverage_detail` (`policy_coverage_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverage_detail_coverage_id` ON `policy_coverage_detail`;");
        DB::statement("CREATE INDEX `idx_policy_coverage_detail_coverage_id` ON `policy_coverage_detail` (`coverage_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverage_detail_policy_coverage_id` ON `policy_coverage_detail`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverage_detail_coverage_id` ON `policy_coverage_detail`;");
    }
}
