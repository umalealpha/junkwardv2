<?php

namespace AlphaDirect\Services\Reinsurance;

use InvalidArgumentException;

/**
 * The event limit, and the hours clause that decides what one event is.
 *
 * A quota share cedes 70% of every risk, but the reinsurers' liability is capped
 * per OCCURRENCE — 70,000,000 on both treaties. Without that cap the ceded
 * recovery on a catastrophe is reported as whatever the arithmetic gives, and on
 * a 150,000,000 event that overstates the reinsurance asset by 35,000,000 and
 * understates the net retention by the same.
 *
 * THE HOURS CLAUSE IS THE HARD PART, and it is why this is a calculator and not
 * a WHERE clause. Losses do not arrive labelled with an occurrence; the clause
 * defines one, and it defines it differently by peril:
 *
 *   72 hours    windstorm group; earthquake group
 *   72 hours    strike, riot, civil commotion and malicious damage — AND within
 *               the limits of one city, town or village
 *   75 hours    explosion, conflagration, firestorms, bush fires and any other
 *               fires — AND within an 80 kilometre radius. GENERAL ONLY: the
 *               Motor slip has no fire category, so fire there runs 168 hours
 *   168 hours   every other peril
 *
 * Taken from Note 6 of the Alpha Direct Capacities Table 2026/27, which quotes
 * both slips. Two of those conditions bound the window by PLACE as well as time,
 * and both were missed on the first pass: a riot is one occurrence only within
 * one town, and the fire group covers explosion and bush fire, not just "fire".
 *
 * The window is CONSECUTIVE and the cedant chooses where it starts — that is the
 * standard reading, and it is what makes this worth computing rather than
 * eyeballing: the placement of the window changes which losses fall inside it and
 * therefore what the treaty pays. This implementation takes the earliest
 * unassigned loss as each window's start, which is deterministic and never
 * splits a loss across two occurrences. It is NOT necessarily the placement most
 * favourable to the cedant; optimising that is a separate decision and would need
 * Reinsurance to say they want it.
 *
 * FIRE'S RADIUS. The 80 kilometre condition is territorial as well as temporal:
 * two fires 75 hours apart but 400 kilometres apart are two occurrences. Where
 * coordinates are supplied the distance is computed; where only a location key is
 * given, losses group by that key; where neither is present the radius cannot be
 * tested and the loss is grouped on time alone, with a flag on the occurrence
 * saying so. Silently treating unknown geography as "within radius" would merge
 * occurrences and understate what the treaty owes.
 *
 * PURE. No database, no container, no side effects — the same shape as
 * RegulatoryCessionCalculator, and for the same reason: the figures it produces
 * end up on a solvency return, so they have to be reproducible from their inputs
 * alone.
 *
 * Sources: J.B. Boda General Quota Share 2026/27 amended signed slip; Motor
 * Quota Share 2026/27 Continental Re lead signed slip; Alpha Direct Final Terms
 * 2026-27 (Prop sheet), which gives the surplus its own event limit of
 * 120,000,000 against the quota share's 70,000,000.
 */
class EventLimitCalculator
{
    /** The windstorm and earthquake groups. 72 hours, no locality condition. */
    public const PERILS_72 = ['windstorm', 'storm', 'earthquake'];

    /**
     * The strike group. 72 hours AND "within the limits of one city, town or
     * village" — Note 6. The locality condition is as binding as the clock: two
     * riots inside 72 hours in different towns are two occurrences.
     */
    public const PERILS_72_LOCAL = ['strike', 'riot', 'civil commotion', 'malicious damage', 'srcc'];

    /**
     * The fire group, GENERAL TREATY ONLY. Note 6 names "explosion,
     * conflagration, firestorms, bush fires and any other fires" — so explosion
     * and bush fire run 75 hours, not the 168-hour default.
     */
    public const PERILS_FIRE = ['fire', 'explosion', 'conflagration', 'firestorm', 'bush fire'];

