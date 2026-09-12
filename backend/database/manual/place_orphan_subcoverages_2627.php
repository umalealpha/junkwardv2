<?php
/**
 * Puts sub-coverages that reach no reinsurance group into the groups their
 * SIBLINGS already occupy.
 *
 * WHY IT IS NEEDED. From 2026/27 the treaty routes on the regulatory mapping,
 * and the engine reaches that mapping through the group. A sub-coverage whose
 * code appears in no reinsurance_group_coverage row therefore has no class at
 * all — it does not report as retained, it does not report as ceded, it does not
 * report. That is worse than a wrong class, because a wrong class is visible.
 *
 * On COMG2026213751 alone, seven codes carrying 15,120,000 of sum insured were
 * invisible this way, the largest being REINSTAMENT OF DATA at 12,000,000.
 *
 * THE RULE, AND WHY IT IS NOT A GUESS. reinsurance_group_coverage holds
 * SUB-coverages, and the regulatory class is a property of the GROUP. A
 * sub-coverage therefore belongs wherever the other sub-coverages of its own
 * parent belong: REINSTAMENT OF DATA sits under ELECTRONICEQUIPMENT, whose other
 * children are already in ELECTRONIC_EQ_AND_BI_COM and ELECTRONICEQANDBI_DOM, so
 * it joins both and takes each group's own mapping. Nothing here invents a class;
 * every placement is read from where its siblings already are.
 *
 * BOTH TWINS, DELIBERATELY. Sub-coverages sit in the commercial and domestic
 * groups alike — WC_COM and WC_DOM carry byte-identical coverage lists — so an
 * orphan is placed in every group its siblings occupy rather than in one of them.
 * Product scoping at read time is what keeps a domestic group off a commercial
 * policy, not the absence of the row.
 *
 * DERIVED FROM LIVE POLICY DATA, not from one policy. The parent-to-child
 * relationship is read across every policy in the system, so a code that is
 * orphaned on one policy and grouped on another is correctly left alone.
 *
 * WHAT IT WILL NOT DO. A code whose siblings span groups carrying DIFFERENT
 * regulatory classes is reported and skipped: placing it would file a sum insured
 * under a class nobody chose. So is a code with no grouped siblings at all, since
 * there is then nothing to read the class from.
 *
 * ADDITIVE AND IDEMPOTENT. A code already in a group is skipped, never
 * duplicated. Re-running after a first APPLY reports nothing to do.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction. A rollback
 * block is printed on every APPLY.
 *
 * Credentials from the ENVIRONMENT, as treaty_year_config.php — never .env, which
 * has pointed at production before now:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/place_orphan_subcoverages_2627.php        # dry run
 *   php backend/database/manual/place_orphan_subcoverages_2627.php APPLY  # execute
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

$apply = ($argv[1] ?? '') === 'APPLY';

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user,
    $pass,
    [PDO::ATTR_TIMEOUT => 60, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo 'mode     : ' . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

/*
 * Every sub-coverage in use that reaches no group, with the groups its siblings
 * occupy.
 *
 * "In use" means it appears on a policy coverage detail row. A code defined in
 * the master but never written on a policy is left alone: it has no exposure and
 * placing it would be configuring for a case nobody has.
 */
