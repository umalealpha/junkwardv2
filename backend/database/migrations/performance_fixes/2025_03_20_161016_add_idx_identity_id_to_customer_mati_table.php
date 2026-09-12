<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIdxIdentityIdToCustomerMatiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_customer_mati_identity_id` ON `customer_mati`;");
        DB::statement("CREATE INDEX `idx_customer_mati_identity_id` ON `customer_mati` (`identity_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_customer_mati_identity_id` ON `customer_mati`;");
    }
}
