<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use AlphaDirect\Services\RealPayBillingDateSynchroniser;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Find policies whose RealPay debit day no longer matches the billing date
 * configured in Graphite — the MIS2026213635 population.
 *
 * WHY THIS EXISTS
 *   RealPay holds the instalment schedule and debits on InstalmentActionDate by
 *   itself. Editing a policy's billing date wrote policies.billingStartDate and
 *   nothing else, so every such edit since RealPay went live silently left the
 *   contract collecting on the old day. RealPayBillingDateSynchroniser stops
 *   new divergence; this finds the ones already out there.
 *
 * WHAT IT COMPARES
 *   policies.billingStartDate (day of month) against the day of month of the
 *   next ACTIVE realpay_contract_installments row on the policy's live
 *   contract. The instalment rows are written from RealPay's own responses, so
 *   their day is what will actually be debited. customer_banking.billing_day is
 *   deliberately NOT the comparison basis — it is the mirror the edit path was
 *   failing to update, so it agrees with the stale schedule, not the policy.
 *
 *   Month-end is normalised: a policy billed on the 29th/30th/31st collects on
 *   the last day of a short month, and that is a match, not a drift.
 *
 * SAFETY MODEL — same as the other RealPay commands
 *   - report-only by default; --fix required before anything is queued
 *   - --fix QUEUES an instalment move (realpay_logs event 3) per policy; it
 *     never cancels or creates a contract, so it cannot produce a duplicate
 *     debit order, and the synchroniser skips a move that is already queued
 *   - --limit caps policies per run; a corrected policy leaves the population
 *     once the hourly cron applies the move, so re-running is idempotent
 *   - CSV of every decision under storage/app/realpay-billing-date-audit/
 *
 * USAGE
 *   php artisan realpay:audit-billing-dates                        (report)
 *   php artisan realpay:audit-billing-dates --policy=MIS2026213635 --fix
 *   php artisan realpay:audit-billing-dates --limit=100 --fix
 */
class AuditRealpayBillingDates extends Command
{
    protected $signature = 'realpay:audit-billing-dates
        {--policy= : single policy id or policyNumber}
        {--status=1 : policy statuses to audit, comma separated (1 = active)}
        {--limit=0 : max policies to act on (0 = no limit)}
        {--fix : queue the correction for each mismatch. Omit for a report-only run.}
        {--report= : CSV output path (default storage/app/realpay-billing-date-audit/<ts>.csv)}
        {--no-report : suppress the CSV}';

    protected $description = 'Report (and optionally correct) policies whose RealPay debit day differs from their configured billing date.';

    /** @var resource|null */
    private $fh = null;

    private int $matched = 0;
    private int $drifted = 0;
    private int $queued = 0;
    private int $unknown = 0;

    public function handle(): int
    {
        $fix   = (bool) $this->option('fix');
        $limit = (int) $this->option('limit');

        $mode = $fix ? 'FIX (queueing schedule moves)' : 'REPORT-ONLY (nothing queued)';
        $this->info('RealPay billing-date audit — ' . $mode);

        $rows = $this->population();
        $this->line('  candidates : ' . count($rows) . ' policy/contract pair(s) with a live contract');
        $this->line('');

        if ($rows === []) {
            $this->info('No policies with a live RealPay contract in scope.');
            return 0;
        }

        if (! $this->openReport()) {
            return 1;
        }

        $sync    = app(RealPayBillingDateSynchroniser::class);
        $acted   = 0;

        foreach ($rows as $row) {
            $configuredDay = $this->dayOfMonth($row->billingStartDate);
            $scheduledDay  = $this->dayOfMonth($row->next_action_date);

            if ($configuredDay === null || $scheduledDay === null) {
                $this->unknown++;
                $this->writeRow($row, 'UNKNOWN', $configuredDay, $scheduledDay, 'missing billing date or no active instalment');
                continue;
            }

            if ($this->daysMatch($configuredDay, $scheduledDay, $row->next_action_date)) {
                $this->matched++;
                continue;
            }

            $this->drifted++;
            $this->warn(sprintf(
                '  %-20s configured day %02d, RealPay collects day %02d (next %s)',
                $row->policyNumber,
                $configuredDay,
                $scheduledDay,
                $row->next_action_date
            ));

            if (! $fix) {
                $this->writeRow($row, 'DRIFT', $configuredDay, $scheduledDay, 'report-only');
                continue;
            }

            if ($limit > 0 && $acted >= $limit) {
                $this->writeRow($row, 'DRIFT', $configuredDay, $scheduledDay, 'skipped — run limit reached');
                continue;
            }

            $policy = Policy::find($row->id);

            if (! $policy) {
                $this->writeRow($row, 'DRIFT', $configuredDay, $scheduledDay, 'policy row vanished');
                continue;
            }

            $result = $sync->syncForPolicy($policy, (string) $row->billingStartDate, 'audit-command');
            $acted++;

            if (! empty($result['synced'])) {
                $this->queued++;
                $this->line('      → ' . $result['message']);
                $this->writeRow($row, 'QUEUED', $configuredDay, $scheduledDay, $result['message']);
            } else {
                $this->writeRow($row, 'FIX-FAILED', $configuredDay, $scheduledDay, $result['message']);
            }
        }

        $this->closeReport();

        $this->line('');
        $this->info(sprintf(
            'Done. matched=%d drifted=%d queued=%d unknown=%d',
            $this->matched,
            $this->drifted,
            $this->queued,
            $this->unknown
        ));

        Log::info(RealPayBillingDateSynchroniser::LOG . 'audit finished', [
            'fix'      => $fix,
            'matched'  => $this->matched,
            'drifted'  => $this->drifted,
            'queued'   => $this->queued,
            'unknown'  => $this->unknown,
        ]);

        // Non-zero while customers are still being debited on the wrong day, so
        // a scheduled report-only run surfaces in monitoring.
        return $this->drifted > $this->queued ? 1 : 0;
    }

