<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce unique user emails at the DB level.
 *
 * `users.email` had no unique constraint, so duplicate accounts for the same
 * person could be created (the legacy admin path skipped validation, and even
 * the validated paths have a TOCTOU race with no DB backstop). PROD duplicates
 * were resolved manually first (the stale account suspended + its email NULLed);
 * this adds the permanent guarantee for every path + environment.
 *
 * Self-healing + idempotent, per the migration standard:
 *   1. Empty-string emails -> NULL ('' is not a real email and would collide).
 *   2. If real duplicate non-null emails still remain (e.g. a not-yet-deduped
 *      environment), FAIL LOUD with the offending list rather than silently
 *      guess which row to mutate.
 *   3. Add the unique index, guarded by SHOW INDEX so a re-run is a no-op
 *      (covers the case where the index was already added by hand on PROD).
 *
 * NULL emails may repeat — MariaDB allows multiple NULLs under a unique index,
 * so users with no email are unaffected.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'email')) {
            return;
        }

        // 1) Normalize empty strings to NULL (idempotent).
        DB::table('users')->where('email', '')->update(['email' => null]);

        // 2) Refuse to run if real duplicates remain — do not guess which to drop.
        $dupes = DB::table('users')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email')
            ->all();

        if (!empty($dupes)) {
            throw new \RuntimeException(
                'Cannot add users.email unique index — these emails are still '
                . 'duplicated and must be de-duplicated first: '
                . implode(', ', array_map(static fn ($e) => (string) $e, $dupes))
            );
        }

        // 3) Add the unique index, guarded against re-runs / a manual hand-add.
        $exists = collect(DB::select(
            "SHOW INDEX FROM `users` WHERE Key_name = 'users_email_unique'"
        ))->isNotEmpty();

        if (!$exists) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email', 'users_email_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $exists = collect(DB::select(
            "SHOW INDEX FROM `users` WHERE Key_name = 'users_email_unique'"
        ))->isNotEmpty();

        if ($exists) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        }
    }
};
