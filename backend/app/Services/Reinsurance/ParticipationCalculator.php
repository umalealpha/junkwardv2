<?php

namespace AlphaDirect\Services\Reinsurance;

use InvalidArgumentException;

/**
 * Reinsurer participations, and the two bases they are quoted on.
 *
 * A share can be stated two ways and the slips use both, sometimes on the same
 * placement. GIC Re SA on the Motor slip is written as 22.50% and signed "17% of
 * cession", which on a 70% cession is 11.90% of the whole risk. Read the second
 * figure as if it were the first and every allocation to that reinsurer is out by
 * 1/0.70 — roughly 1.43 times.
 *
 * THE BASIS FOR 2026/27 IS THE 100% RISK. Confirmed by Reinsurance on 31 August
 * 2026: "The reinsurer split is based on the 100% risk. The splits may not add up
 * to 100% due to the retention element in the treaty."
 *
 * WHICH MEANS A COMPLETE PANEL SUMS TO THE CESSION, NOT TO 100. On a 30/70 treaty
 * a fully placed panel sums to 70% of the whole risk; the other 30% is Alpha
 * Direct's retention and no reinsurer holds it. That is the whole reason this
 * class exists: ReinsuranceEngine::splitByReinsurers() requires shares summing to
 * 100 — correctly, because it is splitting the CEDED amount — so a share stored
 * on the 100% basis has to be converted before it gets there.
 *
 * AN INCOMPLETE PANEL IS NOT NORMALISED TO 100. This is the safety property. On
 * the signed slips as they stand, the General panel names 36% of the 100% risk
 * against a 70% cession and Motor names 36.90% — roughly half the cession has no
 * reinsurer behind it. Scaling those up to 100% of the cession would report cover
 * that nobody has written, so the panel is reported INCOMPLETE and no split is
 * produced. The shortfall is unplaced cession: exposure Alpha Direct carries
 * while believing it is ceded, which is the worst of the three ways to be wrong.
 *
 * PURE. No database, no container.
 *
 * Sources: J.B. Boda General Quota Share 2026/27 amended signed slip; Motor
 * Quota Share 2026/27 Continental Re lead signed slip; Reinsurance's confirmation
 * of 31 August 2026; RI-02 blockers 1 and 3.
 */
class ParticipationCalculator
{
    /** A share quoted as a percentage of the whole risk. The 2026/27 basis. */
    public const BASIS_HUNDRED = 'of_100_percent';

    /** A share quoted as a percentage of the ceded portion. */
    public const BASIS_CESSION = 'of_cession';

    /** Percentage points of slack allowed before a panel is called incomplete. */
    public const TOLERANCE = 0.01;

    private float $cessionPct;

    /** @param float $cessionPct The ceded proportion, 0.70 on both 2026/27 treaties. */
    public function __construct(float $cessionPct = 0.70)
    {
        if ($cessionPct <= 0.0 || $cessionPct > 1.0) {
            throw new InvalidArgumentException(
                'The cession must be greater than 0 and at most 1; got ' . $cessionPct . '.'
            );
        }

        $this->cessionPct = $cessionPct;
    }

    /**
     * A share of the whole risk, restated as a share of the cession.
     *
     * 11.90% of the 100% risk is 17% of a 70% cession. This is the conversion
     * that has to happen before a share reaches the engine's gate.
     */
    public function toCessionBasis(float $shareOfHundred): float
    {
        return round($shareOfHundred / $this->cessionPct, 6);
    }

    /** The reverse: 17% of cession is 11.90% of the whole risk. */
    public function toHundredBasis(float $shareOfCession): float
    {
        return round($shareOfCession * $this->cessionPct, 6);
    }

    /** What a fully placed panel sums to, on the 100% basis. 70 on both treaties. */
    public function fullPanelOnHundredBasis(): float
    {
        return round($this->cessionPct * 100.0, 6);
    }

