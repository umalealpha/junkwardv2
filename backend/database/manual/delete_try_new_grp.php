<?php
/**
 * Removes the try_new_grp reinsurance group and its three coverage rows.
 *
 * WHAT IT IS. Group id 31, code and name both 'try_new_grp', created 2026-08-03 09:39:02
 * against product 7 while the 2026/27 configuration was being worked through. Test data,
 * confirmed as such by Snehal on 27 August.
 *
 * WHY IT IS SAFE. Every table that carries a group_id was counted before writing this:
 *
 *     reinsurance_group_coverage        3   <- deleted
 *     reinsurance_formula_details       0
 *     policy_reinsurance               0
 *     policy_reinsurance_details       0
 *     tb_porifacdetails                0
 *     fac_placements                   0
 *     mis_premium_data                 0
 *     activation                       1   <- NOT a reference, see below
 *
 * With no formula rows the group can never cede, and with no policy_reinsurance_details
 * rows it has never been applied to a policy in the eight months since the table was
 * populated. Its three coverages - EMPLOYERS LIABLITY (COMMO), ALL STAFF: EMPLOYEES and
 * FREE TEXT - carry no regulatory_mapping, so the group is invisible to the returns too.
 *
 * THE activation HIT IS A COINCIDENCE, NOT A FOREIGN KEY. activation is the device
 * activation-code table - vendor, serial_code, activation_code, rack_no - holding 457,225
 * rows across 153,877 distinct group_id values, and its group_id belongs to an entirely
 * different domain. The row that matches (id 10029, serial AAAOVR) was created
 * 2020-05-20, six years before this group existed. It is left alone, and deleting the
 * reinsurance group cannot affect it: there is no constraint between the two tables.
 *
 * SCOPE. Deletes only group 31 and the coverage rows pointing at it. Deliberately does NOT
 * touch:
 *   - TRAVELINSURANCE_DOM, the other empty group (0 coverages, 0 formulas, 0 policies).
 *     Empty is not the same as fabricated, and nobody has said it is test data.
 *   - MISCANDFG_COM, which is original December 2024 configuration rather than test data,
 *     and whose naming matches Capacities Schedule A row 12 better than MISC_COM's does.
 *   - reinsurance_group_coverage_bk_20260826, the dedupe backup, which keeps its own copy
 *     of the three rows should they ever be wanted back.
 *
 * Matches on the CODE, not the id, and refuses to run if the code is not 'try_new_grp' -
 * so it cannot delete the wrong group if ids have shifted between databases.
 *
 * Credentials from the ENVIRONMENT, as the sibling scripts:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/delete_try_new_grp.php        # dry run
 *   php backend/database/manual/delete_try_new_grp.php APPLY  # execute
 */

const GROUP_CODE = 'try_new_grp';

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

$stmt = $p->prepare('SELECT id, group_code, group_name, product_id, created_at
                       FROM reinsurance_group WHERE group_code = ?');
$stmt->execute([GROUP_CODE]);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$groups) {
    echo "No group with code '" . GROUP_CODE . "'. Nothing to do — it may already be gone.\n";
    exit;
}
if (count($groups) > 1) {
    fwrite(STDERR, 'REFUSING: ' . count($groups) . " groups carry that code. Resolve by hand.\n");
    exit(1);
}

$group = $groups[0];
$groupId = (int) $group['id'];

echo "GROUP TO DELETE\n";
printf("  id %d  code %s  name %s  product %s  created %s\n\n",
    $groupId, $group['group_code'], $group['group_name'],
    $group['product_id'], $group['created_at']);

/**
 * Re-counted at run time rather than trusted from the analysis above, because the database
 * may have moved on since. Any non-zero count outside the coverage table aborts.
 */
$refs = [
    'reinsurance_group_coverage'  => 'SELECT COUNT(*) FROM reinsurance_group_coverage WHERE group_id = ?',
    'reinsurance_formula_details' => 'SELECT COUNT(*) FROM reinsurance_formula_details WHERE group_id = ?',
    'policy_reinsurance'          => 'SELECT COUNT(*) FROM policy_reinsurance WHERE group_id = ?',
    'policy_reinsurance_details'  => 'SELECT COUNT(*) FROM policy_reinsurance_details WHERE group_id = ?',
    'tb_porifacdetails'           => 'SELECT COUNT(*) FROM tb_porifacdetails WHERE group_id = ?',
    'fac_placements'              => 'SELECT COUNT(*) FROM fac_placements WHERE reinsurance_group_id = ?',
    'mis_premium_data'            => 'SELECT COUNT(*) FROM mis_premium_data WHERE group_id = ?',
];

echo "REFERENCES\n";
$blocking = [];
foreach ($refs as $table => $sql) {
    $q = $p->prepare($sql);
    $q->execute([$groupId]);
    $n = (int) $q->fetchColumn();

    $ok = $table === 'reinsurance_group_coverage' || $n === 0;
    printf("  %-30s %5d %s\n", $table, $n, $ok ? '' : '  <-- BLOCKS DELETION');
    if (!$ok) {
        $blocking[$table] = $n;
    }
}
echo "  (activation.group_id is a different domain entirely and is not checked — see header)\n\n";

if ($blocking) {
    fwrite(STDERR, "REFUSING: the group is referenced by " . implode(', ', array_keys($blocking))
        . ". It is in use, not test data. Nothing changed.\n");
    exit(1);
}

$q = $p->prepare('SELECT id, coverage_id, coverage_name, regulatory_mapping
                    FROM reinsurance_group_coverage WHERE group_id = ? ORDER BY id');
$q->execute([$groupId]);
$coverages = $q->fetchAll(PDO::FETCH_ASSOC);

echo "COVERAGE ROWS TO DELETE\n";
foreach ($coverages as $c) {
    printf("  id %-6s coverage %-6s %-28s mapping %s\n",
        $c['id'], $c['coverage_id'], $c['coverage_name'], $c['regulatory_mapping'] ?? 'NULL');
}
echo "\n";

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$p->beginTransaction();
try {
    $rollback = ["INSERT INTO reinsurance_group (id, group_name, group_code, product_id, status, created_at, updated_at) VALUES ({$groupId}, "
        . $p->quote($group['group_name']) . ', ' . $p->quote($group['group_code']) . ", {$group['product_id']}, 1, "
        . $p->quote($group['created_at']) . ', ' . $p->quote($group['created_at']) . ');'];

    foreach ($coverages as $c) {
        $rollback[] = "INSERT INTO reinsurance_group_coverage (id, group_id, coverage_id, coverage_name) VALUES "
            . "({$c['id']}, {$groupId}, {$c['coverage_id']}, " . $p->quote($c['coverage_name']) . ');';
    }

    $del = $p->prepare('DELETE FROM reinsurance_group_coverage WHERE group_id = ?');
    $del->execute([$groupId]);
    $coverageRows = $del->rowCount();

    $del = $p->prepare('DELETE FROM reinsurance_group WHERE id = ? AND group_code = ?');
    $del->execute([$groupId, GROUP_CODE]);
    $groupRows = $del->rowCount();

    if ($groupRows !== 1) {
        throw new RuntimeException("expected to delete 1 group row, deleted {$groupRows}");
    }

    $p->commit();

    echo "deleted : {$groupRows} group row, {$coverageRows} coverage rows\n\nCOMMITTED.\n\n";
    echo "ROLLBACK BLOCK — keep this:\n";
    foreach ($rollback as $sql) {
        echo "  $sql\n";
    }
    echo "\nNo recalculation needed: the group had no formulas and no policy rows, so nothing\n";
    echo "that has already been rated changes.\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
