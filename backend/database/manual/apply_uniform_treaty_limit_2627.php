<?php
/**
 * Applies the ONE treaty limit Reinsurance confirmed on 26 August 2026, replacing the
 * per-class Schedule A limits that were configured.
 *
 * THE RULE, in Tlamelo Chimidza's words:
 *
 *   "the treaty stipulates limit of 7,000,000 on cession and 3,000,000 on retention
 *    for the regulatory mapping. However our limits underwritten may be lower than the
 *    treaty limits which we will use the limit on those covers."
 *
 * So there is a SINGLE treaty first line of 10,000,000 for every regulatory mapping —
 * 3,000,000 retained and 7,000,000 ceded — and where the underwritten sum insured is
 * lower, the 30/70 applies to the sum insured itself. His own examples: Motor at
 * 34,000,000 runs up to the 10,000,000 treaty limit; Motor at 4,000,000 cedes
 * 2,800,000 and retains 1,200,000 off the 4,000,000.
 *
 * WHAT WAS WRONG. Each group carried its own Schedule A class limit — Motor 5,000,000,
 * Goods in Transit 3,000,000, Miscellaneous and Fidelity 1,000,000, Accidental Damage
 * 7,500,000, Electronic Equipment 6,000,000. That understated cession by 56,172,000 on
 * COMG2026213751 against his manual working. The ENGINE was already right: it caps at
 * the lower of sum insured and limit, which is why Motor's 4,000,000 row already showed
 * 1,200,000 / 2,800,000. Only the limit VALUES were wrong.
 *
 * SURPLUS follows the MAPPING, not the group. His working gives every Property-mapped
 * group a surplus — Accidental Damage 40,000,000, Electronic Equipment 2,123,000, Motor
 * Traders External 210,000 — but only PROPERTYANDBI and ENGINEERING had the formula.
 * Motor, Transportation, Miscellaneous and Guarantee show nil surplus on his working and
 * are left without one.
 *
 * AUTO FAC likewise applies across mappings: his working gives Goods in Transit an Auto
 * FAC of 50,000,000, so the groups missing that layer get it.
 *
 * REVERSIBLE. Every change is recorded in the rollback block printed on APPLY. The
 * inserted formulas are listed by id so they can be deleted; the amended si_allocation
 * values are printed with their previous contents.
 *
 * NOTE ON THE COLUMN. si_allocation is TEXT holding comma-formatted strings —
 * "10,000,000", not 10000000. A plain (float) cast on "11,500,000" reads 11. Every read
 * goes through $money and every write through $asText.
 *
 * Credentials from the ENVIRONMENT, as treaty_year_config.php:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/apply_uniform_treaty_limit_2627.php        # dry run
 *   php backend/database/manual/apply_uniform_treaty_limit_2627.php APPLY  # execute
 */

const FIRST_LINE   = 10000000.0;   // treaty limit for EVERY mapping: 3m + 7m
const SURPLUS      = 40000000.0;   // Property and Engineering only
const AUTO_FAC     = 50000000.0;
const TREATY_CAP   = 100000000.0;  // Facultative Placement attaches here

const SURPLUS_MODEL  = 90;   // MATERIALDAMAGE...-SURPLUS-26-27
const AUTOFAC_MODEL  = 96;   // MATERIALDAMAGE...-FACULTATIVE-26-27

/** Mappings that run the surplus layer. Everything else stops at the first line. */
const SURPLUS_MAPPINGS = ['Property', 'Engineering'];

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
$money  = fn ($v) => (float) str_replace(',', '', (string) $v);
$asText = fn (float $v) => number_format($v, 0, '.', ',');

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user, $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo "mode     : " . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

