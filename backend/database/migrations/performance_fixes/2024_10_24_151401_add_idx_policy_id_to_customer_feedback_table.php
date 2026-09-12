<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdxPolicyIdToCustomerFeedbackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_customer_feedback_policy_id` ON `customer_feedback`;");
        DB::statement("CREATE INDEX `idx_customer_feedback_policy_id` ON `customer_feedback` (`policy_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_customer_feedback_policy_id` ON `customer_feedback`;");
    }
}
