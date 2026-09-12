<?php
/**
 * Removes the Auto FAC and Facultative Placement layers from the 2026/27
 * General Quota Share treaty, leaving the Surplus layers in place.
 *
 * WHY. add_2627_property_engineering_layers.php created all five layers for
 * Property and Engineering, copying the 2024/25 shape. Only the Surplus is a
 * treaty cession. RI-TRTY-WP-01 'Programme Structure' section B marks the two
 * removed layers as NON-treaty — Auto FAC "Non-treaty — automatic", FAC
 * "Non-treaty — negotiated" — and its 'Allocation Rules' row 34 states:
 *
 *   "Auto FAC and FAC are derived, not negotiated at treaty level. They are
 *    shown so that the five components reconcile to 100% of the sum insured.
 *    If Auto FAC or FAC is NOT placed on a given risk, that percentage is an
 *    uninsured net exposure, not nil."
 *
 * Booking them as treaty formulas wrote them to policy_reinsurance.treatySI,
 * which reported 151,641,212 of one Property risk as ceded when nothing is
 * placed behind it, and drove the uncovered-gap column to zero. Capacities
 * Notes 2 and 3 confirm neither signed 26/27 slip establishes a facultative
 * facility or an excess of loss programme.
 *
 * Surplus (f#90, f#93) is retained: the paper marks it "Treaty — proportional",
 * borne by surplus reinsurers.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction.
 * Credentials from the ENVIRONMENT:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional)
 */

/** Auto FAC and Fac Placement for Property (19) and Engineering (6), 26/27 only. */
const DROP_IDS = [91, 92, 94, 95];
const KEEP_IDS = [90, 93];
const TREATY_ID = 33;

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

echo $APPLY ? "*** APPLY MODE — deleting inside a transaction ***\n\n" : "--- DRY RUN (no writes) ---\n\n";

$dropList = implode(',', DROP_IDS);

/* ---- validate: only ever touch 26/27 non-treaty layers ---- */
$rows = $pdo->query("
    SELECT fm.id, fm.formula_code, fm.s_FormulaType, fm.reinsurance_type_id AS rt,
           fd.group_id, fd.si_allocation, tm.id AS treaty_id, tm.treaty_name
    FROM reinsurance_formula fm
    JOIN reinsurance_formula_details fd ON fd.formula_id = fm.id
    LEFT JOIN reinsurance_treaty_details td ON td.formula_attached = fm.id
    LEFT JOIN reinsurance_treaty tm ON tm.id = td.treaty_id
    WHERE fm.id IN ({$dropList})
    ORDER BY fm.id")->fetchAll();

if (count($rows) !== count(DROP_IDS)) {
    printf("ERROR: expected %d formulas, found %d. Nothing done.\n", count(DROP_IDS), count($rows));
    exit(1);
}
foreach ($rows as $r) {
    if ((int) $r['treaty_id'] !== TREATY_ID) {
        echo "ERROR: f#{$r['id']} is attached to treaty {$r['treaty_id']} ({$r['treaty_name']}), not "
            . TREATY_ID . ". Refusing to touch another treaty year.\n";
        exit(1);
    }
    if (!in_array($r['s_FormulaType'], ['FACULTATIVE', 'FACULATIVEPLACEMENT'], true)) {
        echo "ERROR: f#{$r['id']} is {$r['s_FormulaType']}, not an Auto FAC / Fac Placement row. Aborting.\n";
        exit(1);
    }
}

echo "TO REMOVE\n" . str_repeat('-', 104) . "\n";
printf("%-5s %-58s %-22s %-8s %s\n", 'f#', 'formula_code', 's_FormulaType', 'group', 'si_allocation');
foreach ($rows as $r)
    printf("%-5s %-58s %-22s %-8s %s\n", $r['id'], mb_strimwidth($r['formula_code'], 0, 58),
        $r['s_FormulaType'], $r['group_id'], $r['si_allocation']);

echo "\nTO KEEP\n" . str_repeat('-', 104) . "\n";
foreach ($pdo->query("
    SELECT fm.id, fm.formula_code, fm.s_FormulaType, fd.group_id, fd.si_allocation
    FROM reinsurance_formula fm
    JOIN reinsurance_formula_details fd ON fd.formula_id = fm.id
    WHERE fm.id IN (" . implode(',', KEEP_IDS) . ") ORDER BY fm.id")->fetchAll() as $r)
    printf("%-5s %-58s %-22s %-8s %s\n", $r['id'], mb_strimwidth($r['formula_code'], 0, 58),
        $r['s_FormulaType'], $r['group_id'], $r['si_allocation']);

if (!$APPLY) {
    echo "\nEffect on a Property / Engineering risk after removal:\n";
    foreach ([20000000, 50000000, 75000000, 201641212] as $w) {
        $sur = min(max($w - 10000000, 0), 40000000);
        printf("  SI %14s ->  retention %11s  GQS %11s  surplus %13s  OUTSIDE TREATY %15s\n",
            number_format($w), number_format(3000000), number_format(7000000),
            number_format($sur), number_format($w - 10000000 - $sur));
    }
    echo "\n  Sum insured above BWP 50,000,000 is reported as outside the treaty, which is\n";
    echo "  what it is until a facultative placement is recorded against the risk.\n";
    echo "\nDry run complete. Re-run with APPLY to execute.\n";
    exit(0);
}

/* ---- apply ---- */
$pdo->beginTransaction();
try {
    $a = $pdo->exec("DELETE FROM reinsurance_treaty_details  WHERE formula_attached IN ({$dropList})");
    $b = $pdo->exec("DELETE FROM reinsurance_formula_details WHERE formula_id       IN ({$dropList})");
    $c = $pdo->exec("DELETE FROM reinsurance_formula         WHERE id               IN ({$dropList})");
    $pdo->commit();
    printf("\nApplied — %d treaty link(s), %d detail row(s), %d formula(s) deleted.\n", $a, $b, $c);
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\nFAILED, rolled back: " . $e->getMessage() . "\n";
    exit(1);
}

$left = $pdo->query("SELECT COUNT(*) FROM reinsurance_treaty_details WHERE treaty_id = " . TREATY_ID)->fetchColumn();
printf("\nTreaty %d now carries %s formulas (was 20, expected 16).\n", TREATY_ID, $left);
echo "\nTo restore, re-run add_2627_property_engineering_layers.php — it recreates any\n";
echo "missing layer by formula_code, though the new ids will not be 91/92/94/95.\n";
echo "\nRecalculate affected policies so the removed layers stop appearing.\n";
