<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyLetterOfCredit;
use AlphaDirect\Models\TreatyReserveDeposit;
use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The reserve deposit ledger and its interest — RI-18 step 6, BR-ACC-12 to 16.
 *
 * A DEPOSIT IS PER REINSURER, NOT PER TREATY, because the rule that decides it
 * is about the reinsurer rather than the business: BR-ACC-14 retains a deposit
 * only against reinsurers NOT domiciled in Botswana, and BR-ACC-13 retains none
 * at all where a letter of credit or irrevocable guarantee is on file. Two
 * reinsurers on the same cession can therefore attract 40%, nil and nil for
 * different reasons, and a treaty-level figure could not express that.
 *
 * NIL SAYS WHICH KIND OF NIL IT IS. A domestic reinsurer and one holding a
 * letter of credit both retain nothing, and the two are not the same fact —
 * a letter of credit can lapse. Both are stored rather than inferred, and
 * TreatyReserveDeposit::exemption() reads back the reason.
 *
 * IT IS RELEASED AND RE-ESTABLISHED EACH YEAR. Reinsurance ruled this on
 * 7 September 2026, and it REPLACES the reading this class carried before —
 * that BR-GOV-12's "no portfolio entry and no portfolio withdrawal" left no
 * annual transfer to release against, so the deposit accumulated indefinitely
 * and BR-ACC-16 discharged it only on termination. It does not: the reserve for
 * a treaty year is released at that year's end and a fresh one is established
 * for the next.
 *
 * SO THE FOURTH QUARTER RETAINS AND THEN RELEASES THE WHOLE POSITION, and both
 * lines are shown rather than netted into one. Q4 covers April to June and its
 * premium attracts a retention like any other quarter; the release then clears
 * opening, retention and accrued interest together, so balance_carried is nil
 * at every year end and Q1 of the next year opens at nil without needing to
 * know it crossed a boundary. WHICH QUARTER CARRIES THE RELEASE IS A
 * CONVENTION, not a quotation from the wording — the ruling says "each year"
 * and not which account renders it. Year-end is the conservative choice: it
 * returns the money in the account for the period that ended rather than
 * holding it a quarter longer. Worth confirming with Reinsurance.
 *
 * INTEREST ACCRUES ON THE OPENING BALANCE. BR-ACC-15 accrues "from the dates on
 * which the respective amounts are credited to the Reserve Fund", and an amount
 * retained in this quarter is credited when this account is rendered — 45 days
 * after the quarter closes, under BR-ACC-01. So it earns nothing in the quarter
 * that created it and a full quarter thereafter. THIS IS A CONVENTION, not a
 * quotation from the wording, and it is the conservative one: crediting mid
 * quarter instead would pay reinsurers more.
 *
 * WHAT IT CANNOT DO YET, and says so rather than returning zero:
 *   - The panel is empty. reinsurer_shares holds no rows, so there is nothing to
 *     apportion the retention across. BR-SEC-08 wants every ceded amount
 *     allocated per reinsurer and reconciling to the total; General is 34.00
 *     points short of the cession and Motor 33.10.
 *   - The average call rate is a market rate with no source in this system. It
 *     is configuration, and a statement reports the rate it used. Reinsurance
 *     confirmed on 7 September 2026 that it is the average call rate FOR THE
 *     YEAR, so it is one rate per treaty year rather than a rate per quarter.
 *
 * MOTOR IS SETTLED, AND RI-01 OPEN ITEM 7 IS CLOSED. Reinsurance ruled on
 * 7 September 2026 that the reserve deposit applies to the GQS treaty. Motor
 * therefore retains NOTHING — a genuine nil, not a refusal. This class used to
 * throw on a Motor deposit on the grounds that its slip said only "Domestic
 * Reinsurers - Nil" while Article 9 provides for a deposit against anyone not
 * domiciled in Botswana, and refusing was not the same as nil. The ruling
 * supplies the term the slip did not, so the nil is now stated rather than
 * assumed.
 *
 * TWO RULINGS CONFIRMED WHAT WAS ALREADY BUILT, and are recorded so nobody
 * reopens them:
 *   - NO LETTER OF CREDIT EXISTS. Reinsurance confirmed on 7 September 2026
 *     that the reinsurers provide none. BR-ACC-13's discharge is read from the
 *     register rather than assumed, so an empty register already means every
 *     foreign reinsurer retains the full 40% — no code depended on a letter
 *     being there. The branch stays, because "none today" is not "none ever"
 *     and a letter can be lodged next year.
 *   - CASE RESERVES ONLY, NO IBNR. Confirmed 7 September 2026 for BR-ACC-07.
 *     Nothing in this system holds an IBNR reserve, so the outstanding builder
 *     was already case-only by construction rather than by choice. Recorded
 *     there too, so an IBNR column arriving later is not silently summed in.
 */
