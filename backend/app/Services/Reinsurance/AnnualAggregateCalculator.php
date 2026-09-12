<?php

namespace AlphaDirect\Services\Reinsurance;

use InvalidArgumentException;

/**
 * The three annual aggregates, and the erosion that makes them different from an
 * event limit.
 *
 * An event limit resets. A hundred separate windstorms each recover up to
 * 70,000,000, because the cap is per occurrence. An ANNUAL AGGREGATE does not
 * reset: it is a pot for the whole underwriting year, and every recovery takes
 * from it. Once it is empty the treaty pays nothing further on that peril until
 * 1 July, however severe the next loss is.
 *
 *   20,000,000   General   SRCC, risks situated in Zimbabwe        BR-CAP-16
 *    6,000,000   General   War and Civil War, Goods in Transit,
 *                          all territories                          BR-CAP-17
 *   50,000,000   Motor     Riot and Strike                          BR-CAP-15
 *
 * MOTOR RIOT IS THE ONE TO UNDERSTAND. Its event limit is 50,000,000 and its
 * annual aggregate is ALSO 50,000,000, so a single riot at the full event limit
 * exhausts the treaty's riot cover for the rest of the year. That is not a
 * misreading of the slip — it is what a 50,000,000 aggregate on a 50,000,000
 * event limit means, and it is the sort of thing nobody notices until the second
 * event. See test_a_full_motor_riot_event_exhausts_the_year.
 *
 * ORDER OF APPLICATION. The event limit is applied first and the aggregate caps
 * what survives it. Reversing them recovers more than the treaty allows on any
 * occurrence large enough to breach both.
 *
 * ORDER OF OCCURRENCES IS NOT COSMETIC. Erosion is path dependent: with
 * 6,000,000 left and two 4,000,000 occurrences, the first recovers 4,000,000 and
 * the second 2,000,000. Which is which depends entirely on date order, so
 * runYear() sorts before it walks and refuses occurrences outside the year.
 *
 * PURE. No database, no container, no side effects — the same shape as
 * EventLimitCalculator and RegulatoryCessionCalculator, and for the same reason:
 * these figures reach a solvency return, so they must be reproducible from their
 * inputs alone. Erosion is passed IN as an opening balance and handed BACK as a
 * closing one; nothing here remembers anything between calls. That also means
 * the year can always be recomputed from the loss record rather than trusted to
 * a running total that may have drifted.
 *
 * Sources: J.B. Boda General Quota Share 2026/27 amended signed slip; Motor
 * Quota Share 2026/27 Continental Re lead signed slip; RI-01 §6.3 BR-CAP-15 to
 * 17; RI-12 §8.
 */
class AnnualAggregateCalculator
{
    /**
     * The treaty year. Both slips run 1 July to 30 June, and an aggregate is a
     * property of the YEAR, so a loss outside it erodes nothing here.
     */
    public const YEAR_STARTS_MONTH = 7;

    /**
     * The three aggregates.
     *
     * `perils` is matched on whole words, not substrings — see matchesAny(). A
     * plain str_contains('war') matches "warehouse", which would erode the
     * 6,000,000 War aggregate on an ordinary warehouse fire and leave nothing for
     * an actual war loss.
     *
     * A null `territories` or `coverages` means the aggregate does not filter on
     * that dimension at all: the War aggregate is expressly "all territories".
     */
    public const AGGREGATES = [
        'srcc_zimbabwe' => [
            'treaty'      => 'general',
            'limit'       => 20000000.0,
            'perils'      => ['srcc', 'strike', 'strikes', 'riot', 'riots',
                              'civil commotion', 'malicious damage'],
            'territories' => ['zimbabwe', 'zw'],
            'coverages'   => null,
            'label'       => 'SRCC — risks situated in Zimbabwe',
            'source'      => 'General slip, BR-CAP-16',
        ],
        'war_goods_in_transit' => [
            'treaty'      => 'general',
            'limit'       => 6000000.0,
            'perils'      => ['war', 'civil war'],
            'territories' => null,
            'coverages'   => ['goods in transit', 'git'],
            'label'       => 'War and Civil War — Goods in Transit, all territories',
            'source'      => 'General slip, BR-CAP-17',
        ],
        'motor_riot_strike' => [
            'treaty'      => 'motor',
            'limit'       => 50000000.0,
            'perils'      => ['riot', 'riots', 'strike', 'strikes', 'srcc',
                              'civil commotion', 'malicious damage'],
            'territories' => null,
            'coverages'   => null,
            'label'       => 'Riot and Strike',
            'source'      => 'Motor slip, BR-CAP-15',
        ],
    ];

    /** @var array<string,float> */
    private array $limits;

