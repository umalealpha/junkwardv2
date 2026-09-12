<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxPolicyIdToPolicyTermTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add id as the primary key
        DB::statement("ALTER TABLE `policy_term` ADD PRIMARY KEY (`id`);");

        // Drop the unique index on id if it exists
        DB::statement("DROP INDEX IF EXISTS `id_index` ON `policy_term`;");

         // Create the index on policy_id, Drop the existing index on policy_id if it exists
         DB::statement("DROP INDEX IF EXISTS `idx_policy_term_policy_id` ON `policy_term`;");
         DB::statement("CREATE INDEX `idx_policy_term_policy_id` ON `policy_term` (`policy_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_term_policy_id` ON `policy_term`;");
    }
}
