<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the release note for the StatusBadge + Toast UI PR. Every user-facing
 * UI change ships a "What's New" entry so users are notified on next login
 * (the announce-every-change process — see feat/whats-new-release-notes).
 *
 * Guarded: only inserts when the release_notes table exists and this version
 * is not already present, so it is safe to re-run.
 */
return new class extends Migration
{
    private string $version = '2026.06.2';

    public function up(): void
    {
        if (!Schema::hasTable('release_notes')) {
            return;
        }
        if (DB::table('release_notes')->where('version', $this->version)->exists()) {
            return;
        }

        DB::table('release_notes')->insert([
            'version'      => $this->version,
            'title'        => 'Clearer status labels & on-screen notifications',
            'highlights'   => json_encode([
                ['tag' => 'improvement', 'text' => 'Status labels (Active, Pending, Expired, Cancelled, Paid…) now use higher-contrast colours that are easier to read at a glance.'],
                ['tag' => 'improvement', 'text' => 'On the policy renewal screen, confirmations and errors now appear as brief notifications in the corner instead of pop-up boxes you have to click away.'],
                ['tag' => 'feature',     'text' => 'These new notifications already support dark mode, ready for when the theme switch arrives.'],
            ]),
            'is_published' => 1,
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('release_notes')) {
            DB::table('release_notes')->where('version', $this->version)->delete();
        }
    }
};