class StatementReserveDepositBuilder
{
    /** Botswana. Anything else is foreign for Article 9. */
    public const DOMESTIC_COUNTRY = 'BW';

    /**
     * The quarter that closes a treaty year, and so releases the deposit.
     *
     * The treaty year runs 1 July to 30 June, so Q4 is April to June. Named
     * rather than written as 4 at the comparison, because "quarter 4" and "the
     * quarter the year ends in" are the same number only by coincidence of
     * where the year starts.
     */
    public const FINAL_QUARTER = 4;

    /**
     * The interest rate a deposit earns: the average call rate less the margin.
     *
     * BOTH HALVES ARE KEPT. The margin is a treaty term and the call rate is a
     * market rate, and next year only one of them moves — storing the net figure
     * alone would lose which.
     *
     * @return array{call_rate_pct:float,margin_pct:float,net_pct:float,configured:bool}
     */
    public function interestRate(string $treaty = 'general'): array
    {
        $terms    = $this->termsFor($treaty);
        $callRate = $terms['call_rate_pct'];
        $margin   = $terms['margin_pct'];

        return [
            'call_rate_pct' => (float) ($callRate ?? 0.0),
            'margin_pct'    => (float) ($margin ?? 0.0),
            'net_pct'       => round(max((float) ($callRate ?? 0.0) - (float) ($margin ?? 0.0), 0.0), 6),
            'configured'    => $callRate !== null && $margin !== null,
            'source'        => 'config',
        ];
    }

    /**
     * The rate that GOVERNS a statement: the one it was rendered on, if it has one.
     *
     * THE RATE THAT APPLIED IS NOT THE RATE THAT IS CONFIGURED TODAY, and until
     * now a rebuild could not tell the difference. writeItems() force-deletes a
     * statement's deposit rows and recreates them from config, so rebuilding a
     * 2026/27 account after the average call rate moved would restate its
     * interest at the later rate — changing money owed on a quarter that may
     * already have been settled, silently, with no control reporting it.
     *
     * THIS IS THE RULE THE CLOCKS ALREADY FOLLOW. TreatyStatement stores its
     * render and confirm dates rather than recomputing them, because "the date
     * that governs is the one that applied when the quarter closed, not one
     * recalculated later from a rule someone has since edited". A market rate
     * is the same kind of fact, and it was the one left recomputed.
     *
     * SO THE STORED RATE WINS WHERE THERE IS ONE. Both halves are read back —
     * the margin is a treaty term and the call rate is a market rate, and next
     * year only one of them moves, so restating from a net figure would lose
     * which. openingBalanceFor() already guards a late rebuild from poisoning
     * later quarters; this is the same protection for the rate.
     *
     * A DELIBERATE RE-RATE IS STILL POSSIBLE and has to be explicit: clear the
     * statement's deposit rows and rebuild, or pass $forceConfigRate. Correcting
     * a rate captured wrong is legitimate; doing it by accident on every rebuild
     * is not.
     *
     * @return array{call_rate_pct:float,margin_pct:float,net_pct:float,configured:bool,source:string}
     */
    public function rateFor(TreatyStatement $statement, bool $forceConfigRate = false): array
    {
        $treaty = strtolower(trim((string) $statement->treaty));

        if ($forceConfigRate) {
            return $this->interestRate($treaty);
        }

        $stored = TreatyReserveDeposit::where('treaty_statement_id', $statement->id)
            ->whereNotNull('call_rate_pct')
            ->first(['call_rate_pct', 'interest_margin_pct']);

        if (! $stored) {
            return $this->interestRate($treaty);
        }

        $callRate = (float) $stored->call_rate_pct;
        $margin   = (float) $stored->interest_margin_pct;

        return [
            'call_rate_pct' => $callRate,
            'margin_pct'    => $margin,
            'net_pct'       => round(max($callRate - $margin, 0.0), 6),
            // A stored rate IS a configured one — it could not have been written
            // otherwise — and a statement reporting "rate not set" while showing
            // interest computed on one would be the worse answer.
            'configured'    => true,
            'source'        => 'statement',
        ];
    }

