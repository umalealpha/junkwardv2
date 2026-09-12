<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToPolicyActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_actions_policy_id` ON `policy_actions`;");
        DB::statement("CREATE INDEX `idx_policy_actions_policy_id` ON `policy_actions` (`policy_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_actions_term_id` ON `policy_actions`;");
        DB::statement("CREATE INDEX `idx_policy_actions_term_id` ON `policy_actions` (`term_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_actions_previous_action_id` ON `policy_actions`;");
        DB::statement("CREATE INDEX `idx_policy_actions_previous_action_id` ON `policy_actions` (`previous_action_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_actions_policy_id` ON `policy_actions`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_actions_term_id` ON `policy_actions`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_actions_previous_action_id` ON `policy_actions`;");
    }
}
