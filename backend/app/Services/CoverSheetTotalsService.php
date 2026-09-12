<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\PolicyAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The two figures printed on the face of the policy cover sheet: total sum
 * insured and total premium.
 *
 * PREMIUM — `policy_actions.premium`, the value behind the Rate banner. That is
 * the canonical whole-policy total for the action, gross and inclusive of VAT,
 * and the V2 quote's own Total Premium is defined as exactly this figure rather
 * than as a sum of its sections. Nothing is recomputed, so the sheet cannot
 * disagree with the banner.
 *
 * SUM INSURED — a port of the reference SQL supplied by the user (2026-09-09),
 * which is the agreed definition of a policy's total sum insured. Each branch
 * below mirrors one UNION arm of that query, table for table, column for
 * column, filter for filter:
 *
 *   policy_coverage_detail.coverage_value            deleted_at IS NULL
 *   policy_specified_items.sum_insured               deleted_at IS NULL
 *   motor.coverage_value                             deleted_at IS NULL OR ''
 *   motor_traders            loss_or_damage + third_party_liability
 *                            + medical_benefits          (no delete filter)
 *   motor_traders_internal   the same three columns      (no delete filter)
 *   policy_extention_detail.extention_coverage_value  deleted_at IS NULL,
 *                            type='Extention', parent coverage NOT IN (22,27)
 *   policy_coverages_data.amount_to_be_guaranteed     (no delete filter)
 *
 * The parts that look wrong are deliberate, and every one of them was a real
 * bug in the first cut of this service:
 *
 *  - motor stores '' as well as NULL in deleted_at, so whereNull alone drops
 *    live vehicles.
 *  - the motor-traders tables carry thirteen *_coverage_value columns but only
 *    THREE count towards sum insured; summing all thirteen overstates.
 *  - extensions use `extention_coverage_value`. `extention_sum_insured` also
 *    exists (added 2026-05-08) but is not the figure this total uses.
 *  - extensions on motor coverages 22 and 27 are excluded — those are the
 *    per-vehicle covers, already counted through `motor`.
 *  - policy_coverages_data has NO deleted_at column, keys on a camel-cased
 *    string id, and stores amount_to_be_guaranteed as a STRING that can be ''.
 *    Referencing deleted_at there throws, and because this whole block is
 *    guarded, that exception silently nulled the ENTIRE figure.
 *
 * Every branch is scoped through the action's own coverage ids.
 * policy_coverages and its children are versioned per action; omitting
 * action_id mixes transactions.
 *
 * `parts` on the result breaks the figure down per source, in the same shape as
 * the reference query's per-source columns, so a total that looks wrong can be
 * traced against it directly.
 */
class CoverSheetTotalsService
{
    /**
     * Products whose sums insured cannot be totalled from these tables.
     * Anything listed here prints the schedule pointer instead of a figure.
     */
    public const INCOMPLETE_PRODUCTS = [];

    /**
     * The motor-traders columns that count towards sum insured — three of the
     * thirteen `*_coverage_value` columns on each table, per the reference
     * query. The other ten are sub-limits and must NOT be added, or the total
     * overstates. Identical on motor_traders and motor_traders_internal.
     */
    public const MOTOR_TRADERS_SI_COLUMNS = [
        'loss_or_damage_coverage_value',
        'third_party_liability_coverage_value',
        'medical_benefits_coverage_value',
    ];

    /**
     * Motor coverages whose extensions are excluded from the extension arm —
     * they are the per-vehicle covers, already counted via `motor`.
     */
    public const MOTOR_COVERAGE_IDS = [22, 27];

