<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Outstanding losses — RI-18 step 4, BR-ACC-07.
 *
 * "Outstanding losses, broken down into YEARS OF OCCURRENCE (General) and
 * UNDERWRITING YEARS (Motor)." Two treaties, two axes, and the same claim sits in
 * different buckets on each: a loss occurring in March 2027 on a policy written
 * in August 2026 is occurrence year 2027 and underwriting year 2026/27. Building
 * one axis and reusing it would be wrong on whichever treaty it was not built
 * for, so the axis is chosen per treaty rather than configured once.
 *
 * OUTSTANDING IS A POSITION, NOT A MOVEMENT. Premium and claims paid are things
 * that happened during the quarter; outstanding is what is still owed at the
 * close of it. So this reads every reserve raised up TO the period end, not those
 * raised within it — the two differ by the whole prior book.
 *
 * IT IS reserve_amt LESS payment_amt, AND NOT balance. The table carries a
 * `balance` column that looks like the obvious choice and is not: on 366 of
 * 16,996 rows it disagrees with reserve less paid, and where it disagrees it
 * looks cumulative — 812.88 against a 406.44 reserve, exactly twice.
 * `totalReserveAmt` is zero on all 16,996 rows. Neither belongs on a statement.
 *
 * CASE RESERVES ONLY, AND NO IBNR. Reinsurance ruled this on 7 September 2026
 * for BR-ACC-07. It needs no code today because nothing in this system holds an
 * IBNR reserve — claim_reserves_coverages carries case reserves and there is no
 * reserve-type column to filter on, so the figure is case-only by construction
 * rather than by choice. IT IS WRITTEN DOWN BECAUSE THAT WILL CHANGE. An IBNR
 * column arriving on this table would be summed in silently by the query below
 * and the outstanding position would quietly include a reserve the treaty
 * excludes. Whoever adds one has to exclude it here.
 *
 * THE GENERAL AXIS CANNOT BE BUILT ON THIS DATA AND SAYS SO. Year of occurrence
 * needs the date of loss, and claims.incident_date is populated on FOUR of 3,550
 * claims; reported_date on seventy. Only created_at is universal, and that is
 * when the record was keyed rather than when the loss happened — a claim keyed in
 * January for a December loss would be filed under the wrong year, and the
 * statement would look complete either way. So the General axis refuses rather
 * than guesses, and names what it needs. The Motor axis works today.
 *
 * THE YEAR IS BUCKETED IN PHP, NOT IN SQL. YEAR() and MONTH() are MySQL-only —
 * sqlite has neither, and a query using them would pass on the server and fail on
 * the test engine. The date arithmetic is the part worth testing anyway, so it
 * lives somewhere a test can reach without a database.
 */
class StatementOutstandingBuilder
{
    private const NOT_VOIDED = '(crc.is_payment_voided IS NULL OR crc.is_payment_voided = 0)';

    public function __construct(private CessionSource $cession)
    {
    }

    /**
     * Refuse to produce outstanding figures on a basis this builder cannot read.
     *
     * Same reason as StatementClaimsBuilder: the ceded proportion of an
     * outstanding loss is the quota share and surplus over the sum insured for
     * the unit the reserve belongs to, and only policy_reinsurance_regulatory
     * carries a rate at that grain. Without the guard this reported regulatory
     * outstanding beside legacy premium on the same statement, over two
     * populations that have nothing to do with each other.
     */
    private function guardBasis(): void
    {
        if ($this->cession->isRegulatory()) {
            return;
        }

        throw new RuntimeException(
            'Outstanding losses cannot be built on the ' . $this->cession->basis() . ' basis. '
            . 'The ceded proportion of a reserve is taken at the grain of the risk unit, which '
            . 'only the regulatory table carries. Set reinsurance.engine to regulatory, and '
            . 'stage the book first.'
        );
    }

    /** Which axis a treaty reports on. BR-ACC-07. */
    public function axisFor(string $treaty): string
    {
        return strtolower(trim($treaty)) === 'motor'
            ? TreatyStatementItem::AXIS_UNDERWRITING
            : TreatyStatementItem::AXIS_OCCURRENCE;
    }

