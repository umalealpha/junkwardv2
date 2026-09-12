<?php
/**
 * Removes MISC_COM's 2026/27 Auto FAC layer and re-derives its Fac Placement attach point.
 *
 * WHY. MISCELLANEOUSCLASSES-COM-AUTOFAC-26-27 (formula 108) carries 2,000,000, which is a
 * straight carry-forward of the 2024/25 row (formula 22, also 2,000,000) and has no 2026/27
 * source of any kind:
 *
 *   - Reinsurance's manual working of 26 August reports nil Auto FAC for Miscellaneous.
 *   - The capacities table built from the signed slips reads "Not provided under this
 *     treaty" in the AutoFAC column for EVERY class, and Note 2 states that neither treaty
 *     establishes a facultative facility, so no AutoFAC capacity can be stated at all.
 *
 * A layer nobody can point to a document for should not sit between the treaty and the
 * facultative market, deciding how 2,000,000 of every large Miscellaneous risk is reported.
 *
 * WHAT CHANGES. Deleting the Auto FAC leaves the group with a first line of 10,000,000 and
 * a Fac Placement above it, so the attach point has to come down from 12,000,000 to
 * 10,000,000 or a 2,000,000 band would sit in neither layer and surface as a phantom
 * Outside Treaty figure. Attach = first line + surplus + auto fac, the same rule
 * fix_fac_attach_points_2627.php applies, and MISC_COM has no surplus.
 *
 * Before, on a 15,000,000 risk : NR 3,000,000  QS 7,000,000  AutoFAC 2,000,000  FAC 3,000,000
 * After                        : NR 3,000,000  QS 7,000,000  AutoFAC -          FAC 5,000,000
 *
 * Both reconcile to the sum insured. The change is which column reports the 2,000,000, and
 * whether Graphite claims an automatic facility that no slip grants.
 *
 * ONE SIDE EFFECT, AND IT IS THE CORRECT BEHAVIOUR. facultativeCapacityFor() reads Band 3
 * from the Auto FAC row, so once that row is gone it returns 0.0 and the Fac Placement
 * layer is uncapped for this group. That is deliberate: with no facility documented there
 * is no capacity to cap against, and inventing one would repeat the mistake being removed
 * here. Groups that do carry a documented band keep their cap.
 *
 * NO POLICY IS AFFECTED TODAY. MISC_COM appears on 18 policies and the largest
 * Miscellaneous sum insured across all of them is under the 10,000,000 first line, so
 * nothing currently reaches either layer. Checked with a numeric cast, because
 * policy_reinsurance.totalSumInsured is varchar(255) and MAX() on it compares as text -
 * "870" sorts above "1,570,000".
 *
 * The 2024/25 rows are left alone. Formula 22 belongs to MUNICH_COM_2024_2025, which
 * expired on 30 June, and is part of the historical record.
 *
 * Matches on formula_code, not id, and verifies the layer's type and value before deleting
 * anything, so it cannot remove the wrong row on another database.
 *
 * Credentials from the ENVIRONMENT, as the sibling scripts:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/remove_misc_com_autofac_2627.php        # dry run
 *   php backend/database/manual/remove_misc_com_autofac_2627.php APPLY  # execute
 */

const AUTOFAC_CODE   = 'MISCELLANEOUSCLASSES-COM-AUTOFAC-26-27';
const FACPLACE_CODE  = 'MISCELLANEOUSCLASSES-COM-FACULATIVEPLACEMENT-26-27';
const EXPECT_AUTOFAC = 2000000.0;
const NEW_ATTACH     = 10000000.0;   // first line + surplus(0) + auto fac(0)

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

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user, $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo 'mode     : ' . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

