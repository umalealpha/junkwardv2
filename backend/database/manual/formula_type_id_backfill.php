<?php
/**
 * Backfill reinsurance_formula.type_id on the 2026/27 formulas.
 *
 * WHY
 * ---
 * The cession calc splits a class per coverage detail only when the formula is
 * flagged Motor:
 *
 *     GROUP BY ... case when fm.type_id = 35 then prid.pocoverage_detail_id else '' end
 *
 * lookup_data (key = reinsurance_formula_key):  35 = Motor, 36 = Non-Motor.
 *
 * Every 2024/25 formula carries that flag — motor groups 35, general groups 36.
 * All 22 of the 2026/27 formulas were created with type_id NULL, because the
 * formula form never sent the field. NULL falls into the `else ''` branch, so the
 * motor classes stopped ceding per vehicle: on a two-vehicle policy the details
 * collapse to one row and the second vehicle is dropped entirely.
 *
 * Observed on COMG2026213751 — MOTOR_COM was fed coverage details 132023
 * (SI 34,000,000) and 132024 (SI 4,000,000); only the first produced a cession.
 *
 * EFFECT
 * ------
 * Motor (82-89) NULL -> 35 : restores one cession per vehicle. This CHANGES
 *                            cession on any multi-vehicle commercial policy.
 * General (68-81) NULL -> 36 : behaviour-neutral today, since NULL and 36 both
 *                            take the `else` branch. Set for correctness so the
 *                            flag is explicit rather than absent.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction.
 * Credentials come from the ENVIRONMENT — never hardcode them:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 * PowerShell:
 *   $env:RI_DB_HOST='graphite-test-write.c1y5xpqlrcpg.af-south-1.rds.amazonaws.com'
 *   $env:RI_DB_NAME='Graphite_live'; $env:RI_DB_USER='graphitebwlive'; $env:RI_DB_PASS='***'
 *   php backend/database/manual/formula_type_id_backfill.php          # dry run
 *   php backend/database/manual/formula_type_id_backfill.php APPLY    # execute
 *
 * Companion to docs/RI-04-treaty-year-config-runbook.md and RI-05 section 1.
 */

// ===================== CONFIG =====================
const MOTOR_KEY     = 35;   // lookup_data 35 = Motor      -> cede per vehicle
const NON_MOTOR_KEY = 36;   // lookup_data 36 = Non-Motor  -> aggregate per class

$MOTOR_FORMULAS   = [82, 83, 84, 85, 86, 87, 88, 89];
$GENERAL_FORMULAS = [68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81];

// Only touch rows that are still NULL — never overwrite a flag already set.
$ONLY_WHEN_NULL = true;
// ==================================================

$APPLY = (($argv[1] ?? '') === 'APPLY');

