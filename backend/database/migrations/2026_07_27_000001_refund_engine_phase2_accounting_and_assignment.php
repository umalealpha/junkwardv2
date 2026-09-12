<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Refund Engine — Phase 2 (CFO decisions, 2026-07-26 email):
 *
 * 1. refund_accounting_entries — the Finance REVIEW-AND-POST queue. The engine
 *    PREPARES the accounting entry when a return-premium refund is paid; it
 *    never auto-posts. Finance reviews (may adjust the earned/unearned split
 *    and dates) and posts, which raises the Credit Note + policy_ledger
 *    'Credit Note' debit + sub_ledger reversal — so written premium drops and
 *    the numbers tie. Plain refunds (overpayment/double debit/goodwill) never
 *    enqueue — the is_refund netting is their whole accounting story.
 *
 * 2. refund_requests.assigned_to — reviewer routing. Approvers/owners can
 *    reassign a submitted request to anyone in the area (CFO: "when Motlatsi
 *    is out, Bharath can reassign to anyone in Unicoin"; D&C owners may
 *    assign to anyone in Finance).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('refund_accounting_entries')) {
            Schema::create('refund_accounting_entries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('refund_request_id')->unique()
                      ->comment('One accounting entry per refund request (idempotent enqueue)');
                $table->string('graphite_ref', 64)->index();
                $table->unsignedBigInteger('policy_id')->nullable();
                $table->string('policy_number', 80);
                $table->unsignedInteger('customer_id')->nullable();
                $table->string('area', 12);
                $table->string('reason_code', 40)->nullable();
                $table->string('entry_type', 24)->default('credit_note');
                $table->decimal('refund_amount', 13, 2)
                      ->comment('The paid refund this entry accounts for');
                // Finance-reviewable proposal (defaults prepared by the engine;
                // editable at post time, bounded by the refund amount).
                $table->decimal('earned_premium', 13, 2)->nullable();
                $table->decimal('unearned_premium', 13, 2)->nullable();
                $table->date('effective_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status', 16)->default('pending_review')
                      ->comment('pending_review | posted | dismissed');
                $table->string('credit_note_no', 20)->nullable()
                      ->comment('Set when posted — the CR###### raised');
                $table->unsignedBigInteger('credit_note_id')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('dismissed_by')->nullable();
                $table->timestamp('dismissed_at')->nullable();
                $table->string('dismiss_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['status', 'area']);
            });
        }

        if (Schema::hasTable('refund_requests')) {
            Schema::table('refund_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('refund_requests', 'assigned_to')) {
                    $table->unsignedBigInteger('assigned_to')->nullable()->index()
                          ->comment('Reviewer this request is routed to (approvers may reassign)');
                }
                if (!Schema::hasColumn('refund_requests', 'assigned_by')) {
                    $table->unsignedBigInteger('assigned_by')->nullable();
                }
                if (!Schema::hasColumn('refund_requests', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_accounting_entries');
        if (Schema::hasTable('refund_requests')) {
            Schema::table('refund_requests', function (Blueprint $table) {
                foreach (['assigned_to', 'assigned_by', 'assigned_at'] as $col) {
                    if (Schema::hasColumn('refund_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
