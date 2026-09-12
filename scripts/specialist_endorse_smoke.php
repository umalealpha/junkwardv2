<?php
/**
 * Specialist Endorse Pro-Rata Smoke
 *
 * Replays Monika's 43-case CSV against the formula implemented in
 *   backend/app/Services/SpecialistEndorse/SpecialistEndorseCalculator.php::writeProRata
 *   backend/app/Models/PolicyAction.php::calculatePremiumEndorse
 *
 * For each case: recomputes impact_incl, impact_excl, vat from inputs
 * and compares against the CSV's claimed values. Also recomputes
 * days-remaining from the stated dates and flags divergence between
 * CSV-stated days vs date-derived days (the source uses Carbon's
 * diffInDays which is end-exclusive; CSV uses inclusive counting).
 *
 * Cap rule: |proRata| <= |annualDelta|.
 * Cancel rule: REFUND rows must end negative.
 *
 * Output: per-case PASS/FAIL/WARN, then summary, exit 0 if all pass.
 */

$csvPath = __DIR__ . '/specialist_endorse_calculation_cases.csv';
if (!file_exists($csvPath)) {
    fwrite(STDERR, "CSV not found: $csvPath\n");
    exit(2);
}

$rows = array_map('str_getcsv', file($csvPath));
$header = null;
$cases = [];
foreach ($rows as $row) {
    if (empty($row) || count($row) < 2) continue;
    if ($row[0] === 'Test ID') { $header = $row; continue; }
    if ($header === null) continue;                            // skip metadata
    if (empty($row[0]) || str_starts_with(trim($row[0]), '===')) continue;
    if (count($row) < count($header)) continue;
    $cases[] = array_combine($header, array_slice($row, 0, count($header)));
}

function inclusive_days_between(string $start, string $end): int {
    $s = new DateTimeImmutable($start);
    $e = new DateTimeImmutable($end);
    return (int) $s->diff($e)->format('%r%a') + 1;             // inclusive
}
function exclusive_days_between(string $start, string $end): int {
    $s = new DateTimeImmutable($start);
    $e = new DateTimeImmutable($end);
    return (int) $s->diff($e)->format('%r%a');                 // Carbon::diffInDays-style
}

$bwp = fn($n) => number_format((float)$n, 2, '.', '');
$within = fn($a, $b, $tol = 0.01) => abs($a - $b) <= $tol;

$results = [];
$summary = ['pass' => 0, 'fail' => 0, 'warn' => 0, 'investigate' => 0];

