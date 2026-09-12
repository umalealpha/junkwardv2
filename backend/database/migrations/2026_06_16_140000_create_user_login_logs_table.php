<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only auth activity trail: login / logout / failed / sso_blocked,
 * with the real client IP + user-agent. Fills the gap where only
 * `users.last_login_at` was tracked (no IP, no history, logout/failed unlogged).
 *
 * Guarded per the self-healing migration standard. NOT owen-it Auditable —
 * it's a log table, so it doesn't feed back into the `audits` table.
 * Subject to the audit-log retention policy (see retention task).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_login_logs')) {
            Schema::create('user_login_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index(); // null when a failed attempt doesn't resolve a user
                $table->string('email')->nullable()->index();                // attempted email (key signal for failed attempts)
                $table->string('event', 20)->index();                        // login | logout | failed | sso_blocked
                $table->string('ip_address', 45)->nullable();                // real client (CF-Connecting-IP); 45 = IPv6-safe
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
    }
};
