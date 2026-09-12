<?php
/**
 * Re-derives every 2026/27 Facultative Placement attach point from the layers that
 * actually sit beneath it.
 *
 * WHY THIS IS NEEDED. apply_uniform_treaty_limit_2627.php raised every first line to the
 * 10,000,000 treaty limit Reinsurance confirmed on 26 August, and added the missing
 * surplus and Auto FAC layers. It did NOT touch the Facultative Placement rows, which
 * still held attach points derived from the old Schedule A class limits. The result on
 * COMG2026213751 was a set of negative "Credit" figures in the Outside Treaty column —
 * the layers summing to more than the sum insured.
 *
 * Accidental Damage is the clearest case. Attach was 7,500,000, so Fac Placement took
 * 120,050,000 - 7,500,000 = 112,550,000. Add the 10,000,000 first line and the policy
 * allocates 122,550,000 against a sum insured of 120,050,000 — a credit of 2,500,000.
 * It also starved the surplus: there was nothing left for the 40,000,000 layer to take,
 * which is why Accidental Damage showed no surplus at all where Tlamelo's working gives
 * it the full 40,000,000.
 *
 * HOW THE ENGINE READS THESE. `operator` is a COMPARISON, not arithmetic — 4 is ">" and
 * 9 is "*", meaning always applies (PolicyCoverage::getReinsuranceCoverageCalculations,
 * the $ExpressionSign switch). si_allocation is the attach point either way, and the
 * layer takes sum insured less that figure. So the attach has to equal everything below
 * it or the layers cannot reconcile.
 *
 * THE RULE APPLIED HERE:
 *
 *     Fac Placement attaches at  =  first line + surplus + auto fac
 *
 * derived per group from what is configured, not hardcoded — so a group with no surplus
 * gets first line plus auto fac, and a group on the Motor Auto FAC band of 1,500,000
 * gets a lower attach than one on the Property 50,000,000.
 *
 * The operator is also aligned to 9 to match PROPERTYANDBI (f#97) and ENGINEERING
 * (f#99), the two that demonstrably reconcile today.
 *
 * si_allocation is TEXT holding comma-formatted strings. Read through $money, written
 * through $asText.
 *
 * Credentials from the ENVIRONMENT, as treaty_year_config.php:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/fix_fac_attach_points_2627.php        # dry run
 *   php backend/database/manual/fix_fac_attach_points_2627.php APPLY  # execute
 */

$host = getenv('RI_DB_HOST') ?: '';
$name = getenv('RI_DB_NAME') ?: '';
$user = getenv('RI_DB_USER') ?: '';
$pass = getenv('RI_DB_PASS');
$port = getenv('RI_DB_PORT') ?: '3306';

if ($host === '' || $name === '' || $user === '' || $pass === false) {
    fwrite(STDERR, "Set RI_DB_HOST, RI_DB_NAME, RI_DB_USER and RI_DB_PASS first.\n");
    exit(1);
}

$apply  = ($argv[1] ?? '') === 'APPLY';
$money  = fn ($v) => (float) str_replace(',', '', (string) $v);
$asText = fn (float $v) => number_format($v, 0, '.', ',');
$norm   = fn ($t) => strtoupper(str_replace([' ', '_'], '', (string) $t));

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user, $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo "mode     : " . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

$rows = $p->query("
    SELECT g.group_code, fd.group_id, fd.id AS detail_id, fd.si_allocation, fd.operator,
           f.s_FormulaType, f.id AS formula_id
      FROM reinsurance_formula f
      JOIN reinsurance_formula_details fd ON fd.formula_id = f.id
      JOIN reinsurance_group g            ON g.id = fd.group_id
     WHERE f.formula_code LIKE '%26-27'
     ORDER BY g.group_code
")->fetchAll(PDO::FETCH_ASSOC);

/** Collect each group's layers so the attach can be derived from them. */
$byGroup = [];
foreach ($rows as $r) {
    $t = $norm($r['s_FormulaType']);
    $byGroup[$r['group_code']]['group_id'] = (int) $r['group_id'];

    if ($t === 'TSI') {
        $byGroup[$r['group_code']]['first_line'] = $money($r['si_allocation']);
    } elseif ($t === 'SURPLUS') {
        $byGroup[$r['group_code']]['surplus'] = $money($r['si_allocation']);
    } elseif ($t === 'FACULTATIVE') {
        $byGroup[$r['group_code']]['auto_fac'] = $money($r['si_allocation']);
    } elseif ($t === 'FACULATIVEPLACEMENT' || $t === 'FACULTATIVEPLACEMENT') {
        $byGroup[$r['group_code']]['fac'] = [
            'detail_id' => (int) $r['detail_id'],
            'was'       => $r['si_allocation'],
            'operator'  => (int) $r['operator'],
        ];
    }
}

printf("%-28s %12s %12s %12s | %13s %13s %s\n",
    'GROUP', 'FIRST LINE', 'SURPLUS', 'AUTO FAC', 'ATTACH NOW', 'SHOULD BE', 'OP');
printf("%s\n", str_repeat('-', 108));

$fix = [];
foreach ($byGroup as $code => $g) {
    if (!isset($g['fac'])) {
        printf("%-28s %s\n", $code, '-- no Facultative Placement layer, skipped');
        continue;
    }

    $first   = $g['first_line'] ?? 0.0;
    $surplus = $g['surplus']    ?? 0.0;
    $autoFac = $g['auto_fac']   ?? 0.0;
    $should  = $first + $surplus + $autoFac;
    $now     = $money($g['fac']['was']);
    $op      = $g['fac']['operator'];

    $needs = (abs($now - $should) > 0.005) || $op !== 9;
    if ($needs) {
        $fix[] = ['detail_id' => $g['fac']['detail_id'], 'should' => $should,
                  'was' => $g['fac']['was'], 'op' => $op, 'code' => $code];
    }

    printf("%-28s %12s %12s %12s | %13s %13s %s%s\n",
        $code, $asText($first), $surplus ? $asText($surplus) : '-', $autoFac ? $asText($autoFac) : '-',
        $asText($now), $asText($should), $op, $needs ? '  <-- fix' : '');
}

echo str_repeat('-', 108) . "\n";
echo 'attach points to correct : ' . count($fix) . "\n\n";

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$p->beginTransaction();
try {
    $upd = $p->prepare('UPDATE reinsurance_formula_details SET si_allocation = ?, operator = 9 WHERE id = ?');
    $rollback = [];

    foreach ($fix as $f) {
        $upd->execute([$asText($f['should']), $f['detail_id']]);
        $rollback[] = "UPDATE reinsurance_formula_details SET si_allocation = '{$f['was']}', "
            . "operator = {$f['op']} WHERE id = {$f['detail_id']};";
    }

    $p->commit();
    echo 'corrected : ' . count($fix) . "\n\nCOMMITTED.\n\n";
    echo "ROLLBACK BLOCK — keep this:\n";
    foreach ($rollback as $sql) {
        echo "  $sql\n";
    }
    echo "\nRecalculate COMG2026213751. Expect the Outside Treaty credits to disappear and\n";
    echo "Accidental Damage and Electronic Equipment to show their surplus.\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
