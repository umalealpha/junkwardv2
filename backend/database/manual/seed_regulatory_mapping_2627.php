<?php
/**
 * Seeds reinsurance_group_coverage.regulatory_mapping from the mapping annexed by
 * Tlamelo Chimidza on 17 August 2026 — config only, no schema change.
 *
 * SOURCE: D:\reinsurence_25-26\regulatory_mapping.xlsx, sheet 'in', 23 rows, keyed
 *   on the pair (s_CoverageCode, group_code).
 *
 * WHY THIS MATTERS. From 2026/27 the treaty allocates on the regulatory mapping, not
 * on the coverage or the group: "We use regulatory mapping for treaties instead of
 * the coverages." The mapping decides the treaty route AND whether a sum insured
 * test applies, so a wrong or missing value does not merely mis-label a row, it
 * changes what cedes. See RegulatoryCessionCalculator and RI-10.
 *
 * ANSWERED BY THE RI-10 REVIEW, returned 24 August 2026, and applied here:
 *
 *   Point 4  — "In essence we have Motor Traders External and Motor Traders
 *              Internal which should all be under property. The grouping for
 *              MOTOR_TRADERS_COM is just a consolidation of the two. In that
 *              regard we should only have the two." The single MOTOR_TRADERS_COM
 *              row is therefore replaced by MOTOR_TRADERS_COM_EXT and
 *              MOTOR_TRADERS_COM_INT, both Property.
 *   Point 12 — "Contractors all risk should be mapped to Property and Engineering
 *              encompasses Plant All Risk, Machinery Breakdown and Assets All
 *              Risk." Recorded in $AWAITING_GROUP below, because none of those
 *              coverages sits in a reinsurance group yet.
 *   Point 14 — Accident and Liability are intentionally unplaced. Already seeded,
 *              which is what lets the calculator raise a deliberate exception.
 *   Point 15 — "Personal Accident maps to liability and personal All risk maps to
 *              property." Confirmed as annexed; both rows stand as they are.
 *
 * WHAT IS STILL DELIBERATELY NOT SEEDED. Guessing any of these would bake an
 * unconfirmed rule into live cession. Each is reported by this script rather than
 * written:
 *
 *   1. ERECTIONALLRISKS has no mapping. Point 12 named Contractors All Risks but
 *      not Erection All Risks, and Schedule A groups the two together under the
 *      Engineering combined limit. Splitting one from the other needs an answer.
 *      RI-11 open question 3.
 *   2. "Assets All Risk" is named in the point 12 answer but is not a coverage
 *      code in this system. The nearest codes are PLANTALLRISKS, MACHINERYBREAKDOWN
 *      and CONTRACTORSALLRISKS. The intended coverage has to be named before it
 *      can be mapped.
 *   3. Passenger Liability has a 2,500,000 Schedule A limit and, confirmed on
 *      24 August, a regulatory mapping of Liability — which is 100% retained, so
 *      the stated capacity is never used. It still has no coverage and no group;
 *      premium_passenger_liability exists only as a field inside motor. The
 *      coverage and group must be created before the mapping can be written.
 *   4. Ten of the 23 rows are DOMESTIC groups. Confirmed on 24 August that domestic
 *      business SHOULD cede under the treaty its mapping points to, but no 2026/27
 *      treaty is configured for product 8, so every one still resolves to 100%
 *      retained. The mapping is seeded; the treaty attachment is the missing half.
 *      RI-11 open question 4.
 *   5. MOTOR_TRAILERS_COM carries coverage MOTORTRADERSEXTERNAL in the annexed
 *      sheet. Point 4 settled the CLASS (Motor Traders is Property; a trailer is
 *      Motor), but the coverage code on the trailer group still looks like a
 *      copy-paste from the row above. The row is seeded as annexed and flagged.
 *
 * Accident and Liability ARE seeded even though neither carries a treaty route.
 * Recording them is what lets the calculator raise a deliberate exception instead of
 * silently finding nothing, which is the behaviour RI-TRTY-WP-01 asks for.
 *
 * USAGE — dry run first, always:
 *   php artisan tinker --execute="require 'database/manual/seed_regulatory_mapping_2627.php';"
 *   php artisan tinker --execute="\$APPLY=true; require 'database/manual/seed_regulatory_mapping_2627.php';"
 *
 * Idempotent: re-running writes nothing once the values match.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$APPLY = isset($APPLY) && $APPLY === true;

/**
 * [s_CoverageCode, group_code, regulatory_mapping] as annexed on 17 August 2026,
 * amended by the review returned 24 August 2026. Amendments are marked.
 */
