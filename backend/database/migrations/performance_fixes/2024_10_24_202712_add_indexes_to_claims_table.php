<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToClaimsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claims_customer_id` ON `claims`;");
        DB::statement("CREATE INDEX `idx_claims_customer_id` ON `claims` (`customer_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_claims_agent_id` ON `claims`;");
        DB::statement("CREATE INDEX `idx_claims_agent_id` ON `claims` (`agent_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_claims_policy_id` ON `claims`;");
        DB::statement("CREATE INDEX `idx_claims_policy_id` ON `claims` (`policy_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_claims_supplier_id` ON `claims`;");
        DB::statement("CREATE INDEX `idx_claims_supplier_id` ON `claims` (`supplier_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claims_customer_id` ON `claims`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claims_agent_id` ON `claims`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claims_policy_id` ON `claims`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claims_supplier_id` ON `claims`;");
    }
}
