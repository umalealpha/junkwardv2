<?php
/**
 * Adds the Surplus layer for Property and Engineering under the 2026/27 General
 * Quota Share treaty.
 *
 * SOURCE: Alpha Direct RI Treaty Allocation Work Paper RI-TRTY-WP-01, sheets
 * 'Programme Structure' (P04, P08) and 'Allocation Rules' section C. P08 limits
 * the surplus trigger to "Property & Engineering only", and Allocation Rules
 * table A routes only those two mappings to 'GQS/Surplus'.
 *
 * The engine already computes the layer correctly — verified by hand against the
 * paper's own test cases: Surplus reduces to MIN(W-10m, 40m), matching T07 10m,
 * T08 40m, T10 40m. So only the formula was missing, not the logic.
 *
 * SURPLUS ONLY, DELIBERATELY. This script first created Auto FAC (50,000,000)
 * and Facultative Placement (100,000,000) alongside the surplus, copying the
 * 2024/25 shape. Both were removed the same day — see
 * remove_2627_autofac_facplacement.php — because the paper marks them NON-treaty
 * ("Non-treaty — automatic" and "Non-treaty — negotiated", Programme Structure
 * section B) and its Allocation Rules row 34 states that an unplaced Auto FAC or
 * FAC percentage "is an uninsured net exposure, not nil". Booking them as treaty
 * formulas wrote them to policy_reinsurance.treatySI, reporting 151,641,212 of a
 * single Property risk as ceded with nothing placed behind it and driving the
 * uncovered-gap column to zero. Do not re-add them without a signed facultative
 * facility document — Capacities Notes 2 and 3 record that neither 26/27 slip
 * establishes one.
 *
 * ADDITIVE ONLY. Retention and quota share below BWP 10,000,000 are untouched:
 * 30% of the 10,000,000 first line is 3,000,000 and 70% is 7,000,000, which is
 * exactly what the paper specifies. Nothing that currently cedes changes.
 *
 * NOT INCLUDED, deliberately. The paper also removes the sum-insured cap from
 * Motor, Transportation, Miscellaneous and Guarantee. That raises ceded sum
 * insured on COMG2026213751 from 34,727,000 to 1,844,964,212 — 53x — with
 * 1.4bn of it from Fidelity alone, on a mapping the paper itself labels
 * "Guarantee?". It also drops Electronic Equipment and Accidental Damage, which
 * its table does not map. Those changes wait on the slip wording (paper Open
 * Item #3) and on confirmation of the Guarantee mapping.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction.
 * Credentials from the ENVIRONMENT:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional)
 */

const TREATY_ID   = 33;            // GENERAL_QS_2026_2027
const PRODUCT_ID  = 7;             // Commercial
const TYPE_ID     = 36;            // lookup_data: Non-Motor
const OPERATOR    = 4;             // layer formulas use '>' , matching f#4 / f#32 / f#33
const DATE_FROM   = '2026-07-01';
const DATE_TO     = '2027-06-30';

/** group_id => [code prefix used in formula_code] */
$GROUPS = [
    19 => 'MATERIALDAMAGEBUSINESSINTERRUPTIONCOMBINED-COM',   // PROPERTYANDBI_COM
    6  => 'ENGINEERING-AND-BI-COM',                            // ENGINEERING_AND_BI_COM
];

/**
 * layer suffix => [s_FormulaType, reinsurance_type_id, si_allocation]
 *
 * Surplus only. See the header before adding AUTOFAC (FACULTATIVE, 5, 50,000,000)
 * or FACULATIVEPLACEMENT (6, 100,000,000) back here.
 */
