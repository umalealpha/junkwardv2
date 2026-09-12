<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Backfill claims.status = '' → 'Pending' (2026-07-04).
     *
     * claims.status is a MySQL ENUM (enum('Pending','Approved','Rejected',
     * 'Closed','Reopen','Open')). The Claim Tracker create path used to write
     * the value 'New', which is NOT a member of the ENUM, so MySQL (non-strict
     * mode) silently coerced it to '' on insert. Those rows render as the grey
     * "Unknown" badge in the claims list.
     *
     * The create path is now fixed to write 'Pending' (ClaimsTrackerController
     * ::store, matching ClaimsController::store). This migration repairs the
     * already-created rows so the list stops showing "Unknown" for them.
     *
     * 'Pending' is the column's own DEFAULT and an existing ENUM member, so the
     * write is safe and needs no schema change. Idempotent: re-running is a
     * no-op once no empty-status rows remain.
     */
    public function up(): void
    {
        // Only touch rows that are actually blank — leave every real status
        // (Pending/Approved/Rejected/Closed/Reopen/Open) untouched.
        $affected = DB::table('claims')
            ->where('status', '')
            ->update(['status' => 'Pending']);

        // Log::info("[backfill] claims.status '' → 'Pending': {$affected} row(s) updated.");
    }

    public function down(): void
    {
        // No-op: a data backfill can't be safely reversed. The original ''
        // values were themselves an invalid-ENUM coercion artifact (never a
        // legitimate state), and we can't tell which of today's 'Pending'
        // rows were the ones repaired here. Rolling back to '' would only
        // re-introduce the "Unknown" bug, so we intentionally do nothing.
    }
};