/** Every 2026/27 group with its mapping, layers and current first line. */
$groups = $p->query("
    SELECT g.id AS group_id, g.group_code, gc.regulatory_mapping AS mapping,
           GROUP_CONCAT(DISTINCT UPPER(f.s_FormulaType)) AS layers
      FROM reinsurance_group g
      JOIN reinsurance_formula_details fd ON fd.group_id = g.id
      JOIN reinsurance_formula f          ON f.id = fd.formula_id
      LEFT JOIN (SELECT DISTINCT group_id, regulatory_mapping
                   FROM reinsurance_group_coverage) gc ON gc.group_id = g.id
     WHERE f.formula_code LIKE '%26-27'
     GROUP BY g.id, g.group_code, gc.regulatory_mapping
     ORDER BY gc.regulatory_mapping, g.group_code
")->fetchAll(PDO::FETCH_ASSOC);

$limitRows = $p->query("
    SELECT fd.id AS detail_id, fd.group_id, fd.si_allocation, fd.percentage
      FROM reinsurance_formula_details fd
      JOIN reinsurance_formula f ON f.id = fd.formula_id
     WHERE f.formula_code LIKE '%26-27' AND UPPER(f.s_FormulaType) = 'TSI'
")->fetchAll(PDO::FETCH_ASSOC);

$fixLimit = [];
foreach ($limitRows as $r) {
    if ($money($r['si_allocation']) !== FIRST_LINE) {
        $fixLimit[] = ['id' => (int) $r['detail_id'], 'was' => $r['si_allocation'], 'group' => (int) $r['group_id']];
    }
}

$addSurplus = $addAutoFac = [];
printf("%-28s %-15s %-34s %s\n", 'GROUP', 'MAPPING', 'LAYERS NOW', 'TO ADD');
printf("%s\n", str_repeat('-', 104));

foreach ($groups as $g) {
    $layers  = explode(',', (string) $g['layers']);
    $mapping = $g['mapping'] ?? '(unmapped)';
    $add     = [];

    $wantsSurplus = in_array($mapping, SURPLUS_MAPPINGS, true);
    if ($wantsSurplus && !in_array('SURPLUS', $layers, true)) {
        $addSurplus[] = $g;
        $add[] = 'SURPLUS 40,000,000';
    }
    if (!in_array('FACULTATIVE', $layers, true)) {
        $addAutoFac[] = $g;
        $add[] = 'AUTO FAC 50,000,000';
    }

    printf("%-28s %-15s %-34s %s\n", $g['group_code'], $mapping,
        implode(',', $layers), $add ? implode(' + ', $add) : '-');
}

echo str_repeat('-', 104) . "\n";
echo 'first lines to correct to 10,000,000 : ' . count($fixLimit) . "\n";
foreach ($fixLimit as $f) {
    $code = '';
    foreach ($groups as $g) {
        if ((int) $g['group_id'] === $f['group']) {
            $code = $g['group_code'];
        }
    }
    printf("    %-28s %14s -> 10,000,000\n", $code, $f['was']);
}
echo 'SURPLUS formulas to add              : ' . count($addSurplus) . "\n";
echo 'AUTO FAC formulas to add             : ' . count($addAutoFac) . "\n\n";

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$models = [];
foreach ([SURPLUS_MODEL, AUTOFAC_MODEL] as $id) {
    $models[$id] = $p->query("SELECT * FROM reinsurance_formula WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    if (!$models[$id]) {
        fwrite(STDERR, "ABORTED: model formula #$id not found.\n");
        exit(1);
    }
}

/** Which treaty a group's existing formulas hang off, so a new one joins the same. */
$treatyFor = function (int $groupId) use ($p): ?int {
    $t = $p->query("
        SELECT td.treaty_id
          FROM reinsurance_formula_details fd
          JOIN reinsurance_treaty_details td ON td.formula_attached = fd.formula_id
         WHERE fd.group_id = $groupId
         LIMIT 1
    ")->fetchColumn();

    return $t ? (int) $t : null;
};

$p->beginTransaction();
try {
    $rollback = [];

    // 1. One treaty first line for every mapping.
    $upd = $p->prepare('UPDATE reinsurance_formula_details SET si_allocation = ? WHERE id = ?');
    foreach ($fixLimit as $f) {
        $upd->execute([$asText(FIRST_LINE), $f['id']]);
        $rollback[] = "UPDATE reinsurance_formula_details SET si_allocation = '{$f['was']}' WHERE id = {$f['id']};";
    }

    // 2 & 3. The missing layers.
    $insF = $p->prepare(
        'INSERT INTO reinsurance_formula
            (product_id, type_id, formula_code, formula_name, s_FormulaType, reinsurance_type_id, status, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,NOW(),NOW())'
    );
    $insD = $p->prepare(
        'INSERT INTO reinsurance_formula_details (formula_id, group_id, operator, si_allocation, created_at, updated_at)
         VALUES (?,?,?,?,NOW(),NOW())'
    );
    $insT = $p->prepare(
        'INSERT INTO reinsurance_treaty_details (treaty_id, formula_attached, created_at, updated_at)
         VALUES (?,?,NOW(),NOW())'
    );

    $add = function (array $g, int $modelId, string $suffix, float $amount, int $operator)
           use ($p, $models, $insF, $insD, $insT, $treatyFor, $asText, &$rollback) {
        $m     = $models[$modelId];
        $code  = str_replace('_', '', $g['group_code']) . "-{$suffix}-26-27";
        $insF->execute([
            $m['product_id'], $m['type_id'], $code, $code,
            $m['s_FormulaType'], $m['reinsurance_type_id'], $m['status'],
        ]);
        $fid = (int) $p->lastInsertId();
        $insD->execute([$fid, (int) $g['group_id'], $operator, $asText($amount)]);

        $treaty = $treatyFor((int) $g['group_id']);
        if (!$treaty) {
            throw new RuntimeException("no treaty found for {$g['group_code']}");
        }
        $insT->execute([$treaty, $fid]);

        $rollback[] = "DELETE FROM reinsurance_treaty_details WHERE formula_attached = $fid;";
        $rollback[] = "DELETE FROM reinsurance_formula_details WHERE formula_id = $fid;";
        $rollback[] = "DELETE FROM reinsurance_formula WHERE id = $fid;";
        echo "  added #$fid  $code\n";

        return $fid;
    };

    echo "SURPLUS:\n";
    foreach ($addSurplus as $g) {
        $add($g, SURPLUS_MODEL, 'SURPLUS', SURPLUS, 4);
    }
    echo "AUTO FAC:\n";
    foreach ($addAutoFac as $g) {
        $add($g, AUTOFAC_MODEL, 'FACULTATIVE', AUTO_FAC, 9);
    }

    // Verify: no 2026/27 first line may remain on anything but 10,000,000.
    $left = 0;
    foreach ($p->query("
        SELECT fd.si_allocation FROM reinsurance_formula_details fd
          JOIN reinsurance_formula f ON f.id = fd.formula_id
         WHERE f.formula_code LIKE '%26-27' AND UPPER(f.s_FormulaType) = 'TSI'
    ") as $r) {
        if ($money($r['si_allocation']) !== FIRST_LINE) {
            $left++;
        }
    }
    if ($left > 0) {
        throw new RuntimeException("$left first line(s) still not 10,000,000");
    }

    $p->commit();
    echo "\nCOMMITTED.\n";
    echo 'first lines corrected : ' . count($fixLimit) . "\n";
    echo 'surplus added         : ' . count($addSurplus) . "\n";
    echo 'auto fac added        : ' . count($addAutoFac) . "\n\n";
    echo "ROLLBACK BLOCK — keep this:\n";
    foreach (array_reverse($rollback) as $sql) {
        echo "  $sql\n";
    }
    echo "\nNow recalculate COMG2026213751 and agree it against Tlamelo's working.\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
