<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxCustomerKycCustomerIdToCustomerKycTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop the existing non-unique index if it exists
        DB::statement("DROP INDEX IF EXISTS `customer_id` ON `customer_kyc`;");
        DB::statement("DROP INDEX IF EXISTS `idx_customer_kyc_customer_id` ON `customer_kyc`;");

        // Create a unique index on customer_id, drop if exists and create
        DB::statement("DROP INDEX IF EXISTS `idx_customer_kyc_customer_id_unique` ON `customer_kyc`;");
        DB::statement("CREATE UNIQUE INDEX `idx_customer_kyc_customer_id_unique` ON `customer_kyc` (`customer_id`);");
   
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         // Drop the unique index
         DB::statement("DROP INDEX IF EXISTS `idx_customer_kyc_customer_id_unique` ON `customer_kyc`;");
 
    }
}
