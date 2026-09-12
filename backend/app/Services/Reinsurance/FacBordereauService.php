<?php

namespace AlphaDirect\Services\Reinsurance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The cession bordereau — what goes to the broker or the reinsurer for a period.
 *
 * This is NOT the register export. The export is a flat data extract of every
 * column, ordered for a spreadsheet. A bordereau is a statement of account
 * addressed to one counterparty: it groups by reinsurer, subtotals each one, and
 * carries a header saying whose it is and what period it covers, so the recipient
 * can agree it line by line against their own book. Month-end control C7 exists to
 * reconcile the cession to exactly this document.
 *
 * WHAT IS DELIBERATELY LEFT OPEN. The broker's required layout has not been
 * supplied — there is no sample bordereau on file. So the columns here are the ones
 * the register already holds and that a cession bordereau conventionally carries,
 * and the format is expected to be marked up and corrected once Reinsurance has put
 * a real one in front of the broker. Getting the figures right is the part that
 * cannot be renegotiated; the column order can.
 *
 * DRAFTS ARE EXCLUDED, and this is the important rule. A draft is a placement no
 * reinsurer has signed. Submitting one on a bordereau would be claiming a cession
 * against a reinsurer who has not agreed to carry it. Reversals are excluded from
 * the lines and shown separately, because a bordereau that nets a reversal into a
 * subtotal hides the movement the recipient is trying to agree.
 */
class FacBordereauService
{
    /** Placements that never belong on a bordereau. */
    private const EXCLUDED_STATUSES = ['draft', 'cancelled'];

    /**
     * The bordereau for a period, grouped by counterparty.
     *
     * @param  array{period_from?:string|null,period_to?:string|null,financial_year?:string|null,counterparty_id?:int|null,placement_type?:string|null,currency?:string|null}  $filters
     * @return array<string,mixed>
     */
    public function build(array $filters = []): array
    {
        $rows = $this->lines($filters);

        $groups = [];
        foreach ($rows as $r) {
            // Keyed on counterparty AND currency. A reinsurer written in two
            // currencies gets two statements — summing across them would produce a
            // total in no currency at all.
            $key = ($r->counterparty_id ?? 0) . '|' . strtoupper((string) $r->currency);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'counterpartyId'   => $r->counterparty_id,
                    'counterpartyName' => $r->counterparty_name ?: '(unnamed)',
                    'currency'         => strtoupper((string) $r->currency),
                    'lines'            => [],
                    'subtotals'        => $this->emptyTotals(),
                ];
            }