foreach ($cases as $c) {
    $id        = $c['Test ID'];
    $type      = $c['Type'] ?? '';
    $termDays  = (int) $c['Term Days'];
    $daysRem   = (int) $c['Days Remaining'];
    $factor    = (float) $c['Pro-rata Factor'];
    $prev      = (float) $c['Prev Annual (incl VAT) BWP'];
    $new       = (float) $c['New Annual (incl VAT) BWP'];
    $deltaAnn  = (float) $c['Annual Delta BWP'];
    $impactInc = (float) $c['Impact Incl-VAT BWP'];
    $impactExc = (float) $c['Impact Excl-VAT BWP'];
    $vat       = (float) $c['VAT 14% BWP'];

    $findings = [];

    // 1. Factor sanity: daysRem/termDays matches stated factor (to 5dp)
    $computedFactor = $termDays > 0 ? $daysRem / $termDays : 0.0;
    if (!$within($computedFactor, $factor, 0.0001)) {
        $findings[] = sprintf("factor stated=%.5f computed=%.5f", $factor, $computedFactor);
    }

    // 2. Annual delta sanity
    if (!$within($new - $prev, $deltaAnn, 0.005)) {
        $findings[] = sprintf("delta stated=%.2f computed=%.2f", $deltaAnn, $new - $prev);
    }

    // 3. Recompute impact incl-VAT with cap + cancel rules (mirrors writeProRata).
    //    Use exact fraction (daysRem * delta / termDays) — NOT the rounded
    //    factor — to match the CSV author's expected precision.
    $rawImpact = $termDays > 0
        ? round($deltaAnn * $daysRem / $termDays, 2)
        : 0.0;
    if (abs($rawImpact) > abs($deltaAnn) && $deltaAnn != 0.0) {
        $rawImpact = $deltaAnn >= 0 ? $deltaAnn : -abs($deltaAnn);
    }
    if ($type === 'REFUND' && $rawImpact > 0) {
        $rawImpact = -abs($rawImpact);
    }
    if (!$within($rawImpact, $impactInc, 0.01)) {
        $findings[] = sprintf("impact_incl stated=%.2f exact=%.2f", $impactInc, $rawImpact);
    }

    // 4. VAT split: excl = incl/1.14, vat = incl*0.14/1.14
    $computedExcl = round($impactInc / 1.14, 2);
    $computedVat  = round($impactInc * 0.14 / 1.14, 2);
    if (!$within($computedExcl, $impactExc, 0.02)) {
        $findings[] = sprintf("excl stated=%.2f computed=%.2f", $impactExc, $computedExcl);
    }
    if (!$within($computedVat, $vat, 0.02)) {
        $findings[] = sprintf("vat stated=%.2f computed=%.2f", $vat, $computedVat);
    }

    // 5. Date-derived days check — measure day-counting convention used per row.
    //    Source code uses Carbon::diffInDays which is end-exclusive.
    //    Audit which convention each CSV row picks (inclusive vs exclusive).
    $termStart = $c['Term Start']       ?? null;
    $termEnd   = $c['Term End']         ?? null;
    $eff       = $c['Endorse Effective']?? null;
    $dayConvention = '';
    if ($termStart && $termEnd && $eff) {
        try {
            $termDaysExc  = exclusive_days_between($termStart, $termEnd);
            $daysRemExc   = exclusive_days_between($eff, $termEnd);
            $termInc = ($termDays === $termDaysExc + 1) ? 'incl'
                     : (($termDays === $termDaysExc)    ? 'excl' : 'OTHER');
            $remInc  = ($daysRem  === $daysRemExc + 1)  ? 'incl'
                     : (($daysRem  === $daysRemExc)     ? 'excl' : 'OTHER');
            $dayConvention = "term=$termInc rem=$remInc";
            if ($termInc === 'OTHER' || $remInc === 'OTHER') {
                $findings[] = sprintf("day_count anomaly: term stated=%d exc=%d | rem stated=%d exc=%d",
                    $termDays, $termDaysExc, $daysRem, $daysRemExc);
            }
        } catch (Exception $e) {
            // X2 has unquoted commas in the CSV that break the parser — flag separately
            if (strpos($e->getMessage(), 'timezone') !== false) {
                $findings[] = "csv malformed: unquoted comma in narrative column (parser misalignment)";
            } else {
                $findings[] = "date parse error: " . $e->getMessage();
            }
        }
    }

    // 6. Investigation marker
    if (stripos($type, 'INVESTIGATE') !== false) {
        $verdict = 'INVESTIGATE';
        $summary['investigate']++;
    } elseif (empty($findings)) {
        $verdict = 'PASS';
        $summary['pass']++;
    } else {
        $verdict = 'FAIL';
        $summary['fail']++;
    }

    $results[] = [
        'id'         => $id,
        'verdict'    => $verdict,
        'type'       => $type,
        'product'    => $c['Product'] ?? '',
        'findings'   => $findings,
        'impactIncl' => $impactInc,
        'convention' => $dayConvention,
    ];
}

// Tally day-counting conventions across all rows
$convTally = [];
foreach ($results as $r) {
    $k = $r['convention'] ?: 'unknown';
    $convTally[$k] = ($convTally[$k] ?? 0) + 1;
}

