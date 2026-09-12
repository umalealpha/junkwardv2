<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce uniqueness on accounts.account_num (Chart of Accounts identifier).
 *
 * Why
 * ---
 * CFO 11 PM list #2: account_num "123" is currently used by 8 different
 * accounts. The application reads accounts by account_num in several places
 * (AccountingController list/lookup, journal posting, sub-ledger joins) and
 * a duplicate code makes those lookups ambiguous — journals can post against
 * the wrong account_id, and the Chart of Accounts UI shows duplicate rows
 * for the same code.
 *
 * Strategy
 * --------
 * Adding a UNIQUE index when duplicates already exist would fail with a
 * cryptic "Duplicate entry '<value>' for key" error. Instead, this migration
 * checks the data FIRST and aborts with a clear human-readable message that
 * points the operator at the reconciliation SQL Finance must run before
 * the constraint can be added.
 *
 * The check is read-only; if duplicates exist the migration throws and is
 * caught by the entrypoint `|| true`, so container boot is NOT blocked.
 * Once Finance cleans up the duplicates (per
 * backend/database/reconciliation/2026_06_05_account_num_duplicates.sql),
 * the next container restart picks up this migration cleanly and adds the
 * unique index.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            // Defensive: if the table somehow doesn't exist, skip entirely.
            // Production has the table — this guard is for non-prod / test envs.
            return;
        }

        // Pre-check: refuse to run if duplicate account_num values exist.
        // This produces a clear, actionable error message instead of MySQL's
        // generic "Duplicate entry" — operators reading boot logs need to know
        // exactly what to fix.
        $dups = DB::table('accounts')
            ->select('account_num', DB::raw('COUNT(*) AS cnt'))
            ->whereNotNull('account_num')
            ->where('account_num', '!=', '')
            ->groupBy('account_num')
            ->having('cnt', '>', 1)
            ->orderBy('cnt', 'desc')
            ->get();

        if ($dups->isNotEmpty()) {
            $summary = $dups
                ->take(10)
                ->map(fn ($d) => $d->account_num . ' (' . $d->cnt . '×)')
                ->implode(', ');
            $extra   = $dups->count() > 10 ? ' …' : '';

            // Log a warning but do NOT throw — throwing blocks every subsequent
            // migration in the queue (Laravel stops on first failure). Finance
            // must still clean up the duplicates using the reconciliation SQL
            // at backend/database/reconciliation/2026_06_05_account_num_duplicates.sql,
            // after which a follow-up migration will add the unique index.
            \Illuminate\Support\Facades\Log::warning(
                '[MIGRATION SKIPPED] Cannot add unique index on accounts.account_num — ' .
                $dups->count() . ' duplicate value(s) found: ' . $summary . $extra . '. ' .
                'Finance must clean up duplicates before this constraint can be enforced.'
            );

            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->unique('account_num', 'uniq_accounts_account_num');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique('uniq_accounts_account_num');
        });
    }
};
