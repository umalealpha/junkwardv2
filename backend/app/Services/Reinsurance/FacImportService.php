<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\FacPlacement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Loads the FAC master workbook history into the register.
 *
 * The workbook is .xlsb, which PhpSpreadsheet cannot read, so the file is first
 * flattened to a canonical CSV by database/fac-import/xlsb_to_csv.py — one row
 * per placement line, with the tab it came from carried as `placement_type`.
 * Everything after that flattening happens here, in PHP, where the
 * reconciliation gate belongs.
 *
 * Import rules, all of them load-bearing:
 *
 *  · One register row per spreadsheet line. Never aggregate.
 *  · No match in Graphite → import anyway with policy_in_graphite = false.
 *    About 20% will not match, and those are precisely the rows Finance most
 *    needs to see.
 *  · Cancelled lines import as status = cancelled with their ORIGINAL negative
 *    amounts and is_reversal = true. Do not sign-flip them: the negative row IS
 *    the reversal, and it is why the June payable reconciles.
 *  · The USD sheet imports with currency = USD and the rate implied by the
 *    sheet's own Pula column, sourced honestly as manual.
 *  · Reconcile PER COUNTERPARTY before committing, never on the grand total.
 *    Totals-only reconciliation hides offsetting errors.
 */
class FacImportService
{
    public function __construct(private FacRegisterService $register)
    {
    }

    /** Columns the flattened CSV must carry. */
    public const REQUIRED_COLUMNS = [
        'placement_type', 'counterparty', 'policy_number', 'gross_ceded_premium',
    ];

