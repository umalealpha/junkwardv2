<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyCashLossRecovery;
use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Claims paid, salvages and recoveries — RI-18 step 3, BR-ACC-06.
 *
 * THE JOIN IS THE WHOLE PROBLEM, and the obvious one is wrong.
 * claim_reserves_coverages.coverage_id and policy_reinsurance.coverage_id are
 * BOTH called coverage_id and are NOT the same identifier. On policy 213504 the
 * claims side carries 193161–193173 while the cession carries 14, 15, 20, 21,
 * 28, 71; matching them returns 10 rows out of 16,940, which reads as "these
 * policies have no claims" rather than as a broken join.
 *
 * WHAT THEY ACTUALLY ARE: the claims column is a policy_coverage_detail.id — all
 * four sampled values resolve there — and so is
 * policy_reinsurance_details.pocoverage_detail_id, which is the grain the
 * regulatory read groups on. Joined that way, 1,904 of 16,996 claim rows reach a
 * staged cession unit and 327 reach a stored regulatory allocation. That is the
 * join used here.
 *
 * A CLAIM IS CEDED IN THE PROPORTION ITS RISK WAS. On a quota share the
 * reinsurer pays the share of the loss it took of the risk, so the ceded claim
 * is the gross claim times (quota share + surplus) over the sum insured for the
 * unit the claim belongs to. Nothing is apportioned by premium: two coverages on
 * one policy can cede at different rates, and a policy-level rate would move
 * money between them.
 *
 * SALVAGES AND RECOVERIES ARE NETTED AND ALSO REPORTED. BR-ACC-06 is "claims
 * paid less salvages and recoveries", and a reader checking that subtraction
 * should not have to take it on trust — so claims_paid carries the net figure
 * and salvages and recoveries are written beside it as memorandum lines that do
 * not enter the balance again.
 */
class StatementClaimsBuilder
{
    /** A payment that was voided never happened. */
    private const NOT_VOIDED = '(crc.is_payment_voided IS NULL OR crc.is_payment_voided = 0)';

    public function __construct(private CessionSource $cession)
    {
    }

    /**
     * Refuse to produce claim figures on a basis this builder cannot read.
     *
     * THIS BUILDER IS REGULATORY-ONLY BY CONSTRUCTION. Its ceded rate is the
     * quota share and surplus over the sum insured FOR THE UNIT A CLAIM BELONGS
     * TO, and only policy_reinsurance_regulatory carries that grain. The legacy
     * table records treatySI and treatyPremium per formula and layer, with no
     * per-coverage-detail rate to apportion a loss by, so there is no legacy
     * equivalent of this query to fall back to.
     *
     * WITHOUT THIS GUARD A STATEMENT MIXED TWO BASES AND STILL FOOTED.
     * StatementPremiumBuilder goes through CessionSource and follows the
     * configured basis; this one queried the regulatory table directly whatever
     * the configuration said. So an account drew premium from the whole legacy
     * book — 50 actions — and claims from the nine actions staged under the
     * regulatory mapping, and balanced perfectly between two populations that
     * have nothing to do with each other. Footing proves the arithmetic, not the
     * inputs.
     */
    private function guardBasis(): void
    {
        if ($this->cession->isRegulatory()) {
            return;
        }

        throw new RuntimeException(
            'Claims cannot be built on the ' . $this->cession->basis() . ' basis. A claim is '
            . 'ceded in the proportion its own risk unit was, and only the regulatory table '
            . 'carries a rate at that grain — the legacy table records premium and sum insured '
            . 'per formula and layer, with nothing to apportion a loss by. Producing regulatory '
            . 'claims beside legacy premium would balance two different populations against each '
            . 'other. Set reinsurance.engine to regulatory, and stage the book first.'
        );
    }

