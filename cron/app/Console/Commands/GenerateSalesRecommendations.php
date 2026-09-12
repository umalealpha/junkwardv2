<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

/**
 * SQL to create the sales_recommendations table:
 *
 * CREATE TABLE sales_recommendations (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     agent_id INT NOT NULL,
 *     customer_id INT NOT NULL,
 *     customer_name VARCHAR(200),
 *     current_product_id INT,
 *     current_product_name VARCHAR(100),
 *     recommended_product_id INT,
 *     recommended_product_name VARCHAR(100),
 *     reason VARCHAR(500),
 *     priority ENUM('high','medium','low') DEFAULT 'medium',
 *     status ENUM('pending','contacted','converted','dismissed') DEFAULT 'pending',
 *     created_at TIMESTAMP NULL,
 *     updated_at TIMESTAMP NULL,
 *     INDEX idx_agent (agent_id, status),
 *     INDEX idx_customer (customer_id)
 * ) ENGINE=InnoDB;
 */

class GenerateSalesRecommendations extends Command
{
    protected $signature = 'sales:generate-recommendations {--dry-run : Read-only mode, no DB writes}';
    protected $description = 'Nightly sales recommendations engine — generates cross-sell/upsell recommendations per agent';

    private $dryRun = false;
    private $recommendations = [];
    private $totalGenerated = 0;
    private $limit = 500;

    /** Product cross-sell mapping */
    private $crossSellMap = [
        // Motor → Domestic
        1 => ['id' => 3, 'reason' => 'Motor customer with good payment history — recommend Domestic cover'],
        // Motor Comprehensive → Domestic
        2 => ['id' => 3, 'reason' => 'Motor Comprehensive customer with good payment history — recommend Domestic cover'],
        // Domestic → Motor Comprehensive
        3 => ['id' => 2, 'reason' => 'Domestic customer with good payment history — recommend Motor Comprehensive cover'],
    ];

