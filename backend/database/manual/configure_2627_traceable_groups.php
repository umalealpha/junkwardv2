<?php
/**
 * Configures the five class groups whose 2026/27 Band 1 capacity is TRACEABLE to
 * Final Lead Terms 2026 — legacy reinsurance_* tables, config only, no schema change.
 *
 * SOURCE: D:\reinsurence_25-26\Reinsurance.xlsx, sheet 'Capacities 2026-27'
 *   (Alpha Direct, period 1 July 2026 - 30 June 2027; basis "Final Lead Terms 2026
 *   (proportional treaties) and Reinsurance Cession Structure briefing note dated
 *   11 August 2026").
 *
 * WHY ONLY FIVE GROUPS. In that sheet a Band 1 cell is either formula-linked to a
 * confirmed input or a hardcoded literal. Only five rows are formula-linked, and
 * every one traces to Final Terms 2026:
 *
 *     row 22  Material Damage & BI        B22 = $B$9   10,000,000
 *     row 23  Engineering                 B23 = $B$9   10,000,000
 *     row 24  Motor Comprehensive (OD)     B24 = $B$13   5,000,000
 *     row 26  Motor Passenger Liability    B26 = $B$14   2,500,000
 *     row 27  Motor Third Party Liability  B27 = $B$15  10,000,000
 *
 * The other fifteen rows hold literals that sheet note 7 marks "carried forward
 * from the 2024/25 capacities table and pending 2026/27 confirmation". They are
 * deliberately NOT configured here.
 *
 * Retention 30% / cession 70% come from B7 and B8 (=1-B7), sourced "Final Terms
 * 2026 - General QS (Munich Re / FM Re) and Motor QS (Continental Re): both 30%".
 *
 * BAND 2 (SURPLUS) IS FIRE & ENGINEERING ONLY. Column E is zero on all eighteen
 * other rows and sheet note 6 states the surplus does not apply to motor. Groups
 * 19 and 6 already carry it from add_2627_property_engineering_layers.php, so this
 * script only VERIFIES it and never re-creates it.
 *
 * BAND 3 (AUTO FAC / FAC) IS EXCLUDED, DELIBERATELY. Two independent reasons:
 *   1. Sheet row 4 and note 7 — every non-proportional figure is carried forward
 *      from 2024/25 "pending 2026 terms". Column F is exactly the column that
 *      would drive it.
 *   2. remove_2627_autofac_facplacement.php already removed these once. Booking
 *      them as treaty formulas wrote them to policy_reinsurance.treatySI and
 *      reported 151,641,212 of a single Property risk as ceded with nothing placed
 *      behind it, zeroing the uncovered-gap column. Do not re-add without a signed
 *      facultative facility document.
 *
 * CASCADE BASIS — STATED ASSUMPTION. Sheet row 53 is still open: "the field driving
 * the cascade is still to be named - sum insured vs estimated maximum loss, and per
 * policy / per risk / per location. Required before the cession logic is built."
 * This script assumes SUM INSURED, because that is what the engine already measures
 * (FacCoverageService: "The gap is now measured in SUM INSURED"). If the answer
 * turns out to be EML, every si_allocation below has to be revisited.
 *
 * NOTHING IS HARDCODED THAT WAS NOT VERIFIED. Group ids, the treaty each formula
 * hangs off, and the s_FormulaType / operator / vehicle_type values are all
 * RESOLVED AT RUNTIME from rows that already exist, then mirrored. The script stops
 * with an exact message rather than guessing.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction.
 * Credentials from the ENVIRONMENT:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional)
 *
 * PowerShell:
 *   $env:RI_DB_HOST='graphite-test-write.c1y5xpqlrcpg.af-south-1.rds.amazonaws.com'
 *   $env:RI_DB_NAME='Graphite_live'; $env:RI_DB_USER='graphitebwlive'; $env:RI_DB_PASS='***'
 *   php backend/database/manual/configure_2627_traceable_groups.php          # dry run
 *   php backend/database/manual/configure_2627_traceable_groups.php APPLY    # execute
 */

