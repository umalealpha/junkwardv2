<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\FacPeriodSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The SUMMARY tab, live.
 *
 * This is the acceptance test for Phase 1: the register must reproduce the June
 * FY26 payable PER COUNTERPARTY to the cent. Reconciled against the master
 * workbook on 30 July 2026 — all 20 counterparty blocks tie, total
 * P10,278,821.60.
 *
 * Three things had to be right for that to happen, and all three are easy to get
 * wrong:
 *
 *  1. GROUP BY (placement_type, currency, counterparty) — not counterparty
 *     alone. Grand Re appears twice on the SUMMARY: Auto FAC P706,829.51 and
 *     Normal FAC P2,761,161.49. Collapse them and nothing ties.
 *
 *  2. The payable is Σ gross_ceded_premium AS CAPTURED — VAT-inclusive where VAT
 *     applies. It is NOT gross less commission. Commission is a separate
 *     receivable in the monthly journal.
 *
 *  3. Sum at full precision and round ONCE at the end. Rounding each line first
 *     drifts 12 thebe on Grand Re alone, because the workbook sums unrounded
 *     values and only the display is rounded.
 */
class FacSummaryService
{
    /**
     * Payable by counterparty, split the way the SUMMARY tab splits it.
     *
     * @param  string|null $asAt        include placements up to this date
     * @param  string|null $financialYear
     * @return array{rows:array<int,array>, totals:array, priorPeriodEnd:string|null}
     */
    public function payableByCounterparty(?string $asAt = null, ?string $financialYear = null): array
    {
        // Round once, at the end — see note 3 above. SUM() is exact over DECIMAL
        // columns, so the only rounding is the final presentation.
        // DRAFTS ARE EXCLUDED. FacAutoEntryService raises instalment lines as
        // drafts precisely so they do NOT count as money owed until a person
        // confirms them — and this is the method that decides what is owed. Left
        // in, the first run of `fac:auto-entries` would inflate the payable, the
        // frozen month-end snapshot, AND the "journal to pass" that Finance
        // actually posts in omni. The June import reconciled only because it
        // created no drafts; the next cron run would have broken it silently.
        $rows = DB::table('fac_placements')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'draft')
            ->when($financialYear, fn ($q) => $q->where('financial_year', $financialYear))
            ->when($asAt, fn ($q) => $q->whereDate('created_at', '<=', $asAt))
            ->groupBy('placement_type', 'currency', 'counterparty_id', 'counterparty_name')
            ->select(
                'placement_type',
                'currency',
                'counterparty_id',
                'counterparty_name',
                DB::raw('SUM(COALESCE(gross_ceded_premium_excl_vat,0)) as premium_excl_vat'),
                DB::raw('SUM(COALESCE(commission_excl_vat,0))          as commission_excl_vat'),
                DB::raw('SUM(COALESCE(gross_ceded_premium,0))          as payable'),
                DB::raw('SUM(COALESCE(gross_ceded_premium_bwp,0))      as payable_bwp'),
                DB::raw('COUNT(*)                                      as line_count'),
                DB::raw("SUM(CASE WHEN status IN ('placed','awaiting_premium','client_paid','ready_to_settle') THEN COALESCE(gross_ceded_premium,0) ELSE 0 END) as open_payable"),
                DB::raw("SUM(CASE WHEN currency <> 'BWP' AND fx_rate IS NULL THEN 1 ELSE 0 END) as rate_missing_count")
            )
            ->orderBy('placement_type')
            ->orderBy('currency')
            ->orderBy('counterparty_name')
            ->get();

        $priorEnd = $this->priorPeriodEnd($asAt);
        $prior    = $this->priorSnapshotMap($priorEnd);

        $out = [];
        $totals = [
            'premiumExclVat' => 0.0, 'commissionExclVat' => 0.0,
            'payable' => 0.0, 'priorPayable' => 0.0, 'change' => 0.0,
            'lineCount' => 0, 'rateMissing' => 0,
        ];

