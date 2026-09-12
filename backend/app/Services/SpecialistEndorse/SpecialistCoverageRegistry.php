<?php

namespace AlphaDirect\Services\SpecialistEndorse;

/**
 * Single source of truth for the 14 "specialist" coverage tables that
 * each carry their own premium columns (one row per policy_coverage) and
 * therefore need bespoke endorsement handling — separate from motor and
 * from COM/DOM. Every code path that fans out across these tables
 * (delete cascade, action replication, refresher CHILD_TABLES, pro-rata
 * writer) should read this registry instead of hard-coding its own list.
 *
 * NOT used by motor (motor / motor_traders / motor_traders_internal),
 * COM/DOM, or policy_coverage_detail. Those keep their existing handling
 * untouched.
 *
 * Each table entry has:
 *   premium_cols   the canonical annual premium column(s) on the row.
 *                  The pro-rata writer sums these per row when computing
 *                  pro_rate_premium = annual_value × factor.
 *   business_key   columns (in addition to policy_coverage_id) used to
 *                  match a row in a target action to its counterpart in
 *                  the source action during refresh / replicate. Empty
 *                  array means "one row per policy_coverage_id" — match
 *                  by parent pc alone (true for almost all specialist
 *                  tables; they're 1:1 with policy_coverage).
 *   product_code   coverage code label (CAR / EAR / etc.) — kept for
 *                  cross-referencing with the existing wording-files
 *                  table map in PolicyCreateController.
 */
class SpecialistCoverageRegistry
{
    /**
     * The canonical 14 specialist tables. premium_cols mirror the column
     * registry that PolicyCreateController already uses for the Rate /
     * Submit-validation paths (see PolicyCreateController around line
     * 2247 and 7767) so all paths report the same annual premium per row.
     */
    public const TABLES = [
        'car_coverages' => [
            'product_code' => 'CAR',
            'premium_cols' => ['section1_total_premium', 'section2_total_premium', 'section3_total_premium'],
            'business_key' => [],
        ],
        'par_coverages' => [
            'product_code' => 'PAR',
            'premium_cols' => ['total_premium', 'section2_total_premium'],
            'business_key' => [],
        ],
        'ear_coverages' => [
            'product_code' => 'EAR',
            'premium_cols' => ['total_premium', 'section1_total_premium', 'section3_total_premium'],
            'business_key' => [],
        ],
        'travel_coverages' => [
            'product_code' => 'TRAVEL',
            'premium_cols' => ['policy_amount'],
            'business_key' => [],
        ],
        'medical_malpractice_coverages' => [
            'product_code' => 'MEDICAL_MALPRACTICE',
            'premium_cols' => ['annual_premium', 'premium'],
            'business_key' => [],
        ],
        'machinery_breakdown_coverages' => [
            'product_code' => 'MACHINERY_BD',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
        'professional_indemnity_coverages' => [
            'product_code' => 'PROFESSIONAL_INDEMNITY',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
        'marine_directors_officers_coverages' => [
            'product_code' => 'MARINE_DO',
            'premium_cols' => ['premium', 'annual_premium'],
            'business_key' => [],
        ],
        'marine_cargo_once_off_coverages' => [
            'product_code' => 'MARINE_ONCEOFF',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
        'marine_cargo_open_coverages' => [
            'product_code' => 'MARINE_OPEN',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
        'medical_evacuation_coverages' => [
            'product_code' => 'MEDICAL_EVACUATION',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
        'commercial_crime_coverages' => [
            'product_code' => 'COMMERCIAL_CRIME',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
        'environmental_liability_coverages' => [
            'product_code' => 'ENVIRONMENTAL_LIABILITY',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
        'bonds_coverages' => [
            'product_code' => 'BONDS',
            'premium_cols' => ['premium'],
            'business_key' => [],
        ],
    ];

    /**
     * Tables whose premium_cols are DISTINCT additive sections that must ALL
     * be summed, vs ALTERNATIVES where the first present column is taken.
     * Only car_coverages is additive — it has no grand total_premium column,
     * so its annual = Section 1 + Section 2 + Section 3. ear/par lead with a
     * grand total_premium, and medical/D&O list annual_premium||premium, which
     * are alternatives. Mirrors PolicyCreateController::calculatePremium's
     * $sumAllColumns = ($table === 'car_coverages').
     */
    public const ADDITIVE_TABLES = ['car_coverages'];

    /** Plain list of table names (for IN clauses, foreach loops). */
    public static function tableNames(): array
    {
        return array_keys(self::TABLES);
    }

    /**
     * Annual premium columns for a given table, or [] if the table is
     * not a specialist table. Empty result tells the caller this table
     * is out-of-scope for specialist pro-rata.
     */
    public static function premiumColsFor(string $table): array
    {
        return self::TABLES[$table]['premium_cols'] ?? [];
    }

    /** Business-key columns for refresh row matching (in addition to pc id). */
    public static function businessKeyFor(string $table): array
    {
        return self::TABLES[$table]['business_key'] ?? [];
    }

    /** True iff $table is one of the 14 specialist tables. */
    public static function isSpecialistTable(string $table): bool
    {
        return isset(self::TABLES[$table]);
    }

    /**
     * True iff $table's premium_cols are summed (additive sections) rather
     * than treated as alternatives (first present wins). See ADDITIVE_TABLES.
     */
    public static function isAdditive(string $table): bool
    {
        return in_array($table, self::ADDITIVE_TABLES, true);
    }

    /**
     * Build the CHILD_TABLES-shaped fragment that BackdatedEndorseRefresher
     * expects, so we can splice it into the refresher without duplicating
     * the column registry inside that file.
     */
    public static function refresherChildTables(): array
    {
        $out = [];
        foreach (self::TABLES as $table => $meta) {
            $out[$table] = [
                'business_key' => $meta['business_key'],
                'value_fields' => $meta['premium_cols'],
            ];
        }
        return $out;
    }
}
