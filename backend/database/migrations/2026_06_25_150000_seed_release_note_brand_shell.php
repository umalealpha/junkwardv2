<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the "What's New" entry for the brand shell restyle (navy sidebar +
 * AlphaDirect logo + Montserrat font), bringing the portal in line with the
 * Reporting portal look. Announce-every-change process; guarded + idempotent.
 */
return new class extends Migration
{
    private string $version = '2026.06.8';

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
            'title'        => 'A refreshed Alpha Direct look',
            'highlights'   => json_encode([
                ['tag' => 'improvement', 'text' => 'The side menu now uses the Alpha Direct navy with the company logo, and the current page is highlighted in orange — matching the Reporting portal. Nothing moved; the menu items are exactly where they were.'],
                ['tag' => 'improvement', 'text' => 'Switched to the Montserrat brand font for a cleaner, more consistent feel across the portal.'],
                ['tag' => 'feature',     'text' => 'Quick-launch tabs now sit on the right edge — the AI Assistant and Help Desk — each expands when you hover, and you can drag the rail up or down to park it wherever suits you (it remembers). The AI Assistant moved here from the top bar to keep the header clean.'],
                ['tag' => 'improvement', 'text' => 'The top bar now shows the page title and a breadcrumb so you always know where you are, and pages now ease in smoothly as you move between them.'],
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
