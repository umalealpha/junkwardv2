<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * De-duplicate users.email BEFORE the unique index is added by
 * 2026_06_15_210000_add_unique_email_to_users_table.
 *
 * Why
 * ───
 * That index migration is fail-loud — it throws when duplicate emails still
 * exist. PROD was de-duped by hand on 2026-06-15, but every other environment
 * (staging, fresh RDS clones, local dev) still carries duplicates, so `migrate`
 * aborts there at the unique-index step and EVERY later migration (help_desk_*,
 * related_ticket_id, …) never runs. That is the root cause of the recurring
 * "artisan / migrate from the deploy always fails on staging" and the missing
 * help-desk tables.
 *
 * Fix
 * ───
 * Keep the lowest id per duplicated email and NULL the email on the others. The
 * app treats a NULL email as "no email" — nothing is deleted, only the
 * duplicate address is cleared so the unique index can be created. Dated one
 * minute before the index migration so it always runs first.
 *
 * Safe everywhere: idempotent and a no-op where the data is already clean (PROD
 * already has the unique index, so this finds zero duplicates and changes
 * nothing). Never throws — it removes the blocker rather than becoming one.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        // Keep MIN(id) per email; NULL the rest. LENGTH(TRIM(...)) avoids quoting
        // an empty-string literal and also treats whitespace-only as empty.
        $affected = DB::affectingStatement(<<<'SQL'
            UPDATE users u
            JOIN (
                SELECT email, MIN(id) AS keep_id
                FROM users
                WHERE email IS NOT NULL AND LENGTH(TRIM(email)) > 0
                GROUP BY email
                HAVING COUNT(*) > 1
            ) dups ON u.email = dups.email AND u.id <> dups.keep_id
            SET u.email = NULL, u.updated_at = NOW()
        SQL);

        if ($affected > 0) {
            Log::warning("[dedup] Cleared {$affected} duplicate users.email value(s) before adding the unique index.");
        }
    }

    public function down(): void
    {
        // No-op: cleared emails cannot be reliably restored.
    }
};
