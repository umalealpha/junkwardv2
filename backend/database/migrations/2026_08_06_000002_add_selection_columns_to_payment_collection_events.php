<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record WHICH outstanding premiums a Collect Now attempt covered.
 *
 * Collect Now used to debit exactly one premium, so the event's `amount` said
 * everything. Now an operator can tick several outstanding installments and
 * take them in one debit, and the event has to answer "which ones?" — both for
 * the confirmation trail (who authorised a P1 050 deduction over 3 premiums)
 * and for the duplicate-collection guard.
 *
 * Additive and nullable: rows written by the old single-premium path stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_collection_events')) {
            return;
        }

        Schema::table('payment_collection_events', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_collection_events', 'premium_count')) {
                // How many outstanding premiums the debit covered (1 = legacy behaviour).
                $table->unsignedInteger('premium_count')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('payment_collection_events', 'schedule_ids')) {
                // JSON array of scheduled_transactions.id that were selected.
                $table->text('schedule_ids')->nullable()->after('premium_count');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_collection_events')) {
            return;
        }

        Schema::table('payment_collection_events', function (Blueprint $table) {
            foreach (['premium_count', 'schedule_ids'] as $column) {
                if (Schema::hasColumn('payment_collection_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