    /** Fire runs 75 hours, and only within the radius below. */
    public const HOURS_FIRE = 75;

    /** Every other peril. */
    public const HOURS_DEFAULT = 168;

    public const HOURS_NAMED = 72;

    /** Kilometres. Fire losses further apart than this are separate occurrences. */
    public const FIRE_RADIUS_KM = 80.0;

    /**
     * Event limits per treaty, from the signed slips.
     *
     * The surplus carries its own, higher limit — 120,000,000 against the quota
     * share's 70,000,000 — so a Property loss running through both is capped
     * twice, once at each layer, not once at the lower figure.
     */
    public const DEFAULT_LIMITS = [
        'general'       => 70000000.0,
        'motor'         => 70000000.0,
        'surplus'       => 120000000.0,
        // Motor riot and strike is capped lower than the treaty's own event limit.
        'motor_riot'    => 50000000.0,
    ];

    private array $limits;

    /** @param array<string,float> $limits overrides for DEFAULT_LIMITS */
    public function __construct(array $limits = [])
    {
        $this->limits = $limits + self::DEFAULT_LIMITS;

        foreach ($this->limits as $key => $value) {
            if (! is_numeric($value) || $value < 0) {
                throw new InvalidArgumentException("Event limit for {$key} must be a non-negative number.");
            }
        }
    }

    /**
     * How many consecutive hours count as one occurrence, for a peril on a treaty.
     *
     * THE TREATY MATTERS. Note 6: Motor carries the same first four categories
     * "with no 75-hour fire category", so a fire on Motor runs the 168-hour
     * default. Applying the General fire window to Motor would merge occurrences
     * the Motor slip keeps apart and under-recover.
     *
     * Two perils also carry a PLACE condition as well as a clock — see
     * localityConditionFor().
     */
    public function hoursFor(string $peril, string $treaty = 'general'): int
    {
        $p = strtolower(trim($peril));

        if ($this->matches($p, self::PERILS_FIRE)) {
            // Motor has no fire category, so fire falls to the default there.
            return strtolower(trim($treaty)) === 'motor' ? self::HOURS_DEFAULT : self::HOURS_FIRE;
        }

        if ($this->matches($p, self::PERILS_72) || $this->matches($p, self::PERILS_72_LOCAL)) {
            return self::HOURS_NAMED;
        }

        return self::HOURS_DEFAULT;
    }

    /**
     * Whether a peril's window is also bounded by place, and how.
     *
     * 'radius' — fire, within an 80km radius of a point the Reinsured selects.
     * 'locality' — strike and riot, within one city, town or village.
     * NULL — the clock alone decides.
     */
    public function localityConditionFor(string $peril, string $treaty = 'general'): ?string
    {
        $p = strtolower(trim($peril));

        if ($this->matches($p, self::PERILS_FIRE)) {
            return strtolower(trim($treaty)) === 'motor' ? null : 'radius';
        }
        if ($this->matches($p, self::PERILS_72_LOCAL)) {
            return 'locality';
        }

        return null;
    }

