<?php
/**
 * Populates reinsurance_group_coverage.regulatory_mapping for 2026/27, BY GROUP.
 *
 * WHY BY GROUP AND NOT BY COVERAGE. seed_regulatory_mapping_2627.php maps by coverage
 * code — CONTRACTORSALLRISKS, MOTORTRADERSEXTERNAL and so on. That is the wrong grain
 * for this table. reinsurance_group_coverage holds SUB-COVERAGES, not coverages: the
 * engine joins gd.coverage_name to tb_cvgpccoverages.s_CoverageCode and then matches
 * that id against policy_coverage_detail, which is the sub-coverage level. So
 * PROPERTYANDBI_COM legitimately holds BUILDINGS, STOCK, PLANT AND MACHINERY, RENT and
 * CONTENTS, and holds no parent FIRE row at all — 18 of the 27 populated groups have no
 * parent coverage, and that is the design rather than a gap.
 *
 * Mapping by coverage code would therefore have populated only the 28 motor and
 * engineering rows and left Property's 59 sub-coverage rows unmapped — Property being
 * the class carrying 201,641,212 of the 687,704,212 on COMG2026213751.
 *
 * The regulatory class is a property of the GROUP, not of the sub-coverage. Every row
 * in PROPERTYANDBI_COM is Property whether it is BUILDINGS or RENT.
 *
 * SOURCES. The regulatory mapping annexed 17 August 2026, as amended by Tlamelo
 * Chimidza's replies of 24 and 25 August:
 *   · point 4  — Motor Traders External and Internal both map to Property
 *   · point 5  — Passenger Liability maps to Liability, which is 100% retained
 *   · point 12 — Contractors All Risks to Property; Engineering covers Plant All Risks,
 *                Machinery Breakdown and Assets All Risks
 *   · point 13 — domestic cedes on the same basis as commercial, so _DOM groups take
 *                the same class as their _COM counterparts
 *   · point 14 — Accident and Liability are intentionally unplaced, 100% retained
 *   · point 15 — Personal Accident to Liability, Personal All Risks to Property
 *
 * REVERSIBLE IN ONE STATEMENT, so no backup table is taken:
 *   UPDATE reinsurance_group_coverage SET regulatory_mapping = NULL;
 *
 * Credentials from the ENVIRONMENT, matching treaty_year_config.php — never .env,
 * which has pointed at production before now:
 *   RI_DB_HOST  RI_DB_NAME  RI_DB_USER  RI_DB_PASS   (RI_DB_PORT optional, 3306)
 *
 *   php backend/database/manual/seed_regulatory_mapping_by_group_2627.php        # dry run
 *   php backend/database/manual/seed_regulatory_mapping_by_group_2627.php APPLY  # execute
 */

/*
 * group_code => regulatory mapping.
 *
 * The four CAPPED classes cede 30/70 within their Schedule A class limit: Motor,
 * Transportation, Miscellaneous, Guarantee. Property and Engineering are LAYERED and
 * run the full cascade. Accident and Liability do not cede at all.
 */
