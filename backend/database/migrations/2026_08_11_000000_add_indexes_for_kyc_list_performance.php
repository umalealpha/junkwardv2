<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the unified KYC list
 * (Api/V1/CustomerKycController::index).
 *
 * That endpoint anchors on `customer_kyc` and joins:
 *   • a MIN(id)-per-customer derived table over the WHOLE `policies` table
 *     (WHERE status <> 2 GROUP BY customer_id), and
 *   • a MAX(id)-per-customer derived table over `customer_kyc_dom_com`,
 * then ORDERs by customer_kyc.updated_at DESC.
 *
 * Unindexed, the paginate() COUNT materialises both derived tables on every
 * load with no LIMIT to short-circuit, and the query blows past the
 * frontend's 30s request timeout — leaving the KYC list blank (staging
 * regression, Aug 2026). These composite indexes let MySQL resolve the two
 * GROUP BY subqueries and the ORDER BY from the index instead of full scans.
 *
 * Safe to run live: adding a secondary index in MySQL 8 is an online INPLACE
 * operation (reads + writes continue). Still prefer a low-traffic window on
 * the large `policies` table. Guarded + idempotent — a re-run, or a table
 * that already carries the index, is a no-op.
 *
 * Default (mysql) connection: customer_kyc / policies / customer_kyc_dom_com
 * live there, consistent with the other customer_kyc column migrations (none
 * of which set ->connection()).
 */
return new class extends Migration {
    public function up(): void
    {
        // Serves the KYC-list policy-anchor: WHERE status <> 2 GROUP BY
        // customer_id, MIN(id) — streaming index scan, no temp/filesort.
        // This is the shape actually applied on staging. product_id is NOT in
        // the index on purpose: the rewritten controller checks the tier via a
        // correlated EXISTS on the customer's (few) policies, so it isn't
        // needed here.
        $this->addIndex('policies', ['customer_id', 'status', 'id'], 'policies_customer_status_id_index');
        // Supports: GROUP BY customer_id, MAX(id)
        $this->addIndex('customer_kyc_dom_com', ['customer_id', 'id'], 'ckdc_customer_id_id_index');
        // Supports: ORDER BY updated_at DESC, id DESC
        $this->addIndex('customer_kyc', ['updated_at', 'id'], 'customer_kyc_updated_at_id_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('policies', 'policies_customer_status_id_index');
        $this->dropIndexIfExists('customer_kyc_dom_com', 'ckdc_customer_id_id_index');
        $this->dropIndexIfExists('customer_kyc', 'customer_kyc_updated_at_id_index');
    }

    private function addIndex(string $table, array $cols, string $name): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        foreach ($cols as $col) {
            if (!Schema::hasColumn($table, $col)) {
                return; // schema differs on this install — skip rather than fail
            }
        }
        if ($this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->index($cols, $name));
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        }
    }

    /**
     * Schema::hasIndex() doesn't exist before Laravel 11; check via
     * information_schema so this works on the current 8.x install.
     */
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1",
            [$table, $index]
        );

        return !empty($rows);
    }
};
