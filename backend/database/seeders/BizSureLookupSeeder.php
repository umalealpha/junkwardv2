<?php

namespace Database\Seeders;

use AlphaDirect\Lookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeds lookup_data rows that BizSure needs to populate dropdowns and
 * validate risk_address values. Idempotent — each (key, value) pair is
 * inserted only if not already present.
 */
class BizSureLookupSeeder extends Seeder
{
    public function run(): void
    {
        $lookupData = [
            'risk_occupation' => [
                'BAKERY',
                'BUTCHER_MEAT_SHOP',
                'RETAIL_SHOP',
                'ACCOUNTANT',
                'MEDICAL_PRACTICE',
                'LEGAL_PRACTICE',
                'CONSULTANCY',
                'SALON_BEAUTY',
                'IT_SERVICES',
                'AGRICULTURE',
                'RESTAURANT_CAFE',
                'OTHER',
            ],
            'risk_construction_type' => [
                'Brick',
                'Concrete',
                'Steel',
                'Wood',
                'Other',
            ],
            'risk_occupancy_type' => [
                'OWNER_OCCUPIED',
                'TENANT',
            ],
            'risk_town_class' => [
                'primary',
                'secondary',
                'tertiary',
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($lookupData as $key => $values) {
            foreach ($values as $value) {
                try {
                    $existing = Lookup::where('key', $key)->where('value', $value)->first();
                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    $row = new Lookup();
                    $row->key   = $key;
                    $row->value = $value;
                    $row->save();

                    $created++;
                } catch (\Throwable $e) {
                    Log::warning('[BizSureLookupSeeder] Failed to insert lookup row', [
                        'key'   => $key,
                        'value' => $value,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->command->info(sprintf(
            'BizSure lookup rows — created: %d, skipped (already present): %d',
            $created,
            $skipped
        ));
    }
}
