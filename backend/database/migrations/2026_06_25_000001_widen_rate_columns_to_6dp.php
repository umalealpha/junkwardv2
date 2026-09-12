<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rates were stored as decimal(10,4) / decimal(11,2), rounding the true
 * configured rate (e.g. 0.005593% → 0.0056%) and inflating premium
 * (5,000,000 × 0.0056% = 280.00 vs × 0.005593% = 279.65). Widen the rate
 * columns to decimal(12,6) so the actual rate is stored and used in premium
 * calculations without rounding.
 *
 * Per decision (2026-06): new/edited rows only — existing rows keep their
 * already-rounded value until re-rated; no backfill here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('policy_specified_items', 'rate')) {
            Schema::table('policy_specified_items', function (Blueprint $table) {
                $table->decimal('rate', 12, 6)->default(0)->change();
            });
        }
        if (Schema::hasColumn('policy_coverage_detail', 'rate')) {
            Schema::table('policy_coverage_detail', function (Blueprint $table) {
                $table->decimal('rate', 12, 6)->default(0)->change();
            });
        }
        if (Schema::hasColumn('policy_coverage', 'rate')) {
            Schema::table('policy_coverage', function (Blueprint $table) {
                $table->decimal('rate', 12, 6)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('policy_specified_items', 'rate')) {
            Schema::table('policy_specified_items', function (Blueprint $table) {
                $table->decimal('rate', 10, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('policy_coverage_detail', 'rate')) {
            Schema::table('policy_coverage_detail', function (Blueprint $table) {
                $table->decimal('rate', 11, 2)->default(0)->change();
            });
        }
        if (Schema::hasColumn('policy_coverage', 'rate')) {
            Schema::table('policy_coverage', function (Blueprint $table) {
                $table->decimal('rate', 11, 2)->nullable()->change();
            });
        }
    }
};
