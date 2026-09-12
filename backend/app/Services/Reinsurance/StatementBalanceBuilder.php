<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Brokerage, VAT and the balance — RI-18 step 5, BR-COM-16 and BR-ACC-17/18.
 *
 * THIS RUNS LAST, AND ON WHAT THE STATEMENT ALREADY CARRIES. Brokerage is a
 * percentage of ceded premium, and the premium in question is the premium
 * written onto this statement by step 2 — not a fresh query of the book. Reading
 * the items has two consequences worth having: a rebuilt quarter cannot end up
 * with brokerage computed on a different premium from the one it shows, and the
 * arithmetic a reader can check by eye is the arithmetic that ran.
 *
 * IT ALSO MEANS ORDER MATTERS. Brokerage on a statement with no premium lines is
 * nil, which is indistinguishable from brokerage nobody calculated — so this
 * refuses rather than writing a nil line, and says which step has not run.
 *
 * THE BASE IS GROSS CEDED PREMIUM, NOT PREMIUM NET OF COMMISSION — and this is
 * now a ruling rather than an inference. Reinsurance confirmed on 7 September
 * 2026 that brokerage is charged on the gross ceded premium and not on premium
 * after commissions. It was already built that way, reasoned from the slips
 * giving 2.50% without a base and from BR-COM-17 — "no OTHER deductions from
 * premium" — putting brokerage alongside the ceding commission, both off
 * premium. Compounding them would also make the order they are applied in
 * matter, and nothing sets one. The confirmation is recorded because the
 * argument used to rest on reading two clauses together, and a future reader
 * finding the compounded reading more natural would have had nothing to stop
 * them: a 2.50% brokerage on premium net of a 32.5% commission understates the
 * fee by roughly a third.
 *
 * NO VAT LINE IS WRITTEN BY DEFAULT. BR-ACC-18 says treaty figures exclude VAT
 * unless otherwise stated, so a statement built from them is VAT-exclusive.
 * BR-ACC-17 says VAT applies to the transactions at the rate current at the
 * time — that VAT exists, not that it belongs in this account. Which lines would
 * attract it is a tax question and the flows run in different directions, so it
 * is configured rather than assumed, and a statement reports whether it was.
 *
 * SIGNS MAKE THE BALANCE AN ADDITION. Premium is positive because it is due to
 * reinsurers; commission and brokerage negative because they come back; claims
 * paid negative. The balance is then a sum of the settling items and needs no
 * rule anyone has to remember — see TreatyStatement::balance().
 */
class StatementBalanceBuilder
{
    /** Brokerage, BR-COM-16. One rate, both treaties. */
    public function brokerageRate(string $treaty): float
    {
        $rate = config('reinsurance.terms.brokerage_pct');

        if ($rate === null) {
            throw new RuntimeException(
                "No brokerage rate configured for treaty '{$treaty}'."
            );
        }

        return (float) $rate;
    }

    /**
     * Whether VAT has been ruled on, and at what rate.
     *
     * @return array{configured:bool,rate:float,applies_to:array<int,string>}
     */
    public function vatTreatment(): array
    {
        $appliesTo = array_values(array_filter(
            (array) config('reinsurance.terms.vat.applies_to', [])
        ));

        return [
            'configured' => $appliesTo !== [],
            'rate'       => (float) config('reinsurance.terms.vat.rate', 0.0),
            'applies_to' => $appliesTo,
        ];
    }

    /**
     * Brokerage on the premium a statement already carries, by class.
     *
     * @return array{rate:float,by_class:array<string,float>,
     *                base_by_class:array<string,float>,base:float,total:float}
     */
    public function brokerageFor(TreatyStatement $statement): array
    {
        $premiums = $statement->items()
            ->where('item_type', TreatyStatementItem::PREMIUM)
            ->get(['regulatory_class', 'amount']);

        if ($premiums->isEmpty()) {
            throw new RuntimeException(
                'Cannot compute brokerage: this statement carries no premium lines. '
                . 'Run the premium builder (RI-18 step 2) first — a nil brokerage and '
                . 'an uncalculated one look identical on a rendered account.'
            );
        }

        $rate = $this->brokerageRate($statement->treaty);

        // The base is kept per class rather than recovered later by dividing the
        // brokerage back out by the rate — that round trip loses cents and would
        // divide by zero on a nil rate.
        $baseByClass = [];
        foreach ($premiums as $p) {
            $class = (string) $p->regulatory_class;
            $baseByClass[$class] = round(($baseByClass[$class] ?? 0.0) + (float) $p->amount, 2);
        }

        ksort($baseByClass);

        $byClass = [];
        foreach ($baseByClass as $class => $base) {
            $byClass[$class] = round($base * $rate, 2);
        }

        return [
            'rate'          => $rate,
            'by_class'      => $byClass,
            'base_by_class' => $baseByClass,
            'base'          => round(array_sum($baseByClass), 2),
            'total'         => round(array_sum($byClass), 2),
        ];
    }

