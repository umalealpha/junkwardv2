<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxPolicyNumberToPolicyactivatecancelleddatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policyactivatecancelleddates_policyNumber` ON `policyactivatecancelleddates`;");
        DB::statement("CREATE INDEX `idx_policyactivatecancelleddates_policyNumber` ON `policyactivatecancelleddates` (`policyNumber`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policyactivatecancelleddates_policyNumber` ON `policyactivatecancelleddates`;");
    }
}
