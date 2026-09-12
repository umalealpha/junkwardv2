<?php
/**
 * Adds the Auto FAC and FAC Placement layers for MOTOR_COM under the 2026/27 Motor
 * Quota Share.
 *
 * SOURCE: D:\reinsurence_25-26\Reinsurance.xlsx, sheet 'Capacities 2026-27', row 24
 * (Motor Comprehensive - Own Damage). Cells, verbatim:
 *     B24 = $B$13        5,000,000   Band 1 quota share - already configured, f#82 / f#83
 *     E24 = 0                    0   Band 2 surplus - note 6, surplus is not motor
 *     F24                1,500,000   Band 3 Auto FAC / FAC   <- Auto FAC capacity
 *     G24 = B24+E24+F24  6,500,000   Total capacity          <- FAC Placement attaches here
 *
 * THE SHEET HAS ONE BAND 3, NOT TWO. Column F is headed "Band 3 - Auto FAC / FAC" and
 * note 5 reads: "Band 3 is Auto FAC or FAC, decided per policy. The underwriter tests the
 * open market first; if quotes are not more favourable than Grand Re's Auto FAC rate, the
 * risk is placed with Auto FAC." So 1,500,000 is a single capacity and the choice between
 * the two markets is a per-policy decision, not a second layer stacked on the first.
 *
 * The two formulas below therefore do NOT split that 1,500,000. They play different roles
 * in the engine, matching the convention the Property layers already use:
 *     FACULTATIVE          si_allocation = the Band 3 capacity      (F24, 1,500,000)
 *     FACULATIVEPLACEMENT  si_allocation = total treaty capacity    (G24, 6,500,000)
 * Auto FAC fills Band 3; FAC Placement is whatever sits above the treaty entirely.
 *
 * FAC PLACEMENT'S si_allocation IS DOCUMENTATION ONLY FOR MOTOR. MotorComReinsurance
 * derives its threshold from the group's configured total capacity, not from this column
 * (it was a hardcoded 6,500,000 until configuredTreatyCapacityFor replaced it). The value
 * is set to G24 anyway so the row states its own attachment point and cannot drift away
 * from what the engine computes. The general/Property branch DOES read si_allocation here,
 * so the convention has to hold either way.
 *
 * NOT CLONED FROM 2024/25, DELIBERATELY. MUNICH_COM_2024_2025 carries both layers (f#15
 * FACULTATIVE 1,500,000 and f#43 FACULATIVEPLACEMENT 1,500,000) but its Band 1 terms are a
 * different structure: f#13 / f#14 are 20/80 against 26/27's 30/70, and pre-2026 years also
 * applied a P300,000 net-retention cap that no longer exists. Note also that f#43's
 * si_allocation of 1,500,000 does not follow the convention above - total motor capacity was
 * 6,500,000 in 2024/25 as well - which is a second reason not to carry it across.
 *
 * BAND 3 IS PROVISIONAL. Sheet row 4 and note 7: every non-proportional figure is carried
 * forward from the 2024/25 table pending 2026 terms, and F24 is a hardcoded literal in the
 * sheet rather than formula-linked to B12 the way Property and Engineering are. The number
 * is the best sourced figure available, not a confirmed 2026/27 term.
 *
 * ONLY MOTOR_COM. The sheet also gives Band 3 of 1,500,000 to Motor Trailers (F25) and
 * Motor Third Party Liability (F27), both hardcoded and pending. Neither is added here:
 * Trailers maps to a group this script has not verified, and Third Party Liability has no
 * group of its own in the exclusion list at PolicyCoverage line 741 - it is most likely a
 * coverage inside MOTOR_COM, which would make a separate formula wrong. Motor Passenger
 * Liability (F26) and Riot and Strike (F28) are zero and need nothing. Motor Traders, which
 * IS on the test policy as MOTOR_TRADERS_COM_EXT / _INT, has no row in the sheet at all -
 * raise that with Finance.
 *
 * HOW THE ENGINE WILL USE THEM. MOTOR_COM is excluded from the general master query and
 * runs through MotorComReinsurance:
 *     FACAuto = net retention + quota share actually used              (Band 1)
 *     Auto FAC     = SI > FACAuto ? (SI <= TCL ? SI - FACAuto : si_allocation) : 0
 *     FAC Placement = SI > TCL ? SI - TCL + QSCShare - FACAuto : 0
 * Both read TCL from configuredTreatyCapacityFor, so they cannot disagree about where the
 * treaty is exhausted. Both derive FACAuto from arrays the TSI branch fills earlier in the
 * same loop and the query orders by formula_id, so these rows must sort AFTER f#82 / f#83.
 * New rows get higher ids, which satisfies that - do not renumber them by hand.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction.
 * Credentials from the ENVIRONMENT:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional)
 *
 * PowerShell:
 *   $env:RI_DB_HOST='graphite-test-write.c1y5xpqlrcpg.af-south-1.rds.amazonaws.com'
 *   $env:RI_DB_NAME='Graphite_live'; $env:RI_DB_USER='graphitebwlive'; $env:RI_DB_PASS='***'
 *   php backend/database/manual/add_2627_motor_autofac.php          # dry run
 *   php backend/database/manual/add_2627_motor_autofac.php APPLY    # execute
 */

