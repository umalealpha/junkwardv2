<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix policy_id columns across payment/contract tables so all joins
 * can use INT comparisons instead of VARCHAR CAST + REGEXP.
 *
 * Changes:
 *  1. realpay_client_contracts.policy_id    VARCHAR → INT UNSIGNED
 *  2. pendding_reccuring_d_p_o.policy_id    VARCHAR → INT UNSIGNED
 *  3. realpay_contract_installments         Add policy_id INT UNSIGNED (populated from realpay_client_contracts)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. realpay_client_contracts ─────────────────────────────────
        // Existing policy_id is VARCHAR. Clean non-numeric values first,
        // then change column type to INT UNSIGNED.
        if ($this->columnType('realpay_client_contracts', 'policy_id') !== 'int') {
            // Null-out any non-numeric values so the ALTER doesn't fail
            DB::statement("
                UPDATE realpay_client_contracts
                SET policy_id = NULL
                WHERE policy_id IS NOT NULL
                  AND (policy_id = '' OR policy_id NOT REGEXP '^[0-9]+$')
            ");

            DB::statement("
                ALTER TABLE realpay_client_contracts
                MODIFY COLUMN policy_id INT UNSIGNED NULL
            ");

            // Add index if not already present
            $this->addIndexIfMissing('realpay_client_contracts', 'idx_rcc_policy_id', 'policy_id');
        }

        // ── 2. pendding_reccuring_d_p_o ──────────────────────────────────
        // Same pattern — policy_id is VARCHAR, convert to INT UNSIGNED.
        if ($this->columnType('pendding_reccuring_d_p_o', 'policy_id') !== 'int') {
            DB::statement("
                UPDATE pendding_reccuring_d_p_o
                SET policy_id = NULL
                WHERE policy_id IS NOT NULL
                  AND (policy_id = '' OR policy_id NOT REGEXP '^[0-9]+$')
            ");

            DB::statement("
                ALTER TABLE pendding_reccuring_d_p_o
                MODIFY COLUMN policy_id INT UNSIGNED NULL
            ");

            $this->addIndexIfMissing('pendding_reccuring_d_p_o', 'idx_dpo_policy_id', 'policy_id');
        }

        // ── 3. realpay_contract_installments ────────────────────────────
        // No policy_id column. Add one and populate from realpay_client_contracts.
        if (!Schema::hasColumn('realpay_contract_installments', 'policy_id')) {
            DB::statement("
                ALTER TABLE realpay_contract_installments
                ADD COLUMN policy_id INT UNSIGNED NULL AFTER id
            ");

            // Populate from the contracts table using clientNumber + contractNumber match
            DB::statement("
                UPDATE realpay_contract_installments rci
                INNER JOIN realpay_client_contracts rcc
                    ON rcc.client_number   = rci.clientNumber
                   AND rcc.contract_number = rci.contractNumber
                   AND rcc.policy_id IS NOT NULL
                SET rci.policy_id = rcc.policy_id
                WHERE rci.policy_id IS NULL
            ");

            $this->addIndexIfMissing('realpay_contract_installments', 'idx_rci_policy_id', 'policy_id');
        }
    }

    public function down(): void
    {
        // Revert realpay_contract_installments.policy_id
        if (Schema::hasColumn('realpay_contract_installments', 'policy_id')) {
            Schema::table('realpay_contract_installments', function ($table) {
                $table->dropColumn('policy_id');
            });
        }

        // Note: reverting INT → VARCHAR for the other two tables is intentionally
        // omitted — rolling back a type narrowing would lose data.
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function columnType(string $table, string $column): string
    {
        $row = DB::selectOne("
            SELECT DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?
        ", [$table, $column]);

        return $row ? strtolower($row->DATA_TYPE) : '';
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        $exists = DB::selectOne("
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND INDEX_NAME   = ?
            LIMIT 1
        ", [$table, $indexName]);

        if (!$exists) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        }
    }
};
