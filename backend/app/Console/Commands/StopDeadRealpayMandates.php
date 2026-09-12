<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\RealpayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rule A (CFO instruction 10 Aug 2026): a cancelled or deactivated policy must
 * stop debiting the customer — automatically, no manual step.
 *
 * WHY A RECONCILING COMMAND, NOT AN OBSERVER HOOK
 *   RealPay holds the instalment schedule and debits on InstalmentActionDate
 *   by itself — Graphite is never asked. Flipping our local contract flag
 *   stops nothing; only the RealPay DELETE (RealpayService::
 *   cancelContractByNumber) stops money. And policy status is written from
 *   many places — Eloquent saves, query-builder bulk fixes, one-off commands —
 *   several of which bypass PolicyObserver entirely. A scheduled reconciler
 *   that continuously compares "policies that are dead" against "contracts
 *   that will still debit" catches every write path, past and future,
 *   including the ~30k backlog this was built to sweep.
 *
 * WHAT COUNTS AS "WILL STILL DEBIT"
 *   The contract has at least one instalment with InstalmentStatus 'A'
 *   (active/queued) dated after today. The local realpay_client_contracts
 *   .status flag is deliberately NOT trusted — three different writers use
 *   three different conventions for it.
 *
 * THE RULE B CARVE-OUT (critical safety rule)
 *   A "dead" policy whose contract COLLECTED money recently is not a dead
 *   mandate — it is a paying customer wrongly switched off (the 2,197 case of
 *   10-11 Aug). Killing its mandate would end a live customer's cover
 *   permanently. So any contract with a successful ('S') instalment inside
 *   --quiet-days (default 90) is NEVER cancelled: it is reported as a
 *   reactivation candidate (Rule B) — REACTIVATE-REVIEW for deactivated
 *   policies, REFUND-REVIEW for cancelled ones (money taken after
 *   cancellation). Finance instruction of 10 Aug says exactly this: "ignore
 *   any policies with 'collected something in the past 3 months'".
 *
 * SAFETY MODEL — same as mis:deactivate-nonpaying / mis:reactivate-paying
 *   - report-only by default; --apply required to call RealPay
 *   - per-contract CSV report + Laravel-log summary on every run
 *   - --limit caps RealPay cancellations per run (backlog drains across runs;
 *     a cancelled contract loses its 'A' instalments and leaves the
 *     population, so the command is idempotent and failures self-retry)
 *   - platform (legacy vs START merchant) chosen per policy product via
 *     RealpayService::platformForProduct — the creating merchant is the only
 *     one that can cancel a contract
 *
 * USAGE
 *   php artisan realpay:stop-dead-mandates                     (report-only)
 *   php artisan realpay:stop-dead-mandates --limit=100 --apply (cancel batch)
 *   php artisan realpay:stop-dead-mandates --policy=MIS2021012345 --apply
 *
 * Scheduled hourly in Kernel; RealPay-side cancellation is armed by
 * REALPAY_MANDATE_STOP_APPLY=true (config/realpay.php) — until then the
 * scheduled run is report-only.
 */
class StopDeadRealpayMandates extends Command
{
    protected $signature = 'realpay:stop-dead-mandates
        {--policy= : single policy id or policyNumber}
        {--status=0,2 : policy statuses treated as dead (0=deactivated, 2=cancelled)}
        {--quiet-days=90 : a contract that collected within this many days is NEVER cancelled — reported for Rule B review instead}
        {--limit=100 : max RealPay cancellations per run (0 = no limit)}
        {--chunk=500 : contracts loaded per DB batch}
        {--apply : actually cancel on RealPay. Omit for a report-only run.}
        {--report= : CSV output path (default storage/app/realpay-mandate-stop/<ts>.csv)}';

    protected $description = 'Rule A: cancel RealPay mandates still debiting on cancelled/deactivated policies (report-only by default; recent collectors are ring-fenced for Rule B).';

    private bool $apply;
    private int $quietDays;

    /** contractNumber => count of future 'A' instalments. */
    private array $futureA = [];
    /** contractNumber => last successful collection date within the quiet window. */
    private array $recentS = [];
    /** contractNumber => true — processed already this run (dedupe supersede rows). */
    private array $seen = [];
    /** platform => bearer token (lazy). */
    private array $tokens = [];

    /** @var resource|null */
    private $fh = null;

    private array $tally = [];
    private int $cancelled = 0;

