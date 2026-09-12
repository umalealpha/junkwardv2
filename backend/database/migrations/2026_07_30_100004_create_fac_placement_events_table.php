<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC Register — step 4 of 5. The append-only trail.
 *
 * `notified_to` is not optional. Without it there is no way to prove afterwards
 * that the RI team was told a premium had landed, or told about a cancellation
 * BEFORE a payment went out. The event is always written even when the mail
 * fails — the trail must never depend on mail delivery.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fac_placement_events')) {
            Schema::create('fac_placement_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('fac_placement_id')->nullable()->index();
                $t->string('event', 60)->index();       // created | client_paid | settled | cancelled | ppw_warned | ppw_breached | slip_sent | …
                $t->string('summary', 500)->nullable();
                $t->json('payload')->nullable();

                $t->text('notified_to')->nullable();     // comma-separated recipients, always recorded
                $t->boolean('notification_sent')->default(false);
                $t->text('notification_error')->nullable();

                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('actor_name', 160)->nullable();
                $t->timestamps();

                $t->index(['fac_placement_id', 'event'], 'fac_event_placement_event_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fac_placement_events');
    }
};