$MAPPING = [
    ['ACCIDENTALDAMAGE',     'ACCIDENTAL_DAMAGE_COM',                      'Property'],
    ['ELECTRONICEQUIPMENT',  'ELECTRONIC_EQ_AND_BI_COM',                   'Property'],
    ['COMPUTEREQUIPMENT',    'ELECTRONICEQANDBI_DOM',                      'Property'],
    ['FIDELITYGUARANTEE',    'FIDELITYG_COM',                              'Guarantee'],
    ['GOODSINTRANSIT',       'GOODSINTRANSIT_COM',                         'Transportation'],
    ['STATEDBENEFITS',       'GROUPPERSONALACCIDENT_COM',                  'Accident'],
    ['OFFICECONTENTS',       'MISC_COM',                                   'Miscellaneous'],
    ['OFFICECONTENTS',       'MISCANDFG_COM',                              'Miscellaneous'],
    ['PERSONALALLRISKS',     'MISCANDFG_DOM',                              'Property'],
    // Point 4, 24 Aug 2026: MOTOR_TRADERS_COM was only ever a consolidation of the
    // two groups the system actually holds. Both are Property.
    ['MOTORTRADERSEXTERNAL', 'MOTOR_TRADERS_COM_EXT',                      'Property'],
    ['MOTORTRADERSINTERNAL', 'MOTOR_TRADERS_COM_INT',                      'Property'],
    ['COMMERCIALMOTOR',      'MOTOR_COM',                                  'Motor'],
    ['PERSONALMOTOR',        'MOTOR_DOM',                                  'Motor'],
    ['PUBLICLIABILITY',      'PRODUCTSLIABILITY_COM',                      'Liability'],
    ['PUBLICLIABILITY',      'PROFESSIONALINDEMNITYINSURANCE_COM',         'Liability'],
    ['FIRE',                 'PROPERTYANDBI_COM',                          'Property'],
    ['HOUSEOWNER-BUILDINGS', 'PROPERTYANDBI_DOM',                          'Property'],
    ['PUBLICLIABILITY',      'PUBLICLIABANDDEFECTIVEWORKMAN_COM',          'Liability'],
    ['PERSONALACCIDENT',     'PUBLICLIABANDDEFECTIVEWORKMAN_DOM',          'Liability'],
    ['PUBLICLIABILITY',      'PUBLICLIABANDDEFECTIVEWORKMANBODYCORP_COM',  'Liability'],
    ['WORKERSCOMPENSATION',  'WC_COM',                                     'Accident'],
    ['WORKERSCOMPENSATION',  'WC_DOM',                                     'Accident'],
    // Flagged, not amended: the CLASS is right — a trailer is Motor — but the
    // coverage code reads like a copy-paste from the Motor Traders row above.
    ['MOTORTRADERSEXTERNAL', 'MOTOR_TRAILERS_COM',                         'Motor'],
    ['PERSONALMOTOR',        'MOTOR_TRAILERS_DOM',                         'Motor'],
];

/**
 * Confirmed mappings that CANNOT be written yet, because the coverage sits in no
 * reinsurance group and the mapping is keyed on the (coverage, group) pair.
 *
 * These are recorded here rather than left in a document so the instruction is
 * held with the code that will apply it. The script reports them; it writes
 * nothing. Creating the group membership is a reinsurance-master task.
 *
 * [s_CoverageCode, intended regulatory_mapping, authority, what is missing]
 */
