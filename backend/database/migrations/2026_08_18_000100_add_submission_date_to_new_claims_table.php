<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `submission_date` to new_claims — the date a claim was formally submitted
 * for management's review, per the claims-team request (2026-08-17, tracker
 * issue #16), so there's visibility of when each claim went for review.
 *
 * Idempotent (hasColumn guard) and additive/nullable — existing claims are
 * untouched (null = not yet submitted for review).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('new_claims') && !Schema::hasColumn('new_claims', 'submission_date')) {
            Schema::table('new_claims', function (Blueprint $table) {
                $table->date('submission_date')->nullable()->after('date_first_visited');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('new_claims') && Schema::hasColumn('new_claims', 'submission_date')) {
            Schema::table('new_claims', function (Blueprint $table) {
                $table->dropColumn('submission_date');
            });
        }
    }
};
