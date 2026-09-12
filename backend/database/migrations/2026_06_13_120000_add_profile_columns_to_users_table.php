<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds three profile-related columns to `users`:
 *
 *   last_login_at        — timestamp of last successful authentication
 *                          (set by AuthController::login and exchangeSsoToken)
 *   reporting_manager_id — self-FK to users(id); powers the
 *                          "reporting manager" surface on the profile page
 *   avatar_path          — S3 key for an uploaded avatar (Phase 2b);
 *                          NULL means render initials avatar
 *
 * Idempotent — each column wrapped in a hasColumn guard so a re-run on a
 * partially-applied DB is a no-op (per the self-healing migration standard).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            }
            if (!Schema::hasColumn('users', 'reporting_manager_id')) {
                $table->unsignedBigInteger('reporting_manager_id')->nullable()->after('company_id');
                $table->index('reporting_manager_id');
            }
            if (!Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 500)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
            if (Schema::hasColumn('users', 'reporting_manager_id')) {
                $table->dropIndex(['reporting_manager_id']);
                $table->dropColumn('reporting_manager_id');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
        });
    }
};
