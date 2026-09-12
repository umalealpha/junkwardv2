<?php
/**
 * Creates the GROUPPERSONALACCIDENT_DOM reinsurance group, mirroring its commercial twin,
 * and moves formula 122 onto it.
 *
 * WHY IT IS NEEDED. The engine's product map already expects this group. At
 * PolicyCoverage.php:2552, ExcessOfLossCoverageReinsurance1() resolves product 8 with
 * coverage 14 to the literal code 'GROUPPERSONALACCIDENT_DOM' and then filters
 * "AND gm.group_code = '<that code>'". No such group exists, so every domestic personal
 * accident policy resolves to a code with nothing behind it and produces no cession row at
 * all. Only GROUPPERSONALACCIDENT_COM (id 9) was ever created.
 *
 * NO YEAR IN THE CODE, DELIBERATELY - the request said GROUPPERSONALACCIDENT_DOM_26-27.
 * Group codes in this schema are not year-scoped: none of the 30 existing codes carries a
 * year, the treaty year lives on reinsurance_treaty, and formulas are scoped to a year by
 * which treaty they attach to. More concretely, the map above compares against the
 * unsuffixed literal, so a group coded GROUPPERSONALACCIDENT_DOM_26-27 would never match
 * and the new group would be as invisible as no group at all. The 26/27 scoping comes from
 * the treaty attachment on the formula, which is where every other year-specific thing in
 * this configuration lives. Renaming later is a one-line UPDATE if a suffix is really
 * wanted.
 *
 * WHAT "SAME AS" MEANS HERE. GROUPPERSONALACCIDENT_DOM did not exist to copy, so the twin
 * copied is GROUPPERSONALACCIDENT_COM (id 9). The WC_COM / WC_DOM pair sets the precedent:
 * twins differ only in product_id (7 commercial, 8 domestic) and the code suffix, and they
 * carry byte-identical coverage_id lists - both WC groups hold exactly
 * 73,110,111,112,113,114,115. So all 22 coverage rows are copied verbatim, values and
 * regulatory_mapping included.
 *
 * FORMULA 122 IS MOVED, NOT COPIED. It currently sits on group 22, and that placement was
 * never right: see the finding below. It is unattached to any treaty, and both engine paths
 * filter on tm.effective_from <= today <= tm.effective_to, so moving it changes no
 * computed figure on any policy today. It simply stops being a Public Liability row.
 *
 * FINDING, NOT FIXED HERE. Group 22 is mis-coded and this is the root of the whole
 * confusion. Its code says PUBLICLIABANDDEFECTIVEWORKMAN_DOM but its coverages_id is 14,
 * and coverage 14 is 'Personal Accident' (tb_cvgpccoverages) - Public Liability is 21.
 * Group 21, the commercial twin, correctly carries 21. So group 22 has been the domestic
 * personal accident group all along, under a public liability name, which is exactly why
 * formulas 29 and 122 were named PERSONALACCIDENT-DOM while living on it. The engine's map
 * is right and the group's code is wrong.
 *
 * That is left alone here on purpose. Renaming group 22 would leave domestic public
 * liability with no group at all, and choosing between renaming it and creating a separate
 * public liability group is a configuration decision. Group 22 has produced zero rows on
 * zero policies, so nothing is at risk either way.
 *
 * ALSO WORTH A LOOK. Group 9's own regulatory_mapping column reads 'Accident' while its 22
 * coverage rows read 'Liability'. Personal Accident should presumably be Accident in both,
 * as Workers Compensation is. Copied verbatim rather than corrected, so the twins stay
 * consistent and the discrepancy stays visible in one place instead of two.
 *
 * NO TREATY ATTACHMENT IS ADDED. Capacities Notes 2, 3 and 4 all say the same thing:
 * personal accident is in neither Schedule A, neither treaty establishes an excess of loss
 * programme, and neither states any AutoFAC capacity. Until an excess of loss programme
 * document exists, the formula stays unattached and cannot fire - which is the safe state.
 *
 * Credentials from the ENVIRONMENT, as the sibling scripts:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/create_gpa_dom_group.php        # dry run
 *   php backend/database/manual/create_gpa_dom_group.php APPLY  # execute
 */

const SRC_GROUP_CODE = 'GROUPPERSONALACCIDENT_COM';
const NEW_GROUP_CODE = 'GROUPPERSONALACCIDENT_DOM';
const NEW_PRODUCT_ID = 8;      // domestic, per the WC_COM(7) / WC_DOM(8) precedent
const MOVE_FORMULA   = 122;    // GROUPPERSONALACCIDENT_DOM-26-27
const MOVE_FROM_GROUP = 22;    // PUBLICLIABANDDEFECTIVEWORKMAN_DOM

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

/** Idempotent: if the group is already there, say so and stop. */
$q = $p->prepare('SELECT id FROM reinsurance_group WHERE group_code = ?');
$q->execute([NEW_GROUP_CODE]);
if ($existing = $q->fetchColumn()) {
    echo NEW_GROUP_CODE . " already exists as group {$existing}. Nothing to do.\n";
    exit;
}

$q = $p->prepare('SELECT * FROM reinsurance_group WHERE group_code = ?');
$q->execute([SRC_GROUP_CODE]);
$src = $q->fetch(PDO::FETCH_ASSOC);
if (!$src) {
    fwrite(STDERR, 'REFUSING: source group ' . SRC_GROUP_CODE . " not found.\n");
    exit(1);
}

