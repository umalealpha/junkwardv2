<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The quarterly statement of account — RI-18 step 1.
 *
 * Four tables. Accounts are rendered quarterly within 45 days of the quarter
 * close and confirmed within 14 (BR-ACC-01, BR-ACC-02), broken down by share and
 * by class (BR-ACC-03), carrying the five accounting items of Article 10.2
 * (BR-ACC-04 to BR-ACC-08) plus brokerage, VAT, the reserve deposit and its
 * interest.
 *
 * MONEY IS decimal(20,2), as in policy_reinsurance_regulatory and for the same
 * reason: these figures reach a solvency return, and the claims tables' own
 * decimal(10,2) already caps a single claim at 99,999,999.99 against an event
 * limit of 70,000,000. This side will not add a second ceiling.
 *
 * AMOUNTS ARE SIGNED, so the balance is a sum rather than a rule. Premium is
 * positive to reinsurers; commission, brokerage and claims are negative. A
 * statement that does not foot is not a statement, and the cheapest way to keep
 * that true is to make footing an addition.
 *
 * THE SHARES TABLE STAYS EMPTY UNTIL THE PANEL IS COMPLETE. BR-SEC-08 requires
 * every ceded amount allocated per reinsurer and reconciling to the total;
 * General is 34.00 points short of the cession and Motor 33.10. An empty table
 * says "not yet" honestly. Writing provisional splits that do not sum to the
 * cession would say something false.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('treaty_statements')) {
            Schema::create('treaty_statements', function (Blueprint $t) {
                $t->id();

                // 'general', 'motor'. Surplus accounts through General.
                $t->string('treaty', 32)->index();

                // The underwriting year this quarter belongs to, as its start:
                // 2026 means 1 Jul 2026 to 30 Jun 2027.
                $t->unsignedSmallInteger('underwriting_year')->index();
                $t->unsignedTinyInteger('quarter');           // 1..4 within the treaty year

                $t->date('period_start');
                $t->date('period_end');

                // Derived on creation and stored, not computed on read: the
                // clock that matters is the one that was set when the quarter
                // closed, not one recalculated later from a changed rule.
                $t->date('render_due');      // period_end + 45 days
                $t->date('confirm_due')->nullable();   // rendered_at + 14 days

                $t->enum('status', [
                    'draft', 'rendered', 'confirmed', 'disputed', 'settled',
                ])->default('draft')->index();

                $t->timestamp('rendered_at')->nullable();
                $t->string('rendered_by', 191)->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->string('confirmed_by', 191)->nullable();

                // Which cession basis produced the premium figures. A statement
                // must say which engine it was built on, because the two do not
                // agree and a reader a year from now cannot tell by looking.
                $t->string('cession_basis', 16)->default('legacy');

                $t->text('note')->nullable();

                $t->string('added_by', 191)->nullable();
                $t->timestamps();
                $t->softDeletes();

                // One statement per treaty per quarter. A second would be a
                // restatement and needs to say so rather than sit alongside.
                $t->unique(['treaty', 'underwriting_year', 'quarter'], 'treaty_statement_period_unique');
            });
        }

        if (! Schema::hasTable('treaty_statement_items')) {
            Schema::create('treaty_statement_items', function (Blueprint $t) {
                $t->id();
                $t->foreignId('treaty_statement_id')->index();

                /*
                 * The Article 10.2 items, then the deductions the slips add.
                 *
                 *   premium              BR-ACC-04, less returns and cancellations
                 *   commission           BR-ACC-05
                 *   claims_paid          BR-ACC-06, already net of salvages and
                 *                        recoveries — those are carried
                 *                        separately as well, so the netting can
                 *                        be shown rather than asserted
                 *   salvages
                 *   recoveries
                 *   outstanding_losses   BR-ACC-07
                 *   cash_loss_recovery   BR-ACC-08
                 *   brokerage            2.50%
                 *   vat                  BR-ACC-17
                 *   reserve_deposit      retained this quarter
                 *   reserve_release
                 *   reserve_interest
                 *   delay_interest       110% of prime, when overdue
                 */
                $t->string('item_type', 32)->index();

                // BR-ACC-03: broken down by class. The regulatory class, which
                // is the grain the treaty cedes at.
                $t->string('regulatory_class', 64)->nullable()->index();

                /*
                 * BR-ACC-07 SPLITS ON A DIFFERENT AXIS PER TREATY, and this is
                 * the column where that bites: General breaks outstanding losses
                 * down by YEAR OF OCCURRENCE, Motor by UNDERWRITING YEAR. The
                 * same claim sits in different buckets on each. One nullable
                 * column with the axis named beside it, rather than two columns
                 * of which one is always empty.
                 */
                $t->unsignedSmallInteger('period_year')->nullable();
                $t->string('period_axis', 24)->nullable();   // occurrence | underwriting

                // Signed. Positive is due TO reinsurers, negative is due FROM.
                $t->decimal('amount', 20, 2)->default(0);

                // What it was computed from, so a figure can be traced without
                // re-running the quarter.
                $t->decimal('basis_amount', 20, 2)->nullable();
                $t->decimal('rate', 12, 8)->nullable();

                $t->text('note')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['treaty_statement_id', 'item_type'], 'tsi_statement_item_idx');
            });
        }

        if (! Schema::hasTable('treaty_statement_shares')) {
            Schema::create('treaty_statement_shares', function (Blueprint $t) {
                $t->id();
                $t->foreignId('treaty_statement_item_id')->index();

                $t->string('reinsurer', 191)->index();

                // Stored on BOTH bases because the slips express them either
                // way and the conversion is where mistakes live: GIC Re's own
                // note reads "17% of cession or 11.90% of 100%".
                $t->decimal('share_of_cession_pct', 9, 6)->nullable();
                $t->decimal('share_of_hundred_pct', 9, 6)->nullable();

                $t->decimal('amount', 20, 2)->default(0);

                $t->timestamps();
                $t->softDeletes();

                $t->unique(['treaty_statement_item_id', 'reinsurer'], 'tss_item_reinsurer_unique');
            });
        }

        if (! Schema::hasTable('treaty_reserve_deposits')) {
            Schema::create('treaty_reserve_deposits', function (Blueprint $t) {
                $t->id();
                $t->foreignId('treaty_statement_id')->index();

                $t->string('reinsurer', 191)->index();

                /*
                 * BR-ACC-14: retained ONLY for reinsurers not domiciled in
                 * Botswana. BR-ACC-13: and not at all where a letter of credit
                 * or irrevocable guarantee is on file. Both are recorded rather
                 * than inferred, so a nil deposit says which of the two reasons
                 * it is nil for.
                 */
                $t->boolean('is_domestic')->default(false);
                $t->boolean('has_letter_of_credit')->default(false);

                $t->decimal('premium_base', 20, 2)->default(0);
                $t->decimal('retained_pct', 9, 6)->nullable();   // General foreign: 40.000000
                $t->decimal('retained', 20, 2)->default(0);
                $t->decimal('released', 20, 2)->default(0);

                // BR-ACC-12: 2.00% BELOW the average call rate for the year, so
                // the call rate is stored beside the margin rather than only the
                // net figure — the margin is a treaty term and the call rate is
                // a market rate, and next year only one of them moves.
                $t->decimal('call_rate_pct', 9, 6)->nullable();
                $t->decimal('interest_margin_pct', 9, 6)->nullable();
                $t->decimal('interest_accrued', 20, 2)->default(0);

                $t->decimal('balance_carried', 20, 2)->default(0);

                $t->text('note')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->unique(['treaty_statement_id', 'reinsurer'], 'trd_statement_reinsurer_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('treaty_reserve_deposits');
        Schema::dropIfExists('treaty_statement_shares');
        Schema::dropIfExists('treaty_statement_items');
        Schema::dropIfExists('treaty_statements');
    }
};
