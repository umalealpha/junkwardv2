<?php
/**
 * Gender Column-Type Reconciliation Smoke
 *
 * Empirically verifies the three findings from the policy_members /
 * controller-writes reconciliation by performing real INSERTs and
 * SELECTs against graphite_dev, then ROLLING BACK so no test rows persist.
 *
 * Targets:
 *   • policy_members.gender         (INT NULL)
 *   • customer_profile.gender       (INT NULL)
 *   • policy_beneficiary.gender     (INT NULL)
 *   • ad_pricings.gender            (VARCHAR(10) 'M'/'F')
 *
 * Each test prints PASS / FAIL with the actual stored / matched value.
 * Exits 0 if all pass, 1 otherwise.
 *
 *   php scripts/gender_reconcile_smoke.php
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=graphite_dev;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);   // real prepared statements — preserves PHP type on bind

$pass = 0; $fail = 0;
$results = [];

function rec(array &$results, string $id, string $verdict, string $detail): void {
    $results[] = compact('id', 'verdict', 'detail');
}

// ── T0: capture sql_mode for context (strict mode would ERROR instead of coerce) ──
$sqlMode = $pdo->query("SELECT @@sql_mode AS m")->fetch(PDO::FETCH_ASSOC)['m'];
$strict  = str_contains($sqlMode, 'STRICT_TRANS_TABLES') || str_contains($sqlMode, 'STRICT_ALL_TABLES');
rec($results, 'T0',
    $strict ? 'INFO-STRICT' : 'INFO-LAX',
    "sql_mode = $sqlMode" . ($strict ? '  (strict mode ON — coercion would raise an error)' : '  (no strict mode — string→int coerces silently)'));

/**
 * Insert `gender` into $table inside a transaction, return what MySQL stored,
 * then rollback. Bind as STRING explicitly to mirror what Laravel/PDO does
 * when controllers pass `normalizeGender()` output.
 */
