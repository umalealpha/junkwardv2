<?php
/**
 * Standalone verification of the MotoLink assessment PUSH endpoint logic.
 *
 * Mirrors the policy_cover verify style (in-memory SQLite, no Laravel needed)
 * so the behaviour can be proven without composer/vendor:
 *
 *   1. REAL CODE — calls MotolinkAssessmentBridge::normalizeAssessment() (pure,
 *      facade-free) directly, so the payload→record mapping under test is the
 *      exact code the controller runs.
 *   2. CONTRACT — reproduces ClaimsTrackerController::assessment() +
 *      MotolinkAssessmentBridge::applyNormalized() decision table against a
 *      SQLite `claims` fixture: 422 (empty / no claim number), 404 (unknown
 *      claim), 200 (mirrored), idempotency, and fill-if-empty on the manual
 *      claim_tracker_workflow.assessment_report_date field.
 *
 * The real end-to-end (route + middleware + DB) is proven separately by the
 * controlled production test on a throwaway claim.
 *
 * Run:  php verify/motolink_push_logic_test.php
 */

require __DIR__ . '/../app/Services/Claims/MotolinkAssessmentBridge.php';

use AlphaDirect\Services\Claims\MotolinkAssessmentBridge;

$bridge = new MotolinkAssessmentBridge();

$pass = 0;
$fail = 0;
function check(string $label, $got, $expected): void
{
    global $pass, $fail;
    $ok = $got === $expected;
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}\n";
        echo "       expected: " . var_export($expected, true) . "\n";
        echo "       got:      " . var_export($got, true) . "\n";
    }
}

// ---------------------------------------------------------------------------
// 1. REAL normalizeAssessment() mapping
// ---------------------------------------------------------------------------
echo "1. normalizeAssessment() — real code\n";

// 1a. Rich, camelCase shape (as in the bridge's own screenshot fixtures).
$n = $bridge->normalizeAssessment([
    'assessmentId' => 'ALPHA-TEST-0004', 'claimNumber' => 'TESTCLM-0002',
    'vin' => 'TESTVIN0000000001', 'registration' => 'BTEST004',
    'make' => 'TOYOTA', 'model' => 'FORTUNER (G) 5D', 'status' => 'Completed',
    'finalCost' => 42310.50, 'assessmentReportDate' => '2026-06-24', 'updatedAt' => '2026-06-24',
]);
check('assessmentId', $n['assessmentId'], 'ALPHA-TEST-0004');
check('claimNumber',  $n['claimNumber'],  'TESTCLM-0002');
check('status',       $n['status'],       'Completed');
check('finalCost',    $n['finalCost'],    42310.50);
check('totalLoss (not a write-off)', $n['totalLoss'], 0);
check('writeOffAlert empty',         $n['writeOffAlert'], '');
check('registration', $n['registration'], 'BTEST004');
check('reportDate',   $n['reportDate'],  '2026-06-24');

// 1b. snake_case + nested alert + status-derived write-off + defensive keys.
$n2 = $bridge->normalizeAssessment([
    'assessment_id' => 'X1', 'claim_number' => 'G2026000001', 'state' => 'Write-Off',
    'authorised_amount' => '18000.5', 'alerts' => [['message' => 'Possible write-off detected']],
    'completedAt' => '2026-01-02', 'vinCode' => 'VIN9',
]);
check('snake assessmentId', $n2['assessmentId'], 'X1');
check('snake claimNumber',  $n2['claimNumber'],  'G2026000001');
check('state->status',      $n2['status'],       'Write-Off');
check('write-off detected', $n2['totalLoss'],    1);
check('nested alert msg',   $n2['writeOffAlert'],'Possible write-off detected');
check('authorised_amount->finalCost', $n2['finalCost'], 18000.5);
check('completedAt->reportDate',      $n2['reportDate'], '2026-01-02');
check('vinCode->vin',       $n2['vin'],          'VIN9');

// 1c. Missing claim number normalises to '' (endpoint must 422 on this).
$n3 = $bridge->normalizeAssessment(['status' => 'Completed', 'finalCost' => 100]);
check('no claim number -> empty', $n3['claimNumber'], '');

