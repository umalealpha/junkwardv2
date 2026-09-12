<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only SLA audit trail — every SLA lifecycle event so nothing changes
 * silently. Mirrors the help_desk_audit_logs pattern but is SLA-specific and
 * kept separate from the ticket activity trail.
 *
 * event_type ∈ sla_started | response_due_set | resolution_due_set | paused |
 *              resumed | warning_sent | breached | escalated | responded |
 *              resolved
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_sla_events')) {
            Schema::create('help_desk_sla_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->index();
                $table->string('event_type', 40)->index();
                $table->string('old_value', 100)->nullable();
                $table->string('new_value', 100)->nullable();
                $table->timestamp('event_timestamp')->index();
                $table->unsignedBigInteger('actor_id')->nullable(); // null => system/scheduler
                $table->json('details_json')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_sla_events');
    }
};