            $groups[$key]['lines'][] = $this->lineJson($r);
            $groups[$key]['subtotals'] = $this->addTo($groups[$key]['subtotals'], $r);
        }

        // Reinsurer name, then currency — the order a recipient expects to find
        // their own section in.
        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => [$a['counterpartyName'], $a['currency']]
            <=> [$b['counterpartyName'], $b['currency']]);

        foreach ($groups as &$g) {
            $g['subtotals'] = $this->roundTotals($g['subtotals']);
        }
        unset($g);

        $grand = $this->emptyTotals();
        foreach ($rows as $r) {
            $grand = $this->addTo($grand, $r);
        }

        $reversals = $this->reversals($filters)->map(fn ($r) => $this->lineJson($r))->values()->all();

        return [
            'header' => [
                'cedant'        => 'Alpha Direct Insurance Company (Pty) Ltd',
                'statement'     => 'Facultative Cession Bordereau',
                'periodFrom'    => $filters['period_from'] ?? null,
                'periodTo'      => $filters['period_to'] ?? null,
                'financialYear' => $filters['financial_year'] ?? null,
                'placementType' => $filters['placement_type'] ?? null,
                'preparedAt'    => now()->toDateTimeString(),
                // Said on the face of the document, so a recipient is never left to
                // wonder whether an unsigned placement is included.
                'basis'         => 'Signed placements only. Drafts awaiting a reinsurer '
                    . 'signature and cancelled placements are excluded. Reversals are '
                    . 'listed separately and are not netted into the subtotals.',
            ],
            'groups'     => $groups,
            'reversals'  => $reversals,
            'grandTotal' => $this->roundTotals($grand),
            // Present ONLY on an empty bordereau, and null otherwise so a caller
            // can branch on it without counting rows. Computed lazily because it
            // costs four queries nobody needs on a document that has lines.
            'emptyReason' => ($groups === [] && $reversals === [])
                ? $this->diagnose($filters)
                : null,
        ];
    }

    /** The signed cession lines for the period. @return \Illuminate\Support\Collection<int,object> */
    public function lines(array $filters = [])
    {
        return $this->baseQuery($filters)
            ->where('is_reversal', false)
            ->orderBy('counterparty_name')
            ->orderBy('currency')
            ->orderBy('policy_number')
            ->orderBy('id')
            ->get();
    }

    /** Reversals, listed apart so a subtotal is never quietly netted. */
    public function reversals(array $filters = [])
    {
        return $this->baseQuery($filters)
            ->where('is_reversal', true)
            ->orderBy('counterparty_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Why a bordereau came back empty — only computed when it did.
     *
     * A NIL BORDEREAU AND A BROKEN ONE LOOK IDENTICAL, and Reinsurance reported
     * this module as "not recording the placements" on the strength of one. It
     * was recording them: the page defaults the period to the current calendar
     * month, the period is tested on the slip signing date, and every real slip
     * had been signed in an earlier month. The document was correct and the
     * screen gave no way to tell that from a fault.
     *
     * So an empty result now says how much there is to find and where. This is
     * the same rule the brokerage builder follows in refusing to write a nil line
     * — "a nil brokerage and an uncalculated one look identical on a rendered
     * account" — applied to a document rather than a figure.
     *
     * THE UNDATED COUNT IS THE ONE THAT MATTERS MOST. A placement with no
     * slip_signed_date is invisible to EVERY dated bordereau, not just this one,
     * because whereDate on a null column matches nothing. Widening the period
     * will never surface it; only filing the signed slip will.
     *
     * @return array<string,mixed>
     */
    private function diagnose(array $filters): array
    {
        // The same scope with the PERIOD dropped: what a wider window would find.
        $withoutPeriod = $filters;
        unset($withoutPeriod['period_from'], $withoutPeriod['period_to']);

        $signedOutside = (clone $this->baseQuery($withoutPeriod))
            ->whereNotNull('slip_signed_date')
            ->count();

        $undated = (clone $this->baseQuery($withoutPeriod))
            ->whereNull('slip_signed_date')
            ->count();

        $range = (clone $this->baseQuery($withoutPeriod))
            ->whereNotNull('slip_signed_date')
            ->selectRaw('MIN(slip_signed_date) AS lo, MAX(slip_signed_date) AS hi')
            ->first();

        // Drafts and cancelled are excluded by baseQuery, so they are counted
        // here on their own — a draft is a placement no reinsurer has signed and
        // belongs on no bordereau, but "excluded" and "absent" are not the same
        // answer to somebody looking for a line they know they captured.
        $blocked = DB::table('fac_placements')
            ->whereNull('deleted_at')
            ->whereIn('status', self::EXCLUDED_STATUSES)
            ->when(
                !empty($withoutPeriod['counterparty_id']),
                fn ($q) => $q->where('counterparty_id', (int) $withoutPeriod['counterparty_id'])
            )
            ->selectRaw("
                SUM(CASE WHEN status = 'draft'     THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
            ")
            ->first();

        $reasons = [];

        if ($signedOutside > 0) {
            $reasons[] = sprintf(
                '%d signed %s outside this period — the register holds slips signed between %s and %s. '
                . 'The period is tested on the date the reinsurer SIGNED, not on capture.',
                $signedOutside,
                $signedOutside === 1 ? 'placement' : 'placements',
                substr((string) ($range->lo ?? ''), 0, 10) ?: '(unknown)',
                substr((string) ($range->hi ?? ''), 0, 10) ?: '(unknown)'
            );
        }

        if ($undated > 0) {
            $reasons[] = sprintf(
                '%d %s no signed date, so %s on NO dated bordereau until the signed slip is filed.',
                $undated,
                $undated === 1 ? 'placement has' : 'placements have',
                $undated === 1 ? 'it appears' : 'they appear'
            );
        }

        if ((int) ($blocked->drafts ?? 0) > 0) {
            $reasons[] = sprintf(
                '%d draft %s excluded — a draft is a placement no reinsurer has signed.',
                (int) $blocked->drafts,
                (int) $blocked->drafts === 1 ? 'is' : 'are'
            );
        }

        if ((int) ($blocked->cancelled ?? 0) > 0) {
            $reasons[] = sprintf('%d cancelled excluded.', (int) $blocked->cancelled);
        }

        return [
            'signedOutsidePeriod' => $signedOutside,
            'withoutSignedDate'   => $undated,
            'drafts'              => (int) ($blocked->drafts ?? 0),
            'cancelled'           => (int) ($blocked->cancelled ?? 0),
            'signedRange'         => [
                'from' => substr((string) ($range->lo ?? ''), 0, 10) ?: null,
                'to'   => substr((string) ($range->hi ?? ''), 0, 10) ?: null,
            ],
            'reasons'             => $reasons,
            'summary'             => $reasons === []
                ? 'No facultative placements are on the register at all.'
                : 'No cessions were signed in this period. ' . implode(' ', $reasons),
        ];
    }

    private function baseQuery(array $filters)
    {
        $q = DB::table('fac_placements')
            ->whereNull('deleted_at')
            ->whereNotIn('status', self::EXCLUDED_STATUSES);

        // The period is tested on the SLIP SIGNING date, not on capture and not on
        // the policy period. A bordereau reports the cessions that came on risk in
        // the period, and that is the date the reinsurer accepted.
        if (!empty($filters['period_from'])) {
            $q->whereDate('slip_signed_date', '>=', Carbon::parse($filters['period_from'])->toDateString());
        }
        if (!empty($filters['period_to'])) {
            $q->whereDate('slip_signed_date', '<=', Carbon::parse($filters['period_to'])->toDateString());
        }
        if (!empty($filters['financial_year'])) {
            $q->where('financial_year', $filters['financial_year']);
        }
        if (!empty($filters['counterparty_id'])) {
            $q->where('counterparty_id', (int) $filters['counterparty_id']);
        }
        if (!empty($filters['placement_type'])) {
            $q->where('placement_type', $filters['placement_type']);
        }
        if (!empty($filters['currency'])) {
            $q->where('currency', strtoupper($filters['currency']));
        }

        return $q;
    }

    /**
     * A money column converted to Pula, or null where it cannot be.
     *
     * On a BWP line the figure IS the Pula figure and no conversion applies. On a
     * foreign line the stored `*_bwp` column is used and nothing is computed here
     * — the rate that applied is captured with the placement, and re-deriving it
     * from today's rate would restate a settled cession.
     */
    private function bwp(object $r, string $column): ?string
    {
        if (strtoupper((string) $r->currency) === 'BWP') {
            return $this->dec($r->{$column});
        }

        $stored = $r->{$column . '_bwp'} ?? null;

        return $stored === null ? null : $this->dec($stored);
    }

    /** @return array<string,mixed> */
    private function lineJson(object $r): array
    {
        return [
            'facReference'      => $r->fac_reference,
            'placementType'     => $r->placement_type === 'auto_fac' ? 'Auto FAC' : 'FAC',
            // ONE SLIP COLUMN, NAMED FOR THE BASIS BY THE CALLER. The master
            // spreadsheet keeps a SHEET PER BASIS -- "FAC Analysis" headed "Fac
            // Slip No.", "Auto FAC Analysis" headed "Auto Fac Slip No." -- rather
            // than two columns on one sheet. So the equivalent of their document is
            // one bordereau per placement_type, and the heading is the CSV's
            // business rather than the row's.
            'slipNo'            => $r->fac_slip_no,
            'policyNumber'      => $r->policy_number,
            'policyType'        => $r->policy_type,
            'insuredName'       => $r->insured_name,
            'riGroupLabel'      => $r->ri_group_label,
            // The underwriter who owns the line. On the register since capture and
            // never on the bordereau, which is the document a broker queries — so
            // the person who can answer the query was the one name missing from it.
            'underwriter'       => $r->underwriter_name,
            'periodFrom'        => $r->period_from,
            'periodTo'          => $r->period_to,
            'slipSignedDate'    => $r->slip_signed_date,
            'riskCarrier'       => $r->risk_carrier,
            'riskPct'           => $this->dec($r->risk_pct),
            'cessionSumInsured' => $this->dec($r->cession_sum_insured),
            'currency'          => $r->currency,
            'grossCededPremium' => $this->dec($r->gross_ceded_premium),
            'premiumExclVat'    => $this->dec($r->gross_ceded_premium_excl_vat),
            'commissionPct'     => $this->dec($r->commission_pct),
            'commission'        => $this->dec($r->commission_amount),
            'commissionExclVat' => $this->dec($r->commission_excl_vat),
            'netCededPremium'   => $this->dec($r->net_ceded_premium),
            // THE PULA COLUMNS THE DOLLAR SHEET CARRIES. "FAC Analysis 2 -Dollar"
            // puts a Pula figure beside the premium and beside the commission,
            // because a statement in a currency the ledger is not kept in cannot
            // be agreed against the ledger without one.
            //
            // NULL RATHER THAN THE FOREIGN FIGURE WHERE NO RATE IS ON FILE, which
            // is the same rule FacPlacement::payableBwp() follows — a foreign
            // amount printed in a Pula column is not a conversion, it is a wrong
            // number in the right place. On a BWP line the Pula IS the amount.
            'grossCededPremiumBwp' => $this->bwp($r, 'gross_ceded_premium'),
            'commissionBwp'        => $this->bwp($r, 'commission_amount'),
            'netCededPremiumBwp'   => $this->bwp($r, 'net_ceded_premium'),
            'isReversal'        => (bool) $r->is_reversal,
        ];
    }

    /** @return array<string,float|int> */
    private function emptyTotals(): array
    {
        return [
            'lineCount'         => 0,
            'cessionSumInsured' => 0.0,
            'grossCededPremium' => 0.0,
            'premiumExclVat'    => 0.0,
            'commission'        => 0.0,
            'commissionExclVat' => 0.0,
            'netCededPremium'   => 0.0,
            // The Pula equivalents, and a count of the lines that have none.
            // A PARTIAL PULA TOTAL IS WORSE THAN NO PULA TOTAL: it reads as the
            // section's Pula value while silently omitting every line whose rate
            // is missing, and it is the figure somebody would agree to the ledger.
            // So the count travels with the sum and the caller suppresses one when
            // the other is non-zero.
            'grossCededPremiumBwp' => 0.0,
            'commissionBwp'        => 0.0,
            'netCededPremiumBwp'   => 0.0,
            'bwpMissing'           => 0,
        ];
    }

    /**
     * @param  array<string,float|int>  $t
     * @return array<string,float|int>
     */
    private function addTo(array $t, object $r): array
    {
        $t['lineCount']++;
        $t['cessionSumInsured'] += (float) $r->cession_sum_insured;
        $t['grossCededPremium'] += (float) $r->gross_ceded_premium;
        $t['premiumExclVat']    += (float) $r->gross_ceded_premium_excl_vat;
        $t['commission']        += (float) $r->commission_amount;
        $t['commissionExclVat'] += (float) $r->commission_excl_vat;
        $t['netCededPremium']   += (float) $r->net_ceded_premium;

        $grossBwp = $this->bwp($r, 'gross_ceded_premium');

        if ($grossBwp === null) {
            $t['bwpMissing']++;
        } else {
            $t['grossCededPremiumBwp'] += (float) $grossBwp;
            $t['commissionBwp']        += (float) ($this->bwp($r, 'commission_amount') ?? 0);
            $t['netCededPremiumBwp']   += (float) ($this->bwp($r, 'net_ceded_premium') ?? 0);
        }

        return $t;
    }

    /**
     * Rounded once, at the end.
     *
     * Rounding each line and then summing would leave the bordereau disagreeing
     * with the register by a few thebe, and a broker reconciling line by line reads
     * that as a real difference to be explained.
     *
     * @param  array<string,float|int>  $t
     * @return array<string,float|int>
     */
    private function roundTotals(array $t): array
    {
        foreach ($t as $k => $v) {
            // lineCount and bwpMissing are counts. Rounding them to two decimals
            // turns "3 lines" into 3.0, which then prints as a money figure.
            if ($k !== 'lineCount' && $k !== 'bwpMissing') {
                $t[$k] = round((float) $v, 2);
            }
        }

        return $t;
    }

    private function dec($v): ?float
    {
        return $v === null ? null : (float) $v;
    }
}