    /**
     * The treaty year a date falls in. July to June, named for the year it opens.
     *
     * 1 July 2026 and 30 June 2027 are both 2026/27, so both return 2026 — and
     * the boundary is the whole point: 30 June belongs to the year closing, 1
     * July to the one opening.
     */
    public function underwritingYearOf(string $date): int
    {
        $ts = strtotime($date);

        if ($ts === false) {
            throw new RuntimeException("Cannot read a date from '{$date}'.");
        }

        $year  = (int) date('Y', $ts);
        $month = (int) date('n', $ts);

        return $month >= TreatyStatement::YEAR_STARTS_MONTH ? $year : $year - 1;
    }

    /**
     * Whether the axis a treaty needs can be computed from the data we hold.
     *
     * @return array{available:bool,axis:string,reason:string|null,coverage:array<string,int>}
     */
    public function axisAvailability(string $treaty): array
    {
        $axis = $this->axisFor($treaty);

        if ($axis === TreatyStatementItem::AXIS_UNDERWRITING) {
            return ['available' => true, 'axis' => $axis, 'reason' => null, 'coverage' => []];
        }

        $c = DB::selectOne('
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN incident_date IS NOT NULL THEN 1 ELSE 0 END) AS with_incident
            FROM claims
        ');

        $total = (int) ($c->total ?? 0);
        $have  = (int) ($c->with_incident ?? 0);

        return [
            'available' => $total > 0 && $have === $total,
            'axis'      => $axis,
            'reason'    => $have === $total ? null : sprintf(
                'Year of occurrence needs the date of loss, and claims.incident_date is '
                . 'populated on %s of %s claims. Filing a loss under the year its record '
                . 'happened to be keyed would put it in the wrong year and look no different '
                . 'on the statement.',
                number_format($have),
                number_format($total)
            ),
            'coverage'  => ['with_incident_date' => $have, 'claims' => $total],
        ];
    }

    /**
     * Regulatory units that cannot reach a statement because their group is
     * unresolvable.
     *
     * REPORTED, NOT FIXED HERE. Every builder joins the regulatory row to
     * reinsurance_group on BOTH the detail's group_id and the row's group_code.
     * That join is a consistency check, and a row whose group_code is null fails
     * it and disappears — on the test server that is 7 Property rows carrying
     * 350,149,075.01 of sum insured, none of which survive the join and none of
     * which are counted anywhere.
     *
     * THE JOIN IS NOT LOOSENED TO FIX IT. Making it tolerant would change the
     * figures on every statement to include units whose mapping is unknown, and
     * that is a decision about the data rather than about the query — either the
     * group_code is backfilled or somebody says these units are out of scope.
     *
     * AND THE HOLE IS NOW ACTUALLY VISIBLE. This said "the size of the hole is
     * at least visible" while no production code called the method — it was
     * public, correct and covered by a test, and reached no reader. A figure
     * computed on request that nothing requests is the same as no figure.
     * outstandingFor() returns it beside unclassified_groups now, which is
     * where somebody looking at an outstanding position would find it.
     *
     * @return array{rows:int,sum_insured:float}
     */
    public function unmappedUnits(): array
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS n, COALESCE(SUM(rr.sum_insured), 0) AS si
            FROM policy_reinsurance_regulatory rr
            WHERE rr.deleted_at IS NULL
              AND (rr.group_code IS NULL OR TRIM(rr.group_code) = '')
        ");

