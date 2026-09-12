<?php

namespace Database\Seeders;

use AlphaDirect\Models\CoverageMaster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Ensures the 14 BizSure SME cover codes exist in tb_cvgpccoverages so
 * BizSure's /createPolicy payload can resolve each cover_code to a
 * coverage_id. Idempotent — existing rows (matched by s_CoverageCode)
 * are left untouched.
 */
class BizSureCoverageMasterSeeder extends Seeder
{
    public function run(): void
    {
        $covers = [
            ['code' => 'building',                  'name' => 'Building (Fire & Allied Perils)'],
            ['code' => 'contents',                  'name' => 'Fixtures & Fittings'],
            ['code' => 'stock',                     'name' => 'Stock & Inventory'],
            ['code' => 'burglary',                  'name' => 'Burglary & Theft'],
            ['code' => 'machinery_breakdown',       'name' => 'Machinery Breakdown'],
            ['code' => 'electronics_all_risk',      'name' => 'Electronics All Risk'],
            ['code' => 'refrigeration_breakdown',   'name' => 'Refrigeration Breakdown'],
            ['code' => 'money',                     'name' => 'Money'],
            ['code' => 'public_liability',          'name' => 'Public Liability'],
            ['code' => 'employers_liability',       'name' => 'Employers Liability'],
            ['code' => 'business_interruption',     'name' => 'Business Interruption'],
            ['code' => 'liquor_liability',          'name' => 'Liquor Liability'],
            ['code' => 'motor_vehicle',             'name' => 'Commercial Motor Vehicle'],
            ['code' => 'professional_indemnity',    'name' => 'Professional Indemnity'],
        ];

        $sequence  = 1;
        $created   = 0;
        $skipped   = 0;

        foreach ($covers as $cover) {
            try {
                $existing = CoverageMaster::where('s_CoverageCode', $cover['code'])->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                $row = new CoverageMaster();
                $row->s_CoverageCode        = $cover['code'];
                $row->s_ScreenName          = $cover['name'];
                $row->s_UsageType           = 'PARENT';
                $row->s_CoverageGroupCode   = 'MAIN';
                $row->s_DISPLAYTOUSER       = '1';
                $row->n_DisplaySequence     = $sequence++;
                $row->save();

                $created++;
            } catch (\Throwable $e) {
                Log::warning('[BizSureCoverageMasterSeeder] Failed to insert cover', [
                    'code'  => $cover['code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->command->info(sprintf(
            'BizSure coverage codes — created: %d, skipped (already present): %d',
            $created,
            $skipped
        ));
    }
}