    /** @param array<string,float> $limits overrides, keyed as AGGREGATES */
    public function __construct(array $limits = [])
    {
        foreach (array_keys($limits) as $key) {
            if (! array_key_exists($key, self::AGGREGATES)) {
                throw new InvalidArgumentException("Unknown annual aggregate '{$key}'.");
            }
        }

        $defaults = array_map(fn ($a) => $a['limit'], self::AGGREGATES);
        $this->limits = $limits + $defaults;

        foreach ($this->limits as $key => $value) {
            if (! is_numeric($value) || $value < 0) {
                throw new InvalidArgumentException(
                    "Annual aggregate for {$key} must be a non-negative number."
                );
            }
        }
    }

    // ───────────────────────────────────────────────────────── the definitions

    /** @return array<string,mixed> */
    public function definition(string $key): array
    {
        if (! array_key_exists($key, self::AGGREGATES)) {
            throw new InvalidArgumentException("Unknown annual aggregate '{$key}'.");
        }

        return ['key' => $key] + self::AGGREGATES[$key] + ['limit' => $this->limits[$key]];
    }

    /**
     * The aggregates that belong to a treaty.
     *
     * @return string[]
     */
    public function keysFor(string $treaty): array
    {
        $t = strtolower(trim($treaty));

        return array_keys(array_filter(
            self::AGGREGATES,
            fn ($a) => $a['treaty'] === $t
        ));
    }

    /** The whole limit, before any erosion. */
    public function limitFor(string $key): float
    {
        $this->definition($key);

        return (float) $this->limits[$key];
    }

    /** What is left of an aggregate after the erosion supplied. */
    public function remaining(string $key, float $eroded): float
    {
        if ($eroded < 0.0) {
            throw new InvalidArgumentException('Erosion cannot be negative.');
        }

        return round(max($this->limitFor($key) - $eroded, 0.0), 2);
    }

    // ───────────────────────────────────────────────────────── does it bite?

    /**
     * Whether an aggregate catches an occurrence, and whether we are sure.
     *
     * UNKNOWN TERRITORY IS TREATED AS CAUGHT, AND FLAGGED. Where the peril
     * matches but no territory is recorded, the SRCC aggregate is applied and
     * `uncertain` is set. That is deliberate and it is the direction that errs
     * safely: assuming the aggregate does NOT bite would recover more than the
     * treaty might allow and overstate the reinsurance asset on the return.
     * Assuming it does bite understates a recovery we can correct once the
     * territory is confirmed. The flag exists so it IS corrected rather than
     * quietly carried.
     *
     * @param  array<string,mixed>  $occurrence
     * @return array{applies:bool,uncertain:bool,reason:string|null}
     */
    public function appliesTo(string $key, array $occurrence): array
    {
        $def  = $this->definition($key);
        $none = ['applies' => false, 'uncertain' => false, 'reason' => null];

        if (! $this->matchesAny($this->valuesOf($occurrence, ['peril', 'perils']), $def['perils'])) {
            return $none;
        }

        $uncertain = false;
        $reasons   = [];

        foreach ([
            'territories' => ['territory', 'territories', 'country', 'countries'],
            'coverages'   => ['coverage', 'coverages', 'coverage_name'],
        ] as $dimension => $fields) {
            if ($def[$dimension] === null) {
                continue;   // the aggregate does not filter on this dimension
            }

            $values = $this->valuesOf($occurrence, $fields);

            if (! $values) {
                $uncertain = true;
                $reasons[] = sprintf(
                    'No %s recorded, so "%s" could not be tested. The aggregate has been '
                    . 'applied — confirm the %s and recompute.',
                    rtrim($dimension, 's'),
                    $def['label'],
                    rtrim($dimension, 's')
                );
                continue;
            }

            if (! $this->matchesAny($values, $def[$dimension])) {
                return $none;
            }
        }

        return [
            'applies'   => true,
            'uncertain' => $uncertain,
            'reason'    => $reasons ? implode(' ', $reasons) : null,
        ];
    }

    /**
     * Every aggregate on a treaty that catches this occurrence.
     *
     * @param  array<string,mixed>  $occurrence
     * @return array<string,array{applies:bool,uncertain:bool,reason:string|null}>
     */
    public function aggregatesFor(array $occurrence, string $treaty): array
    {
        $out = [];

        foreach ($this->keysFor($treaty) as $key) {
            $verdict = $this->appliesTo($key, $occurrence);
            if ($verdict['applies']) {
                $out[$key] = $verdict;
            }
        }

        return $out;
    }

    // ───────────────────────────────────────────────────────── applying it

