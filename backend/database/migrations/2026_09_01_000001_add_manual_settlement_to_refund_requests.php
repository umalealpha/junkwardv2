<?php

/**
 * Refund engine — record a refund that Finance already paid outside Graphite.
 *
 * Why: Finance paid refunds by hand at FNB while the Omni money leg was dark.
 * Those requests still sit in 'approved', so arming the handoff would pay the
 * same customers a second time (Keetile's list of 28, 1 Sep 2026 —
 * BWP 40,886.59, of which 18 / BWP 10,739.03 are payout-eligible today).
 *
 * Until now the only way to close them was a hand-written UPDATE on production.
 * These two columns plus the new 'settled_manual' status give Finance a button
 * instead, so this never needs a database step again.
 *
 * Deliberately NOT reusing omni_paid_at / omni_paid_ref: those mean "Omni's
 * money leg paid this", and reading a manual FNB payment out of them would
 * misreport how the money actually moved.
 *
 * Both nullable — Keetile supplied no payment dates for the original 28, and a
 * missing date must not block closing a refund that is demonstrably paid.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('refund_requests', 'manual_paid_at')) {
                // The date Finance actually paid the client, as told to us.
                $table->date('manual_paid_at')->nullable()->after('omni_paid_at');
            }
            if (!Schema::hasColumn('refund_requests', 'manual_paid_ref')) {
                // Bank / FNB reference for the manual payment, when known.
                $table->string('manual_paid_ref', 120)->nullable()->after('manual_paid_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            foreach (['manual_paid_at', 'manual_paid_ref'] as $col) {
                if (Schema::hasColumn('refund_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
