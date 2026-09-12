<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns on fac_slips, from Reinsurance's UAT of 7 September 2026.
 *
 * 1. `slip_notes` — THE PLACEMENT TERMS THE SLIP HAD NOWHERE TO PUT.
 *
 *    Reinsurance: "We do have some areas that need to be defined as per the
 *    placement terms in the slip. These should be given under the notes which
 *    would be input by the underwriter." The five structured term fields
 *    (deductible, description of risk, territorial scope, risk ceded, basis of
 *    cover) each answer one named question; nothing carried the conditions that
 *    do not fit one. fac_placements.notes exists but is internal — it is not on
 *    the document the reinsurer signs, and was never printed.
 *
 * 2. `basis_of_cover_source` — WHICH AUTHORITY STATED THE BASIS.
 *
 *    The basis was read from the policy and ONLY from the policy: Reinsurance's
 *    rule of 24 August 2026, so a claims-made policy could not produce a
 *    claims-occurring slip. On 7 September they asked for the opposite — the
 *    underwriter states it "as per the terms they have agreed with the
 *    reinsurer", because a facultative cession can genuinely be written on a
 *    different basis from the policy underneath it.
 *
 *    Both are right about their own risk, so neither is discarded. The policy
 *    still supplies the answer where it has one, an underwriter may override it,
 *    and THIS COLUMN RECORDS WHICH HAPPENED. Without it the two cases are
 *    indistinguishable after the fact, and a slip that contradicts its policy
 *    would look exactly like one that agrees with it.
 *
 *    Deriving it by comparing the stored value against the policy would be
 *    wrong: the policy can change afterwards, and a value that once matched
 *    would then read as an override nobody made.
 *
 * Both nullable and additive. fac_slips holds no rows yet, and a null source on
 * an older row means "not recorded", which the reader treats as the policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fac_slips')) {
            return;
        }

        Schema::table('fac_slips', function (Blueprint $t) {
            if (!Schema::hasColumn('fac_slips', 'slip_notes')) {
                $t->text('slip_notes')->nullable()->after('deductible_text');
            }

            if (!Schema::hasColumn('fac_slips', 'basis_of_cover_source')) {
                $t->string('basis_of_cover_source', 20)
                    ->nullable()
                    ->after('basis_of_cover')
                    ->comment('policy | underwriter — which authority stated the basis');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fac_slips')) {
            return;
        }

        Schema::table('fac_slips', function (Blueprint $t) {
            foreach (['slip_notes', 'basis_of_cover_source'] as $col) {
                if (Schema::hasColumn('fac_slips', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
