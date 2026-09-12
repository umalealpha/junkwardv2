<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New specialist products/coverages, following the exact pattern Engineering
 * (id=16) uses for CAR/PAR/EAR/Machinery Breakdown: each coverage is a
 * PARENT row in tb_cvgpccoverages, linked to its product via the
 * product_coverage pivot. No dedicated "<name>_coverages" detail table is
 * created here (that only exists for products with policy-create/rating
 * support — explicitly out of scope for this change; see car_coverages /
 * par_coverages / ear_coverages for that next step when it's needed).
 *
 * - New product "Guarantee" -> coverage "Bonds and Guarantees"
 * - New product "Miscellaneous" -> coverages "Medical Evacuation", "Commercial Crime"
 * - "Environmental Liability" coverage added to the EXISTING "Commercial
 *   Liabilities" product (id=20) rather than a new "Liability" product,
 *   since that product already exists with type=LIABILITY and a second
 *   product of the same type would be confusing in the product list.
 *
 * Idempotent — guarded by name lookups so reruns don't create duplicates.
 */
return new class extends Migration
{
    private const PRODUCT_TEMPLATE = [
        'premium_type_id' => 11,
        'sum_insured' => 200000,
        'billing_cycle' => 'Yearly',
        'region_id' => 7, // Botswana
        'product_type_id' => 1, // matches Engineering/Commercial Liabilities/Marine precedent
        'has_vehicle' => 0,
        'has_member' => 0,
        'has_wordings' => 0,
        'has_schedule' => 0,
        'isForStart' => 0,
        'preinspection' => '0',
        'limit' => null,
        'image' => null,
        'kyc_customer' => '0',
        'kyc_recipient' => '0',
        'has_activation_code' => '0',
        'is_motor_items' => '0',
        'has_subApplicant' => 0,
        'formula' => null,
        'policy_initials' => null,
        'kyc_compliance' => null,
        'status' => 1,
        'line_of_business' => 'ENGINEERING', // matches Engineering/Commercial Liabilities/Marine
        'is_grouped' => 0,
        'added_by' => null,
    ];

    private const COVERAGE_TEMPLATE = [
        's_GroupRowType' => 'COVERAGE',
        's_RatingMethod' => 'PERCENT',
        's_UsageType' => 'PARENT',
        's_CoveragePart' => 'PROPERTY',
        's_CoverageSection' => 'MAIN',
        's_CoverageGroupCode' => 'MAIN',
        's_CoverageGroupName' => 'Main',
        's_ParentCoverageCode' => null,
        's_ParentCoverageID' => null,
        'n_ParentCoverageForRate' => null,
        's_DefaultCovgCategoryCode' => 'ENDCOVG',
        's_DISPLAYTOUSER' => '1',
        's_SubCoverageMainName' => null,
        'n_PrintSequence' => 40,
        'n_DisplaySequence' => 40,
        'n_RateSequence' => 40,
        's_AutoRenew' => 'Y',
        's_PermitDuplication' => 'N',
        's_isAutoSelected' => 'No',
        's_CvgOccurrence' => 'SINGLE',
        'n_CreatedUser' => 0,
        'n_UpdatedUser' => null,
        'n_EditVersion' => null,
        'isDocDisplay' => 'Y',
        'has_risk_address' => null,
        // Unlike CAR/PAR/EAR (which need vehicle/member/device capture for
        // construction equipment/personnel), these coverages don't, so the
        // policy-schedule UI shouldn't show those steps for them.
        'has_vehicle' => 0,
        'has_member' => 0,
        'has_device' => 0,
        'has_company' => null,
        'rate' => '10.00', // placeholder, matches existing specialist coverage rows; no rating implementation at this stage
        'default_coverage_value' => null,
    ];

    public function up(): void
    {
        $now = now();
        $effective = $now->copy()->startOfDay();
        $expiry = $effective->copy()->addYear();

        $guaranteeProductId = $this->ensureProduct('Guarantee', 'GUARANTEE');
        $miscProductId = $this->ensureProduct('Miscellaneous', 'MISCELLANEOUS');
        $commercialLiabilitiesProductId = DB::table('products')->where('name', 'Commercial Liabilities')->value('id');

        $coverages = [
            ['name' => 'Bonds and Guarantees', 'product_id' => $guaranteeProductId],
            ['name' => 'Medical Evacuation', 'product_id' => $miscProductId],
            ['name' => 'Commercial Crime', 'product_id' => $miscProductId],
            ['name' => 'Environmental Liability', 'product_id' => $commercialLiabilitiesProductId],
        ];

        foreach ($coverages as $coverage) {
            if ($coverage['product_id'] === null) {
                continue; // Commercial Liabilities not found — leave for manual follow-up rather than guessing
            }
            $this->ensureCoverage($coverage['name'], $coverage['product_id'], $effective, $expiry, $now);
        }
    }

    public function down(): void
    {
        $coverageNames = ['Bonds and Guarantees', 'Medical Evacuation', 'Commercial Crime', 'Environmental Liability'];
        $coverageIds = DB::table('tb_cvgpccoverages')->whereIn('s_ScreenName', $coverageNames)->pluck('id');

        DB::table('product_coverage')->whereIn('coverage_id', $coverageIds)->delete();
        DB::table('tb_cvgpccoverages')->whereIn('id', $coverageIds)->delete();

        DB::table('products')->whereIn('name', ['Guarantee', 'Miscellaneous'])->delete();
    }

    private function ensureProduct(string $name, string $type): int
    {
        $existing = DB::table('products')->where('name', $name)->value('id');
        if ($existing !== null) {
            return $existing;
        }

        return DB::table('products')->insertGetId(array_merge(self::PRODUCT_TEMPLATE, [
            'name' => $name,
            'type' => $type,
            'slug' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function ensureCoverage(string $name, int $productId, $effective, $expiry, $now): void
    {
        $coverageId = DB::table('tb_cvgpccoverages')->where('s_ScreenName', $name)->value('id');

        if ($coverageId === null) {
            $coverageId = DB::table('tb_cvgpccoverages')->insertGetId(array_merge(self::COVERAGE_TEMPLATE, [
                's_CoverageCode' => strtoupper(str_replace(' ', '', $name)),
                's_ScreenName' => $name,
                's_CoverageName' => $name,
                's_CoverageDesc' => $name,
                'd_EffectiveDt' => $effective->format('Y-m-d'),
                'd_ExpirationDt' => $expiry->format('Y-m-d'),
                'd_CreatedDate' => $now,
                'd_UpdatedDate' => $now,
                'created_at' => null,
                'updated_at' => null,
            ]));
        }

        $alreadyMapped = DB::table('product_coverage')
            ->where('product_id', $productId)
            ->where('coverage_id', $coverageId)
            ->exists();

        if (!$alreadyMapped) {
            DB::table('product_coverage')->insert([
                'product_id' => $productId,
                'coverage_id' => $coverageId,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => null,
            ]);
        }
    }
};
