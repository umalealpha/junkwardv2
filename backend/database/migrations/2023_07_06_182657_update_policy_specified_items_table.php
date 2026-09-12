<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePolicySpecifiedItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('policy_specified_items');
        DB::statement("CREATE TABLE `policy_specified_items` (
          `id` bigint UNSIGNED NOT NULL,
          `policy_coverage_id` bigint DEFAULT NULL,
          `specified_coverage_id` bigint DEFAULT NULL,
          `sum_insured` decimal(10,4) NOT NULL,
          `rate` decimal(10,4) NOT NULL,
          `calculated_value` decimal(10,4) NOT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          `deleted_at` timestamp NULL DEFAULT NULL
        )");
        DB::statement("ALTER TABLE `policy_specified_items` ADD PRIMARY KEY (`id`)");
        DB::statement("ALTER TABLE `policy_specified_items` MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
