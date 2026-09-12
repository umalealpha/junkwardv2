<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\RealPayController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drains the `webhook_buffer` table by replaying each buffered webhook through
 * its real handler.
 *
 * Why this exists
 * ---------------
 * The WebhookBuffer middleware captures incoming webhooks (returns a fast 200
 * so the provider never times out) and stores them with status='pending'. But
 * NOTHING was ever consuming the buffer — so on the V2 cut-over (2026-06-04)
 * RealPay installment notifications began piling up unprocessed (tens of
 * thousands, all 'pending'). Symptoms: "transaction log not up to date"
 * (GRA-0054) and "RealPay webhooks not received". This command is the missing
 * consumer.
 *
 * Safety
 * ------
 * - IDEMPOTENT replay: RealPayController::updateInstallment matches the
 *   installment by InstalmentReferenceNumber and upserts the payment via
 *   updatePaymentTransactions(referenceNumber). Re-applying a payload re-sets
 *   the same state and never duplicates a payment — so re-runs are safe.
 * - LOAD-FRIENDLY: processes a small batch per run (default 100), oldest first.
 *   Schedule it withoutOverlapping so runs never stack; a large backlog is
 *   sipped over many runs rather than one spike.
 * - Each row is isolated in its own try/catch — one bad payload never blocks
 *   the rest. Failures increment `attempts` and retry until --max-attempts,
 *   then are parked as 'failed' for inspection (never silently dropped).
 *
 * Only `realpay` is buffered today (the single route carrying webhook.buffer).
 * Unknown sources are left 'pending' rather than guessing a handler.
 *
 * Usage:
 *   php artisan webhook:process-buffer                 # 100 oldest pending
 *   php artisan webhook:process-buffer --batch=25      # gentle backlog drain
 *   php artisan webhook:process-buffer --source=realpay
 */
class ProcessWebhookBuffer extends Command
{
    protected $signature = 'webhook:process-buffer
        {--batch=100 : Max rows to process this run}
        {--source= : Limit to a single source (e.g. realpay)}
        {--max-attempts=8 : Park a row as failed after this many tries}';

    protected $description = 'Replay pending webhook_buffer rows through their real handlers (idempotent, batched).';

    public function handle(): int
    {
        $batch       = max(1, (int) $this->option('batch'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $source      = $this->option('source');

        $query = DB::connection('mysql_write')->table('webhook_buffer')
            ->where('status', 'pending')
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id');
        if (!empty($source)) {
            $query->where('source', $source);
        }

        $rows = $query->limit($batch)->get();
        if ($rows->isEmpty()) {
            $this->info('webhook_buffer: nothing pending to process.');
            return self::SUCCESS;
        }

        $processed = 0;
        $retried   = 0;
        $failed    = 0;

        foreach ($rows as $row) {
            $attempts = (int) $row->attempts + 1;
            try {
                $payload = json_decode((string) $row->payload, true);
                if (!is_array($payload)) {
                    throw new \RuntimeException('payload is not valid JSON');
                }

                $ok = $this->replay((string) $row->source, $payload);

                if ($ok) {
                    $this->markRow($row->id, 'processed', $attempts);
                    $processed++;
                } elseif ($attempts >= $maxAttempts) {
                    $this->markRow($row->id, 'failed', $attempts);
                    $failed++;
                } else {
                    $this->markRow($row->id, 'pending', $attempts);
                    $retried++;
                }
            } catch (\Throwable $e) {
                $status = $attempts >= $maxAttempts ? 'failed' : 'pending';
                $this->markRow($row->id, $status, $attempts);
                $status === 'failed' ? $failed++ : $retried++;
                Log::error("ProcessWebhookBuffer: row {$row->id} (source={$row->source}) failed on attempt {$attempts}: " . $e->getMessage());
            }
        }

        $this->info("webhook_buffer drain: processed={$processed} retry={$retried} failed={$failed} (batch={$batch}).");

        // A parked row is an unapplied payment notification. Surface it — this
        // is the signal that used to be invisible because failures were being
        // marked 'processed'. Cross-check with `realpay:reconcile-reflection`.
        if ($failed > 0) {
            Log::error("webhook_buffer: {$failed} row(s) parked as 'failed' after {$maxAttempts} attempts — these payment notifications were NOT applied. Run `php artisan realpay:reconcile-reflection` to see the financial impact.", [
                'source' => $source ?: 'all',
                'failed' => $failed,
            ]);
        }

        return self::SUCCESS;
    }

    private function markRow(int $id, string $status, int $attempts): void
    {
        DB::connection('mysql_write')->table('webhook_buffer')
            ->where('id', $id)
            ->update([
                'status'     => $status,
                'attempts'   => $attempts,
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Replay one buffered webhook through its real handler.
     *
     * Returns true ONLY when the handler reports that it applied the payload.
     * Anything else leaves the row for retry and, after --max-attempts, parks it
     * as 'failed' where it is visible.
     *
     * THE BUG THIS FIXES (RealPay debits with no Graphite transaction):
     * this used to `return $statusCode < 500`. updateInstallment()'s own catch
     * returns HTTP **401** on any exception, and its unresolved-policy paths
     * return no Response at all (treated as 200). Both counted as success, so
     * the row was marked 'processed' and the delivery consumed — a RealPay
     * collection that had already debited the customer was discarded with no
     * retry and no trace. A handler that did not apply the payload must never
     * be recorded as having applied it, so only 2xx now counts, and a 4xx is
     * treated as retryable rather than done.
     */
    private function replay(string $source, array $payload): bool
    {
        $request = Request::create('/', 'POST');
        $request->replace($payload);

        switch ($source) {
            case 'realpay':
                $response = app(RealPayController::class)->updateInstallment($request);

                if (!is_object($response) || !method_exists($response, 'getStatusCode')) {
                    // No Response object means the handler fell off one of its
                    // silent early-return paths without applying anything.
                    Log::warning('ProcessWebhookBuffer: realpay handler returned no response — treating as unapplied.', [
                        'client_number'   => $payload['InstalmentGetResponse'][0]['ClientNumber'] ?? null,
                        'contract_number' => $payload['InstalmentGetResponse'][0]['ContractNumber'] ?? null,
                        'reference'       => $payload['InstalmentGetResponse'][0]['InstalmentReferenceNumber'] ?? null,
                    ]);

                    return false;
                }

                $statusCode = (int) $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    return true;
                }

                Log::warning("ProcessWebhookBuffer: realpay handler returned HTTP {$statusCode} — not applied, will retry.", [
                    'reference' => $payload['InstalmentGetResponse'][0]['InstalmentReferenceNumber'] ?? null,
                    'status'    => $payload['InstalmentGetResponse'][0]['InstalmentStatus'] ?? null,
                ]);

                return false;

            default:
                Log::warning("ProcessWebhookBuffer: no handler mapped for source '{$source}' — left pending.");
                return false;
        }
    }
}
