<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent — each column add is guarded individually so partial states
 * recover cleanly. Safe to re-run when the `migrations` tracking table
 * has drifted from the actual DB schema (post-cutover state).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cron_status')) return;
        Schema::table('cron_status', function (Blueprint $table) {
            if (!Schema::hasColumn('cron_status', 'current_step')) {
                $table->string('current_step', 50)->nullable()->after('processedCount');
            }
            if (!Schema::hasColumn('cron_status', 'last_policy_id')) {
                $table->unsignedBigInteger('last_policy_id')->nullable()->after('current_step');
            }
            if (!Schema::hasColumn('cron_status', 'error_message')) {
                $table->text('error_message')->nullable()->after('last_policy_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cron_status')) return;
        Schema::table('cron_status', function (Blueprint $table) {
            $drops = array_values(array_filter(
                ['current_step', 'last_policy_id', 'error_message'],
                fn($c) => Schema::hasColumn('cron_status', $c)
            ));
            if (!empty($drops)) $table->dropColumn($drops);
        });
    }
};