$q = $p->prepare('SELECT coverage_id, coverage_name, si_premium, ri_limit, limit_value, regulatory_mapping
                    FROM reinsurance_group_coverage WHERE group_id = ? ORDER BY coverage_id');
$q->execute([$src['id']]);
$coverages = $q->fetchAll(PDO::FETCH_ASSOC);

echo "SOURCE GROUP\n";
printf("  id %s  %s  product %s  mapping %s  coverages_id %s  (%d coverage rows)\n\n",
    $src['id'], $src['group_code'], $src['product_id'],
    $src['regulatory_mapping'] ?? 'NULL', $src['coverages_id'] ?? 'NULL', count($coverages));

echo "GROUP TO CREATE\n";
printf("  code %s  product %s  status 1  mapping %s  coverages_id %s\n",
    NEW_GROUP_CODE, NEW_PRODUCT_ID, $src['regulatory_mapping'] ?? 'NULL', $src['coverages_id'] ?? 'NULL');
printf("  + %d coverage rows copied verbatim from %s\n\n", count($coverages), SRC_GROUP_CODE);

/** The formula being moved, checked to be exactly where expected. */
$q = $p->prepare('SELECT fd.id, fd.group_id, g.group_code, fd.si_allocation, fd.operator, f.formula_code
                    FROM reinsurance_formula_details fd
                    JOIN reinsurance_formula f ON f.id = fd.formula_id
                    LEFT JOIN reinsurance_group g ON g.id = fd.group_id
                   WHERE fd.formula_id = ?');
$q->execute([MOVE_FORMULA]);
$details = $q->fetchAll(PDO::FETCH_ASSOC);

if (!$details) {
    fwrite(STDERR, 'REFUSING: formula ' . MOVE_FORMULA . " has no detail rows.\n");
    exit(1);
}

echo "FORMULA TO MOVE\n";
foreach ($details as $d) {
    printf("  detail %s  %s  si %s  operator %s\n", $d['id'], $d['formula_code'],
        $d['si_allocation'], $d['operator']);
    printf("    group %s (%s)  ->  %s\n", $d['group_id'], $d['group_code'], NEW_GROUP_CODE);
    if ((int) $d['group_id'] !== MOVE_FROM_GROUP) {
        fwrite(STDERR, 'REFUSING: detail ' . $d['id'] . ' is on group ' . $d['group_id']
            . ', expected ' . MOVE_FROM_GROUP . ". It has moved since this was written.\n");
        exit(1);
    }
}

$q = $p->prepare('SELECT COUNT(*) FROM reinsurance_treaty_details WHERE formula_attached = ?');
$q->execute([MOVE_FORMULA]);
printf("\n  treaty attachment : %s\n\n", $q->fetchColumn() ? 'ATTACHED' : 'none — cannot fire, unchanged by this script');

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$p->beginTransaction();
try {
    $now = date('Y-m-d H:i:s');

    $ins = $p->prepare('INSERT INTO reinsurance_group
        (group_name, group_code, product_id, status, regulatory_mapping, coverages_id, created_at, updated_at)
        VALUES (?, ?, ?, 1, ?, ?, ?, ?)');
    $ins->execute([
        NEW_GROUP_CODE, NEW_GROUP_CODE, NEW_PRODUCT_ID,
        $src['regulatory_mapping'], $src['coverages_id'], $now, $now,
    ]);
    $newId = (int) $p->lastInsertId();

    $insCvg = $p->prepare('INSERT INTO reinsurance_group_coverage
        (group_id, coverage_id, coverage_name, si_premium, ri_limit, limit_value, regulatory_mapping, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($coverages as $c) {
        $insCvg->execute([
            $newId, $c['coverage_id'], $c['coverage_name'], $c['si_premium'],
            $c['ri_limit'], $c['limit_value'], $c['regulatory_mapping'], $now, $now,
        ]);
    }

    $upd = $p->prepare('UPDATE reinsurance_formula_details SET group_id = ?
                         WHERE formula_id = ? AND group_id = ?');
    $upd->execute([$newId, MOVE_FORMULA, MOVE_FROM_GROUP]);
    $moved = $upd->rowCount();

    if ($moved !== count($details)) {
        throw new RuntimeException('expected to move ' . count($details) . " detail rows, moved {$moved}");
    }

    $p->commit();

    printf("created : group %d (%s) with %d coverage rows\n", $newId, NEW_GROUP_CODE, count($coverages));
    printf("moved   : %d formula detail row(s) from group %d to %d\n\nCOMMITTED.\n\n", $moved, MOVE_FROM_GROUP, $newId);

    echo "ROLLBACK BLOCK — keep this:\n";
    echo '  UPDATE reinsurance_formula_details SET group_id = ' . MOVE_FROM_GROUP
        . ' WHERE formula_id = ' . MOVE_FORMULA . " AND group_id = {$newId};\n";
    echo "  DELETE FROM reinsurance_group_coverage WHERE group_id = {$newId};\n";
    echo "  DELETE FROM reinsurance_group WHERE id = {$newId};\n\n";

    echo "NO RECALCULATION NEEDED. The formula has no treaty attachment, and both engine\n";
    echo "paths filter on an in-force treaty, so nothing already rated changes. Attaching it\n";
    echo "waits on an excess of loss programme document — see Capacities Notes 2, 3 and 4.\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
