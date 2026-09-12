<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the regulatory cession is stored.
 *
 * A SEPARATE TABLE, NOT EXTRA COLUMNS ON policy_reinsurance. The two bases do
 * not have the same shape and forcing them into one table loses whichever is
 * not writing:
 *
 *   · The legacy chain writes one row per (group, formula, coverage). A coverage
 *     running four layers is four rows, each repeating totalSumInsured.
 *   · The regulatory basis produces one allocation per (risk address, mapping),
 *     split across NAMED layers — net retention, quota share, surplus, auto FAC,
 *     FAC, outside treaty. There is no formula behind any of them, so
 *     formula_id, type_id and UsedFormula have no honest value.
 *
 * Keeping them apart means the cutover is reversible. Both bases can be written
 * for the same action and compared on real stored rows rather than on a
 * recomputation, and if the regulatory basis is wrong the legacy figures are
 * still exactly where they were.
 *
 * A ROW IS ONE LAYER OF ONE RISK ADDRESS. That is the grain the treaty actually
 * cedes at, and it is the grain Reinsurance's own workbook reports at, which is
 * how COMG2026213751 was reconciled to the cent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nested rather than an early return so the repository's migration guard
        // can see it: that check looks at the SHAPE, and a `return` above the
        // create reads as unguarded even though it behaves identically.
        //
        // The guard matters beyond the check. RI-17 names this migration for
        // someone to run by hand on production, and a step that errors on a
        // second attempt is a step people work around.
        if (! Schema::hasTable('policy_reinsurance_regulatory')) {
            Schema::create('policy_reinsurance_regulatory', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('action_id')->index();
                $table->unsignedBigInteger('policy_id')->nullable()->index();
                $table->unsignedBigInteger('term_id')->nullable();
                $table->unsignedBigInteger('transaction_id')->nullable();

                // The grain: one risk address, one regulatory class, one layer.
                $table->string('risk_address', 191)->nullable()->index();
                $table->string('regulatory_class', 64)->index();
                $table->string('layer', 32)->index();

                // Where it came from. group_id is null where a unit spans several
                // groups at one address, which a layered class can do.
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->string('group_code', 191)->nullable();
                $table->unsignedBigInteger('treaty_id')->nullable()->index();

                // Money. Decimal, not the varchar policy_reinsurance uses: these
                // figures are summed for a solvency return and a string that sorts
                // '9' above '10' has no business in that.
                $table->decimal('sum_insured', 20, 2)->default(0);
                $table->decimal('premium', 20, 2)->default(0);

                // Whether the layer is treaty capacity, and whether it is placed.
                // Auto FAC and FAC are DERIVED, not evidence of cover — Reinsurance's
                // own work paper, open item 8. An unplaced facultative layer is
                // uninsured net exposure, not nil, so it is stored and flagged
                // rather than dropped.
                $table->boolean('is_treaty_capacity')->default(true);
                $table->boolean('is_placed')->nullable();

                // Why a risk routed as it did, and anything that needs a human.
                $table->string('route', 128)->nullable();
                $table->text('exception')->nullable();

                $table->string('basis_version', 32)->default('2026-27');
                $table->string('added_by', 191)->nullable();
                $table->timestamps();
                $table->softDeletes();

                // One row per layer of a risk address on an action. Re-running an
                // action replaces its rows rather than appending a second opinion.
                $table->unique(
                    ['action_id', 'risk_address', 'regulatory_class', 'layer'],
                    'pri_regulatory_grain_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_reinsurance_regulatory');
    }
};