        return [
            'rows'        => (int) ($row->n ?? 0),
            'sum_insured' => round((float) ($row->si ?? 0), 2),
        ];
    }

    /**
     * Outstanding losses at the close of a quarter, by class and by year.
     *
     * @return array{by_class_year:array<string,array<int,array{gross:float,ceded:float}>>,
     *                total_gross:float,total_ceded:float,axis:string,rows:int}
     */
    public function outstandingFor(string $treaty, int $underwritingYear, int $quarter): array
    {
        $this->guardBasis();

        $availability = $this->axisAvailability($treaty);

        if (! $availability['available']) {
            throw new RuntimeException(
                "Outstanding losses cannot be reported on the {$availability['axis']} axis. "
                . $availability['reason']
            );
        }

        $p = TreatyStatement::periodFor($underwritingYear, $quarter);

        $rows = DB::select("
            SELECT u.regulatory_class,
                   u.group_code,
                   COALESCE(pa.transaction_date, DATE(pa.created_at)) AS axis_date,
                   -- COALESCE BOTH, AND NOT FOR TIDINESS. reserve_amt is NULL on
                   -- 9,159 of 16,996 rows and payment_amt on 9,716; NULL minus
                   -- anything is NULL, and SUM skips NULLs rather than treating
                   -- them as zero. So the naive subtraction returned NULL on
                   -- exactly the rows that carry an unpaid reserve -- the whole
                   -- outstanding book -- and reported 0.00 with a straight face.
                   -- Across the table it understated by 11.46m: 7,981,590.25
                   -- against the true 19,439,588.95.
                   SUM(COALESCE(crc.reserve_amt, 0) - COALESCE(crc.payment_amt, 0))
                       AS gross_outstanding,
                   SUM((COALESCE(crc.reserve_amt, 0) - COALESCE(crc.payment_amt, 0)) * u.ceded_rate)
                       AS ceded_outstanding,
                   COUNT(*) AS n
            FROM claim_reserves_coverages crc
            JOIN claim_reserves cr ON cr.id = crc.reserve_id
            -- DISTINCT ON WHAT THE JOIN ACTUALLY USES. This table holds a row
            -- per formula and layer, so one coverage detail appears several
            -- times on the same action, address and group -- coverage 56308
            -- carries four. Joined raw, a single claim payment was counted once
            -- per row: 31 joined rows for 25 payments on the book, and a gross
            -- of 839,923.98 where the truth is 825,160.41.
            JOIN (
                SELECT DISTINCT action_id, risk_address_id, group_id, pocoverage_detail_id
                FROM policy_reinsurance_details
            ) d ON d.pocoverage_detail_id = crc.coverage_id
            JOIN policy_actions pa ON pa.id = d.action_id
            JOIN (
                SELECT rr.action_id, rr.risk_address, rr.group_code, rr.regulatory_class,
                       -- The 1.0 is load-bearing: without it this is integer
                       -- division on sqlite and 7,000,000 over 10,000,000 comes
                       -- back as 0. Same trap as the claims builder.
                       CASE WHEN SUM(rr.sum_insured) > 0
                            THEN (SUM(CASE WHEN rr.layer IN ('quota_share','surplus')
                                           THEN rr.sum_insured ELSE 0 END) * 1.0)
                                 / SUM(rr.sum_insured)
                            ELSE 0 END AS ceded_rate
                FROM policy_reinsurance_regulatory rr
                WHERE rr.deleted_at IS NULL
                GROUP BY rr.action_id, rr.risk_address, rr.group_code, rr.regulatory_class
            ) u ON u.action_id = d.action_id
               AND (CAST(u.risk_address AS CHAR) = CAST(d.risk_address_id AS CHAR)
                    OR (u.risk_address IS NULL AND d.risk_address_id IS NULL))
            -- AN UNGROUPED REGULATORY ROW IS NOT A BROKEN ONE. Where a
            -- regulatory class aggregates several groups the allocation has no
            -- single group_code, and that is what a regulatory mapping does:
            -- Property at one risk address is PROPERTYANDBI_COM,
            -- ACCIDENTAL_DAMAGE_COM and ELECTRONIC_EQ_AND_BI_COM together.
            -- Requiring a code dropped those rows silently and took
            -- 49,810,404.11 of ceded sum insured off every statement.
            --
            -- So: match the code when the row names one, and fall back to the
            -- group's own regulatory_mapping when it does not. Verified not to
            -- double count -- on the affected actions Property appears only as
            -- an ungrouped row and every other class only as a grouped one, so
            -- no detail row can match both ways.
            JOIN reinsurance_group g
              ON g.id = d.group_id
             AND (
                   (u.group_code IS NOT NULL AND TRIM(u.group_code) <> ''
                        AND g.group_code = u.group_code)
                OR ((u.group_code IS NULL OR TRIM(u.group_code) = '')
                        AND TRIM(COALESCE(g.regulatory_mapping, '')) = u.regulatory_class)
                 )
            WHERE cr.date <= ?
              AND pa.deleted_at IS NULL
              AND " . self::NOT_VOIDED . "
            GROUP BY u.regulatory_class, u.group_code, axis_date
        ", [$p['end']]);

        $byClassYear = [];
        $gross = 0.0;
        $ceded = 0.0;
        $n = 0;
        $unclassified = 0;

        foreach ($rows as $r) {
            if ($r->axis_date === null) {
                continue;
            }

            $class = (string) $r->regulatory_class;
            $group = $r->group_code === null ? null : (string) $r->group_code;

            // A STATEMENT CARRIES ITS OWN TREATY'S BUSINESS, AND THE SPLIT IS BY
            // GROUP. The class is what the statement reports by; the group is
            // what decides the treaty. RI-05 is the authority on both lists.
            if (! TreatyStatement::coversGroup($treaty, $group)) {
                continue;
            }

            if (! TreatyStatement::isClassifiableGroup($group)) {
                $unclassified++;
            }

            $year = $this->underwritingYearOf((string) $r->axis_date);

            $g = (float) $r->gross_outstanding;
            $c = (float) $r->ceded_outstanding;

            $byClassYear[$class][$year]['gross'] = round(
                ($byClassYear[$class][$year]['gross'] ?? 0.0) + $g,
                2
            );
            $byClassYear[$class][$year]['ceded'] = round(
                ($byClassYear[$class][$year]['ceded'] ?? 0.0) + $c,
                2
            );

            $gross += $g;
            $ceded += $c;
            $n += (int) $r->n;
        }

        // A year that nets to nothing is dropped, because an outstanding
        // position of zero is not a position. This is the opposite of the
        // premium builder, where a class that netted to nil kept its line —
        // there the nil was the result of a cancellation somebody should see.
        foreach ($byClassYear as $class => $years) {
            foreach ($years as $year => $f) {
                if (abs($f['gross']) < 0.005 && abs($f['ceded']) < 0.005) {
                    unset($byClassYear[$class][$year]);
                }
            }
            if ($byClassYear[$class] === []) {
                unset($byClassYear[$class]);
            } else {
                ksort($byClassYear[$class]);
            }
        }
        ksort($byClassYear);

        return [
            'by_class_year'       => $byClassYear,
            'total_gross'         => round($gross, 2),
            'total_ceded'         => round($ceded, 2),
            'axis'                => $availability['axis'],
            'rows'                => $n,
            // Rows whose group we do not recognise. They are counted into
            // General rather than dropped, and reported so that a statement
            // leaning on them can be looked at before it is rendered.
            'unclassified_groups' => $unclassified,
            // AND THE ROWS THAT DID NOT SURVIVE THE JOIN AT ALL — see
            // unmappedUnits(). Its own docblock said "at least the size of the
            // hole is visible", and it was not: the method was public, correct,
            // and called from nowhere. A figure computed on request that nobody
            // requests is the same failure as no figure, and this one is a hole
            // of 350,149,075.01 of sum insured on the test book.
            //
            // Reported BESIDE the figures rather than raised, because these
            // units are absent from the statement whether anyone looks or not —
            // refusing would withhold a correct outstanding position over rows
            // it never contained. Loosening the join to include them is a
            // decision about the data, not about this query.
            'unmapped_units'      => $this->unmappedUnits(),
        ];
    }

    /**
     * Write the outstanding lines onto a statement.
     *
     * MEMORANDUM ONLY. These report the reserve position and do not settle, so
     * they carry a year and an axis but never enter the balance — see
     * TreatyStatementItem::MEMORANDUM_ONLY. Adding them would overstate every
     * statement by the whole outstanding book.
     *
     * @return array<int,TreatyStatementItem>
     */
    public function writeItems(TreatyStatement $statement): array
    {
        $o = $this->outstandingFor(
            $statement->treaty,
            (int) $statement->underwriting_year,
            (int) $statement->quarter
        );

        $written = [];

        DB::transaction(function () use ($statement, $o, &$written) {
            $statement->items()
                ->where('item_type', TreatyStatementItem::OUTSTANDING_LOSSES)
                ->delete();

            foreach ($o['by_class_year'] as $class => $years) {
                foreach ($years as $year => $f) {
                    $written[] = $statement->items()->create([
                        'item_type'        => TreatyStatementItem::OUTSTANDING_LOSSES,
                        'regulatory_class' => $class,
                        'period_year'      => $year,
                        'period_axis'      => $o['axis'],
                        'amount'           => $f['ceded'],
                        'basis_amount'     => $f['gross'],
                    ]);
                }
            }
        });

        return $written;
    }
}