$MAP = [
    // ── Property. Layered: 3m / 7m / 40m surplus / 50m Auto FAC / FAC ──────
    'PROPERTYANDBI_COM'                         => 'Property',
    'PROPERTYANDBI_DOM'                         => 'Property',
    // Schedule A gives Electronic Equipment and Accidental Damage their own limits,
    // but the annexed mapping routes both to the Property class.
    'ELECTRONIC_EQ_AND_BI_COM'                  => 'Property',
    'ELECTRONICEQANDBI_DOM'                     => 'Property',
    'ACCIDENTAL_DAMAGE_COM'                     => 'Property',
    // Point 4. Both traders groups are Property, NOT Motor, despite the name.
    'MOTOR_TRADERS_COM_EXT'                     => 'Property',
    'MOTOR_TRADERS_COM_INT'                     => 'Property',

    // ── Engineering. Layered, same cascade as Property ────────────────────
    'ENGINEERING_AND_BI_COM'                    => 'Engineering',

    // ── Capped classes ───────────────────────────────────────────────────
    'MOTOR_COM'                                 => 'Motor',           // limit 5,000,000
    'MOTOR_DOM'                                 => 'Motor',
    'MOTOR_TRAILERS_COM'                        => 'Motor',           // limit 1,500,000
    'MOTOR_TRAILERS_DOM'                        => 'Motor',
    'GOODSINTRANSIT_COM'                        => 'Transportation',  // limit 3,000,000
    'MISC_COM'                                  => 'Miscellaneous',   // limit 1,000,000
    'MISCANDFG_COM'                             => 'Miscellaneous',
    'MISCANDFG_DOM'                             => 'Miscellaneous',
    'FIDELITYG_COM'                             => 'Guarantee',       // limit 1,000,000

    // ── Do not cede. Point 14 — intentionally unplaced, 100% retained ────
    //
    // AMENDED 29 AUGUST 2026. Reinsurance: "Workers compensation, Public Liability
    // and Personal Accident are under the Accident regulatory mapping which is
    // 100% retained forming part of the uninsured component." That component is
    // covered under the NON-PROPORTIONAL treaties, which carry their own terms and
    // are outside this working entirely.
    //
    // This settles the second look the previous note asked for. No cession figure
    // moves — Accident and Liability are both 100% retained — but the class each
    // group reports under does, and that is what the regulatory return reads.
    'PUBLICLIABANDDEFECTIVEWORKMAN_COM'         => 'Accident',
    'PUBLICLIABANDDEFECTIVEWORKMAN_DOM'         => 'Accident',
    'PUBLICLIABANDDEFECTIVEWORKMANBODYCORP_COM' => 'Accident',
    'GROUPPERSONALACCIDENT_COM'                 => 'Accident',
    'GROUPPERSONALACCIDENT_DOM'                 => 'Accident',
    'WC_COM'                                    => 'Accident',
    'WC_DOM'                                    => 'Accident',

    // Not named in the 29 August reply, so they stay on Liability. Both are 100%
    // retained either way; do not move them without an instruction.
    'PRODUCTSLIABILITY_COM'                     => 'Liability',
    'PROFESSIONALINDEMNITYINSURANCE_COM'        => 'Liability',

    // ── Classed but held out of the treaty ──────────────────────
    //
    // Confirmed 29 August 2026: "Travel insurance does not make part of the treaty
    // and should be treated as 100% retained. And it should be classed under
    // Miscellaneous."
    //
    // The mapping alone is NOT enough here. Miscellaneous cedes 30/70 within its
    // class limit, so a travel risk carrying it would cede like any other. The
    // group is listed in config/reinsurance.php 'retained_groups', which is what
    // holds it out of the treaty; this line only decides what it reports under.
    'TRAVELINSURANCE_COM'                       => 'Miscellaneous',
    'TRAVELINSURANCE_DOM'                       => 'Miscellaneous',
];

/*
 * Deliberately NOT mapped, and each for a stated reason. Left null so they show up
 * as unmapped rather than being quietly given a class.
 */
$SKIP = [
    'AVIATION_COM'        => 'no Schedule A capacity; carries no rows',
    'BODY_CORPORATE_COM'  => 'no Schedule A capacity; carries no rows',
    'MOTOR_PER_ACCIDENT'  => 'carries no rows',
    'try_new_grp'         => 'test data — should be deleted, not mapped',
];

// ─────────────────────────────────────────────────────────────────────────
$host = getenv('RI_DB_HOST') ?: '';
$name = getenv('RI_DB_NAME') ?: '';
$user = getenv('RI_DB_USER') ?: '';
$pass = getenv('RI_DB_PASS');
$port = getenv('RI_DB_PORT') ?: '3306';

