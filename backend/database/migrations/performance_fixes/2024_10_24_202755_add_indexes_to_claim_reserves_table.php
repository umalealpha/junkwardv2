<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToClaimReservesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_claim_id` ON `claim_reserves`;");
        DB::statement("CREATE INDEX `idx_claim_reserves_claim_id` ON `claim_reserves` (`claim_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_product_id` ON `claim_reserves`;");
        DB::statement("CREATE INDEX `idx_claim_reserves_product_id` ON `claim_reserves` (`product_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_claim_id` ON `claim_reserves`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claim_reserves_product_id` ON `claim_reserves`;");
    }
}
