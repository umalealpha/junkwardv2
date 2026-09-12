<?php

namespace Database\Seeders;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use AlphaDirect\SubCoverages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeds the master catalog of portable-item categories under the
 * BUSINESSALLRISKS parent coverage so BizSure's portable_items[] payload
 * can map each customer-typed item (cell phone / laptop / camera / power
 * tool / portable electronics / other) onto an existing
 * specified_coverage_items row.
 *
 * Idempotent — keyed on (coverage_id, specified_code). Existing rates are
 * preserved (firstOrCreate, not updateOrCreate).
 *
 * Run with: php artisan db:seed --class=BizSureBarSpecifiedItemsSeeder
 */
class BizSureBarSpecifiedItemsSeeder extends Seeder
{
    public function run(): void
    {
        $barCoverage = CoverageMaster::where('s_CoverageCode', 'BUSINESSALLRISKS')->first();
        if (! $barCoverage) {
            $this->command->error(
                'BizSureBarSpecifiedItemsSeeder: BUSINESSALLRISKS coverage row missing in tb_cvgpccoverages — '
                . 'cannot seed portable item catalog.'
            );
            return;
        }

        // Best-effort lookup for the "LIST OF ITEMS" sub-cover so the admin
        // SpecifiedCoveragesItems datatable groups these new rows under the
        // same heading manual entries use. Optional — if absent (or the
        // sub_coverages table itself isn't present in this environment),
        // the catalog rows still resolve via coverage_id alone in the UI.
        $listOfItemsSubCoverId = null;
        try {
            $listOfItemsSubCoverId = SubCoverages::where('name', 'LIST OF ITEMS')
                ->orWhere('name', 'List Of Items')
                ->orWhere('name', 'List of Items')
                ->value('id');
        } catch (\Throwable $e) {
            Log::info('[BizSureBarSpecifiedItemsSeeder] sub_coverages lookup skipped: ' . $e->getMessage());
        }

        $items = [
            ['code' => 'CELL_PHONE',           'name' => 'Cell phone'],
            ['code' => 'LAPTOP',               'name' => 'Laptop'],
            ['code' => 'CAMERA',               'name' => 'Camera'],
            ['code' => 'POWER_TOOL',           'name' => 'Power tool'],
            ['code' => 'PORTABLE_ELECTRONICS', 'name' => 'Portable electronics'],
            ['code' => 'OTHER',                'name' => 'Other portable item'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            try {
                $attributes = [
                    'coverage_id'    => $barCoverage->id,
                    'specified_code' => $item['code'],
                ];

                $defaults = [
                    'specified_name'  => $item['name'],
                    // Rate is stored as a PERCENT to match Graphite's existing
                    // convention (premium = SI * rate / 100). 5 → 5% of SI.
                    'rate'            => 5,
                    'sub_coverage_id' => $listOfItemsSubCoverId,
                    'effective_from'  => '2026-01-01',
                    'effective_to'    => '2099-12-31',
                ];

                $existing = SpecifiedCoveragesItems::where($attributes)->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                SpecifiedCoveragesItems::create(array_merge($attributes, $defaults));
                $created++;
            } catch (\Throwable $e) {
                Log::warning('[BizSureBarSpecifiedItemsSeeder] Failed to insert catalog row', [
                    'code'  => $item['code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->command->info(sprintf(
            'BizSure BAR portable-item catalog — created: %d, skipped (already present): %d',
            $created,
            $skipped
        ));
    }
}
