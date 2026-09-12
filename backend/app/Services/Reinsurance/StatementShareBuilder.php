<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Per-reinsurer allocation — RI-18 step 9, BR-ACC-03 and BR-SEC-08.
 *
 * THE ARITHMETIC PROBLEM IS ROUNDING, NOT PERCENTAGES. BR-SEC-08 requires every
 * ceded amount allocated to each reinsurer in proportion to its participation
 * AND RECONCILING TO THE TOTAL. Those two are in tension: 100.00 split three
 * ways at 33.333% rounds to 33.33 three times and reconciles to 99.99. A cent
 * missing from a reinsurer's line is a statement that does not foot, and it is
 * the reinsurer who finds it.
 *
 * SO THE CENTS ARE DEALT, NOT MULTIPLIED. Each share is taken to whole cents by
 * the largest-remainder method: floor everything, then give the cents left over
 * to the shares with the largest fractional part. The allocation sums to the
 * item EXACTLY, by construction rather than by luck, and the reinsurer who gains
 * the odd cent is the one with the strongest claim to it.
 *
 * IT REFUSES AN INCOMPLETE PANEL, and that refusal is the point of the step.
 * General is 34.00 points short of the cession and Motor 33.10. Allocating
 * anyway would either scale the placed reinsurers up — handing them business
 * they never signed for — or leave a silent gap that makes the statement
 * disagree with itself. ParticipationCalculator already declines; this passes
 * that refusal through with the statement named.
 *
 * A PREVIEW IS STILL USEFUL WHILE THE PANEL IS SHORT. previewFor() shows what
 * would be allocated and by how much the panel misses, without writing
 * anything — so the shape can be checked now and the rows written the day the
 * signing schedule lands.
 */
class StatementShareBuilder
{
    public function __construct(private ParticipationCalculator $participation)
    {
    }

    /**
     * The panel for a treaty year, as ParticipationCalculator wants it.
     *
     * @return array<int,array{reinsurer:string,share_pct:float,basis:string}>
     */
    public function panelFor(string $treaty, int $underwritingYear): array
    {
        $rows = DB::table('reinsurer_shares as rs')
            ->leftJoin('reinsurer as r', function ($j) {
                // Cast both sides: reinsurer_id is a varchar here and an integer
                // there, and an untyped comparison leans on MySQL's coercion.
                $j->on(DB::raw('CAST(rs.reinsurer_id AS CHAR)'), '=', DB::raw('CAST(r.id AS CHAR)'));
            })
            ->where('rs.treaty_year', $underwritingYear)
            ->whereRaw('LOWER(TRIM(rs.treaty_id)) = ?', [strtolower(trim($treaty))])
            ->get(['rs.reinsurer_id', 'rs.share_pct', 'r.company_name']);

        $basis = (string) config(
            'reinsurance.terms.participation_basis',
            ParticipationCalculator::BASIS_HUNDRED
        );

        return $rows->map(fn ($r) => [
            'reinsurer'    => (string) ($r->company_name ?? $r->reinsurer_id),
            'reinsurer_id' => (string) $r->reinsurer_id,
            'share_pct'    => (float) $r->share_pct,
            'basis'        => $basis,
        ])->all();
    }

    /**
     * Split an amount across participations so the parts sum to it EXACTLY.
     *
     * LARGEST REMAINDER, IN WHOLE CENTS. Multiplying and rounding each share
     * independently loses or gains cents against the total; this floors every
     * share to a cent, counts what is left over, and deals those cents to the
     * largest fractional parts. The result reconciles by construction.
     *
     * SIGN IS CARRIED SEPARATELY because commission, brokerage and claims are
     * negative: flooring a negative number moves it away from zero, which would
     * over-allocate a credit. The magnitude is split and the sign reapplied.
     *
     * @param  array<int,array{share_of_cession:float}>  $participations
     * @return array<int,float>  keyed to the participations, in the same order
     */
    public function allocate(float $amount, array $participations): array
    {
        if ($participations === []) {
            return [];
        }

        $sign  = $amount < 0 ? -1 : 1;
        $cents = (int) round(abs($amount) * 100);

        $floors     = [];
        $remainders = [];
        $assigned   = 0;

        foreach ($participations as $i => $p) {
            $exact        = $cents * ((float) $p['share_of_cession'] / 100.0);
            $floors[$i]   = (int) floor($exact);
            $remainders[$i] = $exact - $floors[$i];
            $assigned    += $floors[$i];
        }

        $left = $cents - $assigned;

        // Deal the leftover cents to the largest fractional parts. Ties break on
        // the earlier participation, so the same panel always produces the same
        // allocation — a statement that reshuffles cents between rebuilds would
        // be impossible to reconcile against a previously issued copy.
        arsort($remainders);

        foreach (array_keys($remainders) as $i) {
            if ($left <= 0) {
                break;
            }
            $floors[$i]++;
            $left--;
        }

        ksort($floors);

        return array_map(static fn (int $c) => $sign * $c / 100, $floors);
    }

