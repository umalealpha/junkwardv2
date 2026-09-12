<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two registers the statement of account cannot compute without — RI-19 3.4.
 *
 * Both are CAPTURE, not calculation. Every other figure on a statement is
 * derived from the book; these two record things that happen outside it — a
 * bank issuing an instrument, and a reinsurer paying cash on demand ahead of
 * the quarterly cycle. Nothing in Graphite knows either happened unless someone
 * records it.
 *
 * WHY THEY MATTER SEPARATELY FROM THE ARITHMETIC:
 *
 *   BR-ACC-13 removes the reserve deposit ENTIRELY where a letter of credit or
 *   irrevocable guarantee is furnished. Until now that was a list of company
 *   names in config — a placeholder with no issuer, no amount and no expiry, so
 *   an instrument that lapsed would go on exempting a reinsurer from a 40%
 *   retention for as long as nobody edited the file.
 *
 *   BR-ACC-08 puts cash loss RECOVERIES on the account. A cash loss is money
 *   demanded and received ahead of the quarterly account under BR-CLM-05 and
 *   BR-CLM-06; when the quarter is then made up, that claim appears in claims
 *   paid and the cash already received has to come off, or the reinsurer is
 *   billed twice for one loss.
 *
 * MONEY IS decimal(20,2), as everywhere else in this build and for the same
 * reason: these figures reach a solvency return.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('treaty_letters_of_credit')) {
            Schema::create('treaty_letters_of_credit', function (Blueprint $t) {
                $t->id();

                // Held by NAME rather than by id, matching treaty_reserve_deposits
                // and treaty_statement_shares. The panel is not in this system
                // yet, and a foreign key to a reinsurer we have not been told
                // about would block the register being populated at all.
                $t->string('reinsurer', 191)->index();

                // 'general', 'motor', or NULL for an instrument covering both.
                $t->string('treaty', 32)->nullable()->index();
                $t->unsignedSmallInteger('underwriting_year')->nullable()->index();

                /*
                 * BR-ACC-13 names two instruments and they are not the same
                 * thing: a letter of credit issued by a bank in the form
                 * PRESCRIBED BY THE REGISTRAR of Short Term Insurance, or an
                 * irrevocable guarantee. Which one it is, and whether it is on
                 * the prescribed form, decides whether it discharges anything.
                 */
                $t->string('instrument_type', 32)->default('letter_of_credit');
                $t->boolean('on_prescribed_form')->default(false);

                $t->string('issuer', 191)->nullable();
                $t->string('reference', 191)->nullable();

                $t->decimal('amount', 20, 2)->nullable();
                $t->string('currency', 3)->default('BWP');

                /*
                 * AN INSTRUMENT EXEMPTS ONLY WHILE IT IS IN FORCE. This is the
                 * whole reason the register exists rather than a list of names:
                 * a lapsed letter of credit that still exempts a reinsurer means
                 * a 40% deposit was not retained against security that no longer
                 * exists. A null expiry is open-ended, not expired.
                 */
                $t->date('effective_from');
                $t->date('expires_on')->nullable();

                // Set when an instrument is withdrawn before its expiry.
                $t->date('cancelled_on')->nullable();

                $t->text('note')->nullable();
                $t->string('added_by', 191)->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['reinsurer', 'effective_from'], 'tloc_reinsurer_from_idx');
            });
        }

        if (! Schema::hasTable('treaty_cash_loss_recoveries')) {
            Schema::create('treaty_cash_loss_recoveries', function (Blueprint $t) {
                $t->id();

                $t->string('treaty', 32)->index();
                $t->unsignedSmallInteger('underwriting_year')->index();

                // The claim the cash was demanded against. Not a foreign key for
                // the same reason as above — claims live on another connection
                // in places, and a register that cannot be written is no
                // register.
                $t->unsignedBigInteger('claim_id')->index();

                // Null where the demand was made against the treaty as a whole
                // rather than a named reinsurer, which is the normal case while
                // the panel is unplaced.
                $t->string('reinsurer', 191)->nullable()->index();

                /*
                 * DEMANDED AND RECEIVED ARE DIFFERENT FACTS, and only the second
                 * comes off the account. BR-CLM-05 entitles the Reinsured to
                 * payment on demand once a General claim exceeds 500,000 of the
                 * treaty; BR-CLM-06 gives Motor five working days above 250,000
                 * of the ceded portion. A demand nobody paid is a debt, not a
                 * recovery, and deducting it would relieve reinsurers of money
                 * they never sent.
                 */
                $t->date('demanded_on');
                $t->decimal('amount_demanded', 20, 2)->default(0);

                $t->date('received_on')->nullable();
                $t->decimal('amount_received', 20, 2)->default(0);

                // BR-CLM-05 requires the Loss Notification / Cash Loss Request
                // Form (Annexure A) completed in full before the entitlement
                // arises, so the reference to it is part of the record.
                $t->string('annexure_a_reference', 191)->nullable();

                // The quarter the recovery was taken into account, so a
                // recovery is deducted once and can be traced to the statement
                // that deducted it.
                $t->unsignedTinyInteger('accounted_quarter')->nullable();
                $t->unsignedSmallInteger('accounted_year')->nullable();

                $t->text('note')->nullable();
                $t->string('added_by', 191)->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['treaty', 'underwriting_year', 'received_on'], 'tclr_treaty_year_recv_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('treaty_cash_loss_recoveries');
        Schema::dropIfExists('treaty_letters_of_credit');
    }
};
