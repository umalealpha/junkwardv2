<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discussion / collaboration comments for Help Desk tickets.
 *
 * Append-only and kept deliberately separate from help_desk_audit_logs:
 * the audit trail records system-generated lifecycle events, while this
 * table holds free-text notes users add to a ticket (investigation notes,
 * findings, questions). One row per comment — comments are never edited or
 * overwritten in v1.
 *
 * user_name is denormalised (mirrors help_desk_audit_logs.actor) so the
 * discussion reads correctly even if a user is later renamed or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotency guard — matches the help_desk_audit_logs migration so a
        // re-run / force-mark never errors with "table already exists".
        if (!Schema::hasTable('help_desk_comments')) {
            Schema::create('help_desk_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->index();   // the ticket this comment belongs to
                $table->unsignedBigInteger('user_id')->nullable();  // author (from the session)
                $table->string('user_name')->nullable();            // author display name, denormalised
                $table->text('comment');                            // free-text note
                $table->timestamps();                               // created_at drives chronological order
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_comments');
    }
};
