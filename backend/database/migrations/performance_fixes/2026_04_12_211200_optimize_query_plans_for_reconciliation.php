<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Round 2 — query-plan optimisations.
 *
 * Problem 1 — reconciliation_anomalies:
 *   WHERE anomaly_type = ? ORDER BY id DESC LIMIT 25
 *   MySQL was using idx_ra_anomaly_type (single column), finding 5000+ matching
 *   rows, then sorting all of them by id to find the top 25.
 *   Fix: composite (anomaly_type, id) lets MySQL walk the index backwards and
 *   stop after 25 hits — zero sort, ~25 row fetches instead of 5000+.
 *
 * Problem 2 — payment_transactions:
 *   WHERE policyNumber IN (...) AND status = 'SUCCESS'
 *   SELECT MAX(new_payment_date) GROUP BY policyNumber
 *   Existing policyNumber index found the rows but each row needed a separate
 *   data-page fetch to read new_payment_date (random I/O × thousands of rows).
 *   Fix: covering index (policyNumber, status, new_payment_date) so the entire
 *   MAX query is served from the index leaf pages — no data-page reads at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── reconciliation_anomalies ──────────────────────────────────────────
        // Drop the single-column anomaly_type index; the composite below covers it.
        $this->dropIndexIfExists('reconciliation_anomalies', 'idx_ra_anomaly_type');

        // Composite (anomaly_type, id) — supports ORDER BY id DESC LIMIT n
        // after an equality filter on anomaly_type with no filesort.
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_type_id', function (Blueprint $table) {
            $table->index(['anomaly_type', 'id'], 'idx_ra_type_id');
        });

        // Similarly for status-filtered pages (summary page + status filter)
        $this->dropIndexIfExists('reconciliation_anomalies', 'idx_ra_status');
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_status_id', function (Blueprint $table) {
            $table->index(['status', 'id'], 'idx_ra_status_id');
        });

        // ── payment_transactions ──────────────────────────────────────────────
        // Covering index for the last-payment lookup:
        //   SELECT policyNumber, MAX(new_payment_date)
        //   WHERE policyNumber IN (...) AND status = 'SUCCESS'
        //   GROUP BY policyNumber
        // With (policyNumber, status, new_payment_date) the engine never touches
        // the data pages — it reads MAX(new_payment_date) straight from the index.
        $this->addIndexIfMissing('payment_transactions', 'idx_pt_cover_lastpayment', function (Blueprint $table) {
            $table->index(['policyNumber', 'status', 'new_payment_date'], 'idx_pt_cover_lastpayment');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_anomalies', function (Blueprint $table) {
            $table->dropIndex('idx_ra_type_id');
            $table->dropIndex('idx_ra_status_id');
            // Restore originals
            $table->index('anomaly_type', 'idx_ra_anomaly_type');
            $table->index('status',       'idx_ra_status');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_pt_cover_lastpayment');
        });
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function addIndexIfMissing(string $table, string $name, \Closure $cb): void
    {
        try {
            if (empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]))) {
                Schema::table($table, $cb);
            }
        } catch (\Throwable $e) {
            \Log::warning("Could not add index {$name} on {$table}: " . $e->getMessage());
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        try {
            if (!empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]))) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        } catch (\Throwable $e) {
            \Log::warning("Could not drop index {$name} on {$table}: " . $e->getMessage());
        }
    }
};
