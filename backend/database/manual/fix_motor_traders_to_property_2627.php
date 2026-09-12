<?php
/**
 * Moves MOTOR_TRADERS_COM_EXT and MOTOR_TRADERS_COM_INT onto the GENERAL Quota Share
 * with a surplus, per Tlamelo Chimidza's point 4 of 24 August 2026.
 *
 * THE PROBLEM. Both groups map to Property — his answer was "In essence we have Motor
 * Traders External and Motor Traders Internal which should all be under property" —
 * but their formulas are attached to MOTOR_QS_2026_2027 and carry the Motor Band 3
 * pattern (Auto FAC 1,500,000, Fac Placement 11,500,000). Their first line is already
 * the Property 10,000,000, so the configuration is half-Property, half-Motor.
 *
 * WHAT IT SHOWS ON THE TAB. MOTOR_TRADERS_COM_EXT has a sum insured of 10,210,000, so
 * 210,000 falls above the first line. On the Motor treaty there is no surplus, so that
 * 210,000 lands in Auto FAC — reported as retained. Tlamelo's own manual working
 * (COMG2026213751 VTEST POLICY RI.xlsb, cell K3) puts it in SURPLUS, which is ceded.
 * Small on one policy; it is the difference between ceded and retained on every Motor
 * Traders risk.
 *
 * WHAT THIS CHANGES.
 *   1. Re-attaches the four existing formulas per group from treaty 34 to treaty 33.
 *   2. Adds a SURPLUS formula of 40,000,000 to each group, copying the shape of
 *      MATERIALDAMAGEBUSINESSINTERRUPTIONCOMBINED-COM-SURPLUS-26-27 (f#90).
 *   3. Re-points Auto FAC from the Motor band (1,500,000) to the Property band
 *      (50,000,000), and Fac Placement from 11,500,000 to the Property treaty
 *      capacity of 100,000,000.
 *
 * WHAT IT DOES NOT CHANGE. The 10,000,000 first line and its 3,000,000 / 7,000,000
 * split are already correct for Property and are left alone.
 *
 * NOT RUN YET, AND THERE IS A REASON TO WAIT. His manual working and his written
 * instruction of 25 August disagree on the class limits — his working ignores them,
 * the configuration applies them, and the gap is 56,172,000 of cession on this one
 * policy. This script only fixes the treaty attachment, which both agree on. Hold it
 * until the class-limit question is answered, so two changes are not tangled together
 * in the same reconciliation.
 *
 * Credentials from the ENVIRONMENT, as treaty_year_config.php:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/fix_motor_traders_to_property_2627.php        # dry run
 *   php backend/database/manual/fix_motor_traders_to_property_2627.php APPLY  # execute
 */

const GENERAL_TREATY = 33;   // GENERAL_QS_2026_2027
const MOTOR_TREATY   = 34;   // MOTOR_QS_2026_2027
const SURPLUS_MODEL  = 90;   // MATERIALDAMAGEBUSINESSINTERRUPTIONCOMBINED-COM-SURPLUS-26-27

const GROUPS = ['MOTOR_TRADERS_COM_EXT', 'MOTOR_TRADERS_COM_INT'];

/** The Property bands these groups should carry, replacing the Motor ones. */
const PROPERTY_BANDS = [
    'Surplus'              => 40000000.0,
    'FACULTATIVE'          => 50000000.0,   // Auto FAC capacity
    'FACULATIVEPLACEMENT'  => 100000000.0,  // treaty capacity before individual FAC
];

$host = getenv('RI_DB_HOST') ?: '';
$name = getenv('RI_DB_NAME') ?: '';
$user = getenv('RI_DB_USER') ?: '';
$pass = getenv('RI_DB_PASS');
$port = getenv('RI_DB_PORT') ?: '3306';

if ($host === '' || $name === '' || $user === '' || $pass === false) {
    fwrite(STDERR, "Set RI_DB_HOST, RI_DB_NAME, RI_DB_USER and RI_DB_PASS first.\n");
    exit(1);
}

$apply = ($argv[1] ?? '') === 'APPLY';

/*
 * si_allocation is a TEXT column holding comma-formatted strings — "10,000,000", not
 * 10000000. So it has to be read through a parser and written back in the same shape.
 *
 * This is not cosmetic. A plain (float) cast on "11,500,000" yields 11, which is how
 * the first dry run of this script reported an 11,500,000 band as "11". Writing a bare
 * integer back would leave this row in a different format from every other row in the
 * table, and the engine parses these strings itself.
 */
$money  = fn ($v) => (float) str_replace(',', '', (string) $v);
$asText = fn (float $v) => number_format($v, 0, '.', ',');

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user, $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo "mode     : " . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

$in = "'" . implode("','", GROUPS) . "'";
$rows = $p->query("
    SELECT g.group_code, g.id AS group_id, f.id AS formula_id, f.formula_code,
           f.s_FormulaType, fd.id AS detail_id, fd.si_allocation,
           td.id AS treaty_detail_id, td.treaty_id
      FROM reinsurance_group g
      JOIN reinsurance_formula_details fd ON fd.group_id = g.id
      JOIN reinsurance_formula f          ON f.id = fd.formula_id
      LEFT JOIN reinsurance_treaty_details td ON td.formula_attached = f.id
     WHERE g.group_code IN ($in)
       AND f.formula_code LIKE '%26-27'
     ORDER BY g.group_code, f.s_FormulaType
")->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    fwrite(STDERR, "ABORTED: no 2026/27 formulas found for those groups.\n");
    exit(1);
}