    /**
     * Gross and ceded claim movement for a quarter, by class.
     *
     * @return array{
     *     by_class:array<string,array{gross_paid:float,salvages:float,recoveries:float,
     *                                 ceded_paid:float,ceded_salvages:float,ceded_recoveries:float}>,
     *     gross_paid:float, ceded_net:float, rows:int, unmatched_rows:int
     * }
     */
    public function claimsFor(string $treaty, int $underwritingYear, int $quarter): array
    {
        $this->guardBasis();

        $p = TreatyStatement::periodFor($underwritingYear, $quarter);

        $rows = DB::select("
            SELECT u.regulatory_class,
                   u.group_code,
                   SUM(crc.payment_amt)          AS gross_paid,
                   SUM(crc.salvage_payment)      AS salvages,
                   SUM(crc.subrogation_payment)  AS recoveries,
                   -- The unit's own cession rate, not the policy's. Two coverages
                   -- on one policy can cede at different rates.
                   SUM(crc.payment_amt         * u.ceded_rate) AS ceded_paid,
                   SUM(crc.salvage_payment     * u.ceded_rate) AS ceded_salvages,
                   SUM(crc.subrogation_payment * u.ceded_rate) AS ceded_recoveries,
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
            JOIN (
                SELECT rr.action_id, rr.risk_address, rr.group_code, rr.regulatory_class,
                       -- THE 1.0 IS LOAD-BEARING. Without it this is integer
                       -- division on any engine that does it: 7,000,000 over
                       -- 10,000,000 comes back as 0 rather than 0.7, and a
                       -- 70%-ceded claim recovers nothing. It survived a first
                       -- test pass because the 100% and 0% cases divide
                       -- exactly, and only the fractional ones -- which is
                       -- every real policy -- were wrong.
                       CASE WHEN SUM(rr.sum_insured) > 0
                            THEN (SUM(CASE WHEN rr.layer IN ('quota_share','surplus')
                                           THEN rr.sum_insured ELSE 0 END) * 1.0)
                                 / SUM(rr.sum_insured)
                            ELSE 0 END AS ceded_rate
                FROM policy_reinsurance_regulatory rr
                WHERE rr.deleted_at IS NULL
                GROUP BY rr.action_id, rr.risk_address, rr.group_code, rr.regulatory_class
            ) u ON u.action_id = d.action_id
               -- BOTH SIDES CAST, and not for tidiness. risk_address is a
               -- varchar on the regulatory table and risk_address_id an integer
               -- on the staged one, so an untyped comparison leans on MySQL
               -- coercing '1' to 1 -- the same implicit-coercion trap as
               -- IF(TRIM(type_id)=3, ...) elsewhere in this codebase, which
               -- silently returns nothing on any engine that does not coerce.
               --
               -- Written out rather than <=> because that is MySQL only, and a
               -- risk address is null on classes that do not carry one: those
               -- units must still join.
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
            WHERE cr.date BETWEEN ? AND ?
              AND " . self::NOT_VOIDED . "
            GROUP BY u.regulatory_class, u.group_code
        ", [$p['start'], $p['end']]);

        return $this->shape($rows, $treaty);
    }

    /**
     * Claims that breached the cash loss limit — BR-CLM-05, BR-CLM-06, BR-ACC-08.
     *
     * A CASH LOSS IS MONEY WE WERE ENTITLED TO ASK FOR EARLY. Above the limit
     * the Reinsured may demand payment rather than wait for the quarterly
     * account: on demand for General, provided Annexure A is completed, and
     * within five working days for Motor. A breach nobody acted on is the
     * reinsurer's share of a large claim financed out of our own cash for up to
     * a quarter and a half — 45 days to render, 14 to confirm.
     *
     * THE TWO TREATIES MEASURE IT DIFFERENTLY, and it is the same distinction
     * the participation basis draws. BR-CLM-05 sets General's 500,000 "for 100%
     * of the treaty", so it is tested against the GROSS claim. BR-CLM-06 sets
     * Motor's 250,000 "for the ceded portion", so it is tested against what
     * reinsurers actually owe. Testing both on one basis would either miss
     * Motor breaches or invent General ones.
     *
     * THIS FINDS ENTITLEMENTS, NOT RECOVERIES. BR-ACC-08 puts cash loss
     * RECOVERIES on the account — cash already received, deducted so a
     * reinsurer is not asked to pay the same claim twice. Nothing in this system
     * records that a demand was made or paid: there is no cash loss register,
     * the same gap RI-02 records against the letter of credit register. Until
     * there is one, this reports what could have been demanded and the statement
     * carries no recovery line, because inventing one would credit reinsurers
     * with money they never sent.
     *
     * @return array<int,array{claim_id:int,gross_paid:float,ceded_paid:float,
     *                          basis:string,limit:float,excess:float}>
     */
    public function cashLossCandidatesFor(string $treaty, int $underwritingYear, int $quarter): array
    {
        $this->guardBasis();

        $p = TreatyStatement::periodFor($underwritingYear, $quarter);
        $t = strtolower(trim($treaty));

        $limit = config("reinsurance.terms.{$t}.cash_loss_limit");

        if ($limit === null) {
            throw new RuntimeException(
                "No cash loss limit is configured for the '{$treaty}' treaty."
            );
        }

        // Measured on the gross claim for General and on the ceded portion for
        // Motor. See the note above.
        $onCession = $t === 'motor';

        $rows = DB::select("
            SELECT cr.claim_id,
                   SUM(crc.payment_amt) AS gross_paid,
                   SUM(crc.payment_amt * u.ceded_rate) AS ceded_paid,
                   u.group_code
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
            JOIN (
                SELECT rr.action_id, rr.risk_address, rr.group_code, rr.regulatory_class,
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
            WHERE cr.date BETWEEN ? AND ?
              AND " . self::NOT_VOIDED . "
            GROUP BY cr.claim_id, u.group_code
        ", [$p['start'], $p['end']]);

        $byClaim = [];

        foreach ($rows as $r) {
            if (! TreatyStatement::coversGroup($treaty, $r->group_code)) {
                continue;
            }

            $id = (int) $r->claim_id;
            $byClaim[$id]['gross_paid'] = round(
                ($byClaim[$id]['gross_paid'] ?? 0.0) + (float) $r->gross_paid, 2
            );
            $byClaim[$id]['ceded_paid'] = round(
                ($byClaim[$id]['ceded_paid'] ?? 0.0) + (float) $r->ceded_paid, 2
            );
        }

        $out = [];

        foreach ($byClaim as $id => $f) {
            $tested = $onCession ? $f['ceded_paid'] : $f['gross_paid'];

            if ($tested <= (float) $limit) {
                continue;
            }

            $out[] = [
                'claim_id'   => $id,
                'gross_paid' => $f['gross_paid'],
                'ceded_paid' => $f['ceded_paid'],
                'basis'      => $onCession ? 'ceded portion' : '100% of the treaty',
                'limit'      => (float) $limit,
                'excess'     => round($tested - (float) $limit, 2),
            ];
        }

        usort($out, static fn ($a, $b) => $b['excess'] <=> $a['excess']);

        return $out;
    }

    /**
     * Cash already received from reinsurers, to be deducted — BR-ACC-08.
     *
     * A cash loss is money demanded and paid AHEAD of the quarterly account
     * (BR-CLM-05, BR-CLM-06). When the quarter is then made up, that claim
     * appears in claims paid at its full ceded share, so the cash already
     * received has to come off — or the reinsurer is billed twice for one loss,
     * and the statement foots perfectly while doing it.
     *
     * ON received_on, NOT demanded_on. The demand may sit in one quarter and the
     * money arrive in the next, and it is the money that belongs on the account.
     * A demand nobody paid is a debt owed to us, not a recovery: deducting it
     * would relieve reinsurers of money they never sent.
     *
     * DEDUCTED ONCE. A recovery already taken onto a DIFFERENT statement is
     * skipped and reported, because a second deduction is indistinguishable from
     * the first on the face of an account.
     *
     * @return array{total:float,rows:int,skipped:int,by_claim:array<int,float>}
     */
    public function cashLossRecoveriesFor(string $treaty, int $underwritingYear, int $quarter): array
    {
        $this->guardBasis();

        if (! Schema::hasTable('treaty_cash_loss_recoveries')) {
            return ['total' => 0.0, 'rows' => 0, 'skipped' => 0, 'by_claim' => []];
        }

        $p = TreatyStatement::periodFor($underwritingYear, $quarter);

        $rows = TreatyCashLossRecovery::query()
            ->whereRaw('LOWER(TRIM(treaty)) = ?', [strtolower(trim($treaty))])
            ->where('underwriting_year', $underwritingYear)
            ->receivedBetween($p['start'], $p['end'])
            ->get();

        $total = 0.0;
        $byClaim = [];
        $skipped = 0;

        foreach ($rows as $r) {
            // Already taken onto another quarter's account: leave it there.
            if ($r->isAccounted()
                && ((int) $r->accounted_year !== $underwritingYear
                    || (int) $r->accounted_quarter !== $quarter)) {
                $skipped++;

                continue;
            }

            $total += (float) $r->amount_received;
            $byClaim[(int) $r->claim_id] = round(
                ($byClaim[(int) $r->claim_id] ?? 0.0) + (float) $r->amount_received,
                2
            );
        }

        return [
            'total'    => round($total, 2),
            'rows'     => count($byClaim),
            'skipped'  => $skipped,
            'by_claim' => $byClaim,
        ];
    }

    /**
     * Claim rows in the period that reach no cession at all.
     *
     * REPORTED, NOT DROPPED. A claim on a policy the treaty never took is
     * correctly absent from the statement; a claim that fails to join because
     * its coverage detail is missing is a fault. The two look identical in a
     * total, so the count is returned and a statement carrying many of them is
     * one somebody should look at.
     */
    public function unmatchedFor(string $treaty, int $underwritingYear, int $quarter): int
    {
        $this->guardBasis();

        $p = TreatyStatement::periodFor($underwritingYear, $quarter);

        $row = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM claim_reserves_coverages crc
            JOIN claim_reserves cr ON cr.id = crc.reserve_id
            WHERE cr.date BETWEEN ? AND ?
              AND " . self::NOT_VOIDED . "
              -- THE WHOLE CHAIN, NOT JUST THE FIRST LINK. This used to ask only
              -- whether a detail row existed for the coverage, which is the
              -- easiest of the three joins to satisfy: a claim could have a
              -- detail row, reach no regulatory allocation through it, be
              -- dropped from the figures, and still be counted as matched. The
              -- count then said nothing was missing while money was.
              AND NOT EXISTS (
                  SELECT 1
                  FROM policy_reinsurance_details d
                  JOIN (
                      SELECT rr.action_id, rr.risk_address, rr.group_code, rr.regulatory_class
                      FROM policy_reinsurance_regulatory rr
                      WHERE rr.deleted_at IS NULL
                      GROUP BY rr.action_id, rr.risk_address, rr.group_code, rr.regulatory_class
                  ) u ON u.action_id = d.action_id
                     AND (CAST(u.risk_address AS CHAR) = CAST(d.risk_address_id AS CHAR)
                          OR (u.risk_address IS NULL AND d.risk_address_id IS NULL))
                  JOIN reinsurance_group g
                    ON g.id = d.group_id
                   AND (
                         (u.group_code IS NOT NULL AND TRIM(u.group_code) <> ''
                              AND g.group_code = u.group_code)
                      OR ((u.group_code IS NULL OR TRIM(u.group_code) = '')
                              AND TRIM(COALESCE(g.regulatory_mapping, '')) = u.regulatory_class)
                       )
                  WHERE d.pocoverage_detail_id = crc.coverage_id
              )
        ", [$p['start'], $p['end']]);

        return (int) ($row->n ?? 0);
    }

    /**
     * Write the claim lines onto a statement.
     *
     * SIGNS: claims paid are NEGATIVE — money coming back from reinsurers.
     * Salvages and recoveries are written positive as memorandum lines and are
     * excluded from the balance, because they are already inside the net figure.
     *
     * @return array<int,TreatyStatementItem>
     */
    public function writeItems(TreatyStatement $statement): array
    {
        $claims = $this->claimsFor(
            $statement->treaty,
            (int) $statement->underwriting_year,
            (int) $statement->quarter
        );

        $cashLoss = $this->cashLossRecoveriesFor(
            $statement->treaty,
            (int) $statement->underwriting_year,
            (int) $statement->quarter
        );

        $written = [];

        DB::transaction(function () use ($statement, $claims, $cashLoss, &$written) {
            $statement->items()->whereIn('item_type', [
                TreatyStatementItem::CLAIMS_PAID,
                TreatyStatementItem::SALVAGES,
                TreatyStatementItem::RECOVERIES,
                TreatyStatementItem::CASH_LOSS_RECOVERY,
            ])->delete();

            /*
             * CASH LOSS RECOVERIES ARE POSITIVE — BR-ACC-08.
             *
             * Claims paid are negative, being money coming back from
             * reinsurers. Cash they already sent ahead of the quarter reduces
             * what is still owed, so it runs the other way. Without it the
             * reinsurer is billed a second time for a loss they have already
             * funded, and the account foots either way.
             */
            if (abs($cashLoss['total']) >= 0.005) {
                $written[] = $statement->items()->create([
                    'item_type' => TreatyStatementItem::CASH_LOSS_RECOVERY,
                    'amount'    => $cashLoss['total'],
                    'note'      => sprintf(
                        'Cash received on demand ahead of this account, across %d claim(s).',
                        $cashLoss['rows']
                    ),
                ]);

                // Stamp which account took them, so a recovery is deducted once
                // and can be traced to the statement that deducted it.
                if (Schema::hasTable('treaty_cash_loss_recoveries')) {
                    $p = TreatyStatement::periodFor(
                        (int) $statement->underwriting_year,
                        (int) $statement->quarter
                    );

                    TreatyCashLossRecovery::query()
                        ->whereRaw('LOWER(TRIM(treaty)) = ?', [strtolower(trim((string) $statement->treaty))])
                        ->where('underwriting_year', (int) $statement->underwriting_year)
                        ->receivedBetween($p['start'], $p['end'])
                        ->whereNull('accounted_year')
                        ->update([
                            'accounted_year'    => (int) $statement->underwriting_year,
                            'accounted_quarter' => (int) $statement->quarter,
                        ]);
                }
            }

            foreach ($claims['by_class'] as $class => $f) {
                $net = round($f['ceded_paid'] - $f['ceded_salvages'] - $f['ceded_recoveries'], 2);

                $written[] = $statement->items()->create([
                    'item_type'        => TreatyStatementItem::CLAIMS_PAID,
                    'regulatory_class' => $class,
                    'amount'           => -1 * $net,
                    'basis_amount'     => $f['gross_paid'],
                ]);

                foreach ([
                    TreatyStatementItem::SALVAGES   => $f['ceded_salvages'],
                    TreatyStatementItem::RECOVERIES => $f['ceded_recoveries'],
                ] as $type => $amount) {
                    if (abs($amount) < 0.005) {
                        continue;
                    }
                    $written[] = $statement->items()->create([
                        'item_type'        => $type,
                        'regulatory_class' => $class,
                        'amount'           => $amount,
                    ]);
                }
            }
        });

        return $written;
    }

    /** @param array<int,object> $rows */
    private function shape(array $rows, string $treaty): array
    {
        $byClass = [];
        $n = 0;

        foreach ($rows as $r) {
            // A STATEMENT CARRIES ITS OWN TREATY'S BUSINESS, AND THE SPLIT IS BY
            // GROUP. RI-05 lists the two treaties by group, not by regulatory
            // class — the class is what a statement REPORTS by (BR-RPT-02), and
            // several groups share one. An earlier version split on the class
            // and so put every MOTOR_* group in one bucket and everything else
            // in the other, which is right by accident on this book and wrong as
            // soon as a group's class does not match its treaty.
            if (! TreatyStatement::coversGroup($treaty, $r->group_code)) {
                continue;
            }

            // ACCUMULATED, NOT ASSIGNED. The query now groups by class AND
            // group, so two groups sharing a class arrive as two rows; a plain
            // assignment would keep the last and silently drop the rest.
            $class = (string) $r->regulatory_class;
            $f = $byClass[$class] ?? [
                'gross_paid' => 0.0, 'salvages' => 0.0, 'recoveries' => 0.0,
                'ceded_paid' => 0.0, 'ceded_salvages' => 0.0, 'ceded_recoveries' => 0.0,
            ];

            $byClass[$class] = [
                'gross_paid'       => round($f['gross_paid']       + (float) $r->gross_paid, 2),
                'salvages'         => round($f['salvages']         + (float) $r->salvages, 2),
                'recoveries'       => round($f['recoveries']       + (float) $r->recoveries, 2),
                'ceded_paid'       => round($f['ceded_paid']       + (float) $r->ceded_paid, 2),
                'ceded_salvages'   => round($f['ceded_salvages']   + (float) $r->ceded_salvages, 2),
                'ceded_recoveries' => round($f['ceded_recoveries'] + (float) $r->ceded_recoveries, 2),
            ];
            $n += (int) $r->n;
        }

        ksort($byClass);

        $cededNet = 0.0;
        foreach ($byClass as $f) {
            $cededNet += $f['ceded_paid'] - $f['ceded_salvages'] - $f['ceded_recoveries'];
        }

        return [
            'by_class'   => $byClass,
            'gross_paid' => round(array_sum(array_column($byClass, 'gross_paid')), 2),
            'ceded_net'  => round($cededNet, 2),
            'rows'       => $n,
        ];
    }
}
