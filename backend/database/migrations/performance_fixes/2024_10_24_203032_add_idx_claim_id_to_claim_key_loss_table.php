<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxClaimIdToClaimKeyLossTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_key_loss_claim_id` ON `claim_key_loss`;");
        DB::statement("CREATE INDEX `idx_claim_key_loss_claim_id` ON `claim_key_loss` (`claim_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_key_loss_claim_id` ON `claim_key_loss`;");
    }
}
