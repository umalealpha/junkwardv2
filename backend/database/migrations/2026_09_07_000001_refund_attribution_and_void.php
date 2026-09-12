<?php

/**
 * Two attribution gaps in the refund engine, both ours.
 *
 * ── 1. Who marked a refund paid outside Graphite? ───────────────────────────
 * `settleManually()` (added 1 Sep) records manual_paid_at and manual_paid_ref
 * but NOT the actor. So 37 refunds — BWP 46,763.05 — say the money was paid
 * outside the system without the row saying who asserted it. Attribution exists
 * only in refund_request_events. A money record should carry its own actor.
 * Backfilled below from the events, which do have it.
 *
 * ── 2. A posted accounting entry cannot be neutralised ──────────────────────
 * refund_accounting_entries has dismissed_by/at/reason, but dismiss() only
 * accepts 'pending_review'. The 11 rows written directly to PROD on
 * 2026-08-13 12:24:50 are all 'posted', so today the ONLY way to clear them is
 * DELETE — which destroys the evidence of an unexplained production write.
 *
 * Three of those rows sit on genuine return-premium refunds (RFND-000010,
 * -000019, -000022). Because refund_request_id is UNIQUE, enqueueIfReturnPremium()
 * finds the fake row, returns it, and the real credit note is never raised —
 * silently, with no error.
 *
 * So voiding must also RELEASE the slot, or it is cosmetic. The unique key
 * becomes (refund_request_id, void_marker): 0 for the one live entry, and the
 * row's own id once voided. One live entry per refund is still guaranteed;
 * any number of voided ones may sit beside it, preserved.
 *
 * Nothing is voided here. That is a data decision and it waits on knowing who
 * wrote those rows. This only makes the correct handling possible.
 *
 * Additive and reversible. No money is touched.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── 1. the actor on a manual settlement ─────────────────────────────
        if (Schema::hasTable('refund_requests')
            && !Schema::hasColumn('refund_requests', 'manual_paid_by')) {
            Schema::table('refund_requests', function (Blueprint $t) {
                $t->unsignedBigInteger('manual_paid_by')->nullable()->after('manual_paid_ref');
            });

            // Recover the 37 existing ones from their own audit trail. The
            // event carries actor_id; the row never did.
            DB::statement("
                UPDATE refund_requests r
                JOIN (
                    SELECT e.refund_request_id, MAX(e.actor_id) AS actor_id
                    FROM refund_request_events e
                    WHERE e.action = 'settled_manual' AND e.actor_id IS NOT NULL
                    GROUP BY e.refund_request_id
                ) x ON x.refund_request_id = r.id
                SET r.manual_paid_by = x.actor_id
                WHERE r.manual_paid_by IS NULL
            ");
        }

        // ── 2. voiding a posted accounting entry ────────────────────────────
        if (!Schema::hasTable('refund_accounting_entries')) {
            return;
        }
        Schema::table('refund_accounting_entries', function (Blueprint $t) {
            if (!Schema::hasColumn('refund_accounting_entries', 'voided_by')) {
                $t->unsignedBigInteger('voided_by')->nullable()->after('dismiss_reason');
            }
            if (!Schema::hasColumn('refund_accounting_entries', 'voided_at')) {
                $t->timestamp('voided_at')->nullable()->after('voided_by');
            }
            if (!Schema::hasColumn('refund_accounting_entries', 'void_reason')) {
                $t->string('void_reason', 500)->nullable()->after('voided_at');
            }
            if (!Schema::hasColumn('refund_accounting_entries', 'void_marker')) {
                // 0 = the live entry. Once voided, set to the row's own id so
                // the slot is released without deleting anything.
                $t->unsignedBigInteger('void_marker')->default(0)->after('void_reason');
            }
        });

        // Swap UNIQUE(refund_request_id) for UNIQUE(refund_request_id, void_marker).
        // Guarded: on a fresh database the index name may differ, and re-running
        // must not fail.
        $idx = DB::select("
            SELECT INDEX_NAME FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'refund_accounting_entries'
              AND INDEX_NAME = 'refund_accounting_entries_refund_request_id_unique'
            LIMIT 1
        ");
        if (!empty($idx)) {
            DB::statement('ALTER TABLE refund_accounting_entries
                           DROP INDEX refund_accounting_entries_refund_request_id_unique');
        }
        $new = DB::select("
            SELECT INDEX_NAME FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'refund_accounting_entries'
              AND INDEX_NAME = 'refund_acct_entry_live_unique' LIMIT 1
        ");
        if (empty($new)) {
            DB::statement('ALTER TABLE refund_accounting_entries
                           ADD UNIQUE INDEX refund_acct_entry_live_unique (refund_request_id, void_marker)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('refund_accounting_entries')) {
            $new = DB::select("
                SELECT INDEX_NAME FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'refund_accounting_entries'
                  AND INDEX_NAME = 'refund_acct_entry_live_unique' LIMIT 1
            ");
            if (!empty($new)) {
                // Only restorable while no refund holds more than one entry.
                $dupes = DB::table('refund_accounting_entries')
                    ->select('refund_request_id')->groupBy('refund_request_id')
                    ->havingRaw('COUNT(*) > 1')->exists();
                DB::statement('ALTER TABLE refund_accounting_entries DROP INDEX refund_acct_entry_live_unique');
                if (!$dupes) {
                    DB::statement('ALTER TABLE refund_accounting_entries
                                   ADD UNIQUE INDEX refund_accounting_entries_refund_request_id_unique (refund_request_id)');
                }
            }
            Schema::table('refund_accounting_entries', function (Blueprint $t) {
                foreach (['void_marker', 'void_reason', 'voided_at', 'voided_by'] as $c) {
                    if (Schema::hasColumn('refund_accounting_entries', $c)) {
                        $t->dropColumn($c);
                    }
                }
            });
        }
        if (Schema::hasTable('refund_requests')
            && Schema::hasColumn('refund_requests', 'manual_paid_by')) {
            Schema::table('refund_requests', function (Blueprint $t) {
                $t->dropColumn('manual_paid_by');
            });
        }
    }
};
