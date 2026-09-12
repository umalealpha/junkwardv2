<?php
/**
 * Smart Underwriting Upload — remember WHERE the extracted schedule is going.
 *
 * Broker schedules almost never carry a Graphite policy number (the Diesel
 * Heads 2026-2027 schedule has none at all), so the engine cannot reliably
 * infer New Business vs Endorse vs Renew. Instead the underwriter picks the
 * target BEFORE uploading — New Business, or an existing policy + one of its
 * actions — and we stamp that choice on the upload row here.
 *
 * Nullable throughout: rows written before this migration (and any future
 * upload that skips the picker) keep working as plain New Business.
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
            // 'new_business' | 'existing'. Nullable = legacy row, treat as new_business.
            if (!Schema::hasColumn('smart_uw_uploads', 'target_mode')) {
                $t->string('target_mode', 16)->nullable()->after('file_ext');
            }
            // Set only when target_mode = 'existing'.
            if (!Schema::hasColumn('smart_uw_uploads', 'target_policy_id')) {
                $t->unsignedBigInteger('target_policy_id')->nullable()->after('target_mode');
            }
            // The policy_actions row the operator chose to load the schedule into.
            if (!Schema::hasColumn('smart_uw_uploads', 'target_action_id')) {
                $t->unsignedBigInteger('target_action_id')->nullable()->after('target_policy_id');
            }
        });

        // Separate pass so the column definitely exists first. Wrapped in
        // try/catch because a re-run would otherwise throw "Duplicate key name"
        // and block the rest of the migration pipeline.
        try {
            Schema::table('smart_uw_uploads', function (Blueprint $t) {
                $t->index('target_policy_id');
            });
        } catch (\Throwable $e) {
            // index already present — fine
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('smart_uw_uploads')) {
            return;
        }
        Schema::table('smart_uw_uploads', function (Blueprint $t) {
            foreach (['target_action_id', 'target_policy_id', 'target_mode'] as $col) {
                if (Schema::hasColumn('smart_uw_uploads', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