$AWAITING_GROUP = [
    ['CONTRACTORSALLRISKS', 'Property',    'RI-10 point 12, 24 Aug 2026', 'sits in no reinsurance group'],
    ['PLANTALLRISKS',       'Engineering', 'RI-10 point 12, 24 Aug 2026', 'sits in no reinsurance group; ENGINEERING_AND_BI_COM is empty'],
    ['MACHINERYBREAKDOWN',  'Engineering', 'RI-10 point 12, 24 Aug 2026', 'sits in no reinsurance group; ENGINEERING_AND_BI_COM is empty'],
    // Answered 31 August 2026: "Erection All Risk should be classed under
    // Engineering just as the final terms have combined them with Contractors All
    // Risk." Both classes are LAYERED and run the identical cascade, so no cession
    // figure turns on this — only the class it reports under.
    ['ERECTIONALLRISKS',    'Engineering', 'Reinsurance, 31 Aug 2026', 'sits in no reinsurance group; ENGINEERING_AND_BI_COM is empty'],
    // Answered 31 August 2026: "Assets All Risk should be under the same coverage
    // as Plant All Risk." Taken as the same treatment, hence Engineering. If a
    // distinct ASSETSALLRISKS coverage code is created later it takes this row.
    ['ASSETSALLRISKS',      'Engineering', 'Reinsurance, 31 Aug 2026', 'no such coverage code yet; treated as Plant All Risks'],
];

/**
 * Named in an answer but not yet actionable. Reported so they cannot be forgotten
 * between one treaty year and the next.
 *
 * [what, why it cannot be applied]
 */
$UNACTIONABLE = [
    // Erection All Risks and Assets All Risks were answered on 31 August 2026 and
    // have moved to AWAITING_GROUP above. What remains open is narrower:
    ['Passenger Liability -> Liability', 'Confirmed 24 Aug 2026. No coverage and no group exist; premium_passenger_liability is a field inside motor. Create both, then map. Note the 2,500,000 Schedule A capacity is unused once it is Liability.'],
];

$line = str_repeat('-', 96);
echo $line . PHP_EOL;
echo ($APPLY ? 'APPLYING' : 'DRY RUN') . ' — regulatory mapping 2026/27' . PHP_EOL;
echo $line . PHP_EOL;

if (! Schema::hasColumn('reinsurance_group_coverage', 'regulatory_mapping')) {
    echo 'ABORT: reinsurance_group_coverage.regulatory_mapping does not exist. Run migration'
       . ' 2026_08_19_000001_add_regulatory_mapping_to_reinsurance_group_coverage first.' . PHP_EOL;
    return;
}

$written = $already = $unresolved = 0;
$conflicts = [];

foreach ($MAPPING as [$coverageCode, $groupCode, $mapping]) {
    // group_code lives on reinsurance_group; the coverage code on the child row.
    $targets = DB::table('reinsurance_group_coverage as gd')
        ->join('reinsurance_group as gm', 'gm.id', '=', 'gd.group_id')
        ->where('gm.group_code', $groupCode)
        ->where('gd.coverage_name', $coverageCode)
        ->select('gd.id', 'gd.regulatory_mapping')
        ->get();

    if ($targets->isEmpty()) {
        $unresolved++;
        printf("  UNRESOLVED  %-22s in %-42s -> %s%s", $coverageCode, $groupCode, $mapping, PHP_EOL);
        continue;
    }

    foreach ($targets as $t) {
        if ($t->regulatory_mapping === $mapping) {
            $already++;
            continue;
        }

        // Never overwrite a different agreed value silently — report it and move on.
        if ($t->regulatory_mapping !== null && $t->regulatory_mapping !== '') {
            $conflicts[] = "  CONFLICT    {$coverageCode} in {$groupCode}: holds '{$t->regulatory_mapping}', sheet says '{$mapping}' — left unchanged";
            continue;
        }

        printf("  %-11s %-22s in %-42s -> %s%s", $APPLY ? 'WRITE' : 'WOULD WRITE', $coverageCode, $groupCode, $mapping, PHP_EOL);

        if ($APPLY) {
            DB::table('reinsurance_group_coverage')->where('id', $t->id)->update(['regulatory_mapping' => $mapping]);
        }
        $written++;
    }
}