/** One row per layer, or we are not looking at what this script was written for. */
$layer = function (string $code) use ($p) {
    $q = $p->prepare('SELECT f.id AS formula_id, f.formula_code, f.s_FormulaType,
                             fd.id AS detail_id, fd.group_id, g.group_code,
                             fd.si_allocation, fd.operator
                        FROM reinsurance_formula f
                        JOIN reinsurance_formula_details fd ON fd.formula_id = f.id
                        LEFT JOIN reinsurance_group g ON g.id = fd.group_id
                       WHERE f.formula_code = ?');
    $q->execute([$code]);

    return $q->fetchAll(PDO::FETCH_ASSOC);
};

$autoFac  = $layer(AUTOFAC_CODE);
$facPlace = $layer(FACPLACE_CODE);

if (!$autoFac) {
    if ($facPlace && abs($money($facPlace[0]['si_allocation']) - NEW_ATTACH) < 0.005) {
        echo "Already done — no " . AUTOFAC_CODE . ", and the attach is already "
            . $asText(NEW_ATTACH) . ".\n";
        exit;
    }
    fwrite(STDERR, 'REFUSING: no formula with code ' . AUTOFAC_CODE . ". Nothing changed.\n");
    exit(1);
}
if (count($autoFac) !== 1 || count($facPlace) !== 1) {
    fwrite(STDERR, 'REFUSING: expected exactly one detail row per layer, found '
        . count($autoFac) . ' and ' . count($facPlace) . ". Resolve by hand.\n");
    exit(1);
}

$af = $autoFac[0];
$fp = $facPlace[0];

if (abs($money($af['si_allocation']) - EXPECT_AUTOFAC) > 0.005) {
    fwrite(STDERR, 'REFUSING: Auto FAC holds ' . $af['si_allocation'] . ', expected '
        . $asText(EXPECT_AUTOFAC) . ". It has changed since this was written.\n");
    exit(1);
}
if ($af['group_code'] !== 'MISC_COM' || $fp['group_code'] !== 'MISC_COM') {
    fwrite(STDERR, "REFUSING: one of the layers is not on MISC_COM.\n");
    exit(1);
}

$q = $p->prepare('SELECT td.id, t.treaty_name FROM reinsurance_treaty_details td
                    LEFT JOIN reinsurance_treaty t ON t.id = td.treaty_id
                   WHERE td.formula_attached = ?');
$q->execute([$af['formula_id']]);
$att = $q->fetchAll(PDO::FETCH_ASSOC);

echo "TO DELETE — the Auto FAC layer\n";
printf("  formula %-4s %s\n", $af['formula_id'], $af['formula_code']);
printf("  detail  %-4s group %s (%s)  type %s  si_allocation %s  operator %s\n",
    $af['detail_id'], $af['group_id'], $af['group_code'], $af['s_FormulaType'],
    $af['si_allocation'], $af['operator']);
foreach ($att as $a) {
    printf("  treaty_details %-4s %s\n", $a['id'], $a['treaty_name']);
}

echo "\nTO RE-POINT — the Fac Placement attach\n";
printf("  detail  %-4s %s\n", $fp['detail_id'], $fp['formula_code']);
printf("  si_allocation %s  ->  %s\n\n", $fp['si_allocation'], $asText(NEW_ATTACH));

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$p->beginTransaction();
try {
    $rollback = [];

    foreach ($att as $a) {
        $rollback[] = "INSERT INTO reinsurance_treaty_details (id, treaty_id, formula_attached) "
            . "SELECT {$a['id']}, id, {$af['formula_id']} FROM reinsurance_treaty WHERE treaty_name = "
            . $p->quote($a['treaty_name']) . ';';
    }
    $rollback[] = "INSERT INTO reinsurance_formula (id, formula_code, formula_name, s_FormulaType) VALUES "
        . "({$af['formula_id']}, " . $p->quote($af['formula_code']) . ', '
        . $p->quote($af['formula_code']) . ', ' . $p->quote($af['s_FormulaType']) . ');';
    $rollback[] = "INSERT INTO reinsurance_formula_details (id, formula_id, group_id, si_allocation, operator) VALUES "
        . "({$af['detail_id']}, {$af['formula_id']}, {$af['group_id']}, "
        . $p->quote($af['si_allocation']) . ", {$af['operator']});";
    $rollback[] = "UPDATE reinsurance_formula_details SET si_allocation = "
        . $p->quote($fp['si_allocation']) . " WHERE id = {$fp['detail_id']};";

    $d = $p->prepare('DELETE FROM reinsurance_treaty_details WHERE formula_attached = ?');
    $d->execute([$af['formula_id']]);
    $tdRows = $d->rowCount();

    $d = $p->prepare('DELETE FROM reinsurance_formula_details WHERE formula_id = ?');
    $d->execute([$af['formula_id']]);
    $fdRows = $d->rowCount();

    $d = $p->prepare('DELETE FROM reinsurance_formula WHERE id = ? AND formula_code = ?');
    $d->execute([$af['formula_id'], AUTOFAC_CODE]);
    if ($d->rowCount() !== 1) {
        throw new RuntimeException('expected to delete 1 formula row, deleted ' . $d->rowCount());
    }

    $u = $p->prepare('UPDATE reinsurance_formula_details SET si_allocation = ? WHERE id = ?');
    $u->execute([$asText(NEW_ATTACH), $fp['detail_id']]);
    if ($u->rowCount() !== 1) {
        throw new RuntimeException('expected to re-point 1 attach row, updated ' . $u->rowCount());
    }

    $p->commit();

    printf("deleted   : 1 formula, %d detail row(s), %d treaty attachment(s)\n", $fdRows, $tdRows);
    printf("re-pointed: Fac Placement attach to %s\n\nCOMMITTED.\n\n", $asText(NEW_ATTACH));

    echo "ROLLBACK BLOCK — keep this:\n";
    foreach ($rollback as $sql) {
        echo "  $sql\n";
    }
    echo "\nNo recalculation needed: no Miscellaneous risk on any of the 18 policies reaches\n";
    echo "the 10,000,000 first line, so neither layer was contributing anything to rate.\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
