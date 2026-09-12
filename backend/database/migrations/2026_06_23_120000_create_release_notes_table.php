<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "What's New" release notes — in-app changelog surfaced on login so users are
 * notified of every UI change/fix (supports the gradual UX rollout: evolve, but
 * always announce). Per-user seen-tracking via users.last_seen_release_note_id.
 *
 * Guarded + idempotent (self-healing migration standard); seeds one kickoff note
 * only when the table is empty so the panel has content to show on first deploy.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('release_notes')) {
            Schema::create('release_notes', function (Blueprint $t) {
                $t->id();
                $t->string('version', 40)->nullable();
                $t->string('title');
                // [{ tag: 'feature'|'fix'|'improvement', text: '...' }, ...]
                $t->json('highlights')->nullable();
                $t->boolean('is_published')->default(true);
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
                $t->index(['is_published', 'id']);
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'last_seen_release_note_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('last_seen_release_note_id')->nullable()->after('email');
            });
        }

        if (Schema::hasTable('release_notes') && DB::table('release_notes')->count() === 0) {
            DB::table('release_notes')->insert([
                'version'      => '2026.06',
                'title'        => 'A fresher look — and more on the way',
                'highlights'   => json_encode([
                    ['tag' => 'improvement', 'text' => 'Refreshed the Alpha Direct brand colours across the portal for a cleaner, more consistent look.'],
                    ['tag' => 'feature',     'text' => 'Dark mode is coming — you will be able to switch it on from the top bar soon.'],
                    ['tag' => 'improvement', 'text' => 'We are polishing tables, forms and status labels step by step. Nothing moves — it just gets clearer.'],
                    ['tag' => 'feature',     'text' => 'This "What\'s New" panel: every update is announced here when you sign in.'],
                ]),
                'is_published' => 1,
                'published_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'last_seen_release_note_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('last_seen_release_note_id');
            });
        }
        Schema::dropIfExists('release_notes');
    }
};
