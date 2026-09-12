<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop the duplicate index on `id` if it exists, as it's already the primary key
        DB::statement("DROP INDEX IF EXISTS `id` ON `products`;");

        DB::statement("DROP INDEX IF EXISTS `idx_products_region_id` ON `products`;");
        DB::statement("CREATE INDEX `idx_products_region_id` ON `products` (`region_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_products_product_type_id` ON `products`;");
        DB::statement("CREATE INDEX `idx_products_product_type_id` ON `products` (`product_type_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_products_region_id` ON `products`;");
        DB::statement("DROP INDEX IF EXISTS `idx_products_product_type_id` ON `products`;");
    }
}
