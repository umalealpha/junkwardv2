<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToClaimReservesCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_reserve_id` ON `claim_reserves_coverages`;");
        DB::statement("CREATE INDEX `idx_claim_reserves_coverages_reserve_id` ON `claim_reserves_coverages` (`reserve_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_claim_id` ON `claim_reserves_coverages`;");
        DB::statement("CREATE INDEX `idx_claim_reserves_coverages_claim_id` ON `claim_reserves_coverages` (`claim_id`);");
        
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_product_id` ON `claim_reserves_coverages`;");
        DB::statement("CREATE INDEX `idx_claim_reserves_coverages_product_id` ON `claim_reserves_coverages` (`product_id`);");
        
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_coverage_id` ON `claim_reserves_coverages`;");
        DB::statement("CREATE INDEX `idx_claim_reserves_coverages_coverage_id` ON `claim_reserves_coverages` (`coverage_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_reserve_id` ON `claim_reserves_coverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_claim_id` ON `claim_reserves_coverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_product_id` ON `claim_reserves_coverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_coverages_coverage_id` ON `claim_reserves_coverages`;");
    }
}
