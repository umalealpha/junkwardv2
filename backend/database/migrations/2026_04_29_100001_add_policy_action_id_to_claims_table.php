<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors graphiteBWV8 claims.policy_action_id — the FK to policy_actions
 * that records WHICH version of a policy a claim was filed against.
 * The Reserves/Payments tab uses it to show only the coverages that were
 * live on that action term; null falls back to the full coverage list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $t) {
            if (!Schema::hasColumn('claims', 'policy_action_id')) {
                // Place near the other policy linkage columns for readability.
                $t->unsignedBigInteger('policy_action_id')->nullable()->after('policy_id');
                $t->index('policy_action_id', 'idx_claims_policy_action_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $t) {
            if (Schema::hasColumn('claims', 'policy_action_id')) {
                try { $t->dropIndex('idx_claims_policy_action_id'); } catch (\Throwable $e) { /* index may not exist */ }
                $t->dropColumn('policy_action_id');
            }
        });
    }
};