const TREATY_NAME = 'MOTOR_QS_2026_2027';
const GROUP_CODE  = 'MOTOR_COM';
const OPERATOR    = 4;   // '>' — matches the 24/25 motor layers and the Property ones.
                         //  Only has to be NOT NULL for the master query to return the row.

/**
 * formula_code prefix for the new rows.
 *
 * Leave empty to derive it as the longest common prefix of the group's existing Band 1
 * codes, which is the safe default — it cannot disagree with whatever convention this
 * group already follows. Set it explicitly only if the derived value looks wrong in the
 * dry run, which prints it.
 *
 * The 2024/25 motor layers are named MOTORCOMPREHENSIVE-COM-AUTOFAC and
 * MOTORCOMPREHENSIVE-COM-FACULATIVEPLACEMENT (f#15, f#43), so 'MOTORCOMPREHENSIVE-COM'
 * is the expected answer here.
 */
const CODE_PREFIX = '';

/** A derived prefix shorter than this is treated as a failed derivation, not a name. */
const MIN_PREFIX_LENGTH = 8;

const RI_TYPE_NET_RETENTION = 3;
const RI_TYPE_QUOTA_SHARE   = 1;

/**
 * suffix => [s_FormulaType, reinsurance_type_id, si_allocation, sheet cell, what it means]
 * Order matters only for readability; the engine orders by formula_id.
 */