$LAYERS = [
    'SURPLUS' => ['SURPLUS', 4, '40,000,000'],
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

echo $APPLY ? "*** APPLY MODE — writing inside a transaction ***\n\n" : "--- DRY RUN (no writes) ---\n\n";

/* ---- validate ---- */
$t = $pdo->query("SELECT treaty_name, effective_from, effective_to FROM reinsurance_treaty
                  WHERE id = " . TREATY_ID)->fetch();
if (!$t || $t['treaty_name'] !== 'GENERAL_QS_2026_2027') {
    echo "ERROR: treaty " . TREATY_ID . " is not GENERAL_QS_2026_2027.\n"; exit(1);
}
printf("treaty %d  %s  %s -> %s\n", TREATY_ID, $t['treaty_name'], $t['effective_from'], $t['effective_to']);

foreach (array_keys($GROUPS) as $gid) {
    $g = $pdo->query("SELECT group_code FROM reinsurance_group WHERE id = {$gid}")->fetch();
    if (!$g) { echo "ERROR: group {$gid} not found.\n"; exit(1); }
    printf("group  %-3d %s\n", $gid, $g['group_code']);
}

/* ---- plan ---- */
$plan = [];
foreach ($GROUPS as $gid => $prefix) {
    foreach ($LAYERS as $suffix => [$formulaType, $riType, $alloc]) {
        $code = "{$prefix}-{$suffix}-26-27";
        $exists = $pdo->prepare("SELECT id FROM reinsurance_formula WHERE formula_code = ?");
        $exists->execute([$code]);
        if ($exists->fetchColumn()) {
            echo "  SKIP (already exists): {$code}\n";
            continue;
        }
        $plan[] = compact('gid', 'code', 'formulaType', 'riType', 'alloc');
    }
}

echo "\n" . str_repeat('-', 104) . "\n";
printf("%-58s %-22s %-6s %s\n", 'formula_code', 's_FormulaType', 'ritype', 'si_allocation');
echo str_repeat('-', 104) . "\n";
foreach ($plan as $p)
    printf("%-58s %-22s %-6s %s\n", $p['code'], $p['formulaType'], $p['riType'], $p['alloc']);
printf("\n%d formula(s) to create, each attached to treaty %d.\n", count($plan), TREATY_ID);

if (!$plan) { echo "Nothing to do.\n"; exit(0); }

if (!$APPLY) {
    echo "\nEffect on a Property / Engineering risk once applied:\n";
    foreach ([20000000, 50000000, 75000000, 201641212] as $w) {
        $sur = min(max($w - 10000000, 0), 40000000);
        printf("  SI %14s ->  retention %11s  GQS %11s  surplus %13s  OUTSIDE TREATY %15s\n",
            number_format($w), number_format(3000000), number_format(7000000),
            number_format($sur), number_format($w - 10000000 - $sur));
    }
    echo "\n  Sum insured above BWP 50,000,000 stays outside the treaty, which is what it\n";
    echo "  is until a facultative placement is recorded against the risk.\n";
    echo "\nDry run complete. Re-run with APPLY to execute.\n";
    exit(0);
}

/* ---- apply ---- */
$created = [];
$pdo->beginTransaction();
try {
    $fIns = $pdo->prepare("INSERT INTO reinsurance_formula
        (product_id, type_id, formula_code, formula_name, s_FormulaType, reinsurance_type_id, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,1,NOW(),NOW())");
    $dIns = $pdo->prepare("INSERT INTO reinsurance_formula_details
        (formula_id, group_id, vehicle_type, operator, si_allocation, n_ValueLimitsBetween, percentage, date_from, date_to, created_at, updated_at)
        VALUES (?,?,NULL,?,?,NULL,NULL,?,?,NOW(),NOW())");
    $tIns = $pdo->prepare("INSERT INTO reinsurance_treaty_details
        (treaty_id, formula_attached, created_at, updated_at) VALUES (?,?,NOW(),NOW())");

    foreach ($plan as $p) {
        $fIns->execute([PRODUCT_ID, TYPE_ID, $p['code'], $p['code'], $p['formulaType'], $p['riType']]);
        $fid = (int) $pdo->lastInsertId();
        $dIns->execute([$fid, $p['gid'], OPERATOR, $p['alloc'], DATE_FROM, DATE_TO]);
        $tIns->execute([TREATY_ID, $fid]);
        $created[] = ['id' => $fid, 'code' => $p['code']];
    }
    $pdo->commit();
    printf("\nApplied — %d formula(s) created and attached.\n", count($created));
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\nFAILED, rolled back: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nCREATED\n" . str_repeat('-', 70) . "\n";
foreach ($created as $c) printf("  f#%-4s %s\n", $c['id'], $c['code']);

$ids = implode(',', array_column($created, 'id'));
echo "\nROLLBACK (keep this)\n" . str_repeat('-', 70) . "\n";
echo "  DELETE FROM reinsurance_treaty_details WHERE formula_attached IN ({$ids});\n";
echo "  DELETE FROM reinsurance_formula_details WHERE formula_id IN ({$ids});\n";
echo "  DELETE FROM reinsurance_formula WHERE id IN ({$ids});\n";

echo "\nNow recalculate a Property or Engineering policy with sum insured above\n";
echo "BWP 10,000,000 and confirm retention 3,000,000 + quota share 7,000,000 +\n";
echo "surplus MIN(SI-10m, 40m), with the balance reported outside the treaty.\n";