    /**
     * What would be allocated, and whether it can be.
     *
     * WRITES NOTHING. While the panel is short this is the only honest output:
     * the shape is checkable, the shortfall is named, and no row claims a
     * reinsurer signed for something.
     *
     * @return array{complete:bool,placed_of_hundred:float,unplaced_of_hundred:float,
     *                exception:string|null,by_item:array<int,array<string,mixed>>,
     *                by_reinsurer:array<string,float>,items:int,reconciles:bool}
     */
    public function previewFor(TreatyStatement $statement): array
    {
        $panel = $this->panelFor(
            (string) $statement->treaty,
            (int) $statement->underwriting_year
        );

        $assessed = $this->participation->assessPanel($panel);

        $byItem = [];
        $byReinsurer = [];
        $reconciles = true;

        // ONLY THE SETTLING ITEMS ARE ALLOCATED. Outstanding losses, salvages and
        // recoveries are reported rather than settled, so there is no ceded
        // amount to apportion — BR-SEC-08 is about money that moves.
        foreach ($statement->items()->settling()->get() as $item) {
            $amounts = $this->allocate((float) $item->amount, $assessed['participations']);

            $rows = [];
            foreach ($assessed['participations'] as $i => $p) {
                $name = (string) $p['reinsurer'];
                $rows[] = [
                    'reinsurer'            => $name,
                    'share_of_hundred_pct' => $p['share_of_hundred'],
                    'share_of_cession_pct' => $p['share_of_cession'],
                    'amount'               => $amounts[$i],
                ];
                $byReinsurer[$name] = round(($byReinsurer[$name] ?? 0.0) + $amounts[$i], 2);
            }

            $sum = round(array_sum($amounts), 2);
            $itemReconciles = abs($sum - round((float) $item->amount, 2)) < 0.005;
            $reconciles = $reconciles && $itemReconciles;

            $byItem[] = [
                'item_id'    => $item->id,
                'item_type'  => $item->item_type,
                'class'      => $item->regulatory_class,
                'amount'     => round((float) $item->amount, 2),
                'allocated'  => $sum,
                'reconciles' => $itemReconciles,
                'shares'     => $rows,
            ];
        }

        ksort($byReinsurer);

        return [
            'complete'            => $assessed['complete'],
            'placed_of_hundred'   => $assessed['placed_of_hundred'],
            'unplaced_of_hundred' => $assessed['unplaced_of_hundred'],
            'exception'           => $assessed['exception'],
            'by_item'             => $byItem,
            'by_reinsurer'        => $byReinsurer,
            'items'               => count($byItem),
            'reconciles'          => $reconciles,
        ];
    }

    /**
     * Write the per-reinsurer allocation.
     *
     * @return int  rows written
     */
    public function writeItems(TreatyStatement $statement): int
    {
        $preview = $this->previewFor($statement);

        if (! $preview['complete']) {
            throw new RuntimeException(sprintf(
                'No allocation can be written for %s %d Q%d: the panel is %s%% of the 100%% risk '
                . 'against a cession of %s%%, leaving %s%% unplaced. Allocating a short panel '
                . 'would either scale the placed reinsurers up, handing them business they never '
                . 'signed for, or leave a gap that makes the statement disagree with itself.',
                $statement->treaty,
                $statement->underwriting_year,
                $statement->quarter,
                $preview['placed_of_hundred'],
                $this->participation->fullPanelOnHundredBasis(),
                $preview['unplaced_of_hundred']
            ));
        }

        if (! $preview['reconciles']) {
            throw new RuntimeException(
                'An allocation did not reconcile to its item. Nothing was written. This is an '
                . 'arithmetic fault rather than a data one — the largest-remainder split is '
                . 'meant to make it impossible.'
            );
        }

        $written = 0;

        DB::transaction(function () use ($statement, $preview, &$written) {
            $itemIds = $statement->items()->pluck('id');

            DB::table('treaty_statement_shares')
                ->whereIn('treaty_statement_item_id', $itemIds)
                ->delete();

            foreach ($preview['by_item'] as $item) {
                foreach ($item['shares'] as $s) {
                    // A reinsurer with no money on a line is not written: an
                    // empty row says a share was allocated when none was.
                    if (abs($s['amount']) < 0.005) {
                        continue;
                    }

                    DB::table('treaty_statement_shares')->insert([
                        'treaty_statement_item_id' => $item['item_id'],
                        'reinsurer'                => $s['reinsurer'],
                        'share_of_hundred_pct'     => $s['share_of_hundred_pct'],
                        'share_of_cession_pct'     => $s['share_of_cession_pct'],
                        'amount'                   => $s['amount'],
                        'created_at'               => now(),
                        'updated_at'               => now(),
                    ]);
                    $written++;
                }
            }
        });

        return $written;
    }
}
