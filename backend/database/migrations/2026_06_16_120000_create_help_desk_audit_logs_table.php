<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for Help Desk tickets — who did what, when.
 * Mirrors the EmployerGroupAuditLog pattern. One row per lifecycle action
 * (created / assigned / reassigned / unassigned / status_changed / closed,
 * and later edited / attachment changes).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotency guard (matches the migration-idempotency check; avoids a
        // "table already exists" error if this is ever re-run / force-marked).
        if (!Schema::hasTable('help_desk_audit_logs')) {
            Schema::create('help_desk_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->index();
                $table->string('event', 40);            // created | assigned | reassigned | unassigned | status_changed | closed | ...
                $table->string('description', 255);     // human-readable: "Assigned to Pramod"
                $table->string('actor')->nullable();    // who did it (display name)
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('details')->nullable();    // structured before/after where useful
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_audit_logs');
    }
};
