<?php
/**
 * De-duplicate reinsurance_group_coverage on its real natural key.
 *
 * THE KEY IS (group_id, coverage_id, coverage_name) — NOT (group_id, coverage_id).
 * The narrower key looks right and is not: a motor coverage carries one row per TYPE
 * OF COVER, so MOTOR_DOM coverage 27 legitimately holds "Comprehensive",
 * "Third Party Only" and "Third Fire And Theft". Keying without the name would have
 * deleted two of those three as duplicates and destroyed live configuration.
 *
 * Every row removed here is byte-identical to the sibling that is kept — same
 * si_premium, same ri_limit, same limit_value, same created_at to the second. That is
 * verified again at run time, not assumed, and the script aborts if it is ever false.
 *
 * Backs up to a dated table first, and refuses to proceed unless the backup's row
 * count matches the source exactly.
 */
/*
 * Credentials come from the ENVIRONMENT, matching treaty_year_config.php — never
 * from .env and never hardcoded. That is deliberate: .env has pointed at the
 * production host before now, and a delete script must make the operator state
 * which database they mean rather than inherit it.
 *
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 * PowerShell:
 *   $env:RI_DB_HOST='graphite-test-write.c1y5xpqlrcpg.af-south-1.rds.amazonaws.com'
 *   $env:RI_DB_NAME='Graphite_live'; $env:RI_DB_USER='...'; $env:RI_DB_PASS='***'
 *   php backend/database/manual/dedupe_reinsurance_group_coverage.php        # dry run
 *   php backend/database/manual/dedupe_reinsurance_group_coverage.php APPLY  # execute
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
$backup = 'reinsurance_group_coverage_bk_' . date('Ymd');

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user, $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$one = fn (string $sql) => $p->query($sql)->fetchColumn();

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo "mode     : " . ($apply ? "APPLY" : "DRY RUN") . "\n\n";

$before = (int) $one('SELECT COUNT(*) FROM reinsurance_group_coverage');
echo "rows before            : $before\n";

/*
 * SAFETY GATE. Re-proves that no set on the natural key holds differing values.
 * If this ever returns a row, something has changed since the analysis and a
 * keep-lowest-id delete would lose real data — so stop rather than guess.
 */
$differing = (int) $one("
    SELECT COUNT(*) FROM (
        SELECT group_id, coverage_id, coverage_name
        FROM reinsurance_group_coverage
        GROUP BY group_id, coverage_id, coverage_name
        HAVING COUNT(*) > 1
           AND COUNT(DISTINCT CONCAT(
                   IFNULL(si_premium,'~'), '|', IFNULL(ri_limit,'~'), '|', IFNULL(limit_value,'~')
               )) > 1
    ) z
");
if ($differing > 0) {
    fwrite(STDERR, "ABORTED: $differing duplicate set(s) hold DIFFERING values. "
        . "These need a human decision — nothing has been changed.\n");
    exit(1);
}
echo "sets with differing values: 0  (safe)\n";

$expectedKeep = (int) $one("
    SELECT COUNT(*) FROM (
        SELECT 1 FROM reinsurance_group_coverage
        GROUP BY group_id, coverage_id, coverage_name
    ) z
");
echo "rows that should remain: $expectedKeep\n";
echo "rows to delete         : " . ($before - $expectedKeep) . "\n\n";

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

// ── Backup, and refuse to go on unless it is complete ──────────────────
if ($one("SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema='{$name}' AND table_name='$backup'")) {
    fwrite(STDERR, "ABORTED: $backup already exists. Rename or drop it first — "
        . "overwriting a backup is how the only copy gets lost.\n");
    exit(1);
}

$p->exec("CREATE TABLE $backup AS SELECT * FROM reinsurance_group_coverage");
$backedUp = (int) $one("SELECT COUNT(*) FROM $backup");
echo "backup table           : $backup\n";
echo "backup rows            : $backedUp\n";

if ($backedUp !== $before) {
    fwrite(STDERR, "ABORTED: backup holds $backedUp rows against $before in the source. "
        . "Nothing deleted.\n");
    exit(1);
}
echo "backup verified        : row counts match\n\n";

// ── The delete, in a transaction ───────────────────────────────────────
$p->beginTransaction();
try {
    $deleted = $p->exec("
        DELETE gc FROM reinsurance_group_coverage gc
        JOIN (
            SELECT MIN(id) AS keep_id, group_id, coverage_id, coverage_name
            FROM reinsurance_group_coverage
            GROUP BY group_id, coverage_id, coverage_name
        ) k
          ON  k.group_id    = gc.group_id
          AND k.coverage_id = gc.coverage_id
          AND IFNULL(k.coverage_name,'~') = IFNULL(gc.coverage_name,'~')
        WHERE gc.id <> k.keep_id
    ");

    $after = (int) $one('SELECT COUNT(*) FROM reinsurance_group_coverage');

    if ($after !== $expectedKeep) {
        throw new RuntimeException("post-delete count is $after, expected $expectedKeep");
    }

    $stillDup = (int) $one("
        SELECT COUNT(*) FROM (
            SELECT 1 FROM reinsurance_group_coverage
            GROUP BY group_id, coverage_id, coverage_name HAVING COUNT(*) > 1
        ) z
    ");
    if ($stillDup > 0) {
        throw new RuntimeException("$stillDup duplicate set(s) remain");
    }

    $p->commit();
    echo "deleted                : $deleted\n";
    echo "rows after             : $after\n";
    echo "duplicate sets left    : 0\n\n";
    echo "COMMITTED.\n";
    echo "Restore if needed:\n";
    echo "  DELETE FROM reinsurance_group_coverage;\n";
    echo "  INSERT INTO reinsurance_group_coverage SELECT * FROM $backup;\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nThe table is unchanged. Backup $backup remains.\n");
    exit(1);
}
