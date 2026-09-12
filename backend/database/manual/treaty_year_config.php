<?php
/**
 * Treaty-year configuration — legacy reinsurance_* tables.
 * Companion to docs/RI-04-treaty-year-config-runbook.md.
 *
 * Stands up a new treaty year (config only, no schema change): closes the
 * outgoing treaty, inserts the new one, attaches formulas, and (optionally)
 * clones formulas with new terms. DRY-RUN by default; pass "APPLY" to execute
 * inside a transaction. Captures a rollback block on every run.
 *
 * Credentials come from the ENVIRONMENT — never hardcode them in the repo:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, default 3306)
 *
 * PowerShell:
 *   $env:RI_DB_HOST='graphite-test-write.c1y5xpqlrcpg.af-south-1.rds.amazonaws.com'
 *   $env:RI_DB_NAME='Graphite_live'; $env:RI_DB_USER='graphitebwlive'; $env:RI_DB_PASS='***'
 *   php backend/database/manual/treaty_year_config.php          # dry run
 *   php backend/database/manual/treaty_year_config.php APPLY    # execute
 */

// ===================== CONFIG — fill from the signing schedule =====================
$OUTGOING = [
    'treaty_id'        => 17,           // treaty being renewed/closed (e.g. MUNICH_COM_2024_2025)
    'new_effective_to' => '2026-06-30', // day BEFORE the new treaty incepts — prevents overlap/double-cede
];

$NEW_TREATY = [
    'treaty_name'            => 'MUNICH_COM_2026_2027',
    'treaty_number'          => 'FILL-SLIP-NUMBER',
    'effective_from'         => '2026-07-01',
    'effective_to'           => '2027-06-30',
    'added_by'               => 'FILL-USER',
    'provisional_commission' => 'FILL', // informational — legacy calc reads the formulas, not this column
    'proportional_share'     => 'FILL', // informational
    'cash_loss_advise'       => '0',
    'event_limit'            => 'FILL',  // informational
    'exclusions'             => 'FILL',  // informational
];

// Option A — REUSE existing formulas (like-for-like renewal). List the formula ids to carry over.
// Look them up: SELECT formula_attached FROM reinsurance_treaty_details WHERE treaty_id = 17;
$ATTACH_FORMULAS = [];   // e.g. [16, 17, 18, 44]

// Option B — CLONE formulas with new terms (leave [] to skip; do NOT set both options).
// 'percentage' => null keeps the source value; set a string to override the split for that formula.
$CLONE_FORMULAS = [];    // e.g. [['src'=>16,'code_suffix'=>'-2627','percentage'=>'30'],
                         //       ['src'=>17,'code_suffix'=>'-2627','percentage'=>'70']]

$PROBE_DATE = '2026-08-01'; // a date inside the new window, for the "exactly one active treaty" check
// ===================================================================================

$APPLY = (($argv[1] ?? '') === 'APPLY');