        foreach ($rows as $r) {
            $key   = $this->snapshotKey($r->placement_type, $r->currency, (string) $r->counterparty_name);
            $prev  = $prior[$key] ?? 0.0;
            $pay   = round((float) $r->payable, 2);

            $row = [
                'placementType'      => $r->placement_type,
                'placementTypeLabel' => $r->placement_type === 'auto_fac' ? 'Auto FAC' : 'Normal FAC',
                'currency'           => $r->currency,
                'counterpartyId'     => $r->counterparty_id ? (int) $r->counterparty_id : null,
                'counterpartyName'   => $r->counterparty_name,
                'premiumExclVat'     => round((float) $r->premium_excl_vat, 2),
                'commissionExclVat'  => round((float) $r->commission_excl_vat, 2),
                'payable'            => $pay,
                'payableBwp'         => round((float) $r->payable_bwp, 2),
                'openPayable'        => round((float) $r->open_payable, 2),
                'priorPayable'       => round($prev, 2),
                'change'             => round($pay - $prev, 2),
                'lineCount'          => (int) $r->line_count,
                'rateMissingCount'   => (int) $r->rate_missing_count,
            ];
            $out[] = $row;

            $totals['premiumExclVat']    += $row['premiumExclVat'];
            $totals['commissionExclVat'] += $row['commissionExclVat'];
            $totals['payable']           += $row['payable'];
            $totals['priorPayable']      += $row['priorPayable'];
            $totals['change']            += $row['change'];
            $totals['lineCount']         += $row['lineCount'];
            $totals['rateMissing']       += $row['rateMissingCount'];
        }

        foreach (['premiumExclVat', 'commissionExclVat', 'payable', 'priorPayable', 'change'] as $k) {
            $totals[$k] = round($totals[$k], 2);
        }

