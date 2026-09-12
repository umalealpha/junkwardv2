<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Policy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateLedgerForPoliciesChunk extends Command
{
    protected $signature = 'policy:generate-ledger-chunk
                            {--chunk=100 : Number of policies per chunk}
                            {--sleep-ms=50 : Sleep in milliseconds between each policy}
                            {--from-id=0 : Start processing from this policy id (exclusive)}
                            {--to-id=0 : Process up to this policy id (inclusive)}
                            {--product= : Filter by product_id (omit for all products)}
                            {--only-missing : Only process policies that need new invoices}
                            {--log-each-id=1 : Log each processed policy id (1=yes, 0=no)}';

    protected $description = 'Generate ledger for active policies using chunked processing (all products)';

    public function handle()
    {
        ini_set('max_execution_time', '0');

        $chunkSize = max((int) $this->option('chunk'), 1);
        $sleepMs = max((int) $this->option('sleep-ms'), 0);
        $fromId = max((int) $this->option('from-id'), 0);
        $toId = max((int) $this->option('to-id'), 0);
        $productId = $this->option('product');
        $onlyMissing = $this->option('only-missing');
        $logEachId = (int) $this->option('log-each-id') === 1;

        $processed = 0;
        $success = 0;
        $skipped = 0;
        $failed = 0;
        $startTime = microtime(true);

        $controller = new PolicyController();

        $query = Policy::query()
            ->where('status', 1)
            ->whereNotNull('policyActivatedDate')
            ->where('premium', '>', 0)
            ->orderBy('id')
            ->select(['id', 'policyNumber', 'premium_freq']);

        if ($productId) {
            $query->where('product_id', $productId);
            $this->info("Filter: product_id={$productId}");
        }

        if ($fromId > 0) {
            $query->where('id', '>', $fromId);
            $this->info("Starting from policy id > {$fromId}");
        }

        if ($toId > 0) {
            $query->where('id', '<=', $toId);
        }

        // Pre-filter: only policies that actually need invoices
        if ($onlyMissing) {
            // Get policy IDs with missing invoices (zero invoices or stale >35 days)
            $missingIds = DB::select("
                SELECT p.id FROM policies p
                LEFT JOIN (
                    SELECT policy_id, MAX(invoice_date) as last_inv, COUNT(*) as cnt
                    FROM policy_ledger WHERE trans_type = 'Invoice' AND deleted_at IS NULL
                    GROUP BY policy_id
                ) li ON li.policy_id = p.id
                WHERE p.status = 1 AND p.policyActivatedDate IS NOT NULL AND p.premium > 0
                  AND (li.cnt IS NULL OR li.cnt = 0 OR li.last_inv < DATE_SUB(NOW(), INTERVAL 35 DAY))
                " . ($productId ? " AND p.product_id = " . intval($productId) : "") . "
                " . ($fromId > 0 ? " AND p.id > " . intval($fromId) : "") . "
                " . ($toId > 0 ? " AND p.id <= " . intval($toId) : "") . "
                ORDER BY p.id
            ");
            $ids = array_column($missingIds, 'id');
            $this->info("Found " . count($ids) . " policies needing invoices (--only-missing)");

            if (empty($ids)) {
                $this->info("Nothing to process.");
                return Command::SUCCESS;
            }

            $query = Policy::whereIn('id', $ids)->orderBy('id')->select(['id', 'policyNumber', 'premium_freq']);
        }

        $total = (clone $query)->count();
        $this->info("Starting ledger generation — {$total} policies in chunks of {$chunkSize}, sleep={$sleepMs}ms");

        $query->chunkById($chunkSize, function ($policies) use (
            $controller, $sleepMs, $logEachId,
            &$processed, &$success, &$skipped, &$failed, $startTime, $total
        ) {
            foreach ($policies as $policy) {
                try {
                    $result = $controller->generateLedgerForPolicy($policy->id);

                    if (is_array($result) && !empty($result['success'])) {
                        $success++;
                        if ($logEachId) {
                            Log::info('Ledger OK', ['policy_id' => $policy->id]);
                        }
                    } elseif (is_array($result) && isset($result['message']) && str_contains($result['message'], 'up to date')) {
                        $skipped++;
                    } else {
                        $failed++;
                        Log::warning('Ledger generation failed', [
                            'policy_id' => $policy->id,
                            'result' => $result,
                        ]);
                    }
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('Ledger exception', [
                        'policy_id' => $policy->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $processed++;

                if ($processed % 100 === 0) {
                    $elapsed = round(microtime(true) - $startTime, 1);
                    $rate = $processed > 0 ? round($processed / $elapsed, 1) : 0;
                    $eta = $rate > 0 ? round(($total - $processed) / $rate / 60, 1) : '?';
                    $this->info("Progress: {$processed}/{$total} | OK: {$success} | Skip: {$skipped} | Fail: {$failed} | {$rate}/sec | ETA: {$eta}min");
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        }, 'id', 'id');

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->info("Completed in {$elapsed}s — Processed: {$processed} | Success: {$success} | Skipped: {$skipped} | Failed: {$failed}");

        return Command::SUCCESS;
    }
}
