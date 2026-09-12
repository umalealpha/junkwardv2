<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data fix (Pramod, 2026-09-10): the two union schemes were registered against
 * each other's Legal Insurance plan, so BONU showed P49 per member and
 * BOWASEWU P75. Correct pricing:
 *
 *   BONU      → P75 Legal Insurance Group scheme (plan_unique_id LEINP75)
 *   BOWASEWU  → P49 Legal Insurance Bronze       (plan_unique_id LEINP49)
 *
 * Mirrors UnionSchemeController::update(): the plan sets the union's
 * monthly_premium (plan price × VAT factor), and the group policy's plan_id,
 * premium and annual_premium follow. Idempotent — a union already on the
 * right plan is left alone. Plans are looked up by plan_unique_id, not by
 * numeric id, so the fix is correct on every environment.
 */
return new class extends Migration {
    private const MAPPING = [
        'BONU'     => 'LEINP75',
        'BOWASEWU' => 'LEINP49',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('unions') || !Schema::hasTable('product_plans')) {
            return;
        }

        $factor = 1 + ((float) env('BW_VAT_PERCENT', 14) / 100);
        $now = Carbon::now();

        foreach (self::MAPPING as $code => $planUid) {
            $plan = DB::table('product_plans')
                ->where('product_id', 4)
                ->where('plan_unique_id', $planUid)
                ->first(['id', 'name', 'premium']);
            if (!$plan) {
                echo "[union-pricing] plan {$planUid} not found — skipping {$code}.\n";
                continue;
            }

            $union = DB::table('unions')->where('union_code', $code)->whereNull('deleted_at')->first();
            if (!$union) {
                echo "[union-pricing] union {$code} not registered here — nothing to do.\n";
                continue;
            }

            $premium = round((float) $plan->premium * $factor, 2);
            if ((int) $union->plan_id === (int) $plan->id && (float) $union->monthly_premium === $premium) {
                echo "[union-pricing] {$code} already on {$plan->name} @ P{$premium}.\n";
                continue;
            }

            DB::transaction(function () use ($union, $plan, $premium, $now, $code) {
                DB::table('unions')->where('id', $union->id)->update([
                    'plan_id'         => $plan->id,
                    'monthly_premium' => $premium,
                    'updated_at'      => $now,
                ]);
                if ($union->policy_id) {
                    DB::table('policies')->where('id', $union->policy_id)->update([
                        'plan_id'        => $plan->id,
                        'premium'        => $premium,
                        'annual_premium' => round($premium * 12, 2),
                        'updated_at'     => $now,
                    ]);
                }
                if (Schema::hasTable('activity_log')) {
                    DB::table('activity_log')->insert([
                        'log_name'     => 'Union',
                        'description'  => "Plan mapping corrected: {$code} → {$plan->name} (P{$premium}/member)",
                        'subject_type' => 'AlphaDirect\\Models\\Union',
                        'subject_id'   => $union->id,
                        'properties'   => json_encode([
                            'old' => ['plan_id' => $union->plan_id, 'monthly_premium' => $union->monthly_premium],
                            'new' => ['plan_id' => $plan->id, 'monthly_premium' => $premium],
                            'source' => '2026_09_10_100000_fix_bonu_bowasewu_plan_mapping',
                        ]),
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            });
            echo "[union-pricing] {$code}: plan {$union->plan_id} → {$plan->id} ({$plan->name}), P{$union->monthly_premium} → P{$premium}.\n";
        }
    }

    public function down(): void
    {
        // Intentionally empty: the previous mapping was the error being fixed.
    }
};
