<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index — covers the exact query pattern used in ReconciliationController:
        // WHERE policyNumber IN (...) AND status = 'SUCCESS'
        // Also covers single-column lookups on policyNumber alone.
        $this->addIndexIfMissing('payment_transactions', 'idx_pt_policy_number_status', function (Blueprint $table) {
            $table->index(['policyNumber', 'status'], 'idx_pt_policy_number_status');
        });

        // Standalone status index for queries that filter only on status
        $this->addIndexIfMissing('payment_transactions', 'idx_pt_status', function (Blueprint $table) {
            $table->index('status', 'idx_pt_status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_pt_policy_number_status');
            $table->dropIndex('idx_pt_status');
        });
    }

    private function addIndexIfMissing(string $tableName, string $indexName, \Closure $callback): void
    {
        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);
        if (empty($indexes)) {
            Schema::table($tableName, $callback);
        }
    }
};