    public function handle()
    {
        $this->apply     = (bool) $this->option('apply');
        $this->quietDays = max(1, (int) $this->option('quiet-days'));
        $limit           = (int) $this->option('limit');
        $chunk           = max(100, (int) $this->option('chunk'));

        $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('status'))), 'strlen'));
        $statuses = array_map('intval', $statuses);
        if ($statuses === [] || array_diff($statuses, [0, 2, 3]) !== []) {
            $this->error('--status may only contain 0, 2 and/or 3.');
            return 1;
        }
        if (in_array(1, $statuses, true)) {
            $this->error('Refusing: status 1 (active) can never be in the dead set.');
            return 1;
        }

        if (! $this->openReport()) {
            return 1;
        }

        $mode = $this->apply ? 'APPLY (CANCELLING ON REALPAY)' : 'REPORT-ONLY (no RealPay calls)';
        $this->info("RealPay dead-mandate stop — {$mode}");
        $this->line('  dead statuses : ' . implode(',', $statuses));
        $this->line("  quiet window  : {$this->quietDays} days (recent collectors ring-fenced)");
        $this->line('  cancel limit  : ' . ($limit > 0 ? $limit : 'none'));
        $this->line('');

        Log::info('realpay:stop-dead-mandates started', [
            'mode' => $this->apply ? 'apply' : 'report-only',
            'statuses' => $statuses, 'quiet_days' => $this->quietDays, 'limit' => $limit,
        ]);

        $this->loadInstalmentSets();

        $singlePolicyId = null;
        if ($ref = $this->option('policy')) {
            $p = DB::table('policies')->where('id', $ref)->orWhere('policyNumber', $ref)->first();
            if (! $p) {
                $this->error("Policy not found: {$ref}");
                $this->closeReport();
                return 1;
            }
            $singlePolicyId = (int) $p->id;
        }

        $processed = 0;
        $lastId = 0;

        while (true) {
            $q = DB::table('realpay_client_contracts as c')
                ->join('policies as p', 'p.id', '=', 'c.policy_id')
                ->select('c.id as contract_row_id', 'c.policy_id', 'c.client_number', 'c.contract_number',
                    'c.status as contract_status', 'p.policyNumber', 'p.status as policy_status', 'p.product_id')
                ->where('c.id', '>', $lastId)
                ->orderBy('c.id');

            if ($singlePolicyId !== null) {
                $q->where('c.policy_id', $singlePolicyId);
            } else {
                $q->whereIn('p.status', $statuses);
            }

            $rows = $q->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = $row->contract_row_id;
                $this->processOne($row, $statuses);
                $processed++;

                if ($limit > 0 && $this->cancelled >= $limit) {
                    $this->warn("Cancel limit of {$limit} reached — remaining contracts pick up next run.");
                    break 2;
                }
            }

            $this->line("  ... {$processed} contracts examined");
        }

        $this->closeReport();
        $this->renderTally($processed);

        return 0;
    }

    // ---------------------------------------------------------- pre-loads

    /**
     * Two set-based queries answer "which contracts will still debit" and
     * "which contracts collected recently" for the entire run — the same
     * 4-queries-not-66,000 approach as mis:deactivate-nonpaying.
     */
    private function loadInstalmentSets(): void
    {
        $this->line('Loading instalment sets...');

        $today = Carbon::now()->format('Y-m-d');
        foreach (DB::table('realpay_contract_installments')
                     ->select('contractNumber', DB::raw('COUNT(*) as n'))
                     ->where('InstalmentStatus', 'A')
                     ->where('InstalmentActionDate', '>', $today)
                     ->groupBy('contractNumber')
                     ->cursor() as $r) {
            $this->futureA[$r->contractNumber] = (int) $r->n;
        }
        $this->line('  contracts with future debits : ' . count($this->futureA));

        $cutoff = Carbon::now()->subDays($this->quietDays)->format('Y-m-d');
        foreach (DB::table('realpay_contract_installments')
                     ->select('contractNumber', DB::raw('MAX(InstalmentActionDate) as last_s'))
                     ->where('InstalmentStatus', 'S')
                     ->where('InstalmentActionDate', '>=', $cutoff)
                     ->groupBy('contractNumber')
                     ->cursor() as $r) {
            $this->recentS[$r->contractNumber] = (string) $r->last_s;
        }
        $this->line('  contracts that collected in the quiet window : ' . count($this->recentS));
        $this->line('');
    }

    // ---------------------------------------------------------- per contract

    private function processOne(object $row, array $statuses): void
    {
        $cn = trim((string) $row->contract_number);

        if ($cn === '' || trim((string) $row->client_number) === '') {
            $this->record($row, 'SKIPPED', 'malformed contract row (missing client/contract number)');
            return;
        }

        if (isset($this->seen[$cn])) {
            $this->record($row, 'SKIPPED', 'duplicate row for a contract already handled this run');
            return;
        }
        $this->seen[$cn] = true;

        if (! in_array((int) $row->policy_status, $statuses, true)) {
            // Only reachable in --policy mode: report the state, touch nothing.
            $this->record($row, 'SKIPPED', 'policy status ' . $row->policy_status . ' not in the dead set');
            return;
        }

        if (! isset($this->futureA[$cn])) {
            $this->record($row, 'SKIPPED', 'no future debits queued — mandate already dead');
            return;
        }

        // Rule B carve-out — a collecting contract is a paying customer,
        // never a mandate to kill.
        if (isset($this->recentS[$cn])) {
            $outcome = (int) $row->policy_status === 2 ? 'REFUND-REVIEW' : 'REACTIVATE-REVIEW';
            $this->record($row, $outcome,
                'collected ' . $this->recentS[$cn] . ' (within ' . $this->quietDays . 'd) — Rule B candidate, NOT cancelled');
            return;
        }

        if (! $this->apply) {
            $this->record($row, 'WOULD-CANCEL', $this->futureA[$cn] . ' future debit(s), quiet ' . $this->quietDays . 'd+');
            return;
        }

        $platform = RealpayService::platformForProduct($row->product_id);
        $token = $this->tokenFor($platform);
        if ($token === null) {
            $this->record($row, 'ERROR', "no RealPay token for platform '{$platform}' — skipped");
            return;
        }

        $svc = (new RealpayService())->usePlatform($platform);
        $res = $svc->cancelContractByNumber($token, (string) $row->client_number, $cn, (int) $row->policy_id);

        if (! empty($res['cancelled'])) {
            // cancelContractByNumber already flipped the local contract row,
            // set remaining 'A' instalments to 'I' and cancelled the mandate.
            $this->cancelled++;
            unset($this->futureA[$cn]);
            Log::info('realpay:stop-dead-mandates cancelled', [
                'policy_id' => $row->policy_id, 'policyNumber' => $row->policyNumber,
                'contract' => $cn, 'platform' => $platform,
            ]);
            $this->record($row, 'CANCELLED', 'RealPay confirmed — future debits stopped');
        } else {
            Log::warning('realpay:stop-dead-mandates cancel failed', [
                'policy_id' => $row->policy_id, 'contract' => $cn,
                'platform' => $platform, 'error' => $res['error'] ?? null, 'http' => $res['status'] ?? null,
            ]);
            $this->record($row, 'FAILED', (string) ($res['error'] ?? 'RealPay did not confirm') . ' — retries next run');
        }

        usleep(250000); // 4 calls/sec max — be a polite API citizen
    }

    private function tokenFor(string $platform): ?string
    {
        if (! array_key_exists($platform, $this->tokens)) {
            $this->tokens[$platform] = (new RealpayService())->usePlatform($platform)->getAccessToken();
            if ($this->tokens[$platform] === null) {
                Log::error('realpay:stop-dead-mandates token fetch failed', ['platform' => $platform]);
            }
        }
        return $this->tokens[$platform];
    }

    // ------------------------------------------------------------------ io

    private function openReport(): bool
    {
        $path = $this->option('report');
        if (! $path) {
            $dir = storage_path('app/realpay-mandate-stop');
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error("Cannot create report directory: {$dir}");
                return false;
            }
            $path = $dir . '/mandate-stop-'
                . ($this->apply ? 'apply' : 'report') . '-'
                . Carbon::now()->format('Ymd-His') . '.csv';
        }

        $this->fh = @fopen($path, 'w');
        if (! $this->fh) {
            $this->error("Cannot open report path: {$path}");
            return false;
        }

        fputcsv($this->fh, [
            'policy_id', 'policyNumber', 'policy_status', 'product_id', 'platform',
            'client_number', 'contract_number', 'future_debits', 'last_collection_in_window',
            'outcome', 'reason', 'run_at',
        ]);
        $this->info("Report: {$path}");
        return true;
    }

    private function record(object $row, string $outcome, string $reason): void
    {
        $this->tally[$outcome] = ($this->tally[$outcome] ?? 0) + 1;

        if ($this->fh) {
            $cn = (string) $row->contract_number;
            fputcsv($this->fh, [
                $row->policy_id,
                $row->policyNumber,
                $row->policy_status,
                $row->product_id,
                RealpayService::platformForProduct($row->product_id),
                $row->client_number,
                $cn,
                $this->futureA[$cn] ?? 0,
                $this->recentS[$cn] ?? '',
                $outcome,
                $reason,
                Carbon::now()->toDateTimeString(),
            ]);
        }

        if ($this->option('policy')) {
            $this->line("  {$row->policyNumber}  {$row->contract_number}  {$outcome}  — {$reason}");
        }
    }

    private function closeReport(): void
    {
        if ($this->fh) {
            fclose($this->fh);
            $this->fh = null;
        }
    }

    private function renderTally(int $processed): void
    {
        $this->line('');
        $this->info("Contracts examined: {$processed}");
        foreach ($this->tally as $outcome => $count) {
            $this->line(sprintf('  %-18s %6d', $outcome, $count));
        }

        Log::info('realpay:stop-dead-mandates finished', [
            'mode' => $this->apply ? 'apply' : 'report-only',
            'examined' => $processed,
            'tally' => $this->tally,
        ]);

        if (! $this->apply) {
            $this->line('');
            $this->warn('REPORT-ONLY — no RealPay calls were made. Re-run with --apply to cancel.');
        }
    }
}
