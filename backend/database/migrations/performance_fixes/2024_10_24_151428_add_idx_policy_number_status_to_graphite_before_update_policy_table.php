<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxPolicyNumberStatusToGraphiteBeforeUpdatePolicyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('mysql2')->statement("DROP INDEX IF EXISTS `idx_policyNumber_status` ON `before_update_policy`;");
        DB::connection('mysql2')->statement("CREATE INDEX `idx_policyNumber_status` ON `before_update_policy` (`policyNumber`, `status`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('mysql2')->statement("DROP INDEX IF EXISTS `idx_policyNumber_status` ON `before_update_policy`;");
    }
}
