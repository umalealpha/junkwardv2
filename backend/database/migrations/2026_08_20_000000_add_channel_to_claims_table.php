<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add claims.channel (Broker/Direct) so synced tracker claims persist their
 * real channel instead of the V2 list/dashboard guessing it from the policy's
 * agent_id. Nullable + backward-compatible: existing rows stay NULL and the
 * reads fall back to the agent_id heuristic until a one-time channel remap +
 * the tracker's outbound `channel` field populate it.
 *
 * Idempotent (Schema::hasColumn guards) so a re-run / partial deploy is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'channel')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->string('channel', 20)->nullable()->after('agent_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('claims', 'channel')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }
    }
};