// ---------------------------------------------------------------------------
// 2. Endpoint contract against a SQLite `claims` fixture
//    (mirrors ClaimsTrackerController::assessment + applyNormalized)
// ---------------------------------------------------------------------------
echo "2. endpoint contract — SQLite fixture\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE claims (
  id INTEGER PRIMARY KEY, claim_number TEXT,
  motolink_assessment_id TEXT, motolink_status TEXT, motolink_final_cost REAL,
  motolink_total_loss INTEGER DEFAULT 0, motolink_write_off_alert TEXT,
  motolink_vin TEXT, motolink_registration TEXT, motolink_make TEXT, motolink_model TEXT,
  motolink_updated_at TEXT, motolink_synced_at TEXT, motolink_sync_error TEXT, updated_at TEXT
)");
$pdo->exec("CREATE TABLE claim_tracker_workflow (
  id INTEGER PRIMARY KEY, claim_id INTEGER, assessment_report_date TEXT, updated_at TEXT
)");
$pdo->exec("INSERT INTO claims (id, claim_number) VALUES (1, 'TESTCLM-0002')");
$pdo->exec("INSERT INTO claim_tracker_workflow (id, claim_id, assessment_report_date) VALUES (1, 1, NULL)");

/**
 * Reproduces assessment() + applyNormalized() exactly. Returns [httpStatus, body].
 */
