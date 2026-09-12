<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToPolicyLedgerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop existing composite and standalone indexes
        DB::statement("DROP INDEX IF EXISTS `policy_id_trans_type_accounting_date` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `status_trans_type_accounting_date` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `policy_id_trans_type` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `policy_id_status_trans_type_accounting_date` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `policy_id` ON `policy_ledger`;");

        // Create new indexes with descriptive names, drop if exists
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_policy_id` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_policy_id` ON `policy_ledger` (`policy_id`);");
        
        // Create Composite index on policy_id, status, trans_type, and accounting_date with a descriptive name, drop if exists
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_policy_status_trans_type_date` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_policy_status_trans_type_date` ON `policy_ledger` (`policy_id`, `status`, `trans_type`, `accounting_date`);");


        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_customer_id` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_customer_id` ON `policy_ledger` (`customer_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_account_id` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_account_id` ON `policy_ledger` (`account_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_term_id` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_term_id` ON `policy_ledger` (`term_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_action_id` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_action_id` ON `policy_ledger` (`action_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_claim_id` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_claim_id` ON `policy_ledger` (`claim_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_banking_id` ON `policy_ledger`;");
        DB::statement("CREATE INDEX `idx_policy_ledger_banking_id` ON `policy_ledger` (`banking_id`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_policy_id` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_policy_status_trans_type_date` ON `policy_ledger`;");
        
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_customer_id` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_account_id` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_term_id` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_action_id` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_claim_id` ON `policy_ledger`;");
        DB::statement("DROP INDEX IF EXISTS `idx_policy_ledger_banking_id` ON `policy_ledger`;");

    }
}
