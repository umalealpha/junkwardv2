<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the indexes added to policy_ledger (see performance_fixes/
 * 2024_10_24_154117_add_indexes_to_policy_ledger_table.php). The policy_subledger
 * table was missing an index on policy_id, so the Sub Ledger query in
 * PolicyController@ledger (WHERE policy_id = ? ORDER BY id DESC) did a full-table
 * scan + filesort on every Ledger open. On large policies (e.g. MIS / Instant
 * Insurance) this made the Ledger tab hang at "0% loading".
 */
class AddIndexesToPolicySubledgerTable extends Migration
{
    public function up()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_subledger_policy_id` ON `policy_subledger`;");
        DB::statement("CREATE INDEX `idx_policy_subledger_policy_id` ON `policy_subledger` (`policy_id`);");
    }

    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_policy_subledger_policy_id` ON `policy_subledger`;");
    }
}
