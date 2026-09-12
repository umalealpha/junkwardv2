<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Api\V1\UnionSchemeController;
use AlphaDirect\Models\Union;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bring the Legal Insurance union group schemes to their agreed configuration:
 *
 *   BONU      MISBONU      Legal Insurance Group Scheme   P75.00 / member / month
 *   BOWASEWU  MISBOWASEWU  Legal Insurance Group Scheme   P49.00 / member / month
 *
 * Idempotent — run it as often as you like. Per union it either registers the
 * union (minting the MIS<code> group policy) or updates the existing one to the
 * premium/description above; a union that already matches is left untouched and
 * reported as "ok".
 *
 * The create/update work is delegated to UnionSchemeController so the
 * customer + policies + unions insert sequence, the group-policy premium sync
 * and the OwenIt audit entries all stay on exactly one code path. Duplicating
 * that sequence here is what would drift.
 *
 * SAFE BY DEFAULT: no --commit => dry-run, zero writes, just the plan.
 */
class SyncUnionLegalSchemes extends Command
{
    protected $signature = 'unions:sync-legal-schemes
        {--commit : Actually write. Without this flag the command is a read-only dry-run.}
        {--code= : Restrict to a single union code (BONU or BOWASEWU).}';

    protected $description = 'Sync the BONU / BOWASEWU Legal Insurance group schemes to their agreed premiums';

    /** Legal Insurance. Matches UnionSchemeController::PRODUCT_ID. */
    private const PRODUCT_ID = 4;

    private const DESCRIPTION = 'Legal Insurance Group Scheme';

    /** union_code => [union_name, monthly premium per member]. */
    private const SCHEMES = [
        ['code' => 'BONU',     'name' => 'BONU',     'premium' => 75.00],
        ['code' => 'BOWASEWU', 'name' => 'BOWASEWU', 'premium' => 49.00],
    ];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $only   = $this->option('code') ? strtoupper(trim($this->option('code'))) : null;

        $this->info(($commit ? 'COMMIT' : 'DRY-RUN') . ' — Legal Insurance union schemes');
        $this->line('DB: ' . config('database.connections.' . config('database.default') . '.host')
            . ' / ' . config('database.connections.' . config('database.default') . '.database'));
        $this->newLine();

        $controller = new UnionSchemeController();
        $rows       = [];
        $failed     = 0;

        foreach (self::SCHEMES as $spec) {
            if ($only && $spec['code'] !== $only) continue;

            $union    = Union::whereNull('deleted_at')->where('union_code', $spec['code'])->first();
            $expected = 'MIS' . $spec['code'];

            if (!$union) {
                $rows[] = [$spec['code'], 'CREATE', '—', number_format($spec['premium'], 2), $expected,
                    $commit ? $this->create($controller, $spec, $failed) : 'planned'];
                continue;
            }

            $changes = [];
            if (round((float) $union->monthly_premium, 2) !== round($spec['premium'], 2)) {
                $changes[] = 'premium ' . number_format((float) $union->monthly_premium, 2)
                    . ' → ' . number_format($spec['premium'], 2);
            }
            if ((string) $union->description !== self::DESCRIPTION) {
                $changes[] = 'description';
            }
            // An already-correct premium is not enough now — the union also has
            // to carry the Legal product mapping, or the Edit screen opens with
            // an empty product dropdown.
            $targetPlanId = $this->resolvePlanId($spec['premium']);
            if ($targetPlanId && (int) $union->plan_id !== $targetPlanId) {
                $changes[] = 'legal product → plan #' . $targetPlanId;
            }

            // union_code / policy_number are immutable once the group policy is
            // minted — flag a mismatch for a human instead of trying to fix it.
            if ($union->policy_number && $union->policy_number !== $expected) {
                $this->warn("  {$spec['code']}: policy number is {$union->policy_number}, expected {$expected} "
                    . '(immutable — leaving as-is)');
            }
            if ((int) $union->product_id !== self::PRODUCT_ID) {
                $this->warn("  {$spec['code']}: product_id is {$union->product_id}, expected "
                    . self::PRODUCT_ID . ' (Legal) — not changed by this command');
            }

            if (!$changes) {
                $rows[] = [$spec['code'], 'ok', number_format((float) $union->monthly_premium, 2),
                    number_format($spec['premium'], 2), $union->policy_number ?? '—', 'no change'];
                continue;
            }

            $rows[] = [$spec['code'], 'UPDATE ' . implode(', ', $changes),
                number_format((float) $union->monthly_premium, 2), number_format($spec['premium'], 2),
                $union->policy_number ?? '—',
                $commit ? $this->update($controller, $union, $spec, $failed) : 'planned'];
        }