// ── Drift check: what would the source ACTUALLY produce using Carbon-style
//    end-exclusive day counts? Per-case re-run with exclusive math.
$driftRows = [];
foreach ($cases as $c) {
    $termStart = $c['Term Start']       ?? null;
    $termEnd   = $c['Term End']         ?? null;
    $eff       = $c['Endorse Effective']?? null;
    if (!$termStart || !$termEnd || !$eff) continue;
    try {
        $termDaysExc = max(1, exclusive_days_between($termStart, $termEnd));
        $daysRemExc  = max(0, exclusive_days_between($eff, $termEnd));
        $deltaAnn    = (float) $c['Annual Delta BWP'];
        $factorExc   = $daysRemExc / $termDaysExc;
        $impactExc   = round($deltaAnn * $factorExc, 2);
        // Cap
        if (abs($impactExc) > abs($deltaAnn) && $deltaAnn != 0.0) {
            $impactExc = $deltaAnn >= 0 ? $deltaAnn : -abs($deltaAnn);
        }
        if (($c['Type'] ?? '') === 'REFUND' && $impactExc > 0) {
            $impactExc = -abs($impactExc);
        }
        $stated = (float) $c['Impact Incl-VAT BWP'];
        $drift  = $impactExc - $stated;
        if (abs($drift) >= 0.01) {
            $driftRows[] = [
                'id'    => $c['Test ID'],
                'stated'=> $stated,
                'source'=> $impactExc,
                'drift' => $drift,
            ];
        }
    } catch (Exception $e) {}
}

// ── Print results
echo str_repeat('=', 88) . "\n";
echo "SPECIALIST ENDORSE PRO-RATA SMOKE — " . count($cases) . " cases\n";
echo str_repeat('=', 88) . "\n\n";

printf("%-7s  %-11s  %-18s  %-17s  %s\n", 'CASE', 'VERDICT', 'PRODUCT/TYPE', 'CONVENTION', 'FINDINGS');
echo str_repeat('-', 110) . "\n";
foreach ($results as $r) {
    $findStr = empty($r['findings']) ? '' : implode(' | ', $r['findings']);
    printf("%-7s  %-11s  %-18s  %-17s  %s\n",
        $r['id'], $r['verdict'],
        substr($r['product'] . '/' . $r['type'], 0, 18),
        $r['convention'],
        $findStr);
}

echo "\n" . str_repeat('=', 88) . "\n";
printf("SUMMARY: %d PASS  %d FAIL  %d INVESTIGATE (%d total)\n",
    $summary['pass'], $summary['fail'], $summary['investigate'], count($cases));
echo str_repeat('=', 88) . "\n\n";

echo "DAY-COUNTING CONVENTION TALLY (per-row, audit of CSV authoring):\n";
foreach ($convTally as $k => $n) {
    printf("  %-26s  %d rows\n", $k, $n);
}
echo "\n";

if (!empty($driftRows)) {
    echo "DRIFT vs SOURCE CODE (Carbon end-exclusive day count):\n";
    echo "  Source uses \$end->diffInDays(\$start) which is end-exclusive.\n";
    echo "  CSV uses inclusive day counting. For year-long terms this is 365 vs 364.\n";
    echo "  Each row below is what calculatePremiumEndorse would actually produce.\n\n";
    printf("  %-7s  %12s  %12s  %12s\n", 'CASE', 'CSV (incl)', 'SOURCE (exc)', 'DRIFT BWP');
    echo "  " . str_repeat('-', 50) . "\n";
    foreach (array_slice($driftRows, 0, 50) as $d) {
        printf("  %-7s  %12s  %12s  %+12s\n",
            $d['id'], $bwp($d['stated']), $bwp($d['source']), $bwp($d['drift']));
    }
    echo "\n  TOTAL DRIFT-AFFECTED CASES: " . count($driftRows) . " of " . count($cases) . "\n";
}

exit($summary['fail'] > 0 ? 1 : 0);
