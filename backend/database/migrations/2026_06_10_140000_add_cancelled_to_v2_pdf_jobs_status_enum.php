<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add 'cancelled' to v2_pdf_jobs.status ENUM + repair existing rows.
 *
 * Bug confirmed on PROD 2026-06-10:
 *   - Schema:  status ENUM('queued','queued_long','processing','completed','failed')
 *   - Code (NotificationController::cancelDocumentJob) writes
 *     `'status' => 'cancelled'` when a user clicks Cancel in the
 *     Document Jobs UI.
 *   - MySQL/MariaDB in non-strict mode silently coerces out-of-ENUM
 *     values to '' (empty string). The UPDATE proceeded, message was
 *     set correctly, but status became '' instead of 'cancelled'.
 *
 * Symptom users saw: clicking Cancel "did nothing visible" — the row
 * stayed indistinguishable from a fresh queue entry. Then because the
 * dedupe filter checks `status IN ('queued','queued_long','processing')`,
 * the cancelled-but-empty row was invisible to dedupe, so the next
 * Generate Quote click stacked another row on top — producing the
 * 2-3 duplicate stacks per (policy_id, action_id) seen in PROD.
 *
 * Fix:
 *  1. MODIFY the ENUM to include 'cancelled'.
 *  2. Backfill all existing empty-status rows that carry the cancel
 *     marker message to the correct 'cancelled' status.
 *
 * Both steps are idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1 — widen the ENUM. Raw SQL because Schema Builder doesn't
        // support modifying ENUMs cleanly. mysql_system is the V2-owned
        // connection (V2 PROD master); the legacy `mysql` connection on
        // PROD points at V1's read replica and would 1290 a write here.
        DB::connection('mysql_system')->statement("
            ALTER TABLE v2_pdf_jobs
            MODIFY COLUMN status
                ENUM('queued','queued_long','processing','completed','failed','cancelled')
                NOT NULL DEFAULT 'queued'
        ");

        // Step 2 — repair existing rows. We use the cancel-marker message
        // because that's the only deterministic signal we have for what
        // SHOULD have been 'cancelled'. Cap at the production timestamp
        // window we observed; older empty-status rows pre-date the cancel
        // feature and have unknown intent — leave them alone.
        DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->where('status', '')
            ->where('message', 'LIKE', 'Cancelled by user via Document Jobs%')
            ->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        // Reverse: any 'cancelled' rows go back to '' (matches the broken
        // pre-fix state), then narrow the ENUM. We only roll back the
        // schema; data rollback would re-introduce the bug so we keep the
        // repaired rows as-is and just contract the ENUM.
        DB::connection('mysql_system')->statement("
            ALTER TABLE v2_pdf_jobs
            MODIFY COLUMN status
                ENUM('queued','queued_long','processing','completed','failed')
                NOT NULL DEFAULT 'queued'
        ");
    }
};
