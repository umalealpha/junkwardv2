<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index realpay_contract_installments (InstalmentStatus, created_at).
 *
 * WHY
 * ---
 * Two RealPay reflection tools find "collected on RealPay but no payment row in
 * Graphite" with the same shape of query:
 *
 *     WHERE InstalmentStatus = 'S'
 *       AND created_at >= <window>
 *       AND NOT EXISTS (SELECT 1 FROM payment_transactions pt
 *                       WHERE pt.referenceNumber = InstalmentReferenceNumber)
 *
 *   - RecoverRealpaySuccessMissingTx  (realpay:recover-missing-tx)
 *   - ReconcileRealpayReflection      (realpay:reconcile-reflection --source=installments)
 *
 * The NOT EXISTS side is already indexed — payment_transactions has an index on
 * (referenceNumber, deleted_at). The DRIVING side was not:
 * realpay_contract_installments (~6.5M rows) had no index leading with
 * InstalmentStatus and none on created_at at all. Its indexes are
 * (InstalmentReferenceNumber), (clientNumber, InstalmentStatus),
 * (clientNumber, contractNumber, TrackingCode), (contractNumber,
 * InstalmentStatus) and (policy_id) — none of which can serve a query that
 * filters on status + date without a client or contract number.
 *
 * So the scan was the whole table, every run. That is what caused the
 * 2026-08-03 runaway pile-up: `realpay:recover-missing-tx --commit --days=14`
 * ran for days, outliving its withoutOverlapping(30) 30-minute lock, so a fresh
 * copy fired every 6 hours until ~26 were stacked and the master sat at 99% CPU.
 * The backstop has been disabled in both schedulers since (backend
 * Console/Kernel.php and cron/ twin), which is why installment-vs-payment gaps
 * are currently neither repaired nor reported.
 *
 * ReconcileRealpayReflection itself names this index as the prerequisite:
 *   "until (InstalmentStatus, created_at) is indexed" (source B note).
 *
 * This migration adds only that index. Re-enabling the scheduled backstop is a
 * separate, deliberate change — it should follow a timed dry run
 * (`realpay:recover-missing-tx --days=14`, no --commit) confirming the query now
 * returns in seconds, and an overlap guard that exceeds the measured runtime.
 *
 * Column order: InstalmentStatus first (equality) then created_at (range) — the
 * order that lets the optimiser seek straight to the 'S' rows in the window.
 *
 * IDEMPOTENCY (pr-guard)
 * ----------------------
 * Three layers, so a re-run of `migrate --force` against a drifted state cannot
 * halt the pipeline:
 *   1. indexExists() short-circuits before any DDL is emitted;
 *   2. the DDL itself carries IF NOT EXISTS / IF EXISTS — the server is MariaDB
 *      (11.4 on RDS), which supports those on ADD INDEX, CREATE INDEX and DROP
 *      INDEX. NOTE: plain MySQL does NOT support `CREATE INDEX IF NOT EXISTS`;
 *      if this schema is ever moved onto MySQL, layer 1 is what keeps this
 *      migration safe and the statements below must be revisited;
 *   3. the fallback path is itself guarded the same way.
 *
 * Statements are written as single complete literals rather than concatenated
 * from the constants below. That is deliberate: the pr-guard check reads the
 * first string literal after DB::statement(, so building the SQL by
 * concatenation would hide the IF NOT EXISTS from it (and from a human
 * skim-reading the file for what DDL actually runs).
 *
 * `ALGORITHM=INPLACE, LOCK=NONE` keeps the table writable while the index
 * builds. Some managed configurations reject the hint outright rather than
 * ignoring it, hence the plain CREATE INDEX fallback.
 */
return new class extends Migration {
    /** Kept in step with the DDL literals in up()/down() below. */
    private const TABLE = 'realpay_contract_installments';
    private const INDEX = 'rci_status_created_at_idx';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || $this->indexExists()) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `realpay_contract_installments` ADD INDEX IF NOT EXISTS `rci_status_created_at_idx` (`InstalmentStatus`, `created_at`), ALGORITHM=INPLACE, LOCK=NONE');
        } catch (\Throwable $e) {
            DB::statement('CREATE INDEX IF NOT EXISTS `rci_status_created_at_idx` ON `realpay_contract_installments` (`InstalmentStatus`, `created_at`)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS `rci_status_created_at_idx` ON `realpay_contract_installments`');
    }

    /** Index existence by name — Schema::hasIndex() is not available on this Laravel version. */
    private function indexExists(): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLE)
            ->where('index_name', self::INDEX)
            ->exists();
    }
};
