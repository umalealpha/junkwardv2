<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarterly premium payments, and enough precision to hold a real cession rate.
 * (Reinsurance, 25 August 2026 — defect 5, and the resolution of the premium
 * variance on Auto FAC slip 2026-002.)
 *
 * 1. PREMIUM PAYMENT FREQUENCY. The signed slip states "(Quarterly Payments)" under
 *    the period and carries a "Quarterly Premium Due to Re-insurer(s)" figure of
 *    6,383.23 alongside the annual 25,532.91. Nothing in the register held a payment
 *    frequency, so neither could be printed.
 *
 *    This is NOT the premium payment warranty. Slip 2026-002 carries both, and they
 *    are different terms: the warranty is "90 DAY PPW" — the deadline for the
 *    premium to reach us — while the frequency is how the premium is broken up.
 *    ppw_terms already holds the former and must not be overloaded with the latter.
 *
 * 2. RISK_PCT PRECISION. The premium variance Reinsurance queried turned out not to
 *    be an arithmetic fault at all. The slip stated 17% where the actual cession is
 *    16.634507% — 50,000,000 of a 300,580,000 total — and the 17% was a rounding for
 *    presentation. Their instruction is that actual rates are entered from now on.
 *
 *    decimal(12,6) cannot hold that. 16.634507% is 0.16634507 as a fraction, which
 *    needs EIGHT decimal places; stored at six it becomes 0.166345 and the net
 *    premium comes out a thebe light — 25,532.90 against the slip's 25,532.91. One
 *    cent is small, but it is a cent the register can never reconcile away, and
 *    somebody has to investigate every one of them. Widened to decimal(14,8).
 *
 *    DEPLOYMENT NOTE, because this one is not free: unlike adding a nullable column,
 *    changing a decimal's precision is a table REBUILD in MySQL, not an instant
 *    metadata change. fac_placements should be rebuilt while nothing is writing to
 *    it. Widening is non-destructive — every existing value fits the larger type
 *    unchanged — but the operation itself is not instant.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fac_placements')) {
            return;
        }

        Schema::table('fac_placements', function (Blueprint $t) {
            if (!Schema::hasColumn('fac_placements', 'premium_frequency')) {
                /*
                 * How the ceded premium is paid: annual, quarterly, monthly.
                 *
                 * Nullable, and null means "not stated" rather than annual. A slip
                 * that says nothing about instalments is not the same as one that
                 * says the premium is paid once, and the printed slip stays silent
                 * rather than asserting a term nobody agreed.
                 *
                 * A string rather than an enum so a frequency Reinsurance meets
                 * later — semi-annual, say — does not need a migration to print.
                 * The request validator constrains it.
                 */
                $t->string('premium_frequency', 20)->nullable()->after('commission_pct');
            }
        });

        // Widened separately: a precision change is a rebuild, and keeping it in its
        // own statement makes that visible in the log rather than hidden inside a
        // batch that otherwise looks additive.
        Schema::table('fac_placements', function (Blueprint $t) {
            $t->decimal('risk_pct', 14, 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fac_placements')) {
            return;
        }

        Schema::table('fac_placements', function (Blueprint $t) {
            if (Schema::hasColumn('fac_placements', 'premium_frequency')) {
                $t->dropColumn('premium_frequency');
            }
        });

        /*
         * NARROWING BACK IS DELIBERATELY NOT DONE.
         *
         * Any rate captured to eight decimals since this ran would be silently
         * rounded on the way down, which changes the premium on a placement that may
         * already have been settled. A rollback must not quietly restate money. The
         * column keeps the wider type; nothing depends on it being narrow.
         */
    }
};