printf("%-24s %-22s %14s  %s\n", 'GROUP', 'TYPE', 'SI ALLOCATION', 'ACTION');
printf("%s\n", str_repeat('-', 96));

$reattach = [];
$reband   = [];
$needSurplus = array_fill_keys(GROUPS, true);

foreach ($rows as $r) {
    $type   = $r['s_FormulaType'];
    $action = [];

    if ((int) $r['treaty_id'] === MOTOR_TREATY) {
        $reattach[] = (int) $r['treaty_detail_id'];
        $action[] = 'move to GENERAL_QS';
    }

    foreach (PROPERTY_BANDS as $bandType => $amount) {
        if (strcasecmp($type, $bandType) === 0 && $money($r['si_allocation']) !== $amount) {
            $reband[] = ['id' => (int) $r['detail_id'], 'amount' => $amount];
            $action[] = 'si ' . $asText($money($r['si_allocation'])) . ' -> ' . $asText($amount);
        }
    }

    if (strcasecmp($type, 'Surplus') === 0) {
        $needSurplus[$r['group_code']] = false;
    }

    printf("%-24s %-22s %14s  %s\n", $r['group_code'], $type,
        $asText($money($r['si_allocation'])), $action ? implode('; ', $action) : 'unchanged');
}

echo str_repeat('-', 96) . "\n";
echo 'formulas to re-attach to GENERAL_QS : ' . count($reattach) . "\n";
echo 'band amounts to correct             : ' . count($reband) . "\n";
echo 'SURPLUS formulas to add             : ' . count(array_filter($needSurplus)) . "\n";
foreach (array_keys(array_filter($needSurplus)) as $g) {
    echo "    $g  -> SURPLUS 40,000,000\n";
}
echo "\n";

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$model = $p->query('SELECT * FROM reinsurance_formula WHERE id = ' . SURPLUS_MODEL)->fetch(PDO::FETCH_ASSOC);
if (!$model) {
    fwrite(STDERR, "ABORTED: model surplus formula #" . SURPLUS_MODEL . " not found.\n");
    exit(1);
}

$p->beginTransaction();
try {
    // 1. Re-attach to the General treaty.
    if ($reattach) {
        $p->prepare('UPDATE reinsurance_treaty_details SET treaty_id = ? WHERE id IN ('
            . implode(',', array_fill(0, count($reattach), '?')) . ')')
          ->execute(array_merge([GENERAL_TREATY], $reattach));
    }

    // 2. Correct the Auto FAC / Fac Placement bands to the Property capacities.
    $bandUpd = $p->prepare('UPDATE reinsurance_formula_details SET si_allocation = ? WHERE id = ?');
    foreach ($reband as $b) {
        $bandUpd->execute([$asText($b['amount']), $b['id']]);
    }

    // 3. Add the missing SURPLUS formulas, shaped like the Property one.
    $insF = $p->prepare(
        'INSERT INTO reinsurance_formula
            (product_id, type_id, formula_code, formula_name, s_FormulaType, reinsurance_type_id, status, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,NOW(),NOW())'
    );
    $insD = $p->prepare(
        'INSERT INTO reinsurance_formula_details
            (formula_id, group_id, operator, si_allocation, created_at, updated_at)
         VALUES (?,?,?,?,NOW(),NOW())'
    );
    $insT = $p->prepare(
        'INSERT INTO reinsurance_treaty_details (treaty_id, formula_attached, created_at, updated_at)
         VALUES (?,?,NOW(),NOW())'
    );

    $added = 0;
    foreach (array_keys(array_filter($needSurplus)) as $code) {
        $gid = null;
        foreach ($rows as $r) {
            if ($r['group_code'] === $code) {
                $gid = (int) $r['group_id'];
                break;
            }
        }
        if (!$gid) {
            throw new RuntimeException("no group id for $code");
        }

        $fcode = str_replace('_', '', $code) . '-SURPLUS-26-27';
        $insF->execute([
            $model['product_id'], $model['type_id'], $fcode, $fcode,
            $model['s_FormulaType'], $model['reinsurance_type_id'], $model['status'],
        ]);
        $newId = (int) $p->lastInsertId();

        $insD->execute([$newId, $gid, 4, $asText(PROPERTY_BANDS['Surplus'])]);
        $insT->execute([GENERAL_TREATY, $newId]);
        $added++;
        echo "added formula #$newId  $fcode\n";
    }

    // Verify: nothing for these groups may remain on the Motor treaty.
    $left = (int) $p->query("
        SELECT COUNT(*) FROM reinsurance_group g
          JOIN reinsurance_formula_details fd ON fd.group_id = g.id
          JOIN reinsurance_treaty_details td  ON td.formula_attached = fd.formula_id
         WHERE g.group_code IN ($in) AND td.treaty_id = " . MOTOR_TREATY
    )->fetchColumn();
    if ($left > 0) {
        throw new RuntimeException("$left formula(s) still on the Motor treaty");
    }

    $p->commit();
    echo "\nre-attached : " . count($reattach) . "\n";
    echo 'rebanded    : ' . count($reband) . "\n";
    echo "surplus added: $added\n\nCOMMITTED.\n";
    echo "Recalculate COMG2026213751 and check MOTOR_TRADERS_COM_EXT shows 210,000 as SURPLUS, not Auto FAC.\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
