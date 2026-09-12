<?php
/**
 * Smart Underwriting Upload — remember how many times the extractor was started.
 *
 * Extraction no longer relies on a queue worker existing: it is kicked as a
 * detached CLI process (SmartUnderwritingExtractJob::start) and re-kicked from
 * the upload screen's status poll when the row is still 'queued'. That retry
 * loop needs state that BOTH the web node and the spawned process can see, and
 * it must not be per-node — file cache is per-container here — so it lives on
 * the row.
 *
 * Nullable throughout: an un-migrated database just loses the retry tracking
 * (the job's stampSpawn swallows the failure), it does not break uploads.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('smart_uw_uploads')) {
            return; // base migration hasn't run yet — nothing to extend
        }

        Schema::table('smart_uw_uploads', function (Blueprint $t) {
            // How many times the extractor process has been launched for this
            // row. Capped in code (3) so a host that cannot spawn at all fails
            // the row with a real message instead of retrying forever.
            if (!Schema::hasColumn('smart_uw_uploads', 'spawn_attempts')) {
                $t->unsignedSmallInteger('spawn_attempts')->default(0)->after('message');
            }
            // When the last launch was attempted. The revive check is paced off
            // this, NOT off updated_at — updated_at is what the poll-side
            // watchdog measures the total wait from, so re-kicks must not
            // refresh it or the 1500s give-up deadline would never arrive.
            if (!Schema::hasColumn('smart_uw_uploads', 'last_spawn_at')) {
                $t->timestamp('last_spawn_at')->nullable()->after('spawn_attempts');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('smart_uw_uploads')) {
            return;
        }
        Schema::table('smart_uw_uploads', function (Blueprint $t) {
            foreach (['last_spawn_at', 'spawn_attempts'] as $col) {
                if (Schema::hasColumn('smart_uw_uploads', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
