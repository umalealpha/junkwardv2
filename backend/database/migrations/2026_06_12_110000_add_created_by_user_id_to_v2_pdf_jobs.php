<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V2 Quote Sheet — cross-user awareness.
 *
 * Adds created_by_user_id to v2_pdf_jobs so the polling endpoint can show
 * "Started by Sonali Vispute" / "Started by you" on the banner across every
 * user viewing the same policy. Lets a second underwriter see that a quote
 * is already being generated and avoid kicking off a duplicate job.
 *
 * Also adds a composite (policy_id, created_at DESC) index that the new
 * "latest job for policy" endpoint hits on every poll. Without it the
 * lookup would scan the full v2_pdf_jobs table (~3,000 rows today, grows
 * monotonically with every quote generation).
 *
 * Idempotent — column + index existence checked before each statement.
 * Runs on the V2 PROD master via the mysql_system connection. Migration
 * is safe to re-run if the broken-pipeline workaround retries it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_system')->table('v2_pdf_jobs', function (Blueprint $table) {
            if (!Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'created_by_user_id')) {
                // Nullable so existing 3,000+ rows stay valid + cron/system-
                // triggered jobs (no authenticated user) can leave it NULL.
                // No FK constraint — users table lives on the same DB but
                // we don't want a DELETE on users to cascade into job history.
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('action_id');
            }
        });

        // Index the polling endpoint hits — per-policy newest-first lookup.
        // Without this, every poll (1 per active page every 5s) does a full
        // table scan ordered by id desc; with it, it's an O(log n) index seek.
        if (!$this->indexExists('v2_pdf_jobs', 'v2_pdf_jobs_policy_id_created_at_index')) {
            Schema::connection('mysql_system')->table('v2_pdf_jobs', function (Blueprint $table) {
                $table->index(['policy_id', 'created_at'], 'v2_pdf_jobs_policy_id_created_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_system')->table('v2_pdf_jobs', function (Blueprint $table) {
            if ($this->indexExists('v2_pdf_jobs', 'v2_pdf_jobs_policy_id_created_at_index')) {
                $table->dropIndex('v2_pdf_jobs_policy_id_created_at_index');
            }
            if (Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'created_by_user_id')) {
                $table->dropColumn('created_by_user_id');
            }
        });
    }

    /**
     * Schema::hasIndex doesn't exist on Laravel 8.x; check via raw query
     * against information_schema so this works on the current install.
     */
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::connection('mysql_system')->select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $index]
        );
        return !empty($rows);
    }
};
