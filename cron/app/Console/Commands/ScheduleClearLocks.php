<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Surgical clear of Laravel scheduler locks.
 *
 * Laravel's `->withoutOverlapping()` and `->onOneServer()` schedule
 * directives acquire cache keys named `framework/schedule-*` to prevent
 * concurrent runs. If a container is killed mid-run (OOM, deploy SIGKILL),
 * the key persists for up to `expiresAt` minutes — and in production we
 * have seen it persist for weeks, blocking the cron entirely.
 *
 * `cache:clear` works as a fix but nukes the entire application cache
 * (login lookups, premium calc caches, ledger snapshots, ...). This
 * command is the surgical alternative: it only clears `framework/schedule-*`
 * keys, leaving everything else intact.
 *
 * Usage from GitHub Actions:
 *   What to deploy: run-artisan
 *   Artisan command: schedule:clear-locks
 *   Which ECS service: cron
 *
 * Verification:
 *   Run with --dry-run first to list the keys that would be cleared.
 *   Then re-run without --dry-run to actually clear.
 *
 * The command targets the default cache store (Redis in prod). If
 * Laravel's scheduler is configured to use a different store, pass --store=<name>.
 *
 * Refs: ref_stuck_cron_recovery memory note; see also the wrap-in-try/finally
 * fixes to DomComMonthlyAutoRenew / DomComQuaterlyAutoRenew / RenewAnnualPolicies
 * (this PR) for the defensive layer that makes future stucks less likely.
 */
class ScheduleClearLocks extends Command
{
    protected $signature = 'schedule:clear-locks
        {--dry-run : List keys that would be cleared without clearing them}
        {--store= : Cache store to clear (defaults to Laravel default)}
        {--pattern=framework/schedule-* : Glob pattern of keys to clear (Redis only)}';

    protected $description = 'Clear stuck Laravel scheduler mutex locks (alternative to cache:clear that preserves the rest of the cache).';

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $store   = $this->option('store') ?: null;
        $pattern = $this->option('pattern');

        $cache = $store ? Cache::store($store) : Cache::store();
        $storeImpl = $cache->getStore();
        $storeClass = get_class($storeImpl);

        $this->line('');
        $this->info('═══ Schedule Lock Clear ═══');
        $this->line("Cache store : {$storeClass}");
        $this->line("Pattern     : {$pattern}");
        $this->line('Mode        : ' . ($dryRun ? 'DRY-RUN (no writes)' : 'WRITE'));
        $this->line('');

        // Redis store supports pattern-based key listing; other stores (file,
        // array, database) don't expose enough to SCAN by pattern. Previously
        // this command just failed on those stores — silently turning the
        // nightly self-heal schedule into a no-op on any env not running
        // Redis as cache.default. Fall back to Laravel's own schedule:clear-cache,
        // which forgets each event's mutex key by exact name (no pattern
        // scanning needed) and works against whatever store is configured.
        if (!method_exists($storeImpl, 'connection')) {
            $this->line("Store does not support key scanning ({$storeClass}) — falling back to schedule:clear-cache.");
            if ($dryRun) {
                $this->info('DRY-RUN: would run `php artisan schedule:clear-cache`.');
                return self::SUCCESS;
            }
            $exit = $this->call('schedule:clear-cache');
            if ($exit === self::SUCCESS) {
                $this->info('Cleared scheduler mutex locks via schedule:clear-cache.');
                Log::info('schedule:clear-locks fell back to schedule:clear-cache (non-Redis store)');
            } else {
                $this->error('schedule:clear-cache failed.');
            }
            return $exit;
        }

        try {
            $redis  = $storeImpl->connection();
            $prefix = method_exists($storeImpl, 'getPrefix') ? $storeImpl->getPrefix() : '';
            $fullPattern = $prefix . $pattern;

            // Use SCAN (cursor-based) to avoid blocking Redis on large keyspaces
            $keys   = $this->scanKeys($redis, $fullPattern);
            $count  = count($keys);

            if ($count === 0) {
                $this->info('No matching keys found. No locks to clear.');
                return self::SUCCESS;
            }

            $this->line("Found {$count} matching key(s):");
            foreach (array_slice($keys, 0, 30) as $k) {
                // Strip the prefix from display for readability
                $display = $prefix && strpos($k, $prefix) === 0
                    ? substr($k, strlen($prefix))
                    : $k;
                $this->line("  • {$display}");
            }
            if ($count > 30) {
                $this->line('  • ... (' . ($count - 30) . ' more)');
            }

            if ($dryRun) {
                $this->line('');
                $this->info('DRY-RUN complete. Re-run without --dry-run to clear.');
                return self::SUCCESS;
            }

            // Delete in batches of 100 to avoid timeouts on the DEL command
            $deleted = 0;
            foreach (array_chunk($keys, 100) as $batch) {
                $deleted += (int) $redis->del($batch);
            }
            $this->line('');
            $this->info("Cleared {$deleted} schedule lock key(s).");
            Log::info("schedule:clear-locks cleared {$deleted} keys matching {$pattern}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            Log::error('schedule:clear-locks failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    /**
     * Use Redis SCAN cursor to find keys matching the pattern.
     * Avoids the blocking KEYS command on large keyspaces.
     *
     * @return string[]
     */
    private function scanKeys($redis, string $pattern): array
    {
        $keys   = [];
        $cursor = '0';
        // Strip "redis" PhpRedis prefix injection (we already pre-pended it above)
        if (method_exists($redis, '_prefix')) {
            $internalPrefix = $redis->_prefix('');
            if ($internalPrefix && strpos($pattern, $internalPrefix) === 0) {
                $pattern = substr($pattern, strlen($internalPrefix));
            }
        }
        do {
            $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 200]);
            if ($result === false) {
                break;
            }
            // PhpRedis returns [cursor, keys]; Predis returns [cursor, keys] too
            [$cursor, $batch] = is_array($result) && isset($result[0]) ? $result : [$cursor, []];
            if (!empty($batch)) {
                $keys = array_merge($keys, $batch);
            }
        } while ($cursor !== '0' && $cursor !== 0);

        return array_values(array_unique($keys));
    }
}