    /**
     * @return array{sumInsured:?float, premium:?float, complete:bool, parts:array<string,float>}
     */
    public function forAction(int $policyId, ?int $actionId, ?int $productId = null): array
    {
        $premium = $this->premium($policyId, $actionId);
        $none    = ['sumInsured' => null, 'premium' => $premium, 'complete' => false, 'parts' => []];

        if (!$actionId) {
            // Without an action there is no versioned coverage tree to total.
            return $none;
        }

        $parts = [];

        try {
            // coverage_id comes back too, so the extension arm can drop the
            // motor covers without a second round trip.
            $coverages = DB::table('policy_coverages')
                ->where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereNull('deleted_at')
                ->get(['id', 'coverage_id']);

            if ($coverages->isEmpty()) {
                return $none;
            }

            $coverageIds = $coverages->pluck('id');

            // Extensions exclude the per-vehicle motor covers (22, 27).
            $extensionCoverageIds = $coverages
                ->reject(fn ($c) => in_array((int) $c->coverage_id, self::MOTOR_COVERAGE_IDS, true))
                ->pluck('id');

            $parts['coverage_detail'] = (float) DB::table('policy_coverage_detail')
                ->whereIn('policy_coverage_id', $coverageIds)
                ->whereNull('deleted_at')
                ->sum('coverage_value');

            // Miscellaneous items share this table with specified items, so
            // both are counted here.
            $parts['specified_items'] = (float) DB::table('policy_specified_items')
                ->whereIn('policy_coverage_id', $coverageIds)
                ->whereNull('deleted_at')
                ->sum('sum_insured');

            // deleted_at is a STRING here and live rows carry '' as well as
            // NULL. whereNull alone silently drops vehicles.
            $parts['motor'] = (float) DB::table('motor')
                ->whereIn('policy_coverage_id', $coverageIds)
                ->where(function ($q) {
                    $q->whereNull('deleted_at')->orWhere('deleted_at', '');
                })
                ->sum('coverage_value');

            // No delete filter on either motor-traders table, matching the
            // reference query.
            $parts['motor_traders']          = $this->motorTradersTotal('motor_traders', $coverageIds);
            $parts['motor_traders_internal'] = $this->motorTradersTotal('motor_traders_internal', $coverageIds);

            $parts['extension'] = $extensionCoverageIds->isEmpty()
                ? 0.0
                : (float) DB::table('policy_extention_detail')
                    ->whereIn('policy_coverage_id', $extensionCoverageIds)
                    ->whereNull('deleted_at')
                    ->where('type', 'Extention')
                    ->sum('extention_coverage_value');

            // No deleted_at on this table; the id is a camel-cased string and
            // the amount is a string that can be ''.
            $parts['fidelity'] = (float) DB::table('policy_coverages_data')
                ->whereIn(DB::raw('CAST(policyCoverageID AS UNSIGNED)'), $coverageIds)
                ->selectRaw("COALESCE(SUM(CAST(NULLIF(amount_to_be_guaranteed,'') AS DECIMAL(20,2))),0) AS si")
                ->value('si');
        } catch (\Throwable $e) {
            // A schema difference, or a database that is briefly unreachable,
            // must not stop the sheet printing — it prints the schedule
            // pointer instead of a figure. Logged loudly, because a total
            // silently becoming "no figure" is exactly how the fidelity
            // deleted_at bug hid.
            Log::warning('cover_sheet.sum_insured_failed', [
                'policy_id' => $policyId,
                'action_id' => $actionId,
                'error'     => $e->getMessage(),
            ]);

            return $none;
        }

        $total = array_sum($parts);

        // A zero total is not a real answer — these policies all insure
        // something — so treat it as "no figure" rather than print BWP 0.00
        // on a client document.
        if ($total <= 0) {
            return ['sumInsured' => null, 'premium' => $premium, 'complete' => false, 'parts' => $parts];
        }

        return [
            'sumInsured' => $total,
            'premium'    => $premium,
            'complete'   => !in_array((int) $productId, self::INCOMPLETE_PRODUCTS, true),
            'parts'      => $parts,
        ];
    }

    /**
     * Sum of the three sum-insured columns on a motor-traders table.
     *
     * Column names come from MOTOR_TRADERS_SI_COLUMNS, never from input, so
     * the raw expression cannot be injected. IFNULL matches the reference
     * query's treatment of empty columns as zero.
     */
    private function motorTradersTotal(string $table, $coverageIds): float
    {
        $terms = array_map(
            fn (string $col) => "IFNULL(`{$col}`,0)",
            self::MOTOR_TRADERS_SI_COLUMNS
        );

        return (float) DB::table($table)
            ->whereIn('policy_coverage_id', $coverageIds)
            ->selectRaw('COALESCE(SUM(' . implode(' + ', $terms) . '),0) AS si')
            ->value('si');
    }

    /**
     * The canonical premium for the action — the Rate banner value, gross and
     * including VAT. Never a sum of sections: the V2 quote's own total is
     * defined as this figure, and a per-section sum has historically
     * disagreed with it.
     */
    private function premium(int $policyId, ?int $actionId): ?float
    {
        try {
            $premium = $actionId
                ? PolicyAction::where('id', $actionId)->value('premium')
                : null;

            // Falls back to the policy only when the action carries nothing —
            // an unrated transaction, for instance.
            if ($premium === null) {
                $premium = DB::table('policies')->where('id', $policyId)->value('premium');
            }
        } catch (\Throwable $e) {
            // Guarded like the sums above: a database problem must degrade the
            // figure, not fail the whole sheet.
            Log::warning('cover_sheet.premium_lookup_failed', [
                'policy_id' => $policyId,
                'action_id' => $actionId,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }

        return $premium === null ? null : (float) $premium;
    }
}
