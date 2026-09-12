<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes to cron_status.
 *
 * cron_status has 196,735+ rows with NO index on `name`.
 * The kernelJobs() endpoint runs: SELECT name, MAX(start), MAX(end) FROM cron_status GROUP BY name
 * Without an index this is a full table scan on every cron portal page load.
 */
class AddIndexesCronStatus extends Migration
{
    public function up()
    {
        Schema::table('cron_status', function (Blueprint $table) {
            // Index for GROUP BY name + MAX(start) aggregation in kernelJobs()
            if (!$this->indexExists('cron_status', 'idx_cron_status_name')) {
                $table->index('name', 'idx_cron_status_name');
            }
            // Composite index for showKernelJob() — WHERE name = ? ORDER BY start DESC LIMIT 50
            if (!$this->indexExists('cron_status', 'idx_cron_status_name_start')) {
                $table->index(['name', 'start'], 'idx_cron_status_name_start');
            }
            // Index for kernelStatusSummary() — WHERE end IS NULL AND start >= ? / WHERE start >= ?
            if (!$this->indexExists('cron_status', 'idx_cron_status_start')) {
                $table->index('start', 'idx_cron_status_start');
            }
            // Index for end-based filtering
            if (!$this->indexExists('cron_status', 'idx_cron_status_end')) {
                $table->index('end', 'idx_cron_status_end');
            }
        });
    }

    public function down()
    {
        Schema::table('cron_status', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_cron_status_name');
            $table->dropIndexIfExists('idx_cron_status_name_start');
            $table->dropIndexIfExists('idx_cron_status_start');
            $table->dropIndexIfExists('idx_cron_status_end');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return count($indexes) > 0;
    }
}