foreach (['RI_DB_HOST','RI_DB_NAME','RI_DB_USER','RI_DB_PASS'] as $k) {
    if (getenv($k) === false || getenv($k) === '') { fwrite(STDERR, "Missing env var: $k\n"); exit(2); }
}
$pdo = new PDO(
    'mysql:host='.getenv('RI_DB_HOST').';port='.(getenv('RI_DB_PORT') ?: '3306').';dbname='.getenv('RI_DB_NAME'),
    getenv('RI_DB_USER'), getenv('RI_DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$q = fn($sql) => $pdo->query($sql)->fetchColumn();

echo $APPLY ? "*** APPLY MODE — writing inside a transaction ***\n\n" : "--- DRY RUN (no writes) ---\n\n";

/* ---- validation ---- */
$out = $pdo->query("SELECT * FROM reinsurance_treaty WHERE id = ".(int)$OUTGOING['treaty_id'])->fetch(PDO::FETCH_ASSOC);
if (!$out) { echo "ERROR: outgoing treaty {$OUTGOING['treaty_id']} not found.\n"; exit(1); }
$origEffectiveTo = $out['effective_to'];

if ($ATTACH_FORMULAS && $CLONE_FORMULAS) { echo "ERROR: set ATTACH_FORMULAS or CLONE_FORMULAS, not both.\n"; exit(1); }
if (!$ATTACH_FORMULAS && !$CLONE_FORMULAS) { echo "ERROR: no formulas specified.\n"; exit(1); }
foreach ($ATTACH_FORMULAS as $fid) {
    if (!$q("SELECT 1 FROM reinsurance_formula WHERE id = ".(int)$fid)) { echo "ERROR: formula id $fid not found.\n"; exit(1); }
}
foreach ($CLONE_FORMULAS as $c) {
    if (!$q("SELECT 1 FROM reinsurance_formula WHERE id = ".(int)$c['src'])) { echo "ERROR: clone source formula {$c['src']} not found.\n"; exit(1); }
}

/* ---- overlap warning (other active treaties intersecting the new window) ---- */
$ov = $pdo->prepare("SELECT id, treaty_name, effective_from, effective_to FROM reinsurance_treaty
    WHERE status = 1 AND id <> ? AND NOT (effective_to < ? OR effective_from > ?)");
$ov->execute([$OUTGOING['treaty_id'], $NEW_TREATY['effective_from'], $NEW_TREATY['effective_to']]);
$overlaps = $ov->fetchAll(PDO::FETCH_ASSOC);
if ($overlaps) {
    echo "!! WARNING: other ACTIVE treaties intersect the new window {$NEW_TREATY['effective_from']}..{$NEW_TREATY['effective_to']}.\n";
    echo "   If any covers the same class, policies will DOUBLE-CEDE. Confirm class before APPLY:\n";
    foreach ($overlaps as $o) echo "     id={$o['id']}  {$o['treaty_name']}  {$o['effective_from']} -> {$o['effective_to']}\n";
    echo "\n";
}

/* ---- id allocation (explicit — works with or without auto-increment) ---- */
$newTreatyId = (int)$q("SELECT COALESCE(MAX(id),0)+1 FROM reinsurance_treaty");
$tdBase = (int)$q("SELECT COALESCE(MAX(id),0) FROM reinsurance_treaty_details");
$fBase  = (int)$q("SELECT COALESCE(MAX(id),0) FROM reinsurance_formula");
$fdBase = (int)$q("SELECT COALESCE(MAX(id),0) FROM reinsurance_formula_details");

/* ---- build the clone plan (Option B) ---- */
$clonePlan = [];              // ['newFormulaId','src','code_suffix','percentage','fdRows'=>[[newFdId, srcFdId]]]
$fSeq = 0; $fdSeq = 0;
foreach ($CLONE_FORMULAS as $c) {
    $newFid = $fBase + (++$fSeq);
    $fdRows = [];
    foreach ($pdo->query("SELECT id FROM reinsurance_formula_details WHERE formula_id = ".(int)$c['src'])->fetchAll(PDO::FETCH_COLUMN) as $srcFdId) {
        $fdRows[] = [$fdBase + (++$fdSeq), (int)$srcFdId];
    }
    $clonePlan[] = ['newFormulaId'=>$newFid, 'src'=>(int)$c['src'], 'code_suffix'=>$c['code_suffix'] ?? '-2627',
                    'percentage'=>$c['percentage'] ?? null, 'fdRows'=>$fdRows];
}

/* ---- the final list of formula ids to attach to the new treaty ---- */
$formulaIdsToAttach = $ATTACH_FORMULAS ?: array_map(fn($p)=>$p['newFormulaId'], $clonePlan);
$tdIds = [];
foreach ($formulaIdsToAttach as $i => $fid) $tdIds[$fid] = $tdBase + $i + 1;

/* ---- print the plan ---- */
echo "== PLAN ==\n";
echo "  Close treaty {$OUTGOING['treaty_id']} ({$out['treaty_name']}): effective_to {$origEffectiveTo} -> {$OUTGOING['new_effective_to']}\n";
echo "  New treaty id {$newTreatyId}: {$NEW_TREATY['treaty_name']} {$NEW_TREATY['effective_from']} -> {$NEW_TREATY['effective_to']}\n";
foreach ($clonePlan as $p) {
    $pct = $p['percentage'] === null ? '(keep source)' : $p['percentage'];
    echo "  Clone formula {$p['src']} -> new id {$p['newFormulaId']} (suffix {$p['code_suffix']}, pct {$pct}, ".count($p['fdRows'])." detail rows)\n";
}
foreach ($tdIds as $fid => $tid) echo "  Attach formula {$fid} to treaty {$newTreatyId} via treaty_details id {$tid}\n";
echo "\n";

/* ---- rollback block ---- */
echo "== ROLLBACK (save this) ==\n";
echo "  DELETE FROM reinsurance_treaty_details WHERE id IN (".implode(',', array_values($tdIds)).");\n";
echo "  DELETE FROM reinsurance_treaty         WHERE id = {$newTreatyId};\n";
if ($clonePlan) {
    $cf = implode(',', array_map(fn($p)=>$p['newFormulaId'], $clonePlan));
    $cfd = [];
    foreach ($clonePlan as $p) foreach ($p['fdRows'] as $r) $cfd[] = $r[0];
    echo "  DELETE FROM reinsurance_formula         WHERE id IN ({$cf});\n";
    echo "  DELETE FROM reinsurance_formula_details WHERE id IN (".implode(',', $cfd).");\n";
}
echo "  UPDATE reinsurance_treaty SET effective_to = ".$pdo->quote($origEffectiveTo)." WHERE id = {$OUTGOING['treaty_id']};\n\n";

if (!$APPLY) { echo "--- DRY RUN complete. Fill CONFIG, re-check, then re-run with APPLY. ---\n"; exit(0); }

/* ---- APPLY ---- */
$pdo->beginTransaction();
try {
    // 1. clone formulas + their details (Option B)
    foreach ($clonePlan as $p) {
        $s = $pdo->prepare("INSERT INTO reinsurance_formula
            (id, product_id, type_id, formula_code, formula_name, s_FormulaType, reinsurance_type_id, status, created_at, updated_at)
            SELECT ?, product_id, type_id, CONCAT(formula_code, ?), CONCAT(formula_name, ?), s_FormulaType, reinsurance_type_id, status, NOW(), NOW()
            FROM reinsurance_formula WHERE id = ?");
        $s->execute([$p['newFormulaId'], $p['code_suffix'], $p['code_suffix'], $p['src']]);

        foreach ($p['fdRows'] as [$newFdId, $srcFdId]) {
            if ($p['percentage'] === null) {
                $s = $pdo->prepare("INSERT INTO reinsurance_formula_details
                    (id, formula_id, group_id, vehicle_type, operator, si_allocation, n_ValueLimitsBetween, percentage, date_from, date_to, created_at, updated_at)
                    SELECT ?, ?, group_id, vehicle_type, operator, si_allocation, n_ValueLimitsBetween, percentage, date_from, date_to, NOW(), NOW()
                    FROM reinsurance_formula_details WHERE id = ?");
                $s->execute([$newFdId, $p['newFormulaId'], $srcFdId]);
            } else {
                $s = $pdo->prepare("INSERT INTO reinsurance_formula_details
                    (id, formula_id, group_id, vehicle_type, operator, si_allocation, n_ValueLimitsBetween, percentage, date_from, date_to, created_at, updated_at)
                    SELECT ?, ?, group_id, vehicle_type, operator, si_allocation, n_ValueLimitsBetween, ?, date_from, date_to, NOW(), NOW()
                    FROM reinsurance_formula_details WHERE id = ?");
                $s->execute([$newFdId, $p['newFormulaId'], $p['percentage'], $srcFdId]);
            }
        }
    }

    // 2. new treaty row
    $s = $pdo->prepare("INSERT INTO reinsurance_treaty
        (id, treaty_name, treaty_number, status, effective_from, effective_to, created_at, updated_at, added_by,
         provisional_commission, proportional_share, cash_loss_advise, event_limit, exclusions)
        VALUES (?, ?, ?, 1, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?)");
    $s->execute([$newTreatyId, $NEW_TREATY['treaty_name'], $NEW_TREATY['treaty_number'],
        $NEW_TREATY['effective_from'], $NEW_TREATY['effective_to'], $NEW_TREATY['added_by'],
        $NEW_TREATY['provisional_commission'], $NEW_TREATY['proportional_share'],
        $NEW_TREATY['cash_loss_advise'], $NEW_TREATY['event_limit'], $NEW_TREATY['exclusions']]);

    // 3. attach formulas
    foreach ($tdIds as $fid => $tid) {
        $pdo->prepare("INSERT INTO reinsurance_treaty_details (id, treaty_id, formula_attached, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())")
            ->execute([$tid, $newTreatyId, $fid]);
    }

    // 4. close the outgoing treaty
    $pdo->prepare("UPDATE reinsurance_treaty SET effective_to = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$OUTGOING['new_effective_to'], $OUTGOING['treaty_id']]);

    // 5. verify — exactly one active treaty for the probe date
    $active = $pdo->prepare("SELECT id, treaty_name FROM reinsurance_treaty WHERE status=1 AND effective_from <= ? AND effective_to >= ?");
    $active->execute([$PROBE_DATE, $PROBE_DATE]);
    $activeRows = $active->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();
    echo "COMMITTED.\n";
    echo "  Active treaties on {$PROBE_DATE}: ".count($activeRows)."\n";
    foreach ($activeRows as $a) echo "    id={$a['id']}  {$a['treaty_name']}\n";
    echo "  (If more than one covers the same class, run the rollback above and fix the dates.)\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "ROLLED BACK — error: ".$e->getMessage()."\n";
    exit(1);
}
