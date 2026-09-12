<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Help Desk tickets now start their lifecycle in the "new" state instead of
 * "open". The application layer already sets the status explicitly on create
 * (see HelpDeskController::store / clone), but the column default is moved to
 * 'new' too so any direct insert stays consistent with the new workflow:
 *
 *     New → In Progress → Resolved → Closed   (Reopened when applicable)
 *
 * Existing rows are intentionally left untouched: tickets created before this
 * change keep whatever status they already hold (including legacy 'open'), so
 * history and live dashboards are not rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE help_desk_tickets MODIFY status VARCHAR(20) NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE help_desk_tickets MODIFY status VARCHAR(20) NOT NULL DEFAULT 'open'");
    }
};
