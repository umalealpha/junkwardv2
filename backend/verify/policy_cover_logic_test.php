<?php
/**
 * Standalone verification of the policyCover() business logic against an
 * in-memory SQLite fixture. This mirrors the resolution rules implemented
 * in ClaimsTrackerController::policyCover (no Laravel needed) so we can
 * prove the behaviour: claim->policy resolution, sum_insured fallback,
 * per-vehicle excess selection, standard-schedule fallback, and the
 * named-missing-field routing.
 */

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE policies (id INTEGER PRIMARY KEY, policyNumber TEXT, sum_assured REAL)");
$pdo->exec("CREATE TABLE claims (id INTEGER PRIMARY KEY, claim_number TEXT, policy_id INTEGER)");
$pdo->exec("CREATE TABLE policy_coverages (id INTEGER PRIMARY KEY, policy_id INTEGER, deleted_at TEXT)");
$pdo->exec("CREATE TABLE motor (
  id INTEGER PRIMARY KEY, policy_coverage_id INTEGER, estimated_value TEXT, deleted_at TEXT,
  own_damage_minimun_percent REAL, own_damage_minimum_amount REAL,
  windscreen_minimun_percent REAL, windscreen_minimum_amount REAL,
  loss_of_keys_minimun_percent REAL, loss_of_keys_minimum_amount REAL
)");

// ---- Fixtures -------------------------------------------------------------
// Policy A: full sum_assured + per-vehicle excess captured.
$pdo->exec("INSERT INTO policies VALUES (10,'COMG2026000010',250000)");
$pdo->exec("INSERT INTO claims   VALUES (1,'G2026000001',10)");
$pdo->exec("INSERT INTO policy_coverages VALUES (100,10,NULL)");
$pdo->exec("INSERT INTO motor VALUES (1000,100,'240000',NULL, 5,2500, 0,500, 0,350)");

// Policy B: NO sum_assured -> fall back to motor.estimated_value; no excess -> standard schedule.
$pdo->exec("INSERT INTO policies VALUES (20,'COMG2026000020',0)");
$pdo->exec("INSERT INTO claims   VALUES (2,'G2026000002',20)");
$pdo->exec("INSERT INTO policy_coverages VALUES (200,20,NULL)");
$pdo->exec("INSERT INTO motor VALUES (2000,200,'180000',NULL, NULL,NULL, NULL,NULL, NULL,NULL)");

// Policy C: no sum_assured anywhere, no motor value -> missing_fields=[sum_insured].
$pdo->exec("INSERT INTO policies VALUES (30,'COMG2026000030',0)");
$pdo->exec("INSERT INTO claims   VALUES (3,'G2026000003',30)");
$pdo->exec("INSERT INTO policy_coverages VALUES (300,30,NULL)");
$pdo->exec("INSERT INTO motor VALUES (3000,300,'',NULL, NULL,NULL, NULL,NULL, NULL,NULL)");

// ---- Logic under test (port of policyCover) --------------------------------
const STANDARD_EXCESS_SCHEDULE = [
    ['type' => 'Own Damage',                          'min_percent' => null, 'min_amount' => null],
    ['type' => 'Theft/Hijacking Excess (each claim)', 'min_percent' => null, 'min_amount' => null],
    ['type' => 'Underage Driver <30 Years',           'min_percent' => null, 'min_amount' => null],
    ['type' => 'License Issue <2 Years from Policy Issue', 'min_percent' => null, 'min_amount' => null],
    ['type' => 'Windscreen / Glass',                  'min_percent' => null, 'min_amount' => null],
    ['type' => 'Loss of Keys',                        'min_percent' => null, 'min_amount' => null],
];

