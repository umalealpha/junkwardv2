<?php

namespace AlphaDirect\Jobs;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Services\BackdatedEndorse\EndorseRefreshRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs "Refresh Endorsement" off the HTTP request.
 *
 * The rebuild (clear + re-replicate the full coverage tree into every
 * downstream action) runs for minutes on a large policy — longer than any
 * web-server / load-balancer timeout — so doing it synchronously in the
 * request made the browser hang and the gateway kill the connection mid-way,
 * leaving a half-rebuilt tree. Here it runs on the queue with a long timeout,
 * writing progress to a status file the FE can poll.
 *
 * Status lives in storage/app (EFS-shared between the web node and the queue
 * worker) so file cache — which is per-node here — is avoided.
 */
class RefreshEndorseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A single rebuild of a huge policy can take several minutes. */
    public $timeout = 3600;

    /** Rebuild is destructive (hard-delete + re-replicate); never auto-retry. */
    public $tries = 1;

    /**
     * How long a 'running' status is trusted as a live worker. Generous on
     * purpose: the heartbeat lands once per downstream action, and one action of
     * a huge policy can take minutes — a tight window would let a second worker
     * barge into a healthy rebuild, which is the failure this guards against.
     */
    private const HEARTBEAT_STALE_SECONDS = 600;

    protected int $policyId;
    protected int $sourceActionId;
    protected ?int $userId;

    /**
     * Optional inclusive upper bound (action id). When set, the rebuild only
     * touches actions effective on/before this action — the "Refresh
     * Endorsement Range" (from → to) case. Null = refresh all downstream.
     */
    protected ?int $toActionId;

    /**
     * 'rebuild' (default, destructive hard-clear + re-copy), 'fill_missing'
     * (non-destructive — only add rows the target lacks; an ISSUED endorse
     * target's pro-rata stays sealed), 'fill_missing_reprice' (same, but an
     * ISSUED endorse target is repriced and its invoice moved in place), or the
     * coverage-selective 'coverage_forward' / 'coverage_drop' modes (act on
     * $coverageIds only; RENEW/ANNIVERSARY repriced, others sealed).
     */
    protected string $mode;

    /**
     * When true the refresh touches ONLY the To action ($toActionId), not the
     * whole from → to span — the "Refresh To Only" case. Requires $toActionId.
     */
    protected bool $onlyTarget;

    /**
     * Coverage ids acted on by the coverage_forward / coverage_drop modes.
     * Null / empty for every other mode.
     */
    protected ?array $coverageIds;

    public function __construct(int $policyId, int $sourceActionId, ?int $userId = null, ?int $toActionId = null, string $mode = 'rebuild', bool $onlyTarget = false, ?array $coverageIds = null)
    {
        $this->policyId       = $policyId;
        $this->sourceActionId = $sourceActionId;
        $this->userId         = $userId;
        $this->toActionId     = $toActionId;
        $this->mode           = $mode;
        $this->onlyTarget     = $onlyTarget;
        $this->coverageIds    = $coverageIds;
    }

    /** Status file path for a policy's refresh — polled by the FE. */
    public static function statusPath(int $policyId): string
    {
        return 'endorse-refresh/' . $policyId . '.json';
    }

    /**
     * START a refresh WITHOUT depending on a queue worker being alive.
     *
     * Why this exists: dispatch() only parks the job on the configured queue
     * connection. On PROD the consuming worker has repeatedly not been the one
     * draining that connection (supervisord pins `queue:work redis` while
     * QUEUE_CONNECTION is database; ECS task defs only add a dedicated worker
     * for the smartuw queue), so the status file sat at 'queued' forever and
     * the operator got "no worker has picked it up" — the refresh never ran.
     *
     * So the PRIMARY trigger is now the same detached-CLI kick the V2 Quote PDF
     * path already relies on (see PolicyCreateController::generateV2QuoteSheet
     * and ProcessPdfJobs::spawnJobsDetached): spawn `php artisan
     * endorse:refresh-run …` as its own background process. It survives the
     * php-fpm request ending, has no execution-time limit, and needs no worker.
     * Queue dispatch remains only as the fallback when the shell functions are
     * unavailable (disable_functions), and a failed spawn is re-kicked by
     * self::reviveIfStalled() — driven by the FE's own status poll, so the
     * recovery path depends on NO scheduler entry and no queue worker.
     *
     * The queued status payload echoes the run arguments so the revive can
     * relaunch the exact same refresh.
     *
     * @return string 'cli' when a background worker was spawned, 'queue' when it fell back to dispatch()
     */
    public static function start(
        int $policyId,
        int $sourceActionId,
        ?int $userId = null,
        ?int $toActionId = null,
        string $mode = 'rebuild',
        bool $onlyTarget = false,
        ?array $coverageIds = null,
        string $message = 'Refresh queued…'
    ): string {
        $coverageIds = array_values(array_unique(array_filter(array_map('intval', $coverageIds ?? []))));

        self::writeStatus($policyId, [
            'status'           => 'queued',
            'source_action_id' => $sourceActionId,
            'to_action_id'     => $toActionId,
            'done'             => 0,
            'total'            => 0,
            'message'          => $message,
            // Run arguments — read back by self::reviveIfStalled() so a refresh
            // whose worker never started can be relaunched identically.
            'mode'             => $mode,
            'only_target'      => $onlyTarget,
            'coverage_ids'     => $coverageIds,
            'user_id'          => $userId,
            'queued_at'        => now()->toIso8601String(),
            'spawn_attempts'   => 1,
            'last_spawn_at'    => now()->toIso8601String(),
        ]);

        if (self::kickDetached($policyId, $sourceActionId, $userId, $toActionId, $mode, $onlyTarget, $coverageIds)) {
            return 'cli';
        }

        // Shell spawn unavailable — fall back to the queue so a worker (if one
        // IS running) still picks it up.
        Log::warning("RefreshEndorseJob: CLI kick unavailable for policy {$policyId} — falling back to queue dispatch.");
        self::dispatch($policyId, $sourceActionId, $userId, $toActionId, $mode, $onlyTarget, $coverageIds ?: null);

        return 'queue';
    }

    /**
     * Spawn `endorse:refresh-run` as a detached background process.
     *
     * Returns false when the platform's spawn function is unavailable, so the
     * caller can fall back to the queue. A true return only means the process
     * was launched — if it dies before writing status:'running' the
     * endorse:refresh-pending backstop re-kicks it.
     */
    public static function kickDetached(
        int $policyId,
        int $sourceActionId,
        ?int $userId = null,
        ?int $toActionId = null,
        string $mode = 'rebuild',
        bool $onlyTarget = false,
        array $coverageIds = []
    ): bool {
        // ⚠️ NEVER use PHP_BINARY here: under php-fpm it resolves to the FPM
        // SAPI binary, which cannot run artisan — and with output sent to
        // /dev/null the failure is invisible (root cause of the 2026-06-10 PDF
        // pile-up). Resolve a CLI binary: env override → which-discovery →
        // platform default.
        $cliPhp = getenv('PHP_CLI_BINARY') ?: (DIRECTORY_SEPARATOR === '\\'
            ? (trim((string) strtok((string) @shell_exec('where php'), "\r\n")) ?: 'php')
            : (trim((string) @shell_exec('command -v php')) ?: '/usr/local/bin/php'));

        $parts = [
            escapeshellarg($cliPhp),
            escapeshellarg(base_path('artisan')),
            'endorse:refresh-run',
            escapeshellarg((string) $policyId),
            escapeshellarg((string) $sourceActionId),
            '--mode=' . escapeshellarg($mode),
        ];
        if ($toActionId)          $parts[] = '--to=' . escapeshellarg((string) $toActionId);
        if ($onlyTarget)          $parts[] = '--only-target';
        if (!empty($coverageIds)) $parts[] = '--coverages=' . escapeshellarg(implode(',', $coverageIds));
        if ($userId)              $parts[] = '--user=' . escapeshellarg((string) $userId);
        $cmd = implode(' ', $parts);

        try {
            if (DIRECTORY_SEPARATOR === '\\') {
                if (!function_exists('popen') || !function_exists('pclose')) return false;
                // Windows (local dev) — start /B detaches from this process.
                pclose(popen("start /B \"\" {$cmd}", 'r'));
            } else {
                if (!function_exists('shell_exec')) return false;
                // Linux (prod) — nohup survives the php-fpm request ending;
                // full output redirection releases the handle so this returns
                // instantly instead of blocking the HTTP response.
                shell_exec("nohup {$cmd} > /dev/null 2>&1 &");
            }
            Log::info("RefreshEndorseJob: spawned detached worker for policy {$policyId} (source {$sourceActionId}, mode {$mode}).");
            return true;
        } catch (\Throwable $e) {
            Log::warning("RefreshEndorseJob: detached spawn failed for policy {$policyId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * BACKSTOP: relaunch a refresh still sitting at 'queued' because its worker
     * never started, and return the status the caller should report.
     *
     * ⚠️ This deliberately does NOT live in the scheduler. `endorse:refresh-pending`
     * would have needed a hardcoded Kernel::schedule() entry (the DB-driven
     * scheduler depends on `cron_kernels`, absent in prod), and we cannot rely on
     * that tick firing at all. So the trigger is the FE's OWN status poll —
     * PolicyCreateController::refreshEndorseStatus() calls this on every poll.
     * The operator's browser is the heartbeat: it polls every 3s for as long as
     * the operator is waiting, which is exactly when recovery matters. No cron,
     * no queue worker, no supervisord.
     *
     * Safe to call on every poll:
     *   - Only rows at status 'queued' are touched. A live worker flips to
     *     'running' as the first act of handle(), so a healthy run is skipped.
     *   - A row is only re-kicked once its last spawn is $staleSeconds old, so
     *     3s polling still yields at most one kick per window.
     *   - A cache lock makes the decision atomic, so two browser tabs (or a tab
     *     plus the manual command) polling in the same instant cannot both spawn.
     *   - After $maxAttempts the refresh is parked at 'failed' rather than being
     *     retried forever — the rebuild is destructive.
     *
     * With the defaults the spawns land at ~0s / 45s / 90s and the run is parked
     * at 'failed' by ~135s, inside the FE's 150s give-up window, so the operator
     * always sees a real verdict instead of a spinner.
     *
     * @return array the status payload to report (unchanged when nothing was done)
     */
    public static function reviveIfStalled(
        int $policyId,
        array $status,
        int $staleSeconds = 45,
        int $maxAttempts = 3
    ): array {
        if (($status['status'] ?? null) !== 'queued') {
            return $status;
        }

        // Only act once the previous spawn has had time to write 'running'.
        // Plain timestamp compare — diffInSeconds()' sign convention varies
        // across Carbon versions and getting it backwards here would either
        // never re-kick or re-kick a healthy worker.
        $lastSpawn = $status['last_spawn_at'] ?? $status['queued_at'] ?? $status['updated_at'] ?? null;
        if ($lastSpawn) {
            try {
                if (\Carbon\Carbon::parse($lastSpawn)->getTimestamp() > now()->getTimestamp() - $staleSeconds) {
                    return $status; // still inside the grace window
                }
            } catch (\Throwable $e) {
                // Unparseable timestamp — treat as stale and re-kick.
            }
        }

        // Atomic claim so concurrent pollers can't each spawn a worker. A short
        // TTL: this guards the decision only, never the run itself, so a crashed
        // poller cannot block recovery for more than one window.
        $lock = null;
        try {
            $lock = Cache::lock('endorse-refresh-revive:' . $policyId, max(10, $staleSeconds));
            if (!$lock->get()) {
                return $status; // another poller is deciding right now
            }
        } catch (\Throwable $e) {
            // No lock driver — fall through unlocked rather than never recovering.
            $lock = null;
        }

        try {
            $sourceId = (int) ($status['source_action_id'] ?? 0);

            // A pre-existing status file (written before this path shipped) has no
            // run arguments, so it cannot be relaunched — tell the operator to
            // click Refresh again instead of leaving a dead 'queued' screen.
            if ($sourceId <= 0) {
                Log::warning("RefreshEndorseJob::reviveIfStalled: policy {$policyId} queued with no source action — parked as failed.");
                return self::writeStatusAndReturn($policyId, array_merge($status, [
                    'status'  => 'failed',
                    'message' => 'Refresh did not start and could not be restarted automatically. Please click Refresh again.',
                ]));
            }

            $attempts = (int) ($status['spawn_attempts'] ?? 0);
            if ($attempts >= $maxAttempts) {
                Log::error("RefreshEndorseJob::reviveIfStalled: policy {$policyId} parked as failed after {$attempts} spawn attempts.");
                return self::writeStatusAndReturn($policyId, array_merge($status, [
                    'status'  => 'failed',
                    'message' => "Refresh worker failed to start after {$attempts} attempts. Nothing was changed on the policy — please retry, or contact IT if it repeats.",
                ]));
            }

            $attempt = $attempts + 1;
            $status = self::writeStatusAndReturn($policyId, array_merge($status, [
                'status'         => 'queued',
                'spawn_attempts' => $attempt,
                'last_spawn_at'  => now()->toIso8601String(),
                'message'        => "Starting refresh worker (attempt {$attempt})…",
            ]));

            $ok = self::kickDetached(
                $policyId,
                $sourceId,
                isset($status['user_id']) ? (int) $status['user_id'] : null,
                isset($status['to_action_id']) ? (int) $status['to_action_id'] : null,
                (string) ($status['mode'] ?? 'rebuild'),
                (bool) ($status['only_target'] ?? false),
                array_map('intval', (array) ($status['coverage_ids'] ?? []))
            );

            if ($ok) {
                Log::warning("RefreshEndorseJob::reviveIfStalled: re-kicked policy {$policyId} (source {$sourceId}, attempt {$attempt}).");
            } else {
                // Shell spawn unavailable on this host — the queue is the only
                // remaining route, so hand it over rather than looping.
                self::dispatch(
                    $policyId,
                    $sourceId,
                    isset($status['user_id']) ? (int) $status['user_id'] : null,
                    isset($status['to_action_id']) ? (int) $status['to_action_id'] : null,
                    (string) ($status['mode'] ?? 'rebuild'),
                    (bool) ($status['only_target'] ?? false),
                    !empty($status['coverage_ids']) ? array_map('intval', (array) $status['coverage_ids']) : null
                );
                Log::warning("RefreshEndorseJob::reviveIfStalled: CLI spawn unavailable — dispatched policy {$policyId} to the queue instead.");
            }

            return $status;
        } finally {
            if ($lock) {
                try { $lock->release(); } catch (\Throwable $e) { /* TTL will clear it */ }
            }
        }
    }

    /** writeStatus() + the payload it wrote, so callers can report it straight back. */
    private static function writeStatusAndReturn(int $policyId, array $data): array
    {
        self::writeStatus($policyId, $data);

        return array_merge($data, [
            'policy_id'  => $policyId,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /** Read the status payload for a policy, or [] when there is none. */
    public static function readStatus(int $policyId): array
    {
        try {
            $path = self::statusPath($policyId);
            if (Storage::disk('local')->exists($path)) {
                $decoded = json_decode((string) Storage::disk('local')->get($path), true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
            Log::warning('RefreshEndorseJob: could not read status file: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Write/merge the status payload the FE polls.
     *
     * `updated_at` is stamped LAST, not merged as a default: callers that carry a
     * whole previously-read status forward (reviveIfStalled) would otherwise let
     * the stale timestamp win over now(), freezing the field the FE and the
     * staleness check read.
     */
    public static function writeStatus(int $policyId, array $data): void
    {
        $payload = array_merge(['policy_id' => $policyId], $data, [
            'updated_at' => now()->toIso8601String(),
        ]);
        try {
            Storage::disk('local')->put(self::statusPath($policyId), json_encode($payload));
        } catch (\Throwable $e) {
            Log::warning('RefreshEndorseJob: could not write status file: ' . $e->getMessage());
        }
    }

    public function handle(): void
    {
        // Back off if another worker is already rebuilding this policy. The
        // rebuild hard-clears and re-replicates the coverage tree, so two
        // concurrent runs on one policy can interleave into a half-built tree.
        //
        // The check is the status file, not a lock: 'running' is heartbeated by
        // the progress callback below on every action, so a worker that was
        // OOM-killed goes stale on its own and never blocks a retry — whereas a
        // held lock would have to outlive the longest rebuild (an hour) and would
        // strand the policy for that long if its holder died.
        $live = self::readStatus($this->policyId);
        if (($live['status'] ?? null) === 'running' && !empty($live['updated_at'])) {
            try {
                $beat = \Carbon\Carbon::parse($live['updated_at'])->getTimestamp();
                if ($beat > now()->getTimestamp() - self::HEARTBEAT_STALE_SECONDS) {
                    Log::warning("RefreshEndorseJob: policy {$this->policyId} is already being refreshed (last heartbeat {$live['updated_at']}) — this worker is standing down.");
                    return;
                }
            } catch (\Throwable $e) {
                // Unparseable heartbeat — fall through and run.
            }
        }

        $source = PolicyAction::where('id', $this->sourceActionId)
            ->where('policy_id', $this->policyId)
            ->first();

        if (!$source) {
            self::writeStatus($this->policyId, [
                'status'  => 'failed',
                'message' => 'Source action not found.',
            ]);
            return;
        }

        // Optional inclusive upper bound for a bounded (from → to) refresh.
        $until = null;
        if ($this->toActionId) {
            $until = PolicyAction::where('id', $this->toActionId)
                ->where('policy_id', $this->policyId)
                ->first();
            if (!$until) {
                self::writeStatus($this->policyId, [
                    'status'  => 'failed',
                    'message' => 'To-action not found.',
                ]);
                return;
            }
        }

        // Generic action verb per mode for the progress/status messages.
        $verb = match ($this->mode) {
            'fill_missing'     => 'Filled',
            'fill_missing_reprice' => 'Filled + repriced',
            'coverage_forward' => 'Forwarded coverage(s) into',
            'coverage_drop'    => 'Dropped coverage(s) from',
            'fill_missing_backward' => 'Backfilled',
            default            => 'Refreshed',
        };
        $startMessage = match ($this->mode) {
            'fill_missing'     => 'Filling missing coverages into downstream actions…',
            'fill_missing_reprice' => 'Filling missing coverages into downstream actions and repricing them…',
            'coverage_forward' => 'Forwarding selected coverage(s) into downstream actions…',
            'coverage_drop'    => 'Dropping selected coverage(s) from downstream actions…',
            'fill_missing_backward' => 'Backfilling the later action into the earlier one (append-only)…',
            default            => 'Rebuilding downstream actions…',
        };

        self::writeStatus($this->policyId, [
            'status'            => 'running',
            'source_action_id'  => $this->sourceActionId,
            'to_action_id'      => $this->toActionId,
            'done'              => 0,
            'total'             => 0,
            'message'           => $startMessage,
        ]);

        try {
            $result = (new EndorseRefreshRunner())->run(
                $this->policyId,
                $source,
                function (int $done, int $total) use ($verb) {
                    self::writeStatus($this->policyId, [
                        'status'           => 'running',
                        'source_action_id' => $this->sourceActionId,
                        'to_action_id'     => $this->toActionId,
                        'done'             => $done,
                        'total'            => $total,
                        'message'          => "{$verb} {$done} of {$total} downstream action(s)…",
                    ]);
                },
                $until,
                $this->mode,
                $this->onlyTarget,
                $this->coverageIds ?? []
            );

            self::writeStatus($this->policyId, array_merge($result, [
                'status'           => 'completed',
                'source_action_id' => $this->sourceActionId,
                'done'             => $result['total'],
                'message'          => "Refresh complete — {$result['refreshed']} downstream action(s) updated"
                    . ($result['failed'] ? ", {$result['failed']} failed (see logs)" : '') . '.',
            ]));

            try {
                $log = activity('Policy Refreshed');
                if ($this->userId && ($causer = \AlphaDirect\User::find($this->userId))) {
                    $log->causedBy($causer);
                }
                $log->log("Refreshed policy {$this->policyId} from action {$this->sourceActionId} — "
                    . "{$result['refreshed']} updated, {$result['renew_invoices']} RENEW invoice(s) regenerated"
                    . ($result['failed'] ? ", {$result['failed']} failed" : ''));
            } catch (\Throwable $e) {
                Log::warning('RefreshEndorseJob: activity log failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('RefreshEndorseJob failed', [
                'policy_id' => $this->policyId,
                'source_id' => $this->sourceActionId,
                'error'     => $e->getMessage(),
            ]);
            self::writeStatus($this->policyId, [
                'status'  => 'failed',
                'message' => 'Refresh failed: ' . $e->getMessage(),
            ]);
        }
    }
}
