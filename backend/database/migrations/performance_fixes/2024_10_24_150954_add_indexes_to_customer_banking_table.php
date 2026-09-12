<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToCustomerBankingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_customer_banking_customer_id` ON `customer_banking`;");
        DB::statement("CREATE INDEX `idx_customer_banking_customer_id` ON `customer_banking` (`customer_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_customer_banking_policy_id` ON `customer_banking`;");
        DB::statement("CREATE INDEX `idx_customer_banking_policy_id` ON `customer_banking` (`policy_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_customer_banking_customer_id` ON `customer_banking`;");
        DB::statement("DROP INDEX IF EXISTS `idx_customer_banking_policy_id` ON `customer_banking`;");
    }
}
