<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupDuplicateCoverages extends Command
{
    protected $signature = 'policy:cleanup-duplicate-coverages
                            {--apply : Actually delete. Omit for a dry-run preview}
                            {--dry-run : Deprecated no-op — dry run is now the default}
                            {--policy= : Limit cleanup to a single policy ID}';

    protected $description = 'Remove duplicate policy_coverage rows (same coverage_id + risk_address_id + action_id). Keeps rows with real data; discards empty duplicates.';

    public function handle(): int
    {
        // SAFE BY DEFAULT. This used to delete on a bare invocation, with
        // --dry-run as the opt-in — the only cleanup command in the set that
        // way round, and it deletes coverage rows on any action, issued
        // included. Now it previews unless --apply is passed. The old
        // --dry-run flag is accepted and ignored so existing runbooks and
        // any `cron` table row keep working (they just keep previewing).
        $dryRun   = !$this->option('apply');
        $policyId = $this->option('policy');

        $this->info($dryRun ? '[DRY RUN] Scanning for duplicate coverages…' : 'Cleaning duplicate coverage rows…');

        // Find all (policy_id, coverage_id, risk_address_id, action_id) groups that have
        // more than one active row.
        $query = DB::table('policy_coverages')
            ->select('policy_id', 'coverage_id', 'risk_address_id', 'action_id')
            ->selectRaw('COUNT(*) as cnt')
            ->whereNull('deleted_at')
            ->groupBy('policy_id', 'coverage_id', 'risk_address_id', 'action_id')
            ->havingRaw('COUNT(*) > 1');

        if ($policyId) {
            $query->where('policy_id', (int) $policyId);
        }

        $groups = $query->get();

        if ($groups->isEmpty()) {
            $this->info('No duplicate coverages found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$groups->count()} duplicate group(s).");

        $totalDeleted = 0;

        foreach ($groups as $group) {
            // Fetch every row in this duplicate group, ordered oldest → newest.
            $rows = DB::table('policy_coverages')
                ->where('policy_id',       $group->policy_id)
                ->where('coverage_id',     $group->coverage_id)
                // risk_address_id is nullable, and `= NULL` never matches in
                // MySQL — a NULL-address duplicate group was found by the
                // GROUP BY above and then silently fetched as an empty set.
                ->when($group->risk_address_id === null,
                    fn($q) => $q->whereNull('risk_address_id'),
                    fn($q) => $q->where('risk_address_id', $group->risk_address_id))
                ->where('action_id',       $group->action_id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'coverage_value', 'calculated_value']);

            // Enrich each row with its subcoverage count.
            $rows = $rows->map(function ($row) {
                $row->sub_count = DB::table('policy_coverage_detail')
                    ->where('policy_coverage_id', $row->id)
                    ->whereNull('deleted_at')
                    ->count();
                $row->has_data = $row->sub_count > 0
                    || (float) ($row->coverage_value   ?? 0) > 0
                    || (float) ($row->calculated_value ?? 0) > 0;
                return $row;
            });

            $withData    = $rows->filter(fn($r) =>  $r->has_data);
            $withoutData = $rows->filter(fn($r) => !$r->has_data);

            if ($withData->count() > 1) {
                // More than one row in the group carries data. The old rule
                // ($toDelete = $withoutData) left every one of them in place,
                // so a duplicate whose copies BOTH hold the same premium was
                // reported and then skipped — the exact shape produced by
                // replication copying a duplicated source coverage forward
                // (two identical sections on the V2 Quote, two identical lines
                // in the Endorse Change Summary).
                //
                // Only collapse when the copies are genuinely IDENTICAL across
                // every child bucket. If they differ, one of them holds premium
                // or cover the other does not and picking a winner would lose
                // money — report those and leave them for a human. The empty
                // rows in the group are still safe to drop either way.
                $fingerprints = $withData->map(fn($r) => $this->coverageFingerprint($r));
                if ($fingerprints->unique()->count() > 1) {
                    $this->warn(sprintf(
                        '  KEPT (copies differ — needs manual review)  policy_id=%d  coverage_id=%d  action_id=%d  ids=%s',
                        $group->policy_id, $group->coverage_id, $group->action_id, $withData->pluck('id')->implode(', ')
                    ));
                    $toDelete = $withoutData;
                } else {
                    // Identical copies — keep the oldest (lowest id), drop the
                    // rest along with any empty rows, in a single pass.
                    $toDelete = $withoutData->merge($withData->sortBy('id')->slice(1));
                }
            } elseif ($withData->isNotEmpty()) {
                // One real row — delete every empty/zero row.
                $toDelete = $withoutData;
            } else {
                // All rows are empty — keep the most recent (highest ID), delete the rest.
                $toDelete = $rows->sortBy('id')->slice(0, -1);
            }

            if ($toDelete->isEmpty()) {
                continue;
            }

            $idsToDelete = $toDelete->pluck('id')->toArray();

            if ($dryRun) {
                foreach ($idsToDelete as $id) {
                    $this->line(sprintf(
                        '  [DRY RUN] Would delete policy_coverage id=%d  (policy_id=%d, coverage_id=%d)',
                        $id, $group->policy_id, $group->coverage_id
                    ));
                }
            } else {
                // Soft-delete every child bucket, not just sub-coverages: an
                // extension / specified item / vehicle left LIVE under a
                // deleted parent still reaches the pro-rata banner, which sums
                // by action pc-ids WITHOUT filtering deleted parents.
                //
                // The pro-rata stamps are cleared in the same statement. A
                // duplicate that had been carried forward long enough to own a
                // baseline would otherwise be read as a CANCEL on the next Rate
                // click and refund premium the customer was never charged.
                // Unstamped + soft-deleted is skipped by writeLineLevelProRata
                // and excluded from the banner sum.
                foreach (['policy_coverage_detail', 'policy_extention_detail', 'policy_specified_items', 'motor'] as $childTable) {
                    if (!Schema::hasTable($childTable) || !Schema::hasColumn($childTable, 'deleted_at')) {
                        continue;
                    }
                    $clear = ['deleted_at' => now()];
                    foreach (['pro_rate_premium' => 0, 'endors_flag' => 0, 'previousActionIdCov' => 0] as $col => $zero) {
                        if (Schema::hasColumn($childTable, $col)) $clear[$col] = $zero;
                    }
                    DB::table($childTable)
                        ->whereIn('policy_coverage_id', $idsToDelete)
                        ->whereNull('deleted_at')
                        ->update($clear);
                }

                // Soft-delete the duplicate coverage rows.
                DB::table('policy_coverages')
                    ->whereIn('id', $idsToDelete)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now()]);

                $this->line(sprintf(
                    '  Deleted %d duplicate(s)  policy_id=%d  coverage_id=%d',
                    count($idsToDelete), $group->policy_id, $group->coverage_id
                ));
            }

            $totalDeleted += count($idsToDelete);
        }

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$verb} {$totalDeleted} duplicate coverage row(s) in total.");

        if ($dryRun && $totalDeleted > 0) {
            $this->warn('DRY RUN — nothing was written. Re-run with --apply to delete.');
        }

        return Command::SUCCESS;
    }

    /**
     * Content signature of one policy_coverages row: its own values plus every
     * live child row across the four premium-bearing buckets. Two copies with
     * the same signature carry the same cover for the same money, so keeping
     * either one is equivalent. Per-action stamps and surrogate ids are
     * excluded — they differ between copies by definition.
     */
    private function coverageFingerprint($row): string
    {
        $parts = [
            'pc:' . number_format((float) ($row->coverage_value ?? 0), 2, '.', '')
                . '/' . number_format((float) ($row->calculated_value ?? 0), 2, '.', ''),
        ];

        $buckets = [
            'policy_coverage_detail'  => ['coverage_id', 'coverage_value', 'rate', 'calculated_value'],
            'policy_extention_detail' => ['extentions_id', 'type', 'extention_coverage_value', 'extention_calculated_value'],
            'policy_specified_items'  => ['specified_coverage_id', 'motor_id', 'sum_insured', 'rate', 'calculated_value'],
            'motor'                   => ['registration_no', 'calculated_value'],
        ];

        foreach ($buckets as $table => $cols) {
            if (!Schema::hasTable($table)) continue;
            $cols = array_values(array_filter($cols, fn($c) => Schema::hasColumn($table, $c)));
            if (empty($cols)) continue;

            $lines = DB::table($table)
                ->where('policy_coverage_id', $row->id)
                ->whereNull('deleted_at')
                ->get($cols)
                ->map(fn($r) => implode('/', array_map(
                    fn($c) => is_numeric($r->{$c} ?? null)
                        ? number_format((float) $r->{$c}, 2, '.', '')
                        : (string) ($r->{$c} ?? ''),
                    $cols
                )))
                ->sort()   // row order between copies is arbitrary
                ->values()
                ->all();

            $parts[] = $table . ':' . implode(',', $lines);
        }

        return md5(implode('|', $parts));
    }
}
