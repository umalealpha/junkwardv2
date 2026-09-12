<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add started_at + finished_at to cron_runs so the Python engine
 * can record a proper start timestamp at job launch and a finish
 * timestamp on completion — independently of created_at.
 *
 * Also backfills existing rows: sets finished_at = created_at for
 * completed rows (since created_at was written at end), and
 * estimates started_at from elapsed where available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cron_runs', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('job_key')
                  ->comment('When the job actually started executing');
            $table->timestamp('finished_at')->nullable()->after('started_at')
                  ->comment('When the job completed (ok or error)');
        });

        // Backfill: for completed rows, finished_at = created_at (was written at end)
        DB::statement("
            UPDATE cron_runs
            SET finished_at = created_at
            WHERE status IN ('ok', 'error')
              AND finished_at IS NULL
        ");

        // Best-effort: for 'running' rows, started_at = created_at
        DB::statement("
            UPDATE cron_runs
            SET started_at = created_at
            WHERE started_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('cron_runs', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'finished_at']);
        });
    }
};
