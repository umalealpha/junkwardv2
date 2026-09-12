<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\FacPlacement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-generated FAC entries.
 *
 * On the master sheet a slip does not produce one line — it produces one line
 * per premium instalment. Slip 2024-149 (M P MINING, monthly) carries three
 * lines with three different periods; slip 2024-060 carries two. Somebody is
 * typing each of those by hand every month off the same slip terms, which is
 * where drift and omissions come from.
 *
 * This service closes that: when Graphite books a new transaction on a policy
 * that already carries a live facultative placement, the next placement line is
 * raised from the SAME slip terms — same counterparty, same cession %, same
 * commission %, same VAT treatment — with the premium scaled to the new
 * transaction.
 *
 * Generated rows land as `draft` and are NOT counted in the payable until a
 * human confirms them. A register that quietly creates its own liabilities is
 * not a control, it is a second spreadsheet.
 */
class FacAutoEntryService
{
    public function __construct(private FacRegisterService $register)
    {
    }

    /**
     * Raise draft placements for policy transactions booked since the lookback
     * window that are not yet represented in the register.
     *
     * @return array{created:int, considered:int, skipped:array<int,string>}
     */
    public function run(?int $lookbackDays = null): array
    {
        if (!config('fac.auto_entries.enabled', true)) {
            return ['created' => 0, 'considered' => 0, 'skipped' => ['Auto-generation is switched off in configuration.']];
        }

        $lookbackDays = $lookbackDays ?? (int) config('fac.auto_entries.lookback_days', 45);
        $since        = now()->subDays($lookbackDays)->toDateString();

        // Policies that already carry at least one live, non-reversal placement.
        // Auto-generation only ever CONTINUES an existing arrangement; it never
        // invents a first placement, because that is an underwriting decision.
        $templates = FacPlacement::query()
            ->whereNull('deleted_at')
            ->where('is_reversal', false)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('policy_id')
            ->whereIn('policy_type', ['Monthly', 'Quarterly'])
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($p) => $p->policy_id . '|' . $p->fac_slip_no . '|' . $p->counterparty_id);

        $created    = 0;
        $considered = 0;
        $skipped    = [];