$orphans = $p->query("
    SELECT
        tc.s_CoverageCode                                    AS code,
        MAX(parent.s_CoverageCode)                           AS parent_code,
        COUNT(DISTINCT cvgsm.id)                             AS detail_rows,
        SUM(COALESCE(cvgsm.coverage_value, 0))               AS si,
        GROUP_CONCAT(DISTINCT sib.group_id)                  AS sibling_group_ids,
        GROUP_CONCAT(DISTINCT sib.group_code)                AS sibling_groups,
        COUNT(DISTINCT sib.regulatory_mapping)               AS sibling_classes,
        GROUP_CONCAT(DISTINCT sib.regulatory_mapping)        AS classes
    FROM policy_coverages cvgm
    JOIN policy_coverage_detail cvgsm  ON cvgsm.policy_coverage_id = cvgm.id
    JOIN tb_cvgpccoverages tc          ON tc.id = cvgsm.coverage_id
    LEFT JOIN tb_cvgpccoverages parent ON parent.id = cvgm.coverage_id
    -- Is this code itself in any group?
    LEFT JOIN reinsurance_group_coverage own ON own.coverage_name = tc.s_CoverageCode
    -- Where do the OTHER children of the same parent sit?
    LEFT JOIN (
        SELECT DISTINCT
               d.policy_coverage_id,
               g.id           AS group_id,
               g.group_code   AS group_code,
               gc.regulatory_mapping
        FROM policy_coverage_detail d
        JOIN tb_cvgpccoverages t            ON t.id = d.coverage_id
        JOIN reinsurance_group_coverage gc  ON gc.coverage_name = t.s_CoverageCode
        JOIN reinsurance_group g            ON g.id = gc.group_id
        WHERE d.deleted_at IS NULL
    ) sib ON sib.policy_coverage_id = cvgm.id
    WHERE cvgm.deleted_at IS NULL
      AND cvgsm.deleted_at IS NULL
      AND own.id IS NULL
    GROUP BY tc.s_CoverageCode
    HAVING sibling_group_ids IS NOT NULL
    ORDER BY si DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (! $orphans) {
    echo "No orphaned sub-coverages with grouped siblings. Nothing to do.\n";
    exit(0);
}

$line = str_repeat('-', 112);
echo $line . PHP_EOL;
printf("%-28s %-22s %14s %-28s %s\n", 'CODE', 'SITS UNDER', 'SUM INSURED', 'JOINS', 'CLASS');
echo $line . PHP_EOL;

$plan    = [];
$skipped = [];

foreach ($orphans as $o) {
    // Siblings disagreeing on the class is a STOP for that code. Placing it would
    // file its sum insured under a class nobody chose.
    if ((int) $o['sibling_classes'] > 1) {
        $skipped[] = $o['code'] . ' — siblings span ' . $o['classes'];
        printf("%-28s %-22s %14s %-28s %s\n",
            $o['code'], $o['parent_code'] ?? '?', number_format((float) $o['si'], 2),
            'SKIPPED', $o['classes']);
        continue;
    }

    foreach (array_filter(explode(',', (string) $o['sibling_group_ids'])) as $gid) {
        $plan[] = ['code' => $o['code'], 'group_id' => (int) $gid, 'mapping' => $o['classes']];
    }

    printf("%-28s %-22s %14s %-28s %s\n",
        $o['code'], $o['parent_code'] ?? '?', number_format((float) $o['si'], 2),
        substr((string) $o['sibling_groups'], 0, 28), $o['classes']);
}

echo $line . PHP_EOL;
printf("\n%d row(s) to insert across %d coverage code(s).\n", count($plan), count($orphans) - count($skipped));

if ($skipped) {
    echo "\nSkipped, siblings disagree on the class — settle these in the mapping first:\n";
    foreach ($skipped as $s) {
        echo '  ' . $s . PHP_EOL;
    }
}

if (! $plan) {
    echo "\nNothing to insert.\n";
    exit(0);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with APPLY to execute.\n";
    exit(0);
}

$now = date('Y-m-d H:i:s');
$ins = $p->prepare('INSERT INTO reinsurance_group_coverage
    (group_id, coverage_id, coverage_name, si_premium, ri_limit, limit_value, regulatory_mapping, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

$cvgId = $p->prepare('SELECT id FROM tb_cvgpccoverages WHERE s_CoverageCode = ? LIMIT 1');

$p->beginTransaction();
$written = [];

try {
    foreach ($plan as $row) {
        $cvgId->execute([$row['code']]);
        $coverageId = $cvgId->fetchColumn();

        // si_premium, ri_limit and limit_value stay at the empty defaults the
        // sibling rows carry: the layer amounts live on reinsurance_formula_details.
        $ins->execute([
            $row['group_id'], $coverageId ?: null, $row['code'],
            '', '', '', $row['mapping'], $now, $now,
        ]);
        $written[] = (int) $p->lastInsertId();
        printf("  %-28s -> group %-5d as %s\n", $row['code'], $row['group_id'], $row['mapping']);
    }

    $p->commit();
} catch (Throwable $e) {
    $p->rollBack();
    fwrite(STDERR, "\nROLLED BACK: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nDone — " . count($written) . " row(s) inserted.\n\nROLLBACK:\n";
echo '  DELETE FROM reinsurance_group_coverage WHERE id IN (' . implode(',', $written) . ");\n";