    /**
     * Source-data corrections, applied on the way in and RECORDED on the row.
     *
     * The workbook is the source of truth, but a source of truth can still carry
     * a typo, and some typos are fatal rather than cosmetic: a commission of 2.75
     * (275%) is rejected outright by the arithmetic guard, so the line — and with
     * it its counterparty's control total, and with that the whole import — fails.
     *
     * A correction is only ever applied where Reinsurance has confirmed the right
     * value IN WRITING. The authority and the date live in the CSV alongside it,
     * and every corrected placement says on its own notes what was changed and on
     * whose word. Nothing is quietly "cleaned".
     *
     * @return array<string,array<string,array{correct:float,wrong:float,authority:string,confirmed_on:string}>>
     */
    private function loadCorrections(): array
    {
        $path = __DIR__ . '/../../../database/fac-import/corrections.csv';
        if (!is_readable($path)) {
            return [];
        }

        $out = [];
        foreach ($this->readCsv($path) as $r) {
            $pol   = trim((string) ($r['policy_number'] ?? ''));
            $field = trim((string) ($r['field'] ?? ''));
            if ($pol === '' || $field === '') {
                continue;
            }
            $out[$pol][$field] = [
                'correct'      => $this->num($r['correct_value'] ?? 0),
                'wrong'        => $this->num($r['wrong_value'] ?? 0),
                'authority'    => trim((string) ($r['authority'] ?? 'unattributed')),
                'confirmed_on' => trim((string) ($r['confirmed_on'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @param  string $csvPath        the flattened workbook
     * @param  array  $expectedTotals per-counterparty control totals from the SUMMARY tab,
     *                                keyed "placement_type|currency|counterparty"
     * @param  bool   $commit         false = dry run, report only
     */
    public function import(
        string $csvPath,
        string $financialYear,
        array $expectedTotals = [],
        bool $commit = false
    ): array {
        if (!is_readable($csvPath)) {
            throw new \RuntimeException("Cannot read {$csvPath}.");
        }

        $rows = $this->readCsv($csvPath);
        if (!$rows) {
            throw new \RuntimeException('The file has no data rows.');
        }

        $missing = array_diff(self::REQUIRED_COLUMNS, array_keys($rows[0]));
        if ($missing) {
            throw new \RuntimeException('The file is missing these columns: ' . implode(', ', $missing));
        }

        $counterparties = $this->counterpartyMap();
        $corrections    = $this->loadCorrections();
        $prepared       = [];
        $problems       = [];
        $netVariances   = [];
        $corrected      = [];
        $unmatched      = 0;

        foreach ($rows as $i => $r) {
            $lineNo = $i + 2; // header is line 1

            try {
                $prep = $this->prepareRow($r, $financialYear, $counterparties, $lineNo, $corrections, $corrected);
            } catch (\Throwable $e) {
                $problems[] = "Line {$lineNo}: " . $e->getMessage();
                continue;
            }

            if (!$prep['policy_in_graphite']) {
                $unmatched++;
            }
            if ($prep['__net_variance'] !== null) {
                $netVariances[] = [
                    'line'        => $prep['__line_no'],
                    'counterparty'=> $prep['counterparty_name'],
                    'policy'      => $prep['policy_number'],
                    'variance'    => $prep['__net_variance'],
                ];
            }
            $prepared[] = $prep;
        }

        // ── The gate: per-counterparty control totals ────────────────────
        $computed = $this->totalsByCounterparty($prepared);
        $recon    = $this->reconcile($computed, $expectedTotals);

        if ($commit && !$recon['balanced']) {
            return [
                'committed'    => false,
                'reason'       => 'The import did not reconcile per counterparty, so nothing was written.',
                'rowsPrepared' => count($prepared),
                'unmatched'    => $unmatched,
                'problems'     => $problems,
                'reconciliation' => $recon,
                'netVariances' => $netVariances,
                'corrected'    => $corrected,
            ];
        }

        $written = 0;
        if ($commit) {
            DB::transaction(function () use ($prepared, &$written) {
                foreach ($prepared as $p) {
                    unset($p['__net_variance'], $p['__line_no']);
                    $p['fac_reference'] = $this->register->nextReference();
                    FacPlacement::create($p);
                    $written++;
                }
            });
        }

        return [
            'committed'      => $commit && $recon['balanced'],
            'rowsPrepared'   => count($prepared),
            'rowsWritten'    => $written,
            'unmatched'      => $unmatched,
            'problems'       => $problems,
            'reconciliation' => $recon,
            // Lines whose own net does not follow from their own gross and
            // commission. Not fatal, not silent — a data-quality finding for
            // Finance, recorded on each affected placement's notes as well.
            'netVariances'   => $netVariances,
            // Confirmed source-data corrections applied on the way in, each with
            // the authority that approved it. Reported every run, never hidden.
            'corrected'      => $corrected,
        ];
    }

    private function prepareRow(
        array $r,
        string $financialYear,
        array $counterparties,
        int $lineNo,
        array $corrections = [],
        array &$corrected = []
    ): array {
        $policyNumber = trim((string) ($r['policy_number'] ?? ''));
        if ($policyNumber === '') {
            throw new \RuntimeException('No policy number.');
        }

        $counterpartyName = trim((string) ($r['counterparty'] ?? ''));
        if ($counterpartyName === '') {
            throw new \RuntimeException('No counterparty — there is nobody to pay.');
        }

        $gross     = $this->num($r['gross_ceded_premium'] ?? 0);
        $cancelled = $this->truthy($r['cancelled'] ?? null) || $gross < 0;
        $currency  = strtoupper(trim((string) ($r['currency'] ?? 'BWP'))) ?: 'BWP';

        // VAT applicability is taken from the sheet itself where it can be:
        // an "excl VAT" figure equal to gross means the line carries no VAT.
        // That is how the June sheet distinguishes offshore placements, and it
        // is more reliable than assuming from the counterparty.
        $exclVat = isset($r['gross_excl_vat']) && $r['gross_excl_vat'] !== ''
            ? $this->num($r['gross_excl_vat'])
            : null;
        $vatApplicable = $exclVat === null
            ? ($counterparties[strtolower($counterpartyName)]['vat_applicable'] ?? true)
            : (abs($exclVat - $gross) > 0.005);

        $commissionPct = isset($r['commission_pct']) && $r['commission_pct'] !== ''
            ? $this->num($r['commission_pct'])
            : $this->impliedCommissionPct($gross, $this->num($r['commission_amount'] ?? 0));

        // A confirmed correction, applied and recorded — never applied silently.
        $correctionNote = null;
        $fix = $corrections[$policyNumber]['commission_pct'] ?? null;
        if ($fix !== null && abs($commissionPct - $fix['wrong']) < 0.000001) {
            $correctionNote = sprintf(
                'Commission corrected on import from %s%% to %s%% — %s (confirmed %s). '
                . 'The workbook value was arithmetically impossible.',
                number_format($fix['wrong'] * 100, 2),
                number_format($fix['correct'] * 100, 2),
                $fix['authority'],
                $fix['confirmed_on'] ?: 'undated'
            );
            $commissionPct = $fix['correct'];
            $corrected[] = [
                'line'         => $lineNo,
                'policy'       => $policyNumber,
                'field'        => 'commission_pct',
                'from'         => $fix['wrong'],
                'to'           => $fix['correct'],
                'authority'    => $fix['authority'],
                'confirmed_on' => $fix['confirmed_on'],
            ];
        }

        // FX: the USD tab carries no rate, only a Pula column. The rate is
        // therefore IMPLIED, and it is labelled as such — honest about its
        // provenance rather than presented as a sourced rate.
        $fxRate = null;
        $fxSrc  = null;
        if ($currency !== 'BWP') {
            $bwp = isset($r['gross_bwp']) && $r['gross_bwp'] !== '' ? $this->num($r['gross_bwp']) : null;
            if ($bwp !== null && $gross != 0.0) {
                $fxRate = round($bwp / $gross, 8);
                $fxSrc  = 'FAC master sheet (manual, rate implied from the sheet\'s own Pula column)';
            }
        }

        $lookup = $this->register->lookupPolicy($policyNumber);

        $amounts = $this->register->computeAmounts([
            'gross_ceded_premium' => $gross,
            'commission_pct'      => $commissionPct,
            'vat_applicable'      => $vatApplicable,
            'vat_rate'            => config('fac.vat_rate', 0.14),
            'currency'            => $currency,
            'fx_rate'             => $fxRate,
            'fx_rate_date'        => $fxRate !== null ? ($r['period_from'] ?? null) : null,
            'fx_rate_source'      => $fxSrc,
            // NOTE: the sheet's own net is deliberately NOT passed to the guard
            // here. See $netVariance below — on the June FY26 workbook 78 of 301
            // lines carry a net that does not follow from their own gross and
            // commission, so a hard rejection would block the entire history load
            // over a memo figure that does not affect the payable. The variance is
            // measured and reported instead of being either swallowed or fatal.
        ]);

        // Does the sheet's own net agree with its own gross and commission?
        // The payable is computed from GROSS, so a disagreement here changes no
        // money — but it means the source workbook does not tie to itself, and
        // Finance needs to see that rather than have it quietly recomputed away.
        $netVariance = null;
        if (isset($r['net_ceded_premium']) && $r['net_ceded_premium'] !== '') {
            $sheetNet = $this->num($r['net_ceded_premium']);
            $diff     = round($sheetNet - (float) $amounts['net_ceded_premium'], 2);
            if (abs($diff) > 0.01) {
                $netVariance = $diff;
            }
        }

        $cp = $counterparties[strtolower($counterpartyName)] ?? null;

        return array_merge($amounts, [
            'fac_slip_no'               => $this->slip($r['fac_slip_no'] ?? null),
            'financial_year'            => $financialYear,
            'placement_type'            => ($r['placement_type'] ?? 'fac') === 'auto_fac' ? 'auto_fac' : 'fac',
            'policy_id'                 => $lookup['policyId'] ?? null,
            'policy_number'             => $policyNumber,
            'policy_action_id'          => $lookup['policyActionId'] ?? null,
            'insured_name'              => $lookup['insuredName'] ?? ($r['insured_name'] ?? null),
            'policy_type'               => $r['policy_type'] ?? ($lookup['policyType'] ?? null),
            'period_from'               => $this->date($r['period_from'] ?? null),
            'period_to'                 => $this->date($r['period_to'] ?? null),
            'policy_status'             => $lookup['policyStatus'] ?? null,
            'policy_synced_at'          => now(),
            'policy_in_graphite'        => (bool) ($lookup['inGraphite'] ?? false),
            'policy_active_in_graphite' => (bool) ($lookup['isActive'] ?? false),
            'ri_group_label'            => $r['ri_group'] ?? null,
            'cession_sum_insured'       => isset($r['cession']) && $r['cession'] !== '' ? $this->num($r['cession']) : null,
            'risk_pct'                  => isset($r['risk_pct']) && $r['risk_pct'] !== '' ? $this->num($r['risk_pct']) : null,
            'counterparty_id'           => $cp['id'] ?? null,
            'counterparty_name'         => $counterpartyName,
            'risk_carrier'              => $r['risk_carrier'] ?? null,
            'underwriter_name'          => $r['underwriter'] ?? null,
            // The workbook has no PPW date anywhere. Importing a made-up one
            // would be worse than importing none — it stays blank and shows as
            // "not set" so Underwriting can be asked for the real dates.
            'ppw_due_date'              => null,
            'status'                    => $cancelled ? 'cancelled' : 'placed',
            'is_reversal'               => $cancelled,
            'cancelled_at'              => $cancelled ? ($this->date($r['period_from'] ?? null) ?: now()) : null,
            'cancellation_reason'       => $cancelled ? 'Imported as cancelled from the FAC master sheet.' : null,
            'source'                    => 'import',
            'source_ref'                => sprintf('FAC master %s · %s line %d', $financialYear, $r['placement_type'] ?? 'fac', $lineNo),
            'notes'                     => $correctionNote !== null
                ? trim($correctionNote . ' ' . ($netVariance !== null ? sprintf(
                    'Source workbook net (%s) also does not follow from its own gross and commission.',
                    number_format($this->num($r['net_ceded_premium']), 2)
                ) : ''))
                : ($netVariance !== null
                ? sprintf(
                    'Source workbook net (%s) does not follow from its own gross and commission; recomputed to %s, a difference of %s. The payable is unaffected — it is computed from gross.',
                    number_format($this->num($r['net_ceded_premium']), 2),
                    number_format((float) $amounts['net_ceded_premium'], 2),
                    number_format($netVariance, 2)
                )
                : null),
            '__net_variance'            => $netVariance,
            '__line_no'                 => $lineNo,
        ]);
    }

    /**
     * Group prepared rows exactly as the SUMMARY tab groups them.
     *
     * The control figure is compared in PULA. The SUMMARY states its
     * foreign-currency blocks in Pula — the USD block reads P68,508.74, not
     * USD 5,172.41 — so a like-for-like comparison has to convert first. A
     * foreign line with no rate has no Pula value at all, and that block is
     * reported as unreconcilable rather than silently short.
     *
     * @return array<string,array{premium:float,commission:float,payable:float,lines:int,rateMissing:int}>
     */
    public function totalsByCounterparty(array $prepared): array
    {
        $out = [];
        foreach ($prepared as $p) {
            $key = strtolower(trim(
                $p['placement_type'] . '|' . $p['currency'] . '|' . $p['counterparty_name']
            ));
            $out[$key] ??= [
                'premium' => 0.0, 'commission' => 0.0, 'payable' => 0.0,
                'lines' => 0, 'rateMissing' => 0,
            ];

            $isBwp      = strtoupper((string) $p['currency']) === 'BWP';
            $payableBwp = $isBwp
                ? (float) $p['gross_ceded_premium']
                : ($p['gross_ceded_premium_bwp'] !== null ? (float) $p['gross_ceded_premium_bwp'] : null);

            if ($payableBwp === null) {
                $out[$key]['rateMissing']++;
            } else {
                // Accumulate unrounded, round once at the end — matching the way
                // the workbook sums. Rounding each line first drifts 12 thebe on
                // Grand Re alone.
                $out[$key]['payable'] += $payableBwp;
            }

            $out[$key]['premium']    += $isBwp
                ? (float) $p['gross_ceded_premium_excl_vat']
                : ($payableBwp ?? 0.0);
            $out[$key]['commission'] += $isBwp
                ? (float) $p['commission_excl_vat']
                : ((float) ($p['commission_amount_bwp'] ?? 0));
            $out[$key]['lines']++;
        }

        foreach ($out as $k => $v) {
            $out[$k]['premium']    = round($v['premium'], 2);
            $out[$k]['commission'] = round($v['commission'], 2);
            $out[$k]['payable']    = round($v['payable'], 2);
        }

        return $out;
    }

    /**
     * Compare per counterparty. The payable is the control figure — it is what
     * the SUMMARY's "Reinsurance FAC Payable" column holds and what we actually
     * owe.
     */
    public function reconcile(array $computed, array $expected): array
    {
        if (!$expected) {
            return [
                'balanced' => false,
                'message'  => 'No control totals were supplied, so the import cannot be reconciled. Supply the SUMMARY tab figures per counterparty.',
                'lines'    => [],
            ];
        }

        $tolerance = 0.02;
        $lines     = [];
        $balanced  = true;

        foreach (array_unique(array_merge(array_keys($computed), array_keys($expected))) as $key) {
            $c           = $computed[$key]['payable'] ?? 0.0;
            $e           = (float) ($expected[$key] ?? 0.0);
            $d           = round($c - $e, 2);
            $rateMissing = $computed[$key]['rateMissing'] ?? 0;
            $ok          = abs($d) <= $tolerance && $rateMissing === 0;

            if (!$ok) {
                $balanced = false;
            }

            $lines[] = [
                'key'         => $key,
                'computed'    => round($c, 2),
                'expected'    => round($e, 2),
                'difference'  => $d,
                'ok'          => $ok,
                'lineCount'   => $computed[$key]['lines'] ?? 0,
                'rateMissing' => $rateMissing,
                'note'        => $rateMissing > 0
                    ? "{$rateMissing} line(s) are in a foreign currency with no exchange rate, so this block has no Pula value and cannot be reconciled."
                    : null,
            ];
        }

        usort($lines, fn ($a, $b) => abs($b['difference']) <=> abs($a['difference']));

        return [
            'balanced' => $balanced,
            'message'  => $balanced
                ? 'The import reproduces the control totals per counterparty.'
                : 'The import does NOT reproduce the control totals per counterparty. Nothing will be written until it does.',
            'lines'    => $lines,
        ];
    }

    /** @return array<string,array{id:int,vat_applicable:bool}> keyed on lower-cased name */
    private function counterpartyMap(): array
    {
        return DB::table('reinsurer')
            ->whereNull('deleted_at')
            ->get()
            ->mapWithKeys(fn ($r) => [
                strtolower(trim((string) $r->company_name)) => [
                    'id'             => (int) $r->id,
                    'vat_applicable' => (bool) ($r->vat_applicable ?? true),
                ],
            ])
            ->all();
    }

    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new \RuntimeException("Cannot open {$path}.");
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return [];
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $line = array_pad(array_slice($line, 0, count($header)), count($header), null);
            $rows[] = array_combine($header, $line);
        }
        fclose($fh);

        return $rows;
    }

    private function impliedCommissionPct(float $gross, float $commission): float
    {
        return $gross == 0.0 ? 0.0 : round($commission / $gross, 6);
    }

    private function num($v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $v);
        return $clean === '' || $clean === '-' ? 0.0 : (float) $clean;
    }

    private function truthy($v): bool
    {
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'y', 'cancelled', 'cancel'], true);
    }

    /** "-" is how the sheet writes "no slip number", not a slip called "-". */
    private function slip($v): ?string
    {
        $v = trim((string) $v);
        return ($v === '' || $v === '-') ? null : $v;
    }

    private function date($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '-') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $v)->toDateString();
            } catch (\Throwable) {
                // try the next format
            }
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
