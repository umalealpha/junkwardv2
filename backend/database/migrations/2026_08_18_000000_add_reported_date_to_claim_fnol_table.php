<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `reported_date` to claim_fnol so Reported Date (when the loss was reported)
 * and loss_date (Date of Loss — when it happened) are captured SEPARATELY, per the
 * claims-team request (2026-08-17, tracker issue #5). Previously the single
 * "Reported Date" field was stored in loss_date, conflating the two.
 *
 * Idempotent (hasColumn guard) and additive/nullable — existing FNOLs keep their
 * loss_date (Date of Loss) untouched; reported_date is simply null for them and
 * falls back to the convert date, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('claim_fnol') && !Schema::hasColumn('claim_fnol', 'reported_date')) {
            Schema::table('claim_fnol', function (Blueprint $table) {
                $table->date('reported_date')->nullable()->after('loss_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('claim_fnol') && Schema::hasColumn('claim_fnol', 'reported_date')) {
            Schema::table('claim_fnol', function (Blueprint $table) {
                $table->dropColumn('reported_date');
            });
        }
    }
};
