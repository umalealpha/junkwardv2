<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Premium confirmation, moved out of a spreadsheet and out of Odoo.
 *
 * Today: a claims handler asks Finance on a manual Excel sheet; the junior
 * debtors accountant signs it and attaches it to a task in Odoo — a system we
 * otherwise no longer use and still pay for. Of the twenty fields on that sheet
 * only two are actually Finance's (the status comment and the credit
 * signature); the rest is re-typing what Graphite already holds.
 *
 * Here: one row is raised automatically the moment a claim is registered,
 * pre-filled from the ledger. Paid-up releases itself with nobody involved.
 * Arrears always goes to a person and NEVER declines by itself.
 *
 * Additive and idempotent (pr-guard). Dark behind `premium_confirmation`.
 */
class CreateClaimPremiumConfirmations extends Migration
{
    public function up(): void
    {
        // Wrapping `if (!Schema::hasTable(...))` rather than an early return —
        // functionally the same, but it is the house pattern the pr-guard checks
        // for, and matching it keeps the guard useful instead of teaching people
        // to bypass it.
        if (!Schema::hasTable('claim_premium_confirmations')) {
            Schema::create('claim_premium_confirmations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('policy_id')->nullable()->index();

                // auto_released | pending_finance | confirmed | queried
                // NOTE there is deliberately no 'declined'. Finance confirms or
                // queries; declining a claim is a claims decision, never this
                // record's, and never the system's.
                $table->string('status', 30)->default('pending_finance')->index();

                // green | amber | red — what the engine found at the time it ran.
                $table->string('light', 10)->nullable()->index();

                // Finance's own wording: 'Monthly premium current', 'Annual premium
                // fully paid', 'Annual premium current', or 'Premium outstanding'.
                $table->string('premium_status', 60)->nullable();

                $table->decimal('balance', 15, 2)->nullable();
                $table->decimal('premium', 15, 2)->nullable();
                $table->unsignedInteger('premiums_outstanding')->default(0);
                $table->date('unpaid_from')->nullable();
                $table->date('last_payment_date')->nullable();

                // Three consecutive unpaid premiums make a claim a candidate for
                // repudiation (Finance's rule). This HOLDS settlement for a
                // management decision — it does not decline anything, and the No
                // Claim Declaration path stays a human conversation.
                $table->boolean('settlement_hold')->default(false)->index();

                // The whole assessment as it stood when raised, so a later ledger
                // change never silently rewrites what Finance signed.
                $table->json('assessment')->nullable();

                // The 24-hour clock. Calendar hours, by CFO decision 11-Aug-2026 —
                // weekend and holiday breaches are expected and are labelled rather
                // than excluded.
                $table->dateTime('raised_at')->nullable()->index();
                $table->dateTime('due_at')->nullable()->index();
                $table->dateTime('released_at')->nullable()->index();

                $table->string('released_by', 120)->nullable();
                $table->text('finance_comment')->nullable();

                // Genuine arrears means chasing the agent (direct) or the
                // underwriter (broker). The draft is written for them; a person
                // sends it.
                $table->dateTime('collection_drafted_at')->nullable();
                $table->dateTime('collection_sent_at')->nullable();

                $table->timestamps();

                // The tracker's main read: what is open, oldest first.
                $table->index(['status', 'due_at'], 'cpc_status_due_idx');
                // One live confirmation per claim; a re-raise supersedes.
                $table->unique(['claim_id', 'raised_at'], 'cpc_claim_raised_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_premium_confirmations');
    }
}
