<?php
/**
 * Renames formula 122 from PERSONALACCIDENT-DOM-AUTOFAC-26-27 to
 * GROUPPERSONALACCIDENT_DOM-26-27.
 *
 * WHY. The formula was created on 2026-08-27 as a copy of the 2024/25 row (id 29) and
 * inherited its name. Both sit on group 22, PUBLICLIABANDDEFECTIVEWORKMAN_DOM, so the
 * label named one class while the row served another. Snehal asked for the group code in
 * the name so it is unambiguous which group it is meant for.
 *
 * WHAT THIS DOES NOT DO, AND IT MATTERS. formula_code is a LABEL. The cession engine
 * dispatches on reinsurance_formula_details.group_id and s_FormulaType, never on the code
 * string - see getReinsuranceCoverageCalculations() and ExcessOfLossCoverageReinsurance1(),
 * both of which match on gm.group_code from the group table. So after this rename the row
 * is still a PUBLICLIABANDDEFECTIVEWORKMAN_DOM formula. The name will say Group Personal
 * Accident; the behaviour will not be.
 *
 * Repointing it properly needs group_id moved to a GROUPPERSONALACCIDENT_DOM group, and
 * that group DOES NOT EXIST - only GROUPPERSONALACCIDENT_COM (id 9). The engine's product
 * map at PolicyCoverage.php:2552 already points product 8 at the code
 * 'GROUPPERSONALACCIDENT_DOM', so every domestic group-personal-accident policy resolves
 * to a group code with no row behind it and produces nothing. Creating that group, with
 * its coverages, is the actual fix; this rename is the label half of it.
 *
 * The '26-27' suffix is preserved, so fix_fac_attach_points_2627.php's
 * "WHERE formula_code LIKE '%26-27'" selector still picks the row up.
 *
 * Nothing in the application references either code string - checked across app/ - so the
 * rename is safe on its own. No treaty attachment is added or removed here: the row stays
 * unattached, and while it is unattached it cannot fire, because both engine paths filter
 * on tm.effective_from <= today <= tm.effective_to.
 *
 * Matches on the CURRENT code, not the id, and refuses if the row is not exactly where
 * expected - so it cannot rename the wrong formula on another database.
 *
 * Credentials from the ENVIRONMENT, as the sibling scripts:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/rename_gpa_dom_autofac_formula.php        # dry run
 *   php backend/database/manual/rename_gpa_dom_autofac_formula.php APPLY  # execute
 */

const OLD_CODE      = 'PERSONALACCIDENT-DOM-AUTOFAC-26-27';
const NEW_CODE      = 'GROUPPERSONALACCIDENT_DOM-26-27';
const EXPECT_GROUP  = 22;   // PUBLICLIABANDDEFECTIVEWORKMAN_DOM, where the row actually is

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

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user, $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo 'mode     : ' . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

$q = $p->prepare('SELECT id, formula_code, formula_name, s_FormulaType, reinsurance_type_id, type_id
                    FROM reinsurance_formula WHERE formula_code = ?');
$q->execute([OLD_CODE]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    $q = $p->prepare('SELECT id FROM reinsurance_formula WHERE formula_code = ?');
    $q->execute([NEW_CODE]);
    if ($q->fetchColumn()) {
        echo 'Already renamed — ' . NEW_CODE . " exists. Nothing to do.\n";
        exit;
    }
    fwrite(STDERR, 'REFUSING: no formula with code ' . OLD_CODE . ". Nothing changed.\n");
    exit(1);
}
if (count($rows) > 1) {
    fwrite(STDERR, 'REFUSING: ' . count($rows) . " formulas carry that code. Resolve by hand.\n");
    exit(1);
}

$f = $rows[0];

$q = $p->prepare('SELECT id FROM reinsurance_formula WHERE formula_code = ? AND id <> ?');
$q->execute([NEW_CODE, $f['id']]);
if ($fid = $q->fetchColumn()) {
    fwrite(STDERR, 'REFUSING: formula ' . $fid . ' already uses ' . NEW_CODE . ". Nothing changed.\n");
    exit(1);
}

$q = $p->prepare('SELECT fd.id, fd.group_id, g.group_code, fd.operator, fd.si_allocation
                    FROM reinsurance_formula_details fd
                    LEFT JOIN reinsurance_group g ON g.id = fd.group_id
                   WHERE fd.formula_id = ?');
$q->execute([$f['id']]);
$details = $q->fetchAll(PDO::FETCH_ASSOC);

echo "FORMULA\n";
printf("  id %s  type %s  reinsurance_type %s / %s\n",
    $f['id'], $f['s_FormulaType'], $f['reinsurance_type_id'], $f['type_id']);
printf("  code  %s\n     -> %s\n", $f['formula_code'], NEW_CODE);
printf("  name  %s\n     -> %s\n\n", $f['formula_name'], NEW_CODE);

echo "DETAIL ROWS — group_id is what the engine dispatches on, and this rename does NOT touch it\n";
foreach ($details as $d) {
    printf("  detail %-5s group %-4s %-38s operator %-3s si_allocation %s\n",
        $d['id'], $d['group_id'], $d['group_code'] ?? 'MISSING GROUP',
        $d['operator'], $d['si_allocation']);
    if ((int) $d['group_id'] !== EXPECT_GROUP) {
        fwrite(STDERR, 'REFUSING: detail ' . $d['id'] . ' is on group ' . $d['group_id']
            . ', expected ' . EXPECT_GROUP . ". The row has moved since this was written.\n");
        exit(1);
    }
}

$q = $p->prepare('SELECT td.id, t.treaty_name FROM reinsurance_treaty_details td
                    LEFT JOIN reinsurance_treaty t ON t.id = td.treaty_id
                   WHERE td.formula_attached = ?');
$q->execute([$f['id']]);
$att = $q->fetchAll(PDO::FETCH_ASSOC);
echo "\nTREATY ATTACHMENT : " . ($att
    ? implode(', ', array_column($att, 'treaty_name'))
    : 'none — unattached, so it cannot fire') . "\n\n";

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$p->beginTransaction();
try {
    $upd = $p->prepare('UPDATE reinsurance_formula SET formula_code = ?, formula_name = ?, updated_at = NOW()
                         WHERE id = ? AND formula_code = ?');
    $upd->execute([NEW_CODE, NEW_CODE, $f['id'], OLD_CODE]);

    if ($upd->rowCount() !== 1) {
        throw new RuntimeException('expected to update 1 row, updated ' . $upd->rowCount());
    }

    $p->commit();

    echo "renamed : formula {$f['id']}\n\nCOMMITTED.\n\n";
    echo "ROLLBACK — keep this:\n";
    echo "  UPDATE reinsurance_formula SET formula_code = '" . OLD_CODE . "', formula_name = '"
        . $f['formula_name'] . "' WHERE id = {$f['id']};\n\n";
    echo "STILL OUTSTANDING: the row remains on group " . EXPECT_GROUP
        . " (PUBLICLIABANDDEFECTIVEWORKMAN_DOM),\n";
    echo "so it is a Public Liability formula with a Group Personal Accident name. Creating the\n";
    echo "GROUPPERSONALACCIDENT_DOM group and moving group_id onto it is the rest of the job.\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
