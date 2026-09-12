<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToClaimVehicleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_vehicle_claim_id` ON `claim_vehicle`;");
        DB::statement("CREATE INDEX `idx_claim_vehicle_claim_id` ON `claim_vehicle` (`claim_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_claim_vehicle_vehicle_id` ON `claim_vehicle`;");
        DB::statement("CREATE INDEX `idx_claim_vehicle_vehicle_id` ON `claim_vehicle` (`vehicle_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_claim_vehicle_claim_id` ON `claim_vehicle`;");
        DB::statement("DROP INDEX IF EXISTS `idx_claim_vehicle_vehicle_id` ON `claim_vehicle`;");
    }
}
