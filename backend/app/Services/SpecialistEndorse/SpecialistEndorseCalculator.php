<?php

namespace AlphaDirect\Services\SpecialistEndorse;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pro-rata writer for the ten specialist coverage tables. Called from
 * PolicyAction::calculatePremiumEndorse AFTER the existing motor /
 * coverage_detail / extention_detail writes — those keep their settled
 * behaviour byte-for-byte. The math here mirrors the motor pattern:
 *
 *   factor   = unusedDays / totalTermDays   (caller passes this in)
 *   annual   = SUM(premium_cols)            (per row, per table)
 *   pro_rate = ROUND(annual × factor, 2)
 *   cancel   = -abs(pro_rate)               (soft-deleted in this action)
 *
 * Pro-rata is ALWAYS signed: positive on add/charge, negative on cancel.
 * Cancel is detected via deleted_at on the specialist row when the row's
 * action_id (where the column exists) or its parent policy_coverage's
 * action_id matches the current action.
 *
 * Cap rule: pro-rata is capped at the row's annual to prevent rounding
 * drift creating refunds bigger than what was ever paid.
 *
 * NEVER touches motor, COM/DOM, policy_coverage_detail, or any non-
 * specialist table.
 */
class SpecialistEndorseCalculator
{
    /**
     * Sum every specialist row attached to this action into an annual
     * total. Used by sumActionAnnualPremium so an endorse that only
     * changes a specialist row produces the right delta_annual.
     *
     * Soft-deleted rows are excluded — a cancelled row contributes
     * zero to the annual baseline (its refund flows through pro_rate).
     */
    public static function sumAnnualForAction(int $actionId, int $termId, int $policyId): float
    {
        $coverageIds = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->whereNull('deleted_at')
            ->pluck('id');
        if ($coverageIds->isEmpty()) return 0.0;

        $sum = 0.0;
        foreach (SpecialistCoverageRegistry::TABLES as $table => $meta) {
            if (!Schema::hasTable($table)) continue;
            $cols = Schema::getColumnListing($table);
            $premiumCols = array_values(array_filter($meta['premium_cols'], fn($c) => in_array($c, $cols, true)));
            if (empty($premiumCols)) continue;

            $q = DB::table($table)
                ->whereIn('policy_coverage_id', $coverageIds);
            if (in_array('deleted_at', $cols, true)) {
                $q->whereNull('deleted_at');
            }
            // car_coverages is ADDITIVE: Section 1 + Section 2 + Section 3 (it
            // has no grand total_premium column, so summing only the first col
            // dropped Sections 2 & 3 and undercounted CAR). Every other
            // specialist table lists ALTERNATIVES (a grand total with section
            // fallbacks, or annual_premium||premium) — take the FIRST present
            // to avoid double-counting. Mirrors PolicyCreateController::calculatePremium.
            // machinery_breakdown_coverages: the annual is rebuilt from the
            // section item premiums, not read off the scalar column. MB is
            // priced per section and its `premium` only became a computed total
            // later, so rows captured before that can hold a stale or blank
            // figure while every section is priced — which would pro-rata an
            // endorse or refund a cancel against the wrong annual. Falls back
            // to the scalar when the sections carry no premium.
            if ($table === 'machinery_breakdown_coverages') {
                foreach ($q->get() as $row) {
                    $sum += \AlphaDirect\Models\MachineryBreakdownCoverage::resolvedPremium($row);
                }
            } elseif ($table === 'par_coverages') {
                // Same rule as MB: PAR's annual is rebuilt from the insured-items
                // schedule, not read off the stored total_premium scalar. The
                // ENDORSE charge is (this action - baseline action), so both
                // sides must be built the same way or a stale scalar on either
                // side is charged to whatever row the operator just added.
                // See ParCoverage::resolvedPremium.
                foreach ($q->get() as $row) {
                    $sum += \AlphaDirect\Models\ParCoverage::resolvedPremium($row);
                }
            } elseif (SpecialistCoverageRegistry::isAdditive($table)) {
                $expr = implode(' + ', array_map(fn ($c) => "COALESCE({$c}, 0)", $premiumCols));
                $sum += (float) $q->sum(DB::raw("({$expr})"));
            } else {
                $sum += (float) $q->sum(DB::raw("COALESCE({$premiumCols[0]}, 0)"));
            }
        }
        return $sum;
    }

