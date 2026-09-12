<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the "What's New" entry for the Claim Detail alert()-to-toast
 * migration. Announce-every-change process; guarded + idempotent.
 */
return new class extends Migration
{
    private string $version = '2026.06.6';

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
            'title'        => 'Smoother messages on the claim screen',
            'highlights'   => json_encode([
                ['tag' => 'improvement', 'text' => 'On the claim detail screen — attachments, reserves, payments and supplier quotes — confirmations and errors now appear as brief corner notifications instead of pop-up boxes. Delete prompts that need a yes/no answer are unchanged.'],
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
