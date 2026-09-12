<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the term/action/risk-address hierarchy columns that BizSure's
 * createPolicy handler needs to propagate onto the full coverage chain:
 *
 *   policy_coverages        + customer_id
 *   policy_coverage_detail  + term_id, action_id, risk_address_id
 *
 * All nullable. Existing rows stay NULL — no backfill, no column changes,
 * no index drops. Additive only. Non-BizSure callers continue to write
 * the previous column subset; these columns remain NULL for them.
 */
class AddTermActionFieldsToPolicyCoverageTables extends Migration
{
    public function up(): void
    {
        Schema::table('policy_coverages', function (Blueprint $table) {
            if (! Schema::hasColumn('policy_coverages', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable();
            }
        });

        Schema::table('policy_coverage_detail', function (Blueprint $table) {
            if (! Schema::hasColumn('policy_coverage_detail', 'term_id')) {
                $table->unsignedBigInteger('term_id')->nullable();
            }
            if (! Schema::hasColumn('policy_coverage_detail', 'action_id')) {
                $table->unsignedBigInteger('action_id')->nullable();
            }
            if (! Schema::hasColumn('policy_coverage_detail', 'risk_address_id')) {
                $table->unsignedBigInteger('risk_address_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('policy_coverages', function (Blueprint $table) {
            if (Schema::hasColumn('policy_coverages', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
        });

        Schema::table('policy_coverage_detail', function (Blueprint $table) {
            foreach (['term_id', 'action_id', 'risk_address_id'] as $col) {
                if (Schema::hasColumn('policy_coverage_detail', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