$LAYERS = [
    '-AUTOFAC-26-27'             => ['FACULTATIVE',         5, '1,500,000', 'F24', 'Band 3 capacity'],
    '-FACULATIVEPLACEMENT-26-27' => ['FACULATIVEPLACEMENT', 6, '6,500,000', 'G24', 'total treaty capacity'],
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
$money = fn($v) => number_format((float) str_replace(',', '', (string) $v));
$num   = fn($v) => (float) str_replace(',', '', (string) $v);

/* ---------------- resolve treaty ---------------- */
$s = $pdo->prepare("SELECT id, treaty_name, effective_from, effective_to, status
                      FROM reinsurance_treaty WHERE treaty_name = ?");
$s->execute([TREATY_NAME]);
$treaties = $s->fetchAll();
if (count($treaties) !== 1) {
    echo "ERROR: treaty_name '" . TREATY_NAME . "' matched " . count($treaties) . " rows, expected 1.\n";
    foreach ($treaties as $t) echo "   id={$t['id']}  {$t['treaty_name']}\n";
    exit(1);
}
$treaty = $treaties[0];
printf("treaty  %-4s %-24s %s -> %s  (status %s)\n",
    $treaty['id'], $treaty['treaty_name'], $treaty['effective_from'], $treaty['effective_to'], $treaty['status']);

/* ---------------- resolve group ---------------- */
$s = $pdo->prepare("SELECT id, group_code FROM reinsurance_group WHERE group_code = ?");
$s->execute([GROUP_CODE]);
$groups = $s->fetchAll();
if (count($groups) !== 1) {
    echo "ERROR: group_code '" . GROUP_CODE . "' matched " . count($groups) . " rows, expected 1.\n";
    foreach ($groups as $g) echo "   id={$g['id']}  {$g['group_code']}\n";
    exit(1);
}
$group = $groups[0];
printf("group   %-4s %s\n\n", $group['id'], $group['group_code']);

/* ---------------- read what is already there ---------------- */
$s = $pdo->prepare(
    "SELECT fm.id AS fid, fm.formula_code, fm.s_FormulaType,
            fm.reinsurance_type_id AS rity, fm.product_id, fm.type_id, fm.status,
            fd.vehicle_type, fd.operator, fd.si_allocation, fd.percentage,
            fd.date_from, fd.date_to
       FROM reinsurance_treaty_details td
       JOIN reinsurance_formula fm         ON fm.id = td.formula_attached
       JOIN reinsurance_formula_details fd ON fd.formula_id = fm.id
      WHERE td.treaty_id = ? AND fd.group_id = ?
      ORDER BY fm.id");
$s->execute([$treaty['id'], $group['id']]);
$existing = $s->fetchAll();

echo "ALREADY ON THIS TREATY FOR {$group['group_code']}\n" . str_repeat('-', 112) . "\n";
if (!$existing) {
    echo "  (none)\n";
} else {
    printf("%-6s %-46s %-22s %-5s %-14s %s\n", 'f#', 'formula_code', 's_FormulaType', 'rity', 'si_allocation', 'pct');
    foreach ($existing as $e) {
        printf("f#%-4s %-46s %-22s %-5s %-14s %s\n",
            $e['fid'], $e['formula_code'], (string) $e['s_FormulaType'], (string) $e['rity'],
            (string) $e['si_allocation'], (string) $e['percentage']);
    }
}
echo "\n";

/* ---------------- Band 1 is the mirror source and a hard prerequisite ---------------- */
$band1 = array_values(array_filter($existing, fn($e) =>
    in_array((int) $e['rity'], [RI_TYPE_NET_RETENTION, RI_TYPE_QUOTA_SHARE], true)));

if (!$band1) {
    echo "ERROR: no Band 1 (net retention / quota share) formula on this treaty for this group.\n";
    echo "product_id, type_id, the date window and the code prefix are all mirrored from those\n";
    echo "rows, and the engine derives both layers below from the Band 1 it actually used.\n";
    exit(1);
}
$mirror = $band1[0];

$b1cap = 0.0;
foreach ($band1 as $b) $b1cap = max($b1cap, $num($b['si_allocation']));

/* ---------------- resolve the code prefix ---------------- */
$codes = array_column($band1, 'formula_code');

if (CODE_PREFIX !== '') {
    $prefix = CODE_PREFIX;
    printf("code prefix  %s   (set explicitly)\n\n", $prefix);
} else {
    $prefix = $codes[0];
    foreach ($codes as $c) {
        $n = min(strlen($prefix), strlen($c));
        $i = 0;
        while ($i < $n && $prefix[$i] === $c[$i]) { $i++; }
        $prefix = substr($prefix, 0, $i);
    }
    $prefix = rtrim($prefix, '-_ ');

    if (strlen($prefix) < MIN_PREFIX_LENGTH) {
        echo "ERROR: derived prefix '" . $prefix . "' is shorter than " . MIN_PREFIX_LENGTH . " characters.\n";
        echo "Derived from: " . implode(', ', $codes) . "\n";
        echo "Those codes have too little in common to name a formula from. Set CODE_PREFIX\n";
        echo "explicitly at the top of this script — for MOTOR_COM it should be\n";
        echo "'MOTORCOMPREHENSIVE-COM', matching f#15 and f#43 on the 2024/25 treaty.\n";
        exit(1);
    }
    printf("code prefix  %s   (derived from %s)\n\n", $prefix, implode(' + ', $codes));
}

/* ---------------- plan, one entry per layer ---------------- */
$haveRity = [];
foreach ($existing as $e) $haveRity[(int) $e['rity']] = $e;

$dup  = $pdo->prepare("SELECT id FROM reinsurance_formula WHERE formula_code = ?");
$plan = [];
$skips = [];

foreach ($LAYERS as $suffix => [$ftype, $rity, $alloc, $cell, $meaning]) {
    if (isset($haveRity[$rity])) {
        $h = $haveRity[$rity];
        $skips[] = ["{$ftype}", "already present as f#{$h['fid']} {$h['formula_code']}"
            . " (si_allocation {$h['si_allocation']}) - a second would cede the layer twice"];
        continue;
    }
    $code = $prefix . $suffix;
    $dup->execute([$code]);
    if ($dup->fetchColumn()) {
        $skips[] = [$code, 'formula_code already exists'];
        continue;
    }
    $plan[] = compact('code', 'ftype', 'rity', 'alloc', 'cell', 'meaning');
    $haveRity[$rity] = ['fid' => 'planned', 'formula_code' => $code, 'si_allocation' => $alloc];
}

if ($skips) {
    echo "SKIPPED\n" . str_repeat('-', 112) . "\n";
    foreach ($skips as [$what, $why]) printf("  %-30s %s\n", $what, $why);
    echo "\n";
}

if (!$plan) { echo "Nothing to create.\n"; exit(0); }

echo "TO CREATE\n" . str_repeat('-', 112) . "\n";
printf("%-48s %-22s %-5s %-14s %-6s %s\n", 'formula_code', 's_FormulaType', 'rity', 'si_allocation', 'cell', 'meaning');
foreach ($plan as $p)
    printf("%-48s %-22s %-5s %-14s %-6s %s\n",
        $p['code'], $p['ftype'], $p['rity'], $p['alloc'], $p['cell'], $p['meaning']);

printf("\n  group_id %s   treaty %s %s\n", $group['id'], $treaty['id'], $treaty['treaty_name']);
printf("  mirrored from f#%s  product_id=%s type_id=%s vehicle_type=%s %s..%s\n",
    $mirror['fid'], (string) $mirror['product_id'], (string) $mirror['type_id'],
    var_export($mirror['vehicle_type'], true), (string) $mirror['date_from'], (string) $mirror['date_to']);

/* ---------------- projected effect ---------------- */
$afcl = 0.0;
foreach ($LAYERS as [$ft, $r, $a]) if ($r === 5) $afcl = $num($a);
foreach ($existing as $e) if ((int) $e['rity'] === 5) $afcl = $num($e['si_allocation']);
$TCL = $b1cap + $afcl;

echo "\nPROJECTED EFFECT\n" . str_repeat('-', 112) . "\n";
printf("  Band 1 %s   Auto FAC %s   total capacity (TCL) %s\n", $money($b1cap), $money($afcl), $money($TCL));
printf("  TCL is derived by configuredTreatyCapacityFor, not read from si_allocation.\n\n");
printf("  %13s %13s %13s %13s %13s %13s\n",
    'sum insured', 'retention', 'quota share', 'auto fac', 'fac placement', 'total');
$retShare = null; $cedShare = null;
foreach ($band1 as $b) {
    $pct = $num($b['percentage']);
    if ((int) $b['rity'] === RI_TYPE_NET_RETENTION) $retShare = $pct / 100;
    else $cedShare = $pct / 100;
}
if ($retShare === null || $cedShare === null) {
    echo "  (cannot project - Band 1 percentage missing on one leg)\n";
} else {
    foreach ([4000000, 5000000, 6500000, 8000000, 34000000] as $w) {
        $QSC = min($w, $b1cap);
        $net = $QSC * $retShare;
        $quo = $QSC * $cedShare;
        $FACAuto = $net + $quo;
        $fac = ($w > $FACAuto && $FACAuto != 0) ? (($w <= $TCL) ? $w - $FACAuto : $afcl) : 0.0;
        $fp  = ($w > $TCL) ? $w - $TCL + $b1cap - $FACAuto : 0.0;
        printf("  %13s %13s %13s %13s %13s %13s\n",
            $money($w), $money($net), $money($quo), $money($fac), $money($fp),
            $money($net + $quo + $fac + $fp));
    }
    echo "\n  The total column must equal the sum insured on every row.\n";
}

if (!$APPLY) {
    echo "\nDry run complete. Re-run with APPLY to execute.\n";
    exit(0);
}

/* ---------------- apply ---------------- */
$created = [];
$pdo->beginTransaction();
try {
    $fIns = $pdo->prepare("INSERT INTO reinsurance_formula
        (product_id, type_id, formula_code, formula_name, s_FormulaType, reinsurance_type_id, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
    $dIns = $pdo->prepare("INSERT INTO reinsurance_formula_details
        (formula_id, group_id, vehicle_type, operator, si_allocation, n_ValueLimitsBetween, percentage, date_from, date_to, created_at, updated_at)
        VALUES (?,?,?,?,?,NULL,NULL,?,?,NOW(),NOW())");
    $tIns = $pdo->prepare("INSERT INTO reinsurance_treaty_details
        (treaty_id, formula_attached, created_at, updated_at) VALUES (?,?,NOW(),NOW())");

    foreach ($plan as $p) {
        $fIns->execute([$mirror['product_id'], $mirror['type_id'], $p['code'], $p['code'],
                        $p['ftype'], $p['rity'], $mirror['status']]);
        $fid = (int) $pdo->lastInsertId();
        $dIns->execute([$fid, $group['id'], $mirror['vehicle_type'], OPERATOR, $p['alloc'],
                        $mirror['date_from'], $mirror['date_to']]);
        $tIns->execute([$treaty['id'], $fid]);
        $created[] = ['id' => $fid, 'code' => $p['code']];
    }
    $pdo->commit();
    printf("\nCOMMITTED - %d formula(s) created and attached to treaty %s.\n", count($created), $treaty['id']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\nROLLED BACK - " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nCREATED\n" . str_repeat('-', 70) . "\n";
foreach ($created as $c) printf("  f#%-5s %s\n", $c['id'], $c['code']);

$ids = implode(',', array_column($created, 'id'));
echo "\nROLLBACK (keep this)\n" . str_repeat('-', 70) . "\n";
echo "  DELETE FROM reinsurance_treaty_details  WHERE formula_attached IN ({$ids});\n";
echo "  DELETE FROM reinsurance_formula_details WHERE formula_id IN ({$ids});\n";
echo "  DELETE FROM reinsurance_formula         WHERE id IN ({$ids});\n";

echo "\nNow recalculate a MOTOR_COM risk above BWP 6,500,000 and confirm the four components\n";
echo "add back to the sum insured. Auto FAC and FAC Placement will report as ceded even where\n";
echo "no placement exists behind them - that is exposure, not cover, until one is recorded.\n";
