<?php
/**
 * Characterisation check: the seam on the legacy basis must return exactly what
 * the tab's own query returns, for every policy that has cession rows.
 *
 * Read only. Compares, prints, changes nothing.
 */
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use AlphaDirect\Services\Reinsurance\CessionSource;
use Illuminate\Support\Facades\DB;

$original = function (int $policyId) {
    $q = "select
        T.policyNumber, T.risk_id, T.address_name, T.coverage_id,
        (T.group_code) as group_code, (T.id) as group_id, (T.formula_name) as formula_name,
        (T.totalSumInsured) as totalSumInsured, (T.totalPremium) as totalPremium,
        sum(T.NETRETENTION) as 'NETRETENTION',
        SUM(T.QUOTASHARING) as 'QUOTASHARING',
        SUM(T.SURPLUS) as 'SURPLUS',
        SUM(T.FACULTATIVE) as 'FACULTATIVE',
        SUM(T.NETRETENTION_SI) as 'NETRETENTION_SI',
        SUM(T.QUOTASHARING_SI) as 'QUOTASHARING_SI',
        SUM(T.SURPLUS_SI) as 'SURPLUS_SI',
        SUM(T.FACULTATIVE_SI) as 'FACULTATIVE_SI'
        from (select
        pol.policyNumber, prm.risk_id, risk.address_name, gm.group_code, gm.id,
        fm.formula_name, prm.coverage_id, prm.type_id,
        prm.totalSumInsured, prm.totalPremium, prm.treatySI, prm.treatyPercentage, prm.treatyPremium,
        if(trim(prm.type_id)=3,(prm.treatyPremium),0)  as 'NETRETENTION',
        if(trim(prm.type_id)=1,(prm.treatyPremium),0)       as 'QUOTASHARING',
        if(trim(prm.type_id)=4,(prm.treatyPremium),0)      as 'SURPLUS',
        if(trim( COALESCE(prm.type_id,5))=5,(prm.treatyPremium),0)       as 'FACULTATIVE',
        if(trim(prm.type_id)=3,(prm.treatySI),0)  as 'NETRETENTION_SI',
        if(trim(prm.type_id)=1,(prm.treatySI),0)       as 'QUOTASHARING_SI',
        if(trim(prm.type_id)=4,(prm.treatySI),0)      as 'SURPLUS_SI',
        if(trim(COALESCE(prm.type_id,5))=5,(prm.treatySI),0)       as 'FACULTATIVE_SI'
        from policy_reinsurance prm
        left join policies pol on pol.id = prm.policy_id
        left join reinsurance_group gm on gm.id = prm.group_id
        left join reinsurance_formula fm on fm.id = prm.formula_id
        left join risk_address risk on risk.id = prm.risk_id
        where prm.policy_id='" . $policyId . "'
        AND prm.treatyPremium !=0
        ) as T
        group by T.risk_id,T.group_code";

    return DB::select(DB::raw($q));
};

$key = function ($r) {
    return ($r->risk_id ?? 'NULL') . '|' . ($r->group_code ?? 'NULL');
};

$cols = ['NETRETENTION', 'QUOTASHARING', 'SURPLUS', 'FACULTATIVE',
         'NETRETENTION_SI', 'QUOTASHARING_SI', 'SURPLUS_SI', 'FACULTATIVE_SI'];

$src = app(CessionSource::class);

$policyIds = DB::table('policy_reinsurance')
    ->whereNull('deleted_at')
    ->distinct()
    ->orderBy('policy_id')
    ->pluck('policy_id')
    ->filter()
    ->all();

$checked = 0;
$mismatch = [];

foreach ($policyIds as $pid) {
    $a = $original((int) $pid);
    $b = $src->layerRowsForPolicy((int) $pid);

    $ka = [];
    foreach ($a as $r) { $ka[$key($r)] = $r; }
    $kb = [];
    foreach ($b as $r) { $kb[$key($r)] = $r; }

    if (count($ka) !== count($kb) || array_diff_key($ka, $kb) || array_diff_key($kb, $ka)) {
        $mismatch[] = "policy {$pid}: row sets differ (" . count($ka) . ' vs ' . count($kb) . ')';
        continue;
    }

    foreach ($ka as $k => $ra) {
        $rb = $kb[$k];
        foreach ($cols as $c) {
            $va = round((float) ($ra->$c ?? 0), 2);
            $vb = round((float) ($rb->$c ?? 0), 2);
            if (abs($va - $vb) > 0.01) {
                $mismatch[] = "policy {$pid} row {$k} col {$c}: {$va} vs {$vb}";
            }
        }
    }

    $checked++;
}

echo "policies compared : {$checked}\n";
echo 'mismatches        : ' . count($mismatch) . "\n";
foreach (array_slice($mismatch, 0, 12) as $m) {
    echo "  {$m}\n";
}
if (! $mismatch) {
    echo "\nSeam on the legacy basis is identical to the tab's own query.\n";
}