    /**
     * A treaty's reserve deposit terms, or nulls where nobody has stated them.
     *
     * GENERAL IS FULLY SPECIFIED by BR-ACC-12: 40.00% premium reserve, interest
     * 2.00% below the average call rate, loss reserve nil. Only the call rate is
     * outside the slip, being a market rate.
     *
     * MOTOR IS NIL BY RULING, NOT BY ANALOGY. Reinsurance confirmed on
     * 7 September 2026 that the reserve deposit applies to the GQS treaty, so
     * Motor retains nothing. This used to return null — a refusal — because the
     * Motor slip said only "Domestic Reinsurers - Nil" while Article 9 provides
     * for a deposit against reinsurers not domiciled in Botswana and GIC Re is
     * foreign (RI-01 open item 7, now closed).
     *
     * ZERO IS NOW THE HONEST ANSWER AND NULL WOULD NOT BE. The distinction the
     * old code protected was between "no deposit is due" and "nobody has said" —
     * and somebody has now said. A null here would go on refusing to produce a
     * Motor statement over a question that has been answered.
     *
     * THE RULING IS A DEFAULT, NOT A CEILING. Motor's nil is what applies when
     * nobody has configured Motor terms — it is NOT hardcoded past the config.
     * Stating the terms in config still overrides it, because "the deposit
     * applies to GQS" is this year's position and a later Motor deposit would
     * otherwise need a code change to express. A first cut of this DID short
     * circuit the config, and the test proving the mechanism works the day the
     * terms arrive is what caught it.
     *
     * @return array{retained_pct:float|null,margin_pct:float|null,call_rate_pct:float|null}
     */
    public function termsFor(string $treaty): array
    {
        $t = strtolower(trim($treaty));

        if ($t === 'general') {
            return [
                'retained_pct'  => TreatyReserveDeposit::GENERAL_FOREIGN_PCT,
                'margin_pct'    => TreatyReserveDeposit::GENERAL_INTEREST_MARGIN_PCT,
                'call_rate_pct' => config('reinsurance.terms.general.reserve_call_rate_pct'),
            ];
        }

        if ($t === 'motor') {
            return [
                // Nil by ruling where unconfigured; a stated term still wins.
                'retained_pct'  => config('reinsurance.terms.motor.reserve_retained_pct') ?? 0.0,
                'margin_pct'    => config('reinsurance.terms.motor.reserve_margin_pct') ?? 0.0,
                'call_rate_pct' => config('reinsurance.terms.motor.reserve_call_rate_pct') ?? 0.0,
            ];
        }

        return [
            'retained_pct'  => config("reinsurance.terms.{$t}.reserve_retained_pct"),
            'margin_pct'    => config("reinsurance.terms.{$t}.reserve_margin_pct"),
            'call_rate_pct' => config("reinsurance.terms.{$t}.reserve_call_rate_pct"),
        ];
    }

