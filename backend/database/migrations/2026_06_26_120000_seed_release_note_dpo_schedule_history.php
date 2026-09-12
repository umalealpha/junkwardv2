<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the "What's New" entry for the DPO Schedule Transactions history change
 * (tab now shows historical DPO schedules read-only after a payment-method
 * switch). Announce-every-change process; guarded + idempotent.
 */
return new class extends Migration
{
    private string $version = '2026.06.9';

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
            'title'        => 'See past DPO schedules even after a payment-method change',
            'highlights'   => json_encode([
                ['tag' => 'improvement', 'text' => 'The Schedule Transactions tab now appears whenever a policy has any DPO schedule history — so if a customer started on DPO and later switched (or switched to DPO), you can still see the schedule.'],
                ['tag' => 'fix',         'text' => 'When the policy is no longer on DPO, the tab is read-only and shows a clear note that the customer has moved to another method and the data is historical — so no one accidentally edits an old schedule.'],
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
