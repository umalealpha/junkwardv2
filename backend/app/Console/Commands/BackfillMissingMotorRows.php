<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill missing motor rows for policy_coverages whose associated motor rows
 * were not copied during an ENDORSE / RENEW transaction.
 *
 * Problem:
 *   - `motor` table links to `policy_coverage_id` only (no `action_id`).
 *   - On endorsement, PolicyAction::newPolicyAction replicates policy_coverages
 *     and their motor rows. In some cases the pc row is created later and the
 *     motor replication did not fire, leaving the current action without
 *     vehicle details in V2 Quote / Policy Schedule PDFs.
 *
 * Strategy:
 *   For every policy whose latest action has a motor-covering policy_coverage
 *   (coverage_id in [15,16,22,27,630]) that has 0 motor rows, copy the motor
 *   rows from the most-recent action's corresponding policy_coverage.
 *
 * Usage:
 *   php artisan backfill:missing-motor                 (dry-run all policies)
 *   php artisan backfill:missing-motor --apply         (apply fix to all)
 *   php artisan backfill:missing-motor --policy=127478 (single policy)
 */
class BackfillMissingMotorRows extends Command
{
    protected $signature = 'backfill:missing-motor
                            {--apply : Persist changes (otherwise dry-run)}
                            {--policy= : Restrict to a single policy id}
                            {--limit=0 : Max policies to touch (0 = unlimited)}';

    protected $description = 'Copy missing motor rows to the latest policy_coverage where endorsement replication failed.';

    private const MOTOR_COVERAGE_IDS = [15, 16, 22, 27, 630];

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $onlyId    = $this->option('policy');
        $limit     = (int) $this->option('limit');

        $this->info(($apply ? 'APPLY' : 'DRY-RUN') . ' — scanning for policy_coverages with missing motor rows…');

        // Single efficient query: latest action per policy × motor-coverage PCs with 0 motor rows.
        // Restricts to motor products (7, 8, 16, 17, 18, 19) via the policies join.
        $query = DB::table('policy_coverages as pc')
            ->join('policies as p', 'p.id', '=', 'pc.policy_id')
            ->join(DB::raw('(SELECT policy_id, MAX(id) AS latest_action_id FROM policy_actions GROUP BY policy_id) la'),
                function ($j) {
                    $j->on('la.policy_id', '=', 'pc.policy_id')
                      ->on('la.latest_action_id', '=', 'pc.action_id');
                })
            ->leftJoin('motor as m', function ($j) {
                $j->on('m.policy_coverage_id', '=', 'pc.id')->whereNull('m.deleted_at');
            })
            ->whereIn('p.product_id', [7, 8, 16,17,18,20,22,23,24])
            ->whereIn('pc.coverage_id', self::MOTOR_COVERAGE_IDS)
            ->whereNull('pc.deleted_at')
            ->when($onlyId, fn ($q) => $q->where('pc.policy_id', $onlyId))
            ->select('pc.id', 'pc.coverage_id', 'pc.policy_id', 'pc.action_id')
            ->groupBy('pc.id', 'pc.coverage_id', 'pc.policy_id', 'pc.action_id')
            ->havingRaw('COUNT(m.id) = 0');

        $emptyPcsAll = $query->get();
        $this->info('Found ' . $emptyPcsAll->count() . ' policy_coverage rows on latest actions with 0 motor rows.');

        $touched = 0; $copied = 0; $skipped = 0;
        $grouped = $emptyPcsAll->groupBy('policy_id');

        foreach ($grouped as $policyId => $emptyPcs) {
            if ($limit > 0 && $touched >= $limit) break;
            $actionId = $emptyPcs->first()->action_id;

            $this->line("• Policy {$policyId} (action {$actionId}): " . $emptyPcs->count() . ' empty pc row(s)');
            $touched++;

            foreach ($emptyPcs as $pc) {
                // Find the most-recent pc on this policy+coverage that HAS motor rows
                $sourcePc = DB::table('policy_coverages as pc')
                    ->join('motor as m', 'm.policy_coverage_id', '=', 'pc.id')
                    ->where('pc.policy_id', $policyId)
                    ->where('pc.coverage_id', $pc->coverage_id)
                    ->whereNull('pc.deleted_at')
                    ->whereNull('m.deleted_at')
                    ->where('pc.id', '!=', $pc->id)
                    ->orderByDesc('pc.action_id')
                    ->orderByDesc('pc.id')
                    ->select('pc.id', 'pc.action_id')
                    ->first();

                if (!$sourcePc) {
                    $this->warn("  - pc {$pc->id} (cov {$pc->coverage_id}): no source pc found — skipped");
                    $skipped++;
                    continue;
                }

                $motorRows = DB::table('motor')
                    ->where('policy_coverage_id', $sourcePc->id)
                    ->whereNull('deleted_at')
                    ->get();

                if ($motorRows->isEmpty()) {
                    $skipped++;
                    continue;
                }

                $this->line("  → Copy {$motorRows->count()} motor row(s) from pc {$sourcePc->id} (action {$sourcePc->action_id}) → pc {$pc->id}");

                if (!$apply) {
                    $copied += $motorRows->count();
                    continue;
                }

                DB::transaction(function () use ($motorRows, $pc, &$copied) {
                    foreach ($motorRows as $row) {
                        $newRow = (array) $row;
                        unset($newRow['id']);
                        $newRow['policy_coverage_id'] = $pc->id;
                        $newRow['created_at'] = now();
                        $newRow['updated_at'] = now();
                        DB::table('motor')->insert($newRow);
                        $copied++;
                    }
                });
            }
        }

        $this->info('');
        $this->info('Summary: '
            . "touched policies={$touched}, "
            . 'motor rows ' . ($apply ? 'copied' : 'to be copied') . "={$copied}, "
            . "skipped={$skipped}");

        if (!$apply) {
            $this->warn('Dry-run mode. Re-run with --apply to persist changes.');
        }

        return 0;
    }
}
