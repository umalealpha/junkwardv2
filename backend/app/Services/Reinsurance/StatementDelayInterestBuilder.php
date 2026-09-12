<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Delay in payment interest — RI-18 step 11, BR-ACC-10.
 *
 * "Delay in payment interest at 110% of the market prime lending rate on
 * balances due, from due date to date of payment, on overdue balances."
 *
 * WHICH DUE DATE DEPENDS ON WHO OWES, and this is the part that is easy to get
 * backwards. BR-ACC-09 settles the two directions differently: the cedant pays
 * ON A CHEQUE ATTACHED BASIS, at the same time the accounts are rendered, so a
 * balance due TO reinsurers is due on the render date. Reinsurers pay at the
 * same time as the accounts are CONFIRMED, so a balance due FROM them runs from
 * the confirmation date. Using one date for both would charge us interest from a
 * date we never owed anything, or let a reinsurer sit on our money for the 45
 * days we had to prepare the account.
 *
 * THE MULTIPLE IS OURS AND THE RATE IS NOT. 110% is a treaty term and lives in
 * config; the market prime lending rate moves and is not ours to invent. It is
 * stored as effective-dated entries because interest runs across a period and a
 * rate that changed in the middle of it changes the answer — a single "current
 * rate" would silently apply today's number to last quarter's delay.
 *
 * NO RATE, NO INTEREST, AND IT SAYS SO. Guessing prime would charge a reinsurer
 * money on a rate nobody published. The builder reports that it is unconfigured
 * rather than accruing zero, because zero reads as "paid on time".
 *
 * ACTUAL/365. Neither the slips nor Article 10.5 state a day-count convention.
 * Actual days over a 365-day year is the Botswana money-market norm and the
 * simpler of the candidates to check by hand; 360 would pay about 1.4% more
 * interest. A CONVENTION, recorded as one.
 */
class StatementDelayInterestBuilder
{
    /** BR-ACC-10. The multiple of prime, not the rate itself. */
    public const PRIME_MULTIPLE = 1.10;

    /** Days in a year for the accrual. See the note above. */
    public const DAY_COUNT = 365;

    /**
     * The market prime lending rate on a date.
     *
     * EFFECTIVE-DATED, because interest runs across a period. Config holds a
     * list of ['from' => 'Y-m-d', 'pct' => 6.5]; the rate that applies is the
     * latest one starting on or before the date asked for.
     */
    public function primeRateOn(string $date): ?float
    {
        $rates = (array) config('reinsurance.terms.prime_rate', []);

        if ($rates === []) {
            return null;
        }

        $asAt = strtotime(substr($date, 0, 10));
        $best = null;
        $bestFrom = null;

        foreach ($rates as $r) {
            $from = strtotime((string) ($r['from'] ?? ''));

            if ($from === false || $from > $asAt) {
                continue;
            }

            if ($bestFrom === null || $from > $bestFrom) {
                $bestFrom = $from;
                $best     = (float) ($r['pct'] ?? 0);
            }
        }

        return $best;
    }

    /**
     * The interest rate charged on a delay: 110% of prime.
     *
     * @return array{configured:bool,prime_pct:float|null,multiple:float,rate_pct:float|null}
     */
    public function rateOn(string $date): array
    {
        $prime = $this->primeRateOn($date);

        return [
            'configured' => $prime !== null,
            'prime_pct'  => $prime,
            'multiple'   => self::PRIME_MULTIPLE,
            'rate_pct'   => $prime === null ? null : round($prime * self::PRIME_MULTIPLE, 6),
        ];
    }

    /**
     * The balance interest is charged ON, which excludes interest already charged.
     *
     * BR-ACC-10 IS A SIMPLE RATE ON "BALANCES DUE", and the balance due is the
     * account — premium, commission, claims and the rest — not the account plus
     * the interest we last wrote onto it. Reading balance() instead compounds on
     * every rebuild: a first run wrote 542.47, and a second charged 30 days on
     * 100,542.47 and produced 545.41. Excluding the line here rather than
     * deleting it first makes the base correct regardless of call order.
     */
    public function baseBalanceFor(TreatyStatement $statement): float
    {
        return round((float) $statement->items()
            ->settling()
            ->where('item_type', '!=', TreatyStatementItem::DELAY_INTEREST)
            ->sum('amount'), 2);
    }

    /**
     * A due date is a date.
     *
     * sqlite hands a date column back with a time component and MySQL may not.
     * Comparing or printing one against the other would differ by engine, and
     * this figure decides when interest starts running.
     */
    private function dateOnly(?string $v): ?string
    {
        return ($v === null || $v === '') ? null : substr($v, 0, 10);
    }

    /**
     * When the balance on a statement fell due, and who owed it.
     *
     * @return array{owed_by:string,due:string|null,reason:string}
     */
    public function dueDateFor(TreatyStatement $statement): array
    {
        $balance = $this->baseBalanceFor($statement);

        if ($balance >= 0) {
            return [
                'owed_by' => 'cedant',
                'due'     => $this->dateOnly($statement->getRawOriginal('render_due')),
                'reason'  => 'Due to Reinsurers. BR-ACC-09 settles from the cedant on a cheque '
                           . 'attached basis at the same time the accounts are rendered.',
            ];
        }

        return [
            'owed_by' => 'reinsurer',
            'due'     => $this->dateOnly($statement->getRawOriginal('confirm_due')),
            'reason'  => 'Due from Reinsurers. BR-ACC-09 settles from the Reinsurer at the same '
                       . 'time the accounts are confirmed.',
        ];
    }

