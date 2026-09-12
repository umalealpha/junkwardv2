<?php

namespace AlphaDirect\Http\Livewire\Policy;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Services\SpecialistEndorse\SpecialistEndorseCalculator;

/**
 * READ-ONLY Endorse / Cancel change-summary popup.
 *
 * Diagnostic reader for Admin / Super Admin. For an ENDORSE or CANCEL action it
 * shows, in one table, every coverage line that was ADDED / CHANGED / DELETED
 * plus a math block that derives the pro-rata factor from the real dates and
 * reconciles Σ(deltas) × factor against the stored policy_actions.premium.
 *
 * It writes NOTHING. Every value shown is read verbatim from what the canonical
 * Rate engine (PolicyCreateController::calculatePremium / writeLineLevelProRata)
 * already stamped. The only thing recomputed here is the pro-rata FACTOR, and
 * that is for display only — it mirrors the exact expressions in
 * calculatePremium so UW / developers can see WHY a pro-rata is what it is and
 * spot when a stored total does not reconcile.
 */
class EndorseChangeSummary extends Component
{
    public $policyId;
    public $termId;
    public $actionId;

    public bool $showModal = false;
    public bool $loaded    = false;

    /** @var array<int,array> rendered change rows */
    public array $rows = [];
    /** @var array the factor / reconciliation math block */
    public array $math = [];
    public string $error = '';

    /** Motor extension premium columns — mirrors PolicyCreateController /
     *  BackdatedEndorseRefresher (private consts there). A motor line's total
     *  premium = calculated_value + Σ(these), which is what its stored delta
     *  reflects, so we show the total as "calculated value". */
    private const MOTOR_EXT_COLS = [
        'premium_wreckage_removal', 'premium_window_glass', 'premium_locks_keys',
        'premium_parts_accessories', 'premium_riot_strike', 'premium_credit_shortfall',
        'premium_med_dis_passenger', 'premium_med_dis_paid_driver',
        'premium_insured_family', 'premium_medical_expenses',
        'premium_passenger_liability', 'premium_third_party_liability',
        'premium_specified_accessories', 'premium_unorthorised_passanger_liability',
        'premium_parking_facilities', 'premium_com_windscreen',
        'premium_contigent_liability',
    ];

    /** Motor coverage master ids (COM / DOM motor section). detail + extension
     *  rows under these pcs are excluded from the sum — same split the Rate
     *  engine uses (annual keeps motor money in motor.*). */
    private const MOTOR_COVERAGE_IDS = [22, 27];

    /** MOTOR_EXT_COLS filtered to the columns that actually exist on `motor`,
     *  cached per request. The motor schema differs across environments (some
     *  ext-premium columns are absent), so — mirroring how the other child
     *  tables are guarded with Schema::hasColumn — we never SELECT a column
     *  that isn't there. Absent columns simply contribute 0 to the ext sum. */
    private ?array $motorExtColsCache = null;
    private function motorExtCols(): array
    {
        if ($this->motorExtColsCache === null) {
            $this->motorExtColsCache = array_values(array_filter(
                self::MOTOR_EXT_COLS,
                fn ($c) => Schema::hasColumn('motor', $c)
            ));
        }
        return $this->motorExtColsCache;
    }

