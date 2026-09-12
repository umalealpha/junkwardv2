<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePolicyCoveragesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // create new policy_coverages
        Schema::dropIfExists('policy_coverages');
        DB::statement("CREATE TABLE `policy_coverages` (
              `id` bigint UNSIGNED NOT NULL,
              `risk_address_id` bigint DEFAULT NULL,
              `term_id` bigint NOT NULL,
              `action_id` bigint NOT NULL,
              `policy_id` int DEFAULT NULL,
              `coverage_id` int DEFAULT NULL,
              `coverage_code` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            )"
        );
        DB::statement("ALTER TABLE `policy_coverages` ADD PRIMARY KEY (`id`)");
        DB::statement("ALTER TABLE `policy_coverages` MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT");

        //create policy_coverages_Detail
        DB::statement("CREATE TABLE `policy_coverage_detail` (
              `id` bigint NOT NULL,
              `policy_coverage_id` bigint NOT NULL,
              `coverage_id` bigint NOT NULL,
              `coverage_value` decimal(11,2) NOT NULL,
              `discount_surcharge` enum('Discount','Surcharge') NOT NULL,
              `discount_surcharge_type` enum('Flat','Percentage') NOT NULL,
              `discount_surcharge_value` decimal(11,2) NOT NULL,
              `rate` decimal(11,2) NOT NULL,
              `calculated_value` decimal(11,2) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT NULL,
              `deleted_at` timestamp NULL DEFAULT NULL
            )"
        );
        DB::statement("ALTER TABLE `policy_coverage_detail` ADD PRIMARY KEY (`id`)");
        DB::statement("ALTER TABLE `policy_coverage_detail` MODIFY `id` bigint NOT NULL AUTO_INCREMENT");
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
