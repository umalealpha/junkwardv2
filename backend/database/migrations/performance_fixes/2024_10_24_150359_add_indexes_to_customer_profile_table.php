<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToCustomerProfileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        DB::statement("DROP INDEX IF EXISTS `idx_customer_profile_customer_id` ON `customer_profile`;");
        DB::statement("CREATE INDEX `idx_customer_profile_customer_id` ON `customer_profile` (`customer_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_customer_profile_created_at` ON `customer_profile`;");
        DB::statement("CREATE INDEX `idx_customer_profile_created_at` ON `customer_profile` (`created_at`);");

        DB::statement("DROP INDEX IF EXISTS `idx_customer_profile_customer_id_city_state` ON `customer_profile`;");
        DB::statement("CREATE INDEX `idx_customer_profile_customer_id_city_state` ON `customer_profile` (`customer_id`, `city`, `state`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_customer_profile_customer_id` ON `customer_profile`;");
        DB::statement("DROP INDEX IF EXISTS `idx_customer_profile_created_at` ON `customer_profile`;");
        DB::statement("DROP INDEX IF EXISTS `idx_customer_profile_customer_id_city_state` ON `customer_profile`;");
    }
}
