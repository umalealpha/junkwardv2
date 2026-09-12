<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Premium and commission for one quarter — RI-18 step 2, BR-ACC-04 and 05.
 *
 * WHICH DATE PUTS A CESSION IN A QUARTER is the whole question here, and it is
 * not the same one the reconciliation asks. The reconciliation scopes on TERM
 * OVERLAP: is this policy's cover inside the treaty year. A statement of account
 * asks something different — was this premium WRITTEN in this quarter — and that
 * is the transaction date. The two disagree constantly: a policy incepting 1
 * March 2027 was written in an earlier quarter than the one its cover falls in.
 *
 * BR-ACC-04 IS A WRITTEN-PREMIUM ITEM: "written premiums payable to Reinsurers
 * less returns, cancellations and premiums paid for insurance and reinsurance
 * which inure to the benefit of the agreement". Written, not earned, and the
 * returns and cancellations are already in the data as negative endorsements
 * rather than something to subtract afterwards.
 *
 * THE FALLBACK IS DELIBERATE AND IS REPORTED. On the test server
 * transaction_date is null on 16 of the 51 actions carrying a cession, and
 * created_at is present on all 16. Falling back to it silently would put premium
 * in a quarter nobody chose; falling over instead would refuse to produce a
 * statement at all. So it falls back, counts how often, and hands that count
 * back with the figures — a statement whose premium leans on a fallback is one
 * somebody should look at before it is rendered.
 *
 * PURE OF THE BASIS QUESTION. Ceded premium comes from CessionSource, so this
 * follows whichever cession basis is live rather than deciding one.
 */
class StatementPremiumBuilder
{
    /** Only these ever carry premium into a statement. */
    public const COUNTED_STATUSES = ['ISSUED', 'APPROVED'];

    public function __construct(private CessionSource $cession)
    {
    }

    /**
     * The ceded premium written in a quarter, by regulatory class.
     *
     * @return array{
     *     by_class:array<string,float>,
     *     total:float,
     *     actions:int,
     *     dated_by_fallback:int,
     *     basis:string
     * }
     */
    public function cededPremiumFor(string $treaty, int $underwritingYear, int $quarter): array
    {
        $p = TreatyStatement::periodFor($underwritingYear, $quarter);

        $actions = $this->actionsWrittenBetween($p['start'], $p['end']);

        $byClass  = [];
        $fallback = 0;

        foreach ($actions as $a) {
            if ($a->dated_by_fallback) {
                $fallback++;
            }

            foreach ($this->cession->byKeyFor((int) $a->id) as $class => $fig) {
                $byClass[$class] = round(
                    ($byClass[$class] ?? 0.0) + (float) $fig['ceded_premium'],
                    2
                );
            }
        }

        // A class that nets to nothing across the quarter is not the same as a
        // class that never appeared. Both are kept: a nil line on a class that
        // traded is information, and dropping it would hide a cancellation that
        // exactly reversed a policy.
        ksort($byClass);

        return [
            'by_class'          => $byClass,
            'total'             => round(array_sum($byClass), 2),
            'actions'           => count($actions),
            'dated_by_fallback' => $fallback,
            'basis'             => $this->cession->basis(),
        ];
    }

    /**
     * Ceding commission on the quarter's premium.
     *
     * ONE RATE PER TREATY, from the slips: General 37.5%, Motor provisional
     * 25.00% until the sliding scale adjusts at the fourth quarter, surplus
     * 30%. The surplus rate is NOT applied here — surplus cession is part of the
     * General treaty's premium and its own commission belongs with the
     * commission engine, which is sequenced to August 2027.
     *
     * @param  array<string,float>  $byClass
     * @return array{rate:float,by_class:array<string,float>,total:float}
     */
    public function commissionOn(string $treaty, array $byClass): array
    {
        $rate = $this->commissionRate($treaty);

        $out = [];
        foreach ($byClass as $class => $premium) {
            $out[$class] = round($premium * $rate, 2);
        }

        return [
            'rate'     => $rate,
            'by_class' => $out,
            'total'    => round(array_sum($out), 2),
        ];
    }

    /** The ceding commission rate for a treaty, from config rather than a literal. */
    public function commissionRate(string $treaty): float
    {
        $t = strtolower(trim($treaty));

        $rate = match ($t) {
            'general' => config('reinsurance.terms.general.ceding_commission_pct'),
            'motor'   => config('reinsurance.terms.motor.provisional_commission_pct'),
            default   => null,
        };

        if ($rate === null) {
            throw new RuntimeException(
                "No ceding commission rate configured for treaty '{$treaty}'."
            );
        }

        return (float) $rate;
    }

    /**
     * Write the premium and commission lines onto a statement.
     *
     * SIGNED AS THE STATEMENT EXPECTS: premium positive because it is due TO
     * reinsurers, commission negative because it comes back. The balance is then
     * an addition rather than a rule that has to be remembered.
     *
     * @return array<int,TreatyStatementItem>
     */
    public function writeItems(TreatyStatement $statement): array
    {
        $premium = $this->cededPremiumFor(
            $statement->treaty,
            (int) $statement->underwriting_year,
            (int) $statement->quarter
        );

        $commission = $this->commissionOn($statement->treaty, $premium['by_class']);

        $written = [];

        DB::transaction(function () use ($statement, $premium, $commission, &$written) {
            // Replace rather than append, so a rebuilt quarter does not end up
            // holding two opinions about the same premium.
            $statement->items()
                ->whereIn('item_type', [TreatyStatementItem::PREMIUM, TreatyStatementItem::COMMISSION])
                ->delete();

            foreach ($premium['by_class'] as $class => $amount) {
                $written[] = $statement->items()->create([
                    'item_type'        => TreatyStatementItem::PREMIUM,
                    'regulatory_class' => $class,
                    'amount'           => $amount,
                    'basis_amount'     => $amount,
                ]);

                $written[] = $statement->items()->create([
                    'item_type'        => TreatyStatementItem::COMMISSION,
                    'regulatory_class' => $class,
                    'amount'           => -1 * ($commission['by_class'][$class] ?? 0.0),
                    'basis_amount'     => $amount,
                    'rate'             => $commission['rate'],
                ]);
            }

            $statement->update(['cession_basis' => $premium['basis']]);
        });

        return $written;
    }

    /**
     * Actions whose premium was WRITTEN between two dates.
     *
     * @return array<int,object>
     */
    private function actionsWrittenBetween(string $from, string $to): array
    {
        $statuses = "'" . implode("','", self::COUNTED_STATUSES) . "'";

        return DB::select("
            SELECT pa.id,
                   COALESCE(pa.transaction_date, DATE(pa.created_at)) AS written_on,
                   CASE WHEN pa.transaction_date IS NULL THEN 1 ELSE 0 END AS dated_by_fallback
            FROM policy_actions pa
            WHERE pa.deleted_at IS NULL
              AND pa.status IN ({$statuses})
              AND COALESCE(pa.transaction_date, DATE(pa.created_at)) BETWEEN ? AND ?
              AND EXISTS (
                  SELECT 1 FROM policy_reinsurance_details d WHERE d.action_id = pa.id
              )
            ORDER BY written_on, pa.id
        ", [$from, $to]);
    }
}