    public function load(): void
    {
        $this->error     = '';
        $this->rows      = [];
        $this->math      = [];
        $this->loaded    = true;
        $this->showModal = true;

        // Read-only diagnostic — open to everyone. No role gate: it writes
        // nothing and only re-reads what the Rate engine already stamped.

        $action = PolicyAction::find($this->actionId);
        if (!$action) {
            $this->error = 'Action not found.';
            return;
        }
        if (!in_array($action->transaction_type, ['ENDORSE', 'CANCEL'], true)) {
            $this->error = 'Change summary is available for ENDORSE and CANCEL actions only.';
            return;
        }

        // This action's coverage tree (withTrashed so cancelled lines show).
        $pcs = DB::table('policy_coverages')
            ->where('policy_id', $this->policyId)
            ->where('action_id', $action->id)
            ->get(['id', 'coverage_id', 'deleted_at']);

        if ($pcs->isEmpty()) {
            $this->error = 'No coverages found for this action.';
            return;
        }

        $pcIds        = $pcs->pluck('id')->all();
        $motorPcIds   = $pcs->whereIn('coverage_id', self::MOTOR_COVERAGE_IDS)->pluck('id')->all();
        $nonMotorPcIds = array_values(array_diff($pcIds, $motorPcIds));

        // pc_id → section screen name (s_ScreenName), and pc_id → coverage_id.
        $screenNameByPc = DB::table('policy_coverages as pc')
            ->leftJoin('tb_cvgpccoverages as m', 'm.id', '=', 'pc.coverage_id')
            ->whereIn('pc.id', $pcIds)
            ->pluck('m.s_ScreenName', 'pc.id');
        $coverageIdByPc = $pcs->pluck('coverage_id', 'id');

        if ($action->transaction_type === 'CANCEL') {
            $this->buildCancel($action, $pcIds, $screenNameByPc, $coverageIdByPc);
        } else {
            $this->buildEndorse($action, $pcIds, $nonMotorPcIds, $screenNameByPc, $coverageIdByPc);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // ENDORSE
    // ─────────────────────────────────────────────────────────────────────
    private function buildEndorse($action, array $pcIds, array $nonMotorPcIds, $screenNameByPc, $coverageIdByPc): void
    {
        // Factor = newDays / prevDays — same source-action selection the engine
        // uses (state action matching effective_to, else containing period,
        // else latest by id).
        $newDays = Carbon::parse($action->effective_from)->diffInDays(Carbon::parse($action->effective_to)) + 1;
        $prev    = $this->stateSourceAction($action);
        $prevDays = ($prev && $prev->effective_from && $prev->effective_to)
            ? Carbon::parse($prev->effective_from)->diffInDays(Carbon::parse($prev->effective_to)) + 1
            : 365;
        $factor = $prevDays > 0 ? ($newDays / $prevDays) : 1.0;

        $deltaSum = 0.0;

        // policy_coverage_detail (non-motor pcs only — motor money lives in motor.*)
        $deltaSum += $this->collectRows(
            'policy_coverage_detail', $nonMotorPcIds, $action->id, $factor,
            'calculated_value', $screenNameByPc, $coverageIdByPc, 'coverage_id'
        );
        // motor (all pcs) — value = calculated_value + Σ extension premiums
        $deltaSum += $this->collectMotorRows($pcIds, $action->id, $factor, $screenNameByPc);
        // policy_extention_detail (non-motor pcs)
        if (Schema::hasColumn('policy_extention_detail', 'pro_rate_premium')) {
            $deltaSum += $this->collectRows(
                'policy_extention_detail', $nonMotorPcIds, $action->id, $factor,
                'extention_calculated_value', $screenNameByPc, $coverageIdByPc, 'extentions_id'
            );
        }
        // policy_specified_items (all pcs)
        if (Schema::hasColumn('policy_specified_items', 'pro_rate_premium')) {
            $deltaSum += $this->collectRows(
                'policy_specified_items', $pcIds, $action->id, $factor,
                'calculated_value', $screenNameByPc, $coverageIdByPc, 'specified_coverage_id'
            );
        }
        // motor traders ext / internal (all pcs) — pro_rate_premium is a row total
        foreach (['motor_traders' => 'Motor Traders (External)', 'motor_traders_internal' => 'Motor Traders (Internal)'] as $tbl => $label) {
            if (Schema::hasColumn($tbl, 'pro_rate_premium')) {
                $deltaSum += $this->collectMotorTraderRows($tbl, $label, $pcIds, $action->id, $factor, $screenNameByPc);
            }
        }

        // Specialist products (own tables) — delta = annual(current) − annual(prev),
        // grouped by section screen name; reconciliation uses the same helper
        // the engine calls (sumAnnualForAction).
        $specialistDelta = $this->collectSpecialistRows($action, $prev, $factor);
        $deltaSum += $specialistDelta;

        $recomputed = round($deltaSum * $factor, 2);
        $stored     = (float) ($action->premium ?? 0);

        $this->math = [
            'type'             => 'ENDORSE',
            'factor_formula'   => 'newDays ÷ prevDays',
            'numerator'        => $newDays,
            'denominator'      => $prevDays,
            'factor'           => round($factor, 6),
            'new_from'         => $this->fmtDate($action->effective_from),
            'new_to'           => $this->fmtDate($action->effective_to),
            'prev_from'        => $prev ? $this->fmtDate($prev->effective_from) : null,
            'prev_to'          => $prev ? $this->fmtDate($prev->effective_to) : null,
            'prev_label'       => $prev ? ($prev->transaction_type . ' #' . $prev->id) : 'none (all lines treated as ADD)',
            'basis_label'      => 'Σ line deltas (Δ)',
            'basis_value'      => round($deltaSum, 2),
            'specialist_delta' => round($specialistDelta, 2),
            'recomputed'       => $recomputed,
            'stored'           => round($stored, 2),
            'reconciles'       => abs($recomputed - $stored) < 0.01,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // CANCEL
    // ─────────────────────────────────────────────────────────────────────
    private function buildCancel($action, array $pcIds, $screenNameByPc, $coverageIdByPc): void
    {
        $policy = DB::table('policies')->where('id', $this->policyId)->first();
        $freq   = (int) ($policy->premium_freq ?? 3);
        $specialistProductIds = [16, 17, 18, 19, 20, 22, 23, 24];
        $isSpecialist = in_array((int) ($policy->product_id ?? 0), $specialistProductIds, true);

        $cancelFrom = Carbon::parse($action->effective_from);
        $stateTypes = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];
        $source = PolicyAction::where('policy_id', $this->policyId)
            ->where('id', '<', $action->id)
            ->whereIn('transaction_type', $stateTypes)
            ->where('effective_to', $action->effective_to)
            ->orderByDesc('id')
            ->first();

        $annual = (float) ($action->annual_premium ?? 0);

        if ($freq === 3 || $isSpecialist) {
            // Annual (or specialist) — refund unexpired portion of the term.
            $termEnd = Carbon::parse($action->effective_to);
            if ($source && $source->effective_from && $source->effective_to) {
                $termStart = Carbon::parse($source->effective_from);
                $totalDays = $termStart->diffInDays(Carbon::parse($source->effective_to)) + 1;
            } elseif (!empty($policy->term_start_date)) {
                $termStart = Carbon::parse($policy->term_start_date);
                $totalDays = $termStart->diffInDays($termEnd) + 1;
            } else {
                $termStart = null;
                $totalDays = 365;
            }
            $unexpiredDays = $cancelFrom->diffInDays($termEnd) + 1;
            $factor = $totalDays > 0 ? ($unexpiredDays / $totalDays) : 0;
            $basisValue = $annual;
            $basisLabel = 'Annual premium';
            $numerator  = $unexpiredDays;
            $denominator = $totalDays;
            $periodFrom = $termStart ? $termStart->toDateString() : null;
            $periodTo   = $termEnd->toDateString();
        } else {
            // Monthly-billed COM/DOM (freq 1/2/5) — refund unused portion of the
            // current billing PERIOD using that period's own premium.
            $periodEnd   = Carbon::parse($action->effective_to);
            $periodStart = ($source && $source->effective_from) ? Carbon::parse($source->effective_from) : $cancelFrom;
            $periodPrem  = ($source && $source->premium !== null) ? (float) $source->premium : $annual;
            $totalDays   = $periodStart->diffInDays($periodEnd) + 1;
            $unexpiredDays = $cancelFrom->diffInDays($periodEnd) + 1;
            $factor = $totalDays > 0 ? ($unexpiredDays / $totalDays) : 0;
            $basisValue = $periodPrem;
            $basisLabel = 'Billing-period premium';
            $numerator  = $unexpiredDays;
            $denominator = $totalDays;
            $periodFrom = $periodStart->toDateString();
            $periodTo   = $periodEnd->toDateString();
        }

        // Line table: every active coverage line being cancelled, with its
        // per-line refund contribution = value × factor (display arithmetic;
        // Σ = basis × factor = the stored refund). This decomposes the refund
        // the engine stored — nothing is recomputed differently.
        $this->collectCancelLines($pcIds, $factor, $screenNameByPc, $coverageIdByPc, $isSpecialist, $action);

        $recomputed = -abs(round($basisValue * $factor, 2));
        $stored     = (float) ($action->premium ?? 0);

        $this->math = [
            'type'           => 'CANCEL',
            'factor_formula' => 'unexpiredDays ÷ totalDays',
            'numerator'      => $numerator,
            'denominator'    => $denominator,
            'factor'         => round($factor, 6),
            'cancel_from'    => $this->fmtDate($action->effective_from),
            'period_from'    => $periodFrom ? Carbon::parse($periodFrom)->format('d/m/Y') : null,
            'period_to'      => $periodTo ? Carbon::parse($periodTo)->format('d/m/Y') : null,
            'freq'           => $freq,
            'basis_label'    => $basisLabel,
            'basis_value'    => round($basisValue, 2),
            'recomputed'     => $recomputed,
            'stored'         => round($stored, 2),
            'reconciles'     => abs($recomputed - $stored) < 0.01,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Collectors
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Generic changed-line collector for a value-bearing child table.
     * Returns the summed stored delta (pro_rate_premium) it added to $rows.
     */
    private function collectRows(string $table, array $pcIds, int $actionId, float $factor, string $valueCol, $screenNameByPc, $coverageIdByPc, string $ownCoverageCol): float
    {
        if (empty($pcIds)) return 0.0;

        $cols = ['id', 'policy_coverage_id', $valueCol, 'pro_rate_premium', 'deleted_at'];
        if (Schema::hasColumn($table, $ownCoverageCol)) $cols[] = $ownCoverageCol;
        if (Schema::hasColumn($table, 'custom_name'))   $cols[] = 'custom_name';

        $rows = DB::table($table)
            ->whereIn('policy_coverage_id', $pcIds)
            ->where('previousActionIdCov', $actionId)   // wizard-stamped changed rows only
            ->get($cols);

        $sum = 0.0;
        foreach ($rows as $r) {
            $delta = (float) ($r->pro_rate_premium ?? 0);
            // Skip carried-forward lines the wizard re-stamps with this action id
            // but that did not actually change (Δ 0, still live) — old == new, no
            // financial impact. Real removals (deleted) always stay. Mirrors the
            // same guard collectMotorTraderRows / collectSpecialistRows already use.
            if (abs($delta) < 0.01 && $r->deleted_at === null) continue;
            $newVal = ($r->deleted_at !== null) ? 0.0 : (float) ($r->{$valueCol} ?? 0);
            // baseline derived from what the engine stamped: delta = new − old
            $oldVal = round($newVal - $delta, 2);
            $sum += $delta;
            $this->pushRow(
                $table, (int) $r->id,
                isset($r->{$ownCoverageCol}) ? (int) $r->{$ownCoverageCol} : ((int) ($coverageIdByPc[$r->policy_coverage_id] ?? 0)),
                $screenNameByPc[$r->policy_coverage_id] ?? ($this->coverageName($r->{$ownCoverageCol} ?? null) ?: '—'),
                $this->lineLabel($table, $r->{$ownCoverageCol} ?? null, $r->custom_name ?? null, (int) $r->id),
                $r->deleted_at, $oldVal, $newVal, $newVal, $delta, round($delta * $factor, 2)
            );
        }
        return $sum;
    }

    private function collectMotorRows(array $pcIds, int $actionId, float $factor, $screenNameByPc): float
    {
        if (empty($pcIds)) return 0.0;
        $cols = array_merge(['id', 'policy_coverage_id', 'registration_no', 'calculated_value', 'pro_rate_premium', 'deleted_at'], $this->motorExtCols());
        $rows = DB::table('motor')
            ->whereIn('policy_coverage_id', $pcIds)
            ->where('previousActionIdCov', $actionId)
            ->get($cols);

        $sum = 0.0;
        foreach ($rows as $r) {
            $delta  = (float) ($r->pro_rate_premium ?? 0);
            // Skip unchanged carried-forward vehicles (Δ 0, still live); real
            // removals (deleted) stay. Same guard as the non-motor collector.
            if (abs($delta) < 0.01 && $r->deleted_at === null) continue;
            $extSum = 0.0;
            foreach ($this->motorExtCols() as $c) $extSum += (float) ($r->{$c} ?? 0);
            $newVal = ($r->deleted_at !== null) ? 0.0 : round((float) ($r->calculated_value ?? 0) + $extSum, 2);
            $oldVal = round($newVal - $delta, 2);
            $sum += $delta;
            $this->pushRow(
                'motor', (int) $r->id, 0,
                trim(($screenNameByPc[$r->policy_coverage_id] ?? 'Motor')),
                'Reg: ' . ($r->registration_no ?: '—'),
                $r->deleted_at, $oldVal, $newVal, $newVal, $delta, round($delta * $factor, 2)
            );
        }
        return $sum;
    }

    private function collectMotorTraderRows(string $table, string $label, array $pcIds, int $actionId, float $factor, $screenNameByPc): float
    {
        if (empty($pcIds)) return 0.0;
        $rows = DB::table($table)
            ->whereIn('policy_coverage_id', $pcIds)
            ->where('previousActionIdCov', $actionId)
            ->get(['id', 'policy_coverage_id', 'pro_rate_premium', 'deleted_at']);

        $sum = 0.0;
        foreach ($rows as $r) {
            $delta = (float) ($r->pro_rate_premium ?? 0);
            if (abs($delta) < 0.01 && $r->deleted_at === null) continue;
            $sum += $delta;
            $this->pushRow(
                $table, (int) $r->id, 0,
                $screenNameByPc[$r->policy_coverage_id] ?? $label,
                $label, $r->deleted_at, null, null, null, $delta, round($delta * $factor, 2)
            );
        }
        return $sum;
    }

    /**
     * Specialist per-section old→new (display) + reconciliation delta.
     * Per-section rows come from annualByScreenNameForAction; the reconciliation
     * NUMBER mirrors the engine exactly (sumAnnualForAction current − prev,
     * L3220-3229 in calculatePremium) so the ✓/✗ badge is trustworthy.
     * Returns [] / 0 for non-specialist products.
     */
    private function collectSpecialistRows($action, $prev, float $factor): float
    {
        if (!$action->term_id) return 0.0;
        try {
            $curAnnual = SpecialistEndorseCalculator::annualByScreenNameForAction((int) $this->policyId, (int) $action->id);
        } catch (\Throwable $e) {
            return 0.0; // non-specialist product / helper unavailable — no-op
        }

        // Engine baseline = immediately previous action by id (any type).
        $prevSpec = PolicyAction::where('policy_id', $this->policyId)
            ->where('id', '<', $action->id)
            ->orderByDesc('id')->first();

        $prevAnnual = [];
        if ($prevSpec) {
            try {
                $prevAnnual = SpecialistEndorseCalculator::annualByScreenNameForAction((int) $this->policyId, (int) $prevSpec->id);
            } catch (\Throwable $e) {
                $prevAnnual = [];
            }
        }

        // Per-section display rows (charge / refund) — only changed sections.
        foreach (array_unique(array_merge(array_keys($curAnnual), array_keys($prevAnnual))) as $name) {
            $new   = round((float) ($curAnnual[$name] ?? 0), 2);
            $old   = round((float) ($prevAnnual[$name] ?? 0), 2);
            $delta = round($new - $old, 2);
            if (abs($delta) < 0.01) continue;
            $deleted = ($new == 0.0 && $old > 0.0) ? Carbon::now() : null;
            $this->pushRow('specialist', 0, 0, (string) $name, 'Specialist section', $deleted, $old, $new, $new, $delta, round($delta * $factor, 2));
        }

        // Reconciliation contribution — exactly what the engine sums.
        try {
            $curSum = SpecialistEndorseCalculator::sumAnnualForAction((int) $action->id, (int) $action->term_id, (int) $this->policyId);
            $prevSum = ($prevSpec && $prevSpec->term_id)
                ? SpecialistEndorseCalculator::sumAnnualForAction((int) $prevSpec->id, (int) $prevSpec->term_id, (int) $this->policyId)
                : 0.0;
            return round($curSum - $prevSum, 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /** CANCEL: list active coverage lines with per-line refund = value × factor. */
    private function collectCancelLines(array $pcIds, float $factor, $screenNameByPc, $coverageIdByPc, bool $isSpecialist, $action): void
    {
        // Standard buckets — active rows carry the annual value being refunded.
        $buckets = [
            ['policy_coverage_detail', 'calculated_value', 'coverage_id'],
            ['policy_extention_detail', 'extention_calculated_value', 'extentions_id'],
            ['policy_specified_items', 'calculated_value', 'specified_coverage_id'],
        ];
        foreach ($buckets as [$table, $valueCol, $ownCol]) {
            if (!Schema::hasColumn($table, $valueCol)) continue;
            $sel = ['id', 'policy_coverage_id', $valueCol];
            if (Schema::hasColumn($table, $ownCol)) $sel[] = $ownCol;
            if (Schema::hasColumn($table, 'custom_name')) $sel[] = 'custom_name';
            $rows = DB::table($table)->whereIn('policy_coverage_id', $pcIds)->whereNull('deleted_at')->get($sel);
            foreach ($rows as $r) {
                $val = (float) ($r->{$valueCol} ?? 0);
                if ($val == 0.0) continue;
                $this->pushRow(
                    $table, (int) $r->id,
                    isset($r->{$ownCol}) ? (int) $r->{$ownCol} : (int) ($coverageIdByPc[$r->policy_coverage_id] ?? 0),
                    $screenNameByPc[$r->policy_coverage_id] ?? '—',
                    $this->lineLabel($table, $r->{$ownCol} ?? null, $r->custom_name ?? null, (int) $r->id),
                    Carbon::now(), $val, 0.0, $val, -abs($val), -abs(round($val * $factor, 2))
                );
            }
        }
        // Motor lines (value = calculated_value + extensions).
        $mcols = array_merge(['id', 'policy_coverage_id', 'registration_no', 'calculated_value'], $this->motorExtCols());
        foreach (DB::table('motor')->whereIn('policy_coverage_id', $pcIds)->whereNull('deleted_at')->get($mcols) as $r) {
            $ext = 0.0;
            foreach ($this->motorExtCols() as $c) $ext += (float) ($r->{$c} ?? 0);
            $val = round((float) ($r->calculated_value ?? 0) + $ext, 2);
            if ($val == 0.0) continue;
            $this->pushRow('motor', (int) $r->id, 0, $screenNameByPc[$r->policy_coverage_id] ?? 'Motor',
                'Reg: ' . ($r->registration_no ?: '—'), Carbon::now(), $val, 0.0, $val, -abs($val), -abs(round($val * $factor, 2)));
        }
        // Specialist sections.
        if ($isSpecialist && $action->term_id) {
            try {
                foreach (SpecialistEndorseCalculator::annualByScreenNameForAction((int) $this->policyId, (int) $action->id) as $name => $annual) {
                    $val = round((float) $annual, 2);
                    if ($val == 0.0) continue;
                    $this->pushRow('specialist', 0, 0, (string) $name, 'Specialist section', Carbon::now(), $val, 0.0, $val, -abs($val), -abs(round($val * $factor, 2)));
                }
            } catch (\Throwable $e) { /* no-op for non-specialist */ }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────
    private function pushRow($bucket, $lineId, $coverageId, $name, $detail, $deletedAt, $old, $new, $calc, $delta, $proRata): void
    {
        $type = $deletedAt !== null ? 'DELETED'
            : (abs((float) $old) < 0.01 ? 'ADDED' : 'CHANGED');

        $whatChanged = $type === 'DELETED'
            ? 'Removed / cancelled'
            : ($type === 'ADDED'
                ? 'New line added'
                : $this->money($old) . ' → ' . $this->money($new));

        $this->rows[] = [
            'bucket'       => $bucket,
            'line_id'      => $lineId,
            'coverage_id'  => $coverageId,
            'name'         => $name ?: '—',
            'detail'       => $detail,
            'change_type'  => $type,
            'what_changed' => $whatChanged,
            'calc_value'   => $calc,
            'delta'        => round((float) $delta, 2),
            'pro_rata'     => round((float) $proRata, 2),
        ];
    }

    /** State-establishing source action for the ENDORSE factor denominator. */
    private function stateSourceAction($action)
    {
        $stateTypes = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];
        $q = fn() => PolicyAction::where('policy_id', $this->policyId)->where('id', '<', $action->id)->whereIn('transaction_type', $stateTypes);
        return $q()->where('effective_to', $action->effective_to)->orderByDesc('id')->first()
            ?? $q()->where('effective_from', '<=', $action->effective_from)->where('effective_to', '>=', $action->effective_from)->orderByDesc('id')->first()
            ?? $q()->orderByDesc('id')->first();
    }

    /**
     * Per-line identity for the DETAIL column.
     *
     * The SECTION column always prints the PARENT coverage's s_ScreenName, so
     * every sub-coverage / extension / specified-item row of one section used
     * to render as an identical line with DETAIL "—". Two lines that look the
     * same are then impossible to read: a section with two genuine sub-rows
     * (the wizard's "+" duplicate-row feature) is indistinguishable from one
     * row duplicated in the table. Printing the row's own master name plus its
     * primary key makes that call obvious — same name AND the ids sitting next
     * to each other means a duplicated row, not two different covers.
     */
    private array $lineNameCache = [];
    private function lineLabel(string $table, $ownId, $customName, int $lineId): string
    {
        $name = trim((string) ($customName ?? ''));
        if ($name === '' && !empty($ownId)) {
            $key = $table . ':' . (int) $ownId;
            if (!array_key_exists($key, $this->lineNameCache)) {
                if ($table === 'policy_extention_detail') {
                    $resolved = Schema::hasTable('extentions')
                        ? DB::table('extentions')->where('id', $ownId)->value('s_ScreenName')
                        : null;
                } elseif ($table === 'policy_specified_items') {
                    $resolved = Schema::hasTable('specified_coverage_items')
                        ? DB::table('specified_coverage_items')->where('id', $ownId)->value('specified_name')
                        : null;
                } else {
                    $resolved = $this->coverageName($ownId);
                }
                $this->lineNameCache[$key] = $resolved;
            }
            $name = trim((string) ($this->lineNameCache[$key] ?? ''));
        }

        return $name !== '' ? $name . ' · #' . $lineId : '#' . $lineId;
    }

    private function coverageName($coverageId): ?string
    {
        if (!$coverageId) return null;
        return DB::table('tb_cvgpccoverages')->where('id', $coverageId)->value('s_ScreenName');
    }

    private function fmtDate($d): ?string
    {
        return $d ? Carbon::parse($d)->format('d/m/Y') : null;
    }

    private function money($v): string
    {
        return number_format((float) $v, 2);
    }

    public function render()
    {
        return view('v2.livewire.policy.endorse-change-summary');
    }
}
