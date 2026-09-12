<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data migration: move every existing Help Desk ticket still in the
 * legacy 'open' state to the new entry state 'new', aligning historical tickets
 * with the current workflow (New → In Progress → Resolved → Closed; Reopened
 * when applicable). Only 'open' rows are touched — in_progress / resolved /
 * closed / reopened tickets are left exactly as they are.
 *
 * down() is intentionally a no-op: once converted, a 'new' row that was migrated
 * is indistinguishable from a ticket genuinely created as 'new' afterwards, so
 * blindly reverting 'new' → 'open' would corrupt fresh tickets. The change is
 * therefore treated as irreversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('help_desk_tickets')
            ->where('status', 'open')
            ->update(['status' => 'new']);
    }

    public function down(): void
    {
        // No-op — see class docblock. Cannot safely distinguish migrated rows
        // from tickets later created directly as 'new'.
    }
};
