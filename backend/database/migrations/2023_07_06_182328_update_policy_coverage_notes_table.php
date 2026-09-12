<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePolicyCoverageNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('policy_coverage_notes');
        DB::statement("CREATE TABLE `policy_coverage_notes` (
          `id` bigint UNSIGNED NOT NULL,
          `policy_coverage_id` bigint NOT NULL,
          `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          `deleted_at` timestamp NULL DEFAULT NULL
        )");
        DB::statement("ALTER TABLE `policy_coverage_notes` ADD PRIMARY KEY (`id`)");
        DB::statement("ALTER TABLE `policy_coverage_notes` MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT");
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
