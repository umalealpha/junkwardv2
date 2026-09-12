<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the "What's New" entry for the branded confirmation dialog
 * (replacing the native browser confirm() pop-up). Announce-every-change
 * process; guarded + idempotent.
 */
return new class extends Migration
{
    private string $version = '2026.06.7';

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
            'title'        => 'A cleaner confirmation pop-up',
            'highlights'   => json_encode([
                ['tag' => 'improvement', 'text' => 'The plain browser "Are you sure?" boxes are being replaced with a clearer, on-brand confirmation pop-up. Destructive actions (like deleting an item) now show a red confirm button. Rolled out on the policy-creation screen first.'],
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