    /**
     * Apply the aggregates to one occurrence against an opening erosion.
     *
     * The figure it caps is what the EVENT LIMIT already allowed — occurrences
     * off EventLimitCalculator::applyLimit() carry that as ceded_recovered.
     * Where there is none, ceded_sought is used and the occurrence is treated as
     * uncapped by any event limit.
     *
     * WHERE TWO AGGREGATES BITE, the smaller remaining decides the recovery and
     * BOTH erode by what was actually recovered. No pair of the three overlaps
     * today — one is Motor and two are General on different perils — but a
     * silent first-match-wins would be wrong the day one is added.
     *
     * @param  array<string,mixed>  $occurrence
     * @param  array<string,float>  $eroded   opening erosion, keyed as AGGREGATES
     * @return array<string,mixed>
     */
    public function apply(array $occurrence, string $treaty, array $eroded = []): array
    {
        $caught = $this->aggregatesFor($occurrence, $treaty);

        // What the event limit left. Occurrences that never met an event limit
        // fall back to what was sought.
        $beforeAggregate = array_key_exists('ceded_recovered', $occurrence)
            ? (float) $occurrence['ceded_recovered']
            : (float) ($occurrence['ceded_sought'] ?? 0.0);

        if ($beforeAggregate < 0.0) {
            throw new InvalidArgumentException('A ceded recovery cannot be negative.');
        }

        // array_merge, NOT the + operator: an occurrence off applyLimit ALREADY
        // carries ceded_recovered, and + keeps the key it already has. The
        // aggregate would then compute a cap and hand back the uncapped figure.
        $out = array_merge($occurrence, ['treaty' => $treaty]);

        if (! $caught) {
            return array_merge($out, [
                'aggregates'                => [],
                'ceded_before_aggregate'    => round($beforeAggregate, 2),
                'ceded_recovered'           => round($beforeAggregate, 2),
                'aggregate_declined'        => 0.0,
                'aggregate_exhausted'       => false,
                'aggregate_uncertain'       => false,
                'erosion_after'             => $this->normalise($eroded),
            ]);
        }

        $headroom = null;
        foreach (array_keys($caught) as $key) {
            $left     = $this->remaining($key, (float) ($eroded[$key] ?? 0.0));
            $headroom = $headroom === null ? $left : min($headroom, $left);
        }

        $recovered = round(min($beforeAggregate, (float) $headroom), 2);
        $declined  = round($beforeAggregate - $recovered, 2);

        $after   = $this->normalise($eroded);
        $detail  = [];
        $exhaust = false;
        $unsure  = false;

        foreach ($caught as $key => $verdict) {
            $opening      = (float) ($eroded[$key] ?? 0.0);
            $after[$key]  = round($opening + $recovered, 2);
            $closingLeft  = $this->remaining($key, $after[$key]);
            $exhaust      = $exhaust || $closingLeft <= 0.0;
            $unsure       = $unsure || $verdict['uncertain'];

            $detail[$key] = [
                'label'             => self::AGGREGATES[$key]['label'],
                'limit'             => $this->limitFor($key),
                'opening_erosion'   => round($opening, 2),
                'eroded_by'         => $recovered,
                'closing_erosion'   => $after[$key],
                'remaining'         => $closingLeft,
                'exhausted'         => $closingLeft <= 0.0,
                'uncertain'         => $verdict['uncertain'],
                'exception'         => $verdict['reason'],
            ];
        }

        return array_merge($out, [
            'aggregates'             => $detail,
            'ceded_before_aggregate' => round($beforeAggregate, 2),
            'ceded_recovered'        => $recovered,
            'aggregate_declined'     => $declined,
            'aggregate_exhausted'    => $exhaust,
            'aggregate_uncertain'    => $unsure,
            'erosion_after'          => $after,
        ]);
    }

