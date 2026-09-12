<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live SLA state — one row per ticket. Holds the computed due dates, the
 * first-response / resolution stamps, breach flags, pause accounting and the
 * last warning level fired. Due-date columns are indexed for fast
 * "nearing breach" range scans across 10k+ tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_slas')) {
            Schema::create('help_desk_slas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->unique();        // 1:1 with help_desk_tickets
                $table->string('priority', 20)->index();                  // snapshot at SLA start
                $table->unsignedBigInteger('policy_id')->nullable()->index();

                $table->unsignedInteger('response_target_minutes');       // business minutes
                $table->unsignedInteger('resolution_target_minutes');     // business minutes

                $table->timestamp('started_at')->nullable();
                $table->timestamp('response_due_at')->nullable()->index();
                $table->timestamp('resolution_due_at')->nullable()->index();
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();

                $table->boolean('response_breached')->default(false)->index();
                $table->boolean('resolution_breached')->default(false)->index();
                $table->unsignedTinyInteger('response_warn_level')->default(0);   // 0 | 75 | 90
                $table->unsignedTinyInteger('resolution_warn_level')->default(0); // 0 | 75 | 90

                $table->unsignedInteger('total_paused_minutes')->default(0);      // business minutes
                $table->timestamp('current_pause_started_at')->nullable();        // non-null => paused now

                $table->unsignedTinyInteger('escalation_level')->default(0);      // future-ready

                $table->string('external_ref', 60)->nullable();                   // Bridge-ready
                $table->timestamp('bridge_synced_at')->nullable();                // Bridge-ready

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_slas');
    }
};