    /**
     * Interest on an overdue balance, to a date of payment.
     *
     * @return array{
     *     overdue:bool, days:int, amount:float, balance:float, owed_by:string,
     *     due:string|null, paid_on:string, rate:array<string,mixed>, note:string
     * }
     */
    public function interestFor(TreatyStatement $statement, ?string $paidOn = null): array
    {
        $paidOn  = $paidOn ?: date('Y-m-d');
        $balance = $this->baseBalanceFor($statement);
        $due     = $this->dueDateFor($statement);

        $base = [
            'balance' => $balance,
            'owed_by' => $due['owed_by'],
            'due'     => $due['due'],
            'paid_on' => $paidOn,
            'rate'    => $this->rateOn($paidOn),
        ];

        // A balance with no due date is not overdue — it is unmeasurable. For a
        // reinsurer balance that means the account has not been confirmed yet,
        // which is a different thing from being late.
        if ($due['due'] === null || $due['due'] === '') {
            return $base + [
                'overdue' => false, 'days' => 0, 'amount' => 0.0,
                'note' => 'No due date: ' . ($due['owed_by'] === 'reinsurer'
                    ? 'the account has not been rendered, so the confirmation date is not set.'
                    : 'the statement carries no render date.'),
            ];
        }

        $days = (int) floor(
            (strtotime(substr($paidOn, 0, 10)) - strtotime(substr($due['due'], 0, 10))) / 86400
        );

        if ($days <= 0) {
            return $base + [
                'overdue' => false, 'days' => 0, 'amount' => 0.0,
                'note' => 'Settled on or before the due date. No interest arises.',
            ];
        }

        if (! $base['rate']['configured']) {
            // OVERDUE BUT UNPRICEABLE. Reported as such rather than as zero,
            // because zero on an overdue balance reads as "paid on time".
            return $base + [
                'overdue' => true, 'days' => $days, 'amount' => 0.0,
                'note' => sprintf(
                    'Overdue by %d days, but no market prime lending rate is on file for %s, so '
                    . 'BR-ACC-10 interest cannot be computed. Interest IS due; this is not nil.',
                    $days,
                    $paidOn
                ),
            ];
        }

        $amount = round(
            abs($balance) * ($base['rate']['rate_pct'] / 100.0) * ($days / self::DAY_COUNT),
            2
        );

        return $base + [
            'overdue' => true,
            'days'    => $days,
            'amount'  => $amount,
            'note'    => sprintf(
                '%d days at %s%% (110%% of a %s%% prime), actual/365.',
                $days,
                $base['rate']['rate_pct'],
                $base['rate']['prime_pct']
            ),
        ];
    }

    /**
     * Write the delay interest line.
     *
     * SIGNED THE WAY THE DEBT RUNS. Interest on a balance we owe is more money
     * out and carries the balance's own sign; interest on a balance a reinsurer
     * owes runs the other way. Taking the sign from the balance rather than
     * fixing it means a statement that flips direction cannot silently invert
     * its interest.
     *
     * IT IS WRITTEN LAST AND EXCLUDED FROM ITS OWN BASE. The balance is read
     * before the line is written, so rebuilding never charges interest on
     * interest — which would compound a figure BR-ACC-10 states as simple.
     */
    public function writeItems(TreatyStatement $statement, ?string $paidOn = null): array
    {
        $i = $this->interestFor($statement, $paidOn);

        DB::transaction(function () use ($statement, $i) {
            $statement->items()
                ->where('item_type', TreatyStatementItem::DELAY_INTEREST)
                ->delete();

            if (abs($i['amount']) < 0.005) {
                return;
            }

            $statement->items()->create([
                'item_type'    => TreatyStatementItem::DELAY_INTEREST,
                'amount'       => ($i['balance'] >= 0 ? 1 : -1) * $i['amount'],
                'basis_amount' => abs($i['balance']),
                'rate'         => $i['rate']['rate_pct'] / 100.0,
                'note'         => $i['note'],
            ]);
        });

        return $i;
    }

    /**
     * Every rendered statement carrying an overdue balance.
     *
     * @return array<int,array<string,mixed>>
     */
    public function overdueAsAt(?string $asAt = null): array
    {
        $asAt = $asAt ?: date('Y-m-d');
        $out  = [];

        $statements = TreatyStatement::whereNotIn('status', [
            TreatyStatement::STATUS_SETTLED,
        ])->get();

        foreach ($statements as $s) {
            $i = $this->interestFor($s, $asAt);

            if ($i['overdue']) {
                $out[] = [
                    'statement' => $s->id,
                    'treaty'    => $s->treaty,
                    'year'      => (int) $s->underwriting_year,
                    'quarter'   => (int) $s->quarter,
                ] + $i;
            }
        }

        return $out;
    }
}
