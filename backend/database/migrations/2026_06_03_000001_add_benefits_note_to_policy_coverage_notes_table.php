<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-introduces the V1 `benefits_note` column on policy_coverage_notes.
 *
 * V1 (graphiteBWV8) stores the WC / Stated Benefits "Benefits for the
 * circumstances" text here (PolicyCoverageNote::updateOrCreate([...],
 * ['benefits_note' => ...]) and reads $coverage->note->benefits_note).
 * The column is absent on the V2 DB (the earlier migration was reverted),
 * so the text had nowhere to persist and rendered blank on the V2 Quote/Doc.
 *
 * Additive + nullable + idempotent (hasColumn guard) — safe to run on a
 * shared DB with no backfill. Existing policies stay blank until re-saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policy_coverage_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverage_notes', 'benefits_note')) {
                $table->text('benefits_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('policy_coverage_notes', function (Blueprint $table) {
            if (Schema::hasColumn('policy_coverage_notes', 'benefits_note')) {
                $table->dropColumn('benefits_note');
            }
        });
    }
};
