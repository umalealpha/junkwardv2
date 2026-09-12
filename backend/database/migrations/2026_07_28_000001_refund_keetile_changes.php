<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Refund Engine — Finance's requested changes (Keetile email
 * 2026-07-28, CFO-approved):
 *   - branch_name : Finance asked for a branch NAME field; branch_code stays
 *     optional. Carried to Omni for the FNB beneficiary.
 *   - second_approved_by / _at : the > P5,000 two-approver rule. Refunds over
 *     P5,000 need a SECOND distinct approver before the money leg arms; at or
 *     under P5,000 a single approval stands (the CFO authorises every payment
 *     in FNB regardless — that is the final gate).
 *   - approved_reason : approvers must now record a reason (parity with reject).
 *
 * Additive + idempotent. No back-fill needed — every column is nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('refund_requests', 'branch_name')) {
                $table->string('branch_name', 120)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('refund_requests', 'approved_reason')) {
                $table->text('approved_reason')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('refund_requests', 'second_approved_by')) {
                $table->unsignedBigInteger('second_approved_by')->nullable()->after('approved_reason');
            }
            if (!Schema::hasColumn('refund_requests', 'second_approved_at')) {
                $table->timestamp('second_approved_at')->nullable()->after('second_approved_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            foreach (['second_approved_at', 'second_approved_by', 'approved_reason', 'branch_name'] as $col) {
                if (Schema::hasColumn('refund_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
