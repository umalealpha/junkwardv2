<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Union → Legal Insurance product mapping.
 *
 * A union is registered against one Legal Insurance product (a `product_plans`
 * row WHERE product_id = 4). That plan — not a hand-typed figure — is now the
 * source of the per-member monthly premium: the Register/Edit Union screen
 * picks the product and the backend derives `monthly_premium` from it. The
 * column stays because every downstream total (roster dashboard, group-policy
 * premium, total monthly premium = active members × monthly_premium) reads it.
 *
 * A separate migration rather than an edit to 2026_07_25_000010_create_unions_
 * table: that one has already run on shared environments, and editing a migration
 * in place leaves those DBs without the column while `migrations` claims it ran.
 *
 * Nullable, because unions registered before this change have no mapping until
 * the backfill below (or an admin) supplies one.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('unions')) {
            return; // create_unions_table hasn't run yet; it will, then this tops up.
        }

        if (!Schema::hasColumn('unions', 'plan_id')) {
            Schema::table('unions', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('product_id');
            });
        }

        $this->backfillFromPremium();
    }

    public function down(): void
    {
        if (Schema::hasTable('unions') && Schema::hasColumn('unions', 'plan_id')) {
            Schema::table('unions', fn(Blueprint $table) => $table->dropColumn('plan_id'));
        }
    }

    /**
     * Map existing unions onto the Legal plan whose price equals the premium
     * already stored on the union. `product_plans.premium` is the ex-VAT amount
     * (LookupController::plansByProduct), while the union carries the all-in
     * figure operators typed (P75 / P49), so both are compared.
     *
     * Deliberately conservative: only a single unambiguous match is written, and
     * a union that already has a plan is never touched. Anything unmatched is
     * left NULL for an admin to set on the Edit Union screen — guessing here
     * would silently re-price a live group scheme.
     */
    private function backfillFromPremium(): void
    {
        try {
            if (!Schema::hasTable('product_plans')) {
                return;
            }

            $vatFactor = 1 + ((float) env('BW_VAT_PERCENT', 14) / 100);

            $plans = DB::table('product_plans')
                ->where('product_id', 4)
                ->where('status', 1)
                ->get(['id', 'premium']);

            if ($plans->isEmpty()) {
                return;
            }

            $unions = DB::table('unions')
                ->whereNull('plan_id')
                ->where('monthly_premium', '>', 0)
                ->get(['id', 'monthly_premium']);

            foreach ($unions as $union) {
                $target = round((float) $union->monthly_premium, 2);

                $matches = $plans->filter(function ($plan) use ($target, $vatFactor) {
                    $exVat  = round((float) $plan->premium, 2);
                    $incVat = round($exVat * $vatFactor, 2);
                    return $exVat === $target || $incVat === $target;
                });

                if ($matches->count() === 1) {
                    DB::table('unions')->where('id', $union->id)
                        ->update(['plan_id' => $matches->first()->id]);
                }
            }
        } catch (\Throwable $e) {
            // The mapping is recoverable from the UI; a partially-seeded
            // product catalogue must not block the schema change.
            \Illuminate\Support\Facades\Log::warning('unions.plan_id backfill skipped', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
