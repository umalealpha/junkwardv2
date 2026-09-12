<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Fire & Allied Perils and Business Interruption schedule a slip has to print
 * (Reinsurance, 24 August 2026 — defect 8 against Auto FAC slip 2026-002).
 *
 * A signed slip does not just state a total. It itemises what is insured:
 *
 *     FIRE AND ALLIED PERILS
 *     Plant and machinery including generators        P 170 000 000
 *     Stock of cables and spares                     P     200 000
 *     ... nine lines in all
 *
 *     BUSINESS INTERRUPTION
 *     Indemnity period – 15 months
 *     Annual gross profit                            P  18 700 000
 *     ... five more
 *
 *     TOTAL LIMITS OF INDEMNITY                      P 300 580 000
 *
 * None of it existed anywhere in the register, so every generated slip was silent
 * on what was actually being reinsured.
 *
 * NOTHING EXISTING IS TOUCHED. This creates one table and alters none. It was
 * originally specified as this table PLUS two columns on fac_placements, for the
 * total limits of indemnity and the indemnity period. Both turned out to be
 * unnecessary:
 *
 *  · the TOTAL is the sum of the lines. Slip 2026-002 proves it to the thebe —
 *    273,800,000 fire plus 26,780,000 business interruption is exactly the
 *    300,580,000 it states. So it is derived, not stored, and cannot drift from
 *    the schedule it is meant to total.
 *  · the INDEMNITY PERIOD is printed as a line of the business interruption block
 *    with no money against it ("Indemnity period – 15 months"), so it is a row
 *    here with a null amount rather than a column of its own.
 *
 * The consequence, stated: there is nowhere to hold a stated total that DIFFERS
 * from the sum of the lines. That is deliberate. On the evidence the two are the
 * same figure, and a schedule that does not add up to its own total is an error to
 * surface rather than a number to store.
 *
 * WHY A TABLE AND NOT JSON ON THE PLACEMENT. These are money figures that must sum
 * to a stated total. A row per line makes that check a SUM(); JSON in a text column
 * makes it string parsing in PHP, and every other total in this module is checkable
 * in SQL.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('fac_placement_schedule_items')) {
            return;
        }

        Schema::create('fac_placement_schedule_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('fac_placement_id');

            /*
             * Which block of the slip the line belongs to — 'fire' or
             * 'business_interruption' today.
             *
             * Deliberately NOT an enum. A liability or engineering schedule would
             * otherwise need a migration to print, and the set of blocks is a
             * wording decision Reinsurance owns, not a schema fact. The request
             * validator constrains it instead.
             */
            $t->string('section', 30);

            // As the slip words it. Free text because the items are specific to the
            // risk: "Boiler & Treatment Plant" is not a system concept.
            $t->string('label', 160);

            /*
             * Nullable, and that is load-bearing. "Indemnity period – 15 months" is
             * a real line of the schedule that carries no money, and a zero there
             * would be added into the total as though it were a nil sum insured.
             * Null means "this line states no amount"; 0.00 would mean "nil cover".
             */
            $t->decimal('amount', 20, 2)->nullable();

            // The order the underwriter entered them, so the printed schedule reads
            // like the one they were working from rather than alphabetically or by
            // insertion id.
            $t->unsignedSmallInteger('sort_order')->default(0);

            $t->timestamps();
            // Soft deletes, as everywhere else in this module: a line removed from a
            // schedule after a slip has gone to a reinsurer still has to be
            // explainable.
            $t->softDeletes();

            // The only access pattern: every line for one placement, in print order.
            $t->index(['fac_placement_id', 'section', 'sort_order'], 'fac_sched_placement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fac_placement_schedule_items');
    }
};
