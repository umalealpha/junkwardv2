<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DEMO treaty configuration — for TESTING the Reinsurance dashboard ONLY.
 * These are NOT Alpha Direct's real treaty terms. Every row is flagged
 * is_demo=true and the dashboard must show a "DEMO TREATY — not real terms"
 * banner. Replace with the real treaty table (from Pako/Kago/Bokani /
 * the reinsurance team) before any board/CFO-facing number is published.
 *
 * Spec hard-rule #1: never assume a rate — read from the treaty table.
 * This seeder exists only so the feature can be exercised end-to-end while we
 * await the real terms; it does NOT satisfy that rule for production figures.
 */
class DemoTreatySeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) date('Y');

        // QS demo for Commercial; Surplus demo for Domestic; XL demo for per-event.
        DB::table('treaty_master')->insert([
            ['treaty_id' => 'DEMO-QS', 'treaty_year' => $year, 'treaty_type' => 'QS', 'class_of_business' => 'Commercial', 'currency' => 'BWP', 'basis' => 'per_policy', 'status' => 'active', 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['treaty_id' => 'DEMO-SURPLUS', 'treaty_year' => $year, 'treaty_type' => 'SURPLUS', 'class_of_business' => 'Domestic', 'currency' => 'BWP', 'basis' => 'per_risk', 'status' => 'active', 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['treaty_id' => 'DEMO-XL', 'treaty_year' => $year, 'treaty_type' => 'XL', 'class_of_business' => 'Commercial', 'currency' => 'BWP', 'basis' => 'per_event', 'status' => 'active', 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('treaty_proportional')->insert([
            ['treaty_id' => 'DEMO-QS', 'treaty_year' => $year, 'cession_pct' => 0.30, 'commission_basis' => 'FLAT', 'commission_rate_flat' => 0.25, 'loss_carryforward_flag' => false, 'created_at' => now(), 'updated_at' => now()],
            ['treaty_id' => 'DEMO-SURPLUS', 'treaty_year' => $year, 'retention_amount' => 1000000, 'lines' => 9, 'treaty_capacity' => 9000000, 'commission_basis' => 'FLAT', 'commission_rate_flat' => 0.225, 'loss_carryforward_flag' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('treaty_xl_layers')->insert([
            ['treaty_id' => 'DEMO-XL', 'treaty_year' => $year, 'layer_no' => 1, 'layer_limit' => 3000000, 'layer_attachment' => 2000000, 'premium_basis' => 'ROL', 'layer_premium_or_rate' => 0.08, 'num_reinstatements' => 2, 'free_reinstatements' => 1, 'reinstatement_pct' => 1.0, 'created_at' => now(), 'updated_at' => now()],
            ['treaty_id' => 'DEMO-XL', 'treaty_year' => $year, 'layer_no' => 2, 'layer_limit' => 5000000, 'layer_attachment' => 5000000, 'premium_basis' => 'ROL', 'layer_premium_or_rate' => 0.05, 'num_reinstatements' => 1, 'free_reinstatements' => 0, 'reinstatement_pct' => 1.0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('reinsurer_shares')->insert([
            ['treaty_id' => 'DEMO-QS', 'treaty_year' => $year, 'reinsurer_id' => 'DEMO-RE-A', 'share_pct' => 60, 'created_at' => now(), 'updated_at' => now()],
            ['treaty_id' => 'DEMO-QS', 'treaty_year' => $year, 'reinsurer_id' => 'DEMO-RE-B', 'share_pct' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['treaty_id' => 'DEMO-SURPLUS', 'treaty_year' => $year, 'reinsurer_id' => 'DEMO-RE-A', 'share_pct' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['treaty_id' => 'DEMO-XL', 'treaty_year' => $year, 'reinsurer_id' => 'DEMO-RE-C', 'share_pct' => 100, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
