<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill help_desk_tickets.assignee_id + assignee_name for tickets that
 * were assigned BEFORE the assign() handler started resolving the email to
 * a User row.
 *
 * Symptom that motivated this: the Help Desk dashboard's "Top Assigned To"
 * column showed email local-parts ("developers", "mmali") because
 * assignee_name stored the raw email, while "Top Raised By" showed full
 * names. Going forward, HelpDeskController::assign() writes the resolved
 * full name; this one-off cleans the historical rows.
 *
 * Idempotent — running it a second time finds zero rows matching the
 * email pattern AND a User row, so it's a no-op. Per the self-healing
 * migration standard.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_tickets') || !Schema::hasTable('users')) {
            return;
        }

        // Pull only tickets where assignee_name looks like an email (still
        // has an @ in it) — tickets that already got the full-name
        // treatment have no @ and are skipped automatically.
        $rows = DB::table('help_desk_tickets as t')
            ->join('users as u', 'u.email', '=', 't.assignee_name')
            ->whereNotNull('t.assignee_name')
            ->where('t.assignee_name', 'like', '%@%')
            ->select('t.id', 'u.id as user_id', 'u.firstName', 'u.lastName', 'u.email')
            ->get();

        $updated = 0;
        foreach ($rows as $row) {
            $fullName = trim(($row->firstName ?? '') . ' ' . ($row->lastName ?? ''));
            // Fall back to the original email if both name parts are blank
            // (rare but defensive — never write an empty assignee_name).
            $displayName = $fullName !== '' ? $fullName : $row->email;

            DB::table('help_desk_tickets')
                ->where('id', $row->id)
                ->update([
                    'assignee_id'   => $row->user_id,
                    'assignee_name' => $displayName,
                    'updated_at'    => now(),
                ]);
            $updated++;
        }

        Log::info('help_desk_assignee_identity_backfill', [
            'scanned' => $rows->count(),
            'updated' => $updated,
        ]);
    }

    public function down(): void
    {
        // No-op. Reversing would require re-resolving each user_id back to
        // an email, which we don't keep around once the relation is set.
        // The backfill is a one-way data-fix, not a schema change.
    }
};
