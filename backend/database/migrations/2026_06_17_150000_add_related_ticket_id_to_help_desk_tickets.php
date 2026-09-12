<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a help-desk ticket to the one it was cloned from ("Raise a related
 * ticket"). Nullable self-reference — the vast majority of tickets carry none.
 * No hard FK by design (matches the rest of this schema): the parent must never
 * block deletion/cleanup of a child, and we only read the ref for display.
 *
 * Guarded per the self-healing migration standard — a no-op where the column
 * already exists, safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_tickets')) {
            return;
        }
        if (!Schema::hasColumn('help_desk_tickets', 'related_ticket_id')) {
            Schema::table('help_desk_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('related_ticket_id')->nullable()->after('status')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('help_desk_tickets') && Schema::hasColumn('help_desk_tickets', 'related_ticket_id')) {
            Schema::table('help_desk_tickets', function (Blueprint $table) {
                $table->dropColumn('related_ticket_id');
            });
        }
    }
};
