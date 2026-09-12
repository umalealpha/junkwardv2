<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewer comment box on a refund request (Finance ask — Keetile 2026-08-10):
 * "kindly add a comment box similar to REASON DETAILS for the reviewer".
 *
 * The reviewer could already write free text when REJECTING or ESCALATING, but
 * had nowhere to record a note when passing a refund on as reviewed — so the
 * approver saw a reviewed request with no explanation of what was checked.
 *
 * Additive + idempotent, nullable — nothing to back-fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('refund_requests', 'review_comment')) {
                $table->text('review_comment')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            if (Schema::hasColumn('refund_requests', 'review_comment')) {
                $table->dropColumn('review_comment');
            }
        });
    }
};
