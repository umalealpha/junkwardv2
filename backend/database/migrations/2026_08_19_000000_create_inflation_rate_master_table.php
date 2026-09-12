<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inflation rate master — UW-owned table of sum-insured uplift percentages.
 *
 * WHY
 *   The first inflationary run (Paul Beka, Sep-2026) carried its scope in the
 *   command line: --pct=10 --product=8 --section=Houseowner-Buildings
 *   --descriptions="Sum Insured". Every future variation (a different % for
 *   Commercial, 0% above a sum insured ceiling, one section uplifted more than
 *   another) meant another operator instruction, or another code change. This
 *   table moves that decision out of the command and into a row UW can edit.
 *
 * ONE ROW = ONE RULE. A rule says: "for this product / this section / this
 * sub-coverage line / this sum-insured band / these effective dates, uplift by
 * this percent". Every matcher column is NULLABLE and null means "any", so the
 * table scales from a single company-wide row to a per-line grid without a
 * schema change.
 *
 * MATCHING (see AlphaDirect\Services\Inflation\InflationRateResolver)
 *   A candidate line is scored against every active rule and the MOST SPECIFIC
 *   match wins — sub-coverage beats section beats product; an id beats a name;
 *   a sum-insured band beats no band. `priority` overrides the score when UW
 *   needs an explicit override, and pct = 0 is a legitimate rule meaning
 *   "hold this line at its current sum insured".
 *
 * IDS vs NAMES
 *   coverage_id / sub_coverage_id are tb_cvgpccoverages.id and are the exact
 *   way to name a line. coverage_name / sub_coverage_name match on
 *   s_ScreenName instead (the Description a user reads in the wizard) and are
 *   the fallback when the same section name is carried by more than one master
 *   row, or when UW writes the rule before someone looks up the id. Set the id
 *   when you have it; the name is only consulted when the id is null.
 *
 * NOT A RATING TABLE. This governs the SUM INSURED only. The premium is still
 * sum insured × rate / 100, exactly as the wizard computes it.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('inflation_rate_master')) {
            Schema::create('inflation_rate_master', function (Blueprint $t) {
                $t->increments('id');
                $t->string('name', 200)->nullable()
                    ->comment('Free-text label for the rule, e.g. "2026 Domestic buildings uplift"');

                // ── Matchers. NULL = any. ──────────────────────────────────
                $t->unsignedInteger('product_id')->nullable()
                    ->comment('products.id. NULL = every product');
                $t->unsignedInteger('coverage_id')->nullable()
                    ->comment('tb_cvgpccoverages.id of the SECTION (parent). NULL = every section');
                $t->string('coverage_name', 200)->nullable()
                    ->comment('s_ScreenName of the section. Only used when coverage_id is NULL');
                $t->unsignedInteger('sub_coverage_id')->nullable()
                    ->comment('tb_cvgpccoverages.id of the line (the wizard Description). NULL = every line in the section');
                $t->string('sub_coverage_name', 200)->nullable()
                    ->comment('s_ScreenName of the line. Only used when sub_coverage_id is NULL');

                // Band on the line's CURRENT sum insured, so UW can taper the
                // uplift by size (full 10% under 2m, 5% above, 0% over 10m).
                // Inclusive lower bound, inclusive upper bound.
                $t->decimal('si_from', 20, 2)->nullable()
                    ->comment('Applies when current sum insured >= this. NULL = no lower bound');
                $t->decimal('si_to', 20, 2)->nullable()
                    ->comment('Applies when current sum insured <= this. NULL = no upper bound');

                $t->string('transaction_type', 40)->nullable()->default('ANNIVERSARY-RENEW')
                    ->comment('Action transaction_type the rule applies to. NULL = any');
                $t->date('effective_from')->nullable()
                    ->comment('Matched against the action effective_from. NULL = no lower bound');
                $t->date('effective_to')->nullable()
                    ->comment('Matched against the action effective_from. NULL = open-ended');

                // ── The decision ──────────────────────────────────────────
                $t->decimal('pct', 8, 4)->default(0)
                    ->comment('Uplift percent applied to the sum insured. 0 = hold. Negative = reduce');

                $t->integer('priority')->default(0)
                    ->comment('Manual tie-break. Higher wins regardless of specificity');
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();

                $t->index(['is_active', 'product_id', 'coverage_id'], 'infl_rate_active_scope_idx');
                $t->index(['effective_from', 'effective_to'], 'infl_rate_window_idx');
            });
        }

        // Seed the rule that reproduces the command's own hard-coded default,
        // so switching the cron to --master changes nothing about the Sep-2026
        // run. Only on a first, empty create — never overwrite UW's edits.
        if (Schema::hasTable('inflation_rate_master')
            && DB::table('inflation_rate_master')->count() === 0) {
            DB::table('inflation_rate_master')->insert([
                'name'              => '2026 Domestic Houseowner-Buildings uplift (Beka)',
                'product_id'        => 8,
                'coverage_name'     => 'Houseowner-Buildings',
                'sub_coverage_name' => 'Sum Insured',
                'transaction_type'  => 'ANNIVERSARY-RENEW',
                'effective_from'    => '2026-09-01',
                'pct'               => 10,
                'is_active'         => 1,
                'notes'             => 'Seeded from the original policy:inflate-buildings-si defaults (pct 10, product 8, section Houseowner-Buildings, Description "Sum Insured", from 2026-09-01).',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inflation_rate_master');
    }
};