$endpoint = function (array $payload) use ($bridge, $pdo): array {
    if ($payload === []) {
        return [422, ['status' => false, 'error' => 'Empty or invalid JSON body']];
    }
    $n = $bridge->normalizeAssessment($payload);
    if ($n['claimNumber'] === '') {
        return [422, ['status' => false, 'error' => 'Assessment is missing a claim number']];
    }
    $claim = $pdo->query("SELECT * FROM claims WHERE claim_number = " . $pdo->quote($n['claimNumber']))->fetch(PDO::FETCH_ASSOC);
    if (! $claim) {
        return [404, ['status' => false, 'error' => 'No Graphite claim found for claim number ' . $n['claimNumber']]];
    }
    $stmt = $pdo->prepare("UPDATE claims SET
        motolink_assessment_id=:aid, motolink_status=:st, motolink_final_cost=:fc,
        motolink_total_loss=:tl, motolink_write_off_alert=:al, motolink_vin=:vin,
        motolink_registration=:reg, motolink_make=:mk, motolink_model=:md,
        motolink_updated_at=:ua, motolink_synced_at=:sa, motolink_sync_error='', updated_at=:ua2
        WHERE id=:id");
    $stmt->execute([
        ':aid' => $n['assessmentId'], ':st' => $n['status'], ':fc' => $n['finalCost'],
        ':tl' => $n['totalLoss'], ':al' => $n['writeOffAlert'], ':vin' => $n['vin'],
        ':reg' => $n['registration'], ':mk' => $n['make'], ':md' => $n['model'],
        ':ua' => $n['updatedAt'], ':sa' => '2026-08-25 09:00:00', ':ua2' => '2026-08-25 09:00:00',
        ':id' => $claim['id'],
    ]);
    // fill-if-empty on the MANUAL workflow field — never overwrite.
    if ($n['reportDate'] !== '') {
        $wf = $pdo->query("SELECT * FROM claim_tracker_workflow WHERE claim_id = " . (int) $claim['id'])->fetch(PDO::FETCH_ASSOC);
        if ($wf && ($wf['assessment_report_date'] === null || $wf['assessment_report_date'] === '')) {
            $u = $pdo->prepare("UPDATE claim_tracker_workflow SET assessment_report_date=:d WHERE id=:id");
            $u->execute([':d' => $n['reportDate'], ':id' => $wf['id']]);
        }
    }
    return [200, [
        'status' => true, 'claim_number' => $n['claimNumber'], 'assessment_id' => $n['assessmentId'],
        'motolink_status' => $n['status'], 'total_loss' => (bool) $n['totalLoss'], 'idempotent' => true,
    ]];
};

// 2a. empty body -> 422
[$code] = $endpoint([]);
check('empty body -> 422', $code, 422);

// 2b. payload with no claim number -> 422
[$code] = $endpoint(['status' => 'Completed', 'finalCost' => 100]);
check('no claim number -> 422', $code, 422);

// 2c. unknown claim -> 404
[$code] = $endpoint(['claim_number' => 'G-DOES-NOT-EXIST', 'status' => 'Completed']);
check('unknown claim -> 404', $code, 404);

// 2d. valid push -> 200 + row mirrored
[$code, $body] = $endpoint([
    'assessmentId' => 'ALPHA-TEST-0004', 'claimNumber' => 'TESTCLM-0002',
    'registration' => 'BTEST004', 'make' => 'TOYOTA', 'model' => 'FORTUNER (G) 5D',
    'status' => 'Completed', 'finalCost' => 42310.50, 'assessmentReportDate' => '2026-06-24',
]);
check('valid push -> 200', $code, 200);
check('response idempotent flag', $body['idempotent'], true);
$row = $pdo->query("SELECT * FROM claims WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
check('mirror: motolink_status',        $row['motolink_status'], 'Completed');
check('mirror: motolink_final_cost',    (float) $row['motolink_final_cost'], 42310.50);
check('mirror: motolink_assessment_id', $row['motolink_assessment_id'], 'ALPHA-TEST-0004');
check('mirror: motolink_registration',  $row['motolink_registration'], 'BTEST004');
$wf = $pdo->query("SELECT * FROM claim_tracker_workflow WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
check('workflow report date filled', $wf['assessment_report_date'], '2026-06-24');

// 2e. idempotency: re-send the SAME event -> still 200, still one row, no change
[$code2] = $endpoint([
    'assessmentId' => 'ALPHA-TEST-0004', 'claimNumber' => 'TESTCLM-0002',
    'registration' => 'BTEST004', 'status' => 'Completed', 'finalCost' => 42310.50,
]);
$count = (int) $pdo->query("SELECT COUNT(*) FROM claims WHERE claim_number = 'TESTCLM-0002'")->fetchColumn();
check('re-send -> 200', $code2, 200);
check('idempotent: still exactly one claim row', $count, 1);

// 2f. fill-if-empty: a later send with a DIFFERENT report date must NOT overwrite
$endpoint([
    'assessmentId' => 'ALPHA-TEST-0004', 'claimNumber' => 'TESTCLM-0002',
    'status' => 'Completed', 'report_date' => '2099-01-01',
]);
$wf2 = $pdo->query("SELECT assessment_report_date FROM claim_tracker_workflow WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
check('manual field NOT overwritten', $wf2['assessment_report_date'], '2026-06-24');

// 2g. total-loss push -> write-off flag + alert mirrored
$pdo->exec("INSERT INTO claims (id, claim_number) VALUES (2, 'TESTCLM-0003')");
$endpoint([
    'assessmentId' => 'ALPHA-TEST-0003', 'claimNumber' => 'TESTCLM-0003',
    'status' => 'Total Loss', 'writeOffAlert' => 'Possible write-off detected',
]);
$tl = $pdo->query("SELECT motolink_total_loss, motolink_write_off_alert FROM claims WHERE id = 2")->fetch(PDO::FETCH_ASSOC);
check('total-loss flag set',  (int) $tl['motolink_total_loss'], 1);
check('write-off alert set',  $tl['motolink_write_off_alert'], 'Possible write-off detected');

// ---------------------------------------------------------------------------
// 3. X-Correlation-Id echo guard (ClaimsTrackerController::assessment)
//    Replicates the exact sanitizer: echo only a safe token, never anything
//    that could smuggle header characters (CR/LF would 500 via Symfony).
// ---------------------------------------------------------------------------
echo "3. X-Correlation-Id echo guard\n";

$echoable = function (string $raw): bool {
    $cid = trim($raw);
    return $cid !== '' && strlen($cid) <= 128 && preg_match('/^[A-Za-z0-9._:-]+$/', $cid) === 1;
};

check('plain uuid echoed',        $echoable('550e8400-e29b-41d4-a716-446655440000'), true);
check('dotted/colon id echoed',   $echoable('motolink:job.42_v1'), true);
check('empty not echoed',         $echoable(''), false);
check('whitespace not echoed',    $echoable('   '), false);
check('CRLF smuggle rejected',    $echoable("abc\r\nSet-Cookie: x=1"), false);
check('space rejected',           $echoable('abc def'), false);
check('over-long (129) rejected', $echoable(str_repeat('a', 129)), false);
check('exactly 128 accepted',     $echoable(str_repeat('a', 128)), true);

// ---------------------------------------------------------------------------
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