        return ['rows' => $out, 'totals' => $totals, 'priorPeriodEnd' => $priorEnd];
    }

    /**
     * Variance to the general ledger, and therefore the journal to pass.
     *
     * Graphite has no ledger. The GL comparatives are an INPUT, captured with a
     * source and a date on fac_period_gl — never derived and never guessed. With
     * no GL figure captured the variance is reported as unavailable rather than
     * as zero, because a silent zero reads as "agreed".
     */
    public function varianceToGl(string $periodEnd, ?string $financialYear = null): array
    {
        $summary = $this->payableByCounterparty($periodEnd, $financialYear);
        $gl      = DB::table('fac_period_gl')->whereDate('period_end', $periodEnd)->first();

        $registerPremium    = $summary['totals']['premiumExclVat'];
        $registerCommission = $summary['totals']['commissionExclVat'];

        if (!$gl || $gl->gl_premium === null) {
            return [
                'periodEnd'          => $periodEnd,
                'available'          => false,
                'message'            => 'No general-ledger figure has been captured for this period, so the variance cannot be computed. Capture it on the Settlements screen with its source and date.',
                'registerPremium'    => $registerPremium,
                'registerCommission' => $registerCommission,
            ];
        }

        return [
            'periodEnd'           => $periodEnd,
            'available'           => true,
            'registerPremium'     => $registerPremium,
            'registerCommission'  => $registerCommission,
            'glPremium'           => round((float) $gl->gl_premium, 2),
            'glCommission'        => $gl->gl_commission !== null ? round((float) $gl->gl_commission, 2) : null,
            'glSource'            => $gl->gl_source,
            'glAsAt'              => $gl->gl_as_at,
            'premiumVariance'     => round($registerPremium - (float) $gl->gl_premium, 2),
            'commissionVariance'  => $gl->gl_commission !== null
                ? round($registerCommission - (float) $gl->gl_commission, 2)
                : null,
            // The journal Finance passes is the variance. Stated, not posted —
            // Graphite is not an accounting system and never posts to a ledger.
            'journalToPass'       => [
                'premium'    => round($registerPremium - (float) $gl->gl_premium, 2),
                'commission' => $gl->gl_commission !== null
                    ? round($registerCommission - (float) $gl->gl_commission, 2)
                    : null,
                'note'       => 'Pass this in omni. Graphite does not post to the ledger.',
                // THE FOUR FIGURES, NOT THE TWO VARIANCES. The journal needs the
                // balances as well as the movement between them, because that is
                // how Reinsurance check it — and passing variances already
                // rounded put the payable a cent out. Round once, at the end, as
                // everything else here does.
                'lines'      => $this->journalLines(
                    $registerPremium,
                    (float) $gl->gl_premium,
                    $registerCommission,
                    $gl->gl_commission !== null ? (float) $gl->gl_commission : null
                ),
            ],
        ];
    }

    /**
     * The five classified lines Finance actually posts — SUMMARY, "Entries to Pass".
     *
     * Reinsurance's master spreadsheet carries these and this service produced
     * only the two variance figures they are built from, so the classification,
     * the gross-up and the tax line were all being done by hand in the workbook
     * every month.
     *
     * EVERY FIGURE COMES OFF TWO NUMBERS AND THE VAT RATE. The premium and
     * commission variances are already computed above; the rest is the treatment:
     *
     *   Expense       Reins. FAC Cover             premium variance         DR
     *   Liability     Reins. FAC payables          premium variance + VAT   CR
     *   Current Asset FAC commission receivable    commission var. + VAT    DR
     *   revenue       FAC Commission               commission variance      CR
     *   Liability     Tax Paid                     VAT premium − VAT comm.  DR
     *
     * Reproduced against their August 2026 sheet to the cent: 176,366.18 and
     * 201,057.44 on the premium side, 58,175.24 and 51,030.91 on the commission
     * side, and 17,546.94 of tax.
     *
     * THE EXPENSE IS NET AND THE PAYABLE IS GROSS, which is the whole shape of
     * it. What the cession costs is the premium; what is owed to the reinsurer is
     * the premium plus the VAT charged on it. The commission mirrors it — the
     * receivable is gross, the revenue recognised is net — and the difference
     * between the two VAT amounts is the net tax position, input tax on the
     * cession less output tax on the commission.
     *
     * IT BALANCES BY CONSTRUCTION, NOT BY COINCIDENCE. Debits are
     * p + c(1+r) + r(p − c) and credits are p(1+r) + c, and those are the same
     * expression. The control is still computed and returned rather than asserted
     * in a comment, because a rate read from config could arrive as something
     * that does not divide cleanly and a reader is entitled to see the zero.
     *
     * REFUSED WITHOUT A GL COMMISSION FIGURE. Three of the five lines need it,
     * and a journal missing three lines does not balance — publishing the premium
     * half alone would hand Finance an entry that cannot be posted.
     *
     * @return array<string,mixed>
     */
    private function journalLines(
        float $registerPremium,
        float $glPremium,
        float $registerCommission,
        ?float $glCommission
    ): array {
        $premiumVariance    = $registerPremium - $glPremium;
        $commissionVariance = $glCommission === null ? null : $registerCommission - $glCommission;

        if ($commissionVariance === null) {
            return [
                'available' => false,
                'message'   => 'No general-ledger commission figure has been captured, and three of '
                    . 'the five journal lines are derived from it. A journal missing them would not '
                    . 'balance, so none is produced.',
            ];
        }

        $rate = (float) config('reinsurance.terms.vat.rate', 0.14);

        // EVERY FIGURE OFF THE UNROUNDED VARIANCE, rounded once as it is written.
        // The tax line especially: taking it from the already-rounded gross and
        // net amounts compounds two roundings into a figure that then has to
        // balance against them.
        $premiumGross    = $premiumVariance * (1 + $rate);
        $commissionGross = $commissionVariance * (1 + $rate);
        $taxPaid         = ($premiumGross - $premiumVariance)
            - ($commissionGross - $commissionVariance);

        $premiumVariance    = round($premiumVariance, 2);
        $commissionVariance = round($commissionVariance, 2);
        $premiumGross       = round($premiumGross, 2);
        $commissionGross    = round($commissionGross, 2);
        $taxPaid            = round($taxPaid, 2);

        // DR positive, CR negative, so the control is a sum rather than a rule
        // somebody has to remember — the same convention the treaty statement
        // uses for exactly this reason.
        $lines = [
            ['classification' => 'Expense',       'account' => 'Reins. FAC Cover',          'amount' => $premiumVariance,  'drCr' => 'DR'],
            ['classification' => 'Liability',     'account' => 'Reins. FAC payables',       'amount' => $premiumGross,     'drCr' => 'CR'],
            ['classification' => 'Current Asset', 'account' => 'FAC commission receivable', 'amount' => $commissionGross,  'drCr' => 'DR'],
            ['classification' => 'revenue',       'account' => 'FAC Commission',            'amount' => $commissionVariance, 'drCr' => 'CR'],
            ['classification' => 'Liability',     'account' => 'Tax Paid',                  'amount' => $taxPaid,          'drCr' => 'DR'],
        ];

        $control = 0.0;

        foreach ($lines as &$l) {
            $l['signed'] = $l['drCr'] === 'DR' ? $l['amount'] : -1 * $l['amount'];
            $control += $l['signed'];
        }
        unset($l);

        $control = round($control, 2);

        return [
            'available' => true,
            'vatRate'   => $rate,
            'lines'     => $lines,
            // THE BALANCES THE ENTRY IS THE MOVEMENT BETWEEN.
            //
            // Reinsurance describe the receivable as "a sum of the Commission
            // inclusive of VAT", and the entry to pass as comparing "the
            // receivable from the previous month to the current receivable"
            // (8 September 2026). That is the SAME ARITHMETIC as grossing up the
            // variance -- 1.14a - 1.14b is 1.14(a - b) -- so no figure changes.
            // What their answer settles is the MEANING of the ledger column:
            // "As per GL" is the PRIOR MONTH'S balance, not an unrelated
            // comparative.
            //
            // Reported because that is how they check it. On their August sheet
            // the receivable runs 372,180.81 to 430,356.05, a movement of
            // 58,175.24 -- and an entry nobody can see the two ends of is an
            // entry taken on trust.
            'balances'  => [
                'receivable' => [
                    'prior'    => round($glCommission * (1 + $rate), 2),
                    'current'  => round($registerCommission * (1 + $rate), 2),
                    'movement' => $commissionGross,
                ],
                'payable'    => [
                    'prior'    => round($glPremium * (1 + $rate), 2),
                    'current'  => round($registerPremium * (1 + $rate), 2),
                    'movement' => $premiumGross,
                ],
            ],
            // Their sheet calls this CONTROL CHECK and it is the reason the
            // journal can be trusted. Reported, never assumed.
            'control'   => $control,
            'balanced'  => abs($control) < 0.005,
        ];
    }

    /**
     * Freeze the month-end position so next month has a prior column.
     *
     * Re-running for the same period overwrites that period's snapshot and
     * nothing else — safe to repeat while a close is still moving.
     */
    public function closePeriod(string $periodEnd, ?string $financialYear = null): array
    {
        $summary  = $this->payableByCounterparty($periodEnd, $financialYear);
        $priorEnd = $this->priorPeriodEnd($periodEnd);
        $written  = 0;

        DB::transaction(function () use ($summary, $periodEnd, $financialYear, &$written) {
            FacPeriodSnapshot::whereDate('period_end', $periodEnd)->delete();

            foreach ($summary['rows'] as $r) {
                FacPeriodSnapshot::create([
                    'period_end'          => $periodEnd,
                    'financial_year'      => $financialYear,
                    'placement_type'      => $r['placementType'],
                    'currency'            => $r['currency'],
                    'counterparty_id'     => $r['counterpartyId'],
                    'counterparty_name'   => $r['counterpartyName'] ?? '(unnamed)',
                    'premium_excl_vat'    => $r['premiumExclVat'],
                    'commission_excl_vat' => $r['commissionExclVat'],
                    'payable'             => $r['payable'],
                    'prior_payable'       => $r['priorPayable'],
                    'change'              => $r['change'],
                    'line_count'          => $r['lineCount'],
                    'closed_at'           => now(),
                    'closed_by'           => Auth::id(),
                ]);
                $written++;
            }
        });

        // The lock memoises how far the register is closed, and this just moved
        // it. The snapshot writes above fire it themselves, but a close that
        // produces NO rows only mass-deletes — which fires no model events — so
        // the answer is dropped here unconditionally rather than by inference.
        app(FacPeriodLock::class)->forget();

        return [
            'periodEnd'      => $periodEnd,
            'priorPeriodEnd' => $priorEnd,
            'rowsWritten'    => $written,
            'payable'        => $summary['totals']['payable'],
        ];
    }

    /** The latest closed period strictly before the given date. */
    private function priorPeriodEnd(?string $asAt): ?string
    {
        $q = FacPeriodSnapshot::query();
        if ($asAt) {
            $q->whereDate('period_end', '<', $asAt);
        }
        $d = $q->max('period_end');

        return $d ? Carbon::parse($d)->toDateString() : null;
    }

    /** @return array<string,float> */
    private function priorSnapshotMap(?string $periodEnd): array
    {
        if (!$periodEnd) {
            return [];
        }

        return FacPeriodSnapshot::whereDate('period_end', $periodEnd)
            ->get()
            ->mapWithKeys(fn ($s) => [
                $this->snapshotKey($s->placement_type, $s->currency, (string) $s->counterparty_name)
                    => (float) $s->payable,
            ])
            ->all();
    }

    private function snapshotKey(?string $type, ?string $currency, string $name): string
    {
        return strtolower(trim(($type ?? 'fac') . '|' . ($currency ?? 'BWP') . '|' . $name));
    }
}
