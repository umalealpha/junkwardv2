<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed ad_pricings for ADI (product = 1).
 *
 * Why
 * ---
 * After the 2026-06-04 V1→V2 cutover, the PROD master DB ended up with
 * ad_pricings rows only for products 24, 25, 26, 27, 28 — all orphan
 * IDs that don't exist in the V2 `products` table. The clean V2 product
 * row for Accidental Death Insurance is `products.id = 1`, but
 * `ad_pricings` has zero rows under that ID.
 *
 * AccidentalDeathController::lookupRate() (line 485) queries
 * `ad_pricings WHERE product = 1`, finds nothing, and returns
 * { ok: false, error: 'no_pricing_band' }. Start V2 customers saw
 * "Couldn't create the quote: no_pricing_band" on every ADI P49/P79
 * attempt for ~5 days post-cutover.
 *
 * Source of truth
 * ---------------
 * Staging master `ad_pricings` already has the canonical 22-row rate
 * card under product = 1 (Female + Male × 11 age bands each). PROD's
 * product = 24 rows happen to be byte-identical to that — confirming
 * that staging's product = 1 IS the right rate card and the cutover
 * just dropped the data under the wrong ID on PROD. Values below were
 * pulled directly from staging on 2026-06-08.
 *
 * Idempotency
 * -----------
 * Skips seeding if any rows already exist for product = 1. Safe to run
 * on every env regardless of starting state — staging stays unchanged,
 * PROD gets seeded, future envs get seeded on first migrate.
 *
 * Follow-ups (NOT in this migration — separate work)
 * --------------------------------------------------
 * - Orphan product IDs 24-28 in ad_pricings should be audited and
 *   either remapped to real product IDs or removed.
 * - ADPricing model has `total_dependents` in $fillable but the column
 *   doesn't exist in the table — model/schema drift, harmless but worth
 *   cleaning up.
 * - Finance to validate the rate values are still current (rates per
 *   age band, both genders).
 */
return new class extends Migration {
    public function up(): void
    {
        $existing = DB::table('ad_pricings')->where('product', 1)->count();
        if ($existing > 0) {
            // Staging already has 22 rows here; nothing to do. Echo so the
            // migrate output makes it clear we deliberately skipped.
            echo "[ad_pricings seed] product = 1 already has {$existing} row(s); skipping seed.\n";
            return;
        }

        // 22 rows: gender (Female/Male) × 11 age bands.
        // Final band has age_to = NULL meaning "65+". The controller's
        // WHERE age_to >= $age clause won't match NULL — that's pre-
        // existing behaviour on staging too; out of scope for this fix.
        $rates = [
            // Female
            ['gender' => 'Female', 'age_from' =>  0, 'age_to' => 19,   'main' => 0.00,   'adult_dependent' => 0.00,   'child_dependent' => 43.00],
            ['gender' => 'Female', 'age_from' => 20, 'age_to' => 24,   'main' => 93.00,  'adult_dependent' => 77.00,  'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 25, 'age_to' => 29,   'main' => 98.00,  'adult_dependent' => 81.00,  'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 30, 'age_to' => 34,   'main' => 104.00, 'adult_dependent' => 86.00,  'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 35, 'age_to' => 39,   'main' => 110.00, 'adult_dependent' => 90.00,  'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 40, 'age_to' => 44,   'main' => 115.00, 'adult_dependent' => 94.00,  'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 45, 'age_to' => 49,   'main' => 120.00, 'adult_dependent' => 97.00,  'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 50, 'age_to' => 54,   'main' => 122.00, 'adult_dependent' => 99.00,  'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 55, 'age_to' => 59,   'main' => 124.00, 'adult_dependent' => 100.00, 'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 60, 'age_to' => 64,   'main' => 126.00, 'adult_dependent' => 101.00, 'child_dependent' => 0.00],
            ['gender' => 'Female', 'age_from' => 65, 'age_to' => null, 'main' => 127.00, 'adult_dependent' => 103.00, 'child_dependent' => 0.00],
            // Male
            ['gender' => 'Male',   'age_from' =>  0, 'age_to' => 19,   'main' => 0.00,   'adult_dependent' => 0.00,   'child_dependent' => 46.00],
            ['gender' => 'Male',   'age_from' => 20, 'age_to' => 24,   'main' => 76.00,  'adult_dependent' => 65.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 25, 'age_to' => 29,   'main' => 77.00,  'adult_dependent' => 65.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 30, 'age_to' => 34,   'main' => 83.00,  'adult_dependent' => 70.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 35, 'age_to' => 39,   'main' => 87.00,  'adult_dependent' => 73.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 40, 'age_to' => 44,   'main' => 91.00,  'adult_dependent' => 76.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 45, 'age_to' => 49,   'main' => 95.00,  'adult_dependent' => 79.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 50, 'age_to' => 54,   'main' => 101.00, 'adult_dependent' => 83.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 55, 'age_to' => 59,   'main' => 107.00, 'adult_dependent' => 87.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 60, 'age_to' => 64,   'main' => 113.00, 'adult_dependent' => 92.00,  'child_dependent' => 0.00],
            ['gender' => 'Male',   'age_from' => 65, 'age_to' => null, 'main' => 117.00, 'adult_dependent' => 95.00,  'child_dependent' => 0.00],
        ];

        $now  = now();
        $rows = array_map(fn ($r) => array_merge($r, [
            'product'    => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $rates);

        DB::table('ad_pricings')->insert($rows);

        echo "[ad_pricings seed] inserted " . count($rows) . " ADI rate rows for product = 1.\n";
    }

    public function down(): void
    {
        // Surgical down: delete only the rows seeded by this migration.
        // Other products (24-28 orphans, future real products) untouched.
        DB::table('ad_pricings')->where('product', 1)->delete();
    }
};