function policyCover(PDO $pdo, string $claimNumber): array {
    $st = $pdo->prepare("SELECT * FROM claims WHERE claim_number=?");
    $st->execute([$claimNumber]);
    $claim = $st->fetch(PDO::FETCH_ASSOC);
    if (!$claim) return ['status'=>false,'http'=>404,'message'=>'Claim not found'];

    $st = $pdo->prepare("SELECT * FROM policies WHERE id=?");
    $st->execute([$claim['policy_id']]);
    $policy = $st->fetch(PDO::FETCH_ASSOC);
    if (!$policy) return ['status'=>false,'http'=>404,'message'=>'Policy not found'];

    $sumInsured = is_numeric($policy['sum_assured']) ? (float)$policy['sum_assured'] : null;
    if ($sumInsured === null || $sumInsured <= 0) {
        $st = $pdo->prepare("SELECT m.estimated_value FROM motor m JOIN policy_coverages pc ON pc.id=m.policy_coverage_id WHERE pc.policy_id=? AND m.deleted_at IS NULL AND pc.deleted_at IS NULL ORDER BY m.id DESC LIMIT 1");
        $st->execute([$policy['id']]);
        $mv = $st->fetchColumn();
        if (is_numeric($mv) && (float)$mv > 0) $sumInsured = (float)$mv;
    }

    $st = $pdo->prepare("SELECT own_damage_minimun_percent,own_damage_minimum_amount,windscreen_minimun_percent,windscreen_minimum_amount,loss_of_keys_minimun_percent,loss_of_keys_minimum_amount FROM motor m JOIN policy_coverages pc ON pc.id=m.policy_coverage_id WHERE pc.policy_id=? AND m.deleted_at IS NULL AND pc.deleted_at IS NULL ORDER BY m.id DESC LIMIT 1");
    $st->execute([$policy['id']]);
    $mr = $st->fetch(PDO::FETCH_ASSOC);

    $excesses = []; $src = 'standard_schedule';
    if ($mr) {
        $cands = [
            ['type'=>'Own Damage','p'=>$mr['own_damage_minimun_percent'],'a'=>$mr['own_damage_minimum_amount']],
            ['type'=>'Windscreen / Glass','p'=>$mr['windscreen_minimun_percent'],'a'=>$mr['windscreen_minimum_amount']],
            ['type'=>'Loss of Keys','p'=>$mr['loss_of_keys_minimun_percent'],'a'=>$mr['loss_of_keys_minimum_amount']],
        ];
        foreach ($cands as $c) {
            $hasP = is_numeric($c['p']) && (float)$c['p']>0;
            $hasA = is_numeric($c['a']) && (float)$c['a']>0;
            if ($hasP || $hasA) $excesses[] = ['type'=>$c['type'],'min_percent'=>$hasP?(float)$c['p']:null,'min_amount'=>$hasA?(float)$c['a']:null];
        }
        if ($excesses) $src = 'policy';
    }
    if (!$excesses) { $excesses = STANDARD_EXCESS_SCHEDULE; $src='standard_schedule'; }

    if ($sumInsured !== null && $sumInsured <= 0) $sumInsured = null; // never expose 0 as a real cover
    $missing = [];
    if ($sumInsured === null) $missing[] = 'sum_insured';

    return ['status'=>true,'http'=>200,'policy_number'=>$policy['policyNumber'],'sum_insured'=>$sumInsured,'currency'=>'BWP','excesses'=>$excesses,'excess_source'=>$src,'missing_fields'=>$missing];
}

// ---- Assertions ------------------------------------------------------------
$pass = 0; $fail = 0;
function check($name,$cond) { global $pass,$fail; if ($cond){echo "PASS  $name\n";$pass++;} else {echo "FAIL  $name\n";$fail++;} }

$A = policyCover($pdo,'G2026000001');
check('A resolves via claim_number',         $A['status']===true && $A['http']===200);
check('A sum_insured = policies.sum_assured', $A['sum_insured']===250000.0);
check('A excess_source = policy',             $A['excess_source']==='policy');
check('A own-damage excess captured (5% / 2500)', $A['excesses'][0]['type']==='Own Damage' && $A['excesses'][0]['min_percent']===5.0 && $A['excesses'][0]['min_amount']===2500.0);
check('A no missing fields',                  $A['missing_fields']===[]);

$B = policyCover($pdo,'G2026000002');
check('B sum_insured falls back to motor.estimated_value', $B['sum_insured']===180000.0);
check('B excess falls back to standard schedule', $B['excess_source']==='standard_schedule' && count($B['excesses'])===6);
check('B no missing fields (value recovered)', $B['missing_fields']===[]);

$C = policyCover($pdo,'G2026000003');
check('C sum_insured null when truly missing', $C['sum_insured']===null);  // normalized: 0/empty -> null
check('C missing_fields=[sum_insured]',        $C['missing_fields']===['sum_insured']);
check('C still 200 (named-missing-field route, no guess)', $C['http']===200 && $C['status']===true);

$N = policyCover($pdo,'G2099999999');
check('Unknown claim -> 404',                  $N['http']===404);

// ---- Privacy whitelist check ----------------------------------------------
$keys = array_keys($A);
$forbidden = ['name','omang','passport','dob','bank','customer','phone','email','id_number'];
$leak = false;
foreach ($keys as $k) foreach ($forbidden as $f) if (stripos($k,$f)!==false) $leak=true;
check('Response exposes NO PII keys', $leak===false);

echo "\n--- sample A payload ---\n".json_encode($A, JSON_PRETTY_PRINT)."\n";
echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail===0 ? 0 : 1);
