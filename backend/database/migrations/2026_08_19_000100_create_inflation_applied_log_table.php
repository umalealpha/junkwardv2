<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inflation applied log — one row per sum-insured line actually uplifted.
 *
 * WHY A TABLE AND NOT JUST THE NOTE MARKER
 *   The single-percent run could guard itself with a marker on
 *   policy_actions.note ("INFL-SI:10% applied"): one action, one percent, one
 *   decision. Once the percent comes from the master it varies LINE BY LINE, so
 *   the guard has to be line by line too. Without it, adding a second rule in
 *   October (say Household Contents) and re-running would either skip the whole
 *   action (marker present) or re-uplift the buildings line it already touched.
 *
 * The guard is therefore "has THIS rule already been applied to THIS row of
 * THIS action" — which this table answers, and which lets a later rule land on
 * an action an earlier rule has already been through.
 *
 * It is also the audit answer to "which policies moved, by how much, under
 * whose rule" — append-only, never updated. A --force re-run appends a second
 * row rather than editing the first, so a compounded uplift stays visible.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('inflation_applied_log')) {
            Schema::create('inflation_applied_log', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('rule_id')->comment('inflation_rate_master.id that decided the percent');
                $t->unsignedInteger('policy_id')->nullable();
                $t->unsignedInteger('action_id')->comment('policy_actions.id that was re-priced');
                $t->string('source_table', 60)->default('policy_coverage_detail')
                    ->comment('Table the uplifted row lives in — room for extensions / specified items later');
                $t->unsignedInteger('row_id')->comment('Primary key of the uplifted row in source_table');
                $t->unsignedInteger('coverage_id')->nullable()
                    ->comment('tb_cvgpccoverages.id of the line, as captured on the row');

                $t->decimal('pct', 8, 4);
                $t->decimal('si_before', 20, 2)->default(0);
                $t->decimal('si_after', 20, 2)->default(0);
                $t->decimal('premium_before', 20, 2)->default(0);
                $t->decimal('premium_after', 20, 2)->default(0);

                $t->string('run_marker', 100)->nullable()
                    ->comment('Marker written on policy_actions.note for the same run');
                $t->timestamps();

                $t->index(['action_id', 'source_table', 'row_id', 'rule_id'], 'infl_log_guard_idx');
                $t->index(['policy_id'], 'infl_log_policy_idx');
                $t->index(['rule_id'], 'infl_log_rule_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inflation_applied_log');
    }
};
