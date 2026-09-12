<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToVehicleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Dropping existing indexes
        DB::statement("DROP INDEX IF EXISTS `policy_id` ON `vehicle`;");
        
        DB::statement("DROP INDEX IF EXISTS `vehiclePlate` ON `vehicle`;");
        DB::statement("CREATE INDEX `idx_vehicle_vehicle_plate` ON `vehicle` (`vehiclePlate`);");
        
        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_customer_id` ON `vehicle`;");
        DB::statement("CREATE INDEX `idx_vehicle_customer_id` ON `vehicle` (`customer_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_policy_id` ON `vehicle`;");
        DB::statement("CREATE INDEX `idx_vehicle_policy_id` ON `vehicle` (`policy_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_term_id` ON `vehicle`;");
        DB::statement("CREATE INDEX `idx_vehicle_term_id` ON `vehicle` (`term_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_action_id` ON `vehicle`;");
        DB::statement("CREATE INDEX `idx_vehicle_action_id` ON `vehicle` (`action_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_risk_id` ON `vehicle`;");
        DB::statement("CREATE INDEX `idx_vehicle_risk_id` ON `vehicle` (`risk_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_vehicle_plate` ON `vehicle`;");
        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_customer_id` ON `vehicle`;");
        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_policy_id` ON `vehicle`;");
        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_term_id` ON `vehicle`;");
        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_action_id` ON `vehicle`;");
        DB::statement("DROP INDEX IF EXISTS `idx_vehicle_risk_id` ON `vehicle`;");

    }
}
