<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a JSON `settings` column to integration_settings for non-secret
 * per-integration config that operators can edit from the admin UI without a
 * redeploy — e.g. Swiftly's program_id. Secrets (api_key, webhook_secret)
 * intentionally stay in env/SSM and are NOT stored here.
 *
 * Guarded per the self-healing migration standard.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('integration_settings') && !Schema::hasColumn('integration_settings', 'settings')) {
            Schema::table('integration_settings', function (Blueprint $table) {
                $table->text('settings')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('integration_settings') && Schema::hasColumn('integration_settings', 'settings')) {
            Schema::table('integration_settings', function (Blueprint $table) {
                $table->dropColumn('settings');
            });
        }
    }
};