    /**
     * Every in-scope policy that holds a live contract, joined to the day
     * RealPay will next debit it.
     *
     * The next action date is the earliest ACTIVE instalment on that contract:
     * a correlated subquery rather than a join, so a contract with dozens of
     * instalments still yields exactly one row per policy.
     *
     * @return array<int, object>
     */
    private function population(): array
    {
        $single   = trim((string) $this->option('policy'));
        $statuses = array_values(array_filter(array_map('intval', array_map('trim', explode(',', (string) $this->option('status'))))));

        $q = DB::table('policies as p')
            ->join('realpay_client_contracts as rcc', function ($j) {
                $j->on('rcc.policy_id', '=', 'p.id')->where('rcc.status', '=', 1);
            })
            ->whereNotNull('p.billingStartDate')
            ->select([
                'p.id',
                'p.policyNumber',
                'p.billingStartDate',
                'rcc.contract_number',
                // Single-quoted, not double: double quotes are identifiers under
                // ANSI_QUOTES (and in SQLite), which would silently turn the
                // literal 'A' into a column reference.
                DB::raw("(SELECT MIN(rci.InstalmentActionDate)
                            FROM realpay_contract_installments rci
                           WHERE rci.contractNumber = rcc.contract_number
                             AND rci.InstalmentStatus = 'A'
                             AND rci.InstalmentActionDate IS NOT NULL) as next_action_date"),
            ]);

        if ($single !== '') {
            $q->where(function ($w) use ($single) {
                $w->where('p.policyNumber', $single);
                if (ctype_digit($single)) {
                    $w->orWhere('p.id', (int) $single);
                }
            });
        } elseif ($statuses !== []) {
            $q->whereIn('p.status', $statuses);
        }

        return $q->orderBy('p.id')->get()->all();
    }

    /**
     * Month-end tolerance: a policy billed on the 31st legitimately collects on
     * the 28th/29th/30th in a short month, and RealPay's own CollectionDay 99
     * ("last day") produces exactly that. Treat a scheduled date that IS the
     * last day of its month as matching any configured day at or beyond it.
     */
    private function daysMatch(int $configuredDay, int $scheduledDay, ?string $scheduledDate): bool
    {
        if ($configuredDay === $scheduledDay) {
            return true;
        }

        if ($configuredDay < 28 || empty($scheduledDate)) {
            return false;
        }

        try {
            $date = Carbon::parse($scheduledDate);
        } catch (\Throwable $e) {
            return false;
        }

        return $scheduledDay === $date->daysInMonth && $configuredDay >= $scheduledDay;
    }

    private function dayOfMonth($date): ?int
    {
        if (empty($date)) {
            return null;
        }

        try {
            $raw = (string) $date;

            return (int) (strpos($raw, '/') !== false
                ? Carbon::createFromFormat('d/m/Y', $raw)->format('d')
                : Carbon::parse($raw)->format('d'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Report
    // ──────────────────────────────────────────────────────────────────

    private function openReport(): bool
    {
        if ($this->option('no-report')) {
            return true;
        }

        $path = (string) $this->option('report');

        if ($path === '') {
            $dir = storage_path('app/realpay-billing-date-audit');
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error('Could not create the report directory: ' . $dir);
                return false;
            }
            $path = $dir . DIRECTORY_SEPARATOR . Carbon::now()->format('Ymd_His') . '.csv';
        }

        $this->fh = @fopen($path, 'w');

        if ($this->fh === false) {
            $this->fh = null;
            $this->error('Could not open the report file: ' . $path);
            return false;
        }

        fputcsv($this->fh, [
            'policy_id', 'policy_number', 'contract_number', 'billing_start_date',
            'configured_day', 'realpay_next_date', 'realpay_day', 'outcome', 'message',
        ]);
        $this->line('  report     : ' . $path);

        return true;
    }

    private function writeRow($row, string $outcome, ?int $configuredDay, ?int $scheduledDay, string $message): void
    {
        if ($this->fh === null) {
            return;
        }

        fputcsv($this->fh, [
            $row->id,
            $row->policyNumber,
            $row->contract_number,
            $row->billingStartDate,
            $configuredDay,
            $row->next_action_date,
            $scheduledDay,
            $outcome,
            $message,
        ]);
    }

    private function closeReport(): void
    {
        if ($this->fh !== null) {
            fclose($this->fh);
            $this->fh = null;
        }
    }
}