    /**
     * The action a specialist ENDORSE delta must be measured against — the
     * previous STATE OF THE WORLD, not simply "id − 1".
     *
     * Every other baseline on this codebase (motor, Fidelity, replication
     * source) already resolves the predecessor as the latest ISSUED,
     * non-deleted action strictly before this one in (effective_from, id)
     * chronology. The three specialist delta sites were the last holdouts on a
     * bare `id < current ORDER BY id DESC`, which silently picks up:
     *   - an un-issued QUOTE sitting between the last ISSUED action and this
     *     endorse (its schedule is a draft, not what the client is on cover
     *     for),
     *   - a soft-deleted action,
     *   - a future-dated action (a batch RENEW created ahead of a back-dated
     *     endorse).
     * Any of those makes the delta — and therefore the pro-rata charge for a
     * newly added schedule item — differ from the premium actually captured on
     * that item, by whatever that stray action's total happened to be.
     *
     * Falls back to the old bare-id lookup when the policy has no ISSUED
     * predecessor at all (e.g. an endorse stacked on a still-quoted new
     * business), so nothing that works today changes.
     */
    public static function baselineActionFor(int $policyId, $action)
    {
        $currentId = (int) (is_object($action) ? ($action->id ?? 0) : $action);
        if ($currentId <= 0) return null;

        $fallback = fn () => \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
            ->where('id', '<', $currentId)
            ->orderByDesc('id')
            ->first();

        $effectiveFrom = is_object($action) ? ($action->effective_from ?? null) : null;
        if (empty($effectiveFrom)) return $fallback();

        $date = \Carbon\Carbon::parse($effectiveFrom)->toDateString();

        $issued = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->where('id', '!=', $currentId)
            // Strictly before this action on the (effective_from, id) timeline.
            ->where(function ($q) use ($date, $currentId) {
                $q->where('effective_from', '<', $date)
                  ->orWhere(function ($same) use ($date, $currentId) {
                      $same->where('effective_from', '=', $date)
                           ->where('id', '<', $currentId);
                  });
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $issued ?: $fallback();
    }

    /**
     * Write pro_rate_premium / endors_flag / previousActionIdCov on every
     * specialist row attached to $actionId. Positive on active rows
     * (charge), NEGATIVE on soft-deleted rows scoped to this action
     * (refund). Idempotent: re-running for the same action overwrites
     * the same fields with the same values.
     */
    public static function writeProRata(int $actionId, int $termId, int $policyId, float $proRataFactor): array
    {
        $coverageIds = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->pluck('id'); // include soft-deleted pcs too — cancel still needs the refund
        if ($coverageIds->isEmpty()) {
            return ['rowsActive' => 0, 'rowsCancelled' => 0, 'sumActive' => 0.0, 'sumCancelled' => 0.0];
        }

        $rowsActive = 0;
        $rowsCancelled = 0;
        $sumActive = 0.0;
        $sumCancelled = 0.0;

        foreach (SpecialistCoverageRegistry::TABLES as $table => $meta) {
            if (!Schema::hasTable($table)) continue;
            $cols = Schema::getColumnListing($table);
            if (!in_array('pro_rate_premium', $cols, true)) continue;

            $premiumCols = array_values(array_filter($meta['premium_cols'], fn($c) => in_array($c, $cols, true)));
            if (empty($premiumCols)) continue;
            $primaryCol = $premiumCols[0];

            $rows = DB::table($table)
                ->whereIn('policy_coverage_id', $coverageIds)
                ->get();

            foreach ($rows as $row) {
                // car_coverages is additive (Section 1 + 2 + 3); others use the
                // primary column only. Mirrors sumAnnualForAction.
                // Machinery Breakdown rebuilds its annual from the section item
                // premiums — same reason as sumAnnualForAction above.
                if ($table === 'machinery_breakdown_coverages') {
                    $annual = \AlphaDirect\Models\MachineryBreakdownCoverage::resolvedPremium($row);
                } elseif ($table === 'par_coverages') {
                    // Schedule-derived annual — mirrors sumAnnualForAction.
                    $annual = \AlphaDirect\Models\ParCoverage::resolvedPremium($row);
                } else {
                    $annual = SpecialistCoverageRegistry::isAdditive($table)
                        ? array_sum(array_map(fn ($c) => (float) ($row->{$c} ?? 0), $premiumCols))
                        : (float) ($row->{$primaryCol} ?? 0);
                }
                $proRata = round($annual * $proRataFactor, 2);

                // Cap pro-rata to annual so rounding drift can't refund
                // more than was ever rated.
                if (abs($proRata) > abs($annual)) {
                    $proRata = $annual >= 0 ? $annual : -$annual;
                }

                $isCancelled = in_array('deleted_at', $cols, true) && !empty($row->deleted_at);
                if ($isCancelled) {
                    // Cancel pro-rata is ALWAYS negative regardless of
                    // sign of factor (matches the settled motor cancel rule).
                    $proRata = -abs($proRata);
                }

                $update = [
                    'pro_rate_premium' => $proRata,
                ];
                if (in_array('endors_flag', $cols, true)) {
                    $update['endors_flag'] = 1;
                }
                if (in_array('previousActionIdCov', $cols, true)) {
                    $update['previousActionIdCov'] = $actionId;
                }
                if (in_array('updated_at', $cols, true)) {
                    $update['updated_at'] = now();
                }

                DB::table($table)->where('id', $row->id)->update($update);

                if ($isCancelled) {
                    $rowsCancelled++;
                    $sumCancelled += $proRata;
                } else {
                    $rowsActive++;
                    $sumActive += $proRata;
                }
            }
        }

        return [
            'rowsActive' => $rowsActive,
            'rowsCancelled' => $rowsCancelled,
            'sumActive' => round($sumActive, 2),
            'sumCancelled' => round($sumCancelled, 2),
        ];
    }

    /**
     * Mark a specialist row CANCELLED in the context of $actionId by
     * soft-deleting it. The next calculatePremiumEndorse call will pick
     * up the deleted_at flag and write a negative pro_rate_premium for
     * the refund. Returns true if the row was found and soft-deleted.
     *
     * The actionId argument is kept for API symmetry with the motor
     * cancel flow (writeLineLevelProRata) but the specialist tables
     * are one-to-one with policy_coverage so deletion is per-row.
     */
    public static function cancelSpecialistRow(string $table, int $rowId, int $actionId): bool
    {
        if (!SpecialistCoverageRegistry::isSpecialistTable($table)) return false;
        if (!Schema::hasTable($table)) return false;
        $cols = Schema::getColumnListing($table);
        if (!in_array('deleted_at', $cols, true)) return false;

        $update = ['deleted_at' => now()];
        if (in_array('updated_at', $cols, true)) {
            $update['updated_at'] = now();
        }
        if (in_array('previousActionIdCov', $cols, true)) {
            $update['previousActionIdCov'] = $actionId;
        }
        if (in_array('endors_flag', $cols, true)) {
            $update['endors_flag'] = 1;
        }

        return DB::table($table)->where('id', $rowId)->update($update) > 0;
    }

    /**
     * Reverse of cancelSpecialistRow — used by REINSTATE flow. Clears
     * deleted_at on the row so it goes back to active. Pro-rata for the
     * reinstate action is recomputed by calculatePremiumEndorse against
     * the now-active row in the usual way.
     */
    public static function reinstateSpecialistRow(string $table, int $rowId, int $actionId): bool
    {
        if (!SpecialistCoverageRegistry::isSpecialistTable($table)) return false;
        if (!Schema::hasTable($table)) return false;
        $cols = Schema::getColumnListing($table);
        if (!in_array('deleted_at', $cols, true)) return false;

        $update = ['deleted_at' => null];
        if (in_array('updated_at', $cols, true)) {
            $update['updated_at'] = now();
        }
        if (in_array('previousActionIdCov', $cols, true)) {
            $update['previousActionIdCov'] = $actionId;
        }
        if (in_array('endors_flag', $cols, true)) {
            $update['endors_flag'] = 1;
        }

        return DB::table($table)->where('id', $rowId)->update($update) > 0;
    }

    // ──────────────────────────────────────────────────────────────────
    // PDF presentation — per-section pro-rata for the V2 Quote / Policy
    // Doc "Index of Sections" table. These compute display values only;
    // they do NOT write to the DB. The blade just prints what's returned,
    // so there is no calculation in the view.
    // ──────────────────────────────────────────────────────────────────

    /**
     * Pro-rata charge/refund per specialist coverage, keyed by the coverage
     * screen name (matches $section->name / $coverage->s_ScreenName in the
     * blades). Returned figures are incl-VAT: the section annuals are stored
     * GROSS-incl-VAT (canonical), so the pro-rata is already incl-VAT and
     * $vatMultiplier is NOT applied (kept in the signature for caller
     * stability — re-applying it double-counted VAT).
     *
     * Basis (mirrors the Rate banner, so the PDF reconciles with what was
     * rated): for an ENDORSE-class action, each section's pro-rata =
     *   (thisAction sectionAnnual − previousAction sectionAnnual) × factor
     * where factor = newDays / prevDays (see endorseProRataFactor). A
     * positive result is a charge (premiumInclVat), negative is a refund
     * (refundInclVat) — so add-coverage, change-premium and cancel-coverage
     * inside an endorse all surface correctly. Returns [] for non-endorse
     * actions (NEWBUSINESS etc. show no pro-rata) and for COM/DOM (no
     * specialist rows).
     *
     * @return array<string, array{premiumInclVat: float, refundInclVat: float}>
     */
    public static function proRataInclVatByScreenName(int $policyId, $action, float $vatMultiplier): array
    {
        if (!$action) return [];
        $type = $action->transaction_type ?? '';

        // Whole-policy CANCEL: every active specialist section is refunded its
        // pro-rated premium for the unexpired period. factor mirrors the Rate
        // CANCEL branch (PolicyCreateController::calculatePremium) so the sum of
        // the per-section refunds reconciles with the rated action-level refund.
        if ($type === 'CANCEL') {
            $factor = self::cancelProRataFactor($policyId, $action);
            $out = [];
            foreach (self::annualByScreenNameForAction($policyId, (int) $action->id) as $name => $annual) {
                // Section annuals are stored GROSS-incl-VAT (canonical), so the
                // refund is already incl-VAT: annual × factor. $vatMultiplier is
                // NOT re-applied — doing so double-counted VAT. See docblock.
                $refundInclVat = round($annual * $factor, 2);
                $out[$name] = [
                    'premiumInclVat' => 0.0,
                    // Positive magnitude — blade renders the leading minus.
                    'refundInclVat'  => $refundInclVat > 0 ? $refundInclVat : 0.0,
                ];
            }
            return $out;
        }

        // ENDORSE-class: per-section delta (this action − previous) × factor.
        if (!in_array($type, ['ENDORSE', 'EXTENSION-COVER', 'ENDORSE-RENEW'], true)) {
            return [];
        }

        $factor = self::endorseProRataFactor($policyId, $action);

        // Baseline = the previous ISSUED state of the world in (effective_from,
        // id) chronology — NOT "id − 1". See baselineActionFor: an un-issued
        // QUOTE, a soft-deleted action or a future-dated batch RENEW sitting
        // between the last ISSUED action and this endorse used to become the
        // baseline, which made the pro-rata for a newly added schedule item
        // differ from the premium captured against it.
        $prevAction = self::baselineActionFor($policyId, $action);

        $current = self::annualByScreenNameForAction($policyId, (int) $action->id);
        $previous = $prevAction
            ? self::annualByScreenNameForAction($policyId, (int) $prevAction->id)
            : [];

        $out = [];
        foreach (array_unique(array_merge(array_keys($current), array_keys($previous))) as $name) {
            $delta   = ($current[$name] ?? 0.0) - ($previous[$name] ?? 0.0);
            // Section annuals are stored GROSS-incl-VAT (canonical — same basis
            // as the Rate banner, which both quote blades treat as incl-VAT by
            // deriving Excl = total ÷ (1+VAT)). The pro-rata charge/refund is
            // therefore ALREADY incl-VAT: delta × factor. Re-applying
            // $vatMultiplier double-counted VAT (e.g. P 4,154.79 → P 4,736.47).
            $inclVat = round($delta * $factor, 2);
            $out[$name] = [
                // Charge positive in the Premium column; refund (cancel/decrease)
                // kept as a POSITIVE magnitude here — the blade renders it with a
                // leading minus ("P - X.XX"), matching the canonical COM/DOM
                // v2-quote-sheet Pro Rata Refund convention.
                'premiumInclVat' => $inclVat > 0 ? $inclVat : 0.0,
                'refundInclVat'  => $inclVat < 0 ? abs($inclVat) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Annual specialist premium for one action, grouped by coverage screen
     * name. Sums each table's premium column(s) — additive tables (car_coverages)
     * sum all sections, others take premium_cols[0] — matching sumAnnualForAction,
     * for the action's non-deleted coverages —
     * so a cancelled (soft-deleted) coverage drops out of the current action
     * and reads as a refund delta against the previous action.
     *
     * @return array<string, float>
     */
    public static function annualByScreenNameForAction(int $policyId, int $actionId): array
    {
        $coverages = DB::table('policy_coverages as pc')
            ->join('tb_cvgpccoverages as m', 'm.id', '=', 'pc.coverage_id')
            ->where('pc.policy_id', $policyId)
            ->where('pc.action_id', $actionId)
            ->whereNull('pc.deleted_at')
            ->get(['pc.id', 'm.s_ScreenName as name']);
        if ($coverages->isEmpty()) return [];

        $nameByPc = [];
        foreach ($coverages as $c) {
            $nameByPc[(int) $c->id] = (string) $c->name;
        }
        $pcIds = array_keys($nameByPc);

        $out = [];
        // Track which coverages actually carry a specialist row, so the misc
        // (Miscellaneous Items) subtotal below is only folded into specialist
        // sections — never into COM/DOM/motor, whose specified-items pro-rata
        // flows through its own per-section path (would double-count here).
        $specialistPcIds = [];
        foreach (SpecialistCoverageRegistry::TABLES as $table => $meta) {
            if (!Schema::hasTable($table)) continue;
            $cols = Schema::getColumnListing($table);
            $premiumCols = array_values(array_filter($meta['premium_cols'], fn ($c) => in_array($c, $cols, true)));
            if (empty($premiumCols)) continue;
            // car_coverages is additive (Section 1 + 2 + 3); others use the
            // primary column only. Mirrors sumAnnualForAction.
            $annualExpr = SpecialistCoverageRegistry::isAdditive($table)
                ? implode(' + ', array_map(fn ($c) => "COALESCE({$c}, 0)", $premiumCols))
                : "COALESCE({$premiumCols[0]}, 0)";

            $q = DB::table($table)->whereIn('policy_coverage_id', $pcIds);
            if (in_array('deleted_at', $cols, true)) {
                $q->whereNull('deleted_at');
            }
            // PAR resolves its annual from the insured-items schedule in PHP
            // rather than off the stored total_premium scalar — same rule as
            // sumAnnualForAction/writeProRata, so the Change Summary and the
            // quote-sheet pro-rata column show the number the engine charges.
            // See ParCoverage::resolvedPremium.
            $isPar = ($table === 'par_coverages');
            $selects = $isPar
                ? ['policy_coverage_id', 'insured_items', 'total_premium']
                : ['policy_coverage_id', DB::raw("({$annualExpr}) as annual")];

            foreach ($q->get($selects) as $row) {
                $name = $nameByPc[(int) $row->policy_coverage_id] ?? null;
                if ($name === null) continue;
                $annual = $isPar
                    ? \AlphaDirect\Models\ParCoverage::resolvedPremium($row)
                    : (float) $row->annual;
                $out[$name] = ($out[$name] ?? 0.0) + $annual;
                $specialistPcIds[(int) $row->policy_coverage_id] = true;
            }
        }

        // Miscellaneous Items subtotal (policy_specified_items.calculated_value)
        // is stored OUTSIDE the specialist coverage's premium column — the
        // quote-sheet premium column already adds it inline, so the per-section
        // pro-rata must too or the misc premium silently drops out of the
        // engineering/specialist pro-rata (and the delta vs the previous action
        // wouldn't reflect an added/removed misc item). Scoped to specialist
        // pcs only (see $specialistPcIds) so COM/DOM specified items are
        // untouched. Active (non-deleted) rows only — a cancelled misc item
        // drops to zero here and surfaces as a refund delta against the prior
        // action, mirroring the specialist-table treatment above.
        if (!empty($specialistPcIds) && Schema::hasTable('policy_specified_items')) {
            $miscRows = DB::table('policy_specified_items')
                ->whereIn('policy_coverage_id', array_keys($specialistPcIds))
                ->whereNull('deleted_at')
                ->get(['policy_coverage_id', 'calculated_value']);
            foreach ($miscRows as $row) {
                $name = $nameByPc[(int) $row->policy_coverage_id] ?? null;
                if ($name === null) continue;
                $out[$name] = ($out[$name] ?? 0.0) + (float) $row->calculated_value;
            }
        }

        return $out;
    }

    /**
     * ENDORSE pro-rata time factor = newDays / prevDays. Mirrors
     * PolicyCreateController::calculatePremium's ENDORSE branch (state-action
     * source by matching effective_to, then containing period, then latest)
     * so the per-section pro-rata reconciles with the rated banner. Kept
     * here (not extracted from the controller) to avoid touching the settled
     * Rate path.
     */
    public static function endorseProRataFactor(int $policyId, $action): float
    {
        if (empty($action->effective_from) || empty($action->effective_to)) return 1.0;

        $newDays = \Carbon\Carbon::parse($action->effective_from)
            ->diffInDays(\Carbon\Carbon::parse($action->effective_to)) + 1;

        $stateTypes = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];
        $base = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
            ->where('id', '<', $action->id)
            ->whereIn('transaction_type', $stateTypes);

        $prev = (clone $base)->where('effective_to', $action->effective_to)->orderByDesc('id')->first()
            ?: (clone $base)
                ->where('effective_from', '<=', $action->effective_from)
                ->where('effective_to', '>=', $action->effective_from)
                ->orderByDesc('id')->first()
            ?: (clone $base)->orderByDesc('id')->first();

        $prevDays = ($prev && $prev->effective_from && $prev->effective_to)
            ? \Carbon\Carbon::parse($prev->effective_from)->diffInDays(\Carbon\Carbon::parse($prev->effective_to)) + 1
            : 365;

        return $prevDays > 0 ? $newDays / $prevDays : 1.0;
    }

    /**
     * Whole-policy CANCEL refund factor. Mirrors the COM/DOM cancel logic in
     * PolicyCreateController::calculatePremium so a specialist cancel refund
     * matches the rated banner:
     *   - Annual (premium_freq 3): unexpiredDays / totalTermDays, where
     *     totalTermDays comes from the source state action (NEWBUSINESS/RENEW/…)
     *     whose effective_to matches the cancel, falling back to the policy
     *     term then 365.
     *   - Monthly (1/2/5): (1 / 12) × (daysLeftInMonth / daysInMonth).
     * Returns a positive factor; the caller applies it to each section's
     * annual premium to get the refund magnitude.
     */
    public static function cancelProRataFactor(int $policyId, $action): float
    {
        if (empty($action->effective_from) || empty($action->effective_to)) return 0.0;

        $policy = \AlphaDirect\Policy::find($policyId);
        $cancelFrom = \Carbon\Carbon::parse($action->effective_from);

        // Specialist products are paid as an annual premium up front, so the
        // cancel refund factor is ALWAYS date-based (unexpired days of the term
        // ÷ total term days), regardless of premium_freq — matching the Rate
        // CANCEL branch for specialist products. (No per-month instalment
        // refund; that only applies to monthly-billed COM/DOM.)
        $termEnd = \Carbon\Carbon::parse($action->effective_to);

        $stateTypes = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];
        $source = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
            ->where('id', '<', $action->id)
            ->whereIn('transaction_type', $stateTypes)
            ->where('effective_to', $action->effective_to)
            ->orderByDesc('id')
            ->first();

        if ($source && $source->effective_from && $source->effective_to) {
            $totalTermDays = \Carbon\Carbon::parse($source->effective_from)
                ->diffInDays(\Carbon\Carbon::parse($source->effective_to)) + 1;
        } elseif (!empty($policy->term_start_date)) {
            $totalTermDays = \Carbon\Carbon::parse($policy->term_start_date)->diffInDays($termEnd) + 1;
        } else {
            $totalTermDays = 365;
        }

        $unexpiredDays = $cancelFrom->diffInDays($termEnd) + 1;
        return $totalTermDays > 0 ? $unexpiredDays / $totalTermDays : 0.0;
    }
}
