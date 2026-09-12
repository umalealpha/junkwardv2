<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the "What's New" entry for the FormField + button brand-colour
 * tokenization (navy focus rings / on-brand buttons, replacing off-brand
 * blue/teal). Announce-every-change process; guarded + idempotent.
 */
return new class extends Migration
{
    private string $version = '2026.06.5';

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
            'title'        => 'On-brand colours on forms and buttons',
            'highlights'   => json_encode([
                ['tag' => 'improvement', 'text' => 'Form fields and buttons on the policy-creation and renewal screens now use Alpha Direct navy instead of the old blue/teal, for a more consistent look.'],
                ['tag' => 'improvement', 'text' => 'Selected form fields show a clearer navy highlight, making it easier to see which field you are in.'],
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