    /** @param array<int,string> $needles */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }

    /** The cap on the reinsurers' liability for one occurrence on one treaty. */
    public function limitFor(string $treaty, ?string $peril = null): float
    {
        $t = strtolower(trim($treaty));

        // Motor riot and strike is capped below the Motor treaty's own event limit.
        if ($t === 'motor' && $peril !== null) {
            $p = strtolower($peril);
            if (str_contains($p, 'riot') || str_contains($p, 'strike')) {
                return (float) $this->limits['motor_riot'];
            }
        }

        if (! array_key_exists($t, $this->limits)) {
            throw new InvalidArgumentException("No event limit configured for treaty '{$treaty}'.");
        }

        return (float) $this->limits[$t];
    }

    /**
     * Group losses into occurrences under the hours clause.
     *
     * Losses are grouped per PERIL, because the window length is a property of
     * the peril: a windstorm 100 hours after another windstorm is a second
     * occurrence, while two thefts 100 hours apart are one.
     *
     * @param  array<int,array{occurred_at:string,peril:string,ceded:float|int|string,latitude?:float|null,longitude?:float|null,location?:string|null,reference?:string|null}>  $losses
     * @return array<int,array<string,mixed>>
     */
    public function occurrencesFor(array $losses, string $treaty = 'general'): array
    {
        if (! $losses) {
            return [];
        }

        // Bucket by peril first — the window length is a property of the peril.
        $byPeril = [];
        foreach ($losses as $i => $loss) {
            $peril = (string) ($loss['peril'] ?? '');
            $at    = strtotime((string) ($loss['occurred_at'] ?? ''));

            if ($at === false) {
                throw new InvalidArgumentException(
                    'Loss ' . ($loss['reference'] ?? $i) . ' has no readable occurrence date.'
                );
            }

            $loss['_at'] = $at;
            $byPeril[strtolower(trim($peril))][] = $loss;
        }

        $occurrences = [];

        foreach ($byPeril as $peril => $group) {
            usort($group, fn ($a, $b) => $a['_at'] <=> $b['_at']);

            $hours     = $this->hoursFor($peril, $treaty);
            $condition = $this->localityConditionFor($peril, $treaty);
            $window    = $hours * 3600;
            $assigned  = [];

            foreach ($group as $idx => $loss) {
                if (isset($assigned[$idx])) {
                    continue;
                }

                // The earliest unassigned loss opens the window.
                $members        = [$idx];
                $assigned[$idx] = true;
                $placeKnown     = true;

                foreach ($group as $j => $other) {
                    if (isset($assigned[$j]) || $j === $idx) {
                        continue;
                    }
                    if (($other['_at'] - $loss['_at']) > $window) {
                        break; // sorted, so nothing later can fall inside
                    }

                    if ($condition !== null) {
                        $near = $condition === 'radius'
                            ? $this->withinFireRadius($loss, $other)
                            : $this->sameLocality($loss, $other);

                        if ($near === null) {
                            // Geography unknown. Group on time, and say so.
                            $placeKnown = false;
                        } elseif ($near === false) {
                            continue; // outside the place condition: separate occurrence
                        }
                    }

                    $members[]    = $j;
                    $assigned[$j] = true;
                }

                $occurrences[] = $this->buildOccurrence(
                    $group, $members, $peril, $hours, $condition, $placeKnown
                );
            }
        }

        // Newest last, so a report reads in the order events happened.
        usort($occurrences, fn ($a, $b) => $a['first_loss_at'] <=> $b['first_loss_at']);

        return $occurrences;
    }

    /**
     * The event limit for an occurrence that spans two or more underwriting years.
     *
     * Note 5, quoting General Article 5.9: "Where an event involves two or more
     * underwriting years, the General event limit is reduced in the same
     * proportion as the losses of that event involving the agreement contribute
     * to the sum of all losses of that event across all underwriting years."
     *
     * So an event striking 60,000,000 of business written under this agreement
     * and 40,000,000 written under the previous one does not get the whole
     * 70,000,000: it gets 60/100 of it, which is 42,000,000. Applying the full
     * limit to a straddling event would recover more from this year's treaty than
     * this year's business contributed to the loss.
     *
     * ONE YEAR MEANS THE FULL LIMIT. The reduction only bites where the event
     * genuinely spans years, so a single-year event is untouched rather than
     * scaled by 1 and rounded.
     *
     * @param  float  $thisAgreement  losses of the event written under this agreement
     * @param  float  $allYears       losses of the event across every underwriting year
     */
    public function limitForStraddlingEvent(
        string $treaty,
        float $thisAgreement,
        float $allYears,
        ?string $peril = null
    ): float {
        $limit = $this->limitFor($treaty, $peril);

        if ($allYears <= 0.0) {
            throw new InvalidArgumentException(
                'Total losses across all underwriting years must be greater than zero.'
            );
        }
        if ($thisAgreement < 0.0 || $thisAgreement > $allYears) {
            throw new InvalidArgumentException(
                'Losses under this agreement cannot be negative, nor exceed the total across all years.'
            );
        }

        // Wholly within one year: nothing to apportion.
        if (abs($thisAgreement - $allYears) < 0.005) {
            return $limit;
        }

        return round($limit * ($thisAgreement / $allYears), 2);
    }

    /**
     * Apply the limit to a straddling occurrence, and say what was reduced.
     *
     * @param  array<string,mixed>  $occurrence
     * @return array<string,mixed>
     */
    public function applyStraddlingLimit(
        array $occurrence,
        string $treaty,
        float $thisAgreement,
        float $allYears
    ): array {
        $full    = $this->limitFor($treaty, $occurrence['peril'] ?? null);
        $reduced = $this->limitForStraddlingEvent(
            $treaty, $thisAgreement, $allYears, $occurrence['peril'] ?? null
        );

        $sought = (float) ($occurrence['ceded_sought'] ?? 0.0);
        $paid   = min($sought, $reduced);

        return $occurrence + [
            'treaty'             => $treaty,
            'event_limit'        => $reduced,
            'event_limit_full'   => $full,
            'straddles_years'    => $reduced < $full,
            'agreement_share'    => round($thisAgreement / $allYears, 6),
            'ceded_recovered'    => $paid,
            'ceded_declined'     => round($sought - $paid, 2),
            'limit_exhausted'    => $sought >= $reduced,
        ];
    }

    /**
     * Cap an occurrence's ceded recovery at the treaty's event limit.
     *
     * The excess is NOT a retention in the ordinary sense — it is recovery the
     * treaty does not provide, so it falls to Alpha Direct net. RI-02 blocker 3
     * is the same point: a 150,000,000 occurrence cedes 70,000,000 and leaves
     * 80,000,000 net, which is a catastrophe-cover question rather than a
     * systems one.
     *
     * @param  array<string,mixed>  $occurrence
     * @return array<string,mixed>
     */
    public function applyLimit(array $occurrence, string $treaty): array
    {
        $limit  = $this->limitFor($treaty, $occurrence['peril'] ?? null);
        $sought = (float) ($occurrence['ceded_sought'] ?? 0.0);
        $paid   = min($sought, $limit);

        return $occurrence + [
            'treaty'          => $treaty,
            'event_limit'     => $limit,
            'ceded_recovered' => $paid,
            'ceded_declined'  => round($sought - $paid, 2),
            'limit_exhausted' => $sought >= $limit,
        ];
    }

    /**
     * Every occurrence on a set of losses, with the limit applied to each.
     *
     * @param  array<int,array<string,mixed>>  $losses
     * @return array{occurrences:array<int,array<string,mixed>>, totals:array<string,float>}
     */
    public function assess(array $losses, string $treaty): array
    {
        $occurrences = array_map(
            fn ($o) => $this->applyLimit($o, $treaty),
            $this->occurrencesFor($losses, $treaty)
        );

        $sum = fn (string $k) => round(array_sum(array_map(
            fn ($o) => (float) ($o[$k] ?? 0),
            $occurrences
        )), 2);

        return [
            'occurrences' => $occurrences,
            'totals'      => [
                'occurrences'     => count($occurrences),
                'ceded_sought'    => $sum('ceded_sought'),
                'ceded_recovered' => $sum('ceded_recovered'),
                'ceded_declined'  => $sum('ceded_declined'),
            ],
        ];
    }

    // ───────────────────────────────────────────────────────── internals

    /** @param array<int,array<string,mixed>> $group */
    private function buildOccurrence(
        array $group,
        array $members,
        string $peril,
        int $hours,
        ?string $condition,
        bool $placeKnown
    ): array {
        $losses = array_map(fn ($i) => $group[$i], $members);
        $times  = array_column($losses, '_at');

        $occurrence = [
            'peril'         => $peril,
            'hours_clause'  => $hours,
            'place_clause'  => $condition,
            'first_loss_at' => date('Y-m-d H:i:s', min($times)),
            'last_loss_at'  => date('Y-m-d H:i:s', max($times)),
            'loss_count'    => count($losses),
            'ceded_sought'  => round(array_sum(array_map(
                fn ($l) => (float) ($l['ceded'] ?? 0),
                $losses
            )), 2),
            'references'    => array_values(array_filter(array_map(
                fn ($l) => $l['reference'] ?? null,
                $losses
            ))),
            // Carried through for the ANNUAL AGGREGATES, which filter on more
            // than peril: SRCC is Zimbabwe only and War is Goods in Transit
            // only. Without these an occurrence reaches
            // AnnualAggregateCalculator with nothing to test, and every riot
            // anywhere is treated as a Zimbabwe riot and flagged uncertain.
            // Distinct values, because one occurrence can span several risks.
            'territories'   => $this->distinct($losses, ['territory', 'country']),
            'coverages'     => $this->distinct($losses, ['coverage', 'coverage_name']),
        ];

        // Only a peril with a PLACE condition can be uncertain about place.
        if ($condition !== null && ! $placeKnown) {
            $occurrence['place_untested'] = true;
            $occurrence['exception'] = $condition === 'radius'
                ? 'Fire losses grouped on time alone — no coordinates or location on at least one '
                  . 'loss, so the 80km radius could not be tested. Occurrences may be merged that '
                  . 'the clause would separate.'
                : 'Strike or riot losses grouped on time alone — no locality on at least one loss, '
                  . 'so "within one city, town or village" could not be tested. Occurrences may be '
                  . 'merged that the clause would separate.';
        }

        return $occurrence;
    }

    /**
     * The distinct values of whichever of these fields the losses carry.
     *
     * @param  array<int,array<string,mixed>>  $losses
     * @param  string[]                        $fields
     * @return string[]
     */
    private function distinct(array $losses, array $fields): array
    {
        $out = [];

        foreach ($losses as $loss) {
            foreach ($fields as $field) {
                $value = trim((string) ($loss[$field] ?? ''));
                if ($value !== '' && ! in_array($value, $out, true)) {
                    $out[] = $value;
                }
            }
        }

        return $out;
    }

    /**
     * Whether two losses fall in one city, town or village — the strike group's
     * condition under Note 6.
     *
     * NULL where it cannot be tested, for the same reason as the fire radius:
     * unknown place must not read as "same place" any more than as "different".
     */
    private function sameLocality(array $a, array $b): ?bool
    {
        $aLoc = $a['locality'] ?? $a['location'] ?? null;
        $bLoc = $b['locality'] ?? $b['location'] ?? null;

        if ($aLoc === null || $bLoc === null) {
            return null;
        }

        return strcasecmp(trim((string) $aLoc), trim((string) $bLoc)) === 0;
    }

    /**
     * Whether two fire losses fall inside the 80km radius.
     *
     * NULL means it could not be tested, which is deliberately distinct from
     * false: unknown geography must not silently read as "outside", any more than
     * it may read as "inside".
     */
    private function withinFireRadius(array $a, array $b): ?bool
    {
        $aLat = $a['latitude']  ?? null;
        $aLon = $a['longitude'] ?? null;
        $bLat = $b['latitude']  ?? null;
        $bLon = $b['longitude'] ?? null;

        if ($aLat !== null && $aLon !== null && $bLat !== null && $bLon !== null) {
            return $this->haversineKm((float) $aLat, (float) $aLon, (float) $bLat, (float) $bLon)
                <= self::FIRE_RADIUS_KM;
        }

        // No coordinates. A shared location key is the next best evidence: two
        // fires at the same site are plainly within 80km of each other.
        $aLoc = $a['location'] ?? null;
        $bLoc = $b['location'] ?? null;

        if ($aLoc !== null && $bLoc !== null) {
            return strcasecmp(trim((string) $aLoc), trim((string) $bLoc)) === 0;
        }

        return null;
    }

    /** Great-circle distance in kilometres. */
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r    = 6371.0088;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
