<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use AlphaDirect\Models\CronStatus;

class ProcessWebhookBuffer extends Command
{
    protected $signature = 'webhook:process
        {--source=all : Process specific source (realpay, dpo, ngenius, all)}
        {--batch=50 : Records per batch}
        {--max-batches=100 : Safety limit on total batches per run}
        {--pause=200 : Milliseconds pause between batches}
        {--dry-run : Preview without processing}';

    protected $description = 'Process buffered webhook responses in controlled batches — replays through original handlers';

    private int $processed = 0;
    private int $failed = 0;

    public function handle()
    {
        $source     = $this->option('source');
        $batchSize  = (int) $this->option('batch');
        $maxBatches = (int) $this->option('max-batches');
        $pauseMs    = (int) $this->option('pause');
        $dryRun     = $this->option('dry-run');

        $cronStatus = null;
        if (!$dryRun) {
            try {
                $cronStatus = CronStatus::create([
                    'name'  => 'webhook:process --source=' . $source,
                    'start' => now(),
                ]);
            } catch (\Exception $e) {}
        }

        $this->info("WEBHOOK BUFFER PROCESSOR" . ($dryRun ? ' [DRY RUN]' : ''));
        $this->info("Source: {$source} | Batch: {$batchSize} | Pause: {$pauseMs}ms\n");

        // Pending count
        $pendingQuery = DB::table('webhook_buffer')->where('status', 'pending')->where('attempts', '<', 3);
        if ($source !== 'all') $pendingQuery->where('source', $source);
        $pending = $pendingQuery->count();
        $this->info("Pending: {$pending}\n");

        if ($pending === 0) {
            $this->info("Nothing to process.");
            if ($cronStatus) $cronStatus->update(['end' => now()]);
            return 0;
        }

        $batchNum = 0;

        while ($batchNum < $maxBatches) {
            $batchNum++;

            $query = DB::table('webhook_buffer')
                ->where('status', 'pending')
                ->where('attempts', '<', 3)
                ->orderBy('id')
                ->limit($batchSize);

            if ($source !== 'all') $query->where('source', $source);

            $batch = $query->get();
            if ($batch->isEmpty()) break;

            // Lock batch
            $ids = $batch->pluck('id')->toArray();
            if (!$dryRun) {
                DB::table('webhook_buffer')->whereIn('id', $ids)
                    ->update(['status' => 'processing', 'updated_at' => now()]);
            }

            foreach ($batch as $record) {
                if ($dryRun) {
                    $this->line("  [DRY] #{$record->id} {$record->source} {$record->event_type}");
                    continue;
                }

                try {
                    $payload = json_decode($record->payload, true);
                    $this->processRecord($record, $payload);

                    DB::table('webhook_buffer')->where('id', $record->id)->update([
                        'status'       => 'done',
                        'processed_at' => now(),
                        'updated_at'   => now(),
                    ]);
                    $this->processed++;

                } catch (\Exception $e) {
                    $attempts = $record->attempts + 1;
                    DB::table('webhook_buffer')->where('id', $record->id)->update([
                        'status'     => $attempts >= 3 ? 'failed' : 'pending',
                        'attempts'   => $attempts,
                        'error'      => substr($e->getMessage(), 0, 500),
                        'updated_at' => now(),
                    ]);
                    $this->failed++;
                    Log::warning("Webhook #{$record->id} failed (attempt {$attempts}): " . $e->getMessage());
                }
            }

            $this->line("Batch {$batchNum}: processed {$this->processed}, failed {$this->failed}");

            // Controlled pace
            usleep($pauseMs * 1000);
        }

        $remaining = DB::table('webhook_buffer')->where('status', 'pending')->count();
        $this->info("\nDone. Processed: {$this->processed} | Failed: {$this->failed} | Remaining: {$remaining}");

        if ($cronStatus) $cronStatus->update(['end' => now()]);
        return 0;
    }

    /**
     * Replay the webhook through the original controller handler.
     * This preserves ALL existing business logic: policy activation, SMS, email, etc.
     */
    private function processRecord(object $record, array $payload): void
    {
        match ($record->source) {
            'realpay' => $this->processRealpay($payload),
            'dpo'     => $this->processDpo($payload),
            'ngenius' => $this->processNgenius($payload),
            default   => Log::info("Unknown webhook source: {$record->source}"),
        };
    }

    /**
     * RealPay: Replay through RealPayController::updateInstallment
     *
     * Original handler does:
     * 1. Update RealpayContractInstallments
     * 2. Look up policy, update status if first payment (S)
     * 3. Update payment_transactions
     * 4. Send SMS (payment success/failed)
     * 5. Send email for failed payments
     * 6. Store in realpay_webhook_response
     */
    private function processRealpay(array $payload): void
    {
        // Validate payload has required structure
        if (!isset($payload['InstalmentGetResponse'][0])) {
            throw new \Exception('Missing InstalmentGetResponse in payload');
        }

        // Create a fake Request object with the payload
        $request = new Request($payload);

        // Call the existing handler — preserves ALL business logic
        $controller = app(\AlphaDirect\Http\Controllers\Admin\RealPayController::class);
        $response = $controller->updateInstallment($request);

        // GRA-0203 (2026-08-26): updateInstallment reports failure by its RETURN
        // VALUE, never by throwing — its own try/catch returns HTTP 401 on any
        // internal error, and some early-return paths return no Response at all.
        // This command used to ignore that return, so a collection that was NOT
        // applied still fell through as "success", the buffer row was marked
        // 'done', and the already-debited payment was dropped with no transaction
        // and no trace (the 4-June-2026 V2-cutover recording cliff). Only a real
        // 2xx means the payload was applied; anything else must raise so the outer
        // loop retries and, after max attempts, parks the row 'failed' where it is
        // visible. Mirrors the backend copy's fix (webhook:process-buffer,
        // 2026-08-17). NOTE: the backend copy of this command carries the twin of
        // this guard — keep the two in sync.
        $reference = $payload['InstalmentGetResponse'][0]['InstalmentReferenceNumber'] ?? 'n/a';

        if (!is_object($response) || !method_exists($response, 'getStatusCode')) {
            throw new \RuntimeException("RealPay updateInstallment returned no response — payload not applied (ref={$reference})");
        }

        $statusCode = (int) $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException("RealPay updateInstallment returned HTTP {$statusCode} — payload not applied (ref={$reference})");
        }
    }

    /**
     * DPO: Replay through DpoPaymentController
     */
    private function processDpo(array $payload): void
    {
        $request = new Request($payload);

        if (isset($payload['TransactionToken'])) {
            $controller = app(\AlphaDirect\Http\Controllers\DpoPaymentController::class);
            $controller->verifyPayment($request);
        }
    }

    /**
     * N-Genius: Replay through NgeniusPaymentController
     */
    private function processNgenius(array $payload): void
    {
        $request = new Request($payload);
        $controller = app(\AlphaDirect\Http\Controllers\NgeniusPaymentController::class);
        $controller->ngeniuswebhook($request);
    }
}
