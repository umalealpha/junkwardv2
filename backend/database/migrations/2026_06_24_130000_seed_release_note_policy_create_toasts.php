<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the "What's New" entry for the policy-creation step of the
 * alert()-to-toast migration (StepPolicyDetails — company / sub-company
 * forms). Announce-every-change process; guarded + idempotent.
 */
return new class extends Migration
{
    private string $version = '2026.06.3';

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
            'title'        => 'Smoother messages when creating a policy',
            'highlights'   => json_encode([
                ['tag' => 'improvement', 'text' => 'On the policy-creation screen, the company and sub-company forms now show confirmations and errors as brief corner notifications instead of pop-up boxes you have to click away.'],
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
