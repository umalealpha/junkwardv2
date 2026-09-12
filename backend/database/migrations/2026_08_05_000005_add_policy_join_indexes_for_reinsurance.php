<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Companion to the coverage-code index migration. The reinsurance calc joins into
 * several policy-side tables whose join columns were unindexed:
 *   - policy_specified_items.policy_coverage_id  (63k rows, joined per coverage)
 *   - reinsurance_group_coverage.group_id        (full-scanned in the treaty subquery)
 *   - policy_reinsurance (policy_id, action_id)  (display + recalc lookups)
 *
 * Only high-value join columns are added. Deliberately NOT indexed:
 *   policy_coverages.risk_address_id, vehicle.vehicle_type, motor.registration_no,
 *   and extra columns on policy_reinsurance_details (408k, delete+reinserted every
 *   recompute) — those joins are already anchored on indexed action_id /
 *   policy_coverage_id / PK columns, so extra indexes would only slow writes.
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('policy_specified_items', 'idx_psi_policy_coverage_id', 'policy_coverage_id');
        $this->addIndex('reinsurance_group_coverage', 'idx_rgc_group_id', 'group_id');
        $this->addIndex('policy_reinsurance', 'idx_pr_policy_action', 'policy_id, action_id');
    }

    public function down(): void
    {
        $this->dropIndex('policy_specified_items', 'idx_psi_policy_coverage_id');
        $this->dropIndex('reinsurance_group_coverage', 'idx_rgc_group_id');
        $this->dropIndex('policy_reinsurance', 'idx_pr_policy_action');
    }

    private function addIndex(string $table, string $name, string $colspec): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$table, $name]
        );
        if ($exists && (int) $exists->c > 0) {
            return;
        }
        DB::statement("CREATE INDEX {$name} ON {$table} ({$colspec})");
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$table, $name]
        );
        if (! $exists || (int) $exists->c === 0) {
            return;
        }
        DB::statement("ALTER TABLE {$table} DROP INDEX {$name}");
    }
};