const DATE_FROM = '2026-07-01';
const DATE_TO   = '2027-06-30';
const PROBE     = '2026-08-01';   // a date inside the window, for the overlap check

const RI_TYPE_NET_RETENTION = 3;
const RI_TYPE_QUOTA_SHARE   = 1;
const RI_TYPE_SURPLUS       = 4;

const RETENTION_PCT = '30';
const CESSION_PCT   = '70';

/**
 * The five traceable groups. 'match' is matched against reinsurance_group.group_code
 * with LIKE, because the sheet's class names are prose and the codes are not.
 *
 * band1   - Band 1 quota share capacity, the si_allocation cap (sheet col B)
 * band2   - Band 2 surplus capacity, 0 where the surplus does not apply (col E)
 * cell    - the sheet cell, so a reviewer can trace every number back
 */
$TARGETS = [
    'MATERIALDAMAGEBUSINESSINTERRUPTIONCOMBINED%' => [
        'label' => 'Material Damage & BI (Fire and Allied Perils)',
        'band1' => 10000000, 'band2' => 40000000, 'cell' => 'B22 = $B$9',
    ],
    'ENGINEERING-AND-BI%' => [
        'label' => 'Engineering (CAR, EAR, MB)',
        'band1' => 10000000, 'band2' => 40000000, 'cell' => 'B23 = $B$9',
    ],
    '%MOTOR%OWN%DAMAGE%' => [
        'label' => 'Motor Comprehensive (Own Damage)',
        'band1' => 5000000,  'band2' => 0, 'cell' => 'B24 = $B$13',
    ],
    '%PASSENGER%LIABILIT%' => [
        'label' => 'Motor Passenger Liability',
        'band1' => 2500000,  'band2' => 0, 'cell' => 'B26 = $B$14',
    ],
    '%THIRD%PARTY%LIABILIT%' => [
        'label' => 'Motor Third Party Liability',
        'band1' => 10000000, 'band2' => 0, 'cell' => 'B27 = $B$15',
    ],
];

$APPLY = (($argv[1] ?? '') === 'APPLY');

