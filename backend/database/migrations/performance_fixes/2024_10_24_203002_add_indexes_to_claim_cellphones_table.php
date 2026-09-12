<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToClaimCellphonesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_cellphones_claim_id` ON `claim_cellphones`;");
        DB::statement("CREATE INDEX `idx_claim_cellphones_claim_id` ON `claim_cellphones` (`claim_id`);");
        
        DB::statement("DROP INDEX IF EXISTS `idx_claim_cellphones_policy_id` ON `claim_cellphones`;");
        DB::statement("CREATE INDEX `idx_claim_cellphones_policy_id` ON `claim_cellphones` (`policy_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_cellphones_claim_id` ON `claim_cellphones`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claim_cellphones_policy_id` ON `claim_cellphones`;");
    }
}