if ($host === '' || $name === '' || $user === '' || $pass === false) {
    fwrite(STDERR, "Set RI_DB_HOST, RI_DB_NAME, RI_DB_USER and RI_DB_PASS first.\n");
    exit(1);
}

$apply = ($argv[1] ?? '') === 'APPLY';

$p = new PDO(
    "mysql:host={$host};port={$port};dbname={$name}",
    $user, $pass,
    [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "host     : {$host}\n";
echo "database : {$name}\n";
echo "mode     : " . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

$groups = $p->query(
    'SELECT g.id, g.group_code, COUNT(gc.id) AS rows_
       FROM reinsurance_group g
       LEFT JOIN reinsurance_group_coverage gc ON gc.group_id = g.id
      GROUP BY g.id, g.group_code
      ORDER BY g.group_code'
)->fetchAll(PDO::FETCH_ASSOC);

/*
 * A group in the database that this script has never heard of is a STOP, not a
 * warning. Silently leaving it unmapped means its whole class quietly retains, and
 * nobody finds out until a cession is short.
 */
$unknown = [];
foreach ($groups as $g) {
    if (!isset($MAP[$g['group_code']]) && !isset($SKIP[$g['group_code']])) {
        $unknown[] = $g['group_code'] . ' (' . $g['rows_'] . ' rows)';
    }
}
if ($unknown) {
    fwrite(STDERR, "ABORTED — group(s) this script does not know:\n  "
        . implode("\n  ", $unknown)
        . "\nAdd them to \$MAP or \$SKIP with a reason. Nothing has been changed.\n");
    exit(1);
}

printf("%-44s %-16s %6s\n", 'GROUP', 'MAPPING', 'ROWS');
printf("%s\n", str_repeat('-', 68));

$plan = [];
$totalRows = 0;
foreach ($groups as $g) {
    $code = $g['group_code'];
    $rows = (int) $g['rows_'];

    if (isset($SKIP[$code])) {
        printf("%-44s %-16s %6d   -- skipped: %s\n", $code, '(none)', $rows, $SKIP[$code]);
        continue;
    }

    printf("%-44s %-16s %6d\n", $code, $MAP[$code], $rows);
    if ($rows > 0) {
        $plan[$code] = ['id' => (int) $g['id'], 'mapping' => $MAP[$code], 'rows' => $rows];
        $totalRows += $rows;
    }
}

echo str_repeat('-', 68) . "\n";
echo "groups to map : " . count($plan) . "\n";
echo "rows to set   : $totalRows\n\n";

if (!$apply) {
    echo "DRY RUN — nothing changed. Re-run with APPLY to execute.\n";
    exit;
}

$p->beginTransaction();
try {
    $st = $p->prepare(
        'UPDATE reinsurance_group_coverage SET regulatory_mapping = :m WHERE group_id = :g'
    );

    $written = 0;
    foreach ($plan as $code => $row) {
        $st->execute([':m' => $row['mapping'], ':g' => $row['id']]);
        $written += $st->rowCount();
    }

    $set   = (int) $p->query('SELECT COUNT(*) FROM reinsurance_group_coverage WHERE regulatory_mapping IS NOT NULL')->fetchColumn();
    $unset = (int) $p->query('SELECT COUNT(*) FROM reinsurance_group_coverage WHERE regulatory_mapping IS NULL')->fetchColumn();

    if ($set !== $totalRows) {
        throw new RuntimeException("$set rows carry a mapping, expected $totalRows");
    }

    $p->commit();
    echo "rows written  : $written\n";
    echo "rows mapped   : $set\n";
    echo "rows unmapped : $unset  (skipped groups only)\n\n";
    echo "COMMITTED.\n";
    echo "Revert with:  UPDATE reinsurance_group_coverage SET regulatory_mapping = NULL;\n";
} catch (Throwable $t) {
    $p->rollBack();
    fwrite(STDERR, "ROLLED BACK: {$t->getMessage()}\nNothing changed.\n");
    exit(1);
}
