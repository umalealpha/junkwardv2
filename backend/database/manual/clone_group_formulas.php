<?php
/**
 * Clones every reinsurance formula from one group to another, values and all.
 *
 * Written to give Engineering (CAR / EAR / MB) the same layer set as Material Damage
 * & Business Interruption under the 2026/27 General Quota Share — same operators, same
 * si_allocation, same percentage, same treaty attachment, differing only in the group
 * it hangs off and the group prefix in formula_code / formula_name.
 *
 * NOTHING IS HARDCODED FROM A SCREENSHOT. Every value is read from the source formula
 * at run time and copied. The si_allocation and percentage on the Material Damage rows
 * were never visible to whoever wrote this, and guessing them would have been worse
 * than useless: a wrong Band 1 capacity silently mis-cedes every Engineering risk.
 *
 * SCOPED TO ONE TREATY. Only formulas attached to TREATY_ID are considered, so the
 * legacy pre-2026 set stays out of it. The clone is attached to the same treaty as its
 * source.
 *
 * s_FormulaType IS CANONICALISED ON THE WAY THROUGH. The V2 formula API served each
 * type as an id/name pair and a client posted the label back, so live rows carry
 * 'Surplus' and 'Facultative Placement' where the cession dispatch wants 'SURPLUS' and
 * 'FACULATIVEPLACEMENT' — see 96bf61ecb. Clones are written with the canonical token so
 * this does not propagate. The source rows are left exactly as they are; 96bf61ecb makes
 * the engine tolerant of both, so there is nothing to repair.
 *
 * ADDITIVE AND IDEMPOTENT. A target formula_code that already exists is skipped, not
 * updated. Engineering already has its SURPLUS-26-27 row from
 * add_2627_property_engineering_layers.php, so expect that one to be reported as a skip.
 *
 * DRY-RUN by default; pass "APPLY" to execute inside a transaction.
 * Credentials from the ENVIRONMENT:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional)
 *
 * PowerShell:
 *   $env:RI_DB_HOST='graphite-test-write.c1y5xpqlrcpg.af-south-1.rds.amazonaws.com'
 *   $env:RI_DB_NAME='Graphite_live'; $env:RI_DB_USER='graphitebwlive'; $env:RI_DB_PASS='***'
 *   php backend/database/manual/clone_group_formulas.php          # dry run
 *   php backend/database/manual/clone_group_formulas.php APPLY    # execute
 */

const TREATY_ID = 33;   // GENERAL_QS_2026_2027

/** Source: Material Damage & BI. group_code, and the prefix its formula_code uses. */
const SRC_GROUP_CODE  = 'PROPERTYANDBI_COM';
const SRC_CODE_PREFIX = 'MATERIALDAMAGEBUSINESSINTERRUPTIONCOMBINED-COM';

/** Target: Engineering. The group_code and formula_code prefix differ — do not derive one
 *  from the other, they genuinely are not the same string.
 *
 *  DST_CODE_PREFIX is deliberately shorter than the group_code and shorter than the
 *  prefix add_2627_property_engineering_layers.php used ('ENGINEERING-AND-BI-COM'). That
 *  older prefix is why the duplicate check below is by LAYER TYPE and not by formula_code:
 *  a code-only check would not recognise ENGINEERING-AND-BI-COM-SURPLUS-26-27 as the same
 *  layer as ENGINEERING-COM-SURPLUS-26-27, would create the second one, and the master
 *  query would then return two SURPLUS rows for one group and cede the layer twice. */
const DST_GROUP_CODE  = 'ENGINEERING_AND_BI_COM';
const DST_CODE_PREFIX = 'ENGINEERING-COM';

/** s_FormulaType spellings folded to the token the cession dispatch compares against. */
const FORMULA_TYPE_ALIASES = [
    'TSI'                  => 'TSI',
    'OTHER'                => 'OTHER',
    'SURPLUS'              => 'SURPLUS',
    'FACULTATIVE'          => 'FACULTATIVE',
    'FACULATIVE'           => 'FACULTATIVE',
    'FACULTATIVEPLACEMENT' => 'FACULATIVEPLACEMENT',
    'FACULATIVEPLACEMENT'  => 'FACULATIVEPLACEMENT',
];

