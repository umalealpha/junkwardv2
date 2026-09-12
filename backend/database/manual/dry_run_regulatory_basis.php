<?php
/**
 * Run the system on the REGULATORY basis, once, and see what breaks.
 *
 * The seam is built, every read site follows it and the pre-flight passes — but
 * RI_CESSION_ENGINE has never been set anywhere, so the switch has never been
 * exercised. Better to find out what happens now, while we are waiting on the
 * routing sign-off, than in the hour after it arrives.
 *
 * READ ONLY. It flips the basis in memory for this process, exercises every
 * consumer, and writes nothing. The stored config is untouched.
 *
 *   php database/manual/dry_run_regulatory_basis.php
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use AlphaDirect\Services\Reinsurance\CessionSource;
use AlphaDirect\Services\Reinsurance\FacCoverageService;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

function check(string $what, callable $fn): void
{
    global $pass, $fail;

    try {
        $note = $fn();
        $pass++;
        printf("  [ ok ] %-46s %s\n", $what, $note ?? '');
    } catch (\Throwable $e) {
        $fail++;
        printf("  [FAIL] %-46s %s: %s\n", $what, get_class($e),
            mb_strimwidth($e->getMessage(), 0, 90, '…'));
    }
}

$money = fn ($v) => number_format((float) $v, 2);

// A policy that IS persisted, and one that is not.
$persisted = (int) DB::table('policy_reinsurance_regulatory')->value('action_id');
$persistedPolicy = (int) DB::table('policy_reinsurance_regulatory')
    ->where('action_id', $persisted)->value('policy_id');

$unpersisted = DB::table('policy_reinsurance_details as d')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
        ->from('policy_reinsurance_regulatory as r')
        ->whereColumn('r.action_id', 'd.action_id'))
    ->value('d.action_id');

echo "Dry run — the system on the regulatory basis\n";
echo str_repeat('=', 78) . "\n";
echo "  persisted action   : {$persisted} (policy {$persistedPolicy})\n";
echo '  unpersisted action : ' . ($unpersisted ?? 'none') . "\n\n";

// ── the switch itself ──────────────────────────────────────────────────────
echo "THE SWITCH\n";

$src = app(CessionSource::class);
check('basis reads legacy before the flip', function () use ($src) {
    if ($src->basis() !== 'legacy') {
        throw new RuntimeException('expected legacy, got ' . $src->basis());
    }
    return '';
});

config(['reinsurance.engine' => 'regulatory']);
$src = app(CessionSource::class);

check('basis reads regulatory after the flip', function () use ($src) {
    if (! $src->isRegulatory()) {
        throw new RuntimeException('flag did not take: ' . $src->basis());
    }
    return '';
});

// ── the seam's own readers ─────────────────────────────────────────────────
echo "\nTHE SEAM\n";

check('totalsFor() on a persisted action', function () use ($src, $persisted, $money) {
    $t = $src->totalsFor($persisted);
    if (($t['is_cession'] ?? null) !== true) {
        throw new RuntimeException('is_cession should be true on the regulatory basis');
    }
    return 'ceded ' . $money($t['ceded_si']);
});

check('byKeyFor() groups by regulatory class', function () use ($src, $persisted) {
    $k = $src->byKeyFor($persisted);
    return count($k) . ' class(es): ' . implode(', ', array_keys($k));
});

check('totalsFor() RAISES on an unpersisted action', function () use ($src, $unpersisted) {
    if ($unpersisted === null) {
        return 'no unpersisted action to test';
    }
    try {
        $src->totalsFor((int) $unpersisted);
    } catch (\RuntimeException $e) {
        return 'raised as designed';
    }
    throw new RuntimeException('returned a figure instead of raising');
});

// ── the two live tabs ──────────────────────────────────────────────────────
echo "\nTHE TABS\n";

check('layerRowsForAction() — both branches', function () use ($src, $persistedPolicy, $persisted) {
    $r = $src->layerRowsForAction($persistedPolicy, $persisted);
    return sprintf('motor %d, non-motor %d', count($r['motor']), count($r['non_motor']));
});

check('layerRowsForPolicy()', function () use ($src, $persistedPolicy) {
    return count($src->layerRowsForPolicy($persistedPolicy)) . ' row(s)';
});

check('tab rows carry no formula name', function () use ($src, $persistedPolicy, $persisted) {
    $r = $src->layerRowsForAction($persistedPolicy, $persisted);
    $rows = array_merge($r['motor'], $r['non_motor']);
    foreach ($rows as $row) {
        if (($row->formula_name ?? null) !== null) {
            throw new RuntimeException('formula_name should be null on the regulatory basis');
        }
    }
    return 'null as intended';
});

// ── the facultative coverage gap ───────────────────────────────────────────
echo "\nTHE FACULTATIVE COVERAGE GAP\n";

$fac = app(FacCoverageService::class);

check('riskUnitsQuery() scoped to one action', function () use ($src, $persistedPolicy, $persisted, $money) {
    $u = $src->riskUnitsQuery($persistedPolicy, $persisted)->get();
    return sprintf('%d unit(s), risk %s', $u->count(), $money($u->sum('risk_si')));
});

check('shortfallFor()', function () use ($fac, $persistedPolicy, $persisted, $money) {
    $s = $fac->shortfallFor($persistedPolicy, $persisted);
    return sprintf('outside %s, over-ceded %s', $money($s['sumInsured']), $money($s['overCededSumInsured']));
});

check('scan() across the book', function () use ($fac) {
    $t0 = microtime(true);
    $rows = $fac->scan(true, 5000);
    return sprintf('%d row(s) in %.1fs', count($rows), microtime(true) - $t0);
});

// ── comparison against the legacy answer ───────────────────────────────────
echo "\nWHAT MOVES\n";

config(['reinsurance.engine' => 'legacy']);
$legacySrc = app(CessionSource::class);
$legacyFac = app(FacCoverageService::class);
$legacyShort = $legacyFac->shortfallFor($persistedPolicy, $persisted);
$legacyTabs = $legacySrc->layerRowsForAction($persistedPolicy, $persisted);

config(['reinsurance.engine' => 'regulatory']);
$regSrc = app(CessionSource::class);
$regFac = app(FacCoverageService::class);
$regShort = $regFac->shortfallFor($persistedPolicy, $persisted);
$regTabs = $regSrc->layerRowsForAction($persistedPolicy, $persisted);

printf("  facultative shortfall  legacy %-18s regulatory %s\n",
    $money($legacyShort['sumInsured']), $money($regShort['sumInsured']));
printf("  tab rows               legacy %-18s regulatory %s\n",
    count($legacyTabs['motor']) + count($legacyTabs['non_motor']),
    count($regTabs['motor']) + count($regTabs['non_motor']));

echo "\n" . str_repeat('=', 78) . "\n";
printf("%d passed, %d failed. The stored config was never changed.\n", $pass, $fail);
