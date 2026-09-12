<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two corrections asked for by Reinsurance after testing the register
 * (10 August 2026).
 *
 *  1. The premium payment warranty is a PERIOD, not a fixed date. It is
 *     negotiated on the slip as "within 90 days", "monthly", "quarterly" — and
 *     it runs from the day the slip was signed. Capturing only a due date threw
 *     that away and made the capturer do the arithmetic in their head.
 *     `ppw_due_date` stays, and stays the single field the breach alarm reads,
 *     but it is now DERIVED from the signing date plus the window whenever both
 *     are given. Nothing downstream changes.
 *
 *  2. `gross_ceded_premium` is the reinsurer's share, and it was being worked
 *     out by hand from the full premium on a calculator. `source_premium` holds
 *     the full gross premium received on the policy so the ceded share can be
 *     computed instead of typed. It is a basis of derivation, never the payable:
 *     the payable remains the sum of `gross_ceded_premium`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fac_placements')) {
            return;
        }

        Schema::table('fac_placements', function (Blueprint $t) {
            // ── 1. The warranty as a period ──────────────────────────────
            if (!Schema::hasColumn('fac_placements', 'slip_signed_date')) {
                $t->date('slip_signed_date')->nullable()->after('fac_slip_no');
            }
            if (!Schema::hasColumn('fac_placements', 'ppw_days')) {
                $t->unsignedSmallInteger('ppw_days')->nullable()->after('ppw_due_date')
                    ->comment('Days from slip signing by which the client premium must reach us');
            }
            if (!Schema::hasColumn('fac_placements', 'ppw_terms')) {
                $t->string('ppw_terms', 60)->nullable()->after('ppw_days')
                    ->comment('The window as the slip words it, e.g. "90 days", "Monthly", "Quarterly"');
            }

            // ── 2. The basis the ceded share is worked out from ──────────
            if (!Schema::hasColumn('fac_placements', 'source_premium')) {
                $t->decimal('source_premium', 18, 2)->nullable()->after('cession_sum_insured')
                    ->comment('Full gross premium received on the policy. NOT the payable.');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fac_placements')) {
            return;
        }

        Schema::table('fac_placements', function (Blueprint $t) {
            foreach (['slip_signed_date', 'ppw_days', 'ppw_terms', 'source_premium'] as $col) {
                if (Schema::hasColumn('fac_placements', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
