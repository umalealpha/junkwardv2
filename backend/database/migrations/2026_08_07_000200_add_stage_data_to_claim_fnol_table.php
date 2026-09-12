<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a nullable `stage_data` JSON column to claim_fnol.
 *
 * The unified tracker-style FNOL create form renders the Claims-Tracker "New
 * Claim" 7 stage accordions, but an FNOL has no claim_id yet — so the stage
 * timeline cannot live on claim_tracker_workflow (which is keyed by claim) at
 * intake. It is parked here as JSON and, on CONVERT, applied to the new claim's
 * claim_tracker_workflow row via ClaimStageTimelineService.
 *
 * Fully ADDITIVE + REVERSIBLE + idempotent (Schema::hasColumn guard); nullable,
 * no backfill. Inert until the `claims_fnol` flag is ON.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_fnol')) {
            return; // base table not present yet — its own migration creates it first
        }
        if (!Schema::hasColumn('claim_fnol', 'stage_data')) {
            Schema::table('claim_fnol', function (Blueprint $table) {
                // JSON map of tracker stage-timeline fields (keys mirror
                // ClaimStageTimelineService::EDITABLE_FIELDS). Applied on convert.
                $table->json('stage_data')->nullable()->after('comment_sub_reason');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('claim_fnol')) {
            return;
        }
        if (Schema::hasColumn('claim_fnol', 'stage_data')) {
            Schema::table('claim_fnol', function (Blueprint $table) {
                $table->dropColumn('stage_data');
            });
        }
    }
};
