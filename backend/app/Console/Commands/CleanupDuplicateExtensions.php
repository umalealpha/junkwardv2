<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Clean up DUPLICATE policy_extention_detail rows — the same extension written
 * more than once under one policy_coverage.
 *
 * WHERE THEY CAME FROM (all fixed in PolicyAction, this command clears the
 * rows those bugs already wrote):
 *   1. replicateRecordsIfMissing() looked for the target row through the
 *      SoftDeletes scope, so a row REMOVED on the target batch was invisible
 *      and got re-cloned as a fresh LIVE row on every re-run of the additive
 *      replicate (endorse-issue forward propagation into the ANNIVERSARY-RENEW
 *      quote, Refresh "fill missing", batch renew). Removing the duplicate by
 *      hand therefore just produced another one on the next run.
 *   2. The coverage-level "already exists?" check had the same blindness, so a
 *      cancelled coverage was re-inserted with a full duplicate child set.
 *   3. replicateRecords() compared whole attribute sets, so two copies of one
 *      extension that had been stamped by different Rate runs looked distinct
 *      and the anniversary cron carried both into the new term.
 *
 * Identity = policy_coverage_id + extentions_id + type, and for custom / Excess
 * rows (extentions_id NULL) + custom_name / extention_text_value. Same key the
 * replicator now uses (PolicyAction::extensionBusinessKey).
 *
 * Safety:
 *  - DRY-RUN by default. Nothing is written unless --execute is passed.
 *  - Soft-delete only (sets deleted_at); fully reversible.
 *  - Only ever touches rows where deleted_at IS NULL, and always KEEPS one row
 *    per group (the lowest id — the original), so premium can only come down to
 *    the correct single-count figure, never to zero.
 *  - Idempotent: re-runs are no-ops once clean.
 *
 * After running with --execute on a policy, re-Rate / re-open the affected
 * quote so the action totals pick up the corrected extension sum.
 */
class CleanupDuplicateExtensions extends Command
{
    protected $signature = 'policy:cleanup-duplicate-extensions
                            {--execute : Actually soft-delete. WITHOUT this flag the command only reports (dry-run).}
                            {--policy= : Limit to a single policyNumber (e.g. COMG2024103813) or policy id.}
                            {--action= : Limit to a single policy_actions.id (e.g. the anniversary quote).}
                            {--products= : Comma-separated product IDs to scope to. Omit for all products.}
                            {--chunk=500 : Soft-delete batch size.}';

    protected $description = 'Soft-delete duplicate extension rows (same coverage + same extension) keeping one per group. Dry-run unless --execute.';

    public function handle(): int
    {
        $execute  = (bool) $this->option('execute');
        $policy   = $this->option('policy');
        $actionId = $this->option('action');
        $products = array_filter(array_map('intval', explode(',', (string) $this->option('products'))));
        $chunk    = max(50, (int) $this->option('chunk'));

        $hasCustomName = Schema::hasColumn('policy_extention_detail', 'custom_name');

        $mode = $execute ? 'EXECUTE' : 'DRY RUN';
        $this->info("[{$mode}] Cleanup duplicate extension rows"
            . ($products ? '  products=' . implode(',', $products) : '')
            . ($policy ? "  policy={$policy}" : '')
            . ($actionId ? "  action={$actionId}" : ''));

        // Live extension rows under live coverages / live actions, scoped.
        $base = DB::table('policy_extention_detail as ped')
            ->join('policy_coverages as pc', 'pc.id', '=', 'ped.policy_coverage_id')
            ->join('policy_actions as pa', 'pa.id', '=', 'pc.action_id')
            ->join('policies as p', 'p.id', '=', 'pc.policy_id')
            ->whereNull('ped.deleted_at')
            ->whereNull('pc.deleted_at')
            ->whereNull('pa.deleted_at');

        if ($products) {
            $base->whereIn('p.product_id', $products);
        }
        if ($policy) {
            $base->where(function ($q) use ($policy) {
                $q->where('p.policyNumber', $policy);
                if (ctype_digit((string) $policy)) {
                    $q->orWhere('p.id', (int) $policy);
                }
            });
        }
        if ($actionId) {
            $base->where('pa.id', (int) $actionId);
        }

        $select = [
            'ped.id as ped_id',
            'ped.policy_coverage_id',
            'ped.extentions_id',
            'ped.type',
            'ped.extention_text_value',
            'ped.extention_calculated_value',
            'p.policyNumber',
            'pa.id as action_id',
            'pa.transaction_type',
            'pa.status as action_status',
        ];
        if ($hasCustomName) {
            $select[] = 'ped.custom_name';
        }

        $rows = (clone $base)->select($select)->orderBy('ped.id')->get();

        if ($rows->isEmpty()) {
            $this->info('No extension rows matched the given scope.');
            return Command::SUCCESS;
        }

        // Group by the replicator's business key and keep the lowest id.
        $groups = $rows->groupBy(function ($r) use ($hasCustomName) {
            $key = [$r->policy_coverage_id, (int) ($r->extentions_id ?? 0), (string) ($r->type ?? '')];

            if (empty($r->extentions_id)) {
                $custom = $hasCustomName ? trim((string) ($r->custom_name ?? '')) : '';
                $key[]  = $custom !== '' ? $custom : trim((string) ($r->extention_text_value ?? ''));
            }

            return implode('|', $key);
        })->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('Nothing to clean — no duplicate extension rows found.');
            return Command::SUCCESS;
        }

        $dropIds   = [];
        $dropValue = 0.0;
        $affected  = [];

        foreach ($groups as $group) {
            $sorted = $group->sortBy('ped_id')->values();
            $sorted->shift();                       // keep the original
            foreach ($sorted as $extra) {
                $dropIds[]  = $extra->ped_id;
                $dropValue += (float) ($extra->extention_calculated_value ?? 0);

                $label = $extra->policyNumber . ' / action ' . $extra->action_id
                    . ' (' . $extra->transaction_type . ' ' . $extra->action_status . ')';
                $affected[$label] = ($affected[$label] ?? 0) + 1;
            }
        }

        $this->info(sprintf(
            'Found %d duplicate group(s) → %d extra row(s) carrying %s in extension premium.',
            $groups->count(),
            count($dropIds),
            number_format($dropValue, 2)
        ));

        foreach (array_slice($affected, 0, 50, true) as $label => $count) {
            $this->line(sprintf('  %-70s %d extra row(s)', $label, $count));
        }
        if (count($affected) > 50) {
            $this->line('  … (' . (count($affected) - 50) . ' more actions)');
        }

        if (!$execute) {
            $this->warn('DRY RUN — no changes made. Re-run with --execute to apply.');
            return Command::SUCCESS;
        }

        $now     = now();
        $deleted = 0;
        foreach (array_chunk($dropIds, $chunk) as $batch) {
            $deleted += DB::table('policy_extention_detail')
                ->whereIn('id', $batch)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);
        }

        $msg = "Soft-deleted {$deleted} duplicate extension row(s) across " . count($affected) . ' action(s).';
        $this->info($msg);
        $this->warn('Re-Rate / re-open the affected quotes so the action totals pick up the corrected extension sum.');

        Log::info('[CleanupDuplicateExtensions] ' . $msg, [
            'groups'   => $groups->count(),
            'matched'  => count($dropIds),
            'deleted'  => $deleted,
            'products' => $products,
            'policy'   => $policy,
            'action'   => $actionId,
        ]);

        return Command::SUCCESS;
    }
}
