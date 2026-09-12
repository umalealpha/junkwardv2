<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToPolicyCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_policy_id` ON `policy_coverages`;");
        DB::statement("CREATE INDEX `idx_policy_coverages_policy_id` ON `policy_coverages` (`policy_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_policy_id_coverage_id` ON `policy_coverages`;");
        DB::statement("CREATE INDEX `idx_policy_coverages_policy_id_coverage_id` ON `policy_coverages` (`policy_id`, `coverage_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_coverage_id` ON `policy_coverages`;");
        DB::statement("CREATE INDEX `idx_policy_coverages_coverage_id` ON `policy_coverages` (`coverage_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_term_id` ON `policy_coverages`;");
        DB::statement("CREATE INDEX `idx_policy_coverages_term_id` ON `policy_coverages` (`term_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_action_id` ON `policy_coverages`;");
        DB::statement("CREATE INDEX `idx_policy_coverages_action_id` ON `policy_coverages` (`action_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_policy_id` ON `policy_coverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_policy_id_coverage_id` ON `policy_coverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_coverage_id` ON `policy_coverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_term_id` ON `policy_coverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_coverages_action_id` ON `policy_coverages`;");
    }
}