    /**
     * Assess a panel of participations.
     *
     * Shares may be quoted on either basis, per row, because the slips do exactly
     * that. Everything is normalised to the 100% basis for the completeness test,
     * and to the cession basis for the split.
     *
     * @param  array<int,array{reinsurer_id?:string|int,reinsurer?:string,share_pct:float|int|string,basis?:string}>  $shares
     * @return array{
     *     participations:array<int,array<string,mixed>>,
     *     placed_of_hundred:float, placed_of_cession:float,
     *     unplaced_of_hundred:float, complete:bool, exception:string|null
     * }
     */
    public function assessPanel(array $shares): array
    {
        $rows            = [];
        $placedOfHundred = 0.0;

        foreach ($shares as $i => $s) {
            $basis = $s['basis'] ?? self::BASIS_HUNDRED;

            if (! in_array($basis, [self::BASIS_HUNDRED, self::BASIS_CESSION], true)) {
                throw new InvalidArgumentException(
                    "Participation {$i} carries an unknown basis '{$basis}'. "
                    . 'Use of_100_percent or of_cession.'
                );
            }

            $pct = (float) ($s['share_pct'] ?? 0);
            if ($pct < 0) {
                throw new InvalidArgumentException("Participation {$i} has a negative share.");
            }

            $ofHundred = $basis === self::BASIS_HUNDRED ? $pct : $this->toHundredBasis($pct);
            $ofCession = $basis === self::BASIS_CESSION ? $pct : $this->toCessionBasis($pct);

            $placedOfHundred += $ofHundred;

            $rows[] = [
                'reinsurer_id'      => $s['reinsurer_id'] ?? null,
                'reinsurer'         => $s['reinsurer'] ?? null,
                'stated_pct'        => $pct,
                'stated_basis'      => $basis,
                'share_of_hundred'  => round($ofHundred, 6),
                'share_of_cession'  => round($ofCession, 6),
            ];
        }

        $full     = $this->fullPanelOnHundredBasis();
        $placed   = round($placedOfHundred, 6);
        $unplaced = round($full - $placed, 6);
        $complete = abs($unplaced) <= self::TOLERANCE;

        return [
            'participations'      => $rows,
            'placed_of_hundred'   => $placed,
            'placed_of_cession'   => round($this->toCessionBasis($placed), 6),
            'unplaced_of_hundred' => $unplaced,
            'complete'            => $complete,
            'exception'           => $this->exceptionFor($placed, $full, $unplaced, $complete),
        ];
    }

    /**
     * Shares of the cession, ready for ReinsuranceEngine::splitByReinsurers().
     *
     * REFUSES AN INCOMPLETE PANEL. The engine requires shares summing to 100 and
     * it is right to; the answer to a short panel is not to scale it up but to
     * decline to publish a split at all. Scaling would allocate the whole ceded
     * amount across reinsurers who between them signed for half of it.
     *
     * @return array<int,array{reinsurer_id:mixed,share_pct:float}>
     */
    public function splitShares(array $shares): array
    {
        $panel = $this->assessPanel($shares);

        if (! $panel['complete']) {
            throw new InvalidArgumentException(
                'The panel is not fully placed: ' . $panel['placed_of_hundred'] . '% of the 100% risk '
                . 'against a cession of ' . $this->fullPanelOnHundredBasis() . '%, leaving '
                . $panel['unplaced_of_hundred'] . '% unplaced. No split can be published until the '
                . 'signing schedule is complete.'
            );
        }

        return array_map(fn ($r) => [
            'reinsurer_id' => $r['reinsurer_id'],
            'share_pct'    => $r['share_of_cession'],
        ], $panel['participations']);
    }

    private function exceptionFor(float $placed, float $full, float $unplaced, bool $complete): ?string
    {
        if ($complete) {
            return null;
        }

        if ($unplaced > 0) {
            return sprintf(
                'Panel short by %s of the 100%% risk — %s placed against a cession of %s. That '
                . 'shortfall is ceded exposure with no reinsurer behind it, carried by Alpha Direct '
                . 'while it reads as placed. Chase the signing schedule.',
                $this->pct($unplaced),
                $this->pct($placed),
                $this->pct($full)
            );
        }

        return sprintf(
            'Panel OVER-placed by %s — %s signed against a cession of %s. Either a share is on the '
            . 'wrong basis, or the same line has been recorded twice.',
            $this->pct(abs($unplaced)),
            $this->pct($placed),
            $this->pct($full)
        );
    }

    private function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') . '%';
    }
}
