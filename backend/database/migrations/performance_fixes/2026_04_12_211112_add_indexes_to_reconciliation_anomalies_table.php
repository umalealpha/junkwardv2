<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-column indexes for individual filter lookups
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_anomaly_type', function (Blueprint $table) {
            $table->index('anomaly_type', 'idx_ra_anomaly_type');
        });

        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_status', function (Blueprint $table) {
            $table->index('status', 'idx_ra_status');
        });

        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_severity', function (Blueprint $table) {
            $table->index('severity', 'idx_ra_severity');
        });

        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_run_id', function (Blueprint $table) {
            $table->index('run_id', 'idx_ra_run_id');
        });

        // Composite — covers summary query: WHERE status = 'open' GROUP BY severity / anomaly_type
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_status_severity', function (Blueprint $table) {
            $table->index(['status', 'severity'], 'idx_ra_status_severity');
        });

        // Composite — covers common filtered list: WHERE anomaly_type = ? AND status = ?
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_type_status', function (Blueprint $table) {
            $table->index(['anomaly_type', 'status'], 'idx_ra_type_status');
        });

        // Covers the 30-day trend query: WHERE created_at >= ? GROUP BY DATE(created_at)
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_created_at', function (Blueprint $table) {
            $table->index('created_at', 'idx_ra_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_anomalies', function (Blueprint $table) {
            $table->dropIndex('idx_ra_anomaly_type');
            $table->dropIndex('idx_ra_status');
            $table->dropIndex('idx_ra_severity');
            $table->dropIndex('idx_ra_run_id');
            $table->dropIndex('idx_ra_status_severity');
            $table->dropIndex('idx_ra_type_status');
            $table->dropIndex('idx_ra_created_at');
        });
    }

    private function addIndexIfMissing(string $tableName, string $indexName, \Closure $callback): void
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);
            if (empty($indexes)) {
                Schema::table($tableName, $callback);
            }
        } catch (\Throwable $e) {
            // Table may not exist yet in some environments — skip gracefully
        }
    }
};