foreach (['RI_DB_HOST', 'RI_DB_NAME', 'RI_DB_USER', 'RI_DB_PASS'] as $k) {
    if (getenv($k) === false || getenv($k) === '') {
        fwrite(STDERR, "Missing env var: {$k}\n");
        exit(2);
    }
}
$pdo = new PDO(
    'mysql:host=' . getenv('RI_DB_HOST') . ';port=' . (getenv('RI_DB_PORT') ?: '3306')
        . ';dbname=' . getenv('RI_DB_NAME'),
    getenv('RI_DB_USER'), getenv('RI_DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo $APPLY ? "*** APPLY MODE — writing inside a transaction ***\n\n"
            : "--- DRY RUN (no writes) ---\n\n";

/* ---- validate the lookup keys exist ---- */
foreach ([MOTOR_KEY => 'Motor', NON_MOTOR_KEY => 'Non-Motor'] as $id => $expected) {
    $row = $pdo->query("SELECT value FROM lookup_data
                        WHERE id = {$id} AND `key` = 'reinsurance_formula_key'")->fetch();
    if (!$row) {
        echo "ERROR: lookup_data id {$id} (reinsurance_formula_key) not found.\n";
        exit(1);
    }
    if ($row['value'] !== $expected) {
        echo "ERROR: lookup_data id {$id} is '{$row['value']}', expected '{$expected}'. Aborting.\n";
        exit(1);
    }
    echo "  lookup_data {$id} = {$row['value']}  ok\n";
}

/* ---- current state ---- */
$all = array_merge($MOTOR_FORMULAS, $GENERAL_FORMULAS);
$in  = implode(',', $all);
$rows = $pdo->query("
    SELECT fm.id, fm.formula_code, fm.type_id,
           (SELECT gm.group_code FROM reinsurance_formula_details fd
              JOIN reinsurance_group gm ON gm.id = fd.group_id
             WHERE fd.formula_id = fm.id LIMIT 1) AS group_code
    FROM reinsurance_formula fm WHERE fm.id IN ({$in}) ORDER BY fm.id")->fetchAll();

if (count($rows) !== count($all)) {
    echo "\nERROR: expected " . count($all) . " formulas, found " . count($rows) . ". Aborting.\n";
    exit(1);
}

$target = [];
foreach ($MOTOR_FORMULAS as $id)   $target[$id] = MOTOR_KEY;
foreach ($GENERAL_FORMULAS as $id) $target[$id] = NON_MOTOR_KEY;

echo "\n" . str_repeat('-', 104) . "\n";
printf("%-5s %-50s %-26s %-10s %-10s %s\n", 'f#', 'formula_code', 'group', 'current', 'target', 'action');
echo str_repeat('-', 104) . "\n";

$toUpdate = [];
$rollback = [];
foreach ($rows as $r) {
    $cur  = $r['type_id'];
    $want = $target[$r['id']];
    if ((string) $cur === (string) $want) {
        $action = 'already set — skip';
    } elseif ($cur !== null && $ONLY_WHEN_NULL) {
        $action = "*** has {$cur}, not NULL — SKIPPED (guard) ***";
    } else {
        $action = 'UPDATE';
        $toUpdate[] = ['id' => $r['id'], 'want' => $want];
        $rollback[] = "UPDATE reinsurance_formula SET type_id = "
            . ($cur === null ? 'NULL' : (int) $cur) . " WHERE id = {$r['id']};";
    }
    printf("%-5s %-50s %-26s %-10s %-10s %s\n", $r['id'], substr($r['formula_code'], 0, 50),
        $r['group_code'] ?? '-', $cur === null ? 'NULL' : $cur, $want, $action);
}

echo "\n" . count($toUpdate) . " formula(s) to update.\n";
if (!$toUpdate) { echo "Nothing to do.\n"; exit(0); }

echo "\n--- statements ---\n";
foreach ($toUpdate as $u)
    echo "UPDATE reinsurance_formula SET type_id = {$u['want']}, updated_at = NOW() WHERE id = {$u['id']};\n";

echo "\n--- ROLLBACK (keep this) ---\n";
foreach ($rollback as $r) echo "{$r}\n";

if (!$APPLY) {
    echo "\nDry run complete. Re-run with APPLY to execute.\n";
    echo "After applying, recalculate a multi-vehicle commercial policy and confirm one\n";
    echo "cession pair per vehicle, each at 30/70 of its own capacity.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE reinsurance_formula SET type_id = :t, updated_at = NOW() WHERE id = :id");
    foreach ($toUpdate as $u) $stmt->execute([':t' => $u['want'], ':id' => $u['id']]);
    $pdo->commit();
    echo "\nApplied " . count($toUpdate) . " update(s).\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\nFAILED, rolled back: " . $e->getMessage() . "\n";
    exit(1);
}

/* ---- verify ---- */
echo "\n--- verification ---\n";
foreach ($pdo->query("SELECT id, type_id FROM reinsurance_formula WHERE id IN ({$in}) ORDER BY id")->fetchAll() as $r) {
    $ok = (string) $r['type_id'] === (string) $target[$r['id']];
    printf("  f#%-4s type_id %-6s %s\n", $r['id'], $r['type_id'] ?? 'NULL', $ok ? 'ok' : '*** MISMATCH ***');
}
echo "\nNow recalculate the policy and re-check the cession rows.\n";