function probe(PDO $pdo, string $table, $value, ?int $bindType = null): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO {$table} (gender, created_at, updated_at) VALUES (?, NOW(), NOW())");
        $stmt->bindValue(1, $value, $bindType ?? (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
        $stmt->execute();
        $id   = $pdo->lastInsertId();
        $row  = $pdo->query("SELECT gender FROM {$table} WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
        // Disambiguate null-stored from missing-row — `??` collapses both.
        if ($row === false)                        $stored = '(no row found)';
        elseif (!array_key_exists('gender', $row)) $stored = '(no gender column)';
        else                                       $stored = $row['gender'];   // may legitimately be null
        $pdo->rollBack();
        return ['ok' => true, 'stored' => $stored];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ─────────── Finding 1: policy_members.gender INT vs controller string write ───────────

$r = probe($pdo, 'policy_members', 'Male');
if (!$r['ok']) {
    rec($results, 'T1.1', 'FAIL', "INSERT 'Male' into policy_members.gender raised: " . $r['error']); $fail++;
} else {
    $coerced = ($r['stored'] === '0' || $r['stored'] === 0);
    rec($results, 'T1.1',
        $coerced ? 'BUG-CONFIRMED' : 'UNEXPECTED',
        "INSERT 'Male' → stored = " . var_export($r['stored'], true)
            . ($coerced ? '  ✓ string silently coerced to 0' : '  ⚠ unexpected stored value'));
    $coerced ? $pass++ : $fail++;
}

$r = probe($pdo, 'policy_members', 'Female');
$coerced = $r['ok'] && ($r['stored'] === '0' || $r['stored'] === 0);
rec($results, 'T1.2', $coerced ? 'BUG-CONFIRMED' : 'UNEXPECTED',
    "INSERT 'Female' → stored = " . var_export($r['stored'] ?? null, true)
        . ($coerced ? '  ✓ Female also collapses to 0 — Male/Female indistinguishable' : ''));
$coerced ? $pass++ : $fail++;

$r = probe($pdo, 'policy_members', 1, PDO::PARAM_INT);
$ok = $r['ok'] && ((int) $r['stored'] === 1);
rec($results, 'T1.3', $ok ? 'PASS' : 'FAIL',
    "INSERT integer 1 → stored = " . var_export($r['stored'] ?? null, true)
        . ($ok ? '  ✓ INT bind round-trips correctly (1 = Male per V8 convention)' : ''));
$ok ? $pass++ : $fail++;

$r = probe($pdo, 'policy_members', null, PDO::PARAM_NULL);
$ok = $r['ok'] && $r['stored'] === null;
rec($results, 'T1.4', $ok ? 'PASS' : 'FAIL',
    "INSERT NULL → stored = " . var_export($r['stored'] ?? null, true)
        . ($ok ? '  ✓ NULL preserved' : ''));
$ok ? $pass++ : $fail++;

// ─────────── Finding 2: same bug on customer_profile.gender + policy_beneficiary.gender ───────────

$r = probe($pdo, 'customer_profile', 'Male');
$coerced = $r['ok'] && ($r['stored'] === '0' || $r['stored'] === 0);
rec($results, 'T2.1', $coerced ? 'BUG-CONFIRMED' : 'UNEXPECTED',
    "customer_profile: INSERT 'Male' → stored = " . var_export($r['stored'] ?? null, true)
        . ($coerced ? '  ✓ same coercion — explains 7,614 historic gender=0 rows' : ''));
$coerced ? $pass++ : $fail++;

$r = probe($pdo, 'policy_beneficiary', 'Male');
$coerced = $r['ok'] && ($r['stored'] === '0' || $r['stored'] === 0);
rec($results, 'T2.2', $coerced ? 'BUG-CONFIRMED' : 'UNEXPECTED',
    "policy_beneficiary: INSERT 'Male' → stored = " . var_export($r['stored'] ?? null, true)
        . ($coerced ? '  ✓ same coercion — explains 11,033 historic gender=0 rows' : ''));
$coerced ? $pass++ : $fail++;

// ─────────── Finding 3: ad_pricings rate lookup broken ───────────

$hitMale  = (int) $pdo->query("SELECT COUNT(*) FROM ad_pricings WHERE gender='Male'")->fetchColumn();
$hitM     = (int) $pdo->query("SELECT COUNT(*) FROM ad_pricings WHERE gender='M'")->fetchColumn();
$hitF     = (int) $pdo->query("SELECT COUNT(*) FROM ad_pricings WHERE gender='F'")->fetchColumn();
$hitFemale= (int) $pdo->query("SELECT COUNT(*) FROM ad_pricings WHERE gender='Female'")->fetchColumn();

rec($results, 'T3.1',
    $hitMale === 0 ? 'BUG-CONFIRMED' : 'UNEXPECTED',
    "ad_pricings WHERE gender='Male' → $hitMale hits"
        . ($hitMale === 0 ? "  ✓ controller's normalized 'Male' never matches stored 'M'" : ''));
$hitMale === 0 ? $pass++ : $fail++;

rec($results, 'T3.2',
    $hitM === 55 ? 'PASS' : 'WARN',
    "ad_pricings WHERE gender='M' → $hitM hits  (control: 'M' literal works)");
$hitM === 55 ? $pass++ : $fail++;

rec($results, 'T3.3',
    $hitFemale === 0 ? 'BUG-CONFIRMED' : 'UNEXPECTED',
    "ad_pricings WHERE gender='Female' → $hitFemale hits  ('Female' also fails to match 'F')");
$hitFemale === 0 ? $pass++ : $fail++;

// ─────────── Bonus: cross-direction coercion in WHERE clauses ───────────

$hitWrong = (int) $pdo->query("SELECT COUNT(*) FROM customer_profile WHERE gender='Male'")->fetchColumn();
$hit0     = (int) $pdo->query("SELECT COUNT(*) FROM customer_profile WHERE gender=0")->fetchColumn();
rec($results, 'T4.1',
    ($hitWrong === $hit0) ? 'BUG-CONFIRMED' : 'WARN',
    "customer_profile WHERE gender='Male' → $hitWrong hits, WHERE gender=0 → $hit0 hits"
        . ($hitWrong === $hit0 ? "  ✓ string predicate against INT col coerces 'Male'→0, matches ALL coerced rows" : ''));
($hitWrong === $hit0) ? $pass++ : $fail++;

// ─────────── Print results ───────────

echo str_repeat('=', 88) . "\n";
echo "GENDER COLUMN-TYPE RECONCILIATION SMOKE\n";
echo str_repeat('=', 88) . "\n\n";

printf("%-7s  %-15s  %s\n", 'TEST', 'VERDICT', 'EVIDENCE');
echo str_repeat('-', 88) . "\n";
foreach ($results as $r) {
    printf("%-7s  %-15s  %s\n", $r['id'], $r['verdict'], $r['detail']);
}

$bugConfirmed = count(array_filter($results, fn($r) => $r['verdict'] === 'BUG-CONFIRMED'));
echo "\n" . str_repeat('=', 88) . "\n";
printf("PASS: %d   FAIL: %d   BUG-CONFIRMED: %d   (total %d checks)\n",
    $pass, $fail, $bugConfirmed, count($results));
echo str_repeat('=', 88) . "\n\n";

if ($fail > 0) {
    echo "⚠  Some checks failed unexpectedly — review evidence above before trusting the report.\n";
    exit(1);
}
echo "✓ All probes behaved as the reconciliation predicted. Findings stand.\n";
exit(0);
