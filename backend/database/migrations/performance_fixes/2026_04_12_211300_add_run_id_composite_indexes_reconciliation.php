<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes that include run_id so ORDER BY run_id DESC, id DESC
 * can be satisfied from the index without a filesort.
 */
return new class extends Migration
{
    public function up(): void
    {
        // (anomaly_type, run_id, id) — optimal for:
        //   WHERE anomaly_type = ? ORDER BY run_id DESC, id DESC LIMIT n
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_type_run_id', function (Blueprint $table) {
            $table->index(['anomaly_type', 'run_id', 'id'], 'idx_ra_type_run_id');
        });

        // (run_id, id) — optimal for unfiltered list ORDER BY run_id DESC, id DESC
        $this->addIndexIfMissing('reconciliation_anomalies', 'idx_ra_run_id_id', function (Blueprint $table) {
            $table->index(['run_id', 'id'], 'idx_ra_run_id_id');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_anomalies', function (Blueprint $table) {
            $table->dropIndex('idx_ra_type_run_id');
            $table->dropIndex('idx_ra_run_id_id');
        });
    }

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
};