        foreach ($templates as $tpl) {
            $actions = DB::table('policy_actions')
                ->where('policy_id', $tpl->policy_id)
                ->whereNull('deleted_at')
                ->whereDate('transaction_date', '>=', $since)
                ->orderBy('id')
                ->select('id', 'transaction_date', 'premium', 'effective_from', 'effective_to')
                ->get();

            foreach ($actions as $a) {
                $considered++;

                // Already registered against this transaction on this slip?
                $exists = FacPlacement::where('policy_id', $tpl->policy_id)
                    ->where('policy_action_id', $a->id)
                    ->where('counterparty_id', $tpl->counterparty_id)
                    ->where('fac_slip_no', $tpl->fac_slip_no)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                $actionPremium = (float) ($a->premium ?? 0);
                if ($actionPremium <= 0) {
                    $skipped[] = sprintf(
                        '%s transaction %d: no premium on the transaction, so nothing could be ceded.',
                        $tpl->policy_number,
                        $a->id
                    );
                    continue;
                }

                // The cession share is taken from the template line's own
                // proportion of its transaction — the underwriter's decision,
                // carried forward. It is never re-derived from treaty tables,
                // which are empty.
                $sharePct = $this->shareOf($tpl);
                if ($sharePct === null) {
                    $skipped[] = sprintf(
                        '%s slip %s: the original line has no risk %% recorded, so the share cannot be carried forward.',
                        $tpl->policy_number,
                        $tpl->fac_slip_no ?? '-'
                    );
                    continue;
                }

                $gross = round($actionPremium * $sharePct, 2);
                if ($gross <= 0) {
                    continue;
                }

                $amounts = $this->register->computeAmounts([
                    'gross_ceded_premium' => $gross,
                    'commission_pct'      => $tpl->commission_pct,
                    'vat_applicable'      => (bool) $tpl->vat_applicable,
                    'vat_rate'            => (float) $tpl->vat_rate,
                    'currency'            => $tpl->currency,
                    'period_from'         => $a->effective_from,
                ]);

                $row = FacPlacement::create(array_merge($amounts, [
                    'fac_reference'             => $this->register->nextReference(),
                    'fac_slip_no'               => $tpl->fac_slip_no,
                    'fac_slip_id'               => $tpl->fac_slip_id,
                    'financial_year'            => $this->register->financialYearFor($a->transaction_date),
                    'placement_type'            => $tpl->placement_type,
                    'policy_id'                 => $tpl->policy_id,
                    'policy_number'             => $tpl->policy_number,
                    'policy_action_id'          => $a->id,
                    'insured_name'              => $tpl->insured_name,
                    'policy_type'               => $tpl->policy_type,
                    'period_from'               => $a->effective_from ?: $tpl->period_from,
                    'period_to'                 => $a->effective_to ?: $tpl->period_to,
                    'policy_status'             => $tpl->policy_status,
                    'policy_synced_at'          => now(),
                    'policy_in_graphite'        => true,
                    'policy_active_in_graphite' => (bool) $tpl->policy_active_in_graphite,
                    'reinsurance_group_id'      => $tpl->reinsurance_group_id,
                    'ri_group_label'            => $tpl->ri_group_label,
                    'risk_pct'                  => $tpl->risk_pct,
                    'counterparty_id'           => $tpl->counterparty_id,
                    'counterparty_name'         => $tpl->counterparty_name,
                    'risk_carrier'              => $tpl->risk_carrier,
                    'underwriter_id'            => $tpl->underwriter_id,
                    'underwriter_name'          => $tpl->underwriter_name,
                    'ppw_due_date'              => $this->ppwFor($tpl, $a),
                    'status'                    => config('fac.auto_entries.create_as_status', 'draft'),
                    'source'                    => 'auto',
                    'source_ref'                => sprintf(
                        'Carried forward from %s on policy transaction %d',
                        $tpl->fac_reference,
                        $a->id
                    ),
                ]));

                $this->register->recordEvent($row, 'auto_generated', sprintf(
                    'Draft placement raised from slip %s for the %s instalment. Confirm it before it counts towards the payable.',
                    $tpl->fac_slip_no ?? '-',
                    $a->transaction_date ? Carbon::parse($a->transaction_date)->format('M Y') : 'new'
                ), ['template' => $tpl->fac_reference, 'policy_action_id' => $a->id]);

                $created++;
            }
        }

        return ['created' => $created, 'considered' => $considered, 'skipped' => $skipped];
    }

    /**
     * The template line's ceded premium as a proportion of the transaction it
     * was written against. Prefers the recorded risk %; falls back to the actual
     * ratio when the risk % was never captured.
     */
    private function shareOf(FacPlacement $tpl): ?float
    {
        if ($tpl->risk_pct !== null && (float) $tpl->risk_pct > 0) {
            return (float) $tpl->risk_pct;
        }

        if (!$tpl->policy_action_id) {
            return null;
        }

        $basePremium = (float) DB::table('policy_actions')
            ->where('id', $tpl->policy_action_id)
            ->value('premium');

        if ($basePremium <= 0) {
            return null;
        }

        return round(((float) $tpl->gross_ceded_premium) / $basePremium, 8);
    }

    /**
     * PPW for the new instalment. The warranty period the underwriter set on the
     * original line is carried forward from the new transaction's start, so a
     * 30-day warranty stays a 30-day warranty. No PPW on the template means no
     * PPW here — never a made-up date.
     */
    private function ppwFor(FacPlacement $tpl, $action): ?string
    {
        if (!$tpl->ppw_due_date || !$tpl->period_from) {
            return null;
        }

        $days  = Carbon::parse($tpl->period_from)->diffInDays(Carbon::parse($tpl->ppw_due_date), false);
        $start = $action->effective_from ?: $action->transaction_date;

        if ($days <= 0 || !$start) {
            return null;
        }

        return Carbon::parse($start)->addDays($days)->toDateString();
    }
}
