<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — V2 Quote architecture hardening.
 *
 * Adds two columns to v2_pdf_jobs:
 *
 *   heartbeat_at — Timestamp the worker subprocess writes every ~30s while
 *                  running. Replaces the fragile `updated_at < NOW() - 10m`
 *                  stale-reset heuristic with an explicit liveness signal.
 *                  A live worker that's been rendering a big policy for
 *                  20 minutes will NOT be killed because its heartbeat is
 *                  fresh; a worker that OOM-killed at minute 3 is detected
 *                  within ~2 minutes because heartbeat went stale.
 *
 *   attempts    — Counter incremented each time a job is picked up by
 *                  pdf:process-one. Hard-capped in ProcessPdfJobs so a
 *                  job that keeps crashing the renderer gets parked at
 *                  status='failed' with a clear "exceeded max attempts"
 *                  message instead of looping forever on each stale-reset.
 *
 * Migration is idempotent. All NULL/0 defaults — existing 2,950+ rows
 * stay unchanged, new behavior only kicks in for jobs created after
 * deploy.
 *
 * Runs on the V2 PROD master via the mysql_system connection. The PROD
 * migrations pipeline is currently blocked by an earlier whats_app_log
 * abort — once that's unblocked this migration applies automatically.
 * For now it ships in the repo so staging picks it up cleanly and we
 * have a single source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_system')->table('v2_pdf_jobs', function (Blueprint $table) {
            if (!Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'heartbeat_at')) {
                $table->timestamp('heartbeat_at')->nullable()->after('updated_at');
            }
            if (!Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('progress');
            }
        });

        // Index lets the stale-reset sweep ('processing' rows with stale heartbeat)
        // scan only the small in-flight slice instead of the full table — at
        // ~3,000 rows today the table-scan is cheap, but it will grow.
        if (!$this->indexExists('v2_pdf_jobs', 'v2_pdf_jobs_status_heartbeat_at_index')) {
            Schema::connection('mysql_system')->table('v2_pdf_jobs', function (Blueprint $table) {
                $table->index(['status', 'heartbeat_at'], 'v2_pdf_jobs_status_heartbeat_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_system')->table('v2_pdf_jobs', function (Blueprint $table) {
            if ($this->indexExists('v2_pdf_jobs', 'v2_pdf_jobs_status_heartbeat_at_index')) {
                $table->dropIndex('v2_pdf_jobs_status_heartbeat_at_index');
            }
            if (Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'attempts')) {
                $table->dropColumn('attempts');
            }
            if (Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'heartbeat_at')) {
                $table->dropColumn('heartbeat_at');
            }
        });
    }

    /**
     * Schema::hasIndex doesn't exist before Laravel 11; check via raw query
     * against information_schema so this works on the current 9.x install.
     */
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::connection('mysql_system')->select(
            "SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1",
            [$table, $index]
        );
        return !empty($rows);
    }
};