foreach ($conflicts as $c) {
    echo $c . PHP_EOL;
}

echo $line . PHP_EOL;
printf('%s: %d, already correct: %d, unresolved: %d, conflicts: %d%s',
    $APPLY ? 'written' : 'would write', $written, $already, $unresolved, count($conflicts), PHP_EOL);

// Confirmed instructions that have nowhere to land yet. These are not failures of
// this script — they are reinsurance-master work it cannot do for itself.
echo $line . PHP_EOL;
echo 'CONFIRMED MAPPINGS AWAITING A REINSURANCE GROUP: ' . count($AWAITING_GROUP) . PHP_EOL;
foreach ($AWAITING_GROUP as [$coverage, $mapping, $authority, $missing]) {
    printf('  %-22s -> %-12s %s%s', $coverage, $mapping, $missing, PHP_EOL);
    printf('  %-22s    authority: %s%s', '', $authority, PHP_EOL);
}

// A group whose coverages already exist is worth checking against the list above,
// because the moment one is created this script becomes able to write it.
$engineering = DB::table('reinsurance_group as gm')
    ->leftJoin('reinsurance_group_coverage as gd', 'gm.id', '=', 'gd.group_id')
    ->where('gm.group_code', 'ENGINEERING_AND_BI_COM')
    ->count('gd.id');
printf('  ENGINEERING_AND_BI_COM currently holds %d coverage(s).%s', $engineering, PHP_EOL);

echo $line . PHP_EOL;
echo 'NAMED IN AN ANSWER BUT NOT YET ACTIONABLE: ' . count($UNACTIONABLE) . PHP_EOL;
foreach ($UNACTIONABLE as [$what, $why]) {
    printf('  %s%s    %s%s', $what, PHP_EOL, $why, PHP_EOL);
}

// Anything still NULL after seeding is 100% retained and raised as an exception, so
// it has to be visible rather than discovered on a month-end cession report.
$gaps = DB::table('reinsurance_group_coverage as gd')
    ->join('reinsurance_group as gm', 'gm.id', '=', 'gd.group_id')
    ->whereNull('gd.regulatory_mapping')
    ->where('gm.status', 1)
    ->select('gm.group_code', 'gd.coverage_name')
    ->orderBy('gm.group_code')
    ->get();

echo $line . PHP_EOL;
echo 'ACTIVE GROUP-COVERAGES STILL WITHOUT A MAPPING (each is 100% retained + exception): '
   . $gaps->count() . PHP_EOL;
foreach ($gaps as $g) {
    printf('  %-42s %s%s', $g->group_code, $g->coverage_name, PHP_EOL);
}

// A group whose coverages disagree cannot be aggregated to one treaty row.
$split = DB::table('reinsurance_group_coverage as gd')
    ->join('reinsurance_group as gm', 'gm.id', '=', 'gd.group_id')
    ->whereNotNull('gd.regulatory_mapping')
    ->groupBy('gm.group_code')
    ->havingRaw('COUNT(DISTINCT gd.regulatory_mapping) > 1')
    ->select('gm.group_code', DB::raw('GROUP_CONCAT(DISTINCT gd.regulatory_mapping) as mappings'))
    ->get();

echo $line . PHP_EOL;
echo 'GROUPS WHOSE COVERAGES CARRY MORE THAN ONE MAPPING: ' . $split->count() . PHP_EOL;
foreach ($split as $s) {
    printf('  %-42s %s%s', $s->group_code, $s->mappings, PHP_EOL);
}
echo $line . PHP_EOL;
