<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency invariant for policy_ledger (Payment-reflection bulletproofing,
 * design §2.A / D:\tmp\sync_review_ledger_statement_sink.md §5.A):
 *
 *   ADD UNIQUE (policy_id, trans_type, trans_ref)
 *
 * Today the ONLY guard against a double-posted Payment is application-level
 * trans_ref dedup + a 120s overlap timer — there is NO DB constraint
 * (`idx_policy_ledger_trans_ref` is Non_unique). This makes double-posting
 * impossible even under churn/concurrency, and lets the poster + the
 * reconciliation:repair backstop use INSERT ... ON DUPLICATE KEY / firstOrCreate.
 *
 * ⚠️ CAUTION — THE LIVE TABLE HAS EXISTING DUPLICATES (is_ledger reset churn,
 * legacy re-posts, soft-delete + re-create). A blind unique-index ADD would FAIL
 * with error 1062. This migration is therefore DELIBERATELY DEPLOY-SAFE and NON-
 * DESTRUCTIVE:
 *
 *   1. It NEVER deletes or mutates any ledger row.
 *   2. It NEVER throws — the entrypoint runs `migrate --force` FAIL-LOUD on every
 *      boot, so a throw would abort ALL backend deploys. On duplicates (or when
 *      not explicitly enabled) it LOGS guidance and returns (no-op).
 *   3. It only ADDs the index when BOTH: env LEDGER_UNIQUE_INDEX_ENABLE=true AND
 *      zero duplicate groups exist. Idempotent — no-op if the index already exists.
 *
 * ⚠️ HOW TO ACTUALLY ENABLE THE INDEX (a migration runs ONCE — this file will
 * already be recorded as "migrated" after its first no-op deploy, so flipping the
 * flag + redeploying will NOT re-run it). When ready:
 *   a. `php artisan reconciliation:list-ledger-duplicates` → dedup with Finance/IT
 *      sign-off until it reports 0 (financial-row dedup is a reviewed data-fix).
 *   b. HARDEN the ledger posters (PolicyLedgerDaily inserts a Payment row with no
 *      pre-existence guard → a churn-reset is_ledger could 1062-abort the nightly
 *      run once the index is live) to firstOrCreate / INSERT ... ON DUPLICATE KEY.
 *   c. THEN add the index via a FRESH migration authored at that time (or, on a
 *      controlled maintenance window, `migrate:rollback` this file and re-migrate
 *      with LEDGER_UNIQUE_INDEX_ENABLE=true). Do NOT rely on flag-flip alone.
 *
 * A composite unique index does not constrain rows where any indexed column is
 * NULL, so the duplicate pre-check matches that: it only counts rows where
 * policy_id, trans_type AND trans_ref are all non-null (Payment rows always are).
 * Soft-deleted rows ARE counted — the index is enforced on physical rows.
 *
 * NOT scheduled. Default behaviour on every deploy = safe no-op.
 */
return new class extends Migration {
    private string $indexName = 'uq_policy_ledger_payment_idem';

    public function up(): void
    {
        if (! Schema::hasTable('policy_ledger')) {
            return; // table not on this connection — nothing to do (self-healing)
        }

        if ($this->indexExists()) {
            return; // already applied — idempotent no-op
        }

        // DEPLOY-SAFE: the entrypoint runs `migrate --force` FAIL-LOUD on every
        // boot, so this migration must NEVER throw. Adding the UNIQUE index is a
        // DELIBERATE, gated step that must only happen AFTER (a) duplicates are
        // removed and (b) the non-idempotent ledger posters (PolicyLedgerDaily
        // inserts a Payment row with no pre-existence guard) are hardened to
        // firstOrCreate / INSERT ... ON DUPLICATE KEY — otherwise a churn-reset
        // is_ledger could make the nightly poster hit error 1062 and abort.
        // Therefore the add is gated behind an explicit opt-in flag; by default
        // this is a safe no-op on every deploy.
        if (! filter_var(env('LEDGER_UNIQUE_INDEX_ENABLE', false), FILTER_VALIDATE_BOOLEAN)) {
            \Log::info('add_payment_idempotency_unique: SKIPPED (gated no-op). Index NOT added. '
                . 'NOTE: this migration will be recorded as migrated, so flipping the flag + '
                . 'redeploying will NOT re-run it. To enable later: (1) `reconciliation:list-ledger-duplicates` '
                . '-> dedup with Finance/IT sign-off until 0; (2) harden the ledger posters to '
                . 'firstOrCreate/ON DUPLICATE KEY; (3) add the index via a FRESH migration (or a controlled '
                . 'migrate:rollback + re-migrate with LEDGER_UNIQUE_INDEX_ENABLE=true).');
            return;
        }

        $dupGroups = (int) (DB::selectOne("
            SELECT COUNT(*) AS c FROM (
                SELECT 1
                FROM policy_ledger
                WHERE policy_id IS NOT NULL
                  AND trans_type IS NOT NULL
                  AND trans_ref IS NOT NULL
                GROUP BY policy_id, trans_type, trans_ref
                HAVING COUNT(*) > 1
            ) d
        ")->c ?? 0);

        // Still non-aborting even when enabled: if dups remain, skip (do not throw),
        // so an accidental flag flip can never break the deploy.
        if ($dupGroups > 0) {
            \Log::warning("add_payment_idempotency_unique: SKIPPED — policy_ledger still has {$dupGroups} "
                . "duplicate (policy_id, trans_type, trans_ref) group(s). Dedup them "
                . "(`reconciliation:list-ledger-duplicates`) before enabling. Index NOT added; deploy not aborted.");
            return;
        }

        DB::statement(
            "ALTER TABLE `policy_ledger` "
            . "ADD UNIQUE INDEX `{$this->indexName}` (`policy_id`, `trans_type`, `trans_ref`)"
        );
        \Log::info('add_payment_idempotency_unique: UNIQUE index added.');
    }

    public function down(): void
    {
        if (Schema::hasTable('policy_ledger') && $this->indexExists()) {
            DB::statement("DROP INDEX `{$this->indexName}` ON `policy_ledger`");
        }
    }

    private function indexExists(): bool
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'policy_ledger'
              AND INDEX_NAME = ?
        ", [$this->indexName]);

        return ((int) ($row->c ?? 0)) > 0;
    }
};