    /**
     * VAT on whichever item types have been ruled to attract it.
     *
     * SIGN FOLLOWS THE UNDERLYING. VAT on a deduction is itself a deduction; VAT
     * on premium is due alongside the premium. Taking the sign from the item
     * rather than fixing it here means a future ruling cannot silently invert a
     * line.
     *
     * @return array{configured:bool,rate:float,by_class:array<string,float>,total:float}
     */
    public function vatFor(TreatyStatement $statement): array
    {
        $treatment = $this->vatTreatment();

        if (! $treatment['configured']) {
            return $treatment + ['by_class' => [], 'total' => 0.0];
        }

        $items = $statement->items()
            ->whereIn('item_type', $treatment['applies_to'])
            ->get(['regulatory_class', 'amount']);

        $byClass = [];
        foreach ($items as $i) {
            $class = (string) $i->regulatory_class;
            $byClass[$class] = round(
                ($byClass[$class] ?? 0.0) + ((float) $i->amount * $treatment['rate']),
                2
            );
        }

        ksort($byClass);

        return $treatment + [
            'by_class' => $byClass,
            'total'    => round(array_sum($byClass), 2),
        ];
    }

    /**
     * Write the brokerage and VAT lines, and return the statement's position.
     *
     * @return array{brokerage:array<string,mixed>,vat:array<string,mixed>,
     *                balance:float,memorandum:float,items:int}
     */
    public function writeItems(TreatyStatement $statement): array
    {
        $brokerage = $this->brokerageFor($statement);

        $written = 0;

        DB::transaction(function () use ($statement, $brokerage, &$written) {
            $statement->items()
                ->whereIn('item_type', [TreatyStatementItem::BROKERAGE, TreatyStatementItem::VAT])
                ->delete();

            foreach ($brokerage['by_class'] as $class => $amount) {
                $statement->items()->create([
                    'item_type'        => TreatyStatementItem::BROKERAGE,
                    'regulatory_class' => $class,
                    // NEGATIVE: brokerage is a deduction from the premium due to
                    // reinsurers, the same direction as the ceding commission.
                    'amount'           => -1 * $amount,
                    'basis_amount'     => $brokerage['base_by_class'][$class] ?? 0.0,
                    'rate'             => $brokerage['rate'],
                ]);
                $written++;
            }
        });

        // VAT is computed AFTER brokerage is written, so a ruling that VAT
        // attaches to brokerage sees the brokerage line rather than missing it
        // by one step.
        $vat = $this->vatFor($statement);

        if ($vat['configured']) {
            DB::transaction(function () use ($statement, $vat, &$written) {
                foreach ($vat['by_class'] as $class => $amount) {
                    if (abs($amount) < 0.005) {
                        continue;
                    }
                    $statement->items()->create([
                        'item_type'        => TreatyStatementItem::VAT,
                        'regulatory_class' => $class,
                        'amount'           => $amount,
                        'rate'             => $vat['rate'],
                    ]);
                    $written++;
                }
            });
        }

        return [
            'brokerage'  => $brokerage,
            'vat'        => $vat,
            'balance'    => $statement->balance(),
            'memorandum' => $statement->memorandumTotal(),
            'items'      => $written,
        ];
    }

    /**
     * The statement's position, broken down by item type.
     *
     * WHAT SETTLES AND WHAT ONLY REPORTS, side by side. A reader checking the
     * balance should be able to see every line that entered it and every line
     * that deliberately did not, rather than taking the difference on trust.
     *
     * @return array{by_type:array<string,float>,settling:float,memorandum:float,
     *                balance:float,foots:bool}
     */
    public function summaryFor(TreatyStatement $statement): array
    {
        $byType = [];
        foreach ($statement->items()->get(['item_type', 'amount']) as $i) {
            $type = (string) $i->item_type;
            $byType[$type] = round(($byType[$type] ?? 0.0) + (float) $i->amount, 2);
        }

        ksort($byType);

        $settling = 0.0;
        foreach ($byType as $type => $amount) {
            if (! in_array($type, TreatyStatementItem::MEMORANDUM_ONLY, true)) {
                $settling += $amount;
            }
        }

        $settling = round($settling, 2);
        $balance  = $statement->balance();

        return [
            'by_type'    => $byType,
            'settling'   => $settling,
            'memorandum' => $statement->memorandumTotal(),
            'balance'    => $balance,
            // The sum of the settling lines and the balance are computed by
            // different routes — one in PHP over every item, one in SQL over a
            // scope. They must agree, and a statement that does not foot is one
            // nobody should render.
            'foots'      => abs($settling - $balance) < 0.005,
        ];
    }
}
