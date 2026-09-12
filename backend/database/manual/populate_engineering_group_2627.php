<?php
/**
 * Puts the Engineering coverages into ENGINEERING_AND_BI_COM, which exists and is
 * empty.
 *
 * WHY IT IS NEEDED. The regulatory mapping decides the treaty route from 2026/27,
 * but the engine reaches it through the GROUP: a coverage that sits in no
 * reinsurance group has no mapping to read and produces no cession row at all.
 * ENGINEERING_AND_BI_COM was created and never populated, so every Engineering
 * coverage on the book currently routes nowhere and reports as retained by
 * omission rather than by decision.
 *
 * WHAT GOES IN, AND ON WHOSE AUTHORITY.
 *
 *   PLANTALLRISKS       Engineering   RI-10 point 12, 24 August 2026
 *   MACHINERYBREAKDOWN  Engineering   RI-10 point 12, 24 August 2026
 *   ERECTIONALLRISKS    Engineering   Reinsurance, 31 August 2026 — "Erection All
 *                                     Risk should be classed under Engineering
 *                                     just as the final terms have combined them
 *                                     with Contractors All Risk."
 *
 * TWO THAT ARE DELIBERATELY NOT HERE.
 *
 *   CONTRACTORSALLRISKS — RI-10 point 12 put it under PROPERTY on 24 August, and
 *   the 31 August answer reads as putting it under Engineering alongside Erection
 *   All Risks. Both classes are LAYERED and run the identical cascade, so no
 *   cession figure turns on it; the class it reports under does, and that is what
 *   the regulatory return reads. Left out until the two instructions are
 *   reconciled rather than guessed at.
 *
 *   ASSETSALLRISKS — "Assets All Risk should be under the same coverage as Plant
 *   All Risk" (31 August). There is no such coverage code in tb_cvgpccoverages. If
 *   it is genuinely the same coverage then Plant All Risks already carries it and
 *   nothing is needed; if a distinct code is created later, add it here.
 *
 * THE MAPPING IS A PROPERTY OF THE GROUP. Every row written carries 'Engineering',
 * matching seed_regulatory_mapping_by_group_2627.php, which maps by group and not
 * by sub-coverage.
 *
 * ADDITIVE AND IDEMPOTENT. A coverage already in the group is reported and skipped,
 * never duplicated. A coverage code that does not exist is reported and skipped,
 * never invented.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction. A rollback
 * block is printed on every APPLY.
 *
 * Credentials from the ENVIRONMENT, as treaty_year_config.php — never .env, which
 * has pointed at production before now:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/populate_engineering_group_2627.php        # dry run
 *   php backend/database/manual/populate_engineering_group_2627.php APPLY  # execute
 */

const GROUP_CODE = 'ENGINEERING_AND_BI_COM';
const MAPPING    = 'Engineering';

/** Coverage codes to place in the group, with the authority for each. */
const COVERAGES = [
    'PLANTALLRISKS'      => 'RI-10 point 12, 24 Aug 2026',
    'MACHINERYBREAKDOWN' => 'RI-10 point 12, 24 Aug 2026',
    'ERECTIONALLRISKS'   => 'Reinsurance, 31 Aug 2026',
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

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user,
    $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo 'mode     : ' . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

// ── the group ───────────────────────────────────────────────────────────────
$g = $p->prepare('SELECT id, group_code, product_id FROM reinsurance_group WHERE group_code = ?');
$g->execute([GROUP_CODE]);
$group = $g->fetch(PDO::FETCH_ASSOC);

if (! $group) {
    fwrite(STDERR, 'ABORT: group ' . GROUP_CODE . " does not exist. Create it first.\n");
    exit(1);
}

$groupId = (int) $group['id'];
printf("group    : %s (id %d, product %s)\n\n", $group['group_code'], $groupId, $group['product_id'] ?? 'NULL');

// ── what is already in it ───────────────────────────────────────────────────
$have = $p->prepare('SELECT coverage_name FROM reinsurance_group_coverage WHERE group_id = ?');
$have->execute([$groupId]);
$existing = array_map('strtoupper', $have->fetchAll(PDO::FETCH_COLUMN));

printf("already in the group: %d row(s)%s\n\n", count($existing),
    $existing ? ' — ' . implode(', ', $existing) : '');

// ── resolve each coverage code ──────────────────────────────────────────────
$lookup = $p->prepare('SELECT id, s_CoverageCode FROM tb_cvgpccoverages WHERE s_CoverageCode = ?');
$plan   = [];
$line   = str_repeat('-', 88);

echo $line . PHP_EOL;
printf("%-22s %-10s %-28s %s\n", 'COVERAGE', 'ID', 'AUTHORITY', 'ACTION');
echo $line . PHP_EOL;

foreach (COVERAGES as $code => $authority) {
    $lookup->execute([$code]);
    $cvg = $lookup->fetch(PDO::FETCH_ASSOC);

    if (! $cvg) {
        printf("%-22s %-10s %-28s %s\n", $code, '—', $authority, 'SKIP — no such coverage code');
        continue;
    }
    if (in_array(strtoupper($code), $existing, true)) {
        printf("%-22s %-10s %-28s %s\n", $code, $cvg['id'], $authority, 'SKIP — already in the group');
        continue;
    }

    $plan[] = ['id' => (int) $cvg['id'], 'code' => $cvg['s_CoverageCode'], 'why' => $authority];
    printf("%-22s %-10s %-28s %s\n", $code, $cvg['id'], $authority, 'INSERT');
}

echo $line . PHP_EOL;
printf("\n%d row(s) to insert, each mapped '%s'.\n\n", count($plan), MAPPING);

if (! $plan) {
    echo "Nothing to do.\n";
    exit(0);
}

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with APPLY to execute.\n";
    exit(0);
}

// ── write ───────────────────────────────────────────────────────────────────
$now = date('Y-m-d H:i:s');
$ins = $p->prepare('INSERT INTO reinsurance_group_coverage
    (group_id, coverage_id, coverage_name, si_premium, ri_limit, limit_value, regulatory_mapping, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

$p->beginTransaction();
$written = [];

try {
    foreach ($plan as $row) {
        // si_premium, ri_limit and limit_value are left at the empty defaults the
        // other Engineering-shaped rows carry: the layer amounts live on
        // reinsurance_formula_details, not here.
        $ins->execute([$groupId, $row['id'], $row['code'], '', '', '', MAPPING, $now, $now]);
        $written[] = (int) $p->lastInsertId();
        printf("  inserted %-22s (coverage %d) as row %d\n", $row['code'], $row['id'], end($written));
    }

    $p->commit();
} catch (Throwable $e) {
    $p->rollBack();
    fwrite(STDERR, "\nROLLED BACK: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nDone.\n\nROLLBACK:\n";
echo '  DELETE FROM reinsurance_group_coverage WHERE id IN (' . implode(',', $written) . ");\n";
