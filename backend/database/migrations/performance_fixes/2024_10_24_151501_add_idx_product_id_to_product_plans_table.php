<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxProductIdToProductPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_product_plans_product_id` ON `product_plans`;");
        DB::statement("CREATE INDEX `idx_product_plans_product_id` ON `product_plans` (`product_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_product_plans_product_id` ON `product_plans`;");
    }
}