function canonicalFormulaType($raw): ?string
{
    if ($raw === null || $raw === '') {
        return $raw;
    }
    $key = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $raw));

    return FORMULA_TYPE_ALIASES[$key] ?? $raw;
}

$APPLY = (($argv[1] ?? '') === 'APPLY');

foreach (['RI_DB_HOST', 'RI_DB_NAME', 'RI_DB_USER', 'RI_DB_PASS'] as $k) {
    if (getenv($k) === false || getenv($k) === '') { fwrite(STDERR, "Missing env var: {$k}\n"); exit(2); }
}
$pdo = new PDO(
    'mysql:host=' . getenv('RI_DB_HOST') . ';port=' . (getenv('RI_DB_PORT') ?: '3306')
        . ';dbname=' . getenv('RI_DB_NAME'),
    getenv('RI_DB_USER'), getenv('RI_DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo $APPLY ? "*** APPLY MODE - writing inside a transaction ***\n\n" : "--- DRY RUN (no writes) ---\n\n";

/* ---------------- validate treaty ---------------- */
$t = $pdo->query("SELECT treaty_name, effective_from, effective_to, status
                    FROM reinsurance_treaty WHERE id = " . TREATY_ID)->fetch();
if (!$t) { echo "ERROR: treaty " . TREATY_ID . " not found.\n"; exit(1); }
printf("treaty %d  %s  %s -> %s  (status %s)\n\n",
    TREATY_ID, $t['treaty_name'], $t['effective_from'], $t['effective_to'], $t['status']);

/* ---------------- resolve groups ---------------- */
$grp = function (string $code) use ($pdo) {
    $s = $pdo->prepare("SELECT id, group_code FROM reinsurance_group WHERE group_code = ?");
    $s->execute([$code]);
    $rows = $s->fetchAll();
    if (count($rows) !== 1) {
        echo "ERROR: group_code '{$code}' matched " . count($rows) . " rows, expected 1.\n";
        if ($rows) foreach ($rows as $r) echo "   {$r['id']}  {$r['group_code']}\n";
        else {
            echo "   Nearest codes:\n";
            $like = '%' . preg_replace('/[^A-Za-z]/', '%', $code) . '%';
            $n = $pdo->prepare("SELECT id, group_code FROM reinsurance_group WHERE group_code LIKE ? LIMIT 10");
            $n->execute([$like]);
            foreach ($n->fetchAll() as $r) echo "     {$r['id']}  {$r['group_code']}\n";
        }
        exit(1);
    }
    return $rows[0];
};

$src = $grp(SRC_GROUP_CODE);
$dst = $grp(DST_GROUP_CODE);
printf("source group %-4s %s   prefix %s\n", $src['id'], $src['group_code'], SRC_CODE_PREFIX);
printf("target group %-4s %s   prefix %s\n\n", $dst['id'], $dst['group_code'], DST_CODE_PREFIX);

if ($src['id'] === $dst['id']) { echo "ERROR: source and target are the same group.\n"; exit(1); }

/* ---------------- read the source set ---------------- */
$q = $pdo->prepare(
    "SELECT fm.id AS fid, fm.product_id, fm.type_id, fm.formula_code, fm.formula_name,
            fm.s_FormulaType, fm.reinsurance_type_id, fm.status,
            fd.id AS did, fd.vehicle_type, fd.operator, fd.si_allocation,
            fd.n_ValueLimitsBetween, fd.percentage, fd.date_from, fd.date_to
       FROM reinsurance_treaty_details td
       JOIN reinsurance_formula fm         ON fm.id = td.formula_attached
       JOIN reinsurance_formula_details fd ON fd.formula_id = fm.id
      WHERE td.treaty_id = ? AND fd.group_id = ?
      ORDER BY fm.reinsurance_type_id, fm.id");
$q->execute([TREATY_ID, $src['id']]);
$source = $q->fetchAll();

if (!$source) {
    echo "Nothing to clone: no formulas on treaty " . TREATY_ID . " for group {$src['id']}.\n";
    exit(1);
}

/* ---------------- what the target already has ---------------- */
/* Keyed by canonical layer type, NOT by formula_code. The target's existing rows were
 * created under a different code prefix, so only the layer type identifies them. Two
 * formulas of the same type on one group and treaty would both come back from the
 * master query and the layer would cede twice. */
$q->execute([TREATY_ID, $dst['id']]);
$targetExisting = $q->fetchAll();

/* Key on type AND reinsurance_type_id: net retention and quota share are BOTH stored as
 * s_FormulaType 'TSI' and differ only by reinsurance_type_id (3 = retention, 1 = quota
 * share). Keying on the type alone would treat the two legs of Band 1 as one layer and
 * silently drop the second. */
$layerKey = fn($row) => canonicalFormulaType($row['s_FormulaType']) . '|' . (int) $row['reinsurance_type_id'];

$haveType = [];
foreach ($targetExisting as $e) {
    $haveType[$layerKey($e)] = $e;
}

/* ---------------- build the plan ---------------- */
$exists = $pdo->prepare("SELECT id FROM reinsurance_formula WHERE formula_code = ?");
$plan = [];
$skips = [];

foreach ($source as $s) {
    if (strpos($s['formula_code'], SRC_CODE_PREFIX) !== 0) {
        $skips[] = [$s['formula_code'], 'code does not start with the source prefix - cannot rename safely'];
        continue;
    }
    $suffix  = substr($s['formula_code'], strlen(SRC_CODE_PREFIX));
    $newCode = DST_CODE_PREFIX . $suffix;
    $type    = canonicalFormulaType($s['s_FormulaType']);
    $key     = $layerKey($s);

    if (isset($haveType[$key])) {
        $have = $haveType[$key];
        $skips[] = [$newCode, "target already has {$type} / ritype {$have['reinsurance_type_id']}"
            . " - f#{$have['fid']} {$have['formula_code']}"];
        continue;
    }

    $exists->execute([$newCode]);
    if ($exists->fetchColumn()) {
        $skips[] = [$newCode, 'formula_code already exists'];
        continue;
    }

    $newName = strpos($s['formula_name'], SRC_CODE_PREFIX) === 0
        ? DST_CODE_PREFIX . substr($s['formula_name'], strlen(SRC_CODE_PREFIX))
        : $newCode;

    $plan[] = [
        'src'     => $s,
        'code'    => $newCode,
        'name'    => $newName,
        'type'    => $type,
        'retyped' => $type !== $s['s_FormulaType'],
    ];

    // Claim the layer so a duplicate on the SOURCE side cannot plan it twice either.
    $haveType[$key] = ['fid' => 'planned', 'formula_code' => $newCode,
                       'reinsurance_type_id' => $s['reinsurance_type_id']];
}

/* ---------------- report ---------------- */
echo "SOURCE FORMULAS ON TREATY " . TREATY_ID . " FOR {$src['group_code']}\n" . str_repeat('-', 118) . "\n";
printf("%-6s %-52s %-22s %-5s %-4s %-16s %s\n",
    'f#', 'formula_code', 's_FormulaType', 'rity', 'op', 'si_allocation', 'pct');
foreach ($source as $s) {
    printf("f#%-4s %-52s %-22s %-5s %-4s %-16s %s\n",
        $s['fid'], $s['formula_code'], (string) $s['s_FormulaType'], (string) $s['reinsurance_type_id'],
        (string) $s['operator'], (string) $s['si_allocation'], (string) $s['percentage']);
}

echo "\nALREADY ON TREATY " . TREATY_ID . " FOR {$dst['group_code']} (group {$dst['id']})\n" . str_repeat('-', 118) . "\n";
if (!$targetExisting) {
    echo "  (none)\n";
} else {
    printf("%-6s %-52s %-22s %-5s %-4s %-16s %s\n",
        'f#', 'formula_code', 's_FormulaType', 'rity', 'op', 'si_allocation', 'pct');
    foreach ($targetExisting as $e) {
        printf("f#%-4s %-52s %-22s %-5s %-4s %-16s %s\n",
            $e['fid'], $e['formula_code'], (string) $e['s_FormulaType'], (string) $e['reinsurance_type_id'],
            (string) $e['operator'], (string) $e['si_allocation'], (string) $e['percentage']);
    }
}

if ($skips) {
    echo "\nSKIPPED\n" . str_repeat('-', 118) . "\n";
    foreach ($skips as [$code, $why]) printf("  %-52s %s\n", $code, $why);
}

if (!$plan) { echo "\nNothing to create - target already has every formula.\n"; exit(0); }

echo "\nTO CREATE FOR {$dst['group_code']} (group {$dst['id']})\n" . str_repeat('-', 118) . "\n";
printf("%-52s %-22s %-5s %-4s %-16s %s\n",
    'formula_code', 's_FormulaType', 'rity', 'op', 'si_allocation', 'pct');
foreach ($plan as $p) {
    printf("%-52s %-22s %-5s %-4s %-16s %s%s\n",
        $p['code'], $p['type'], (string) $p['src']['reinsurance_type_id'],
        (string) $p['src']['operator'], (string) $p['src']['si_allocation'],
        (string) $p['src']['percentage'],
        $p['retyped'] ? "   <- canonicalised from '{$p['src']['s_FormulaType']}'" : '');
}
printf("\n%d formula(s) to create, each attached to treaty %d.\n", count($plan), TREATY_ID);

if (!$APPLY) {
    echo "\nDry run complete. Re-run with APPLY to execute.\n";
    exit(0);
}

/* ---------------- apply ---------------- */
$created = [];
$pdo->beginTransaction();
try {
    $fIns = $pdo->prepare("INSERT INTO reinsurance_formula
        (product_id, type_id, formula_code, formula_name, s_FormulaType, reinsurance_type_id, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
    $dIns = $pdo->prepare("INSERT INTO reinsurance_formula_details
        (formula_id, group_id, vehicle_type, operator, si_allocation, n_ValueLimitsBetween, percentage, date_from, date_to, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $tIns = $pdo->prepare("INSERT INTO reinsurance_treaty_details
        (treaty_id, formula_attached, created_at, updated_at) VALUES (?,?,NOW(),NOW())");

    foreach ($plan as $p) {
        $s = $p['src'];
        $fIns->execute([$s['product_id'], $s['type_id'], $p['code'], $p['name'],
                        $p['type'], $s['reinsurance_type_id'], $s['status']]);
        $fid = (int) $pdo->lastInsertId();

        $dIns->execute([$fid, $dst['id'], $s['vehicle_type'], $s['operator'], $s['si_allocation'],
                        $s['n_ValueLimitsBetween'], $s['percentage'], $s['date_from'], $s['date_to']]);
        $tIns->execute([TREATY_ID, $fid]);

        $created[] = ['id' => $fid, 'code' => $p['code']];
    }
    $pdo->commit();
    printf("\nCOMMITTED - %d formula(s) created and attached.\n", count($created));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\nROLLED BACK - " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nCREATED\n" . str_repeat('-', 70) . "\n";
foreach ($created as $c) printf("  f#%-5s %s\n", $c['id'], $c['code']);

$ids = implode(',', array_column($created, 'id'));
echo "\nROLLBACK (keep this)\n" . str_repeat('-', 70) . "\n";
echo "  DELETE FROM reinsurance_treaty_details  WHERE formula_attached IN ({$ids});\n";
echo "  DELETE FROM reinsurance_formula_details WHERE formula_id IN ({$ids});\n";
echo "  DELETE FROM reinsurance_formula         WHERE id IN ({$ids});\n";

echo "\nNow recalculate a policy carrying an Engineering risk above BWP 10,000,000 and\n";
echo "confirm the split matches Material Damage at the same sum insured.\n";