foreach (['RI_DB_HOST', 'RI_DB_NAME', 'RI_DB_USER', 'RI_DB_PASS'] as $k) {
    if (getenv($k) === false || getenv($k) === '') { fwrite(STDERR, "Missing env var: {$k}\n"); exit(2); }
}
$pdo = new PDO(
    'mysql:host=' . getenv('RI_DB_HOST') . ';port=' . (getenv('RI_DB_PORT') ?: '3306')
        . ';dbname=' . getenv('RI_DB_NAME'),
    getenv('RI_DB_USER'), getenv('RI_DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo $APPLY ? "*** APPLY MODE - writing inside a transaction ***\n\n" : "--- DRY RUN (no writes) ---\n\n";
$money = fn($n) => number_format((float) $n);

/* ------------------------------------------------------------------ *
 * 1. Treaty landscape for the 2026/27 window
 * ------------------------------------------------------------------ */
echo "== TREATIES ACTIVE IN " . DATE_FROM . " .. " . DATE_TO . " ==\n";
$tq = $pdo->prepare("SELECT id, treaty_name, effective_from, effective_to
                     FROM reinsurance_treaty
                     WHERE status = 1 AND NOT (effective_to < ? OR effective_from > ?)
                     ORDER BY treaty_name");
$tq->execute([DATE_FROM, DATE_TO]);
$treaties = $tq->fetchAll();
if (!$treaties) {
    echo "  NONE. No active treaty covers the window - configure the treaty year first\n";
    echo "  (see treaty_year_config.php and docs/RI-04-treaty-year-config-runbook.md).\n";
    exit(1);
}
foreach ($treaties as $t)
    printf("  id=%-4s %-32s %s -> %s\n", $t['id'], $t['treaty_name'], $t['effective_from'], $t['effective_to']);
echo "\n";

/* ------------------------------------------------------------------ *
 * 2. Resolve each target group, and read what it already has
 * ------------------------------------------------------------------ */
$resolved = [];
$unresolved = [];

foreach ($TARGETS as $like => $spec) {
    $g = $pdo->prepare("SELECT id, group_code FROM reinsurance_group WHERE group_code LIKE ? ORDER BY id");
    $g->execute([$like]);
    $rows = $g->fetchAll();

    if (count($rows) !== 1) {
        $unresolved[] = [
            'like'  => $like,
            'label' => $spec['label'],
            'hits'  => array_map(fn($r) => "{$r['id']}:{$r['group_code']}", $rows),
        ];
        continue;
    }
    $resolved[] = ['gid' => (int) $rows[0]['id'], 'code' => $rows[0]['group_code']] + $spec;
}

if ($unresolved) {
    echo "!! GROUP CODES DID NOT RESOLVE TO EXACTLY ONE ROW\n";
    foreach ($unresolved as $u) {
        $hits = $u['hits'] ? implode(', ', $u['hits']) : 'no match';
        printf("   %-34s %s\n      pattern %s -> %s\n", $u['label'], '', $u['like'], $hits);
    }
    echo "\n   Fix the patterns in \$TARGETS against reinsurance_group.group_code, then re-run.\n";
    echo "   Nothing was written.\n";
    exit(1);
}

/* ------------------------------------------------------------------ *
 * 3. Existing formula rows for those groups inside the window
 * ------------------------------------------------------------------ */
echo "== EXISTING CONFIG FOR THE FIVE GROUPS ==\n";
printf("  %-4s %-40s %-6s %-20s %-5s %-16s %-6s %s\n",
    'grp', 'formula_code', 'f#', 's_FormulaType', 'rity', 'si_allocation', 'pct', 'window');
echo '  ' . str_repeat('-', 128) . "\n";

$existing = [];   // gid => list of rows
foreach ($resolved as $r) {
    $q = $pdo->prepare(
        "SELECT f.id AS fid, f.formula_code, f.s_FormulaType, f.reinsurance_type_id AS rity,
                f.product_id, f.type_id,
                d.id AS did, d.si_allocation, d.percentage, d.operator, d.vehicle_type,
                d.date_from, d.date_to,
                td.treaty_id
           FROM reinsurance_formula_details d
           JOIN reinsurance_formula f ON f.id = d.formula_id
      LEFT JOIN reinsurance_treaty_details td ON td.formula_attached = f.id
          WHERE d.group_id = ?
            AND NOT (d.date_to < ? OR d.date_from > ?)
       ORDER BY f.reinsurance_type_id, f.id");
    $q->execute([$r['gid'], DATE_FROM, DATE_TO]);
    $existing[$r['gid']] = $q->fetchAll();

    if (!$existing[$r['gid']]) {
        printf("  %-4s %-40s %s\n", $r['gid'], '(nothing in window)', $r['code']);
        continue;
    }
    foreach ($existing[$r['gid']] as $e) {
        printf("  %-4s %-40s %-6s %-20s %-5s %-16s %-6s %s..%s  treaty=%s\n",
            $r['gid'], substr($e['formula_code'], 0, 40), 'f#' . $e['fid'],
            (string) $e['s_FormulaType'], (string) $e['rity'],
            (string) $e['si_allocation'], (string) $e['percentage'],
            $e['date_from'], $e['date_to'], $e['treaty_id'] ?? '-');
    }
}
echo "\n";

/* ------------------------------------------------------------------ *
 * 4. Compare against the Capacities 2026-27 targets
 * ------------------------------------------------------------------ */
$norm = fn($v) => (float) str_replace([',', ' '], '', (string) $v);

echo "== TARGET vs ACTUAL (Capacities 2026-27) ==\n";
$actions = [];

foreach ($resolved as $r) {
    printf("\n  %s\n    group %d  %s   Band 1 %s  (%s)\n",
        $r['label'], $r['gid'], $r['code'], $money($r['band1']), $r['cell']);

    $rows = $existing[$r['gid']];

    $byType = [];
    foreach ($rows as $e) $byType[(int) $e['rity']][] = $e;

    /* -- Band 1: retention + quota share, capped at band1 -- */
    foreach ([RI_TYPE_NET_RETENTION => ['Net retention', RETENTION_PCT],
              RI_TYPE_QUOTA_SHARE   => ['Quota share',   CESSION_PCT]] as $rity => [$name, $pct]) {

        if (empty($byType[$rity])) {
            printf("      %-14s MISSING       -> create at cap %s, %s%%\n", $name, $money($r['band1']), $pct);
            $actions[] = ['kind' => 'create', 'gid' => $r['gid'], 'label' => $r['label'],
                          'rity' => $rity, 'name' => $name, 'cap' => $r['band1'], 'pct' => $pct];
            continue;
        }
        foreach ($byType[$rity] as $e) {
            $capNow = $norm($e['si_allocation']);
            $pctNow = trim((string) $e['percentage']);
            $capOk  = abs($capNow - $r['band1']) < 0.01;
            $pctOk  = $pctNow === '' || $norm($pctNow) == (float) $pct;

            if ($capOk && $pctOk) {
                printf("      %-14s OK            f#%-5s cap %s  pct %s\n",
                    $name, $e['fid'], $money($capNow), $pctNow === '' ? '-' : $pctNow);
            } else {
                printf("      %-14s MISMATCH      f#%-5s cap %s (want %s)  pct %s (want %s)\n",
                    $name, $e['fid'], $money($capNow), $money($r['band1']),
                    $pctNow === '' ? '-' : $pctNow, $pct);
                $actions[] = ['kind' => 'update', 'did' => (int) $e['did'], 'fid' => (int) $e['fid'],
                              'label' => $r['label'], 'name' => $name,
                              'cap_from' => $capNow, 'cap_to' => $r['band1'],
                              'pct_from' => $pctNow, 'pct_to' => $pct];
            }
        }
    }

    /* -- Band 2: surplus. Verify only; never created here. -- */
    $sur = $byType[RI_TYPE_SURPLUS] ?? [];
    if ($r['band2'] > 0) {
        if (!$sur) {
            printf("      %-14s MISSING       -> run add_2627_property_engineering_layers.php (not this script)\n", 'Surplus');
        } else {
            foreach ($sur as $e) {
                $ok = abs($norm($e['si_allocation']) - $r['band2']) < 0.01;
                printf("      %-14s %-13s f#%-5s cap %s%s\n", 'Surplus', $ok ? 'OK' : 'MISMATCH',
                    $e['fid'], $money($norm($e['si_allocation'])),
                    $ok ? '' : '  (want ' . $money($r['band2']) . ')');
            }
        }
    } elseif ($sur) {
        printf("      %-14s UNEXPECTED    f#%-5s - sheet note 6: surplus does not apply to motor\n",
            'Surplus', $sur[0]['fid']);
    }

    /* -- Band 3: must not be present. -- */
    foreach ($rows as $e) {
        if (in_array((int) $e['rity'], [5, 6], true)) {
            printf("      %-14s PRESENT       f#%-5s %s - Band 3 is provisional (sheet row 4);\n",
                'Auto FAC/FAC', $e['fid'], (string) $e['s_FormulaType']);
            printf("      %-14s               see remove_2627_autofac_facplacement.php before relying on it\n", '');
        }
    }
}

/* ------------------------------------------------------------------ *
 * 5. Plan
 * ------------------------------------------------------------------ */
echo "\n== PLAN ==\n";
if (!$actions) {
    echo "  Nothing to change - all five groups already match Capacities 2026-27.\n";
    echo "\n  Recalculate a policy in each class and confirm the split:\n";
    foreach ($resolved as $r) {
        $w = $r['band1'] * 3;
        $ret = 0.30 * min($w, $r['band1']);
        $qs  = 0.70 * min($w, $r['band1']);
        $sp  = min(max($w - $r['band1'], 0), $r['band2']);
        printf("    %-42s SI %13s -> ret %11s  QS %11s  surplus %12s  FAC %13s\n",
            $r['label'], $money($w), $money($ret), $money($qs), $money($sp),
            $money($w - $ret - $qs - $sp));
    }
    exit(0);
}

$creates = array_values(array_filter($actions, fn($a) => $a['kind'] === 'create'));
$updates = array_values(array_filter($actions, fn($a) => $a['kind'] === 'update'));

foreach ($updates as $a)
    printf("  UPDATE fd#%-5s %-42s %-14s cap %s -> %s   pct %s -> %s\n",
        $a['did'], $a['label'], $a['name'], $money($a['cap_from']), $money($a['cap_to']),
        $a['pct_from'] === '' ? '-' : $a['pct_from'], $a['pct_to']);

foreach ($creates as $a)
    printf("  CREATE        %-42s %-14s cap %s  pct %s%%\n",
        $a['label'], $a['name'], $money($a['cap']), $a['pct']);

if ($creates) {
    echo "\n  !! CREATE requires values this script will not invent: the treaty to attach to,\n";
    echo "     and s_FormulaType / product_id / type_id / operator / vehicle_type. Those are\n";
    echo "     mirrored from an existing row of the same reinsurance_type_id for the same\n";
    echo "     group. None was found for the rows above, so there is nothing to mirror.\n";
    echo "     Resolve by hand from the treaty list at the top, then re-run.\n";
}

if (!$APPLY) {
    echo "\n--- DRY RUN complete. Re-run with APPLY to execute the UPDATEs. ---\n";
    exit(0);
}

if ($creates) {
    echo "\nREFUSING TO APPLY: " . count($creates) . " CREATE action(s) cannot be performed safely.\n";
    echo "Re-run once every group has a row to mirror, or add them explicitly.\n";
    exit(1);
}

/* ------------------------------------------------------------------ *
 * 6. Apply - UPDATEs only, inside a transaction
 * ------------------------------------------------------------------ */
$rollback = [];
$pdo->beginTransaction();
try {
    $sel = $pdo->prepare("SELECT si_allocation, percentage FROM reinsurance_formula_details WHERE id = ?");
    $upd = $pdo->prepare("UPDATE reinsurance_formula_details
                             SET si_allocation = ?, percentage = ?, updated_at = NOW()
                           WHERE id = ?");
    foreach ($updates as $a) {
        $sel->execute([$a['did']]);
        $before = $sel->fetch();
        $rollback[] = sprintf(
            "  UPDATE reinsurance_formula_details SET si_allocation = %s, percentage = %s WHERE id = %d;",
            $pdo->quote((string) $before['si_allocation']),
            $before['percentage'] === null ? 'NULL' : $pdo->quote((string) $before['percentage']),
            $a['did']);
        $upd->execute([number_format($a['cap_to']), $a['pct_to'], $a['did']]);
    }
    $pdo->commit();
    printf("\nCOMMITTED - %d detail row(s) updated.\n", count($updates));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\nROLLED BACK - " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n== ROLLBACK (keep this) ==\n" . implode("\n", $rollback) . "\n";

echo "\nNow recalculate one policy per class and confirm against Capacities 2026-27:\n";
foreach ($resolved as $r) {
    printf("  %-42s Band 1 cap %13s  retention 30%% = %s\n",
        $r['label'], $money($r['band1']), $money(0.30 * $r['band1']));
}
echo "\nSum insured above Band 1 + Band 2 stays outside the treaty until a facultative\n";
echo "placement is recorded - Band 3 is not configured, by design.\n";
