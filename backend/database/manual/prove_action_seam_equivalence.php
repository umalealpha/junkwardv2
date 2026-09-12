<?php
/**
 * Does CessionSource::layerRowsForAction() return exactly what the two tabs'
 * own queries return?
 *
 * TmpTable (Livewire) and PolicyController::reinsurance() (v2 API) run the same
 * pair of queries. This compares the seam against that pair, row by row, for
 * every (policy, action) carrying cession rows.
 *
 * WHY THIS AND NOT A SQLITE TEST. The split turns on IF(TRIM(type_id)=3, ...).
 * MySQL coerces TRIM's string result back to a number and matches; sqlite does
 * not and returns nil for every bucket. A sqlite test would pass while
 * asserting the opposite of production, which is worse than no test. Real data
 * is the only honest check for these two.
 *
 * READ ONLY. Compares, prints, changes nothing.
 *
 *   php database/manual/prove_action_seam_equivalence.php
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use AlphaDirect\Services\Reinsurance\CessionSource;
use Illuminate\Support\Facades\DB;

$motorGroupIds = [13, 14, 15, 28, 29];

$original = function (int $policyId, int $actionId) use ($motorGroupIds) {
    $baseSelect = "
        pol.policyNumber,
        prm.risk_id,
        risk.address_name,
        gm.group_code,
        gm.id as group_id,
        fm.formula_name,
        tr.treaty_name,
        tr.treaty_number,
        prm.coverage_id,
        prm.totalSumInsured,
        prm.totalPremium,
        IF(TRIM(prm.type_id)=3, prm.treatyPremium, 0) AS NETRETENTION,
        IF(TRIM(prm.type_id)=1, prm.treatyPremium, 0) AS QUOTASHARING,
        IF(TRIM(prm.type_id)=4, prm.treatyPremium, 0) AS SURPLUS,
        IF(TRIM(COALESCE(prm.type_id,5))=5, prm.treatyPremium, 0) AS FACULTATIVE,
        IF(TRIM(COALESCE(prm.type_id,6))=6, prm.treatyPremium, 0) AS FACULATIVEPLACEMENT,
        IF(TRIM(prm.type_id)=3, prm.treatySI, 0) AS NETRETENTION_SI,
        IF(TRIM(prm.type_id)=1, prm.treatySI, 0) AS QUOTASHARING_SI,
        IF(TRIM(prm.type_id)=4, prm.treatySI, 0) AS SURPLUS_SI,
        IF(TRIM(COALESCE(prm.type_id,5))=5, prm.treatySI, 0) AS FACULTATIVE_SI,
        IF(TRIM(COALESCE(prm.type_id,6))=6, prm.treatySI, 0) AS FACULATIVEPLACEMENT_SI
    ";

    $baseFrom = "
        FROM policy_reinsurance prm
        LEFT JOIN policies pol ON pol.id = prm.policy_id
        LEFT JOIN reinsurance_group gm ON gm.id = prm.group_id
        LEFT JOIN reinsurance_formula fm ON fm.id = prm.formula_id
        LEFT JOIN reinsurance_treaty tr ON tr.id = prm.treaty_id
        LEFT JOIN risk_address risk ON risk.id = prm.risk_id
        WHERE prm.policy_id = ?
        AND prm.action_id = ?
        AND prm.deleted_at IS NULL
    ";

    $ids = implode(',', $motorGroupIds);

    $motorSql = "
        SELECT T.policyNumber, T.risk_id, T.address_name, T.coverage_id,
            T.group_code, T.group_id, T.formula_name,
            T.treaty_name, T.treaty_number,
            T.totalSumInsured, T.totalPremium,
            SUM(DISTINCT T.NETRETENTION) AS NETRETENTION,
            SUM(DISTINCT T.QUOTASHARING) AS QUOTASHARING,
            SUM(DISTINCT T.SURPLUS) AS SURPLUS,
            SUM(DISTINCT T.FACULTATIVE) AS FACULTATIVE,
            SUM(DISTINCT T.FACULATIVEPLACEMENT) AS FACULATIVEPLACEMENT,
            SUM(DISTINCT T.NETRETENTION_SI) AS NETRETENTION_SI,
            SUM(DISTINCT T.QUOTASHARING_SI) AS QUOTASHARING_SI,
            SUM(DISTINCT T.SURPLUS_SI) AS SURPLUS_SI,
            SUM(DISTINCT T.FACULTATIVE_SI) AS FACULTATIVE_SI,
            SUM(DISTINCT T.FACULATIVEPLACEMENT_SI) AS FACULATIVEPLACEMENT_SI
        FROM (SELECT {$baseSelect} {$baseFrom} AND gm.id IN({$ids})) AS T
        GROUP BY T.coverage_id
    ";

    $nonMotorSql = "
        SELECT T.policyNumber, T.risk_id, T.address_name, T.coverage_id,
            T.group_code, T.group_id, T.formula_name,
            T.treaty_name, T.treaty_number,
            T.totalSumInsured, T.totalPremium,
            SUM(T.NETRETENTION) AS NETRETENTION,
            SUM(T.QUOTASHARING) AS QUOTASHARING,
            SUM(T.SURPLUS) AS SURPLUS,
            SUM(T.FACULTATIVE) AS FACULTATIVE,
            SUM(T.FACULATIVEPLACEMENT) AS FACULATIVEPLACEMENT,
            SUM(T.NETRETENTION_SI) AS NETRETENTION_SI,
            SUM(T.QUOTASHARING_SI) AS QUOTASHARING_SI,
            SUM(T.SURPLUS_SI) AS SURPLUS_SI,
            SUM(T.FACULTATIVE_SI) AS FACULTATIVE_SI,
            SUM(T.FACULATIVEPLACEMENT_SI) AS FACULATIVEPLACEMENT_SI
        FROM (SELECT {$baseSelect} {$baseFrom}
            AND gm.id NOT IN({$ids})
            AND prm.treatyPremium != 0
            AND prm.totalSumInsured != 0) AS T
        GROUP BY T.risk_id, T.group_code
    ";

    $params = [$policyId, $actionId];

    return [
        'motor'     => DB::select($motorSql, $params),
        'non_motor' => DB::select($nonMotorSql, $params),
    ];
};

$cols = ['NETRETENTION', 'QUOTASHARING', 'SURPLUS', 'FACULTATIVE', 'FACULATIVEPLACEMENT',
         'NETRETENTION_SI', 'QUOTASHARING_SI', 'SURPLUS_SI', 'FACULTATIVE_SI',
         'FACULATIVEPLACEMENT_SI'];

$src = app(CessionSource::class);

$pairs = DB::table('policy_reinsurance')
    ->whereNull('deleted_at')
    ->whereNotNull('policy_id')
    ->whereNotNull('action_id')
    ->distinct()
    ->orderBy('policy_id')
    ->get(['policy_id', 'action_id']);

$checked = 0;
$mismatch = [];

foreach ($pairs as $p) {
    $a = $original((int) $p->policy_id, (int) $p->action_id);
    $b = $src->layerRowsForAction((int) $p->policy_id, (int) $p->action_id);

    foreach (['motor', 'non_motor'] as $branch) {
        $ka = [];
        foreach ($a[$branch] as $r) {
            $ka[($r->risk_id ?? 'N') . '|' . ($r->group_code ?? 'N') . '|' . ($r->coverage_id ?? 'N')] = $r;
        }
        $kb = [];
        foreach ($b[$branch] as $r) {
            $kb[($r->risk_id ?? 'N') . '|' . ($r->group_code ?? 'N') . '|' . ($r->coverage_id ?? 'N')] = $r;
        }

        if (count($ka) !== count($kb)) {
            $mismatch[] = "policy {$p->policy_id} action {$p->action_id} {$branch}: "
                . count($ka) . ' rows vs ' . count($kb);
            continue;
        }

        foreach ($ka as $k => $ra) {
            if (! isset($kb[$k])) {
                $mismatch[] = "policy {$p->policy_id} {$branch}: row {$k} missing from seam";
                continue;
            }
            foreach ($cols as $c) {
                $va = round((float) ($ra->$c ?? 0), 2);
                $vb = round((float) ($kb[$k]->$c ?? 0), 2);
                if (abs($va - $vb) > 0.01) {
                    $mismatch[] = "policy {$p->policy_id} {$branch} {$k} {$c}: {$va} vs {$vb}";
                }
            }
        }
    }

    $checked++;
}

echo "policy/action pairs compared : {$checked}\n";
echo 'mismatches                   : ' . count($mismatch) . "\n";
foreach (array_slice($mismatch, 0, 12) as $m) {
    echo "  {$m}\n";
}
if (! $mismatch) {
    echo "\nSeam on the legacy basis is identical to both tabs' own queries.\n";
}