    /**
     * Walk a treaty year in date order, eroding as it goes.
     *
     * Sorted before it is walked, because erosion is path dependent and an
     * unsorted list silently pays the wrong occurrences. Occurrences outside the
     * year are REFUSED rather than skipped: one belonging to another year is a
     * data fault, and quietly dropping it produces a year that foots and is still
     * wrong.
     *
     * @param  array<int,array<string,mixed>>  $occurrences
     * @param  array<string,float>             $opening  brought-forward erosion
     * @return array{
     *     year:string,
     *     occurrences:array<int,array<string,mixed>>,
     *     closing_erosion:array<string,float>,
     *     aggregates:array<string,array<string,mixed>>,
     *     totals:array<string,float|int>
     * }
     */
    public function runYear(
        array $occurrences,
        string $treaty,
        int $yearStart,
        array $opening = []
    ): array {
        $from = mktime(0, 0, 0, self::YEAR_STARTS_MONTH, 1, $yearStart);
        $to   = mktime(0, 0, 0, self::YEAR_STARTS_MONTH, 1, $yearStart + 1) - 1;

        $dated = [];
        foreach ($occurrences as $i => $o) {
            $raw = $o['first_loss_at'] ?? $o['occurred_at'] ?? null;
            if ($raw === null) {
                throw new InvalidArgumentException(
                    "Occurrence {$i} has no first_loss_at or occurred_at, so it cannot be placed "
                    . 'in an underwriting year.'
                );
            }

            $at = strtotime((string) $raw);
            if ($at === false) {
                throw new InvalidArgumentException("Occurrence {$i} has an unreadable date '{$raw}'.");
            }
            if ($at < $from || $at > $to) {
                throw new InvalidArgumentException(sprintf(
                    'Occurrence %d falls on %s, outside the %d/%02d treaty year. An aggregate '
                    . 'belongs to one year and cannot be eroded by a loss from another.',
                    $i,
                    date('Y-m-d', $at),
                    $yearStart,
                    ($yearStart + 1) % 100
                ));
            }

            // Index carried so equal timestamps keep the order they arrived in.
            $dated[] = ['at' => $at, 'i' => $i, 'o' => $o];
        }

        usort($dated, fn ($a, $b) => $a['at'] <=> $b['at'] ?: $a['i'] <=> $b['i']);

        $eroded = $this->normalise($opening);
        $walked = [];

        foreach ($dated as $row) {
            $applied = $this->apply($row['o'], $treaty, $eroded);
            $eroded  = $applied['erosion_after'];
            $walked[] = $applied;
        }

        $sum = fn (string $k) => round(array_sum(array_map(
            fn ($o) => (float) ($o[$k] ?? 0),
            $walked
        )), 2);

        $summary = [];
        foreach ($this->keysFor($treaty) as $key) {
            $summary[$key] = [
                'label'     => self::AGGREGATES[$key]['label'],
                'source'    => self::AGGREGATES[$key]['source'],
                'limit'     => $this->limitFor($key),
                'opening'   => round((float) ($opening[$key] ?? 0.0), 2),
                'eroded'    => round($eroded[$key] - (float) ($opening[$key] ?? 0.0), 2),
                'closing'   => $eroded[$key],
                'remaining' => $this->remaining($key, $eroded[$key]),
                'exhausted' => $this->remaining($key, $eroded[$key]) <= 0.0,
            ];
        }

        return [
            'year'            => sprintf('%d/%02d', $yearStart, ($yearStart + 1) % 100),
            'treaty'          => strtolower(trim($treaty)),
            'occurrences'     => $walked,
            'closing_erosion' => $eroded,
            'aggregates'      => $summary,
            'totals'          => [
                'occurrences'            => count($walked),
                'ceded_before_aggregate' => $sum('ceded_before_aggregate'),
                'ceded_recovered'        => $sum('ceded_recovered'),
                'aggregate_declined'     => $sum('aggregate_declined'),
            ],
        ];
    }

    // ───────────────────────────────────────────────────────────── internals

    /**
     * Whole-word matching, deliberately not str_contains.
     *
     * "war" inside "warehouse" is the trap this exists to avoid: on substring
     * matching an ordinary warehouse fire would erode the 6,000,000 War
     * aggregate and leave nothing for a war loss. Multi-word needles like
     * "civil commotion" are matched as a phrase on the same boundaries.
     *
     * @param  string[]  $haystacks
     * @param  string[]  $needles
     */
    private function matchesAny(array $haystacks, array $needles): bool
    {
        foreach ($haystacks as $haystack) {
            $h = strtolower(trim($haystack));

            foreach ($needles as $needle) {
                $n = preg_quote(strtolower(trim($needle)), '/');
                if ($n !== '' && preg_match('/(?<![a-z0-9])' . $n . '(?![a-z0-9])/', $h) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The values of whichever of these fields the occurrence carries, flattened.
     *
     * Occurrences off EventLimitCalculator carry a single `peril`; a caller
     * building its own may carry a list. Both read the same way here.
     *
     * @param  array<string,mixed>  $occurrence
     * @param  string[]             $fields
     * @return string[]
     */
    private function valuesOf(array $occurrence, array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $occurrence) || $occurrence[$field] === null) {
                continue;
            }

            foreach ((array) $occurrence[$field] as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $out[] = $value;
                }
            }
        }

        return $out;
    }

    /**
     * Erosion for every aggregate, with anything unnamed at zero.
     *
     * @param  array<string,float>  $eroded
     * @return array<string,float>
     */
    private function normalise(array $eroded): array
    {
        $out = [];

        foreach (array_keys(self::AGGREGATES) as $key) {
            $value = (float) ($eroded[$key] ?? 0.0);
            if ($value < 0.0) {
                throw new InvalidArgumentException("Erosion for {$key} cannot be negative.");
            }
            $out[$key] = round($value, 2);
        }

        foreach (array_keys($eroded) as $key) {
            if (! array_key_exists($key, self::AGGREGATES)) {
                throw new InvalidArgumentException("Unknown annual aggregate '{$key}' in erosion.");
            }
        }

        return $out;
    }
}
