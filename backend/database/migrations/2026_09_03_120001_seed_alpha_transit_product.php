<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alpha Transit Cover — seed the legacy `products` row and the courier
 * `agencies` rows the webhook receiver scopes ATC policies under.
 *
 * The products table has no seeder and env-variable columns, so the row is
 * built defensively (column probing, same idiom as LookupController) and the
 * product is resolved at runtime by slug/name — never by a hardcoded id.
 *
 * Idempotent by lookup (PR-guard standard): re-running never duplicates.
 */
return new class extends Migration {
    private const PRODUCT_NAME = 'Alpha Transit Cover';
    private const PRODUCT_SLUG = 'alpha-transit-cover';

    private const AGENCIES = ['EG Couriers', 'KTU Express', 'Aramex Botswana'];

    public function up(): void
    {
        if (Schema::hasTable('products')) {
            $exists = DB::table('products')->where('name', self::PRODUCT_NAME)
                ->when(Schema::hasColumn('products', 'slug'), function ($q) {
                    return $q->orWhere('slug', self::PRODUCT_SLUG);
                })
                ->exists();

            if (!$exists) {
                $cols = Schema::getColumnListing('products');
                $row  = array_intersect_key([
                    'name'        => self::PRODUCT_NAME,
                    'slug'        => self::PRODUCT_SLUG,
                    'type'        => 'general',
                    'description' => 'Goods-in-transit cover, 14 days per shipment. Sold on start.alphadirect (GIT prefix) and issued by courier partners on the Alpha Transit platform (ATC prefix, ingested via the alpha-transit webhook).',
                    'status'      => 1,
                    'has_member'  => 0,
                    'has_vehicle' => 0,
                    'isForStart'  => 1, // sold on start.alphadirect via the instant flow
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ], array_flip($cols));

                // Pin id 25 (1–24 are taken across envs) so the FE deep link
                // (?productId=25) and GoodsInTransitController::PRODUCT_ID hold
                // everywhere. If a foreign row already owns 25 in some env,
                // fall back to auto-increment — the controller resolves by id
                // first, then by name.
                if (!DB::table('products')->where('id', 25)->exists()) {
                    $row['id'] = 25;
                }
                DB::table('products')->insert($row);
            }
        }

        // Plans = goods categories. GIT premium is value-rated (% of declared
        // value, per zone), so `premium` here is only the from-price for the
        // plans dropdown; the authoritative premium is computed by
        // GoodsInTransitController::calculatePremium. The ATC minimums (P20 /
        // P40) are VAT-INCLUSIVE, and plansByProduct adds BW VAT on top of
        // this column — so the ex-VAT figure is stored (20/1.14, 40/1.14) and
        // the dropdown shows P20 / P40 inclusive. Looked up at runtime by
        // plan_unique_id (GITSTD / GITELE) — never by id.
        if (Schema::hasTable('products') && Schema::hasTable('product_plans')) {
            $productId = DB::table('products')->where('name', self::PRODUCT_NAME)->value('id');
            if ($productId) {
                $planCols = Schema::getColumnListing('product_plans');
                foreach ([
                    ['plan_unique_id' => 'GITSTD', 'name' => 'Standard Goods', 'slug' => 'Goods-in-Transit — Standard Goods (from P20)', 'premium' => 17.54],
                    ['plan_unique_id' => 'GITELE', 'name' => 'Electronics & Fragile', 'slug' => 'Goods-in-Transit — Electronics & Fragile (from P40)', 'premium' => 35.09],
                ] as $plan) {
                    if (!DB::table('product_plans')->where('plan_unique_id', $plan['plan_unique_id'])->exists()) {
                        DB::table('product_plans')->insert(array_intersect_key($plan + [
                            'product_id'  => $productId,
                            'sum_assured' => 75000, // per-shipment declared-value ceiling
                            'region_id'   => 7,     // Botswana — drives regions.vat lookup
                            'status'      => 1,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ], array_flip($planCols)));
                    }
                }
            }
        }

        if (Schema::hasTable('agencies')) {
            foreach (self::AGENCIES as $name) {
                if (!DB::table('agencies')->where('name', $name)->exists()) {
                    DB::table('agencies')->insert([
                        'name'       => $name,
                        'status'     => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Seed-only migration: rows are left in place on rollback — policies
        // may already reference the product/agency ids.
    }
};