    /**
     * The placed panel for a treaty year, with each reinsurer's domicile.
     *
     * @return array<int,array{reinsurer:string,share_pct:float,is_domestic:bool,
     *                          has_letter_of_credit:bool}>
     */
    /**
     * Reinsurers whose deposit obligation is discharged on a date — BR-ACC-13.
     *
     * READ FROM THE REGISTER, AND AS AT A DATE. This used to be a list of
     * company names in config, and a name has no expiry: an instrument that
     * lapsed went on exempting a reinsurer from a 40% retention until somebody
     * edited a file. The deposit exists as security, so an exemption outliving
     * the security it rests on is the failure that matters here.
     *
     * A LETTER OF CREDIT NOT ON THE PRESCRIBED FORM DISCHARGES NOTHING.
     * BR-ACC-13 is specific — issued by a bank in the form prescribed by the
     * Registrar of Short Term Insurance — and a bank's own wording is not that.
     * An irrevocable guarantee is named separately and carries no form test.
     *
     * @return array<int,string>  reinsurer names, upper-cased for comparison
     */
    public function reinsurersDischargedOn(string $treaty, string $asAt): array
    {
        if (! Schema::hasTable('treaty_letters_of_credit')) {
            return [];
        }

        return TreatyLetterOfCredit::query()
            ->forTreaty($treaty)
            ->inForceOn($asAt)
            ->get()
            ->filter(fn (TreatyLetterOfCredit $l) => $l->discharges())
            ->map(fn (TreatyLetterOfCredit $l) => strtoupper(trim((string) $l->reinsurer)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  string|null  $asAt  the date the letters of credit are tested on;
     *                             the quarter's close where a statement drives this
     */
    public function panelFor(string $treaty, int $underwritingYear, ?string $asAt = null): array
    {
        $asAt ??= date('Y-m-d');

        $rows = DB::table('reinsurer_shares as rs')
            ->leftJoin('reinsurer as r', function ($j) {
                // reinsurer_id is a varchar on the shares table and the id is an
                // integer on the other, so both sides are cast rather than left
                // to MySQL's coercion — the same trap as the risk_address join.
                $j->on(DB::raw('CAST(rs.reinsurer_id AS CHAR)'), '=', DB::raw('CAST(r.id AS CHAR)'));
            })
            ->where('rs.treaty_year', $underwritingYear)
            ->whereRaw('LOWER(TRIM(rs.treaty_id)) = ?', [strtolower(trim($treaty))])
            ->get(['rs.reinsurer_id', 'rs.share_pct', 'r.company_name', 'r.country']);

        $withLc = $this->reinsurersDischargedOn($treaty, $asAt);

        $panel = [];
        foreach ($rows as $r) {
            $name = (string) ($r->company_name ?? $r->reinsurer_id);

            $panel[] = [
                'reinsurer'            => $name,
                'share_pct'            => (float) $r->share_pct,
                // A MISSING COUNTRY IS NOT BOTSWANA. Treating an unknown
                // domicile as domestic would retain nothing and look settled;
                // treating it as foreign retains a deposit somebody will query.
                'is_domestic'          => strtoupper(trim((string) $r->country)) === self::DOMESTIC_COUNTRY,
                'has_letter_of_credit' => in_array(strtoupper($name), $withLc, true),
            ];
        }

        return $panel;
    }

    /**
     * Compute each reinsurer's deposit for a statement's quarter.
     *
     * @return array{by_reinsurer:array<int,array<string,mixed>>,retained:float,
     *                interest:float,carried:float,panel_placed:bool,
     *                interest_rate:array<string,mixed>,premium_base:float}
     */
    public function depositsFor(TreatyStatement $statement): array
    {
        $treaty = strtolower(trim((string) $statement->treaty));
        $terms  = $this->termsFor($treaty);

        // NO PERCENTAGE, NO DEPOSIT COMPUTED — and that is not the same as a
        // deposit of nil. GENERAL AND MOTOR BOTH REACH PAST THIS NOW: General
        // carries BR-ACC-12's 40% and Motor is a stated nil since Reinsurance
        // ruled the deposit applies to the GQS treaty (7 September 2026). This
        // is left for a THIRD treaty arriving without terms, where zero would
        // assert something nobody has said and General's 40% by analogy would
        // retain real money on terms nobody agreed.
        if ($terms['retained_pct'] === null) {
            throw new RuntimeException(sprintf(
                'No reserve deposit percentage is configured for the %s treaty, so none can be '
                . 'computed. General and Motor are both settled — General retains 40%% against '
                . 'foreign reinsurers under BR-ACC-12 and Motor retains nil, the deposit applying '
                . 'to the GQS treaty. Set reinsurance.terms.%s.reserve_retained_pct once '
                . 'Reinsurance states this treaty\'s terms. Refusing is not the same as nil.',
                $treaty,
                $treaty
            ));
        }

        $premiumBase = (float) $statement->items()
            ->where('item_type', TreatyStatementItem::PREMIUM)
            ->sum('amount');

        // THE RATE THE STATEMENT WAS RENDERED ON, not the one configured today.
        // See rateFor(): a rebuild after the market rate moved would otherwise
        // restate interest on a quarter that may already be settled.
        $rate  = $this->rateFor($statement);
        // LETTERS OF CREDIT ARE TESTED AT THE QUARTER'S CLOSE, not today. The
        // deposit position is a position as at the close, so an instrument that
        // lapsed after the quarter ended was in force for the quarter being
        // accounted — and one taken out afterwards was not.
        $period = TreatyStatement::periodFor(
            (int) $statement->underwriting_year,
            (int) $statement->quarter
        );

        $panel = $this->panelFor($treaty, (int) $statement->underwriting_year, $period['end']);

        // THE YEAR-END QUARTER CLEARS THE DEPOSIT — Reinsurance, 7 September
        // 2026: released and re-established each year. Q4 covers April to June
        // and closes the treaty year, so it retains on its own premium like any
        // quarter and then releases the whole position. Q1 of the next year
        // needs no special case: it opens on a Q4 that carried nil.
        $isYearEnd = (int) $statement->quarter === self::FINAL_QUARTER;

        $byReinsurer = [];
        $retained = 0.0;
        $interest = 0.0;
        $released = 0.0;
        $carried  = 0.0;

        foreach ($panel as $p) {
            $share = $p['share_pct'] / 100.0;
            $base  = round($premiumBase * $share, 2);

            $exempt = $p['is_domestic'] || $p['has_letter_of_credit'];

            $thisRetention = $exempt
                ? 0.0
                : round($base * ($terms['retained_pct'] / 100.0), 2);

            $opening = $this->openingBalanceFor($statement, $p['reinsurer']);

            // Interest on the OPENING balance only — this quarter's retention is
            // credited when this account is rendered, so it earns from next
            // quarter. A quarter is a quarter of a year.
            $accrued = $exempt ? 0.0 : round($opening * ($rate['net_pct'] / 100.0) / 4, 2);

            // The position this quarter closes on, before any release.
            $closing = round($opening + $thisRetention + $accrued, 2);

            // AT YEAR END THE WHOLE POSITION GOES BACK — opening, this
            // quarter's retention and the interest on it together. Releasing
            // only the opening balance would leave Q4's own retention behind
            // and the deposit would never actually reach nil.
            $thisRelease = $isYearEnd ? $closing : 0.0;

            $byReinsurer[] = [
                'reinsurer'            => $p['reinsurer'],
                'share_pct'            => $p['share_pct'],
                'is_domestic'          => $p['is_domestic'],
                'has_letter_of_credit' => $p['has_letter_of_credit'],
                'premium_base'         => $base,
                'retained_pct'         => $exempt ? 0.0 : $terms['retained_pct'],
                'retained'             => $thisRetention,
                'opening_balance'      => $opening,
                'interest_accrued'     => $accrued,
                'released'             => $thisRelease,
                'balance_carried'      => $isYearEnd ? 0.0 : $closing,
            ];

            $retained += $thisRetention;
            $interest += $accrued;
            $released += $thisRelease;
            $carried  += $isYearEnd ? 0.0 : $closing;
        }

        return [
            'by_reinsurer'  => $byReinsurer,
            'retained'      => round($retained, 2),
            'interest'      => round($interest, 2),
            'released'      => round($released, 2),
            'is_year_end'   => $isYearEnd,
            'carried'       => round($carried, 2),
            'premium_base'  => round($premiumBase, 2),
            'retained_pct'  => $terms['retained_pct'],
            'interest_rate' => $rate,
            // BR-SEC-08: an empty panel is reported, never treated as nil.
            'panel_placed'  => $panel !== [],
        ];
    }

    /**
     * What a reinsurer carried out of the most recent earlier statement.
     *
     * THE LEDGER IS THE PRIOR QUARTER'S CLOSING BALANCE, not a running sum of
     * every row — a restated quarter must not be counted twice. Ordering is by
     * the period the statement covers rather than by when the row was written,
     * because a late rebuild of an early quarter would otherwise become the
     * opening balance of every quarter after it.
     */
    public function openingBalanceFor(TreatyStatement $statement, string $reinsurer): float
    {
        $prior = DB::table('treaty_reserve_deposits as d')
            ->join('treaty_statements as s', 's.id', '=', 'd.treaty_statement_id')
            ->where('d.reinsurer', $reinsurer)
            ->whereNull('d.deleted_at')
            ->whereNull('s.deleted_at')
            ->where('s.treaty', $statement->treaty)
            ->where(function ($q) use ($statement) {
                $q->where('s.underwriting_year', '<', (int) $statement->underwriting_year)
                    ->orWhere(function ($q2) use ($statement) {
                        $q2->where('s.underwriting_year', (int) $statement->underwriting_year)
                            ->where('s.quarter', '<', (int) $statement->quarter);
                    });
            })
            ->orderByDesc('s.underwriting_year')
            ->orderByDesc('s.quarter')
            ->first(['d.balance_carried']);

        return round((float) ($prior->balance_carried ?? 0.0), 2);
    }

    /**
     * Write the ledger rows and the statement lines that settle against them.
     *
     * THE LEDGER AND THE ACCOUNT ARE BOTH WRITTEN. The ledger carries the
     * per-reinsurer position across quarters; the statement carries what moves
     * this quarter. A retention is NEGATIVE on the account — money the cedant
     * holds back rather than remits — and the interest on it is positive,
     * because it is owed to the reinsurer whose money is being held.
     *
     * @return array<string,mixed>
     */
    public function writeItems(TreatyStatement $statement): array
    {
        $d = $this->depositsFor($statement);

        DB::transaction(function () use ($statement, $d) {
            $statement->items()->whereIn('item_type', [
                TreatyStatementItem::RESERVE_DEPOSIT,
                TreatyStatementItem::RESERVE_RELEASE,
                TreatyStatementItem::RESERVE_INTEREST,
            ])->delete();

            TreatyReserveDeposit::where('treaty_statement_id', $statement->id)->forceDelete();

            foreach ($d['by_reinsurer'] as $r) {
                TreatyReserveDeposit::create([
                    'treaty_statement_id'  => $statement->id,
                    'reinsurer'            => $r['reinsurer'],
                    'is_domestic'          => $r['is_domestic'],
                    'has_letter_of_credit' => $r['has_letter_of_credit'],
                    'premium_base'         => $r['premium_base'],
                    'retained_pct'         => $r['retained_pct'],
                    'retained'             => $r['retained'],
                    'released'             => $r['released'],
                    'call_rate_pct'        => $d['interest_rate']['call_rate_pct'],
                    'interest_margin_pct'  => $d['interest_rate']['margin_pct'],
                    'interest_accrued'     => $r['interest_accrued'],
                    'balance_carried'      => $r['balance_carried'],
                ]);
            }

            if (abs($d['retained']) >= 0.005) {
                $statement->items()->create([
                    'item_type'    => TreatyStatementItem::RESERVE_DEPOSIT,
                    'amount'       => -1 * $d['retained'],
                    'basis_amount' => $d['premium_base'],
                    'rate'         => (float) $d['retained_pct'] / 100.0,
                ]);
            }

            // POSITIVE, BECAUSE THE MONEY GOES BACK. A retention is negative —
            // premium the cedant holds rather than remits — so its release is
            // the opposite sign, alongside the interest it earned while held.
            // Written as its own line and never netted against the retention:
            // on a year-end account both are real movements and a single net
            // figure would hide that the deposit was cleared at all.
            if (abs($d['released']) >= 0.005) {
                $statement->items()->create([
                    'item_type' => TreatyStatementItem::RESERVE_RELEASE,
                    'amount'    => $d['released'],
                ]);
            }

            if (abs($d['interest']) >= 0.005) {
                $statement->items()->create([
                    'item_type' => TreatyStatementItem::RESERVE_INTEREST,
                    'amount'    => $d['interest'],
                    'rate'      => $d['interest_rate']['net_pct'] / 100.0,
                ]);
            }
        });

        return $d + ['balance' => $statement->balance()];
    }
}
