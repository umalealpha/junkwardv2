<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `claim_allocated_on` to claim_fnol so the "Allocated Date" (formerly
 * "Claims Allocated On") can be captured at intake on the new-claim form, per the
 * claims-team request (Bharath, 2026-08-26). On convert it is carried onto the
 * claim's claims.claim_allocated_on column by ClaimFnolController::convert.
 *
 * Idempotent (hasColumn guard) and additive/nullable — existing FNOLs are
 * unaffected (null allocated date, exactly as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('claim_fnol') && !Schema::hasColumn('claim_fnol', 'claim_allocated_on')) {
            Schema::table('claim_fnol', function (Blueprint $table) {
                $table->date('claim_allocated_on')->nullable()->after('reported_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('claim_fnol') && Schema::hasColumn('claim_fnol', 'claim_allocated_on')) {
            Schema::table('claim_fnol', function (Blueprint $table) {
                $table->dropColumn('claim_allocated_on');
            });
        }
    }
};