    public function handle()
    {
        $this->dryRun = $this->option('dry-run');

        // Track cron status
        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create([
                    'name' => 'sales:generate-recommendations',
                    'start' => now(),
                ]);
            } catch (\Exception $e) {
                // CronStatus tracking is optional
            }
        }

        $this->info("========================================");
        $this->info("SALES RECOMMENDATIONS ENGINE" . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info("Started: " . now());
        $this->info("========================================\n");

        if ($this->dryRun) {
            $this->warn("[DRY RUN] No data will be written to the database.\n");
        }

        try {
            // Clear stale pending recommendations older than 30 days
            $this->clearStaleRecommendations();

            // Load products lookup
            $products = DB::table('products')->pluck('name', 'id')->toArray();

            // Generate recommendations
            $this->singleProductCrossSell($products);
            $this->renewalUpsell($products);
            $this->lapsedCustomerReengagement($products);

            // Bulk insert collected recommendations
            $this->flushRecommendations();

            $this->printSummary();

            // Update cron status
            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }

            return 0;

        } catch (\Exception $e) {
            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }
            $this->error("FAILED: " . $e->getMessage());
            Log::error('GenerateSalesRecommendations failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Clear old pending recommendations older than 30 days.
     */
    private function clearStaleRecommendations()
    {
        $cutoff = Carbon::now()->subDays(30);
        $count = DB::table('sales_recommendations')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count > 0) {
            if (!$this->dryRun) {
                DB::table('sales_recommendations')
                    ->where('status', 'pending')
                    ->where('created_at', '<', $cutoff)
                    ->delete();
            }
            $this->info("Cleared {$count} stale pending recommendations (older than 30 days).\n");
        }
    }

    /**
     * Rule 1: Single-product customers with good payment history (6+ months on-time).
     * Recommend complementary product. Priority: high.
     */
    private function singleProductCrossSell(array $products)
    {
        $this->info("--- Single-Product Cross-Sell ---");

        if ($this->hasReachedLimit()) return;

        $sixMonthsAgo = Carbon::now()->subMonths(6);

        // Find customers with exactly 1 active policy, created 6+ months ago
        $singlePolicyCustomers = DB::table('policies as p')
            ->select([
                'p.customer_id',
                'p.agent_id',
                'p.product_id',
                'p.id as policy_id',
                'p.policyNumber',
                DB::raw("CONCAT(c.firstName, ' ', c.lastName) as customer_name"),
            ])
            ->join('customer as c', 'c.id', '=', 'p.customer_id')
            ->where('p.status', 1)
            ->where('p.created_at', '<=', $sixMonthsAgo)
            ->whereIn('p.customer_id', function ($q) {
                $q->select('customer_id')
                    ->from('policies')
                    ->where('status', 1)
                    ->groupBy('customer_id')
                    ->havingRaw('COUNT(DISTINCT product_id) = 1');
            })
            ->get();

        $this->info("Found {$singlePolicyCustomers->count()} single-product customers with 6+ month tenure.");

        $goodPaymentCount = 0;

        foreach ($singlePolicyCustomers as $row) {
            if ($this->hasReachedLimit()) break;

            // Check payment history — at least 6 successful payments
            $successfulPayments = DB::table('payment_transactions')
                ->where('policy_id', $row->policy_id)
                ->whereIn('status', ['SUCCESS', 'Paid'])
                ->where('is_reverse', 0)
                ->count();

            if ($successfulPayments < 6) {
                continue;
            }

            $goodPaymentCount++;

            // Cross-sell from map
            if (isset($this->crossSellMap[$row->product_id])) {
                $rec = $this->crossSellMap[$row->product_id];

                // Check customer doesn't already have the recommended product
                $alreadyHas = DB::table('policies')
                    ->where('customer_id', $row->customer_id)
                    ->where('product_id', $rec['id'])
                    ->where('status', 1)
                    ->exists();

                if (!$alreadyHas) {
                    $this->addRecommendation([
                        'agent_id' => $row->agent_id,
                        'customer_id' => $row->customer_id,
                        'customer_name' => $row->customer_name,
                        'current_product_id' => $row->product_id,
                        'current_product_name' => $products[$row->product_id] ?? 'Unknown',
                        'recommended_product_id' => $rec['id'],
                        'recommended_product_name' => $products[$rec['id']] ?? 'Unknown',
                        'reason' => $rec['reason'],
                        'priority' => 'high',
                    ]);
                }
            }

            // Always recommend Legal Insurance (product_id=4) if they don't have it
            if ($row->product_id != 4) {
                $hasLegal = DB::table('policies')
                    ->where('customer_id', $row->customer_id)
                    ->where('product_id', 4)
                    ->where('status', 1)
                    ->exists();

                if (!$hasLegal) {
                    $this->addRecommendation([
                        'agent_id' => $row->agent_id,
                        'customer_id' => $row->customer_id,
                        'customer_name' => $row->customer_name,
                        'current_product_id' => $row->product_id,
                        'current_product_name' => $products[$row->product_id] ?? 'Unknown',
                        'recommended_product_id' => 4,
                        'recommended_product_name' => $products[4] ?? 'Legal Insurance',
                        'reason' => 'Loyal customer with good payment history — recommend Legal Insurance add-on',
                        'priority' => 'high',
                    ]);
                }
            }
        }

        $this->info("Customers with good payment history: {$goodPaymentCount}");
        $this->info("Recommendations queued so far: {$this->totalGenerated}\n");
    }

    /**
     * Rule 2: Renewal opportunities — policies expiring within 30 days.
     * Recommend renewal + upsell. Priority: high.
     */
    private function renewalUpsell(array $products)
    {
        $this->info("--- Renewal Upsell Opportunities ---");

        if ($this->hasReachedLimit()) return;

        $now = Carbon::now();
        $thirtyDaysOut = Carbon::now()->addDays(30);

        $expiringPolicies = DB::table('policies as p')
            ->select([
                'p.customer_id',
                'p.agent_id',
                'p.product_id',
                'p.id as policy_id',
                'p.policyNumber',
                'p.expiry_date',
                DB::raw("CONCAT(c.firstName, ' ', c.lastName) as customer_name"),
            ])
            ->join('customer as c', 'c.id', '=', 'p.customer_id')
            ->where('p.status', 1)
            ->whereBetween('p.expiry_date', [$now, $thirtyDaysOut])
            ->get();

        $this->info("Found {$expiringPolicies->count()} policies expiring within 30 days.");

        foreach ($expiringPolicies as $row) {
            if ($this->hasReachedLimit()) break;

            $daysLeft = Carbon::parse($row->expiry_date)->diffInDays($now);
            $productName = $products[$row->product_id] ?? 'Unknown';

            $this->addRecommendation([
                'agent_id' => $row->agent_id,
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'current_product_id' => $row->product_id,
                'current_product_name' => $productName,
                'recommended_product_id' => $row->product_id,
                'recommended_product_name' => $productName,
                'reason' => "Policy {$row->policyNumber} expires in {$daysLeft} days — contact for renewal and upsell opportunity",
                'priority' => 'high',
            ]);
        }

        $this->info("Recommendations queued so far: {$this->totalGenerated}\n");
    }

    /**
     * Rule 3: Lapsed customers — cancelled 3+ months ago, no active policy.
     * Recommend re-engagement with same product. Priority: medium.
     */
    private function lapsedCustomerReengagement(array $products)
    {
        $this->info("--- Lapsed Customer Re-engagement ---");

        if ($this->hasReachedLimit()) return;

        $threeMonthsAgo = Carbon::now()->subMonths(3);

        // Customers who had a cancelled policy 3+ months ago and have NO active policy now
        $lapsedCustomers = DB::table('policies as p')
            ->select([
                'p.customer_id',
                'p.agent_id',
                'p.product_id',
                DB::raw("CONCAT(c.firstName, ' ', c.lastName) as customer_name"),
            ])
            ->join('customer as c', 'c.id', '=', 'p.customer_id')
            ->where('p.status', 2)
            ->where('p.updated_at', '<=', $threeMonthsAgo)
            ->whereNotIn('p.customer_id', function ($q) {
                $q->select('customer_id')
                    ->from('policies')
                    ->where('status', 1);
            })
            ->groupBy('p.customer_id', 'p.agent_id', 'p.product_id', 'customer_name')
            ->get();

        $this->info("Found {$lapsedCustomers->count()} lapsed customers (cancelled 3+ months, no active policy).");

        foreach ($lapsedCustomers as $row) {
            if ($this->hasReachedLimit()) break;

            $productName = $products[$row->product_id] ?? 'Unknown';

            $this->addRecommendation([
                'agent_id' => $row->agent_id,
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'current_product_id' => $row->product_id,
                'current_product_name' => $productName,
                'recommended_product_id' => $row->product_id,
                'recommended_product_name' => $productName,
                'reason' => "Lapsed customer — previously had {$productName}, cancelled 3+ months ago. Re-engagement opportunity",
                'priority' => 'medium',
            ]);
        }

        $this->info("Recommendations queued so far: {$this->totalGenerated}\n");
    }

    /**
     * Add a recommendation to the buffer.
     */
    private function addRecommendation(array $data)
    {
        if ($this->hasReachedLimit()) return;

        $data['status'] = 'pending';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $this->recommendations[] = $data;
        $this->totalGenerated++;

        // Flush in batches of 100
        if (count($this->recommendations) >= 100) {
            $this->flushRecommendations();
        }
    }

    /**
     * Flush buffered recommendations to the database.
     */
    private function flushRecommendations()
    {
        if (empty($this->recommendations)) return;

        $count = count($this->recommendations);

        if (!$this->dryRun) {
            // Insert in chunks to avoid packet size issues
            foreach (array_chunk($this->recommendations, 50) as $chunk) {
                DB::table('sales_recommendations')->insert($chunk);
            }
            $this->info("Inserted {$count} recommendations.");
        } else {
            $this->info("[DRY RUN] Would insert {$count} recommendations.");
        }

        $this->recommendations = [];
    }

    /**
     * Check if we've reached the per-run limit.
     */
    private function hasReachedLimit(): bool
    {
        return $this->totalGenerated >= $this->limit;
    }

    /**
     * Print final summary.
     */
    private function printSummary()
    {
        $this->info("\n========================================");
        $this->info("SALES RECOMMENDATIONS — SUMMARY");
        $this->info("========================================");
        $this->info("Total recommendations generated: {$this->totalGenerated}");
        $this->info("Limit per run: {$this->limit}");
        $this->info("Completed: " . now());
        $this->info("========================================\n");
    }
}
