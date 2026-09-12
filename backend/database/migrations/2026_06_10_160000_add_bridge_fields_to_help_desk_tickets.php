<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-compatibility with the Alpha Bridge HelpDesk (PMO/Kanban).
 *
 * These columns map 1:1 onto Bridge's `Ticket` model so that when a Graphite
 * issue is later handed off to Bridge (via email-to-ticket or a direct
 * ingestion endpoint) the data carries over losslessly:
 *   title       -> Ticket.title        (Bridge Kanban card title)
 *   priority    -> Ticket.priority      (LOW|MEDIUM|HIGH|CRITICAL)
 *   type        -> Ticket.type          (TASK|BUG|FEATURE|IMPROVEMENT)
 *   source      -> Ticket.source        (origin surface; Graphite = "web")
 *   external_ref-> Ticket.ticketKey     (the linked Bridge key, e.g. DEV-001,
 *                                         stored here once synced — back-link)
 *   bridge_synced_at                    (when this ticket was pushed to Bridge)
 *
 * Graphite stays the lightweight reporter — no Kanban here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->string('title', 150)->nullable()->after('ticket_ref');
            $table->string('priority', 20)->default('medium')->after('description')->index();
            $table->string('type', 20)->default('bug')->after('priority')->index();
            $table->string('source', 30)->default('web')->after('type');
            // Reserved for the linked Bridge ticket key (DEV-###) once synced.
            $table->string('external_ref', 60)->nullable()->after('source');
            $table->timestamp('bridge_synced_at')->nullable()->after('external_ref');
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->dropColumn(['title', 'priority', 'type', 'source', 'external_ref', 'bridge_synced_at']);
        });
    }
};