        $this->table(['Code', 'Action', 'Premium now', 'Premium target', 'Group policy', 'Result'], $rows);

        if (!$commit) {
            $this->newLine();
            $this->warn('Dry-run — nothing written. Re-run with --commit to apply.');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The Legal Insurance product (product_plans row) priced at $premium, or
     * null when the catalogue has no such tier.
     *
     * Unions are mapped to a product now, not just an amount, so the command
     * resolves the plan and lets the controller derive the premium from it.
     * `product_plans.premium` is ex-VAT while the agreed scheme figures (P75 /
     * P49) are all-in, so both are compared — same rule as the plan dropdown.
     */
    private function resolvePlanId(float $premium): ?int
    {
        try {
            $factor = 1 + ((float) env('BW_VAT_PERCENT', 14) / 100);
            $target = round($premium, 2);

            $matches = DB::table('product_plans')
                ->where('product_id', self::PRODUCT_ID)
                ->where('status', 1)
                ->get(['id', 'premium'])
                ->filter(function ($plan) use ($target, $factor) {
                    $exVat = round((float) $plan->premium, 2);
                    return $exVat === $target || round($exVat * $factor, 2) === $target;
                });

            return $matches->count() === 1 ? (int) $matches->first()->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Register a missing union (mints customer + MIS<code> policy + union row). */
    private function create(UnionSchemeController $controller, array $spec, int &$failed): string
    {
        // plan_id when the catalogue has the matching tier; monthly_premium is
        // the documented fallback so an incomplete catalogue can't block the sync.
        $planId = $this->resolvePlanId($spec['premium']);
        if (!$planId) {
            $this->warn("  {$spec['code']}: no Legal product priced at P"
                . number_format($spec['premium'], 2) . ' — registering by premium, product mapping left unset');
        }

        $resp = $controller->store(Request::create('/unions', 'POST', [
            'union_name'      => $spec['name'],
            'union_code'      => $spec['code'],
            'description'     => self::DESCRIPTION,
            'product_id'      => self::PRODUCT_ID,
            'plan_id'         => $planId,
            'monthly_premium' => $spec['premium'],
            'status'          => 1,
        ]));

        $body = $resp->getData(true);
        if ($resp->getStatusCode() >= 300) {
            $failed++;
            return 'FAILED: ' . ($body['error'] ?? $resp->getStatusCode());
        }

        return 'created (union #' . ($body['id'] ?? '?') . ')';
    }

    /** Update an existing union — also syncs the group policy premium. */
    private function update(UnionSchemeController $controller, Union $union, array $spec, int &$failed): string
    {
        $planId = $this->resolvePlanId($spec['premium']);

        $resp = $controller->update(Request::create("/unions/{$union->id}", 'PUT', array_filter([
            'description'     => self::DESCRIPTION,
            // Sending plan_id re-prices from the product; without a match fall
            // back to setting the amount directly.
            'plan_id'         => $planId,
            'monthly_premium' => $planId ? null : $spec['premium'],
        ], fn($x) => $x !== null)), $union->id);

        if ($resp->getStatusCode() >= 300) {
            $failed++;
            return 'FAILED: ' . ($resp->getData(true)['error'] ?? $resp->getStatusCode());
        }

        // Report the member-count impact, since the union premium drives billing.
        $active = DB::table('union_members')->where('union_id', $union->id)
            ->whereNull('deleted_at')->where('status', 1)->count();

        return 'updated (' . $active . ' active member(s) → P'
            . number_format($active * $spec['premium'], 2) . '/month)';
    }
}
