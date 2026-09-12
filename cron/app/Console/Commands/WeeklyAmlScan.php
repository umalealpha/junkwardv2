<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

class WeeklyAmlScan extends Command
{
    protected $signature = 'aml:weekly-scan {--dry-run} {--limit=500}';
    protected $description = 'Weekly AML/sanctions screening of active customers via OpenSanctions API';

    private $dryRun = false;
    private $scanned = 0;
    private $matches = 0;
    private $clean = 0;
    private $errors = 0;
    private $skipped = 0;

    public function handle()
    {
        $this->dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        // Track cron status
        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create([
                    'name' => 'aml:weekly-scan',
                    'start' => now(),
                ]);
            } catch (\Exception $e) {
                // CronStatus tracking is optional
            }
        }

        $this->info("========================================");
        $this->info("AML/SANCTIONS WEEKLY SCAN" . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info("Started: " . now());
        $this->info("Limit: {$limit} customers");
        $this->info("========================================\n");

        try {
            $customers = $this->getCustomersToScan($limit);

            if ($customers->isEmpty()) {
                $this->info('No customers to scan. All active customers have been scanned within the last 7 days.');
                $this->finishCron($cronStatus);
                return 0;
            }

            $this->info("Customers to scan: " . $customers->count() . "\n");

            foreach ($customers as $customer) {
                $this->processCustomer($customer);
            }

            $this->printSummary();
            $this->finishCron($cronStatus);

            return 0;

        } catch (\Exception $e) {
            $this->error("FATAL ERROR: " . $e->getMessage());
            Log::error('WeeklyAmlScan fatal error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->finishCron($cronStatus);
            return 1;
        }
    }

    /**
     * Get active customers who haven't been scanned in the last 7 days
     * and are not already blocked.
     */
    private function getCustomersToScan(int $limit)
    {
        return DB::table('customer')
            ->select([
                'customer.id',
                'customer.firstName',
                'customer.lastName',
                'customer.idNumber',
                'customer.is_blocked',
                'customer.cellphone',
                'customer.email',
            ])
            ->join('policies', 'policies.customer_id', '=', 'customer.id')
            ->where('policies.status', 1)
            ->where(function ($q) {
                $q->whereNull('customer.is_blocked')
                  ->orWhere('customer.is_blocked', '0')
                  ->orWhere('customer.is_blocked', '');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('customer_aml_verification_log')
                  ->whereRaw('customer_aml_verification_log.customer_id = customer.id')
                  ->where('customer_aml_verification_log.searched_on', '>=', Carbon::now()->subDays(7));
            })
            ->groupBy(
                'customer.id',
                'customer.firstName',
                'customer.lastName',
                'customer.idNumber',
                'customer.is_blocked',
                'customer.cellphone',
                'customer.email'
            )
            ->limit($limit)
            ->get();
    }

    /**
     * Process a single customer: build names, call API, handle result.
     */
    private function processCustomer($customer)
    {
        try {
            // Skip if already blocked
            if ($customer->is_blocked && $customer->is_blocked !== '0') {
                $this->skipped++;
                return;
            }

            $fullName = trim($customer->firstName . ' ' . $customer->lastName);
            if (empty($fullName) || strlen($fullName) < 2) {
                $this->skipped++;
                $this->warn("  Skipping customer #{$customer->id} — no valid name.");
                return;
            }

            // Build search names: primary name + aliases
            $searchNames = [$fullName];

            try {
                $aliases = DB::table('customer_aliases')
                    ->where('customer_id', $customer->id)
                    ->pluck('alias_name')
                    ->filter()
                    ->toArray();

                foreach ($aliases as $alias) {
                    $alias = trim($alias);
                    if (!empty($alias) && !in_array($alias, $searchNames)) {
                        $searchNames[] = $alias;
                    }
                }
            } catch (\Exception $e) {
                // customer_aliases table may not exist — continue with primary name only
            }

            $this->line("  Scanning #{$customer->id} {$fullName} (" . count($searchNames) . " name(s))...");

            $highestScore = 0;
            $matchedName = null;
            $apiResponse = null;

            foreach ($searchNames as $name) {
                try {
                    $response = $this->callOpenSanctionsApi($name);
                    $results = data_get($response, 'responses.q1.results', []);
                    $maxScore = collect($results)->pluck('score')->max() ?? 0;

                    if ($maxScore > $highestScore) {
                        $highestScore = $maxScore;
                        $matchedName = $name;
                        $apiResponse = $response;
                    }

                    // Rate limit: max 2 calls/second -> sleep 500ms between calls
                    usleep(500000);

                } catch (\Exception $e) {
                    $this->errors++;
                    $this->warn("    API error for name '{$name}': " . $e->getMessage());
                    Log::error('WeeklyAmlScan API error', [
                        'customer_id' => $customer->id,
                        'name' => $name,
                        'error' => $e->getMessage(),
                    ]);
                    // Sleep before retrying next name to avoid hammering API after error
                    usleep(500000);
                    continue;
                }
            }

            $this->scanned++;

            if ($highestScore > 0.7) {
                // Match found — block customer
                $this->matches++;
                $blockReason = "AML sanctions match (weekly scan): {$matchedName}";
                $this->error("    MATCH: score={$highestScore} name='{$matchedName}'");

                if (!$this->dryRun) {
                    DB::table('customer')
                        ->where('id', $customer->id)
                        ->update([
                            'is_blocked' => '1',
                            'block_reason' => $blockReason,
                            'updated_at' => now(),
                        ]);

                    $this->logAmlVerification($customer->id, true, $apiResponse, $matchedName, $highestScore);
                }
            } else {
                // Clean — update searched_on
                $this->clean++;

                if (!$this->dryRun) {
                    $this->logAmlVerification($customer->id, false, $apiResponse, null, $highestScore);
                }
            }

        } catch (\Exception $e) {
            $this->errors++;
            $this->warn("  Error processing customer #{$customer->id}: " . $e->getMessage());
            Log::error('WeeklyAmlScan customer error', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Call OpenSanctions match API — mirrors OpenSanctionsClient.php pattern.
     */
    private function callOpenSanctionsApi(string $name): array
    {
        $baseUrl = rtrim(config('services.opensanctions.base', 'https://api.opensanctions.org'), '/');
        $apiKey = config('services.opensanctions.key');

        $payload = [
            'queries' => [
                'q1' => [
                    'schema' => 'Person',
                    'properties' => [
                        'name' => [$name],
                    ],
                ],
            ],
        ];

        $request = Http::timeout(20)->acceptJson();

        if ($apiKey) {
            $request = $request->withToken($apiKey);
        }

        return $request
            ->post($baseUrl . '/match/default', $payload)
            ->throw()
            ->json();
    }

    /**
     * Log result to customer_aml_verification_log table.
     */
    private function logAmlVerification(int $customerId, bool $isMatch, ?array $response, ?string $matchedName, float $score)
    {
        $existing = DB::table('customer_aml_verification_log')
            ->where('customer_id', $customerId)
            ->first();

        $logData = [
            'is_aml_verification_done' => $isMatch ? 1 : 0,
            'aml_verification_response' => json_encode([
                'source' => 'opensanctions_weekly_scan',
                'score' => $score,
                'matched_name' => $matchedName,
                'response_summary' => $response ? [
                    'total_results' => count(data_get($response, 'responses.q1.results', [])),
                ] : null,
            ]),
            'searched_on' => now(),
        ];

        if ($existing) {
            DB::table('customer_aml_verification_log')
                ->where('customer_id', $customerId)
                ->update($logData);
        } else {
            $logData['customer_id'] = $customerId;
            DB::table('customer_aml_verification_log')->insert($logData);
        }
    }

    /**
     * Print run summary.
     */
    private function printSummary()
    {
        $this->info("\n========================================");
        $this->info("AML WEEKLY SCAN — SUMMARY");
        $this->info("========================================");
        $this->info("  Total scanned:   {$this->scanned}");
        $this->error("  Matches found:   {$this->matches}");
        $this->info("  Already clean:   {$this->clean}");
        $this->warn("  Errors:          {$this->errors}");
        $this->info("  Skipped:         {$this->skipped}");
        $this->info("  Completed:       " . now());
        $this->info("========================================\n");

        Log::info('WeeklyAmlScan completed', [
            'scanned' => $this->scanned,
            'matches' => $this->matches,
            'clean' => $this->clean,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
        ]);
    }

    /**
     * Finish cron status tracking.
     */
    private function finishCron(?CronStatus $cronStatus)
    {
        if ($cronStatus) {
            try {
                $cronStatus->update(['end' => now()]);
            } catch (\Exception $e) {
                // CronStatus tracking is optional
            }
        }
    }
}
