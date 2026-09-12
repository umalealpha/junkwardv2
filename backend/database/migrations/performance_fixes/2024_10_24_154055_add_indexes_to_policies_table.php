<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToPoliciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop unique and regular indexes on `policyNumber`
        DB::statement("DROP INDEX IF EXISTS `policyNumber_UNIQUE` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `policyNumber` ON `policies`;");

        // Drop redundant index on `id` (already primary key)
        DB::statement("DROP INDEX IF EXISTS `id` ON `policies`;");

        // Drop regular index on `customer_id` if it exists
        DB::statement("DROP INDEX IF EXISTS `customer_id` ON `policies`;");
 
        // Drop composite index on (`agent_id`, `product_id`, `plan_id`, `storeID`)
        DB::statement("DROP INDEX IF EXISTS `indexjoin` ON `policies`;");
 
        // Create unique index on `policyNumber`, drop if exists and create
        DB::statement("DROP INDEX IF EXISTS `idx_policies_policyNumber_unique` ON `policies`;");
        DB::statement("CREATE UNIQUE INDEX `idx_policies_policyNumber_unique` ON `policies` (`policyNumber`);");

         
        DB::statement("DROP INDEX IF EXISTS `idx_policies_customer_id` ON `policies`;");
        DB::statement("CREATE INDEX `idx_policies_customer_id` ON `policies` (`customer_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policies_agency_id` ON `policies`;");
        DB::statement("CREATE INDEX `idx_policies_agency_id` ON `policies` (`agency_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policies_agent_id` ON `policies`;");
        DB::statement("CREATE INDEX `idx_policies_agent_id` ON `policies` (`agent_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policies_product_id` ON `policies`;");
        DB::statement("CREATE INDEX `idx_policies_product_id` ON `policies` (`product_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policies_plan_id` ON `policies`;");
        DB::statement("CREATE INDEX `idx_policies_plan_id` ON `policies` (`plan_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policies_storeID` ON `policies`;");
        DB::statement("CREATE INDEX `idx_policies_storeID` ON `policies` (`storeID`);");

        // Create composite index on (`agent_id`, `product_id`, `plan_id`, `storeID`)
        DB::statement("CREATE INDEX `idx_policies_agent_product_plan_store` ON `policies` (`agent_id`, `product_id`, `plan_id`, `storeID`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policies_policyNumber_unique` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policies_customer_id` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policies_agency_id` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policies_agent_id` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policies_product_id` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policies_plan_id` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policies_storeID` ON `policies`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policies_agent_product_plan_store` ON `policies`;");
    }
}
